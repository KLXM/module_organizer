(function () {
    'use strict';

    function init() {
        var root = document.getElementById('mo-organizer');
        var tree = document.getElementById('mo-tree');
        if (!root || !tree) {
            return;
        }

        var modules = {};
        try {
            modules = JSON.parse(root.getAttribute('data-modules') || '{}');
        } catch (e) {
            modules = {};
        }

        var orderMsgSaved = root.getAttribute('data-order-msg-saved') || '';
        var orderMsgFailed = root.getAttribute('data-order-msg-failed') || '';
        var categoryNamePrompt = root.getAttribute('data-category-name-prompt') || '';
        var categoryDeleteConfirm = root.getAttribute('data-category-delete-confirm') || '';

        var emptyHint = document.getElementById('mo-sidebar-empty');
        var form = document.getElementById('mo-sidebar-form');
        var fieldModuleId = document.getElementById('mo-field-module-id');
        var sidebarTitle = document.getElementById('mo-sidebar-title');
        var fieldFavorite = document.getElementById('mo-field-favorite');
        var fieldDescription = document.getElementById('mo-field-description');
        var iconRadios = form ? form.querySelectorAll('input[name="mo-icon-key"]') : [];
        var saveStatus = document.getElementById('mo-save-status');

        var mediaPreview = document.getElementById('mo-icon-media-preview');
        var mediaPreviewImg = document.getElementById('mo-icon-media-preview-img');
        var mediaPreviewName = document.getElementById('mo-icon-media-preview-name');
        var mediaPickBtn = document.getElementById('mo-icon-media-pick');
        var mediaRemoveBtn = document.getElementById('mo-icon-media-remove');
        var mediaIconKey = null; // "media:<filename>" oder null

        var saveTimer = null;
        var activeModuleId = null;

        // Manche Aenderungen (Favorit-Status, Kategorie an-/umbenennen/
        // loeschen, Custom-Icon speichern/loeschen) brauchen einen echten
        // Seiten-Reload, weil serverseitig gerenderte Bereiche (Baum-Struktur,
        // Icon-Galerie) sich sonst nicht aktualisieren. Damit dabei nicht die
        // gerade bearbeitete Modul-Auswahl und die Scrollposition im Baum
        // verloren gehen, vor dem Reload kurz merken und danach wiederherstellen.
        var SELECTION_STORAGE_KEY = 'mo-organizer-selection';

        function persistSelectionAndReload() {
            try {
                sessionStorage.setItem(SELECTION_STORAGE_KEY, JSON.stringify({
                    moduleId: activeModuleId,
                    scrollTop: tree.scrollTop,
                }));
            } catch (e) {
                // sessionStorage kann in seltenen Faellen (privater Modus o.ae.)
                // nicht verfuegbar sein - Reload funktioniert dann trotzdem,
                // nur ohne Wiederherstellung.
            }
            window.location.reload();
        }

        function restoreSelectionAfterReload() {
            var raw;
            try {
                raw = sessionStorage.getItem(SELECTION_STORAGE_KEY);
                sessionStorage.removeItem(SELECTION_STORAGE_KEY);
            } catch (e) {
                return;
            }
            if (!raw) {
                return;
            }
            var state;
            try {
                state = JSON.parse(raw);
            } catch (e) {
                return;
            }
            if (state && state.moduleId && modules[state.moduleId]) {
                selectModule(state.moduleId);
            }
            if (state && 'number' === typeof state.scrollTop) {
                tree.scrollTop = state.scrollTop;
            }
        }

        // SVG-Dateien koennen NICHT ueber den Media-Manager ausgeliefert
        // werden (REDAXO-Kernverhalten) - dafuer die rohe Medienpool-URL
        // direkt nutzen. boot.php liefert sie ueber
        // rex_view::setJsProperty('module_organizer', ...), Teil des Backend-
        // globalen "rex"-JS-Objekts (siehe auch module_organizer.js).
        function mediaUrl(filename) {
            if (/\.svg$/i.test(filename)) {
                var base = (window.rex && window.rex.module_organizer && window.rex.module_organizer.mediaBaseUrl) || '';
                return base + filename;
            }
            return 'index.php?rex_media_type=rex_media_small&rex_media_file=' + encodeURIComponent(filename);
        }

        function showMediaPreview(filename) {
            if (!mediaPreview) {
                return;
            }
            mediaIconKey = 'media:' + filename;
            iconRadios.forEach(function (radio) {
                radio.checked = false;
                var label = radio.closest('.mo-icon-radio');
                if (label) {
                    label.classList.remove('is-selected');
                }
            });
            mediaPreviewImg.src = mediaUrl(filename);
            mediaPreviewName.textContent = filename;
            mediaPreview.hidden = false;
        }

        function clearMediaPreview() {
            mediaIconKey = null;
            if (mediaPreview) {
                mediaPreview.hidden = true;
                mediaPreviewImg.src = '';
                mediaPreviewName.textContent = '';
            }
        }

        if (mediaPickBtn) {
            mediaPickBtn.addEventListener('click', function () {
                if (typeof MP === 'undefined' || typeof MP.open !== 'function') {
                    return;
                }
                MP.open(function (filename) {
                    showMediaPreview(filename);
                    saveMeta();
                }, { filter: 'images' });
            });
        }

        if (mediaRemoveBtn) {
            mediaRemoveBtn.addEventListener('click', function () {
                clearMediaPreview();
                saveMeta();
            });
        }

        // ---- Mini-Icon-Editor (assets/module_organizer_icon_editor.js) ----
        var iconEditorOpenBtn = document.getElementById('mo-icon-editor-open');
        if (iconEditorOpenBtn) {
            iconEditorOpenBtn.addEventListener('click', function () {
                if (typeof MOIconEditor === 'undefined') {
                    return;
                }
                MOIconEditor.open(function (id, svg) {
                    if (!activeModuleId) {
                        return;
                    }
                    mediaIconKey = 'custom:' + id;
                    saveMeta();
                    // Neu gezeichnetes Icon muss als Galerie-Kachel serverseitig
                    // gerendert werden, bevor es dort auswaehlbar ist - selbes
                    // Reload-Muster wie bei Kategorie anlegen/umbenennen/loeschen.
                    persistSelectionAndReload();
                });
            });
        }

        // ---- Bereiche: Kategorie nur in bestimmten Strukturkategorien anbieten ----
        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('.mo-tree-category-areas');
            var saveBtn = event.target.closest('.mo-areas-save');
            var resetBtn = event.target.closest('.mo-areas-reset');
            if (!toggle && !saveBtn && !resetBtn) {
                return;
            }
            event.preventDefault();
            var categoryEl = event.target.closest('.mo-tree-category');
            var panel = categoryEl.querySelector('.mo-tree-category-areas-panel');
            if (toggle) {
                panel.hidden = !panel.hidden;
                toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
                return;
            }
            var select = panel.querySelector('select');
            if (resetBtn) {
                Array.prototype.forEach.call(select.options, function (o) { o.selected = false; });
            }
            var body = 'rex-api-call=module_organizer_category&op=areas&id=' + encodeURIComponent(categoryEl.getAttribute('data-category-id'))
                + '&structure_children=' + (panel.querySelector('.mo-areas-children').checked ? '1' : '0');
            Array.prototype.forEach.call(select.selectedOptions, function (o) {
                body += '&structure_ids[]=' + encodeURIComponent(o.value);
            });
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (result && result.success) {
                    persistSelectionAndReload();
                }
            });
        });

        // ---- SVG-Code einfügen (serverseitig bereinigt, siehe lib/SvgSanitizer.php) ----
        var svgPasteOpen = document.getElementById('mo-svg-paste-open');
        var svgPaste = document.getElementById('mo-svg-paste');
        if (svgPasteOpen && svgPaste) {
            var svgCode = document.getElementById('mo-svg-paste-code');
            var svgTitle = document.getElementById('mo-svg-paste-title');
            var svgError = document.getElementById('mo-svg-paste-error');
            var toggleSvgPaste = function (show) {
                svgPaste.hidden = !show;
                svgPasteOpen.setAttribute('aria-expanded', show ? 'true' : 'false');
                svgError.hidden = true;
                if (show) {
                    svgCode.focus();
                }
            };
            svgPasteOpen.addEventListener('click', function () { toggleSvgPaste(svgPaste.hidden); });
            document.getElementById('mo-svg-paste-cancel').addEventListener('click', function () { toggleSvgPaste(false); });
            document.getElementById('mo-svg-paste-save').addEventListener('click', function () {
                if (!activeModuleId || '' === svgCode.value.trim()) {
                    return;
                }
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'rex-api-call=module_organizer_save_custom_icon'
                        + '&svg=' + encodeURIComponent(svgCode.value)
                        + '&title=' + encodeURIComponent(svgTitle.value)
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        mediaIconKey = 'custom:' + result.id;
                        saveMeta();
                        persistSelectionAndReload();
                    } else {
                        svgError.hidden = false;
                    }
                }).catch(function () {
                    svgError.hidden = false;
                });
            });
        }

        document.addEventListener('click', function (event) {
            var deleteBtn = event.target.closest('.mo-custom-icon-delete');
            if (!deleteBtn) {
                return;
            }
            event.preventDefault();
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'rex-api-call=module_organizer_delete_custom_icon&id=' + encodeURIComponent(deleteBtn.getAttribute('data-custom-icon-id'))
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (result && result.success) {
                    persistSelectionAndReload();
                }
            });
        });

        // ---- Sidebar: Modul auswaehlen und Felder befuellen (Vorbild: mform
        // Formbuilder-Sidebar - Klick auf ein Element zeigt sofort dessen
        // Eigenschaften, kein Seitenwechsel noetig). Die Kategorie-Zuordnung
        // wird NICHT hier gepflegt, sondern rein durch die Baum-Position
        // (Drag&Drop) bestimmt. ----
        // Nutzung des Moduls: Anzahl und Seiten (Links in den Editiermodus)
        function renderUsage(usage) {
            var box = document.getElementById('mo-usage');
            if (!box) {
                return;
            }
            box.innerHTML = '';
            var head = document.createElement('p');
            head.className = 'mo-usage-head';
            head.textContent = usage.count > 0
                ? box.getAttribute('data-label-count').replace('{0}', usage.count).replace('{1}', usage.pages.length)
                : box.getAttribute('data-label-none');
            box.appendChild(head);
            if (!usage.pages.length) {
                return;
            }
            var list = document.createElement('ul');
            list.className = 'mo-usage-list';
            usage.pages.forEach(function (page) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = page.url;
                a.textContent = page.name;
                li.appendChild(a);
                if (page.n > 1) {
                    li.appendChild(document.createTextNode(' (' + page.n + '×)'));
                }
                list.appendChild(li);
            });
            box.appendChild(list);
            if (usage.pages.length >= 30) {
                var more = document.createElement('p');
                more.className = 'help-block';
                more.textContent = box.getAttribute('data-label-more');
                box.appendChild(more);
            }
        }

        function selectModule(moduleId) {
            var data = modules[moduleId];
            if (!data) {
                return;
            }

            activeModuleId = moduleId;
            data.__wasFavorite = !!data.is_favorite;
            fieldModuleId.value = moduleId;
            sidebarTitle.textContent = data.name;
            fieldFavorite.checked = !!data.is_favorite;
            fieldDescription.value = data.description || '';
            renderUsage(data.usage || { count: 0, pages: [] });

            var iconKey = data.icon_key || '';
            var isMediaIcon = iconKey.indexOf('media:') === 0;

            iconRadios.forEach(function (radio) {
                radio.checked = !isMediaIcon && radio.value === iconKey;
                var label = radio.closest('.mo-icon-radio');
                if (label) {
                    label.classList.toggle('is-selected', radio.checked);
                }
            });

            if (isMediaIcon) {
                showMediaPreview(iconKey.slice('media:'.length));
            } else {
                clearMediaPreview();
            }

            if (emptyHint) {
                emptyHint.hidden = true;
            }
            form.hidden = false;
            if (saveStatus) {
                saveStatus.textContent = '';
            }

            tree.querySelectorAll('.mo-tree-module').forEach(function (item) {
                item.classList.toggle('mo-tree-module-active', item.getAttribute('data-id') === String(moduleId));
            });
        }

        tree.addEventListener('click', function (event) {
            var item = event.target.closest('.mo-tree-module');
            if (item) {
                selectModule(item.getAttribute('data-id'));
            }
        });

        form.querySelectorAll('.mo-icon-radio').forEach(function (label) {
            label.addEventListener('click', function () {
                form.querySelectorAll('.mo-icon-radio').forEach(function (l) { l.classList.remove('is-selected'); });
                label.classList.add('is-selected');
                clearMediaPreview();
            });
        });

        function currentModuleCategoryGroup(moduleId) {
            var moduleEl = tree.querySelector('.mo-tree-module[data-id="' + moduleId + '"]:not(.mo-tree-module-favorite-mirror)');
            if (!moduleEl) {
                return null;
            }
            var group = moduleEl.closest('.mo-tree-modules');
            return group ? group.getAttribute('data-category-id') : null;
        }

        function saveMeta() {
            if (!activeModuleId) {
                return;
            }

            var iconKey = mediaIconKey || '';
            if (!iconKey) {
                iconRadios.forEach(function (radio) {
                    if (radio.checked) {
                        iconKey = radio.value;
                    }
                });
            }

            var data = modules[activeModuleId] || {};
            data.is_favorite = fieldFavorite.checked;
            data.description = fieldDescription.value;
            data.icon_key = iconKey || null;
            modules[activeModuleId] = data;

            var categoryGroup = currentModuleCategoryGroup(activeModuleId);
            var categoryId = categoryGroup && 'favorites' !== categoryGroup ? categoryGroup : '0';

            var body = 'rex-api-call=module_organizer_save_meta'
                + '&module_id=' + encodeURIComponent(activeModuleId)
                + '&category_id=' + encodeURIComponent(categoryId)
                + '&is_favorite=' + (fieldFavorite.checked ? '1' : '')
                + '&description=' + encodeURIComponent(fieldDescription.value)
                + '&icon_key=' + encodeURIComponent(iconKey);

            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (saveStatus) {
                    saveStatus.textContent = result && result.success
                        ? saveStatus.getAttribute('data-msg-saved')
                        : saveStatus.getAttribute('data-msg-failed');
                }
                if (result && result.success && fieldFavorite.checked !== data.__wasFavorite) {
                    // Favorit-Status geaendert: Baum neu laden, damit die
                    // Favoriten-Spiegelgruppe ganz oben aktuell bleibt. Alle
                    // anderen Speicherungen (Icon, Beschreibung) betreffen nur
                    // die Sidebar und brauchen keinen Reload - sonst geht die
                    // aktuelle Baum-Scrollposition/Auswahl bei jeder
                    // Bild-Auswahl/-Entfernung verloren.
                    data.__wasFavorite = fieldFavorite.checked;
                    persistSelectionAndReload();
                }
            }).catch(function () {
                if (saveStatus) {
                    saveStatus.textContent = saveStatus.getAttribute('data-msg-failed');
                }
            });
        }

        function scheduleSave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveMeta, 400);
        }

        fieldFavorite.addEventListener('change', function () {
            var data = modules[activeModuleId] || {};
            data.__wasFavorite = !fieldFavorite.checked;
            saveMeta();
        });
        fieldDescription.addEventListener('input', scheduleSave);
        iconRadios.forEach(function (radio) {
            radio.addEventListener('change', saveMeta);
        });

        // ---- Kategorie ein-/ausklappen ----
        tree.querySelectorAll('.mo-tree-category-header').forEach(function (header) {
            header.addEventListener('click', function (event) {
                if (event.target.closest('button')) {
                    return;
                }
                header.closest('.mo-tree-category').classList.toggle('mo-tree-collapsed');
            });
        });

        // ---- Kategorie anlegen/umbenennen/loeschen (inline, AJAX) ----
        var addCategoryBtn = document.getElementById('mo-tree-add-category');
        if (addCategoryBtn) {
            addCategoryBtn.addEventListener('click', function () {
                var name = window.prompt(categoryNamePrompt, '');
                if (!name || !name.trim()) {
                    return;
                }
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'rex-api-call=module_organizer_category&op=create&name=' + encodeURIComponent(name.trim())
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        persistSelectionAndReload();
                    }
                });
            });
        }

        tree.addEventListener('click', function (event) {
            var renameBtn = event.target.closest('.mo-tree-category-rename');
            if (renameBtn) {
                event.stopPropagation();
                var categoryEl = renameBtn.closest('.mo-tree-category');
                var nameEl = categoryEl.querySelector('.mo-tree-category-name');
                var currentName = nameEl.getAttribute('data-category-name') || nameEl.textContent;
                var newName = window.prompt(categoryNamePrompt, currentName);
                if (!newName || !newName.trim() || newName.trim() === currentName) {
                    return;
                }
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'rex-api-call=module_organizer_category&op=rename'
                        + '&id=' + encodeURIComponent(categoryEl.getAttribute('data-category-id'))
                        + '&name=' + encodeURIComponent(newName.trim())
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        persistSelectionAndReload();
                    }
                });
                return;
            }

            var deleteBtn = event.target.closest('.mo-tree-category-delete');
            if (deleteBtn) {
                event.stopPropagation();
                if (!window.confirm(categoryDeleteConfirm)) {
                    return;
                }
                var categoryEl = deleteBtn.closest('.mo-tree-category');
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'rex-api-call=module_organizer_category&op=delete&id=' + encodeURIComponent(categoryEl.getAttribute('data-category-id'))
                }).then(function (response) {
                    return response.json();
                }).then(function (result) {
                    if (result && result.success) {
                        persistSelectionAndReload();
                    }
                });
            }
        });

        // ---- Native Drag&Drop-Sortierung/-Verschiebung ueber mehrere
        // Kategorie-Gruppen hinweg (kein SortableJS/jQuery UI). Die Favoriten-
        // Spiegelgruppe ist read-only (mo-tree-modules-readonly): Module dort
        // koennen nicht gegriffen und nichts kann dorthin fallengelassen
        // werden - der Favorit-Status wird ausschliesslich ueber die Sidebar-
        // Checkbox gesetzt. ----
        var dragEl = null;
        var dragSourceGroup = null;

        function saveGroupOrder(groupEl) {
            var categoryId = groupEl.getAttribute('data-category-id');
            var order = Array.prototype.map.call(
                groupEl.querySelectorAll('.mo-tree-module'),
                function (el) { return el.getAttribute('data-id'); }
            );

            var body = 'rex-api-call=module_organizer_reorder&mode=modules'
                + '&category_id=' + encodeURIComponent(categoryId)
                + '&order=' + encodeURIComponent(JSON.stringify(order));

            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (saveStatus) {
                    saveStatus.textContent = data && data.success ? orderMsgSaved : orderMsgFailed;
                }
            }).catch(function () {
                if (saveStatus) {
                    saveStatus.textContent = orderMsgFailed;
                }
            });
        }

        function getDragAfterElement(container, y) {
            var items = Array.prototype.slice.call(container.querySelectorAll('.mo-tree-module:not(.mo-dragging)'));
            return items.reduce(function (closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        }

        tree.querySelectorAll('.mo-tree-modules:not(.mo-tree-modules-readonly) .mo-tree-module').forEach(function (item) {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', function () {
                dragEl = item;
                dragSourceGroup = item.closest('.mo-tree-modules');
                item.classList.add('mo-dragging');
            });

            item.addEventListener('dragend', function () {
                item.classList.remove('mo-dragging');
                var targetGroup = item.closest('.mo-tree-modules');
                if (targetGroup) {
                    saveGroupOrder(targetGroup);
                    if (dragSourceGroup && dragSourceGroup !== targetGroup) {
                        saveGroupOrder(dragSourceGroup);
                    }
                }
                dragEl = null;
                dragSourceGroup = null;
            });
        });

        tree.querySelectorAll('.mo-tree-modules:not(.mo-tree-modules-readonly)').forEach(function (group) {
            group.addEventListener('dragover', function (event) {
                if (!dragEl) {
                    return;
                }
                event.preventDefault();
                var afterElement = getDragAfterElement(group, event.clientY);
                if (null === afterElement) {
                    group.appendChild(dragEl);
                } else {
                    group.insertBefore(dragEl, afterElement);
                }
            });
        });

        // ---- Kategorien selbst per Drag&Drop sortieren (Handle = Header) ----
        var categoryDragEl = null;

        function saveCategoryOrder() {
            var order = Array.prototype.map.call(
                tree.querySelectorAll('.mo-tree-category[data-category-id]:not(.mo-tree-category-favorites):not(.mo-tree-category-uncategorized)'),
                function (el) { return el.getAttribute('data-category-id'); }
            );

            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'rex-api-call=module_organizer_reorder&mode=categories&order=' + encodeURIComponent(JSON.stringify(order))
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (saveStatus) {
                    saveStatus.textContent = data && data.success ? orderMsgSaved : orderMsgFailed;
                }
            });
        }

        function getCategoryDragAfterElement(y) {
            var items = Array.prototype.slice.call(
                tree.querySelectorAll('.mo-tree-category:not(.mo-dragging):not(.mo-tree-category-favorites):not(.mo-tree-category-uncategorized)')
            );
            return items.reduce(function (closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        }

        tree.querySelectorAll('.mo-tree-category:not(.mo-tree-category-favorites):not(.mo-tree-category-uncategorized)').forEach(function (categoryEl) {
            var header = categoryEl.querySelector('.mo-tree-category-header');
            header.setAttribute('draggable', 'true');

            header.addEventListener('dragstart', function (event) {
                categoryDragEl = categoryEl;
                categoryEl.classList.add('mo-dragging');
                event.stopPropagation();
            });

            header.addEventListener('dragend', function () {
                categoryEl.classList.remove('mo-dragging');
                categoryDragEl = null;
                saveCategoryOrder();
            });
        });

        tree.addEventListener('dragover', function (event) {
            if (!categoryDragEl) {
                return;
            }
            event.preventDefault();
            var afterElement = getCategoryDragAfterElement(event.clientY);
            var uncategorized = tree.querySelector('.mo-tree-category-uncategorized');
            if (null === afterElement) {
                tree.insertBefore(categoryDragEl, uncategorized);
            } else {
                tree.insertBefore(categoryDragEl, afterElement);
            }
        });

        restoreSelectionAfterReload();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
