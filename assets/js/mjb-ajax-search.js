jQuery(document).ready(function ($) {
    var $jobsBoard = $('#mjb-jobs-board');
    var $jobsList = $('#mjb-jobs-list');
    var $filterForms = $('[data-mjb-job-filter], #mjb-job-filter, .mjb-job-filter');
    var $jobsLive = $('#mjb-jobs-live');
    var jobsRequest = null;

    function slugifyKeyword(keyword) {
        return $.trim(keyword)
            .toLowerCase()
            .replace(/[^a-z0-9\s_-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-');
    }

    function buildPrettyUrl(filters, page) {
        var parts = [];
        var base = (window.mjb_ajax && mjb_ajax.jobs_search_base) ? mjb_ajax.jobs_search_base : '/jobs/';

        if (filters.search_location) {
            parts.push('in', filters.search_location);
        }
        if (filters.search_category) {
            parts.push('category', filters.search_category);
        }
        if (filters.search_type) {
            parts.push('type', filters.search_type);
        }
        if (filters.search_company) {
            parts.push('company', filters.search_company);
        }
        if (filters.search_keywords) {
            parts.push('keyword', slugifyKeyword(filters.search_keywords));
        }
        if (page && parseInt(page, 10) > 1) {
            parts.push('page', String(page));
        }

        return base + (parts.length ? parts.join('/') + '/' : '');
    }

    function getSearchCompany($form) {
        var fromForm = $form.find('[name="search_company"]').val();
        if (fromForm) {
            return fromForm;
        }

        var fromList = $jobsList.data('search-company');
        if (fromList) {
            return String(fromList);
        }

        var match = window.location.pathname.match(/\/company\/([^/]+)/i);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getFilters($form) {
        $form = $form && $form.length ? $form : $filterForms.first();
        return {
            search_keywords: $.trim($form.find('[name="search_keywords"]').val() || ''),
            search_location: $.trim($form.find('[name="search_location"]').val() || ''),
            search_category: $.trim($form.find('[name="search_category"]').val() || ''),
            search_type: $.trim($form.find('[name="search_type"]').val() || ''),
            search_company: getSearchCompany($form)
        };
    }

    function getJobsResults() {
        var $results = $jobsList.find('#mjb-jobs-results');
        return $results.length ? $results : $jobsList;
    }

    function scrollToPageTop() {
        var list = document.getElementById('mjb-jobs-list') || document.getElementById('mjb-jobs-board');
        if (list) {
            var root = document.documentElement;
            var sidebar = document.querySelector('.mjb-sidebar');
            var offset = 80;
            if (sidebar) {
                var st = parseFloat(window.getComputedStyle(sidebar).top);
                if (!isNaN(st) && st >= 0) {
                    offset = st;
                }
            } else {
                var sticky = parseFloat(window.getComputedStyle(root).getPropertyValue('--mjb-content-sticky-top'));
                if (!isNaN(sticky) && sticky > 0) {
                    offset = sticky;
                }
            }
            var top = list.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            return;
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function announceJobs(message) {
        if ($jobsLive.length) {
            $jobsLive.text(message || '');
        }
    }

    function buildJobsSkeletonHtml() {
        var existing = getJobsResults().find('.mjb-job-card').length;
        var perPage = parseInt($jobsList.data('posts-per-page'), 10) || 10;
        var count = existing > 0 ? existing : perPage;
        count = Math.max(1, Math.min(8, count));

        var cardTpl = document.getElementById('mjb-jobs-skeleton-card');
        var pageTpl = document.getElementById('mjb-jobs-skeleton-pagination');
        var list = document.createElement('div');
        list.className = 'mjb-job-list mjb-skeleton-list';
        list.setAttribute('aria-hidden', 'true');

        if (cardTpl && cardTpl.content) {
            for (var i = 0; i < count; i++) {
                list.appendChild(cardTpl.content.cloneNode(true));
            }
        }

        var wrap = document.createElement('div');
        wrap.appendChild(list);
        if (pageTpl && pageTpl.content) {
            wrap.appendChild(pageTpl.content.cloneNode(true));
        }
        return wrap.innerHTML;
    }

    function setLoadingState(isLoading) {
        var $results = getJobsResults();
        if (isLoading) {
            $jobsBoard.addClass('mjb-is-loading');
            $results.addClass('is-swapping');
            $results.html(buildJobsSkeletonHtml());
            $results.removeClass('is-swapping');
            announceJobs((window.mjb_ajax && mjb_ajax.i18n && mjb_ajax.i18n.loading) || 'Loading jobs');
            return;
        }

        $jobsBoard.removeClass('mjb-is-loading');
        announceJobs('');
    }

    function fetchJobs(page, pushHistory, $form) {
        var filters = getFilters($form);
        var data = {
            action: 'mjb_filter_jobs',
            security: mjb_ajax.nonce,
            search_keywords: filters.search_keywords,
            search_location: filters.search_location,
            search_category: filters.search_category,
            search_type: filters.search_type,
            search_company: filters.search_company,
            mjb_page: page || 1,
            posts_per_page: $jobsList.data('posts-per-page') || 10
        };

        if (jobsRequest && jobsRequest.abort) {
            jobsRequest.abort();
        }

        var $results = getJobsResults();
        var previousHtml = $results.html();
        setLoadingState(true);

        jobsRequest = $.ajax({
            url: mjb_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                $results.addClass('is-swapping');
                window.setTimeout(function () {
                    $results.html(response).removeClass('is-swapping');
                    setLoadingState(false);
                    scrollToPageTop();
                }, 80);

                if (pushHistory !== false && window.history && window.history.pushState) {
                    window.history.pushState(null, '', buildPrettyUrl(filters, page || 1));
                }
            },
            error: function (xhr, status) {
                if (status === 'abort') {
                    return;
                }
                $results.html(previousHtml);
                setLoadingState(false);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Autocomplete                                                       */
    /* ------------------------------------------------------------------ */

    var acRequest = null;
    var acTimer = null;
    var i18n = (window.mjb_ajax && mjb_ajax.i18n) ? mjb_ajax.i18n : {};
    /** In-session response cache for city AC (avoids repeat AJAX while typing). */
    var geocityClientCache = Object.create(null);
    var GEOCITY_SS_KEY = 'mjb_geocity_v4';

    function geocityCacheGet(query) {
        var key = String(query || '').toLowerCase();
        if (!key) {
            return null;
        }
        if (geocityClientCache[key]) {
            return geocityClientCache[key];
        }
        try {
            if (window.sessionStorage) {
                var raw = sessionStorage.getItem(GEOCITY_SS_KEY);
                if (raw) {
                    var map = JSON.parse(raw);
                    if (map && map[key] && Array.isArray(map[key])) {
                        geocityClientCache[key] = map[key];
                        return map[key];
                    }
                }
            }
        } catch (e) { /* private mode / quota */ }
        return null;
    }

    function geocityCacheSet(query, items) {
        var key = String(query || '').toLowerCase();
        if (!key || !Array.isArray(items)) {
            return;
        }
        geocityClientCache[key] = items;
        try {
            if (window.sessionStorage) {
                var map = {};
                var raw = sessionStorage.getItem(GEOCITY_SS_KEY);
                if (raw) {
                    try { map = JSON.parse(raw) || {}; } catch (e2) { map = {}; }
                }
                map[key] = items;
                // Cap entries so sessionStorage stays small.
                var keys = Object.keys(map);
                if (keys.length > 80) {
                    keys.slice(0, keys.length - 60).forEach(function (k) { delete map[k]; });
                }
                sessionStorage.setItem(GEOCITY_SS_KEY, JSON.stringify(map));
            }
        } catch (e) { /* ignore */ }
    }

    /**
     * When the user types a longer prefix, reuse a shorter cached starts-with list
     * if it was incomplete enough to filter client-side without a round-trip.
     */
    function geocityFilterFromShorter(query, country) {
        var q = String(query || '').toLowerCase();
        if (q.length < 3) {
            return null;
        }
        for (var len = q.length - 1; len >= 2; len--) {
            var prefix = geocityCacheKey(q.slice(0, len), country);
            var cached = geocityCacheGet(prefix);
            if (!cached) {
                continue;
            }
            // If the shorter query returned a full page, longer queries may need more hits.
            if (cached.length >= 12) {
                continue;
            }
            var filtered = cached.filter(function (item) {
                var label = String((item && (item.label || item.value)) || '').toLowerCase();
                return label.indexOf(q) === 0 || label.indexOf(q) !== -1;
            });
            return filtered;
        }
        return null;
    }

    function closeAllAcMenus(except) {
        $('.mjb-ac').each(function () {
            if (except && this === except) {
                return;
            }
            var $root = $(this);
            var $menu = $root.find('[data-mjb-ac-menu]');
            var $input = $root.find('.mjb-ac__input');
            $menu.prop('hidden', true).empty();
            $input.attr('aria-expanded', 'false');
            $root.removeClass('is-open');
        });
    }

    function renderAcSkeleton($root) {
        var $menu = $root.find('[data-mjb-ac-menu]');
        var $input = $root.find('.mjb-ac__input');
        $menu.empty();
        for (var i = 0; i < 4; i++) {
            $menu.append(
                $('<div class="mjb-ac__item mjb-ac__item--skeleton" role="presentation"/>')
                    .append($('<span class="mjb-skeleton mjb-skeleton--excerpt"/>'))
            );
        }
        $menu.prop('hidden', false);
        $input.attr('aria-expanded', 'true');
        $root.addClass('is-open');
    }

    function renderAcItems($root, items) {
        var $menu = $root.find('[data-mjb-ac-menu]');
        var $input = $root.find('.mjb-ac__input');
        $menu.empty();

        if (!items || !items.length) {
            $menu.append(
                $('<div class="mjb-ac__empty" role="presentation"/>').text(i18n.noResults || 'No matches')
            );
            $menu.prop('hidden', false);
            $input.attr('aria-expanded', 'true');
            $root.addClass('is-open');
            return;
        }

        items.forEach(function (item, index) {
            var $btn = $('<button type="button" class="mjb-ac__item" role="option"/>')
                .attr('data-value', item.value || '')
                .attr('data-label', item.label || item.value || '')
                .attr('id', ($input.attr('aria-controls') || 'mjb-ac') + '-opt-' + index)
                .text(item.label || item.value || '');
            $menu.append($btn);
        });

        $menu.prop('hidden', false);
        $input.attr('aria-expanded', 'true');
        $root.addClass('is-open');
    }

    function resolvePreferCountry($root) {
        var fromData = String($root.attr('data-prefer-country') || $root.data('prefer-country') || '').toUpperCase();
        if (/^[A-Z]{2}$/.test(fromData)) {
            return fromData;
        }
        // Follow the phone-field country on the same form when present (user may have changed dial code).
        var $form = $root.closest('form');
        if ($form.length) {
            var iso = '';
            var $phoneIso = $form.find('[data-mjb-phone-iso], [name="mjb_phone_country"]');
            if ($phoneIso.length) {
                iso = String($phoneIso.first().val() || '').toUpperCase();
            }
            var phoneRoot = $form.find('.mjb-phone-field, [data-mjb-phone]')[0];
            if (phoneRoot) {
                if (phoneRoot._mjbPhoneCountry && phoneRoot._mjbPhoneCountry.iso) {
                    iso = String(phoneRoot._mjbPhoneCountry.iso).toUpperCase();
                }
            }
            if (/^[A-Z]{2}$/.test(iso)) {
                return iso;
            }
        }
        // Same auto-detect as phone dial (locale + timezone) — any country, not hard-coded.
        if (typeof window.mjbDetectCountryIso === 'function') {
            var detected = String(window.mjbDetectCountryIso() || '').toUpperCase();
            if (/^[A-Z]{2}$/.test(detected)) {
                return detected;
            }
        }
        return '';
    }

    function geocityCacheKey(query, country) {
        return String(query || '').toLowerCase() + '|' + String(country || '').toUpperCase();
    }

    function fetchSuggestions($root, query) {
        var type = $root.data('mjb-ac');
        if (!type || !window.mjb_ajax) {
            return;
        }

        var q = query || '';
        var preferCountry = type === 'geocity' ? resolvePreferCountry($root) : '';
        var cacheKey = type === 'geocity' ? geocityCacheKey(q, preferCountry) : q;

        // City AC: serve from client cache instantly (no AJAX delay while typing).
        if (type === 'geocity' && q.length >= 3) {
            var cached = geocityCacheGet(cacheKey);
            if (cached) {
                renderAcItems($root, cached);
                return;
            }
            var derived = geocityFilterFromShorter(q, preferCountry);
            if (derived) {
                geocityCacheSet(cacheKey, derived);
                renderAcItems($root, derived);
                return;
            }
        }

        if (acRequest && acRequest.abort) {
            acRequest.abort();
        }

        $root.addClass('is-loading');
        renderAcSkeleton($root);

        var ajaxData = {
            action: 'mjb_filter_suggest',
            security: mjb_ajax.nonce,
            type: type,
            q: q,
            limit: 12
        };
        if (preferCountry) {
            ajaxData.country = preferCountry;
        }

        acRequest = $.ajax({
            url: mjb_ajax.ajax_url,
            type: 'GET',
            dataType: 'json',
            data: ajaxData
        }).done(function (res) {
            if (!res || !res.success || !res.data) {
                renderAcItems($root, []);
                return;
            }
            var items = res.data.items || [];
            if (type === 'geocity' && q.length >= 3) {
                geocityCacheSet(cacheKey, items);
            }
            renderAcItems($root, items);
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                renderAcItems($root, []);
            }
        }).always(function () {
            $root.removeClass('is-loading');
        });
    }

    function selectAcItem($root, value, label) {
        var freeText = String($root.data('free-text')) === '1';
        var $input = $root.find('.mjb-ac__input');
        var $hidden = $root.find('.mjb-ac__value');

        if (freeText) {
            $input.val(label || value || '');
        } else {
            $hidden.val(value || '');
            $input.val(label || value || '');
        }

        closeAllAcMenus();
        $input.trigger('change');
        var node = $input.get(0);
        if (node && typeof node.dispatchEvent === 'function') {
            node.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function clearAcIfEmpty($root) {
        var freeText = String($root.data('free-text')) === '1';
        var $input = $root.find('.mjb-ac__input');
        var $hidden = $root.find('.mjb-ac__value');
        if (freeText) {
            return;
        }
        if ($.trim($input.val()) === '') {
            $hidden.val('');
        }
    }

    $(document).on('focus', '.mjb-ac__input', function () {
        var $root = $(this).closest('.mjb-ac');
        closeAllAcMenus($root[0]);
        fetchSuggestions($root, $.trim($(this).val()));
    });

    $(document).on('input', '.mjb-ac__input', function () {
        var $input = $(this);
        var $root = $input.closest('.mjb-ac');
        var freeText = String($root.data('free-text')) === '1';
        var $hidden = $root.find('.mjb-ac__value');

        if (!freeText) {
            // Typing invalidates prior selection until a suggestion is chosen.
            $hidden.val('');
        }

        if (acTimer) {
            clearTimeout(acTimer);
        }
        // City field: shorter debounce — local index is fast; registration should feel instant.
        var type = $root.data('mjb-ac');
        var delay = type === 'geocity' ? 80 : 180;
        acTimer = setTimeout(function () {
            fetchSuggestions($root, $.trim($input.val()));
        }, delay);
    });

    $(document).on('keydown', '.mjb-ac__input', function (e) {
        var $root = $(this).closest('.mjb-ac');
        var $menu = $root.find('[data-mjb-ac-menu]');
        var $items = $menu.find('.mjb-ac__item');
        if (!$items.length || $menu.prop('hidden')) {
            if (e.key === 'Escape') {
                closeAllAcMenus();
            }
            return;
        }

        var $active = $items.filter('.is-active');
        var index = $items.index($active);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = index < $items.length - 1 ? index + 1 : 0;
            $items.removeClass('is-active');
            $items.eq(index).addClass('is-active');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = index > 0 ? index - 1 : $items.length - 1;
            $items.removeClass('is-active');
            $items.eq(index).addClass('is-active');
        } else if (e.key === 'Enter') {
            if ($active.length) {
                e.preventDefault();
                selectAcItem($root, $active.data('value'), $active.data('label'));
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeAllAcMenus();
        }
    });

    $(document).on('mousedown', '.mjb-ac__item', function (e) {
        // mousedown so selection happens before blur closes the menu.
        e.preventDefault();
        var $item = $(this);
        var $root = $item.closest('.mjb-ac');
        selectAcItem($root, $item.data('value'), $item.data('label'));
    });

    $(document).on('blur', '.mjb-ac__input', function () {
        var $root = $(this).closest('.mjb-ac');
        setTimeout(function () {
            clearAcIfEmpty($root);
            if (!$root.find('.mjb-ac__item:hover').length) {
                closeAllAcMenus();
            }
        }, 120);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.mjb-ac').length) {
            closeAllAcMenus();
        }
    });

    /* ------------------------------------------------------------------ */
    /* Filter submit + pagination                                         */
    /* ------------------------------------------------------------------ */

    function resetFilterForm($form) {
        $form.find('.mjb-ac').each(function () {
            var $root = $(this);
            var freeText = String($root.data('free-text')) === '1';
            $root.find('.mjb-ac__input').val('');
            if (!freeText) {
                $root.find('.mjb-ac__value').val('');
            }
        });

        // Keep company lock when browsing a company jobs page.
        var lockedCompany = $form.find('input[type="hidden"][name="search_company"]').val() || '';
        var filters = {
            search_keywords: '',
            search_location: '',
            search_category: '',
            search_type: '',
            search_company: lockedCompany
        };

        window.location.href = buildPrettyUrl(filters, 1);
    }

    if ($filterForms.length) {
        $filterForms.on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            $form.find('.mjb-ac').each(function () {
                clearAcIfEmpty($(this));
            });
            var filters = getFilters($form);
            window.location.href = buildPrettyUrl(filters, 1);
        });

        $filterForms.on('click', '[data-mjb-filter-reset]', function (e) {
            e.preventDefault();
            resetFilterForm($(this).closest('form'));
        });
    }

    if ($jobsList.length) {
        $jobsList.on('click', '.mjb-page-link', function (e) {
            e.preventDefault();

            if ($(this).hasClass('is-disabled') || $(this).hasClass('is-active')) {
                return;
            }

            var page = parseInt($(this).data('page'), 10);
            var targetUrl = $(this).data('url');
            var $form = $filterForms.first();

            if (!page) {
                return;
            }

            if (targetUrl && window.history && window.history.pushState) {
                fetchJobs(page, false, $form);
                window.history.pushState(null, '', targetUrl);
            } else {
                fetchJobs(page, true, $form);
            }
        });
    }
});
