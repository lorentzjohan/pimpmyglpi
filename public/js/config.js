/**
 * -------------------------------------------------------------------------
 * PimpMyGLPI plugin for GLPI
 * @copyright Copyright (C) 2026 LORENTZ Johan
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * -------------------------------------------------------------------------
 *
 * Configuration page: drag and drop of the images, with an immediate preview and the same
 * type / size checks as the server (which checks everything again on save).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-pimpmyglpi-config]');
    if (!root) {
        return;
    }

    var messages = {
        type: root.getAttribute('data-msg-type') || 'Unsupported file type. Accepted formats: %s.',
        size: root.getAttribute('data-msg-size') || 'The file is too large: %s at most.'
    };

    function hasFiles(event) {
        var types = event.dataTransfer && event.dataTransfer.types;
        return !!types && Array.prototype.indexOf.call(types, 'Files') !== -1;
    }

    function formatSize(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace('.0', '') + ' MB' : Math.ceil(bytes / 1024) + ' KB';
    }

    // A file dropped next to a drop zone must not make the browser leave the page.
    ['dragover', 'drop'].forEach(function (type) {
        window.addEventListener(type, function (event) {
            if (hasFiles(event)) {
                event.preventDefault();
            }
        });
    });

    root.querySelectorAll('.pimpmyglpi-dropzone').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        var preview = zone.querySelector('.pimpmyglpi-drop-preview');
        var selection = zone.querySelector('.pimpmyglpi-drop-selection');
        var name = zone.querySelector('.pimpmyglpi-drop-name');
        var clear = zone.querySelector('.pimpmyglpi-drop-clear');
        var error = zone.querySelector('.pimpmyglpi-drop-error');
        var originalPreview = preview.innerHTML;
        var objectUrl = null;

        var formats = (zone.getAttribute('data-formats') || '').toLowerCase().split(/\s*,\s*/).filter(Boolean);
        if (formats.indexOf('jpg') !== -1) {
            formats.push('jpeg');
        }
        var maxSize = parseInt(zone.getAttribute('data-max-size'), 10) || 0;

        function reset() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            preview.innerHTML = originalPreview;
            selection.classList.add('d-none');
            zone.classList.remove('has-file');
        }

        function showError(text) {
            error.textContent = text;
            error.classList.toggle('d-none', text === '');
        }

        function check(file) {
            var extension = (file.name.split('.').pop() || '').toLowerCase();
            if (formats.length && formats.indexOf(extension) === -1) {
                return messages.type.replace('%s', zone.getAttribute('data-formats'));
            }
            if (maxSize && file.size > maxSize) {
                return messages.size.replace('%s', formatSize(maxSize));
            }
            return '';
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            reset();
            showError('');
            if (!file) {
                return;
            }
            var problem = check(file);
            if (problem !== '') {
                input.value = '';
                showError(problem);
                return;
            }
            objectUrl = URL.createObjectURL(file);
            var image = document.createElement('img');
            image.alt = '';
            image.src = objectUrl;
            preview.innerHTML = '';
            preview.appendChild(image);
            name.textContent = file.name + ' (' + formatSize(file.size) + ')';
            selection.classList.remove('d-none');
            zone.classList.add('has-file');
        });

        clear.addEventListener('click', function () {
            input.value = '';
            reset();
            showError('');
        });

        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function (event) {
                if (!hasFiles(event)) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                zone.classList.add('is-dragover');
            });
        });

        zone.addEventListener('dragleave', function (event) {
            if (!zone.contains(event.relatedTarget)) {
                zone.classList.remove('is-dragover');
            }
        });

        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('is-dragover');
            var files = event.dataTransfer && event.dataTransfer.files;
            if (!files || files.length === 0) {
                return;
            }
            // Put the dropped file in the regular file input: the form is sent as usual.
            var transfer = new DataTransfer();
            transfer.items.add(files[0]);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
})();
