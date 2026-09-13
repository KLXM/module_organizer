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
    var MAX_SHAPES = 8;
    var PREVIEW_DEBOUNCE = 150;

    var BOX_TYPES = ['text', 'image', 'video', 'media', 'form', 'rect'];
    var LINE_TYPE = 'line';
    var TYPES = BOX_TYPES.concat([LINE_TYPE]);

    var modal = null;
    var canvasEl = null;
    var previewEl = null;
    var shapes = []; // { el, type, x, y, w, h } bzw. bei type==="line": { el, type, x, y, x2, y2 }
    var activeType = 'image';
    var activeShape = null;
    var onSaveCallback = null;
    var editingIconId = null;
    var previewTimer = null;

    var i18nDict = null;

    function loadI18nDict() {
        if (null !== i18nDict) {
            return i18nDict;
        }
        var el = document.getElementById('mo-i18n-data');
        if (!el) {
            i18nDict = {};
            return i18nDict;
        }
        try {
            i18nDict = JSON.parse(el.textContent) || {};
        } catch (e) {
            i18nDict = {};
        }
        return i18nDict;
    }

    function t(key) {
        return loadI18nDict()['module_organizer_' + key] || key;
    }

    function isLine(shape) {
        return LINE_TYPE === shape.type;
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
                    '<div class="mo-icon-editor-workarea">' +
                        '<div class="mo-icon-editor-canvas-wrap">' +
                            '<div class="mo-icon-editor-canvas"></div>' +
                            '<p class="help-block">' + t('icon_editor_hint') + '</p>' +
                        '</div>' +
                        '<div class="mo-icon-editor-preview-wrap">' +
                            '<div class="mo-icon-editor-preview-label">' + t('icon_editor_preview_label') + '</div>' +
                            '<div class="mo-icon-editor-preview"></div>' +
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
        wireCanvas();

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
            btn.setAttribute('data-type', typeKey);
            btn.addEventListener('click', function () {
                activeType = typeKey;
                toolbar.querySelectorAll('.mo-icon-editor-tool').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
            });
            toolbar.appendChild(btn);
        });

        var deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'mo-icon-editor-tool mo-icon-editor-delete-btn';
        deleteBtn.textContent = t('icon_editor_delete_shape');
        deleteBtn.addEventListener('click', function () {
            if (activeShape) {
                removeShape(activeShape);
                schedulePreview();
            }
        });
        toolbar.appendChild(deleteBtn);

        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'mo-icon-editor-tool mo-icon-editor-clear-btn';
        clearBtn.textContent = t('icon_editor_clear');
        clearBtn.addEventListener('click', function () {
            shapes.slice().forEach(removeShape);
            schedulePreview();
        });
        toolbar.appendChild(clearBtn);
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
        el.innerHTML = '<span class="mo-icon-editor-shape-label">' + t('icon_editor_type_' + shape.type) + '</span>'
            + '<span class="mo-icon-editor-handle mo-icon-editor-handle-se"></span>';
        shape.el = el;
        updateShapeStyle(shape);
        canvasEl.appendChild(el);

        el.addEventListener('pointerdown', function (event) {
            if (event.target.classList.contains('mo-icon-editor-handle-se')) {
                return; // Resize wird separat behandelt.
            }
            event.stopPropagation();
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
        el.className = 'mo-icon-editor-line';

        var handleStart = document.createElement('span');
        handleStart.className = 'mo-icon-editor-handle mo-icon-editor-line-handle mo-icon-editor-line-handle-start';
        var handleEnd = document.createElement('span');
        handleEnd.className = 'mo-icon-editor-handle mo-icon-editor-line-handle mo-icon-editor-line-handle-end';

        shape.el = el;
        shape.handleStart = handleStart;
        shape.handleEnd = handleEnd;
        canvasEl.appendChild(el);
        canvasEl.appendChild(handleStart);
        canvasEl.appendChild(handleEnd);
        updateShapeStyle(shape);

        el.addEventListener('pointerdown', function (event) {
            event.stopPropagation();
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

        canvasEl.addEventListener('pointerdown', function (event) {
            if (event.target !== canvasEl) {
                return;
            }
            if (shapes.length >= MAX_SHAPES) {
                return;
            }
            selectShape(null);
            var start = toCanvasCoords(event.clientX, event.clientY);

            var shape;
            if (LINE_TYPE === activeType) {
                shape = { x: snap(start.x), y: snap(start.y), x2: snap(start.x), y2: snap(start.y), type: activeType };
            } else {
                shape = { x: snap(start.x), y: snap(start.y), w: 0.5, h: 0.5, type: activeType };
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
                selectShape(drawing);
                schedulePreview();
            }
            drawing = null;
        });
    }

    function shapesPayload() {
        return shapes.map(function (s) {
            if (isLine(s)) {
                return { x: s.x, y: s.y, x2: s.x2, y2: s.y2, type: s.type };
            }
            return { x: s.x, y: s.y, w: s.w, h: s.h, type: s.type };
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
        if (0 === shapes.length) {
            previewEl.innerHTML = '';
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
                previewEl.innerHTML = result.svg || '';
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
        if (previewEl) {
            previewEl.innerHTML = '';
        }
    }

    function open(callback) {
        build();
        reset();
        onSaveCallback = callback;
        modal.classList.add('mo-open');
    }

    function close() {
        if (modal) {
            modal.classList.remove('mo-open');
        }
    }

    window.MOIconEditor = { open: open };
})();
