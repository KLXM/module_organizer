(function () {
    'use strict';

    var popover = null;
    var currentTrigger = null;
    var iconCache = {};

    var addonAssetsUrl = (function () {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            if (scripts[i].src && scripts[i].src.indexOf('module_organizer_popover.js') !== -1) {
                return scripts[i].src.replace(/module_organizer_popover\.js.*$/, '');
            }
        }
        return '';
    })();

    // JS-Uebersetzungen + Media-Base-URL: boot.php liefert beides ueber
    // rex_view::setJsProperty('module_organizer', ...) - Teil des Backend-
    // globalen "rex"-JS-Objekts, das der Core bereits im <head> ausgibt.
    var moData = (window.rex && window.rex.module_organizer) || {};
    var i18nDict = moData.i18n || {};
    var mediaBaseUrl = moData.mediaBaseUrl || '';

    function t(key) {
        return i18nDict['module_organizer_' + key] || key;
    }

    function buildMediaIconUrl(filename) {
        if (/\.svg$/i.test(filename)) {
            return mediaBaseUrl + filename;
        }
        return 'index.php?rex_media_type=rex_media_small&rex_media_file=' + encodeURIComponent(filename);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = null == str ? '' : String(str);
        return div.innerHTML;
    }

    function loadIcon(iconKey) {
        if (!iconKey) {
            return Promise.resolve('');
        }
        if (iconCache[iconKey]) {
            return iconCache[iconKey];
        }
        var url = addonAssetsUrl + 'icons/layout-' + iconKey + '.svg';
        iconCache[iconKey] = fetch(url).then(function (response) {
            return response.ok ? response.text() : '';
        }).catch(function () {
            return '';
        });
        return iconCache[iconKey];
    }

    function build() {
        if (popover) {
            return;
        }
        popover = document.createElement('div');
        popover.id = 'mo-popover';
        popover.innerHTML =
            '<div class="mo-popover-search-wrap">' +
                '<input type="text" class="mo-popover-search" placeholder="">' +
            '</div>' +
            '<div class="mo-popover-list"></div>';
        document.body.appendChild(popover);

        var searchEl = popover.querySelector('.mo-popover-search');
        searchEl.setAttribute('aria-label', t('search_placeholder'));
        searchEl.addEventListener('input', function () {
            renderList((this.value || '').trim().toLowerCase());
        });

        // Tastatur: Pfeil runter springt in die Liste, Enter fügt den ersten Treffer ein,
        // in der Liste Pfeil hoch/runter, Pos1/Ende; Pfeil hoch in der ersten Zeile zurück zur Suche
        function rows() {
            return Array.prototype.slice.call(popover.querySelectorAll('.mo-popover-row'));
        }
        searchEl.addEventListener('keydown', function (event) {
            var all = rows();
            if ('ArrowDown' === event.key && all.length) {
                event.preventDefault();
                all[0].focus();
            } else if ('Enter' === event.key && all.length) {
                event.preventDefault();
                all[0].click();
            }
        });
        popover.querySelector('.mo-popover-list').addEventListener('keydown', function (event) {
            var all = rows();
            var index = all.indexOf(document.activeElement);
            if (-1 === index) {
                return;
            }
            var next = null;
            if ('ArrowDown' === event.key) {
                next = all[Math.min(index + 1, all.length - 1)];
            } else if ('ArrowUp' === event.key) {
                next = 0 === index ? searchEl : all[index - 1];
            } else if ('Home' === event.key) {
                next = all[0];
            } else if ('End' === event.key) {
                next = all[all.length - 1];
            }
            if (next) {
                event.preventDefault();
                next.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (!popover.classList.contains('mo-open')) {
                return;
            }
            if (popover.contains(event.target) || (currentTrigger && currentTrigger.contains(event.target))) {
                return;
            }
            close();
        });

        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && popover.classList.contains('mo-open')) {
                close();
            }
        });

        window.addEventListener('scroll', function () {
            if (popover.classList.contains('mo-open')) {
                position();
            }
        }, true);
        window.addEventListener('resize', function () {
            if (popover.classList.contains('mo-open')) {
                position();
            }
        });
    }

    function sortItems(items) {
        return items.slice().sort(function (a, b) {
            return a.title.localeCompare(b.title);
        });
    }

    var lastQuery = '';


    // Persönliche Favoriten gelten für alle „Block hinzufügen“-Knöpfe der Seite (jeder hat eine eigene
    // Datenkopie): Änderungen hier merken und beim Öffnen auf die Einträge anwenden.
    var userFavorites = window.MOUserFavorites = window.MOUserFavorites || {};
    function applyUserFavorites(items) {
        (items || []).forEach(function (item) {
            if (Object.prototype.hasOwnProperty.call(userFavorites, item.id)) {
                item.is_user_favorite = userFavorites[item.id];
                item.is_favorite = !!item.is_global_favorite || item.is_user_favorite;
            }
        });
        return items;
    }
    function favLabel(on) { return t(on ? 'favorite_remove' : 'favorite_add'); }

    function toggleUserFavorite(item, favBtn) {
        var next = !item.is_user_favorite;
        favBtn.disabled = true;

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'rex-api-call=module_organizer_toggle_user_favorite'
                + '&module_id=' + encodeURIComponent(item.id)
                + '&is_favorite=' + (next ? '1' : '')
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            favBtn.disabled = false;
            if (result && result.success) {
                item.is_user_favorite = next;
                item.is_favorite = item.is_global_favorite || next;
                userFavorites[item.id] = next;
                renderList(lastQuery);
            }
        }).catch(function () {
            favBtn.disabled = false;
        });
    }

    function renderRow(item) {
        var row = document.createElement('a');
        row.className = 'mo-popover-row';
        row.href = item.href;

        var iconWrap = document.createElement('span');
        iconWrap.className = 'mo-popover-row-icon';
        row.appendChild(iconWrap);

        var text = document.createElement('span');
        text.className = 'mo-popover-row-text';
        var title = document.createElement('span');
        title.className = 'mo-popover-row-title';
        title.textContent = item.title;
        text.appendChild(title);
        // Beschreibung sichtbar (nicht nur als Tooltip – Touch- und Tastaturnutzer sehen sonst nichts)
        if (item.description) {
            var desc = document.createElement('span');
            desc.className = 'mo-popover-row-desc';
            desc.textContent = item.description;
            text.appendChild(desc);
        }
        row.appendChild(text);

        // Globale Favoriten (admin-gepflegt, Organizer-Strukturbaum) sind
        // hier read-only (voller Stern, kein Klick-Handler). Persoenliche
        // Favoriten kann jeder eingeloggte User selbst an-/abwaehlen -
        // eigener klickbarer Button statt reinem Icon, damit ein Klick auf
        // den Stern nicht gleichzeitig die Zeile selbst (= Modul einfuegen)
        // ausloest.
        if (item.is_global_favorite) {
            var fav = document.createElement('i');
            fav.className = 'rex-icon fa-star mo-popover-row-favorite';
            row.appendChild(fav);
        } else {
            var favBtn = document.createElement('button');
            favBtn.type = 'button';
            favBtn.className = 'mo-popover-row-favorite-toggle' + (item.is_user_favorite ? ' is-active' : '');
            favBtn.innerHTML = '<i class="rex-icon ' + (item.is_user_favorite ? 'fa-star' : 'fa-star-o') + '" aria-hidden="true"></i>';
            favBtn.title = favLabel(item.is_user_favorite);
            favBtn.setAttribute('aria-label', favLabel(item.is_user_favorite) + ': ' + item.title);
            favBtn.setAttribute('aria-pressed', item.is_user_favorite ? 'true' : 'false');
            favBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleUserFavorite(item, favBtn);
            });
            row.appendChild(favBtn);
        }

        // Duotone: Akzentfarbe der Kategorie
        if (item.category_color) {
            iconWrap.style.setProperty('--mo-icon-accent', item.category_color);
        }
        if (item.icon_key && item.icon_key.indexOf('media:') === 0) {
            var filename = item.icon_key.slice('media:'.length);
            var img = document.createElement('img');
            img.src = buildMediaIconUrl(filename);
            img.alt = '';
            iconWrap.appendChild(img);
        } else if (item.icon_key && item.icon_key.indexOf('custom:') === 0) {
            iconWrap.innerHTML = item.custom_svg || '';
        } else if (item.icon_key) {
            loadIcon(item.icon_key).then(function (svg) {
                if (svg) {
                    iconWrap.innerHTML = svg;
                }
            });
        }

        return row;
    }

    function renderGroupHeader(label) {
        var header = document.createElement('div');
        header.className = 'mo-popover-group-header';
        header.textContent = label;
        return header;
    }

    function renderDivider() {
        var divider = document.createElement('div');
        divider.className = 'mo-popover-divider';
        return divider;
    }

    function renderList(query) {
        lastQuery = query || '';
        var list = popover.querySelector('.mo-popover-list');
        list.innerHTML = '';

        var items = currentTrigger ? (currentTrigger.__moItems || []) : [];

        if (query) {
            items = items.filter(function (item) {
                var haystack = [item.title, item.full_title, item.description, item.key].join(' ').toLowerCase();
                return haystack.indexOf(query) !== -1;
            });
        }

        if (0 === items.length) {
            var empty = document.createElement('div');
            empty.className = 'mo-popover-empty';
            empty.textContent = t('no_modules_found');
            list.appendChild(empty);
            return;
        }

        var favorites = sortItems(items.filter(function (item) { return !!item.is_favorite; }));
        var rest = items.filter(function (item) { return !item.is_favorite; });

        var byCategory = {};
        var uncategorized = [];
        rest.forEach(function (item) {
            if (item.category_id && item.category_name) {
                var key = String(item.category_id);
                byCategory[key] = byCategory[key] || { name: item.category_name, priority: item.category_priority || 0, items: [] };
                byCategory[key].items.push(item);
            } else {
                uncategorized.push(item);
            }
        });

        var categoryGroups = Object.keys(byCategory)
            .map(function (key) { return byCategory[key]; })
            .sort(function (a, b) { return (a.priority - b.priority) || a.name.localeCompare(b.name); });

        var renderedAny = false;

        if (favorites.length > 0) {
            list.appendChild(renderGroupHeader(t('filter_favorites')));
            favorites.forEach(function (item) { list.appendChild(renderRow(item)); });
            renderedAny = true;
        }

        // Zuletzt verwendet (je Benutzer) – nur ohne Suchbegriff, damit die Trefferliste kurz bleibt
        var recent = query ? [] : items.filter(function (item) { return item.recent_rank > 0; })
            .sort(function (a, b) { return a.recent_rank - b.recent_rank; });
        if (recent.length > 0) {
            if (renderedAny) {
                list.appendChild(renderDivider());
            }
            list.appendChild(renderGroupHeader(t('filter_recent')));
            recent.forEach(function (item) { list.appendChild(renderRow(item)); });
            renderedAny = true;
        }

        categoryGroups.forEach(function (group) {
            if (renderedAny) {
                list.appendChild(renderDivider());
            }
            list.appendChild(renderGroupHeader(group.name));
            sortItems(group.items).forEach(function (item) { list.appendChild(renderRow(item)); });
            renderedAny = true;
        });

        if (uncategorized.length > 0) {
            if (renderedAny) {
                list.appendChild(renderDivider());
            }
            sortItems(uncategorized).forEach(function (item) { list.appendChild(renderRow(item)); });
        }
    }

    // Klassisches Popdown/Popup-Verhalten: erscheint direkt UNTER dem Button
    // (Popdown), oder DARUEBER (Popup) wenn unten nicht genug Platz ist -
    // wie ein gewoehnliches Dropdown, kein am Button zentriert wachsendes
    // Modal. Breite folgt der Button-Breite (die Trigger sind i.d.R.
    // btn-block, volle Panel-Breite), nicht auf 320px begrenzt.
    function position() {
        if (!currentTrigger) {
            return;
        }
        var rect = currentTrigger.getBoundingClientRect();
        var margin = 6;
        var maxAvailable = 420;

        var spaceBelow = window.innerHeight - rect.bottom - margin;
        var spaceAbove = rect.top - margin;

        var top;
        var maxHeight;
        if (spaceBelow >= 200 || spaceBelow >= spaceAbove) {
            // Popdown: unter dem Button.
            top = rect.bottom + margin;
            maxHeight = Math.min(maxAvailable, spaceBelow);
        } else {
            // Popup: ueber dem Button, wenn unten zu wenig Platz ist.
            maxHeight = Math.min(maxAvailable, spaceAbove);
            top = rect.top - margin - maxHeight;
        }

        popover.style.maxHeight = Math.max(120, maxHeight) + 'px';
        popover.style.top = Math.max(margin, top) + 'px';
        popover.style.left = rect.left + 'px';
        popover.style.width = rect.width + 'px';
    }

    function open(trigger, items) {
        build();
        currentTrigger = trigger;
        trigger.__moItems = applyUserFavorites(items || []);

        var search = popover.querySelector('.mo-popover-search');
        search.placeholder = t('search_placeholder');
        search.value = '';

        renderList('');
        popover.classList.add('mo-open');
        position();
        search.focus();
    }

    function close() {
        if (popover) {
            popover.classList.remove('mo-open');
        }
        currentTrigger = null;
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.mo-trigger[data-mo-mode="popover"]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        if (popover && popover.classList.contains('mo-open') && currentTrigger === trigger) {
            close();
            return;
        }

        var raw = trigger.getAttribute('data-mo-items');
        var items = [];
        try {
            items = JSON.parse(raw || '[]');
        } catch (e) {
            items = [];
        }
        open(trigger, items);
    });
})();
