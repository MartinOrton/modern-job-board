/**
 * Admin Jobs tab: density, views, search, filters, menus, bulk bar.
 */
(function ($) {
    'use strict';

    var DENSITY_KEY = 'mjb-jobs-density';
    var searchTimer = null;
    var openMenu = null;
    var acRequest = null;
    var acOpen = false;
    var acGen = 0;

    function config() {
        return window.mjb_admin_tabs || {};
    }

    function i18n(key, fallback) {
        var pack = config().i18n || {};
        return pack[key] || fallback;
    }

    function reload(opts) {
        if (window.mjbAdminTabs && typeof window.mjbAdminTabs.reloadJobs === 'function') {
            window.mjbAdminTabs.reloadJobs(opts);
        }
    }

    function rootOf(el) {
        return el ? el.closest('.mjb-tab-panel--jobs') : document.querySelector('.mjb-tab-panel--jobs');
    }

    function applyDensity(panel, density) {
        if (!panel) {
            return;
        }
        density = density === 'compact' ? 'compact' : 'comfortable';
        panel.setAttribute('data-density', density);
        var range = document.getElementById('mjb-jobs-density');
        if (range) {
            range.querySelectorAll('button[data-density]').forEach(function (btn) {
                btn.setAttribute('aria-pressed', btn.getAttribute('data-density') === density ? 'true' : 'false');
            });
        }
        try {
            window.localStorage.setItem(DENSITY_KEY, density);
        } catch (e) {
            /* ignore */
        }
        if (window.mjbAdminDashboard && typeof window.mjbAdminDashboard.moveThumbs === 'function') {
            window.requestAnimationFrame(function () {
                window.mjbAdminDashboard.moveThumbs(range);
            });
        }
    }

    function syncExportLink(root) {
        var link = document.getElementById('mjb-export-jobs');
        var base = (config().export_jobs_url || (link && link.getAttribute('href')) || '');
        if (!link || !base || !root) {
            return;
        }
        var url;
        try {
            url = new URL(base, window.location.origin);
        } catch (e) {
            return;
        }
        ['mjb_list', 'mjb_q', 'mjb_company', 'mjb_cat', 'mjb_type'].forEach(function (key) {
            url.searchParams.delete(key);
        });
        var view = root.getAttribute('data-view') || 'all';
        var q = root.getAttribute('data-q') || '';
        var company = root.getAttribute('data-company') || '';
        var cat = root.getAttribute('data-cat') || '';
        var type = root.getAttribute('data-type') || '';
        if (view && view !== 'all') {
            url.searchParams.set('mjb_list', view);
        }
        if (q) {
            url.searchParams.set('mjb_q', q);
        }
        if (company) {
            url.searchParams.set('mjb_company', company);
        }
        if (cat) {
            url.searchParams.set('mjb_cat', cat);
        }
        if (type) {
            url.searchParams.set('mjb_type', type);
        }
        link.setAttribute('href', url.toString());
    }

    function closeMenu() {
        if (!openMenu) {
            return;
        }
        openMenu.menu.setAttribute('hidden', 'hidden');
        openMenu.menu.classList.remove('is-flip');
        if (openMenu.btn) {
            openMenu.btn.setAttribute('aria-expanded', 'false');
        }
        openMenu = null;
    }

    function searchRoot() {
        return document.getElementById('mjb-jobs-search');
    }

    function setSearchFilled(value) {
        var wrap = searchRoot();
        if (!wrap) {
            return;
        }
        if (value) {
            wrap.classList.add('is-filled');
        } else {
            wrap.classList.remove('is-filled');
        }
    }

    function closeAc() {
        var wrap = searchRoot();
        if (!wrap) {
            acOpen = false;
            return;
        }
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb-jobs-q');
        if (menu) {
            menu.setAttribute('hidden', 'hidden');
            menu.innerHTML = '';
        }
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
        wrap.classList.remove('is-open', 'is-loading');
        acOpen = false;
    }

    function renderAcSkeleton(wrap) {
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb-jobs-q');
        if (!menu) {
            return;
        }
        menu.innerHTML = '';
        var i;
        for (i = 0; i < 4; i++) {
            var sk = document.createElement('div');
            sk.className = 'mjb-jobs-ac__item mjb-jobs-ac__item--skeleton';
            sk.setAttribute('role', 'presentation');
            sk.innerHTML = '<span class="mjb-skeleton mjb-skeleton--excerpt"></span>';
            menu.appendChild(sk);
        }
        menu.removeAttribute('hidden');
        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
        wrap.classList.add('is-open');
        acOpen = true;
    }

    function renderAcItems(wrap, items) {
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb-jobs-q');
        if (!menu) {
            return;
        }
        menu.innerHTML = '';
        if (!items || !items.length) {
            var empty = document.createElement('div');
            empty.className = 'mjb-jobs-ac__empty';
            empty.setAttribute('role', 'presentation');
            empty.textContent = i18n('noMatches', 'No matches');
            menu.appendChild(empty);
            menu.removeAttribute('hidden');
            if (input) {
                input.setAttribute('aria-expanded', 'true');
            }
            wrap.classList.add('is-open');
            acOpen = true;
            return;
        }

        var listId = (input && input.getAttribute('aria-controls')) || 'mjb-jobs-ac-list';
        items.forEach(function (item, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mjb-jobs-ac__item';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-value', item.value || '');
            btn.setAttribute('data-label', item.label || item.value || '');
            btn.setAttribute('data-kind', item.kind || '');
            btn.id = listId + '-opt-' + index;
            var label = document.createElement('span');
            label.className = 'mjb-jobs-ac__label';
            label.textContent = item.label || item.value || '';
            btn.appendChild(label);
            if (item.hint) {
                var hint = document.createElement('span');
                hint.className = 'mjb-jobs-ac__hint';
                hint.textContent = item.hint;
                btn.appendChild(hint);
            }
            menu.appendChild(btn);
        });
        menu.removeAttribute('hidden');
        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
        wrap.classList.add('is-open');
        acOpen = true;
    }

    function fetchSuggestions(query) {
        var wrap = searchRoot();
        var cfg = config();
        if (!wrap || !cfg.ajax_url || !cfg.nonce) {
            return;
        }
        if (acRequest && acRequest.abort) {
            acRequest.abort();
        }
        var gen = ++acGen;
        wrap.classList.add('is-loading');
        renderAcSkeleton(wrap);
        acRequest = $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_admin_jobs_suggest',
                security: cfg.nonce,
                q: query || '',
                limit: 12
            }
        }).done(function (res) {
            if (gen !== acGen || !document.body.contains(wrap)) {
                return;
            }
            if (!res || !res.success || !res.data) {
                renderAcItems(wrap, []);
                return;
            }
            renderAcItems(wrap, res.data.items || []);
        }).fail(function (xhr, status) {
            if (status !== 'abort' && gen === acGen && document.body.contains(wrap)) {
                renderAcItems(wrap, []);
            }
        }).always(function () {
            if (gen !== acGen) {
                return;
            }
            if (document.body.contains(wrap)) {
                wrap.classList.remove('is-loading');
            }
            acRequest = null;
        });
    }

    function applySearch(value) {
        if (acRequest && acRequest.abort) {
            acRequest.abort();
        }
        closeAc();
        var input = document.getElementById('mjb-jobs-q');
        if (input) {
            input.value = value;
        }
        setSearchFilled(value);
        reload({ q: value, page: 1 });
    }

    function acItems() {
        var wrap = searchRoot();
        if (!wrap) {
            return [];
        }
        return Array.prototype.slice.call(wrap.querySelectorAll('.mjb-jobs-ac__item:not(.mjb-jobs-ac__item--skeleton)'));
    }

    function openHostMenu(btn) {
        closeAc();
        var host = btn.parentNode;
        var menu = host.querySelector('.mjb-jobs-menu');
        var row = btn.closest('tr');

        if (!menu && row) {
            menu = document.createElement('div');
            menu.className = 'mjb-jobs-menu';
            menu.setAttribute('role', 'menu');
            menu.innerHTML = menuItemsFor(row);
            host.appendChild(menu);
        }
        if (!menu) {
            return;
        }

        if (openMenu && openMenu.btn === btn) {
            closeMenu();
            return;
        }
        closeMenu();

        menu.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
        menu.classList.remove('is-flip');
        if (menu.classList.contains('mjb-jobs-per-menu') || menu.getBoundingClientRect().bottom > window.innerHeight - 10) {
            menu.classList.add('is-flip');
        }
        openMenu = { btn: btn, menu: menu };
        var first = menu.querySelector('[aria-checked="true"]') || menu.querySelector('button');
        if (first && first.focus) {
            first.focus({ preventScroll: true });
        }
    }

    function svg(name) {
        var paths = {
            eye: '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
            copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
            star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            calendar: '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
            check: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            refresh: '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>'
        };
        var d = paths[name] || '';
        return '<svg class="mjb-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
    }

    function item(action, icon, label, danger) {
        return '<button type="button" role="menuitem" data-action="' + action + '"' + (danger ? ' class="is-danger"' : '') + '>' + svg(icon) + label + '</button>';
    }

    function menuItemsFor(row) {
        var status = row.getAttribute('data-status') || 'publish';
        var featured = row.getAttribute('data-featured') === '1';
        var viewUrl = row.querySelector('.mjb-jobs-title') ? row.querySelector('.mjb-jobs-title').getAttribute('href') : '';
        var head = item('view', 'eye', 'View on site') + item('duplicate', 'copy', 'Duplicate');
        if (status === 'draft') {
            return head + item('publish', 'check', 'Publish now') + '<hr>' + item('delete', 'trash', 'Delete', true);
        }
        if (status === 'pending') {
            return head + item('approve', 'check', 'Approve and publish') + '<hr>' + item('delete', 'trash', 'Reject and delete', true);
        }
        if (status === 'expired') {
            return head + item('republish', 'refresh', 'Republish') + item('feature', 'star', 'Feature when live') + '<hr>' + item('delete', 'trash', 'Delete', true);
        }
        if (status === 'filled') {
            return head + item('unfill', 'refresh', 'Reopen listing') + '<hr>' + item('delete', 'trash', 'Delete', true);
        }
        return head +
            item(featured ? 'unfeature' : 'feature', 'star', featured ? 'Remove feature' : 'Feature listing') +
            item('extend', 'calendar', 'Extend expiry') +
            item('fill', 'check', 'Mark filled') +
            '<hr>' +
            item('delete', 'trash', 'Delete', true) +
            (viewUrl ? '' : '');
    }

    function selectedIds(root) {
        var ids = [];
        if (!root) {
            return ids;
        }
        root.querySelectorAll('tbody tr[data-selected]').forEach(function (tr) {
            var id = parseInt(tr.getAttribute('data-job-id'), 10);
            if (id) {
                ids.push(id);
            }
        });
        return ids;
    }

    function syncSelection(root) {
        if (!root) {
            return;
        }
        var rows = [].slice.call(root.querySelectorAll('tbody tr'));
        var boxes = rows.map(function (r) {
            return r.querySelector('input[type=checkbox]');
        }).filter(Boolean);
        var on = boxes.filter(function (b) {
            return b.checked;
        });
        rows.forEach(function (r) {
            var b = r.querySelector('input[type=checkbox]');
            if (b && b.checked) {
                r.setAttribute('data-selected', '');
            } else {
                r.removeAttribute('data-selected');
            }
        });
        var selAll = root.querySelector('#mjb-jobs-sel-all');
        if (selAll) {
            selAll.checked = boxes.length > 0 && on.length === boxes.length;
            selAll.indeterminate = on.length > 0 && on.length < boxes.length;
        }
        var bar = root.querySelector('#mjb-jobs-bulkbar');
        var count = root.querySelector('#mjb-jobs-bulk-count');
        if (!bar) {
            return;
        }
        if (on.length) {
            if (count) {
                count.textContent = on.length === 1
                    ? i18n('jobSelected', '1 job selected')
                    : i18n('jobsSelected', '%d jobs selected').replace('%d', String(on.length));
            }
            bar.setAttribute('data-open', '');
        } else {
            bar.removeAttribute('data-open');
        }
    }

    function clearSelection(root) {
        if (!root) {
            return;
        }
        root.querySelectorAll('tbody input[type=checkbox]').forEach(function (b) {
            b.checked = false;
        });
        var selAll = root.querySelector('#mjb-jobs-sel-all');
        if (selAll) {
            selAll.checked = false;
            selAll.indeterminate = false;
        }
        syncSelection(root);
    }

    function runAction(action, ids, row) {
        if (action === 'view') {
            var url = row ? row.getAttribute('data-view-url') : '';
            if (!url && row) {
                var title = row.querySelector('.mjb-jobs-title');
                url = title ? title.getAttribute('href') : '';
            }
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
            return;
        }
        if (action === 'delete') {
            var trigger = row ? row.querySelector('.mjb-job-delete') : null;
            if (ids.length === 1 && trigger) {
                $(trigger).trigger('click');
                return;
            }
            var confirmMsg = i18n('confirmBulkDelete', 'Move the selected jobs to the trash?');
            if (!window.confirm(confirmMsg)) {
                return;
            }
        }

        var cfg = config();
        if (!cfg.ajax_url || !cfg.nonce || !ids.length) {
            return;
        }

        $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_admin_job_action',
                security: cfg.nonce,
                job_action: action,
                job_ids: ids
            }
        }).done(function (response) {
            if (!response || !response.success) {
                window.alert((response && response.data && response.data.message) || i18n('actionFailed', 'Could not update jobs.'));
                return;
            }
            if (action === 'duplicate' && response.data && response.data.extra && response.data.extra.edit_url) {
                window.location.href = response.data.extra.edit_url;
                return;
            }
            reload({});
        }).fail(function () {
            window.alert(i18n('actionFailed', 'Could not update jobs.'));
        });
    }

    function exportIds(ids) {
        var url = config().export_jobs_url;
        if (!url) {
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'job_ids[]';
            input.value = String(id);
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function bind(root) {
        if (!root) {
            return;
        }

        var stored = 'comfortable';
        try {
            stored = window.localStorage.getItem(DENSITY_KEY) || 'comfortable';
        } catch (e) {
            stored = 'comfortable';
        }
        applyDensity(root.querySelector('#mjb-jobs-panel'), stored);
        syncExportLink(root);
    }

    $(document).on('click', '#mjb-jobs-density button[data-density]', function (e) {
        e.preventDefault();
        var root = rootOf(this);
        applyDensity(root ? root.querySelector('#mjb-jobs-panel') : document.getElementById('mjb-jobs-panel'), this.getAttribute('data-density'));
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-view-pill', function (e) {
        e.preventDefault();
        if (this.getAttribute('aria-selected') === 'true') {
            return;
        }
        reload({ list: this.getAttribute('data-view') || 'all', page: 1 });
    });

    $(document).on('focus', '#mjb-jobs-q', function () {
        closeMenu();
        window.clearTimeout(searchTimer);
        fetchSuggestions($.trim(this.value));
    });

    $(document).on('input', '#mjb-jobs-q', function () {
        var input = this;
        setSearchFilled(input.value);
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            fetchSuggestions($.trim(input.value));
        }, 180);
    });

    $(document).on('click', '.mjb-jobs-search__clear', function (e) {
        e.preventDefault();
        applySearch('');
        var input = document.getElementById('mjb-jobs-q');
        if (input) {
            input.focus();
        }
    });

    $(document).on('keydown', '#mjb-jobs-q', function (e) {
        var items = acItems();
        var wrap = searchRoot();
        var menu = wrap ? wrap.querySelector('[data-mjb-ac-menu]') : null;
        var menuOpen = menu && !menu.hidden && items.length;

        if (e.key === 'ArrowDown' && menuOpen) {
            e.preventDefault();
            var down = items.indexOf(wrap.querySelector('.mjb-jobs-ac__item.is-active'));
            down = down < items.length - 1 ? down + 1 : 0;
            items.forEach(function (el) { el.classList.remove('is-active'); });
            items[down].classList.add('is-active');
            return;
        }
        if (e.key === 'ArrowUp' && menuOpen) {
            e.preventDefault();
            var up = items.indexOf(wrap.querySelector('.mjb-jobs-ac__item.is-active'));
            up = up > 0 ? up - 1 : items.length - 1;
            items.forEach(function (el) { el.classList.remove('is-active'); });
            items[up].classList.add('is-active');
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            var active = wrap ? wrap.querySelector('.mjb-jobs-ac__item.is-active') : null;
            if (active) {
                applySearch(active.getAttribute('data-value') || '');
                return;
            }
            applySearch($.trim(this.value));
            return;
        }
        if (e.key === 'Escape') {
            e.stopPropagation();
            if (acOpen) {
                closeAc();
                return;
            }
            if (this.value) {
                applySearch('');
            }
        }
    });

    $(document).on('mousedown', '.mjb-jobs-ac__item', function (e) {
        e.preventDefault();
        applySearch(this.getAttribute('data-value') || '');
    });

    $(document).on('click', '#mjb-jobs-reset', function (e) {
        e.preventDefault();
        reload({
            list: 'all',
            q: '',
            company: '',
            cat: '',
            type: '',
            orderby: 'posted',
            order: 'desc',
            page: 1
        });
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-jobs-table th[data-key] button', function (e) {
        e.preventDefault();
        var th = this.closest('th');
        if (!th) {
            return;
        }
        var key = th.getAttribute('data-key');
        var current = th.getAttribute('aria-sort');
        var order = current === 'descending' ? 'asc' : 'desc';
        reload({ orderby: key, order: order, page: 1 });
    });

    $(document).on('click', '.mjb-js-popup', function (e) {
        if (!this.closest('.mjb-tab-panel--jobs')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        openHostMenu(this);
    });

    $(document).on('click', function (e) {
        if (openMenu && !e.target.closest('.mjb-jobs-menu') && !e.target.closest('.mjb-js-popup')) {
            closeMenu();
        }
        if (acOpen && !e.target.closest('#mjb-jobs-search')) {
            closeAc();
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            if (openMenu) {
                var btn = openMenu.btn;
                closeMenu();
                if (btn) {
                    btn.focus();
                }
                return;
            }
            if (acOpen) {
                closeAc();
                return;
            }
            var root = document.querySelector('.mjb-jobs');
            var bar = root ? root.querySelector('#mjb-jobs-bulkbar') : null;
            if (bar && bar.hasAttribute('data-open')) {
                clearSelection(root);
            }
        }
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-jobs-menu button[data-action]', function (e) {
        e.preventDefault();
        var action = this.getAttribute('data-action');
        var row = this.closest('tr');
        var id = row ? parseInt(row.getAttribute('data-job-id'), 10) : 0;
        closeMenu();
        if (action && id) {
            runAction(action, [id], row);
        }
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-jobs-menu button[data-per]', function (e) {
        e.preventDefault();
        var per = this.getAttribute('data-per');
        closeMenu();
        reload({ per: per, page: 1 });
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-jobs-filter .mjb-jobs-menu button[data-value]', function (e) {
        e.preventDefault();
        var filter = this.closest('.mjb-jobs-filter');
        var key = filter ? filter.getAttribute('data-filter') : '';
        var value = this.getAttribute('data-value') || '';
        closeMenu();
        var opts = { page: 1 };
        if (key === 'company') {
            opts.company = value;
        } else if (key === 'cat') {
            opts.cat = value;
        } else if (key === 'type') {
            opts.type = value;
        }
        reload(opts);
    });

    $(document).on('click', '.mjb-tab-panel--jobs .mjb-jobs-act[data-action]', function (e) {
        e.preventDefault();
        var row = this.closest('tr');
        var id = row ? parseInt(row.getAttribute('data-job-id'), 10) : 0;
        var action = this.getAttribute('data-action');
        if (action && id) {
            runAction(action, [id], row);
        }
    });

    $(document).on('change', '#mjb-jobs-sel-all', function () {
        var root = rootOf(this);
        var checked = this.checked;
        if (!root) {
            return;
        }
        root.querySelectorAll('tbody input[type=checkbox]').forEach(function (b) {
            b.checked = checked;
        });
        syncSelection(root);
    });

    $(document).on('change', '.mjb-tab-panel--jobs tbody input[type=checkbox]', function () {
        syncSelection(rootOf(this));
    });

    $(document).on('click', '#mjb-jobs-bulk-clear', function (e) {
        e.preventDefault();
        clearSelection(rootOf(this));
    });

    $(document).on('click', '#mjb-jobs-bulkbar button[data-bulk]', function (e) {
        e.preventDefault();
        var root = rootOf(this);
        var ids = selectedIds(root);
        var action = this.getAttribute('data-bulk');
        if (!ids.length || !action) {
            return;
        }
        if (action === 'export') {
            exportIds(ids);
            return;
        }
        runAction(action, ids, null);
    });

    $(document).on('click', '.mjb-jobs-notice__close', function (e) {
        e.preventDefault();
        var notice = document.getElementById('mjb-jobs-apps-notice');
        if (notice) {
            notice.remove();
        }
        var cfg = config();
        if (!cfg.ajax_url || !cfg.nonce) {
            return;
        }
        $.post(cfg.ajax_url, {
            action: 'mjb_admin_jobs_dismiss_notice',
            security: cfg.nonce
        });
    });

    window.mjbAdminJobs = {
        init: function (root) {
            var jobs = root ? root.querySelector('.mjb-tab-panel--jobs') : document.querySelector('.mjb-tab-panel--jobs');
            bind(jobs);
        }
    };
})(jQuery);
