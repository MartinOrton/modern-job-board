/**
 * Admin dashboard chrome: tooltips, mobile nav, range thumb, in-panel tab links.
 */
(function ($) {
    'use strict';

    var GAP = 9;
    var EDGE = 8;
    var tip = null;
    var current = null;

    function ensureTip() {
        if (tip) {
            return tip;
        }
        tip = document.getElementById('mjb-tip');
        if (!tip) {
            tip = document.createElement('div');
            tip.id = 'mjb-tip';
            tip.setAttribute('role', 'tooltip');
            tip.setAttribute('aria-hidden', 'true');
            document.body.appendChild(tip);
        }
        return tip;
    }

    function place(el) {
        ensureTip();
        tip.textContent = el.getAttribute('data-tip') || '';
        tip.style.left = '0px';
        tip.style.top = '0px';
        var r = el.getBoundingClientRect();
        var w = tip.offsetWidth;
        var hgt = tip.offsetHeight;
        var vw = document.documentElement.clientWidth;
        var vh = window.innerHeight;
        var pos = el.getAttribute('data-tip-pos') || 'top';
        var x;
        var y;

        if (pos === 'left') {
            x = r.left - w - GAP;
            y = r.top + r.height / 2 - hgt / 2;
            if (x < EDGE) {
                x = r.right + GAP;
                if (x + w > vw - EDGE) {
                    pos = 'top';
                }
            }
        }
        if (pos !== 'left') {
            x = r.left + r.width / 2 - w / 2;
            y = r.top - hgt - GAP;
            if (y < EDGE) {
                y = r.bottom + GAP;
            }
        }
        x = Math.min(Math.max(x, EDGE), vw - w - EDGE);
        y = Math.min(Math.max(y, EDGE), vh - hgt - EDGE);
        tip.style.left = Math.round(x) + 'px';
        tip.style.top = Math.round(y) + 'px';
    }

    function show(el) {
        current = el;
        place(el);
        ensureTip().setAttribute('data-show', '');
        tip.setAttribute('aria-hidden', 'false');
    }

    function hide() {
        current = null;
        if (tip) {
            tip.removeAttribute('data-show');
            tip.setAttribute('aria-hidden', 'true');
        }
    }

    function bindTips() {
        if (document.documentElement.getAttribute('data-mjb-tips') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-mjb-tips', '1');
        document.addEventListener('mouseover', function (e) {
            var el = e.target.closest('[data-tip]');
            if (el && el !== current) {
                show(el);
            }
        });
        document.addEventListener('mouseout', function (e) {
            var el = e.target.closest('[data-tip]');
            if (el && el === current) {
                hide();
            }
        });
        document.addEventListener('focusin', function (e) {
            var el = e.target.closest('[data-tip]');
            if (el) {
                show(el);
            }
        });
        document.addEventListener('focusout', hide);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hide();
            }
        });
        window.addEventListener('scroll', function () {
            if (current) {
                place(current);
            }
        }, true);
        window.addEventListener('resize', function () {
            if (current) {
                place(current);
            }
        });
    }

    function bindNav() {
        var toggle = document.getElementById('mjb-nav-toggle');
        var nav = document.getElementById('mjb-admin-nav');
        if (!toggle || !nav || toggle.getAttribute('data-mjb-bound') === '1') {
            return;
        }
        toggle.setAttribute('data-mjb-bound', '1');

        function setOpen(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                nav.setAttribute('data-open', '');
            } else {
                nav.removeAttribute('data-open');
            }
        }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                setOpen(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                setOpen(false);
            }
        });
        nav.querySelectorAll('.mjb-admin-tabs__btn').forEach(function (a) {
            a.addEventListener('click', function () {
                setOpen(false);
            });
        });
    }

    function rangesIn(root) {
        var list = [];
        if (!root) {
            root = document;
        }
        if (root.nodeType === 1 && root.classList && root.classList.contains('mjb-range')) {
            list.push(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll('.mjb-range').forEach(function (range) {
                list.push(range);
            });
        }
        return list;
    }

    function moveThumb(range, btn) {
        if (!range) {
            return;
        }
        var thumb = range.querySelector('.mjb-range__thumb');
        btn = btn || range.querySelector('[aria-pressed="true"]');
        if (!thumb || !btn) {
            return;
        }
        var rr = range.getBoundingClientRect();
        var br = btn.getBoundingClientRect();
        var bw = parseFloat(window.getComputedStyle(range).borderLeftWidth) || 0;
        thumb.style.width = br.width + 'px';
        thumb.style.transform = 'translateX(' + (br.left - rr.left - bw) + 'px)';
    }

    var rangeResizeBound = false;

    function bindRange(root) {
        rangesIn(root || document).forEach(function (range) {
            moveThumb(range);
            requestAnimationFrame(function () {
                range.setAttribute('data-ready', '');
            });
        });
        if (rangeResizeBound) {
            return;
        }
        rangeResizeBound = true;
        window.addEventListener('resize', function () {
            rangesIn(document).forEach(function (range) {
                moveThumb(range);
            });
        });
    }

    window.mjbAdminDashboard = {
        init: function (root) {
            bindTips();
            bindNav();
            bindRange(root || document);
        },
        moveThumbs: function (root) {
            rangesIn(root || document).forEach(function (range) {
                moveThumb(range);
            });
        }
    };

    function dismissStatusBox(box) {
        if (!box || box.getAttribute('data-dismissing') === '1') {
            return;
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            box.remove();
            return;
        }
        box.setAttribute('data-dismissing', '1');
        box.style.transition = 'none';
        box.style.boxSizing = 'border-box';
        box.style.overflow = 'hidden';
        box.style.height = box.getBoundingClientRect().height + 'px';
        window.requestAnimationFrame(function () {
            box.style.transition = 'height 0.32s cubic-bezier(0.16, 1, 0.3, 1), margin 0.32s cubic-bezier(0.16, 1, 0.3, 1), padding 0.32s cubic-bezier(0.16, 1, 0.3, 1), border-width 0.32s cubic-bezier(0.16, 1, 0.3, 1)';
            box.style.height = '0px';
            box.style.marginTop = '0px';
            box.style.marginBottom = '0px';
            box.style.paddingTop = '0px';
            box.style.paddingBottom = '0px';
            box.style.borderTopWidth = '0px';
            box.style.borderBottomWidth = '0px';
        });
        var finished = false;
        function done(event) {
            if (finished) {
                return;
            }
            if (event && event.propertyName && event.propertyName !== 'height') {
                return;
            }
            finished = true;
            box.remove();
        }
        box.addEventListener('transitionend', done);
        window.setTimeout(function () {
            done();
        }, 420);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.notice-dismiss');
        if (!button) {
            return;
        }
        var notice = button.closest('.notice-success, .notice-info, .notice-warning, .notice-error, .updated');
        if (!notice) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        dismissStatusBox(notice);
    }, true);

    function slideToActionStatus() {
        var box = document.querySelector('.notice-success, .notice-error, .notice.updated');
        if (!box) {
            return;
        }
        if (window.history && 'scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }

    $(function () {
        window.mjbAdminDashboard.init(document);
        slideToActionStatus();
    });
})(jQuery);
