jQuery(function ($) {
    var $shell = $('.mjb-admin-shell');
    var $panel = $('#mjb-admin-panel');
    var $loader = $('#mjb-admin-loader');
    var $tabButtons = $('.mjb-admin-tabs__btn');
    var config = window.mjb_admin_tabs || {};
    var initialParams = new URLSearchParams(window.location.search);
    var currentTab = $panel.data('active-tab') || config.default_tab || 'dashboard';
    var currentPage = parseInt($panel.data('active-page'), 10) || 1;
    var currentToolsTab = initialParams.get('tools_tab') || 'export';
    var request = null;

    if (!$shell.length || !$panel.length) {
        return;
    }

    function buildAdminUrl(tab, page, toolsTab) {
        var url = new URL(window.location.href);
        url.searchParams.set('page', 'modern-job-board');
        url.searchParams.set('tab', tab);

        if (page && page > 1) {
            url.searchParams.set('mjb_page', String(page));
        } else {
            url.searchParams.delete('mjb_page');
        }

        if (tab === 'tools' && toolsTab) {
            url.searchParams.set('tools_tab', toolsTab);
        } else {
            url.searchParams.delete('tools_tab');
        }

        return url.pathname + url.search;
    }

    function setLoadingState(isLoading) {
        if (!$loader.length) {
            return;
        }

        if (isLoading) {
            $shell.addClass('mjb-admin-is-loading');
            $loader.addClass('is-active').attr('aria-hidden', 'false');
            return;
        }

        $loader.removeClass('is-active').attr('aria-hidden', 'true');
        $shell.removeClass('mjb-admin-is-loading');
    }

    function setActiveTabButton(tab) {
        $tabButtons.each(function () {
            var $btn = $(this);
            var isActive = $btn.data('tab') === tab;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function loadTab(tab, page, pushHistory, toolsTab) {
        page = page || 1;
        toolsTab = toolsTab || currentToolsTab;

        if (request) {
            request.abort();
        }

        setLoadingState(true);

        request = $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_admin_load_tab',
                security: config.nonce,
                tab: tab,
                page: page,
                tools_tab: tab === 'tools' ? toolsTab : ''
            }
        }).done(function (response) {
            if (!response || !response.success || !response.data || !response.data.html) {
                return;
            }

            currentTab = response.data.tab || tab;
            currentPage = response.data.page || page;
            if (tab === 'tools') {
                currentToolsTab = toolsTab;
            }

            $panel
                .html(response.data.html)
                .attr('data-active-tab', currentTab)
                .attr('data-active-page', currentPage)
                .attr('aria-labelledby', 'mjb-tab-' + currentTab);

            setActiveTabButton(currentTab);

            if (pushHistory !== false && window.history && window.history.pushState) {
                window.history.pushState(null, '', buildAdminUrl(currentTab, currentPage, currentToolsTab));
            }
        }).fail(function () {
            // Keep current content on failure.
        }).always(function () {
            setLoadingState(false);
            request = null;
        });
    }

    $tabButtons.on('click', function (event) {
        event.preventDefault();
        var tab = $(this).data('tab');
        if (!tab || tab === currentTab) {
            return;
        }
        currentPage = 1;
        loadTab(tab, 1, true, tab === 'tools' ? currentToolsTab : 'export');
    });

    $panel.on('click', '.mjb-admin-pagination__btn:not([disabled])', function (event) {
        event.preventDefault();
        var page = parseInt($(this).data('page'), 10);
        if (!page || page === currentPage) {
            return;
        }
        loadTab(currentTab, page, true, currentToolsTab);
    });

    $panel.on('click', '.mjb-feature-card--action', function (event) {
        event.preventDefault();
        var tab = $(this).data('tab');
        if (!tab) {
            return;
        }
        currentPage = 1;
        loadTab(tab, 1, true);
    });

    $panel.on('click', '.mjb-tools-subtab', function (event) {
        event.preventDefault();
        var toolsTab = $(this).data('tools-tab');
        if (!toolsTab || toolsTab === currentToolsTab) {
            return;
        }
        currentToolsTab = toolsTab;
        loadTab('tools', 1, true, toolsTab);
    });

    window.addEventListener('popstate', function () {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || config.default_tab || 'dashboard';
        var page = parseInt(params.get('mjb_page'), 10) || 1;
        var toolsTab = params.get('tools_tab') || 'export';
        currentToolsTab = toolsTab;
        loadTab(tab, page, false, toolsTab);
    });
});