(function () {
    'use strict';

    var overlay = null;
    var grid = null;
    var searchInput = null;
    var sidebar = null;
    var searchTimer = null;
    var currentItems = [];
    var currentCategoryFilter = 'all';
    var iconCache = {};

    var addonAssetsUrl = (document.currentScript && document.currentScript.dataset.assetsUrl)
        || (function () {
            var scripts = document.getElementsByTagName('script');
            for (var i = 0; i < scripts.length; i++) {
                if (scripts[i].src && scripts[i].src.indexOf('module_organizer.js') !== -1) {
                    return scripts[i].src.replace(/module_organizer\.js.*$/, '');
                }
            }
            return '';
        })();

    // JS-Uebersetzungen + Media-Base-URL: boot.php liefert beides ueber
    // rex_view::setJsProperty('module_organizer', ...) - das ist Teil des
    // Backend-globalen "rex"-JS-Objekts, das der Core (fragments/core/top.php)
    // bereits im <head> ausgibt, also VOR diesem Script. Kein eigenes
    // <script>-Tag/Nonce-Handling und kein Lazy-Loading mehr noetig.
    var moData = (window.rex && window.rex.module_organizer) || {};
    var i18nDict = moData.i18n || {};
    var mediaBaseUrl = moData.mediaBaseUrl || '';

    function t(key) {
        return i18nDict['module_organizer_' + key] || key;
    }

    // SVG-Dateien koennen NICHT ueber den Media-Manager ausgeliefert werden
    // (REDAXO-Kernverhalten) - dafuer die rohe Medienpool-URL direkt nutzen,
    // alles andere weiterhin per rex_media_type/rex_media_file (Media-
    // Manager-Thumbnail).
    function buildMediaIconUrl(filename) {
        if (/\.svg$/i.test(filename)) {
            return mediaBaseUrl + filename;
        }
        return 'index.php?rex_media_type=rex_media_small&rex_media_file=' + encodeURIComponent(filename);
    }

    function build() {
        if (document.getElementById('mo-overlay')) {
            overlay = document.getElementById('mo-overlay');
            grid = overlay.querySelector('.mo-grid');
            searchInput = overlay.querySelector('.mo-search');
            sidebar = overlay.querySelector('.mo-sidebar');
            return;
        }

        overlay = document.createElement('div');
        overlay.id = 'mo-overlay';
        overlay.innerHTML =
            '<div class="mo-modal">' +
                '<div class="mo-header">' +
                    '<input type="text" class="mo-search" placeholder="' + escHtml(t('search_placeholder')) + '">' +
                    '<button type="button" class="mo-close" aria-label="' + escHtml(t('close')) + '">&times;</button>' +
                '</div>' +
                '<div class="mo-body">' +
                    '<div class="mo-sidebar"></div>' +
                    '<div class="mo-grid"></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        grid = overlay.querySelector('.mo-grid');
        searchInput = overlay.querySelector('.mo-search');
        sidebar = overlay.querySelector('.mo-sidebar');

        overlay.querySelector('.mo-close').addEventListener('click', close);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && overlay.classList.contains('mo-open')) {
                close();
            }
        });

        searchInput.setAttribute('aria-label', t('search_placeholder'));
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(renderGrid, 200);
        });

        // Tastatur: Pfeil runter springt ins Raster, Enter fügt den ersten Treffer ein;
        // im Raster Pfeiltasten (zeilenweise wie sichtbar), Pos1/Ende, Pfeil hoch in der ersten Zeile zurück zur Suche
        function tiles() {
            return Array.prototype.slice.call(grid.querySelectorAll('.mo-tile'));
        }
        searchInput.addEventListener('keydown', function (event) {
            if ('ArrowDown' === event.key || 'Enter' === event.key) {
                clearTimeout(searchTimer);
                renderGrid();
                var all = tiles();
                if (!all.length) {
                    return;
                }
                event.preventDefault();
                if ('Enter' === event.key) {
                    all[0].click();
                } else {
                    all[0].focus();
                }
            }
        });
        grid.addEventListener('keydown', function (event) {
            var all = tiles();
            var current = document.activeElement;
            var index = all.indexOf(current);
            if (-1 === index) {
                return;
            }
            var next = null;
            if ('ArrowRight' === event.key) {
                next = all[Math.min(index + 1, all.length - 1)];
            } else if ('ArrowLeft' === event.key) {
                next = all[Math.max(index - 1, 0)];
            } else if ('Home' === event.key) {
                next = all[0];
            } else if ('End' === event.key) {
                next = all[all.length - 1];
            } else if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
                var down = 'ArrowDown' === event.key;
                var top = current.offsetTop;
                var left = current.offsetLeft;
                var candidates = all.filter(function (tile) {
                    return down ? tile.offsetTop > top : tile.offsetTop < top;
                });
                if (!candidates.length) {
                    next = down ? null : searchInput;
                } else {
                    var rowTop = down
                        ? Math.min.apply(null, candidates.map(function (tile) { return tile.offsetTop; }))
                        : Math.max.apply(null, candidates.map(function (tile) { return tile.offsetTop; }));
                    candidates.filter(function (tile) { return tile.offsetTop === rowTop; }).forEach(function (tile) {
                        if (!next || Math.abs(tile.offsetLeft - left) < Math.abs(next.offsetLeft - left)) {
                            next = tile;
                        }
                    });
                }
            }
            if (next) {
                event.preventDefault();
                next.focus();
            }
        });
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

    function buildSidebar() {
        var categories = {};
        var categoryOrder = {};
        currentItems.forEach(function (item) {
            if (item.category_id && item.category_name) {
                categories[item.category_id] = item.category_name;
                categoryOrder[item.category_id] = item.category_priority || 0;
            }
        });

        var html = '';
        html += '<button type="button" class="mo-filter-btn mo-filter-active" data-filter="all">' + escHtml(t('filter_all')) + '</button>';
        html += '<button type="button" class="mo-filter-btn" data-filter="favorites"><i class="rex-icon fa-star"></i> ' + escHtml(t('filter_favorites')) + '</button>';
        Object.keys(categories).sort(function (a, b) {
            return (categoryOrder[a] - categoryOrder[b]) || categories[a].localeCompare(categories[b]);
        }).forEach(function (categoryId) {
            html += '<button type="button" class="mo-filter-btn" data-filter="cat-' + categoryId + '">' + escHtml(categories[categoryId]) + '</button>';
        });

        sidebar.innerHTML = html;
        sidebar.querySelectorAll('.mo-filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                sidebar.querySelectorAll('.mo-filter-btn').forEach(function (b) { b.classList.remove('mo-filter-active'); });
                btn.classList.add('mo-filter-active');
                currentCategoryFilter = btn.getAttribute('data-filter');
                renderGrid();
            });
        });
    }

    function matchesFilter(item) {
        if ('favorites' === currentCategoryFilter) {
            return !!item.is_favorite;
        }
        if (currentCategoryFilter.indexOf('cat-') === 0) {
            var categoryId = currentCategoryFilter.slice(4);
            return String(item.category_id) === categoryId;
        }
        return true;
    }

    function matchesSearch(item, query) {
        if (!query) {
            return true;
        }
        var haystack = [item.title, item.full_title, item.description, item.key].join(' ').toLowerCase();
        return haystack.indexOf(query) !== -1;
    }

    function sortItems(items) {
        return items.slice().sort(function (a, b) {
            if (!!a.is_favorite !== !!b.is_favorite) {
                return a.is_favorite ? -1 : 1;
            }
            // Kategorien in der Reihenfolge des Organizers, Module ohne Kategorie zuletzt
            var ca = a.category_id ? (a.category_priority || 0) : Number.MAX_SAFE_INTEGER;
            var cb = b.category_id ? (b.category_priority || 0) : Number.MAX_SAFE_INTEGER;
            if (ca !== cb) {
                return ca - cb;
            }
            if (a.priority !== b.priority) {
                return a.priority - b.priority;
            }
            return a.title.localeCompare(b.title);
        });
    }

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
                renderGrid();
            }
        }).catch(function () {
            favBtn.disabled = false;
        });
    }

    function renderGrid() {
        var query = (searchInput.value || '').trim().toLowerCase();
        var filtered = sortItems(currentItems.filter(function (item) {
            return matchesFilter(item) && matchesSearch(item, query);
        }));

        if (0 === filtered.length) {
            grid.innerHTML = '<div class="mo-grid-empty">' + escHtml(t('no_modules_found')) + '</div>';
            return;
        }

        grid.innerHTML = '';
        filtered.forEach(function (item) {
            var tile = document.createElement('a');
            tile.className = 'mo-tile';
            tile.href = item.href;

            var iconWrap = document.createElement('div');
            iconWrap.className = 'mo-tile-icon';
            tile.appendChild(iconWrap);

            if (item.is_global_favorite) {
                var fav = document.createElement('span');
                fav.className = 'mo-tile-favorite';
                fav.innerHTML = '<i class="rex-icon fa-star"></i>';
                tile.appendChild(fav);
            } else {
                var favBtn = document.createElement('button');
                favBtn.type = 'button';
                favBtn.className = 'mo-tile-favorite-toggle' + (item.is_user_favorite ? ' is-active' : '');
                favBtn.innerHTML = '<i class="rex-icon ' + (item.is_user_favorite ? 'fa-star' : 'fa-star-o') + '"></i>';
                favBtn.title = t('toggle_favorite');
                favBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    toggleUserFavorite(item, favBtn);
                });
                tile.appendChild(favBtn);
            }

            var title = document.createElement('div');
            title.className = 'mo-tile-title';
            title.textContent = item.title;
            tile.appendChild(title);

            // Beschreibung sichtbar (nicht nur als Tooltip)
            if (item.description) {
                var desc = document.createElement('div');
                desc.className = 'mo-tile-desc';
                desc.textContent = item.description;
                tile.appendChild(desc);
            }

            grid.appendChild(tile);

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
                // Selbst gezeichnetes Icon: SVG kam bereits inline im
                // Server-Payload mit (item.custom_svg), kein Fetch noetig.
                iconWrap.innerHTML = item.custom_svg || '';
            } else if (item.icon_key) {
                loadIcon(item.icon_key).then(function (svg) {
                    if (svg) {
                        iconWrap.innerHTML = svg;
                    }
                });
            }
        });
    }

    function open(items) {
        build();
        currentItems = items || [];
        currentCategoryFilter = 'all';
        searchInput.value = '';
        buildSidebar();
        renderGrid();
        overlay.classList.add('mo-open');
        searchInput.focus();
    }

    function close() {
        if (overlay) {
            overlay.classList.remove('mo-open');
        }
    }

    window.MO = { open: open, close: close };

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.mo-trigger[data-mo-mode="overlay"]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        var raw = trigger.getAttribute('data-mo-items');
        var items = [];
        try {
            items = JSON.parse(raw || '[]');
        } catch (e) {
            items = [];
        }
        open(items);
    });
})();
