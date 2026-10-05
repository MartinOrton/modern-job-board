/**
 * Recruiter dashboard tab AJAX (mirrors admin mjb-admin-tabs.js).
 */
jQuery(function ($) {
    var $shell = $('.mjb-portal-dashboard.mjb-employer-dashboard');
    var $panel = $('#mjb-recruiter-panel');
    var $live = $('#mjb-recruiter-live');
    var $tabButtons = $shell.find('.mjb-portal-tabs__btn');
    var config = window.mjb_recruiter_dashboard || {};
    var currentTab = $panel.data('active-tab') || config.default_tab || 'overview';
    var currentPage = 1;
    var request = null;

    if (!$shell.length || !$panel.length) {
        return;
    }

    function tabUrl(tab, page) {
        page = page || 1;
        var base = (config.tabs && config.tabs[tab]) ? config.tabs[tab] : '';
        if (!base) {
            return window.location.pathname;
        }
        try {
            var url = new URL(base, window.location.origin);
            if (tab === 'jobs' && page > 1) {
                url.searchParams.set('jobs_page', String(page));
            } else {
                url.searchParams.delete('jobs_page');
            }
            return url.pathname + url.search + url.hash;
        } catch (e) {
            return base;
        }
    }

    function setLoadingState(isLoading, tab) {
        if (isLoading) {
            $shell.addClass('mjb-portal-is-loading');
            var html = (config.skeletons && tab && config.skeletons[tab]) ? config.skeletons[tab] : '';
            if (!html && config.skeletons && config.skeletons.jobs) {
                html = config.skeletons.jobs;
            }
            if (html) {
                $panel.addClass('is-swapping');
                $panel.html(html).removeClass('is-swapping');
            }
            if ($live.length) {
                $live.text((config.i18n && config.i18n.loading) || 'Loading dashboard');
            }
            return;
        }
        if ($live.length) {
            $live.text('');
        }
        $shell.removeClass('mjb-portal-is-loading');
    }

    function setActiveTabButton(tab) {
        $tabButtons.each(function () {
            var $btn = $(this);
            var isActive = $btn.data('tab') === tab;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-selected', isActive ? 'true' : 'false');
        });
        $shell.attr('data-active-tab', tab);
        $panel.attr('data-active-tab', tab).attr('aria-labelledby', 'mjb-rtab-' + tab);
    }

    function loadTab(tab, page, pushHistory) {
        page = page || 1;

        if (request) {
            request.abort();
        }

        var previousHtml = $panel.html();
        setLoadingState(true, tab);

        request = $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_recruiter_load_tab',
                security: config.nonce,
                tab: tab,
                page: page
            }
        }).done(function (response) {
            if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                $panel.html(previousHtml);
                return;
            }

            currentTab = response.data.tab || tab;
            currentPage = response.data.page || page;
            $panel.addClass('is-swapping');
            $panel.html(response.data.html).removeClass('is-swapping');
            setActiveTabButton(currentTab);

            if (pushHistory !== false && window.history && window.history.pushState) {
                var nextUrl = response.data.url || tabUrl(currentTab, currentPage);
                window.history.pushState({ mjbRecruiterTab: currentTab, page: currentPage }, '', nextUrl);
            }
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                $panel.html(previousHtml);
            }
        }).always(function () {
            setLoadingState(false);
            request = null;
        });
    }

    $tabButtons.on('click', function (event) {
        var tab = $(this).data('tab');
        if (!tab) {
            return;
        }
        // Progressive enhancement: without config, allow full navigation.
        if (!config.ajax_url || !config.nonce) {
            return;
        }
        event.preventDefault();
        if (tab === currentTab && currentTab !== 'jobs') {
            return;
        }
        currentPage = 1;
        loadTab(tab, 1, true);
    });

    $panel.on('click', '.mjb-feature-card--ajax', function (event) {
        var tab = $(this).data('tab');
        if (!tab || !config.ajax_url) {
            return;
        }
        event.preventDefault();
        if (tab === currentTab) {
            return;
        }
        currentPage = 1;
        loadTab(tab, 1, true);
    });

    $panel.on('click', '.mjb-pagination--dashboard a', function (event) {
        if (!config.ajax_url || currentTab !== 'jobs') {
            return;
        }
        event.preventDefault();
        var page = 1;
        try {
            var href = this.href;
            var url = new URL(href, window.location.origin);
            page = parseInt(url.searchParams.get('jobs_page'), 10) || 1;
        } catch (e) {
            page = 1;
        }
        if (page === currentPage) {
            return;
        }
        loadTab('jobs', page, true);
    });

    function showRecruiterNotice(message, isError) {
        $shell.children('.mjb-message').remove();
        if (!message) {
            return;
        }
        var notice = $('<div>', {
            'class': 'mjb-message ' + (isError ? 'error' : 'success'),
            role: isError ? 'alert' : 'status',
            text: message
        });
        $shell.prepend(notice);
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }

    $shell.on('submit', 'form', function (event) {
        if (event.isDefaultPrevented()) {
            return;
        }
        var form = this;
        if (!config.ajax_url || !form.querySelector('[name="mjb_dashboard_action"]')) {
            return;
        }
        event.preventDefault();
        if (form.getAttribute('data-busy') === '1') {
            return;
        }
        form.setAttribute('data-busy', '1');
        var body = new FormData(form);
        body.set('action', 'mjb_recruiter_mutate');
        body.set('mjb_panel_tab', currentTab);
        body.set('mjb_panel_page', String(currentPage || 1));
        fetch(config.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            form.removeAttribute('data-busy');
            var data = payload && payload.data ? payload.data : {};
            if (!payload || !payload.success) {
                showRecruiterNotice(data.message || 'Changes could not be saved. Try again.', true);
                return;
            }
            if (typeof data.html === 'string' && data.html !== '') {
                $panel.html(data.html);
            }
            showRecruiterNotice(data.message, false);
        }).catch(function () {
            form.removeAttribute('data-busy');
            showRecruiterNotice('Changes could not be saved. Try again.', true);
        });
    });

    window.addEventListener('popstate', function (event) {
        if (!config.ajax_url) {
            return;
        }
        var tab = currentTab;
        var page = 1;
        if (event.state && event.state.mjbRecruiterTab) {
            tab = event.state.mjbRecruiterTab;
            page = event.state.page || 1;
        } else if (config.tabs) {
            var path = window.location.pathname.replace(/\/+$/, '') + '/';
            Object.keys(config.tabs).forEach(function (key) {
                try {
                    var tpath = new URL(config.tabs[key], window.location.origin).pathname.replace(/\/+$/, '') + '/';
                    if (path === tpath || path.indexOf(tpath) === 0) {
                        // Prefer longer matching paths (jobs/ over overview).
                        if (!tab || tpath.length >= (config.tabs[tab] ? new URL(config.tabs[tab], window.location.origin).pathname.length : 0)) {
                            tab = key;
                        }
                    }
                } catch (e) {
                    // ignore
                }
            });
            try {
                page = parseInt(new URLSearchParams(window.location.search).get('jobs_page'), 10) || 1;
            } catch (e2) {
                page = 1;
            }
        }
        loadTab(tab || config.default_tab || 'overview', page, false);
    });
});
