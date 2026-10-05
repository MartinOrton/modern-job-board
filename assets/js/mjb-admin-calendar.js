/**
 * Shared MJB date calendar modal (License & Plan + job editor).
 */
(function ($) {
    'use strict';

    var calView = new Date();
    calView.setDate(1);
    var calSliding = false;
    var calModalEl = null;
    var calTargetInput = null;
    var calOpenBtn = null;

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toCompactYmd(d) {
        return String(d.getFullYear()) + pad2(d.getMonth() + 1) + pad2(d.getDate());
    }

    function toIsoDate(d) {
        return String(d.getFullYear()) + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    function parseDate(raw) {
        var s = String(raw || '').trim();
        var iso;
        var compact;
        var y;
        var m;
        var d;
        var dt;
        if (!s || s === '00000000') {
            return null;
        }
        iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (iso) {
            y = parseInt(iso[1], 10);
            m = parseInt(iso[2], 10) - 1;
            d = parseInt(iso[3], 10);
            dt = new Date(y, m, d);
            if (dt.getFullYear() !== y || dt.getMonth() !== m || dt.getDate() !== d) {
                return null;
            }
            return dt;
        }
        compact = s.replace(/\D/g, '');
        if (compact.length >= 8) {
            compact = compact.slice(0, 8);
            if (compact === '00000000') {
                return null;
            }
            y = parseInt(compact.slice(0, 4), 10);
            m = parseInt(compact.slice(4, 6), 10) - 1;
            d = parseInt(compact.slice(6, 8), 10);
            dt = new Date(y, m, d);
            if (dt.getFullYear() !== y || dt.getMonth() !== m || dt.getDate() !== d) {
                return null;
            }
            return dt;
        }
        return null;
    }

    function extractTime(raw) {
        var s = String(raw || '');
        var match = s.match(/[T ](\d{2}):(\d{2})/);
        if (match) {
            return match[1] + ':' + match[2];
        }
        return '09:00';
    }

    function formatValue(input, date) {
        var format = (input && input.getAttribute('data-mjb-cal-format')) || 'iso';
        if (format === 'ymd') {
            return toCompactYmd(date);
        }
        if (format === 'datetime') {
            return toIsoDate(date) + ' ' + extractTime(input ? input.value : '');
        }
        return toIsoDate(date);
    }

    function emptyValue(input) {
        if (!input) {
            return '';
        }
        if (input.hasAttribute('data-mjb-cal-empty')) {
            return input.getAttribute('data-mjb-cal-empty');
        }
        return '';
    }

    function resolveModal(btn) {
        var id = btn ? (btn.getAttribute('data-mjb-cal-modal') || btn.getAttribute('aria-controls')) : '';
        if (id) {
            return document.getElementById(id);
        }
        return document.getElementById('mjb-license-cal-modal') || document.querySelector('.mjb-modal[data-mjb-cal]');
    }

    function resolveInput(btn) {
        var id = btn ? btn.getAttribute('data-mjb-cal-for') : '';
        if (id) {
            return document.getElementById(id);
        }
        return document.getElementById('mjb_gen_expires');
    }

    function calOpen() {
        return !!(calModalEl && !calModalEl.hasAttribute('hidden'));
    }

    function monthLabel() {
        return calView.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    }

    function buildGridHtml() {
        var year = calView.getFullYear();
        var month = calView.getMonth();
        var first = new Date(year, month, 1);
        var startPad = (first.getDay() + 6) % 7;
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var selected = parseDate(calTargetInput ? calTargetInput.value : '');
        var today = new Date();
        var html = '';
        var i;
        for (i = 0; i < startPad; i++) {
            html += '<button type="button" class="mjb-license-cal__day is-pad" tabindex="-1" disabled></button>';
        }
        for (i = 1; i <= daysInMonth; i++) {
            var cell = new Date(year, month, i);
            var ymd = toCompactYmd(cell);
            var cls = 'mjb-license-cal__day';
            if (selected && toCompactYmd(selected) === ymd) {
                cls += ' is-selected';
            }
            if (cell.getFullYear() === today.getFullYear() && cell.getMonth() === today.getMonth() && cell.getDate() === today.getDate()) {
                cls += ' is-today';
            }
            html += '<button type="button" class="' + cls + '" data-cal-day="' + ymd + '">' + i + '</button>';
        }
        return html;
    }

    function renderCal() {
        var modal = calModalEl;
        if (!modal) {
            return;
        }
        var label = modal.querySelector('[data-cal-label]');
        var grid = modal.querySelector('[data-cal-grid]');
        if (!label || !grid) {
            return;
        }
        label.textContent = monthLabel();
        grid.innerHTML = buildGridHtml();
    }

    function calPrefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function slideCal(dir) {
        var modal = calModalEl;
        var track = modal ? modal.querySelector('[data-cal-track]') : null;
        var outgoing = track ? track.querySelector('[data-cal-grid]') : null;
        var label = modal ? modal.querySelector('[data-cal-label]') : null;
        if (calSliding) {
            return;
        }
        calView.setMonth(calView.getMonth() + dir);
        if (!track || !outgoing || calPrefersReducedMotion()) {
            renderCal();
            return;
        }

        var incoming = document.createElement('div');
        incoming.className = 'mjb-license-cal__grid';
        incoming.setAttribute('data-cal-grid', '');
        incoming.innerHTML = buildGridHtml();
        calSliding = true;
        if (dir > 0) {
            track.appendChild(incoming);
            track.style.transform = 'translateX(0)';
        } else {
            track.insertBefore(incoming, outgoing);
            track.style.transform = 'translateX(-100%)';
        }
        if (label) {
            label.textContent = monthLabel();
        }
        track.offsetWidth;
        track.classList.add('is-animating');
        track.style.transform = dir > 0 ? 'translateX(-100%)' : 'translateX(0)';

        var finished = false;
        function done(e) {
            if (e && e.target !== track) {
                return;
            }
            if (finished) {
                return;
            }
            finished = true;
            track.removeEventListener('transitionend', done);
            if (outgoing.parentNode) {
                outgoing.parentNode.removeChild(outgoing);
            }
            track.classList.remove('is-animating');
            track.style.transform = '';
            calSliding = false;
        }
        track.addEventListener('transitionend', done);
        window.setTimeout(done, 400);
    }

    function applyOpenChrome(btn, modal) {
        var title = btn ? btn.getAttribute('data-mjb-cal-title') : '';
        var clearLabel = btn ? btn.getAttribute('data-mjb-cal-clear-label') : '';
        var heading = modal.querySelector('.mjb-modal__title');
        var noneBtn = modal.querySelector('[data-cal-none]');
        if (title && heading) {
            heading.textContent = title;
        }
        if (clearLabel && noneBtn) {
            noneBtn.textContent = clearLabel;
        }
    }

    function openCal(btn) {
        var modal = resolveModal(btn);
        var input = resolveInput(btn);
        var current;
        var track;
        var grids;
        var i;
        if (!modal) {
            return;
        }
        calModalEl = modal;
        calTargetInput = input;
        calOpenBtn = btn || null;
        applyOpenChrome(btn, modal);
        current = parseDate(input ? input.value : '');
        calView = current ? new Date(current.getFullYear(), current.getMonth(), 1) : new Date();
        calView.setDate(1);
        calSliding = false;
        track = modal.querySelector('[data-cal-track]');
        if (track) {
            grids = track.querySelectorAll('[data-cal-grid]');
            for (i = 1; i < grids.length; i++) {
                grids[i].parentNode.removeChild(grids[i]);
            }
            track.classList.remove('is-animating');
            track.style.transform = '';
        }
        renderCal();
        modal.classList.remove('is-closing');
        modal.removeAttribute('hidden');
        document.body.classList.add('mjb-modal-open');
        var first = modal.querySelector('[data-cal-day]') || modal.querySelector('[data-cal-none]');
        if (first && first.focus) {
            first.focus();
        }
    }

    function closeCal() {
        var modal = calModalEl;
        var openBtn = calOpenBtn;
        if (!modal || modal.hasAttribute('hidden') || modal.classList.contains('is-closing')) {
            return;
        }
        function hide() {
            modal.classList.remove('is-closing');
            modal.setAttribute('hidden', 'hidden');
            document.body.classList.remove('mjb-modal-open');
            calModalEl = null;
            calTargetInput = null;
            calOpenBtn = null;
            if (openBtn && openBtn.focus) {
                openBtn.focus();
            }
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            hide();
            return;
        }
        modal.classList.add('is-closing');
        window.setTimeout(hide, 180);
    }

    function setTarget(value) {
        if (calTargetInput) {
            calTargetInput.value = value;
        }
    }

    $(document).on('click', '[data-mjb-cal-open]', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (calOpen() && calOpenBtn === this) {
            closeCal();
            return;
        }
        if (calOpen()) {
            closeCal();
        }
        openCal(this);
    });

    $(document).on('click', '[data-mjb-cal-dismiss]', function (e) {
        e.preventDefault();
        closeCal();
    });

    $(document).on('click', '[data-cal-prev]', function (e) {
        e.preventDefault();
        slideCal(-1);
    });

    $(document).on('click', '[data-cal-next]', function (e) {
        e.preventDefault();
        slideCal(1);
    });

    $(document).on('click', '[data-cal-none]', function (e) {
        e.preventDefault();
        setTarget(emptyValue(calTargetInput));
        closeCal();
    });

    $(document).on('click', '[data-cal-day]', function (e) {
        e.preventDefault();
        var compact = this.getAttribute('data-cal-day');
        var date = parseDate(compact);
        if (!date) {
            return;
        }
        setTarget(formatValue(calTargetInput, date));
        closeCal();
    });
})(jQuery);
