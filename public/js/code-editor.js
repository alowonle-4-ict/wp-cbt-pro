/**
 * Monaco is loaded strictly as a text editor. There is no code path here
 * that executes candidate code — the editor's only output is the value of
 * the paired hidden textarea, which then flows through the exact same
 * autosave/submit pipeline as every other question type (§19, §21).
 */
(function () {
    'use strict';

    var containers = document.querySelectorAll('[data-wpcbtpro-code-editor]');
    if (!containers.length) {
        return;
    }

    var DEFAULT_LOADER = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js';

    function loadMonaco(callback) {
        if (window.monaco) {
            callback();
            return;
        }

        var loaderUrl = window.wpcbtproMonacoLoaderSrc || DEFAULT_LOADER;
        var script = document.createElement('script');
        script.src = loaderUrl;
        script.onload = function () {
            window.require.config({ paths: { vs: loaderUrl.replace(/\/loader\.js$/, '') } });
            window.require(['vs/editor/editor.main'], callback);
        };
        document.head.appendChild(script);
    }

    loadMonaco(function () {
        containers.forEach(function (container) {
            var target = container.querySelector('[data-wpcbtpro-monaco-target]');
            var textarea = container.querySelector('[data-wpcbtpro-code-source]');
            if (!target || !textarea) {
                return;
            }

            var editor = window.monaco.editor.create(target, {
                value: textarea.value,
                language: container.dataset.monacoLanguage || 'plaintext',
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 13,
                scrollBeyondLastLine: false,
            });

            editor.onDidChangeModelContent(function () {
                textarea.value = editor.getValue();
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });
})();
