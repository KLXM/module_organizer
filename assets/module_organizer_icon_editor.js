(function () {
    'use strict';

    // Vektor-Editor für eigene Icons (24×18) und Vorschaubilder (32×20 = 16:10).
    //
    // Der Browser schickt NIE rohes SVG, nur Formen (Typ, Koordinaten, Füllstil) – das SVG baut
    // lib/CustomIconRenderer.php auf dem Server (Sicherheit + gleicher Stil wie die Vorlagen).
    // Die Zeichenfläche zeigt genau dieses Server-Ergebnis; darüber liegt eine Bedienebene mit
    // Auswahlrahmen und Anfassern.
    //
    // Aufbau: links Werkzeuge, Bausteine (fertige Gruppen) und Vorlagen, Mitte Zeichenfläche,
    // rechts Eigenschaften und Vorschau. Vollbild wie MediaPlace (Zustand gemerkt).
    // Öffnen: MOIconEditor.open(callback(id, svg, kind), { kind, id, title, shapes })

    var KINDS = {
        icon: { w: 24, h: 18, max: 16, scale: 0.75 },
        preview: { w: 32, h: 20, max: 60, scale: 1 }
    };
    var SNAP = 0.5;
    var PREVIEW_DEBOUNCE = 120;
    var HISTORY_MAX = 80;

    var TOOL_GROUPS = [
        ['basic', ['rect', 'circle', 'line', 'arrow', 'star', 'check', 'pin']],
        ['content', ['heading', 'text', 'button', 'image', 'video', 'media', 'form', 'browser']]
    ];
    var LINE_TYPES = ['line', 'arrow'];
    var STYLES = ['outline', 'soft', 'accent', 'solid', 'muted'];
    var DEFAULT_STYLE = {
        heading: 'solid', text: 'solid', line: 'solid', arrow: 'solid', browser: 'muted',
        button: 'accent', star: 'accent', pin: 'accent', check: 'accent'
    };
    var DEFAULT_SIZE = {
        text: [8, 3], heading: [10, 1.5], button: [5, 1.5], star: [3, 3], pin: [2.5, 3.5], check: [2.5, 2.5],
        circle: [4, 4], browser: [32, 20], image: [8, 6], video: [8, 5]
    };
    // Mini-Symbole der Werkzeuge (nur Anzeige im Editor, 24×18)
    var GLYPH = {
        select: '<path d="M7 3l9 7-4 .6 2.6 5-1.8.9-2.6-5L7 14z"/>',
        rect: '<rect x="4" y="4" width="16" height="10" rx="1.2"/>',
        circle: '<ellipse cx="12" cy="9" rx="6" ry="6"/>',
        line: '<path d="M4 15L20 3"/>',
        arrow: '<path d="M4 15L20 3M13 3h7v7"/>',
        star: '<path d="M12 2.5l2 4.6 5 .4-3.8 3.2 1.2 4.8L12 13l-4.4 2.5 1.2-4.8L5 7.5l5-.4z"/>',
        check: '<circle cx="12" cy="9" r="6.5"/><path d="M9 9.3l2 2 4-4"/>',
        pin: '<path d="M12 16s-5-5-5-8.5a5 5 0 0 1 10 0C17 11 12 16 12 16z"/><circle cx="12" cy="7.5" r="1.8"/>',
        heading: '<path d="M4 6h16M4 11h9" stroke-width="2.4"/>',
        text: '<path d="M4 5h16M4 9h16M4 13h10"/>',
        button: '<rect x="3" y="5.5" width="18" height="7" rx="3.5"/><path d="M9 9h6"/>',
        image: '<rect x="3" y="3" width="18" height="12" rx="1.2"/><circle cx="8" cy="7" r="1.5"/><path d="M4 14l5-5 4 4 2-2 5 3"/>',
        video: '<rect x="3" y="3" width="18" height="12" rx="1.2"/><path d="M10 6v6l5-3z"/>',
        media: '<path d="M6 2h8l4 4v10H6z"/><path d="M14 2v4h4M9 10h6M9 13h4"/>',
        form: '<rect x="3" y="3" width="18" height="4" rx="1"/><rect x="3" y="10" width="11" height="4" rx="1"/><rect x="16" y="10" width="5" height="4" rx="2"/>',
        browser: '<rect x="3" y="2" width="18" height="14" rx="1.5"/><path d="M3 5.5h18"/>'
    };

    // Bausteine: fertige Gruppen in Vorschau-Einheiten (32×20), beim Icon auf 3/4 verkleinert
    var BLOCKS = {
        nav: { w: 29, h: 1, shapes: [['heading', 'solid', 0, 0.2, 3.4, 0.6], ['heading', 'muted', 15.6, 0.3, 2.2, 0.4], ['heading', 'muted', 18.6, 0.3, 2.2, 0.4], ['heading', 'muted', 21.6, 0.3, 2.2, 0.4], ['heading', 'muted', 24.6, 0.3, 2.2, 0.4]] },
        hero: { w: 29, h: 14, shapes: [['image', 'accent', 0, 0, 29, 14], ['heading', 'solid', 2, 4.5, 14, 1.2], ['text', 'muted', 1.8, 6.4, 12, 1.5], ['button', 'accent', 2, 9.4, 5.6, 1.4], ['button', 'outline', 8.2, 9.4, 5, 1.4]] },
        imagetext: { w: 29, h: 11, shapes: [['image', 'accent', 0, 0, 14, 11], ['heading', 'solid', 15.4, 1, 11, 0.8], ['text', 'muted', 15.2, 2.6, 13.8, 4.2], ['button', 'accent', 15.4, 7.6, 4.6, 1.2]] },
        card: { w: 9.2, h: 14, shapes: [['rect', 'outline', 0, 0, 9.2, 14], ['image', 'accent', 0.4, 0.4, 8.4, 5.6], ['heading', 'solid', 0.8, 6.8, 6, 0.6], ['text', 'muted', 0.6, 8, 7.6, 2.4]] },
        cards3: { w: 29.2, h: 14, shapes: [] },
        gallery: { w: 29, h: 14, shapes: [['image', 'accent', 0, 0, 14.2, 6.7], ['image', 'accent', 14.8, 0, 14.2, 6.7], ['image', 'accent', 0, 7.3, 14.2, 6.7], ['image', 'accent', 14.8, 7.3, 14.2, 6.7]] },
        formrow: { w: 20, h: 2.6, shapes: [['heading', 'muted', 0, 0, 6, 0.4], ['rect', 'outline', 0, 0.8, 13.4, 1.6], ['button', 'accent', 14, 0.8, 6, 1.6]] },
        quote: { w: 20, h: 8, shapes: [['star', 'accent', 0, 0, 2.2, 2.2], ['text', 'solid', 3, 0.2, 17, 3.2], ['circle', 'accent', 3, 5, 2, 2], ['heading', 'solid', 5.6, 5.7, 7, 0.5]] },
        stats: { w: 28, h: 4, shapes: [['heading', 'solid', 0, 0, 5, 1.6], ['heading', 'muted', 0, 2.6, 5.6, 0.4], ['heading', 'solid', 7.6, 0, 5, 1.6], ['heading', 'muted', 7.6, 2.6, 5.6, 0.4], ['heading', 'solid', 15.2, 0, 5, 1.6], ['heading', 'muted', 15.2, 2.6, 5.6, 0.4], ['heading', 'solid', 22.8, 0, 5, 1.6], ['heading', 'muted', 22.8, 2.6, 5.6, 0.4]] },
        list: { w: 20, h: 8, shapes: [['circle', 'accent', 0, 0.2, 0.6, 0.6], ['heading', 'muted', 1.4, 0.3, 17, 0.4], ['circle', 'accent', 0, 2.2, 0.6, 0.6], ['heading', 'muted', 1.4, 2.3, 14, 0.4], ['circle', 'accent', 0, 4.2, 0.6, 0.6], ['heading', 'muted', 1.4, 4.3, 16, 0.4], ['circle', 'accent', 0, 6.2, 0.6, 0.6], ['heading', 'muted', 1.4, 6.3, 11, 0.4]] },
        accordion: { w: 29, h: 9, shapes: [['rect', 'outline', 0, 0, 29, 2.4], ['heading', 'solid', 1, 1, 12, 0.4], ['rect', 'outline', 0, 3.2, 29, 2.4], ['heading', 'muted', 1, 4.2, 10, 0.4], ['rect', 'outline', 0, 6.4, 29, 2.4], ['heading', 'muted', 1, 7.4, 13, 0.4]] },
        footer: { w: 32, h: 6, shapes: [['rect', 'muted', 0, 0, 32, 6], ['heading', 'solid', 2, 1.2, 4, 0.6], ['text', 'muted', 1.8, 2.2, 6, 2.2], ['heading', 'solid', 10, 1.2, 4, 0.6], ['text', 'muted', 9.8, 2.2, 6, 2.2], ['heading', 'solid', 18, 1.2, 4, 0.6], ['text', 'muted', 17.8, 2.2, 6, 2.2]] }
    };
    BLOCKS.cards3.shapes = [0, 10, 20].reduce(function (all, dx) {
        return all.concat(BLOCKS.card.shapes.map(function (s) { return [s[0], s[1], s[2] + dx, s[3], s[4], s[5]]; }));
    }, []);

    var i18nDict = (window.rex && window.rex.module_organizer && window.rex.module_organizer.i18n) || {};
    function t(key) { return i18nDict['module_organizer_' + key] || key; }
    function esc(str) { var d = document.createElement('div'); d.textContent = null == str ? '' : String(str); return d.innerHTML; }
    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
    function round(v) { return Math.round(v * 100) / 100; }
    function isLine(s) { return LINE_TYPES.indexOf(s.type) !== -1; }
    function glyph(name) { return '<svg viewBox="0 0 24 18" aria-hidden="true" focusable="false">' + (GLYPH[name] || '') + '</svg>'; }

    var el = {};               // DOM-Referenzen
    var kind = 'icon';
    var canvas = KINDS.icon;
    var shapes = [];
    var selected = [];         // Liste von Formen (Referenzen)
    var tool = 'select';
    var history = [];
    var future = [];
    var clipboard = null;
    var onSave = null;
    var editingId = null;
    var dirty = false;
    var previewTimer = null;
    var templateCache = {};
    var settings = { grid: true, snap: true, fullscreen: false, underlay: false };
    var nextUid = 1;

    try {
        var stored = JSON.parse(window.localStorage.getItem('mo-editor-settings') || '{}');
        Object.keys(settings).forEach(function (k) { if (typeof stored[k] === 'boolean') { settings[k] = stored[k]; } });
    } catch (e) { /* ohne Speicher: Standard */ }
    function storeSettings() {
        try { window.localStorage.setItem('mo-editor-settings', JSON.stringify(settings)); } catch (e) { /* egal */ }
    }

    // ------------------------------------------------------------------ Aufbau

    function build() {
        if (el.root) {
            return;
        }
        var root = document.createElement('div');
        root.id = 'mo-editor';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'mo-ed-title');
        root.innerHTML =
            '<div class="mo-ed">' +
                '<div class="mo-ed-header">' +
                    '<strong id="mo-ed-title"></strong>' +
                    '<span class="mo-ed-kind"></span>' +
                    '<div class="mo-ed-header-tools">' +
                        '<button type="button" class="mo-ed-hbtn" data-act="undo" title="' + esc(t('editor_undo')) + ' (Strg+Z)" aria-label="' + esc(t('editor_undo')) + '"><i class="rex-icon fa-rotate-left" aria-hidden="true"></i></button>' +
                        '<button type="button" class="mo-ed-hbtn" data-act="redo" title="' + esc(t('editor_redo')) + ' (Strg+Umschalt+Z)" aria-label="' + esc(t('editor_redo')) + '"><i class="rex-icon fa-rotate-right" aria-hidden="true"></i></button>' +
                        '<span class="mo-ed-hsep"></span>' +
                        '<button type="button" class="mo-ed-hbtn" data-act="fullscreen" aria-pressed="false"></button>' +
                        '<button type="button" class="mo-ed-hbtn" data-act="close" title="' + esc(t('editor_close')) + '" aria-label="' + esc(t('editor_close')) + '"><i class="rex-icon fa-xmark" aria-hidden="true"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="mo-ed-body">' +
                    '<aside class="mo-ed-left">' +
                        '<section class="mo-ed-panel"><h3>' + esc(t('editor_tools')) + '</h3><div class="mo-ed-tools" role="toolbar" aria-label="' + esc(t('editor_tools')) + '"></div></section>' +
                        '<section class="mo-ed-panel"><h3>' + esc(t('editor_blocks')) + '</h3><div class="mo-ed-blocks"></div></section>' +
                        '<section class="mo-ed-panel"><h3>' + esc(t('editor_templates')) + '</h3>' +
                            '<select class="form-control input-sm mo-ed-template" aria-label="' + esc(t('editor_templates')) + '"></select>' +
                            '<div class="mo-ed-template-actions">' +
                                '<button type="button" class="btn btn-default btn-xs" data-act="template-load">' + esc(t('editor_template_load')) + '</button>' +
                                '<label class="mo-ed-check"><input type="checkbox" data-set="underlay"> ' + esc(t('editor_underlay')) + '</label>' +
                            '</div>' +
                            '<p class="help-block mo-ed-template-hint"></p>' +
                        '</section>' +
                    '</aside>' +
                    '<main class="mo-ed-stage">' +
                        '<div class="mo-ed-stagebar">' +
                            '<label class="mo-ed-check"><input type="checkbox" data-set="grid"> ' + esc(t('editor_grid')) + '</label>' +
                            '<label class="mo-ed-check"><input type="checkbox" data-set="snap"> ' + esc(t('editor_snap')) + '</label>' +
                            '<span class="mo-ed-count" aria-live="polite"></span>' +
                        '</div>' +
                        '<div class="mo-ed-canvas-wrap"><div class="mo-ed-canvas" tabindex="0">' +
                            '<img class="mo-ed-underlay" alt="" hidden>' +
                            '<div class="mo-ed-render" aria-hidden="true"></div>' +
                            '<div class="mo-ed-overlay"></div>' +
                            '<div class="mo-ed-marquee" hidden></div>' +
                        '</div></div>' +
                        '<p class="mo-ed-keys">' + esc(t('editor_keys')) + '</p>' +
                    '</main>' +
                    '<aside class="mo-ed-right">' +
                        '<section class="mo-ed-panel mo-ed-props"></section>' +
                        '<section class="mo-ed-panel"><h3>' + esc(t('icon_editor_preview_label')) + '</h3>' +
                            '<div class="mo-ed-previews">' +
                                '<div class="mo-ed-pv" data-pv="mono"></div>' +
                                '<div class="mo-ed-pv mo-ed-pv-duo" data-pv="duo"></div>' +
                            '</div>' +
                            '<div class="mo-ed-pv-small"><span class="mo-ed-pv-mini"></span><span class="mo-ed-pv-mini mo-ed-pv-duo"></span></div>' +
                        '</section>' +
                    '</aside>' +
                '</div>' +
                '<div class="mo-ed-footer">' +
                    '<label class="mo-ed-name"><span>' + esc(t('editor_name')) + '</span><input type="text" class="form-control input-sm" maxlength="100"></label>' +
                    '<label class="mo-ed-check mo-ed-copy" hidden><input type="checkbox"> ' + esc(t('editor_save_copy')) + '</label>' +
                    '<span class="mo-ed-error text-danger" role="alert"></span>' +
                    '<button type="button" class="btn btn-default" data-act="close">' + esc(t('icon_editor_cancel')) + '</button>' +
                    '<button type="button" class="btn btn-save" data-act="save">' + esc(t('editor_save')) + '</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(root);

        el.root = root;
        el.title = root.querySelector('#mo-ed-title');
        el.kind = root.querySelector('.mo-ed-kind');
        el.tools = root.querySelector('.mo-ed-tools');
        el.blocks = root.querySelector('.mo-ed-blocks');
        el.template = root.querySelector('.mo-ed-template');
        el.templateHint = root.querySelector('.mo-ed-template-hint');
        el.canvas = root.querySelector('.mo-ed-canvas');
        el.underlay = root.querySelector('.mo-ed-underlay');
        el.render = root.querySelector('.mo-ed-render');
        el.overlay = root.querySelector('.mo-ed-overlay');
        el.marquee = root.querySelector('.mo-ed-marquee');
        el.props = root.querySelector('.mo-ed-props');
        el.count = root.querySelector('.mo-ed-count');
        el.name = root.querySelector('.mo-ed-name input');
        el.copy = root.querySelector('.mo-ed-copy');
        el.error = root.querySelector('.mo-ed-error');
        el.fullscreenBtn = root.querySelector('[data-act="fullscreen"]');

        buildTools();
        buildBlocks();
        wireCanvas();

        root.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-act]');
            if (!btn || btn.disabled) {
                return;
            }
            var act = btn.getAttribute('data-act');
            if ('undo' === act) { undo(); }
            else if ('redo' === act) { redo(); }
            else if ('fullscreen' === act) { settings.fullscreen = !settings.fullscreen; storeSettings(); applySettings(); }
            else if ('close' === act) { close(); }
            else if ('save' === act) { save(); }
            else if ('template-load' === act) { loadTemplate(); }
        });
        root.querySelectorAll('[data-set]').forEach(function (input) {
            input.addEventListener('change', function () {
                settings[input.getAttribute('data-set')] = input.checked;
                storeSettings();
                applySettings();
            });
        });
        el.template.addEventListener('change', updateUnderlay);
        document.addEventListener('keydown', onKeydown);
    }

    function buildTools() {
        var html = toolButton('select');
        TOOL_GROUPS.forEach(function (group) {
            html += '<span class="mo-ed-toolsep" aria-hidden="true"></span>';
            group[1].forEach(function (type) { html += toolButton(type); });
        });
        el.tools.innerHTML = html;
        el.tools.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-tool]');
            if (btn) {
                setTool(btn.getAttribute('data-tool'));
            }
        });
    }

    function toolButton(name) {
        var label = 'select' === name ? t('editor_tool_select') : t('icon_editor_type_' + name);
        return '<button type="button" class="mo-ed-tool" data-tool="' + name + '" aria-pressed="false" title="' + esc(label) + '">' + glyph(name) + '<span>' + esc(label) + '</span></button>';
    }

    function buildBlocks() {
        el.blocks.innerHTML = Object.keys(BLOCKS).map(function (key) {
            return '<button type="button" class="mo-ed-block" data-block="' + key + '">' + esc(t('editor_block_' + key)) + '</button>';
        }).join('');
        el.blocks.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-block]');
            if (btn) {
                insertBlock(btn.getAttribute('data-block'));
            }
        });
    }

    function applySettings() {
        el.root.classList.toggle('is-fullscreen', settings.fullscreen);
        el.canvas.classList.toggle('has-grid', settings.grid);
        el.root.querySelectorAll('[data-set]').forEach(function (input) { input.checked = !!settings[input.getAttribute('data-set')]; });
        el.fullscreenBtn.innerHTML = '<i class="rex-icon ' + (settings.fullscreen ? 'fa-compress' : 'fa-expand') + '" aria-hidden="true"></i>';
        var label = t(settings.fullscreen ? 'editor_window' : 'editor_fullscreen');
        el.fullscreenBtn.title = label;
        el.fullscreenBtn.setAttribute('aria-label', label);
        el.fullscreenBtn.setAttribute('aria-pressed', settings.fullscreen ? 'true' : 'false');
        updateUnderlay();
        layout();
    }

    function setTool(name) {
        tool = name;
        el.tools.querySelectorAll('[data-tool]').forEach(function (b) {
            var on = b.getAttribute('data-tool') === name;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        el.canvas.classList.toggle('is-drawing', 'select' !== name);
    }

    // ------------------------------------------------------------------ Formen

    function makeShape(type, x, y, w, h, style) {
        var s = { uid: nextUid++, type: type, style: style || DEFAULT_STYLE[type] || 'soft', x: x, y: y };
        if (LINE_TYPES.indexOf(type) !== -1) {
            s.x2 = w;
            s.y2 = h;
        } else {
            s.w = w;
            s.h = h;
        }
        return s;
    }

    function fromData(d) {
        if (!d || typeof d !== 'object' || !d.type) {
            return null;
        }
        if (LINE_TYPES.indexOf(d.type) !== -1) {
            return makeShape(d.type, +d.x || 0, +d.y || 0, +d.x2 || 0, +d.y2 || 0, d.style);
        }
        return makeShape(d.type, +d.x || 0, +d.y || 0, +d.w || 1, +d.h || 1, d.style);
    }

    function toData(s) {
        if (isLine(s)) {
            return { type: s.type, style: s.style, x: round(s.x), y: round(s.y), x2: round(s.x2), y2: round(s.y2) };
        }
        return { type: s.type, style: s.style, x: round(s.x), y: round(s.y), w: round(s.w), h: round(s.h) };
    }

    function bounds(s) {
        if (isLine(s)) {
            return { x: Math.min(s.x, s.x2), y: Math.min(s.y, s.y2), w: Math.abs(s.x2 - s.x), h: Math.abs(s.y2 - s.y) };
        }
        return { x: s.x, y: s.y, w: s.w, h: s.h };
    }

    function groupBounds(list) {
        var b = null;
        list.forEach(function (s) {
            var r = bounds(s);
            if (!b) { b = { x1: r.x, y1: r.y, x2: r.x + r.w, y2: r.y + r.h }; return; }
            b.x1 = Math.min(b.x1, r.x); b.y1 = Math.min(b.y1, r.y);
            b.x2 = Math.max(b.x2, r.x + r.w); b.y2 = Math.max(b.y2, r.y + r.h);
        });
        return b ? { x: b.x1, y: b.y1, w: b.x2 - b.x1, h: b.y2 - b.y1 } : null;
    }

    function snap(v) { return settings.snap ? Math.round(v / SNAP) * SNAP : round(v); }

    function moveBy(s, dx, dy) {
        s.x += dx; s.y += dy;
        if (isLine(s)) { s.x2 += dx; s.y2 += dy; }
    }

    // Verschieben innerhalb der Fläche halten (Gruppe als Ganzes)
    function clampDelta(list, dx, dy) {
        var b = groupBounds(list);
        return {
            dx: clamp(dx, -b.x, canvas.w - (b.x + b.w)),
            dy: clamp(dy, -b.y, canvas.h - (b.y + b.h))
        };
    }

    // ------------------------------------------------------------------ Verlauf

    function snapshot() { return JSON.stringify(shapes.map(toData)); }

    function commit() {
        history.push(snapshot());
        if (history.length > HISTORY_MAX) { history.shift(); }
        future = [];
        dirty = true;
        syncHistoryButtons();
    }

    // vor einer Änderung aufrufen
    function remember() { commit(); }

    function restore(json) {
        shapes = JSON.parse(json).map(fromData).filter(Boolean);
        selected = [];
        redraw();
    }

    function undo() {
        if (!history.length) { return; }
        future.push(snapshot());
        restore(history.pop());
        dirty = true;
        syncHistoryButtons();
    }

    function redo() {
        if (!future.length) { return; }
        history.push(snapshot());
        restore(future.pop());
        dirty = true;
        syncHistoryButtons();
    }

    function syncHistoryButtons() {
        el.root.querySelector('[data-act="undo"]').disabled = !history.length;
        el.root.querySelector('[data-act="redo"]').disabled = !future.length;
    }

    // ------------------------------------------------------------------ Darstellung

    function pct(v, total) { return (v / total * 100) + '%'; }

    function redraw() {
        el.overlay.innerHTML = '';
        shapes.forEach(function (s) {
            var isSel = selected.indexOf(s) !== -1;
            if (isLine(s)) {
                var line = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                line.setAttribute('class', 'mo-ed-hitline' + (isSel ? ' is-selected' : ''));
                line.setAttribute('viewBox', '0 0 ' + canvas.w + ' ' + canvas.h);
                line.setAttribute('preserveAspectRatio', 'none');
                line.innerHTML = '<line x1="' + s.x + '" y1="' + s.y + '" x2="' + s.x2 + '" y2="' + s.y2 + '" vector-effect="non-scaling-stroke"/>';
                line.querySelector('line').__shape = s;
                el.overlay.appendChild(line);
            } else {
                var box = document.createElement('div');
                box.className = 'mo-ed-hit' + (isSel ? ' is-selected' : '');
                box.style.left = pct(s.x, canvas.w);
                box.style.top = pct(s.y, canvas.h);
                box.style.width = pct(s.w, canvas.w);
                box.style.height = pct(s.h, canvas.h);
                box.__shape = s;
                el.overlay.appendChild(box);
            }
        });
        drawHandles();
        renderProps();
        el.count.textContent = shapes.length + ' / ' + canvas.max + (selected.length ? ' · ' + selected.length + ' ' + t('editor_selected') : '');
        schedulePreview();
    }

    function drawHandles() {
        if (1 === selected.length && isLine(selected[0])) {
            var s = selected[0];
            addHandle('p1', s.x, s.y);
            addHandle('p2', s.x2, s.y2);
            return;
        }
        if (!selected.length) {
            return;
        }
        var b = groupBounds(selected);
        var frame = document.createElement('div');
        frame.className = 'mo-ed-frame';
        frame.style.left = pct(b.x, canvas.w);
        frame.style.top = pct(b.y, canvas.h);
        frame.style.width = pct(b.w, canvas.w);
        frame.style.height = pct(b.h, canvas.h);
        el.overlay.appendChild(frame);
        [['nw', 0, 0], ['n', 0.5, 0], ['ne', 1, 0], ['e', 1, 0.5], ['se', 1, 1], ['s', 0.5, 1], ['sw', 0, 1], ['w', 0, 0.5]].forEach(function (h) {
            addHandle(h[0], b.x + b.w * h[1], b.y + b.h * h[2]);
        });
    }

    function addHandle(dir, x, y) {
        var h = document.createElement('span');
        h.className = 'mo-ed-handle mo-ed-handle-' + dir;
        h.setAttribute('data-handle', dir);
        h.style.left = pct(x, canvas.w);
        h.style.top = pct(y, canvas.h);
        el.overlay.appendChild(h);
    }

    // Größe der Zeichenfläche: größtmöglich im verfügbaren Platz, Seitenverhältnis der Art
    function layout() {
        var wrap = el.canvas.parentNode;
        var availW = wrap.clientWidth;
        var availH = wrap.clientHeight;
        if (!availW || !availH) { return; }
        var ratio = canvas.w / canvas.h;
        var w = Math.min(availW, availH * ratio);
        el.canvas.style.width = Math.floor(w) + 'px';
        el.canvas.style.height = Math.floor(w / ratio) + 'px';
        el.canvas.style.setProperty('--mo-ed-cell-x', (100 / canvas.w) + '%');
        el.canvas.style.setProperty('--mo-ed-cell-y', (100 / canvas.h) + '%');
    }

    // ------------------------------------------------------------------ Eigenschaften

    function renderProps() {
        var html;
        if (!selected.length) {
            html = '<h3>' + esc(t('editor_props')) + '</h3><p class="mo-ed-muted">' + esc(t('editor_props_none')) + '</p>';
            el.props.innerHTML = html;
            return;
        }
        var one = 1 === selected.length ? selected[0] : null;
        html = '<h3>' + esc(one ? t('icon_editor_type_' + one.type) : selected.length + ' ' + t('editor_selected')) + '</h3>';
        if (one) {
            var fields = isLine(one) ? [['x', 'X1'], ['y', 'Y1'], ['x2', 'X2'], ['y2', 'Y2']] : [['x', 'X'], ['y', 'Y'], ['w', t('editor_width')], ['h', t('editor_height')]];
            html += '<div class="mo-ed-fields">' + fields.map(function (f) {
                return '<label><span>' + esc(f[1]) + '</span><input type="number" step="0.1" min="0" class="form-control input-sm" data-field="' + f[0] + '" value="' + round(one[f[0]]) + '"></label>';
            }).join('') + '</div>';
        }
        var style = selected.every(function (s) { return s.style === selected[0].style; }) ? selected[0].style : '';
        html += '<div class="mo-ed-subhead">' + esc(t('icon_editor_style')) + '</div><div class="mo-ed-styles" role="group" aria-label="' + esc(t('icon_editor_style')) + '">' +
            STYLES.map(function (k, i) {
                return '<button type="button" class="mo-ed-style mo-ed-style-' + k + (style === k ? ' is-active' : '') + '" data-style="' + k + '" aria-pressed="' + (style === k ? 'true' : 'false') + '" title="' + esc(t('icon_editor_style_' + k + '_hint')) + ' (' + (i + 1) + ')"><span class="mo-ed-swatch" aria-hidden="true"></span>' + esc(t('icon_editor_style_' + k)) + '</button>';
            }).join('') + '</div>';
        html += '<div class="mo-ed-subhead">' + esc(t('editor_align')) + '</div><div class="mo-ed-iconrow">' +
            [['left', 'fa-align-left'], ['hcenter', 'fa-align-center'], ['right', 'fa-align-right'], ['top', 'fa-arrow-up-long'], ['vcenter', 'fa-arrows-up-down'], ['bottom', 'fa-arrow-down-long']].map(function (a) {
                return '<button type="button" class="mo-ed-ibtn" data-align="' + a[0] + '" title="' + esc(t('editor_align_' + a[0])) + '" aria-label="' + esc(t('editor_align_' + a[0])) + '"><i class="rex-icon ' + a[1] + '" aria-hidden="true"></i></button>';
            }).join('') +
            (selected.length >= 3 ? '<button type="button" class="mo-ed-ibtn" data-distribute="h" title="' + esc(t('editor_distribute_h')) + '" aria-label="' + esc(t('editor_distribute_h')) + '"><i class="rex-icon fa-grip-lines-vertical" aria-hidden="true"></i></button><button type="button" class="mo-ed-ibtn" data-distribute="v" title="' + esc(t('editor_distribute_v')) + '" aria-label="' + esc(t('editor_distribute_v')) + '"><i class="rex-icon fa-grip-lines" aria-hidden="true"></i></button>' : '') +
            '</div><p class="mo-ed-muted mo-ed-align-note">' + esc(t(selected.length > 1 ? 'editor_align_selection' : 'editor_align_canvas')) + '</p>';
        html += '<div class="mo-ed-subhead">' + esc(t('editor_arrange')) + '</div><div class="mo-ed-actions">' +
            '<button type="button" class="btn btn-default btn-xs" data-order="front">' + esc(t('editor_front')) + '</button>' +
            '<button type="button" class="btn btn-default btn-xs" data-order="forward">' + esc(t('icon_editor_forward')) + '</button>' +
            '<button type="button" class="btn btn-default btn-xs" data-order="backward">' + esc(t('icon_editor_backward')) + '</button>' +
            '<button type="button" class="btn btn-default btn-xs" data-order="back">' + esc(t('editor_back')) + '</button>' +
            '</div><div class="mo-ed-actions">' +
            '<button type="button" class="btn btn-default btn-xs" data-cmd="duplicate"><i class="rex-icon fa-clone" aria-hidden="true"></i> ' + esc(t('icon_editor_duplicate')) + '</button>' +
            '<button type="button" class="btn btn-delete btn-xs" data-cmd="delete"><i class="rex-icon fa-trash" aria-hidden="true"></i> ' + esc(t('editor_delete')) + '</button>' +
            '</div>';
        el.props.innerHTML = html;
    }

    function wireProps() {
        el.props.addEventListener('change', function (event) {
            var input = event.target.closest('[data-field]');
            if (!input || 1 !== selected.length) { return; }
            var s = selected[0];
            var v = parseFloat(input.value);
            if (isNaN(v)) { renderProps(); return; }
            remember();
            var f = input.getAttribute('data-field');
            if ('w' === f) { s.w = clamp(v, 0.5, canvas.w - s.x); }
            else if ('h' === f) { s.h = clamp(v, 0.5, canvas.h - s.y); }
            else if ('x' === f) { s.x = clamp(v, 0, isLine(s) ? canvas.w : canvas.w - s.w); }
            else if ('y' === f) { s.y = clamp(v, 0, isLine(s) ? canvas.h : canvas.h - s.h); }
            else { s[f] = clamp(v, 0, 'x2' === f ? canvas.w : canvas.h); }
            redraw();
        });
        el.props.addEventListener('click', function (event) {
            var b = event.target.closest('button');
            if (!b || !selected.length) { return; }
            if (b.hasAttribute('data-style')) { setStyle(b.getAttribute('data-style')); }
            else if (b.hasAttribute('data-align')) { align(b.getAttribute('data-align')); }
            else if (b.hasAttribute('data-distribute')) { distribute(b.getAttribute('data-distribute')); }
            else if (b.hasAttribute('data-order')) { order(b.getAttribute('data-order')); }
            else if ('duplicate' === b.getAttribute('data-cmd')) { duplicate(); }
            else if ('delete' === b.getAttribute('data-cmd')) { removeSelected(); }
        });
    }

    function setStyle(style) {
        remember();
        selected.forEach(function (s) { s.style = style; });
        redraw();
    }

    function align(where) {
        remember();
        var ref = selected.length > 1 ? groupBounds(selected) : { x: 0, y: 0, w: canvas.w, h: canvas.h };
        selected.forEach(function (s) {
            var b = bounds(s);
            var dx = 0, dy = 0;
            if ('left' === where) { dx = ref.x - b.x; }
            if ('right' === where) { dx = ref.x + ref.w - (b.x + b.w); }
            if ('hcenter' === where) { dx = ref.x + ref.w / 2 - (b.x + b.w / 2); }
            if ('top' === where) { dy = ref.y - b.y; }
            if ('bottom' === where) { dy = ref.y + ref.h - (b.y + b.h); }
            if ('vcenter' === where) { dy = ref.y + ref.h / 2 - (b.y + b.h / 2); }
            moveBy(s, dx, dy);
        });
        redraw();
    }

    function distribute(axis) {
        remember();
        var key = 'h' === axis ? 'x' : 'y';
        var size = 'h' === axis ? 'w' : 'h';
        var list = selected.slice().sort(function (a, b) { return bounds(a)[key] - bounds(b)[key]; });
        var first = bounds(list[0]);
        var last = bounds(list[list.length - 1]);
        var total = list.reduce(function (sum, s) { return sum + bounds(s)[size]; }, 0);
        var gap = (last[key] + last[size] - first[key] - total) / (list.length - 1);
        var pos = first[key];
        list.forEach(function (s) {
            var b = bounds(s);
            moveBy(s, 'x' === key ? pos - b.x : 0, 'y' === key ? pos - b.y : 0);
            pos += b[size] + gap;
        });
        redraw();
    }

    function order(how) {
        remember();
        var rest = shapes.filter(function (s) { return selected.indexOf(s) === -1; });
        var sel = shapes.filter(function (s) { return selected.indexOf(s) !== -1; });
        if ('front' === how) { shapes = rest.concat(sel); }
        else if ('back' === how) { shapes = sel.concat(rest); }
        else {
            var step = 'forward' === how ? 1 : -1;
            var list = shapes.slice();
            var idx = sel.map(function (s) { return list.indexOf(s); });
            if (step > 0) { idx.reverse(); }
            idx.forEach(function (i) {
                var j = i + step;
                if (j < 0 || j >= list.length || selected.indexOf(list[j]) !== -1) { return; }
                var tmp = list[j]; list[j] = list[i]; list[i] = tmp;
            });
            shapes = list;
        }
        redraw();
    }

    function duplicate() {
        if (!selected.length || shapes.length + selected.length > canvas.max) { return; }
        remember();
        var copies = selected.map(function (s) { var c = fromData(toData(s)); return c; });
        var d = clampDelta(copies, 1, 1);
        copies.forEach(function (c) { moveBy(c, d.dx, d.dy); });
        shapes = shapes.concat(copies);
        selected = copies;
        redraw();
    }

    function removeSelected() {
        if (!selected.length) { return; }
        remember();
        shapes = shapes.filter(function (s) { return selected.indexOf(s) === -1; });
        selected = [];
        redraw();
    }

    // Formen hinzufügen (Baustein, Vorlage, Einfügen) – mittig, Auswahl = neue Formen
    function addShapes(list, center) {
        list = list.slice(0, Math.max(0, canvas.max - shapes.length));
        if (!list.length) { return; }
        remember();
        if (center) {
            var b = groupBounds(list);
            var dx = (canvas.w - b.w) / 2 - b.x;
            var dy = (canvas.h - b.h) / 2 - b.y;
            list.forEach(function (s) { moveBy(s, dx, dy); });
        }
        shapes = shapes.concat(list);
        selected = list;
        setTool('select');
        redraw();
    }

    function insertBlock(key) {
        var block = BLOCKS[key];
        var f = Math.min(canvas.scale, canvas.w / block.w, canvas.h / block.h);
        addShapes(block.shapes.map(function (d) {
            return makeShape(d[0], d[2] * f, d[3] * f, d[4] * f, d[5] * f, d[1]);
        }), true);
    }

    // ------------------------------------------------------------------ Zeichenfläche

    function toUnits(event) {
        var r = el.canvas.getBoundingClientRect();
        return {
            x: clamp((event.clientX - r.left) / r.width * canvas.w, 0, canvas.w),
            y: clamp((event.clientY - r.top) / r.height * canvas.h, 0, canvas.h)
        };
    }

    function wireCanvas() {
        wireProps();
        // Fläche immer so groß wie möglich (Fenster, Vollbild, Seitenleisten)
        if ('ResizeObserver' in window) {
            new ResizeObserver(function () { if (isOpen()) { layout(); } }).observe(el.canvas.parentNode);
        } else {
            window.addEventListener('resize', function () { if (isOpen()) { layout(); } });
        }

        el.canvas.addEventListener('pointerdown', function (event) {
            if (0 !== event.button) { return; }
            el.canvas.focus({ preventScroll: true });
            var p = toUnits(event);
            var handle = event.target.closest('[data-handle]');
            if (handle) {
                startResize(event, handle.getAttribute('data-handle'), p);
                return;
            }
            if ('select' !== tool) {
                startDraw(event, p);
                return;
            }
            var hit = event.target.__shape || (event.target.closest('.mo-ed-hit') || {}).__shape;
            if (hit) {
                if (event.shiftKey) {
                    selected = selected.indexOf(hit) !== -1 ? selected.filter(function (s) { return s !== hit; }) : selected.concat([hit]);
                    redraw();
                    return;
                }
                if (selected.indexOf(hit) === -1) { selected = [hit]; redraw(); }
                startMove(event, p);
                return;
            }
            // Rahmen im Auswahlrahmen: Gruppe verschieben
            if (event.target.closest('.mo-ed-frame')) {
                startMove(event, p);
                return;
            }
            startMarquee(event, p, event.shiftKey);
        });
    }

    function drag(event, onMove, onEnd) {
        el.canvas.setPointerCapture(event.pointerId);
        function move(e) { onMove(toUnits(e), e); }
        function up(e) {
            el.canvas.removeEventListener('pointermove', move);
            el.canvas.removeEventListener('pointerup', up);
            el.canvas.removeEventListener('pointercancel', up);
            if (onEnd) { onEnd(toUnits(e), e); }
        }
        el.canvas.addEventListener('pointermove', move);
        el.canvas.addEventListener('pointerup', up);
        el.canvas.addEventListener('pointercancel', up);
    }

    function startMove(event, start) {
        var before = snapshot();
        var origin = selected.map(toData);
        var moved = false;
        drag(event, function (p) {
            var b0 = groupBounds(origin.map(fromData));
            var dx = snap(b0.x + p.x - start.x) - b0.x;
            var dy = snap(b0.y + p.y - start.y) - b0.y;
            var d = clampDelta(origin.map(fromData), dx, dy);
            selected.forEach(function (s, i) {
                var o = origin[i];
                s.x = o.x + d.dx; s.y = o.y + d.dy;
                if (isLine(s)) { s.x2 = o.x2 + d.dx; s.y2 = o.y2 + d.dy; }
            });
            moved = moved || Math.abs(d.dx) > 0 || Math.abs(d.dy) > 0;
            redraw();
        }, function () {
            if (moved) { history.push(before); future = []; dirty = true; syncHistoryButtons(); }
        });
    }

    function startResize(event, dir, start) {
        var before = snapshot();
        var changed = false;
        if (1 === selected.length && isLine(selected[0])) {
            var line = selected[0];
            drag(event, function (p) {
                if ('p1' === dir) { line.x = snap(p.x); line.y = snap(p.y); } else { line.x2 = snap(p.x); line.y2 = snap(p.y); }
                changed = true;
                redraw();
            }, done);
            return;
        }
        var b0 = groupBounds(selected);
        var origin = selected.map(toData);
        drag(event, function (p, e) {
            var x1 = b0.x, y1 = b0.y, x2 = b0.x + b0.w, y2 = b0.y + b0.h;
            if (dir.indexOf('w') !== -1) { x1 = Math.min(snap(p.x), x2 - 0.5); }
            if (dir.indexOf('e') !== -1) { x2 = Math.max(snap(p.x), x1 + 0.5); }
            if (dir.indexOf('n') !== -1) { y1 = Math.min(snap(p.y), y2 - 0.5); }
            if (dir.indexOf('s') !== -1) { y2 = Math.max(snap(p.y), y1 + 0.5); }
            var sx = (x2 - x1) / (b0.w || 1);
            var sy = (y2 - y1) / (b0.h || 1);
            if (e.shiftKey && dir.length === 2) {
                // Seitenverhältnis halten
                var f = Math.max(sx, sy);
                sx = sy = f;
                if (dir.indexOf('w') !== -1) { x1 = x2 - b0.w * f; } else { x2 = x1 + b0.w * f; }
                if (dir.indexOf('n') !== -1) { y1 = y2 - b0.h * f; } else { y2 = y1 + b0.h * f; }
            }
            selected.forEach(function (s, i) {
                var o = origin[i];
                s.x = x1 + (o.x - b0.x) * sx;
                s.y = y1 + (o.y - b0.y) * sy;
                if (isLine(s)) {
                    s.x2 = x1 + (o.x2 - b0.x) * sx;
                    s.y2 = y1 + (o.y2 - b0.y) * sy;
                } else {
                    s.w = Math.max(0.2, o.w * sx);
                    s.h = Math.max(0.2, o.h * sy);
                }
                fitIn(s);
            });
            changed = true;
            redraw();
        }, done);
        function done() {
            if (changed) { history.push(before); future = []; dirty = true; syncHistoryButtons(); }
        }
    }

    function fitIn(s) {
        if (isLine(s)) {
            s.x = clamp(s.x, 0, canvas.w); s.x2 = clamp(s.x2, 0, canvas.w);
            s.y = clamp(s.y, 0, canvas.h); s.y2 = clamp(s.y2, 0, canvas.h);
            return;
        }
        s.x = clamp(s.x, 0, canvas.w - 0.2);
        s.y = clamp(s.y, 0, canvas.h - 0.2);
        s.w = clamp(s.w, 0.2, canvas.w - s.x);
        s.h = clamp(s.h, 0.2, canvas.h - s.y);
    }

    function startDraw(event, start) {
        if (shapes.length >= canvas.max) { return; }
        var type = tool;
        var before = snapshot();
        var s = makeShape(type, snap(start.x), snap(start.y), snap(start.x), snap(start.y));
        if (!isLine(s)) { s.w = 0; s.h = 0; }
        shapes.push(s);
        selected = [s];
        var dragged = false;
        drag(event, function (p, e) {
            dragged = true;
            if (isLine(s)) {
                s.x2 = snap(p.x); s.y2 = snap(p.y);
            } else {
                var x2 = snap(p.x), y2 = snap(p.y);
                var w = Math.abs(x2 - start.x), h = Math.abs(y2 - start.y);
                if (e.shiftKey) { w = h = Math.max(w, h); }
                s.x = x2 < start.x ? snap(start.x) - w : snap(start.x);
                s.y = y2 < start.y ? snap(start.y) - h : snap(start.y);
                s.w = w; s.h = h;
                fitIn(s);
            }
            redraw();
        }, function () {
            // nur geklickt: Standardgröße
            if (isLine(s) ? (s.x === s.x2 && s.y === s.y2) : (s.w < 0.5 || s.h < 0.5)) {
                if (isLine(s)) {
                    s.x2 = clamp(s.x + canvas.w / 4, 0, canvas.w);
                } else {
                    var size = DEFAULT_SIZE[type] || [6, 4];
                    var f = canvas.scale;
                    s.w = Math.min(size[0] * f, canvas.w);
                    s.h = Math.min(size[1] * f, canvas.h);
                    s.x = Math.min(s.x, canvas.w - s.w);
                    s.y = Math.min(s.y, canvas.h - s.h);
                }
            }
            history.push(before); future = []; dirty = true; syncHistoryButtons();
            setTool('select');
            redraw();
        });
        redraw();
    }

    function startMarquee(event, start, add) {
        var base = add ? selected.slice() : [];
        el.marquee.hidden = false;
        function box(p) {
            return { x: Math.min(p.x, start.x), y: Math.min(p.y, start.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) };
        }
        drag(event, function (p) {
            var m = box(p);
            el.marquee.style.left = pct(m.x, canvas.w);
            el.marquee.style.top = pct(m.y, canvas.h);
            el.marquee.style.width = pct(m.w, canvas.w);
            el.marquee.style.height = pct(m.h, canvas.h);
            var hits = shapes.filter(function (s) {
                var b = bounds(s);
                return b.x < m.x + m.w && b.x + b.w > m.x && b.y < m.y + m.h && b.y + b.h > m.y;
            });
            selected = base.concat(hits.filter(function (s) { return base.indexOf(s) === -1; }));
            redraw();
        }, function (p) {
            el.marquee.hidden = true;
            var m = box(p);
            if (m.w < 0.2 && m.h < 0.2 && !add) { selected = []; redraw(); }
        });
    }

    // ------------------------------------------------------------------ Tastatur

    function isOpen() { return el.root && el.root.classList.contains('mo-open'); }

    function onKeydown(event) {
        if (!isOpen()) { return; }
        var tag = (event.target && event.target.tagName) || '';
        var typing = 'INPUT' === tag || 'TEXTAREA' === tag || 'SELECT' === tag;
        var mod = event.metaKey || event.ctrlKey;
        if ('Escape' === event.key) {
            event.preventDefault();
            if ('select' !== tool) { setTool('select'); }
            else if (selected.length) { selected = []; redraw(); }
            else { close(); }
            return;
        }
        if (typing) { return; }
        var key = event.key.toLowerCase();
        if (mod && 'z' === key) { event.preventDefault(); if (event.shiftKey) { redo(); } else { undo(); } return; }
        if (mod && 'y' === key) { event.preventDefault(); redo(); return; }
        if (mod && 'a' === key) { event.preventDefault(); selected = shapes.slice(); redraw(); return; }
        if (mod && 'c' === key && selected.length) { event.preventDefault(); clipboard = selected.map(toData); return; }
        if (mod && 'x' === key && selected.length) { event.preventDefault(); clipboard = selected.map(toData); removeSelected(); return; }
        if (mod && 'v' === key && clipboard) {
            event.preventDefault();
            var list = clipboard.map(fromData).filter(Boolean);
            var d = clampDelta(list, 1, 1);
            list.forEach(function (s) { moveBy(s, d.dx, d.dy); });
            clipboard = list.map(toData);
            addShapes(list, false);
            return;
        }
        if (mod && 'd' === key) { event.preventDefault(); duplicate(); return; }
        if (!mod && 'v' === key) { setTool('select'); return; }
        if (!selected.length) { return; }
        var step = event.shiftKey ? 2 : (settings.snap ? SNAP : 0.1);
        var arrows = { arrowleft: [-step, 0], arrowright: [step, 0], arrowup: [0, -step], arrowdown: [0, step] };
        if (arrows[key]) {
            event.preventDefault();
            remember();
            if (event.altKey) {
                selected.forEach(function (s) {
                    if (isLine(s)) { s.x2 += arrows[key][0]; s.y2 += arrows[key][1]; } else { s.w = Math.max(0.2, s.w + arrows[key][0]); s.h = Math.max(0.2, s.h + arrows[key][1]); }
                    fitIn(s);
                });
            } else {
                var dd = clampDelta(selected, arrows[key][0], arrows[key][1]);
                selected.forEach(function (s) { moveBy(s, dd.dx, dd.dy); });
            }
            redraw();
            return;
        }
        if ('delete' === key || 'backspace' === key) { event.preventDefault(); removeSelected(); return; }
        if (/^[1-5]$/.test(event.key) && !mod && !event.altKey) { setStyle(STYLES[parseInt(event.key, 10) - 1]); }
    }

    // ------------------------------------------------------------------ Vorlagen

    function loadTemplates() {
        el.template.innerHTML = '<option value="">' + esc(t('editor_template_choose')) + '</option>';
        var cached = templateCache[kind];
        var ready = cached ? Promise.resolve(cached) : fetch('index.php?rex-api-call=module_organizer_editor_templates&kind=' + kind, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) { templateCache[kind] = data; return data; });
        ready.then(function (data) {
            var html = '<option value="">' + esc(t('editor_template_choose')) + '</option>';
            if (data.own && data.own.length) {
                html += '<optgroup label="' + esc(t('editor_template_own')) + '">' + data.own.map(function (o) {
                    return '<option value="own:' + o.id + '">' + esc(o.title) + '</option>';
                }).join('') + '</optgroup>';
            }
            html += '<optgroup label="' + esc(t('editor_template_presets')) + '">' + data.presets.map(function (p) {
                return '<option value="preset:' + esc(p.key) + '">' + esc(p.label) + '</option>';
            }).join('') + '</optgroup>';
            el.template.innerHTML = html;
            updateUnderlay();
        }).catch(function () { /* Vorlagen sind optional */ });
    }

    function currentTemplate() {
        var data = templateCache[kind];
        var value = el.template.value;
        if (!data || !value) { return null; }
        var parts = value.split(':');
        if ('own' === parts[0]) {
            return data.own.filter(function (o) { return String(o.id) === parts[1]; })[0] || null;
        }
        return data.presets.filter(function (p) { return p.key === parts.slice(1).join(':'); })[0] || null;
    }

    function updateUnderlay() {
        if (!el.root) { return; }
        var tpl = currentTemplate();
        var loadBtn = el.root.querySelector('[data-act="template-load"]');
        loadBtn.disabled = !tpl || !tpl.shapes;
        el.templateHint.textContent = tpl && !tpl.shapes ? t('editor_template_trace_only') : '';
        if (tpl && settings.underlay) {
            var src = tpl.svg && 0 === String(tpl.svg).indexOf('<') ? 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(tpl.svg) : tpl.svg;
            el.underlay.src = src;
            el.underlay.hidden = false;
        } else {
            el.underlay.hidden = true;
            el.underlay.removeAttribute('src');
        }
    }

    function loadTemplate() {
        var tpl = currentTemplate();
        if (!tpl || !tpl.shapes) { return; }
        if (shapes.length && !window.confirm(t('editor_template_replace'))) { return; }
        remember();
        shapes = tpl.shapes.map(fromData).filter(Boolean).slice(0, canvas.max);
        selected = [];
        if (!el.name.value && tpl.label) { el.name.value = tpl.label; }
        redraw();
    }

    // ------------------------------------------------------------------ Vorschau + Speichern

    function payload() { return JSON.stringify(shapes.map(toData)); }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreview, PREVIEW_DEBOUNCE);
    }

    function renderPreview() {
        var targets = el.root.querySelectorAll('.mo-ed-render, .mo-ed-pv, .mo-ed-pv-mini');
        if (!shapes.length) {
            targets.forEach(function (n) { n.innerHTML = ''; });
            return;
        }
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'rex-api-call=module_organizer_preview_custom_icon&kind=' + kind + '&shapes=' + encodeURIComponent(payload())
        }).then(function (r) { return r.json(); }).then(function (result) {
            if (result && result.success) {
                // das SVG baut der Server aus Typ+Koordinaten (kein Client-Markup)
                targets.forEach(function (n) { n.innerHTML = result.svg || ''; });
            }
        }).catch(function () { /* nur Vorschau */ });
    }

    function save() {
        el.error.textContent = '';
        if (!shapes.length) {
            el.error.textContent = t('editor_empty');
            return;
        }
        var asCopy = !el.copy.hidden && el.copy.querySelector('input').checked;
        var body = 'rex-api-call=module_organizer_save_custom_icon&kind=' + kind
            + '&shapes=' + encodeURIComponent(payload())
            + '&title=' + encodeURIComponent(el.name.value.trim())
            + '&id=' + encodeURIComponent(editingId && !asCopy ? editingId : '');
        var btn = el.root.querySelector('[data-act="save"]');
        btn.disabled = true;
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); }).then(function (result) {
            btn.disabled = false;
            if (result && result.success) {
                dirty = false;
                delete templateCache[kind];
                if (onSave) { onSave(result.id, result.svg, kind); }
                close(true);
            } else {
                el.error.textContent = t('save_failed');
            }
        }).catch(function () {
            btn.disabled = false;
            el.error.textContent = t('save_failed');
        });
    }

    // ------------------------------------------------------------------ Öffnen / Schließen

    var lastFocus = null;

    function open(callback, options) {
        options = options || {};
        build();
        kind = 'preview' === options.kind ? 'preview' : 'icon';
        canvas = KINDS[kind];
        onSave = callback || null;
        editingId = options.id || null;
        shapes = (options.shapes || []).map(fromData).filter(Boolean);
        selected = [];
        history = [];
        future = [];
        dirty = false;
        el.title.textContent = t(editingId ? 'editor_title_edit' : 'editor_title_new_' + kind);
        el.kind.textContent = t('editor_kind_' + kind) + ' · ' + canvas.w + '×' + canvas.h;
        el.name.value = options.title || '';
        el.copy.hidden = !editingId;
        el.copy.querySelector('input').checked = false;
        el.error.textContent = '';
        el.root.setAttribute('data-kind', kind);
        lastFocus = document.activeElement;
        el.root.classList.add('mo-open');
        document.documentElement.classList.add('mo-editor-open');
        setTool('select');
        applySettings();
        syncHistoryButtons();
        loadTemplates();
        redraw();
        requestAnimationFrame(function () { layout(); el.canvas.focus({ preventScroll: true }); });
    }

    function close(force) {
        if (!isOpen()) { return; }
        if (true !== force && dirty && !window.confirm(t('editor_discard'))) { return; }
        el.root.classList.remove('mo-open');
        document.documentElement.classList.remove('mo-editor-open');
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }

    window.MOIconEditor = { open: open };
})();
