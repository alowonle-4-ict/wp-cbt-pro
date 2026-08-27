/**
 * Progressive enhancement only. The plain form POSTs handled server-side in
 * ExamRuntimeController already make the exam fully usable with this file
 * absent — this adds a live countdown and background autosave so answers
 * persist without waiting for a page navigation (§9, §10).
 */
(function () {
    'use strict';

    if (typeof wpcbtproExam === 'undefined') {
        return;
    }

    var root = document.querySelector('.wpcbtpro-exam--running');
    if (!root) {
        return;
    }

    var clockOffsetMs = (parseInt(root.dataset.serverNow, 10) * 1000) - Date.now();
    var serverEndMs = parseInt(root.dataset.serverEnd, 10) * 1000;
    var autoSubmitted = false;

    function estimatedServerNowMs() {
        return Date.now() + clockOffsetMs;
    }

    function formatRemaining(ms) {
        var totalSeconds = Math.max(0, Math.floor(ms / 1000));
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    function tickTimer() {
        var el = root.querySelector('[data-wpcbtpro-timer]');
        if (!el) {
            return;
        }

        var remaining = serverEndMs - estimatedServerNowMs();
        el.textContent = formatRemaining(remaining);
        el.classList.toggle('is-critical', remaining <= 60000);

        if (remaining <= 0 && !autoSubmitted) {
            autoSubmitted = true;
            var submitForm = root.querySelector('.wpcbtpro-submit-form');
            if (submitForm) {
                submitForm.submit();
            }
        }
    }

    function resyncTimer(serverNowSeconds, serverEndSeconds) {
        clockOffsetMs = (serverNowSeconds * 1000) - Date.now();
        serverEndMs = serverEndSeconds * 1000;
    }

    setInterval(tickTimer, 1000);
    tickTimer();

    function setSaveStatus(state) {
        var statusEl = root.querySelector('[data-wpcbtpro-save-status]');
        if (!statusEl) {
            return;
        }
        statusEl.classList.remove('is-saved', 'is-offline');
        if (state === 'saving') {
            statusEl.textContent = wpcbtproExam.strings && wpcbtproExam.strings.saving ? wpcbtproExam.strings.saving : 'Saving…';
        } else if (state === 'saved') {
            statusEl.classList.add('is-saved');
            statusEl.textContent = wpcbtproExam.strings && wpcbtproExam.strings.saved ? wpcbtproExam.strings.saved : 'Saved';
        } else {
            statusEl.classList.add('is-offline');
            statusEl.textContent = wpcbtproExam.strings && wpcbtproExam.strings.offline ? wpcbtproExam.strings.offline : 'Not saved — check your connection';
        }
    }

    function collectAnswerValue(form) {
        var els = form.querySelectorAll('[name="wpcbtpro_answer"], [name="wpcbtpro_answer[]"]');
        var checked = [];
        var hasCheckable = false;

        els.forEach(function (el) {
            if (el.type === 'radio' || el.type === 'checkbox') {
                hasCheckable = true;
                if (el.checked) {
                    checked.push(el.value);
                }
            }
        });

        if (hasCheckable) {
            return checked.length <= 1 ? (checked[0] || '') : checked;
        }

        return els.length ? els[0].value : '';
    }

    function debounce(fn, wait) {
        var timeout;
        return function () {
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    var autosave = debounce(function (form) {
        var questionIdField = form.querySelector('[name="question_id"]');
        var markedField = form.querySelector('[name="wpcbtpro_marked_for_review"]');
        if (!questionIdField) {
            return;
        }

        setSaveStatus('saving');

        fetch(wpcbtproExam.restUrl + '/answer', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wpcbtproExam.nonce,
            },
            body: JSON.stringify({
                attempt_id: parseInt(root.dataset.attemptId, 10),
                question_id: parseInt(questionIdField.value, 10),
                value: collectAnswerValue(form),
                marked_for_review: markedField ? markedField.checked : false,
            }),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('save failed');
                }
                return response.json();
            })
            .then(function (data) {
                if (data && typeof data.server_now === 'number' && typeof data.server_end === 'number') {
                    resyncTimer(data.server_now, data.server_end);
                }
                setSaveStatus('saved');
            })
            .catch(function () {
                setSaveStatus('offline');
            });
    }, 400);

    var form = root.querySelector('[data-wpcbtpro-autosave]');
    if (form) {
        form.addEventListener('change', function (event) {
            if (event.target.closest('.wpcbtpro-question__answer') || event.target.name === 'wpcbtpro_marked_for_review') {
                autosave(form);
            }
        });
    }
})();
