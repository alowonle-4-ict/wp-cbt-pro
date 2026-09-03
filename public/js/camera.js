/**
 * Camera access always goes through the standard browser permission flow
 * (getUserMedia) — this file never requests or activates a camera without
 * that dialog, and never uploads a continuous video stream, only discrete
 * captured frames (§11). It is inert wherever the page doesn't need it.
 */
(function () {
    'use strict';

    if (typeof wpcbtproExam === 'undefined') {
        return;
    }

    var strings = wpcbtproExam.cameraStrings || {};
    var activeStream = null;

    function isSecureContext() {
        return window.isSecureContext !== false;
    }

    function supportsCamera() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    function setStatus(el, text, cls) {
        if (!el) {
            return;
        }
        el.textContent = text;
        el.classList.remove('is-ok', 'is-error');
        if (cls) {
            el.classList.add(cls);
        }
    }

    function attachStream(previewEl, stream) {
        if (previewEl) {
            previewEl.srcObject = stream;
        }
    }

    function grabFrameAsDataUrl(previewEl, quality) {
        var canvas = document.createElement('canvas');
        canvas.width = previewEl.videoWidth || 320;
        canvas.height = previewEl.videoHeight || 240;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(previewEl, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', quality || 0.8);
    }

    function postCameraEvent(attemptId, eventType, context) {
        return fetch(wpcbtproExam.restUrl + '/camera-event', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpcbtproExam.nonce },
            body: JSON.stringify({ attempt_id: attemptId, event_type: eventType, context: context || {} }),
        })
            .then(function (response) { return response.json(); })
            .then(function (body) {
                if (body && body.monitoring) {
                    handleMonitoringResult(body.monitoring);
                }
            })
            .catch(function () { /* best-effort; the server also expires attempts lazily */ });
    }

    function postMonitoringViolation(attemptId, violationType) {
        return fetch(wpcbtproExam.restUrl + '/monitoring-violation', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpcbtproExam.nonce },
            body: JSON.stringify({ attempt_id: attemptId, violation_type: violationType }),
        })
            .then(function (response) { return response.json(); })
            .then(function (body) {
                if (body) {
                    handleMonitoringResult(body);
                }
            })
            .catch(function () {});
    }

    function handleMonitoringResult(result) {
        var banner = document.querySelector('[data-wpcbtpro-monitoring-warning]');
        if (!result || !result.message) {
            return;
        }

        if (banner) {
            banner.textContent = result.message;
            banner.classList.remove('wpcbtpro-hidden');
        }

        if (result.submitted) {
            if (banner) {
                banner.textContent = result.message + ' ' + (strings.monitoringSubmitted || '');
            }
            window.location.reload();
        }
    }

    function postSnapshot(attemptId, dataUrl) {
        return fetch(wpcbtproExam.restUrl + '/snapshot', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpcbtproExam.nonce },
            body: JSON.stringify({ attempt_id: attemptId, image: dataUrl }),
        }).catch(function () {});
    }

    function classifyGetUserMediaError(err) {
        if (!err || !err.name) {
            return 'CAMERA_ERROR';
        }
        if (err.name === 'NotAllowedError' || err.name === 'SecurityError') {
            return 'CAMERA_PERMISSION_DENIED';
        }
        if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
            return 'CAMERA_NOT_FOUND';
        }
        return 'CAMERA_ERROR';
    }

    function errorMessageFor(eventType) {
        return {
            CAMERA_PERMISSION_DENIED: strings.denied,
            CAMERA_NOT_FOUND: strings.notFound,
            CAMERA_ERROR: strings.error,
        }[eventType] || strings.error;
    }

    /** ---- Pre-exam system check (exam-start.php) ---- */
    function initSystemCheck() {
        var container = document.querySelector('[data-wpcbtpro-system-check]');
        if (!container) {
            return;
        }

        var browserStatus = container.querySelector('[data-wpcbtpro-check="browser"]');
        var cameraStatus = container.querySelector('[data-wpcbtpro-check="camera"]');
        var preview = container.querySelector('[data-wpcbtpro-camera-preview]');
        var requestBtn = container.querySelector('[data-wpcbtpro-request-camera]');
        var consentBox = container.querySelector('[data-wpcbtpro-consent]');
        var startBtn = document.getElementById('wpcbtpro-start-btn');

        var consentField = document.querySelector('[data-wpcbtpro-consent-field]');
        var cameraGranted = false;

        function refreshStartButton() {
            var consentOk = !consentBox || consentBox.checked;
            if (consentField) {
                consentField.value = consentOk ? '1' : '0';
            }
            if (startBtn) {
                startBtn.disabled = !(cameraGranted && consentOk);
            }
        }

        if (!supportsCamera()) {
            setStatus(browserStatus, strings.unsupported, 'is-error');
            setStatus(cameraStatus, strings.unsupported, 'is-error');
            return;
        }
        setStatus(browserStatus, 'OK', 'is-ok');

        if (!isSecureContext()) {
            setStatus(cameraStatus, strings.insecure, 'is-error');
            return;
        }

        if (consentBox) {
            consentBox.addEventListener('change', refreshStartButton);
        }

        if (requestBtn) {
            requestBtn.addEventListener('click', function () {
                setStatus(cameraStatus, strings.checking);
                navigator.mediaDevices.getUserMedia({ video: true })
                    .then(function (stream) {
                        activeStream = stream;
                        attachStream(preview, stream);
                        cameraGranted = true;
                        setStatus(cameraStatus, strings.granted, 'is-ok');
                        refreshStartButton();
                    })
                    .catch(function (err) {
                        var eventType = classifyGetUserMediaError(err);
                        setStatus(cameraStatus, errorMessageFor(eventType), 'is-error');
                        cameraGranted = false;
                        refreshStartButton();
                    });
            });
        }
    }

    /** ---- Running exam page: keep the camera alive and monitor it ---- */
    function initRunningCamera() {
        var configEl = document.getElementById('wpcbtpro-camera-config');
        if (!configEl) {
            return;
        }

        var config;
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            return;
        }

        if (!config.cameraRequired) {
            return;
        }

        var preview = document.querySelector('[data-wpcbtpro-camera-preview]');
        var status = document.querySelector('.wpcbtpro-camera-status [data-wpcbtpro-check="camera"]');

        function connect(isReconnect) {
            if (!supportsCamera()) {
                setStatus(status, strings.unsupported, 'is-error');
                return;
            }

            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function (stream) {
                    activeStream = stream;
                    attachStream(preview, stream);
                    setStatus(status, isReconnect ? strings.reconnected : strings.granted, 'is-ok');
                    postCameraEvent(config.attemptId, isReconnect ? 'CAMERA_RECONNECTED' : 'CAMERA_CONNECTED');

                    stream.getVideoTracks().forEach(function (track) {
                        track.addEventListener('ended', onDisconnect);
                    });
                })
                .catch(function (err) {
                    var eventType = classifyGetUserMediaError(err);
                    setStatus(status, errorMessageFor(eventType), 'is-error');
                    postCameraEvent(config.attemptId, eventType);
                });
        }

        function onDisconnect() {
            setStatus(status, strings.disconnected, 'is-error');
            postCameraEvent(config.attemptId, 'CAMERA_DISCONNECTED');
            setTimeout(function () { connect(true); }, 3000);
        }

        connect(false);

        if (config.snapshotIntervalSeconds > 0) {
            setInterval(function () {
                if (activeStream && preview && preview.videoWidth) {
                    postSnapshot(config.attemptId, grabFrameAsDataUrl(preview, 0.6));
                }
            }, config.snapshotIntervalSeconds * 1000);
        }

        if (config.autoMonitoringEnabled && config.referencePhotoUrl && config.snapshotIntervalSeconds > 0) {
            initAutoMonitoring(config, preview);
        }
    }

    /**
     * "Warn 3 times, then submit" (server-decided — see AutoMonitoringService).
     * Everything here only ever reports what a check saw; the browser never
     * decides to submit anything itself. Runs the face comparison entirely
     * client-side via face-api.js — no image or descriptor is sent to any
     * third party, only the /monitoring-violation report (attempt id + which
     * kind of violation), the same shape as every other camera event already
     * reported here.
     */
    function initAutoMonitoring(config, preview) {
        var FACE_MATCH_DISTANCE_THRESHOLD = 0.6; // face-api.js's own recommended cutoff
        var referenceDescriptor = null;
        var modelsReady = false;

        function loadScript(src) {
            return new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        function loadModels() {
            var modelsUrl = window.wpcbtproFaceApiModelsUrl;
            return Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsUrl),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsUrl),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelsUrl),
            ]);
        }

        function detectDescriptor(input) {
            return faceapi
                .detectSingleFace(input, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();
        }

        function loadReferenceDescriptor() {
            return new Promise(function (resolve, reject) {
                var img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function () {
                    detectDescriptor(img).then(function (result) {
                        if (!result) {
                            reject(new Error('no face found in reference photo'));
                            return;
                        }
                        resolve(result.descriptor);
                    }, reject);
                };
                img.onerror = reject;
                img.src = config.referencePhotoUrl;
            });
        }

        function runCheck() {
            if (!modelsReady || !referenceDescriptor || !activeStream || !preview || !preview.videoWidth) {
                return;
            }

            detectDescriptor(preview).then(function (result) {
                if (!result) {
                    postMonitoringViolation(config.attemptId, 'NO_FACE_DETECTED');
                    return;
                }

                var distance = faceapi.euclideanDistance(referenceDescriptor, result.descriptor);
                if (distance > FACE_MATCH_DISTANCE_THRESHOLD) {
                    postMonitoringViolation(config.attemptId, 'FACE_MISMATCH');
                }
            }, function () {
                // A detection error (e.g. a mid-frame decode glitch) is not
                // itself evidence of anything — skip this tick rather than
                // reporting a false violation.
            });
        }

        loadScript(window.wpcbtproFaceApiSrc)
            .then(loadModels)
            .then(function () {
                modelsReady = true;
                return loadReferenceDescriptor();
            })
            .then(function (descriptor) {
                referenceDescriptor = descriptor;
                setInterval(runCheck, config.snapshotIntervalSeconds * 1000);
            })
            .catch(function () {
                var banner = document.querySelector('[data-wpcbtpro-monitoring-warning]');
                if (banner && strings.monitoringLoadError) {
                    banner.textContent = strings.monitoringLoadError;
                    banner.classList.remove('wpcbtpro-hidden');
                }
            });
    }

    /** ---- Identity verification interstitial (exam-verify.php) ---- */
    function initVerify() {
        var container = document.querySelector('[data-wpcbtpro-verify]');
        if (!container) {
            return;
        }

        var attemptId = parseInt(container.dataset.attemptId, 10);
        var preview = container.querySelector('[data-wpcbtpro-camera-preview]');
        var captureBtn = container.querySelector('[data-wpcbtpro-capture-verify]');
        var statusEl = container.querySelector('[data-wpcbtpro-verify-status]');

        if (!supportsCamera()) {
            setStatus(statusEl, strings.unsupported, 'is-error');
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function (stream) {
                activeStream = stream;
                attachStream(preview, stream);
            })
            .catch(function (err) {
                setStatus(statusEl, errorMessageFor(classifyGetUserMediaError(err)), 'is-error');
            });

        if (captureBtn) {
            captureBtn.addEventListener('click', function () {
                if (!preview || !preview.videoWidth) {
                    return;
                }

                captureBtn.disabled = true;
                setStatus(statusEl, strings.checking);

                var dataUrl = grabFrameAsDataUrl(preview, 0.85);

                fetch(wpcbtproExam.restUrl + '/verification', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpcbtproExam.nonce },
                    body: JSON.stringify({ attempt_id: attemptId, image: dataUrl }),
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('verification failed');
                        }
                        window.location.reload();
                    })
                    .catch(function () {
                        setStatus(statusEl, strings.error, 'is-error');
                        captureBtn.disabled = false;
                    });
            });
        }
    }

    initSystemCheck();
    initRunningCamera();
    initVerify();
})();
