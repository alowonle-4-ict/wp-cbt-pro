/**
 * Interactive DSA widgets (§27) — direct manipulation, not a static image.
 * Every widget's only output is the hidden textarea's value, through the
 * exact same autosave/submit pipeline every other question type uses; the
 * grading logic these mirror lives server-side in
 * includes/DSA/Structures/*.php and is never duplicated here as a source
 * of truth — this is presentation only.
 */
(function () {
    'use strict';

    var OP_LABELS = {
        PUSH: 'Push', POP: 'Pop',
        ENQUEUE: 'Enqueue', DEQUEUE: 'Dequeue',
        PUSH_FRONT: 'Push Front', PUSH_BACK: 'Push Back', POP_FRONT: 'Pop Front', POP_BACK: 'Pop Back',
        INSERT: 'Insert', EXTRACT_MAX: 'Extract Max',
        INSERT_FRONT: 'Insert Front', INSERT_BACK: 'Insert Back', DELETE: 'Delete',
    };

    var TAKES_VALUE = ['PUSH', 'ENQUEUE', 'PUSH_FRONT', 'PUSH_BACK', 'INSERT', 'INSERT_FRONT', 'INSERT_BACK', 'DELETE'];

    function applyOperation(values, op, arg) {
        switch (op) {
            case 'PUSH': case 'ENQUEUE': case 'INSERT_BACK':
                return values.concat([arg]);
            case 'INSERT':
                return values.concat([arg]).sort(function (a, b) { return parseFloat(a) - parseFloat(b); });
            case 'PUSH_FRONT': case 'INSERT_FRONT':
                return [arg].concat(values);
            case 'PUSH_BACK':
                return values.concat([arg]);
            case 'POP': case 'POP_BACK':
                return values.slice(0, -1);
            case 'DEQUEUE': case 'POP_FRONT':
                return values.slice(1);
            case 'EXTRACT_MAX':
                if (values.length === 0) return values;
                var maxIndex = 0;
                values.forEach(function (v, i) { if (parseFloat(v) > parseFloat(values[maxIndex])) maxIndex = i; });
                return values.slice(0, maxIndex).concat(values.slice(maxIndex + 1));
            case 'DELETE':
                var idx = values.indexOf(arg);
                if (idx === -1) return values;
                return values.slice(0, idx).concat(values.slice(idx + 1));
            default:
                return values;
        }
    }

    function initSequenceWidget(widget) {
        var operations = JSON.parse(widget.dataset.operations || '[]').filter(function (op) { return op !== 'CAPACITY'; });
        var canvas = widget.querySelector('[data-wpcbtpro-dsa-canvas]');
        var controls = widget.querySelector('[data-wpcbtpro-dsa-controls]');
        var source = widget.querySelector('[data-wpcbtpro-dsa-source]');

        var values = [];
        try {
            var initial = JSON.parse(source.value || '{}');
            if (Array.isArray(initial.values)) values = initial.values.map(String);
        } catch (e) { /* start empty */ }

        function render() {
            canvas.innerHTML = '';
            if (values.length === 0) {
                canvas.innerHTML = '<span class="wpcbtpro-dsa-empty">(empty)</span>';
            }
            values.forEach(function (v) {
                var box = document.createElement('span');
                box.className = 'wpcbtpro-dsa-box';
                box.textContent = v;
                canvas.appendChild(box);
            });
            source.value = JSON.stringify({ values: values });
            source.dispatchEvent(new Event('change', { bubbles: true }));
        }

        var valueInput = null;
        if (operations.some(function (op) { return TAKES_VALUE.indexOf(op) !== -1; })) {
            valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'wpcbtpro-dsa-value-input';
            valueInput.placeholder = 'value';
            controls.appendChild(valueInput);
        }

        operations.forEach(function (op) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wpcbtpro-btn wpcbtpro-dsa-op-btn';
            btn.textContent = OP_LABELS[op] || op;
            btn.addEventListener('click', function () {
                var needsValue = TAKES_VALUE.indexOf(op) !== -1;
                var arg = needsValue && valueInput ? valueInput.value.trim() : null;
                if (needsValue && !arg) {
                    return;
                }
                values = applyOperation(values, op, arg);
                if (valueInput) valueInput.value = '';
                render();
            });
            controls.appendChild(btn);
        });

        render();
    }

    function insertBst(node, value) {
        if (!node) {
            return { value: value, left: null, right: null };
        }
        if (parseFloat(value) < parseFloat(node.value)) {
            node.left = insertBst(node.left, value);
        } else if (parseFloat(value) > parseFloat(node.value)) {
            node.right = insertBst(node.right, value);
        }
        return node;
    }

    function initTreeWidget(widget) {
        var canvas = widget.querySelector('[data-wpcbtpro-dsa-canvas]');
        var controls = widget.querySelector('[data-wpcbtpro-dsa-controls]');
        var source = widget.querySelector('[data-wpcbtpro-dsa-source]');

        var root = null;
        try {
            var initial = JSON.parse(source.value || 'null');
            if (initial && initial.value !== undefined) root = initial;
        } catch (e) { /* start empty */ }

        var valueInput = document.createElement('input');
        valueInput.type = 'text';
        valueInput.className = 'wpcbtpro-dsa-value-input';
        valueInput.placeholder = 'value';
        var insertBtn = document.createElement('button');
        insertBtn.type = 'button';
        insertBtn.className = 'wpcbtpro-btn wpcbtpro-dsa-op-btn';
        insertBtn.textContent = 'Insert';
        var resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'wpcbtpro-btn wpcbtpro-btn--ghost';
        resetBtn.textContent = 'Reset';
        controls.appendChild(valueInput);
        controls.appendChild(insertBtn);
        controls.appendChild(resetBtn);

        function render() {
            source.value = root ? JSON.stringify(root) : '';
            source.dispatchEvent(new Event('change', { bubbles: true }));

            canvas.innerHTML = '';
            if (!root) {
                canvas.innerHTML = '<span class="wpcbtpro-dsa-empty">(empty tree)</span>';
                return;
            }

            var positions = [];
            var counter = { n: 0 };
            var maxDepth = { n: 0 };
            (function assign(node, depth) {
                if (!node) return;
                assign(node.left, depth + 1);
                positions.push({ node: node, x: counter.n++, y: depth });
                maxDepth.n = Math.max(maxDepth.n, depth);
                assign(node.right, depth + 1);
            })(root, 0);

            var xSpacing = 56, ySpacing = 60, radius = 18;
            var width = Math.max(200, counter.n * xSpacing + xSpacing);
            var height = (maxDepth.n + 1) * ySpacing + ySpacing;

            var svgNs = 'http://www.w3.org/2000/svg';
            var svg = document.createElementNS(svgNs, 'svg');
            svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
            svg.setAttribute('class', 'wpcbtpro-dsa-tree-svg');

            var byNode = new Map();
            positions.forEach(function (p) {
                byNode.set(p.node, { x: (p.x + 1) * xSpacing, y: (p.y + 1) * ySpacing });
            });

            positions.forEach(function (p) {
                var pos = byNode.get(p.node);
                ['left', 'right'].forEach(function (side) {
                    var child = p.node[side];
                    if (child && byNode.has(child)) {
                        var cp = byNode.get(child);
                        var line = document.createElementNS(svgNs, 'line');
                        line.setAttribute('x1', pos.x); line.setAttribute('y1', pos.y);
                        line.setAttribute('x2', cp.x); line.setAttribute('y2', cp.y);
                        line.setAttribute('class', 'wpcbtpro-dsa-tree-edge');
                        svg.appendChild(line);
                    }
                });
            });

            positions.forEach(function (p) {
                var pos = byNode.get(p.node);
                var circle = document.createElementNS(svgNs, 'circle');
                circle.setAttribute('cx', pos.x); circle.setAttribute('cy', pos.y); circle.setAttribute('r', radius);
                circle.setAttribute('class', 'wpcbtpro-dsa-tree-node');
                svg.appendChild(circle);

                var text = document.createElementNS(svgNs, 'text');
                text.setAttribute('x', pos.x); text.setAttribute('y', pos.y + 4);
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('class', 'wpcbtpro-dsa-tree-text');
                text.textContent = p.node.value;
                svg.appendChild(text);
            });

            canvas.appendChild(svg);
        }

        insertBtn.addEventListener('click', function () {
            var value = valueInput.value.trim();
            if (!value) return;
            root = insertBst(root, value);
            valueInput.value = '';
            render();
        });

        resetBtn.addEventListener('click', function () {
            root = null;
            render();
        });

        render();
    }

    document.querySelectorAll('[data-wpcbtpro-dsa-widget]').forEach(function (widget) {
        if (widget.dataset.widgetType === 'tree') {
            initTreeWidget(widget);
        } else {
            initSequenceWidget(widget);
        }
    });
})();
