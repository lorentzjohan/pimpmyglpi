/**
 * -------------------------------------------------------------------------
 * PimpMyGLPI plugin for GLPI
 * @copyright Copyright (C) 2026 LORENTZ Johan
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * -------------------------------------------------------------------------
 *
 * GLPI declares its own favicon after the plugins' header tags: keep only ours.
 */
(function () {
    'use strict';

    function useCustomFavicon() {
        var custom = document.querySelector('link[rel="icon"][data-pimpmyglpi="favicon"]');
        if (!custom) {
            return;
        }
        document.querySelectorAll('link[rel~="icon"]').forEach(function (link) {
            if (link !== custom) {
                link.parentNode.removeChild(link);
            }
        });
    }

    useCustomFavicon();
    document.addEventListener('DOMContentLoaded', useCustomFavicon);
})();
