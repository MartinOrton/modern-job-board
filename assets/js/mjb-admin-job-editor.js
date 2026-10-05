/**
 * Job listing classic editor: Lucide icons in the Publish box.
 */
(function () {
    'use strict';

    var icons = (window.mjbJobEditor && window.mjbJobEditor.icons) || {};

    function prependIcon(selector, html) {
        var el = document.querySelector(selector);
        if (!el || !html || el.querySelector(':scope > .mjb-icon')) {
            return;
        }
        el.insertAdjacentHTML('afterbegin', html);
    }

    function apply() {
        prependIcon('#submitdiv .misc-pub-post-status', icons.status);
        prependIcon('#submitdiv .misc-pub-visibility', icons.visibility);
        prependIcon('#submitdiv .misc-pub-curtime', icons.date);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
})();
