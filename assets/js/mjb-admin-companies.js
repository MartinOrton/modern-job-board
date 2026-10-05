/**
 * Admin Companies tab: density, views, search, filters, menus, bulk bar.
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
        if (window.mjbAdminTabs && typeof window.mjbAdminTabs.reloadCompanies === 'function') {
            window.mjbAdminTabs.reloadCompanies(opts);
        }
    }

    function rootOf(el) {
        return el ? el.closest('.mjb-tab-panel--companies') : document.querySelector('.mjb-tab-panel--companies');
    }

    function applyDensity(panel, density) {
        if (!panel) {
            return;
        }
        density = density === 'compact' ? 'compact' : 'comfortable';
        panel.setAttribute('data-density', density);
        var range = document.getElementById('mjb-companies-density');
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
        return document.getElementById('mjb-companies-search');
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
        var input = wrap.querySelector('#mjb-companies-q');
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
        var input = wrap.querySelector('#mjb-companies-q');
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
        var input = wrap.querySelector('#mjb-companies-q');
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

        var listId = (input && input.getAttribute('aria-controls')) || 'mjb-companies-ac-list';
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
                action: 'mjb_admin_companies_suggest',
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
        var input = document.getElementById('mjb-companies-q');
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
            briefcase: '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
            check: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
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
        var head = item('view', 'eye', 'View on site') + item('jobs', 'briefcase', 'View jobs');
        if (status === 'pending' || status === 'draft') {
            return head + item('approve', 'check', 'Approve and publish') + '<hr>' + item('delete', 'trash', 'Delete', true);
        }
        return head + '<hr>' + item('delete', 'trash', 'Delete', true);
    }

    function selectedIds(root) {
        var ids = [];
        if (!root) {
            return ids;
        }
        root.querySelectorAll('tbody tr[data-selected]').forEach(function (tr) {
            var id = parseInt(tr.getAttribute('data-company-id'), 10);
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
        var selAll = root.querySelector('#mjb-companies-sel-all');
        if (selAll) {
            selAll.checked = boxes.length > 0 && on.length === boxes.length;
            selAll.indeterminate = on.length > 0 && on.length < boxes.length;
        }
        var bar = root.querySelector('#mjb-companies-bulkbar');
        var count = root.querySelector('#mjb-companies-bulk-count');
        if (!bar) {
            return;
        }
        if (on.length) {
            if (count) {
                count.textContent = on.length === 1
                    ? i18n('companySelected', '1 company selected')
                    : i18n('companiesSelected', '%d companies selected').replace('%d', String(on.length));
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
        var selAll = root.querySelector('#mjb-companies-sel-all');
        if (selAll) {
            selAll.checked = false;
            selAll.indeterminate = false;
        }
        syncSelection(root);
    }

    function runAction(action, ids, row) {
        if (action === 'view') {
            var url = row ? row.getAttribute('data-view-url') : '';
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
            return;
        }
        if (action === 'jobs') {
            var id = row ? parseInt(row.getAttribute('data-company-id'), 10) : 0;
            if (window.mjbAdminTabs && typeof window.mjbAdminTabs.reloadJobs === 'function' && id) {
                window.mjbAdminTabs.reloadJobs({ company: String(id), list: 'all', q: '', page: 1 });
            }
            return;
        }
        if (action === 'delete') {
            var confirmMsg = i18n('confirmBulkDeleteCompanies', 'Move the selected companies to the trash?');
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
                action: 'mjb_admin_company_action',
                security: cfg.nonce,
                company_action: action,
                company_ids: ids
            }
        }).done(function (response) {
            if (!response || !response.success) {
                window.alert((response && response.data && response.data.message) || i18n('actionFailed', 'Could not update companies.'));
                return;
            }
            reload({});
        }).fail(function () {
            window.alert(i18n('actionFailed', 'Could not update companies.'));
        });
    }

    function exportIds(ids) {
        var url = config().export_companies_url;
        if (!url) {
            return;
        }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'company_ids[]';
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
        applyDensity(root.querySelector('#mjb-companies-panel'), stored);
    }

    $(document).on('click', '#mjb-companies-density button[data-density]', function (e) {
        e.preventDefault();
        var root = rootOf(this);
        applyDensity(root ? root.querySelector('#mjb-companies-panel') : document.getElementById('mjb-companies-panel'), this.getAttribute('data-density'));
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-view-pill', function (e) {
        e.preventDefault();
        if (this.getAttribute('aria-selected') === 'true') {
            return;
        }
        reload({ list: this.getAttribute('data-view') || 'all', page: 1 });
    });

    $(document).on('focus', '#mjb-companies-q', function () {
        closeMenu();
        window.clearTimeout(searchTimer);
        fetchSuggestions($.trim(this.value));
    });

    $(document).on('input', '#mjb-companies-q', function () {
        var value = $.trim(this.value);
        setSearchFilled(value);
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            fetchSuggestions(value);
        }, 180);
    });

    $(document).on('keydown', '#mjb-companies-q', function (e) {
        var items = acItems();
        var active = items.filter(function (el) {
            return el.classList.contains('is-active');
        })[0];
        var idx = active ? items.indexOf(active) : -1;
        if (e.key === 'ArrowDown' && items.length) {
            e.preventDefault();
            if (active) {
                active.classList.remove('is-active');
            }
            idx = Math.min(items.length - 1, idx + 1);
            items[idx].classList.add('is-active');
            items[idx].scrollIntoView({ block: 'nearest' });
            return;
        }
        if (e.key === 'ArrowUp' && items.length) {
            e.preventDefault();
            if (active) {
                active.classList.remove('is-active');
            }
            idx = Math.max(0, idx - 1);
            items[idx].classList.add('is-active');
            items[idx].scrollIntoView({ block: 'nearest' });
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (active) {
                applySearch(active.getAttribute('data-value') || '');
                return;
            }
            applySearch($.trim(this.value));
            return;
        }
        if (e.key === 'Escape') {
            closeAc();
        }
    });

    $(document).on('click', '#mjb-companies-search .mjb-jobs-search__clear', function (e) {
        e.preventDefault();
        applySearch('');
    });

    $(document).on('mousedown', '#mjb-companies-search .mjb-jobs-ac__item', function (e) {
        e.preventDefault();
        applySearch(this.getAttribute('data-value') || '');
    });

    $(document).on('click', '#mjb-companies-reset', function (e) {
        e.preventDefault();
        reload({
            list: 'all',
            q: '',
            loc: '',
            orderby: 'posted',
            order: 'desc',
            page: 1
        });
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-jobs-table th[data-key] button', function (e) {
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

    $(document).on('click', '.mjb-tab-panel--companies .mjb-js-popup', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openHostMenu(this);
    });

    $(document).on('click', function (e) {
        if (openMenu && !e.target.closest('.mjb-tab-panel--companies .mjb-jobs-menu') && !e.target.closest('.mjb-tab-panel--companies .mjb-js-popup')) {
            closeMenu();
        }
        if (acOpen && !e.target.closest('#mjb-companies-search')) {
            closeAc();
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
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
        var root = document.querySelector('.mjb-tab-panel--companies');
        var bar = root ? root.querySelector('#mjb-companies-bulkbar') : null;
        if (bar && bar.hasAttribute('data-open')) {
            clearSelection(root);
        }
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-jobs-menu button[data-action]', function (e) {
        e.preventDefault();
        var action = this.getAttribute('data-action');
        var row = this.closest('tr');
        var id = row ? parseInt(row.getAttribute('data-company-id'), 10) : 0;
        closeMenu();
        if (action && id) {
            runAction(action, [id], row);
        }
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-jobs-menu button[data-per]', function (e) {
        e.preventDefault();
        var per = this.getAttribute('data-per');
        closeMenu();
        reload({ per: per, page: 1 });
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-jobs-filter .mjb-jobs-menu button[data-value]', function (e) {
        e.preventDefault();
        var filter = this.closest('.mjb-jobs-filter');
        var key = filter ? filter.getAttribute('data-filter') : '';
        var value = this.getAttribute('data-value') || '';
        closeMenu();
        var opts = { page: 1 };
        if (key === 'loc') {
            opts.loc = value;
        }
        reload(opts);
    });

    $(document).on('click', '.mjb-tab-panel--companies .mjb-jobs-act[data-action]', function (e) {
        e.preventDefault();
        var row = this.closest('tr');
        var id = row ? parseInt(row.getAttribute('data-company-id'), 10) : 0;
        var action = this.getAttribute('data-action');
        if (action && id) {
            runAction(action, [id], row);
        }
    });

    $(document).on('change', '#mjb-companies-sel-all', function () {
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

    $(document).on('change', '.mjb-tab-panel--companies tbody input[type=checkbox]', function () {
        syncSelection(rootOf(this));
    });

    $(document).on('click', '#mjb-companies-bulk-clear', function (e) {
        e.preventDefault();
        clearSelection(rootOf(this));
    });

    $(document).on('click', '#mjb-companies-bulkbar button[data-bulk]', function (e) {
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

    window.mjbAdminCompanies = {
        init: function (root) {
            var panel = root ? root.querySelector('.mjb-tab-panel--companies') : document.querySelector('.mjb-tab-panel--companies');
            bind(panel);
        }
    };
})(jQuery);
