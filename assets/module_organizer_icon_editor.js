(function () {
    'use strict';

    // Mini-Editor zum Zeichnen eigener Layout-Vorschau-Icons: Rechtecke/Linien
    // auf einer 24x18-Flaeche (dasselbe Raster wie die statischen Presets
    // unter assets/icons/) ziehen, verschieben, resizen, je einen Platzhalter-
    // Typ zuweisen. Das Ergebnis wird NIE als rohes SVG an den Server
    // geschickt, sondern nur als Typ+Koordinaten-Liste - lib/CustomIconRenderer.php
    // baut daraus serverseitig das tatsaechliche SVG (Sicherheit + Stilkonsistenz
    // mit den Presets). Die Live-Vorschau (rechts neben der Zeichenflaeche)
    // ruft dafuer bei jeder Aenderung genau diesen Server-Renderer per
    // debounced Fetch auf - kein separates, potenziell abweichendes
    // JS-Nachbau-Rendering, damit "was man zeichnet" = "was gespeichert wird".
    var CANVAS_W = 24;
    var CANVAS_H = 18;
    var MAX_SHAPES = 16;
    var NUDGE = 0.5;
    var PREVIEW_DEBOUNCE = 150;

    var BOX_TYPES = ['text', 'heading', 'image', 'video', 'media', 'form', 'rect', 'circle', 'button', 'star', 'pin', 'check'];
    var LINE_TYPES = ['line', 'arrow'];
    var TYPES = BOX_TYPES.concat(LINE_TYPES);

    // Füllstile (siehe lib/CustomIconRenderer.php::STYLES) - Duotone faerbt
    // "soft" als Hauch und "accent" kraeftig in der Akzentfarbe, "solid"
    // bleibt in der Konturfarbe, "outline" ist nur Kontur.
    var STYLES = ['outline', 'soft', 'accent', 'solid'];
    var DEFAULT_STYLE = {
        heading: 'solid', text: 'solid', line: 'solid', arrow: 'solid',
        button: 'accent', star: 'accent', pin: 'accent', check: 'accent'
    };

    function defaultStyle(type) {
        return DEFAULT_STYLE[type] || 'soft';
    }

    var modal = null;
    var canvasEl = null;
    var previewEl = null;
    var shapes = []; // { el, type, style, x, y, w, h } bzw. bei Linien/Pfeilen: { el, type, style, x, y, x2, y2 }
    var styleBar = null;
    var activeType = 'image';
    var activeShape = null;
    var onSaveCallback = null;
    var editingIconId = null;
    var previewTimer = null;

    // JS-Uebersetzungen: boot.php liefert sie ueber
    // rex_view::setJsProperty('module_organizer', ...) - Teil des Backend-
    // globalen "rex"-JS-Objekts, das der Core bereits im <head> ausgibt.
    var i18nDict = (window.rex && window.rex.module_organizer && window.rex.module_organizer.i18n) || {};

    function t(key) {
        return i18nDict['module_organizer_' + key] || key;
    }

    function isLine(shape) {
        return LINE_TYPES.indexOf(shape.type) !== -1;
    }

    function build() {
        if (modal) {
            return;
        }

        modal = document.createElement('div');
        modal.id = 'mo-icon-editor';
        modal.innerHTML =
            '<div class="mo-icon-editor-box">' +
                '<div class="mo-icon-editor-header">' +
                    '<strong>' + t('icon_editor_title') + '</strong>' +
                    '<button type="button" class="mo-icon-editor-close">&times;</button>' +
                '</div>' +
                '<div class="mo-icon-editor-body">' +
                    '<div class="mo-icon-editor-toolbar"></div>' +
                    '<div class="mo-icon-editor-actions"></div>' +
                    '<div class="mo-icon-editor-workarea">' +
                        '<div class="mo-icon-editor-canvas-wrap">' +
                            '<div class="mo-icon-editor-canvas"></div>' +
                            '<p class="help-block">' + t('icon_editor_hint') + ' <span class="mo-icon-editor-count"></span></p>' +
                            '<p class="help-block mo-icon-editor-keys">' + t('icon_editor_keys') + '</p>' +
                        '</div>' +
                        '<div class="mo-icon-editor-preview-wrap">' +
                            '<div class="mo-icon-editor-preview-label">' + t('icon_editor_preview_label') + '</div>' +
                            '<div class="mo-icon-editor-preview"></div>' +
                            '<div class="mo-icon-editor-preview-label">' + t('icon_editor_preview_duotone') + '</div>' +
                            '<div class="mo-icon-editor-preview mo-icon-editor-preview-duo"></div>' +
                            '<div class="mo-icon-editor-preview-small">' +
                                '<span class="mo-icon-editor-preview-mini"></span>' +
                                '<span class="mo-icon-editor-preview-mini mo-icon-editor-preview-duo"></span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="mo-icon-editor-footer">' +
                    '<button type="button" class="btn btn-default mo-icon-editor-cancel">' + t('icon_editor_cancel') + '</button>' +
                    '<button type="button" class="btn btn-save mo-icon-editor-save">' + t('icon_editor_save') + '</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);

        canvasEl = modal.querySelector('.mo-icon-editor-canvas');
        previewEl = modal.querySelector('.mo-icon-editor-preview');
        buildToolbar();
        buildActions();
        wireCanvas();
        document.addEventListener('keydown', onKeydown);

        modal.querySelector('.mo-icon-editor-close').addEventListener('click', close);
        modal.querySelector('.mo-icon-editor-cancel').addEventListener('click', close);
        modal.querySelector('.mo-icon-editor-save').addEventListener('click', save);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                close();
            }
        });
    }

    function buildToolbar() {
        var toolbar = modal.querySelector('.mo-icon-editor-toolbar');
        toolbar.innerHTML = '';

        TYPES.forEach(function (typeKey) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mo-icon-editor-tool' + (typeKey === activeType ? ' is-active' : '');
            btn.textContent = t('icon_editor_type_' + typeKey);
            btn.setAttribute('aria-pressed', typeKey === activeType ? 'true' : 'false');
            btn.setAttribute('data-type', typeKey);
            btn.addEventListener('click', function () {
                activeType = typeKey;
                toolbar.querySelectorAll('.mo-icon-editor-tool').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                    b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
                });
            });
            toolbar.appendChild(btn);
        });

    }

    function actionButton(container, key, icon, handler, extraClass) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mo-icon-editor-tool ' + (extraClass || '');
        btn.innerHTML = '<i class="rex-icon ' + icon + '" aria-hidden="true"></i> ' + t('icon_editor_' + key);
        btn.addEventListener('click', handler);
        container.appendChild(btn);
        return btn;
    }

    // Zweite Leiste: Fuellstil der markierten Form + Bearbeiten (Duplizieren,
    // Ebenen, Loeschen). Ohne Auswahl ausgegraut.
    function buildActions() {
        var bar = modal.querySelector('.mo-icon-editor-actions');
        bar.innerHTML = '';

        styleBar = document.createElement('div');
        styleBar.className = 'mo-icon-editor-styles';
        styleBar.setAttribute('role', 'group');
        styleBar.setAttribute('aria-label', t('icon_editor_style'));
        styleBar.innerHTML = '<span class="mo-icon-editor-styles-label">' + t('icon_editor_style') + '</span>';
        STYLES.forEach(function (styleKey) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mo-icon-editor-style mo-icon-editor-style-' + styleKey;
            btn.setAttribute('data-style', styleKey);
            btn.setAttribute('aria-pressed', 'false');
            btn.title = t('icon_editor_style_' + styleKey + '_hint');
            btn.innerHTML = '<span class="mo-icon-editor-swatch" aria-hidden="true"></span>' + t('icon_editor_style_' + styleKey);
            btn.addEventListener('click', function () {
                if (activeShape) {
                    activeShape.style = styleKey;
                    applyShapeClasses(activeShape);
                    syncActions();
                    schedulePreview();
                }
            });
            styleBar.appendChild(btn);
        });
        bar.appendChild(styleBar);

        var edit = document.createElement('div');
        edit.className = 'mo-icon-editor-edit';
        actionButton(edit, 'duplicate', 'fa-clone', duplicateActive, 'mo-needs-shape');
        actionButton(edit, 'forward', 'fa-arrow-up', function () { moveLayer(1); }, 'mo-needs-shape');
        actionButton(edit, 'backward', 'fa-arrow-down', function () { moveLayer(-1); }, 'mo-needs-shape');
        actionButton(edit, 'delete_shape', 'fa-trash-o', function () {
            if (activeShape) {
                removeShape(activeShape);
                schedulePreview();
            }
        }, 'mo-needs-shape mo-icon-editor-delete-btn');
        actionButton(edit, 'clear', 'fa-eraser', function () {
            shapes.slice().forEach(removeShape);
            schedulePreview();
        }, 'mo-icon-editor-clear-btn');
        bar.appendChild(edit);
        syncActions();
    }

    function syncActions() {
        if (!modal) {
            return;
        }
        modal.querySelectorAll('.mo-needs-shape').forEach(function (btn) {
            btn.disabled = !activeShape;
        });
        modal.querySelectorAll('.mo-icon-editor-style').forEach(function (btn) {
            var on = !!activeShape && activeShape.style === btn.getAttribute('data-style');
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.disabled = !activeShape;
        });
        var full = shapes.length >= MAX_SHAPES;
        canvasEl.classList.toggle('is-full', full);
        var counter = modal.querySelector('.mo-icon-editor-count');
        if (counter) {
            counter.textContent = shapes.length + ' / ' + MAX_SHAPES;
        }
    }

    function applyShapeClasses(shape) {
        STYLES.forEach(function (styleKey) {
            shape.el.classList.toggle('mo-style-' + styleKey, shape.style === styleKey);
        });
    }

    function cloneShape(shape) {
        var copy = { type: shape.type, style: shape.style, x: shape.x, y: shape.y };
        var offset = NUDGE * 2;
        if (isLine(shape)) {
            copy.x2 = shape.x2;
            copy.y2 = shape.y2;
            var maxX = Math.max(shape.x, shape.x2);
            var maxY = Math.max(shape.y, shape.y2);
            var dx = maxX + offset <= CANVAS_W ? offset : 0;
            var dy = maxY + offset <= CANVAS_H ? offset : 0;
            copy.x += dx; copy.x2 += dx; copy.y += dy; copy.y2 += dy;
        } else {
            copy.w = shape.w;
            copy.h = shape.h;
            copy.x = clamp(shape.x + offset, 0, CANVAS_W - shape.w);
            copy.y = clamp(shape.y + offset, 0, CANVAS_H - shape.h);
        }
        return copy;
    }

    function duplicateActive() {
        if (!activeShape || shapes.length >= MAX_SHAPES) {
            return;
        }
        var copy = cloneShape(activeShape);
        createShapeElement(copy);
        shapes.push(copy);
        selectShape(copy);
        schedulePreview();
    }

    // Ebenenreihenfolge = Reihenfolge im Array = Reihenfolge im SVG.
    function moveLayer(direction) {
        if (!activeShape) {
            return;
        }
        var index = shapes.indexOf(activeShape);
        var target = index + direction;
        if (target < 0 || target >= shapes.length) {
            return;
        }
        shapes.splice(index, 1);
        shapes.splice(target, 0, activeShape);
        restack();
        schedulePreview();
    }

    function restack() {
        shapes.forEach(function (s) {
            canvasEl.appendChild(s.el);
            if (s.handleStart) {
                canvasEl.appendChild(s.handleStart);
                canvasEl.appendChild(s.handleEnd);
            }
        });
    }

    function nudge(shape, dx, dy) {
        if (isLine(shape)) {
            var minX = Math.min(shape.x, shape.x2);
            var maxX = Math.max(shape.x, shape.x2);
            var minY = Math.min(shape.y, shape.y2);
            var maxY = Math.max(shape.y, shape.y2);
            dx = clamp(dx, -minX, CANVAS_W - maxX);
            dy = clamp(dy, -minY, CANVAS_H - maxY);
            shape.x += dx; shape.x2 += dx; shape.y += dy; shape.y2 += dy;
        } else {
            shape.x = clamp(shape.x + dx, 0, CANVAS_W - shape.w);
            shape.y = clamp(shape.y + dy, 0, CANVAS_H - shape.h);
        }
        updateShapeStyle(shape);
    }

    function resizeBy(shape, dw, dh) {
        if (isLine(shape)) {
            shape.x2 = clamp(shape.x2 + dw, 0, CANVAS_W);
            shape.y2 = clamp(shape.y2 + dh, 0, CANVAS_H);
        } else {
            shape.w = clamp(shape.w + dw, 1, CANVAS_W - shape.x);
            shape.h = clamp(shape.h + dh, 1, CANVAS_H - shape.y);
        }
        updateShapeStyle(shape);
    }

    // Tastatur, solange der Editor offen ist: Pfeile verschieben (Umschalt =
    // groesserer Schritt), Alt+Pfeile aendern die Groesse, Entf loescht,
    // Strg/Cmd+D dupliziert, Tab wechselt die Form, Esc schliesst.
    function onKeydown(event) {
        if (!modal || !modal.classList.contains('mo-open')) {
            return;
        }
        var tag = (event.target && event.target.tagName) || '';
        if ('INPUT' === tag || 'TEXTAREA' === tag || 'SELECT' === tag) {
            return;
        }
        if ('Escape' === event.key) {
            event.preventDefault();
            if (activeShape) {
                selectShape(null);
            } else {
                close();
            }
            return;
        }
        if ('Tab' === event.key && shapes.length && event.target === canvasEl) {
            event.preventDefault();
            var index = shapes.indexOf(activeShape);
            var next = (index + (event.shiftKey ? -1 : 1) + shapes.length) % shapes.length;
            selectShape(shapes[next]);
            return;
        }
        if (!activeShape) {
            return;
        }
        var step = event.shiftKey ? NUDGE * 4 : NUDGE;
        var arrows = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (arrows[event.key]) {
            event.preventDefault();
            if (event.altKey) {
                resizeBy(activeShape, arrows[event.key][0], arrows[event.key][1]);
            } else {
                nudge(activeShape, arrows[event.key][0], arrows[event.key][1]);
            }
            schedulePreview();
            return;
        }
        if ('Delete' === event.key || 'Backspace' === event.key) {
            event.preventDefault();
            removeShape(activeShape);
            schedulePreview();
            return;
        }
        if ((event.metaKey || event.ctrlKey) && 'd' === event.key.toLowerCase()) {
            event.preventDefault();
            duplicateActive();
            return;
        }
        if (!event.metaKey && !event.ctrlKey && !event.altKey && /^[1-4]$/.test(event.key)) {
            activeShape.style = STYLES[parseInt(event.key, 10) - 1];
            applyShapeClasses(activeShape);
            syncActions();
            schedulePreview();
        }
    }

    function toCanvasCoords(clientX, clientY) {
        var rect = canvasEl.getBoundingClientRect();
        var x = (clientX - rect.left) / rect.width * CANVAS_W;
        var y = (clientY - rect.top) / rect.height * CANVAS_H;
        return { x: clamp(x, 0, CANVAS_W), y: clamp(y, 0, CANVAS_H) };
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function snap(value) {
        return Math.round(value * 2) / 2; // 0.5-Raster
    }

    function selectShape(shape) {
        activeShape = shape;
        shapes.forEach(function (s) {
            s.el.classList.toggle('is-selected', s === shape);
        });
        syncActions();
    }

    function removeShape(shape) {
        shape.el.remove();
        // Linien haben zusaetzlich zwei separate Endpunkt-Handle-Elemente
        // (siehe createLineElement) - die haengen nicht unter shape.el und
        // muessen einzeln entfernt werden, sonst bleiben die Punkte nach dem
        // Loeschen der Linie verwaist auf der Canvas sichtbar.
        if (shape.handleStart) {
            shape.handleStart.remove();
        }
        if (shape.handleEnd) {
            shape.handleEnd.remove();
        }
        shapes = shapes.filter(function (s) { return s !== shape; });
        if (activeShape === shape) {
            activeShape = null;
        }
        syncActions();
    }

    function updateShapeStyle(shape) {
        if (isLine(shape)) {
            var x1 = shape.x / CANVAS_W * 100;
            var y1 = shape.y / CANVAS_H * 100;
            var x2 = shape.x2 / CANVAS_W * 100;
            var y2 = shape.y2 / CANVAS_H * 100;
            // Linien-Handle: eigenes <div> je Endpunkt statt Box-Resize -
            // die eigentliche Linie wird als CSS-Segment zwischen beiden
            // Punkten gezeichnet (rotiertes 2px-Element), damit man beim
            // Ziehen sofort sieht, wo sie tatsaechlich verlaeuft.
            var dxPct = x2 - x1;
            var dyPct = y2 - y1;
            var rect = canvasEl.getBoundingClientRect();
            var dxPx = dxPct / 100 * rect.width;
            var dyPx = dyPct / 100 * rect.height;
            var lengthPx = Math.sqrt(dxPx * dxPx + dyPx * dyPx) || 0.01;
            var angleDeg = Math.atan2(dyPx, dxPx) * (180 / Math.PI);

            shape.el.style.left = x1 + '%';
            shape.el.style.top = y1 + '%';
            shape.el.style.width = lengthPx + 'px';
            shape.el.style.height = '0';
            shape.el.style.transform = 'rotate(' + angleDeg + 'deg)';
            shape.el.style.transformOrigin = '0 0';

            if (shape.handleEnd) {
                shape.handleEnd.style.left = x2 + '%';
                shape.handleEnd.style.top = y2 + '%';
            }
            if (shape.handleStart) {
                shape.handleStart.style.left = x1 + '%';
                shape.handleStart.style.top = y1 + '%';
            }
            return;
        }

        shape.el.style.left = (shape.x / CANVAS_W * 100) + '%';
        shape.el.style.top = (shape.y / CANVAS_H * 100) + '%';
        shape.el.style.width = (shape.w / CANVAS_W * 100) + '%';
        shape.el.style.height = (shape.h / CANVAS_H * 100) + '%';
    }

    function createShapeElement(shape) {
        if (isLine(shape)) {
            return createLineElement(shape);
        }

        var el = document.createElement('div');
        el.className = 'mo-icon-editor-shape mo-icon-editor-shape-' + shape.type;
        if (!shape.style) {
            shape.style = defaultStyle(shape.type);
        }
        el.innerHTML = '<span class="mo-icon-editor-shape-label">' + t('icon_editor_type_' + shape.type) + '</span>'
            + '<span class="mo-icon-editor-handle mo-icon-editor-handle-se"></span>';
        shape.el = el;
        applyShapeClasses(shape);
        updateShapeStyle(shape);
        canvasEl.appendChild(el);

        el.addEventListener('pointerdown', function (event) {
            if (event.target.classList.contains('mo-icon-editor-handle-se')) {
                return; // Resize wird separat behandelt.
            }
            event.stopPropagation();
            canvasEl.focus({ preventScroll: true });
            selectShape(shape);
            startDragMove(event, shape);
        });

        var handle = el.querySelector('.mo-icon-editor-handle-se');
        handle.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
            selectShape(shape);
            startDragResize(event, shape);
        });

        return el;
    }

    function createLineElement(shape) {
        var el = document.createElement('div');
        el.className = 'mo-icon-editor-line mo-icon-editor-line-' + shape.type;
        if (!shape.style) {
            shape.style = defaultStyle(shape.type);
        }

        var handleStart = document.createElement('span');
        handleStart.className = 'mo-icon-editor-handle mo-icon-editor-line-handle mo-icon-editor-line-handle-start';
        var handleEnd = document.createElement('span');
        handleEnd.className = 'mo-icon-editor-handle mo-icon-editor-line-handle mo-icon-editor-line-handle-end';

        shape.el = el;
        shape.handleStart = handleStart;
        shape.handleEnd = handleEnd;
        applyShapeClasses(shape);
        canvasEl.appendChild(el);
        canvasEl.appendChild(handleStart);
        canvasEl.appendChild(handleEnd);
        updateShapeStyle(shape);

        el.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
            canvasEl.focus({ preventScroll: true });
            selectShape(shape);
            startDragMoveLine(event, shape);
        });
        handleStart.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
            selectShape(shape);
            startDragLineEndpoint(event, shape, 'x', 'y');
        });
        handleEnd.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
            selectShape(shape);
            startDragLineEndpoint(event, shape, 'x2', 'y2');
        });

        return el;
    }

    function startDragLineEndpoint(event, shape, xKey, yKey) {
        function onMove(moveEvent) {
            var point = toCanvasCoords(moveEvent.clientX, moveEvent.clientY);
            shape[xKey] = snap(point.x);
            shape[yKey] = snap(point.y);
            updateShapeStyle(shape);
        }

        function onUp() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            schedulePreview();
        }

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    }

    function startDragMoveLine(event, shape) {
        var startPoint = toCanvasCoords(event.clientX, event.clientY);
        var originX = shape.x;
        var originY = shape.y;
        var originX2 = shape.x2;
        var originY2 = shape.y2;

        function onMove(moveEvent) {
            var point = toCanvasCoords(moveEvent.clientX, moveEvent.clientY);
            var dx = point.x - startPoint.x;
            var dy = point.y - startPoint.y;
            shape.x = clamp(snap(originX + dx), 0, CANVAS_W);
            shape.y = clamp(snap(originY + dy), 0, CANVAS_H);
            shape.x2 = clamp(snap(originX2 + dx), 0, CANVAS_W);
            shape.y2 = clamp(snap(originY2 + dy), 0, CANVAS_H);
            updateShapeStyle(shape);
        }

        function onUp() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            schedulePreview();
        }

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    }

    function startDragMove(event, shape) {
        var startPoint = toCanvasCoords(event.clientX, event.clientY);
        var originX = shape.x;
        var originY = shape.y;

        function onMove(moveEvent) {
            var point = toCanvasCoords(moveEvent.clientX, moveEvent.clientY);
            var dx = point.x - startPoint.x;
            var dy = point.y - startPoint.y;
            shape.x = clamp(snap(originX + dx), 0, CANVAS_W - shape.w);
            shape.y = clamp(snap(originY + dy), 0, CANVAS_H - shape.h);
            updateShapeStyle(shape);
        }

        function onUp() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            schedulePreview();
        }

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    }

    function startDragResize(event, shape) {
        function onMove(moveEvent) {
            var point = toCanvasCoords(moveEvent.clientX, moveEvent.clientY);
            shape.w = clamp(snap(point.x - shape.x), 1, CANVAS_W - shape.x);
            shape.h = clamp(snap(point.y - shape.y), 1, CANVAS_H - shape.y);
            updateShapeStyle(shape);
        }

        function onUp() {
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
            schedulePreview();
        }

        document.addEventListener('pointermove', onMove);
        document.addEventListener('pointerup', onUp);
    }

    function wireCanvas() {
        var drawing = null;

        canvasEl.setAttribute('tabindex', '0');
        canvasEl.setAttribute('aria-label', t('icon_editor_canvas_label'));
        canvasEl.addEventListener('pointerdown', function (event) {
            if (event.target !== canvasEl) {
                return;
            }
            canvasEl.focus({ preventScroll: true });
            if (shapes.length >= MAX_SHAPES) {
                return;
            }
            selectShape(null);
            var start = toCanvasCoords(event.clientX, event.clientY);

            var shape;
            if (LINE_TYPES.indexOf(activeType) !== -1) {
                shape = { x: snap(start.x), y: snap(start.y), x2: snap(start.x), y2: snap(start.y), type: activeType, style: defaultStyle(activeType) };
            } else {
                shape = { x: snap(start.x), y: snap(start.y), w: 0.5, h: 0.5, type: activeType, style: defaultStyle(activeType) };
            }
            createShapeElement(shape);
            shapes.push(shape);
            drawing = shape;
        });

        canvasEl.addEventListener('pointermove', function (event) {
            if (!drawing) {
                return;
            }
            var point = toCanvasCoords(event.clientX, event.clientY);
            if (isLine(drawing)) {
                drawing.x2 = snap(point.x);
                drawing.y2 = snap(point.y);
            } else {
                drawing.w = clamp(snap(Math.max(0.5, point.x - drawing.x)), 0.5, CANVAS_W - drawing.x);
                drawing.h = clamp(snap(Math.max(0.5, point.y - drawing.y)), 0.5, CANVAS_H - drawing.y);
            }
            updateShapeStyle(drawing);
            schedulePreview();
        });

        document.addEventListener('pointerup', function () {
            if (drawing) {
                // Nur geklickt statt gezogen: Form in sinnvoller Standardgroesse anlegen
                if (isLine(drawing) && drawing.x === drawing.x2 && drawing.y === drawing.y2) {
                    drawing.x2 = clamp(drawing.x + 6, 0, CANVAS_W);
                    updateShapeStyle(drawing);
                } else if (!isLine(drawing) && drawing.w <= 0.5 && drawing.h <= 0.5) {
                    var size = { text: [8, 4], heading: [10, 2.5], button: [6, 2.5], star: [4, 4], pin: [3, 4], check: [3, 3], circle: [4, 4] }[drawing.type] || [6, 5];
                    drawing.w = Math.min(size[0], CANVAS_W - drawing.x);
                    drawing.h = Math.min(size[1], CANVAS_H - drawing.y);
                    updateShapeStyle(drawing);
                }
                selectShape(drawing);
                schedulePreview();
            }
            drawing = null;
        });
    }

    function shapesPayload() {
        return shapes.map(function (s) {
            if (isLine(s)) {
                return { x: s.x, y: s.y, x2: s.x2, y2: s.y2, type: s.type, style: s.style };
            }
            return { x: s.x, y: s.y, w: s.w, h: s.h, type: s.type, style: s.style };
        });
    }

    // Live-Vorschau: bei jeder Aenderung debounced den echten Server-Renderer
    // aufrufen (siehe boot.php-Registrierung von module_organizer_preview_custom_icon
    // -> lib/Api/PreviewCustomIcon.php -> CustomIconRenderer::render()), statt
    // die Formen im Client selbst nachzuzeichnen. So sieht man beim Ziehen
    // exakt das spaeter gespeicherte SVG (Ueberlappungen, Verzerrungen,
    // Abstaende) statt nur abstrakter Platzhalter-Boxen.
    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreview, PREVIEW_DEBOUNCE);
    }

    function renderPreview() {
        if (!previewEl) {
            return;
        }
        var targets = modal.querySelectorAll('.mo-icon-editor-preview, .mo-icon-editor-preview-mini');
        if (0 === shapes.length) {
            targets.forEach(function (el) { el.innerHTML = ''; });
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'rex-api-call=module_organizer_preview_custom_icon&shapes=' + encodeURIComponent(JSON.stringify(shapesPayload()))
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (result && result.success) {
                // Das SVG baut der Server aus Typ+Koordinaten (kein Client-Markup)
                targets.forEach(function (el) { el.innerHTML = result.svg || ''; });
            }
        }).catch(function () {
            // Vorschau ist rein informativ - ein Fehlschlag blockiert das
            // Zeichnen/Speichern nicht.
        });
    }

    function save() {
        if (0 === shapes.length) {
            return;
        }

        var body = 'rex-api-call=module_organizer_save_custom_icon'
            + '&shapes=' + encodeURIComponent(JSON.stringify(shapesPayload()))
            + '&id=' + encodeURIComponent(editingIconId || '');

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (result && result.success) {
                if (onSaveCallback) {
                    onSaveCallback(result.id, result.svg);
                }
                close();
            }
        });
    }

    function reset() {
        shapes.slice().forEach(removeShape);
        activeShape = null;
        editingIconId = null;
        if (modal) {
            modal.querySelectorAll('.mo-icon-editor-preview, .mo-icon-editor-preview-mini').forEach(function (el) { el.innerHTML = ''; });
        }
        syncActions();
    }

    function open(callback) {
        build();
        reset();
        onSaveCallback = callback;
        modal.classList.add('mo-open');
        canvasEl.focus({ preventScroll: true });
    }

    function close() {
        if (modal) {
            modal.classList.remove('mo-open');
        }
    }

    window.MOIconEditor = { open: open };
})();
