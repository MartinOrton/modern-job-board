/**
 * Recruiter dashboard tab AJAX (mirrors admin mjb-admin-tabs.js).
 */
jQuery(function ($) {
    var $shell = $('.mjb-portal-dashboard.mjb-employer-dashboard');
    var $panel = $('#mjb-recruiter-panel');
    var $loader = $('#mjb-recruiter-loader');
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

    function setLoadingState(isLoading) {
        if (!$loader.length) {
            return;
        }
        if (isLoading) {
            $shell.addClass('mjb-portal-is-loading');
            $loader.addClass('is-active').attr('aria-hidden', 'false');
            return;
        }
        $loader.removeClass('is-active').attr('aria-hidden', 'true');
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

        setLoadingState(true);

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
                return;
            }

            currentTab = response.data.tab || tab;
            currentPage = response.data.page || page;
            $panel.html(response.data.html);
            setActiveTabButton(currentTab);

            if (pushHistory !== false && window.history && window.history.pushState) {
                var nextUrl = response.data.url || tabUrl(currentTab, currentPage);
                window.history.pushState({ mjbRecruiterTab: currentTab, page: currentPage }, '', nextUrl);
            }
        }).fail(function () {
            // Keep current panel content on failure.
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
