/**
 * Settings tab dropdowns — Jobs filter chrome + menu.
 */
(function ($) {
    'use strict';

    var openMenu = null;
    var CHEVRON = '<svg class="mjb-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

    function panelOf(el) {
        return el && el.closest ? el.closest('.mjb-tab-panel--settings') : document.querySelector('.mjb-tab-panel--settings');
    }

    function setOpenState(host, open) {
        if (!host) {
            return;
        }
        host.classList.toggle('is-open', !!open);
        var row = host.closest('tr');
        if (row) {
            row.classList.toggle('is-open', !!open);
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
        setOpenState(openMenu.host, false);
        openMenu = null;
    }

    function flagImg(opt) {
        var cc = String((opt && opt.getAttribute('data-flag')) || '').toLowerCase();
        if (!/^[a-z]{2}$/.test(cc)) {
            return null;
        }
        var img = document.createElement('img');
        img.className = 'mjb-currency-flag';
        img.src = 'https://flagcdn.com/w40/' + cc + '.png';
        img.alt = '';
        img.width = 20;
        img.height = 15;
        img.loading = 'lazy';
        img.decoding = 'async';
        return img;
    }

    function rebuild(wrap) {
        var select = wrap.querySelector('select');
        var btn = wrap.querySelector('.mjb-jobs-filter__btn');
        var menu = wrap.querySelector('.mjb-jobs-menu');
        if (!select || !btn || !menu) {
            return;
        }
        var selected = select.options[select.selectedIndex];
        var label = selected ? selected.text : '';
        btn.innerHTML = '';
        var selectedFlag = flagImg(selected);
        if (selectedFlag) {
            btn.appendChild(selectedFlag);
        }
        var b = document.createElement('b');
        b.textContent = label;
        btn.appendChild(b);
        btn.insertAdjacentHTML('beforeend', CHEVRON);
        btn.setAttribute('aria-label', label);

        menu.innerHTML = '';
        Array.prototype.forEach.call(select.options, function (opt) {
            var item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'menuitemradio');
            item.setAttribute('data-value', opt.value);
            item.setAttribute('aria-checked', opt.selected ? 'true' : 'false');
            var flag = flagImg(opt);
            if (flag) {
                item.appendChild(flag);
            }
            item.appendChild(document.createTextNode(opt.text));
            if (opt.disabled) {
                item.disabled = true;
            }
            menu.appendChild(item);
        });
    }

    function wrapSelect(select) {
        if (!select || select.closest('.mjb-settings-select') || select.closest('.mjb-currency-ac')) {
            return;
        }
        if (select.multiple || (select.size && select.size > 1)) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'mjb-jobs-filter mjb-settings-select';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mjb-jobs-filter__btn';
        btn.setAttribute('aria-haspopup', 'menu');
        btn.setAttribute('aria-expanded', 'false');
        wrap.appendChild(btn);

        var menu = document.createElement('div');
        menu.className = 'mjb-jobs-menu';
        menu.setAttribute('role', 'menu');
        menu.setAttribute('hidden', 'hidden');
        wrap.appendChild(menu);

        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        rebuild(wrap);
    }

    function enhance(root) {
        var panel = panelOf(root) || (root && root.querySelector ? root.querySelector('.mjb-tab-panel--settings') : null);
        if (!panel) {
            return;
        }
        panel.querySelectorAll('select').forEach(wrapSelect);
    }

    function openHostMenu(btn) {
        var host = btn.parentNode;
        var menu = host.querySelector('.mjb-jobs-menu');
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
        if (menu.getBoundingClientRect().bottom > window.innerHeight - 10) {
            menu.classList.add('is-flip');
        }
        setOpenState(host, true);
        openMenu = { btn: btn, menu: menu, host: host };
        var first = menu.querySelector('[aria-checked="true"]') || menu.querySelector('button');
        if (first && first.focus) {
            first.focus({ preventScroll: true });
        }
    }

    $(document).on('click', '.mjb-tab-panel--settings .mjb-settings-select .mjb-jobs-filter__btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openHostMenu(this);
    });

    $(document).on('click', '.mjb-tab-panel--settings .mjb-settings-select .mjb-jobs-menu button[data-value]', function (e) {
        e.preventDefault();
        var wrap = this.closest('.mjb-settings-select');
        if (!wrap) {
            return;
        }
        var select = wrap.querySelector('select');
        if (!select) {
            return;
        }
        select.value = this.getAttribute('data-value');
        $(select).trigger('change');
        rebuild(wrap);
        closeMenu();
    });

    $(document).on('click', function (e) {
        if (openMenu && !e.target.closest('.mjb-settings-select')) {
            closeMenu();
        }
        if (!e.target.closest('#mjb-gjobs-currency-ac')) {
            closeCurrencyAc();
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            if (calOpen()) {
                closeCal();
                return;
            }
            if (openMenu) {
                var btn = openMenu.btn;
                closeMenu();
                if (btn) {
                    btn.focus();
                }
            }
        }
    });

    $(document).on('change', '.mjb-tab-panel--settings .mjb-doc-label-choice', function () {
        var current = this;
        if (!current.checked) {
            current.checked = true;
            return;
        }
        var name = current.getAttribute('name');
        document.querySelectorAll('.mjb-tab-panel--settings .mjb-doc-label-choice[name="' + name + '"]').forEach(function (el) {
            if (el !== current) {
                el.checked = false;
            }
        });
    });

    var curTimer = null;
    var curRequest = null;
    var curGen = 0;

    function currencyRoot() {
        return document.getElementById('mjb-gjobs-currency-ac');
    }

    function currencyConfig() {
        return window.mjb_admin_tabs || {};
    }

    function setCurrencyFlag(wrap, flag) {
        if (!wrap) {
            return;
        }
        var img = wrap.querySelector('.mjb-currency-ac__flag');
        flag = String(flag || '').toLowerCase();
        if (!img) {
            return;
        }
        if (!/^[a-z]{2}$/.test(flag)) {
            img.hidden = true;
            img.removeAttribute('src');
            wrap.classList.remove('has-flag');
            return;
        }
        img.src = 'https://flagcdn.com/w40/' + flag + '.png';
        img.hidden = false;
        wrap.classList.add('has-flag');
    }

    function closeCurrencyAc() {
        var wrap = currencyRoot();
        if (!wrap) {
            return;
        }
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb_gjobs_currency_q');
        if (menu) {
            menu.setAttribute('hidden', 'hidden');
            menu.innerHTML = '';
        }
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
        wrap.classList.remove('is-open', 'is-loading');
        setOpenState(wrap, false);
    }

    function renderCurrencySkeleton(wrap) {
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb_gjobs_currency_q');
        if (!menu) {
            return;
        }
        menu.innerHTML = '';
        var i;
        for (i = 0; i < 4; i++) {
            var sk = document.createElement('button');
            sk.type = 'button';
            sk.disabled = true;
            sk.setAttribute('role', 'presentation');
            sk.innerHTML = '<span class="mjb-skeleton mjb-skeleton--excerpt"></span>';
            menu.appendChild(sk);
        }
        menu.removeAttribute('hidden');
        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
        wrap.classList.add('is-open');
        setOpenState(wrap, true);
    }

    function renderCurrencyItems(wrap, items) {
        var menu = wrap.querySelector('[data-mjb-ac-menu]');
        var input = wrap.querySelector('#mjb_gjobs_currency_q');
        if (!menu) {
            return;
        }
        menu.innerHTML = '';
        if (!items || !items.length) {
            var empty = document.createElement('button');
            empty.type = 'button';
            empty.disabled = true;
            empty.setAttribute('role', 'presentation');
            empty.textContent = 'No matches';
            menu.appendChild(empty);
            menu.removeAttribute('hidden');
            if (input) {
                input.setAttribute('aria-expanded', 'true');
            }
            wrap.classList.add('is-open');
            setOpenState(wrap, true);
            return;
        }
        var listId = (input && input.getAttribute('aria-controls')) || 'mjb-gjobs-currency-ac-list';
        items.forEach(function (item, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-value', item.value || '');
            btn.setAttribute('data-label', item.label || item.value || '');
            btn.setAttribute('data-flag', item.flag || '');
            btn.id = listId + '-opt-' + index;
            var img = flagImg({ getAttribute: function () { return item.flag || ''; } });
            if (img) {
                btn.appendChild(img);
            }
            btn.appendChild(document.createTextNode(item.label || item.value || ''));
            menu.appendChild(btn);
        });
        menu.removeAttribute('hidden');
        if (input) {
            input.setAttribute('aria-expanded', 'true');
        }
        wrap.classList.add('is-open');
        setOpenState(wrap, true);
    }

    function fetchCurrencies(query) {
        var wrap = currencyRoot();
        var cfg = currencyConfig();
        if (!wrap || !cfg.ajax_url || !cfg.nonce) {
            return;
        }
        if (curRequest && curRequest.abort) {
            curRequest.abort();
        }
        var gen = ++curGen;
        wrap.classList.add('is-loading');
        renderCurrencySkeleton(wrap);
        curRequest = $.ajax({
            url: cfg.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_admin_currency_suggest',
                security: cfg.nonce,
                q: query || '',
                limit: 12
            }
        }).done(function (res) {
            if (gen !== curGen || !document.body.contains(wrap)) {
                return;
            }
            if (!res || !res.success || !res.data) {
                renderCurrencyItems(wrap, []);
                return;
            }
            renderCurrencyItems(wrap, res.data.items || []);
        }).fail(function (xhr, status) {
            if (status !== 'abort' && gen === curGen && document.body.contains(wrap)) {
                renderCurrencyItems(wrap, []);
            }
        }).always(function () {
            if (gen !== curGen) {
                return;
            }
            if (document.body.contains(wrap)) {
                wrap.classList.remove('is-loading');
            }
            curRequest = null;
        });
    }

    function applyCurrency(value, label, flag) {
        var wrap = currencyRoot();
        if (!wrap) {
            return;
        }
        var input = wrap.querySelector('#mjb_gjobs_currency_q');
        var hidden = wrap.querySelector('#mjb_gjobs_currency');
        if (hidden) {
            hidden.value = value || '';
            hidden.setAttribute('data-flag', flag || '');
        }
        if (input) {
            input.value = label || '';
        }
        setCurrencyFlag(wrap, flag);
        if (value) {
            wrap.classList.add('is-filled');
        } else {
            wrap.classList.remove('is-filled');
        }
        closeCurrencyAc();
    }

    function currencyItems() {
        var wrap = currencyRoot();
        if (!wrap) {
            return [];
        }
        return Array.prototype.slice.call(wrap.querySelectorAll('.mjb-jobs-menu button[data-value]'));
    }

    $(document).on('focus', '#mjb_gjobs_currency_q', function () {
        window.clearTimeout(curTimer);
        fetchCurrencies($.trim(this.value));
    });

    $(document).on('input', '#mjb_gjobs_currency_q', function () {
        var wrap = currencyRoot();
        var hidden = wrap ? wrap.querySelector('#mjb_gjobs_currency') : null;
        if (hidden) {
            hidden.value = '';
            hidden.setAttribute('data-flag', '');
        }
        setCurrencyFlag(wrap, '');
        if (wrap) {
            wrap.classList.toggle('is-filled', $.trim(this.value) !== '');
        }
        window.clearTimeout(curTimer);
        var q = $.trim(this.value);
        curTimer = window.setTimeout(function () {
            fetchCurrencies(q);
        }, 180);
    });

    $(document).on('click', '#mjb-gjobs-currency-ac .mjb-currency-ac__clear', function (e) {
        e.preventDefault();
        applyCurrency('', '', '');
        var input = document.getElementById('mjb_gjobs_currency_q');
        if (input) {
            input.focus();
        }
    });

    $(document).on('keydown', '#mjb_gjobs_currency_q', function (e) {
        var wrap = currencyRoot();
        var items = currencyItems();
        var menu = wrap ? wrap.querySelector('[data-mjb-ac-menu]') : null;
        var menuOpen = menu && !menu.hidden && items.length;

        if (e.key === 'ArrowDown' && menuOpen) {
            e.preventDefault();
            var down = items.indexOf(wrap.querySelector('.mjb-jobs-menu button.is-active'));
            down = down < items.length - 1 ? down + 1 : 0;
            items.forEach(function (el) { el.classList.remove('is-active'); });
            items[down].classList.add('is-active');
            return;
        }
        if (e.key === 'ArrowUp' && menuOpen) {
            e.preventDefault();
            var up = items.indexOf(wrap.querySelector('.mjb-jobs-menu button.is-active'));
            up = up > 0 ? up - 1 : items.length - 1;
            items.forEach(function (el) { el.classList.remove('is-active'); });
            items[up].classList.add('is-active');
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            var active = wrap ? wrap.querySelector('.mjb-jobs-menu button.is-active') : null;
            var pick = active || items[0];
            if (pick) {
                applyCurrency(pick.getAttribute('data-value') || '', pick.getAttribute('data-label') || '', pick.getAttribute('data-flag') || '');
            }
            return;
        }
        if (e.key === 'Escape') {
            e.stopPropagation();
            closeCurrencyAc();
        }
    });

    $(document).on('mousedown', '#mjb-gjobs-currency-ac .mjb-jobs-menu button', function (e) {
        e.preventDefault();
        if (this.disabled) {
            return;
        }
        applyCurrency(this.getAttribute('data-value') || '', this.getAttribute('data-label') || '', this.getAttribute('data-flag') || '');
    });

    $(document).on('submit', '#mjb-settings-form', function (event) {
        if (typeof window.ajaxurl !== 'string' || window.ajaxurl === '') {
            return;
        }
        event.preventDefault();
        var form = this;
        if (form.getAttribute('data-busy') === '1') {
            return;
        }
        var button = form.querySelector('#submit');
        var original = button ? button.value : '';
        form.setAttribute('data-busy', '1');
        if (button) {
            button.disabled = true;
            button.value = 'Saving…';
        }
        var body = new FormData(form);
        body.set('action', 'mjb_save_settings');
        fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            form.removeAttribute('data-busy');
            if (button) {
                button.disabled = false;
                if (original) {
                    button.value = original;
                }
            }
            var data = payload && payload.data ? payload.data : {};
            var panel = form.closest('.mjb-tab-panel--settings') || form;
            panel.querySelectorAll(':scope > .notice').forEach(function (notice) {
                notice.remove();
            });
            var notice = document.createElement('div');
            var failed = !payload || !payload.success;
            notice.className = 'notice ' + (failed ? 'notice-error' : 'notice-success');
            notice.setAttribute('role', failed ? 'alert' : 'status');
            var text = document.createElement('p');
            text.textContent = data.message || (failed ? 'Changes could not be saved. Try again.' : 'Settings saved.');
            notice.appendChild(text);
            panel.insertBefore(notice, panel.firstChild);
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        }).catch(function () {
            form.removeAttribute('data-busy');
            if (button) {
                button.disabled = false;
                if (original) {
                    button.value = original;
                }
            }
        });
    });

    window.mjbAdminSettings = {
        init: enhance
    };
})(jQuery);
