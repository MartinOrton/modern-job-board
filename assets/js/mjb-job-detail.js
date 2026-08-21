/**
 * Single job detail: share menu (opens above; icon-only).
 */
(function () {
  'use strict';

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function closeShareMenus(except) {
    qsa('[data-mjb-share-menu]').forEach(function (menu) {
      if (except && menu === except) {
        return;
      }
      menu.hidden = true;
      var wrap = menu.closest('[data-mjb-share]');
      var trigger = wrap ? qs('[data-mjb-share-toggle]', wrap) : null;
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function initShare(root) {
    var wrap = qs('[data-mjb-share]', root);
    if (!wrap) {
      return;
    }

    var toggle = qs('[data-mjb-share-toggle]', wrap);
    var menu = qs('[data-mjb-share-menu]', wrap);
    if (!toggle || !menu) {
      return;
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var willOpen = menu.hidden;
      closeShareMenus(menu);
      menu.hidden = !willOpen;
      toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    menu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    qsa('[data-mjb-share-copy]', menu).forEach(function (copyBtn) {
      copyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var url = copyBtn.getAttribute('data-url') || window.location.href;
        var copiedLabel = copyBtn.getAttribute('data-copied-label') || 'Copied';
        var originalLabel = copyBtn.getAttribute('aria-label') || 'Copy link';
        var done = function () {
          copyBtn.classList.add('is-copied');
          copyBtn.setAttribute('aria-label', copiedLabel);
          copyBtn.setAttribute('title', copiedLabel);
          setTimeout(function () {
            copyBtn.classList.remove('is-copied');
            copyBtn.setAttribute('aria-label', originalLabel);
            copyBtn.setAttribute('title', originalLabel);
          }, 1600);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(done).catch(function () {
            window.prompt('Copy link:', url);
          });
        } else {
          window.prompt('Copy link:', url);
          done();
        }
      });
    });
  }

  document.addEventListener('click', function () {
    closeShareMenus();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeShareMenus();
    }
  });

  function init() {
    qsa('.mjb-application-area').forEach(function (root) {
      initShare(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
