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
    var currentSettingsTab = initialParams.get('settings_tab') || '';
    var request = null;

    if (!$shell.length || !$panel.length) {
        return;
    }

    function buildAdminUrl(tab, page, toolsTab, settingsTab) {
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

        if (tab === 'settings' && settingsTab) {
            url.searchParams.set('settings_tab', settingsTab);
        } else {
            url.searchParams.delete('settings_tab');
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

    function activateSettingsSubtab(settingsTab, pushHistory) {
        if (!settingsTab) {
            return;
        }

        currentSettingsTab = settingsTab;

        $panel.find('.mjb-settings-subtab').each(function () {
            var $btn = $(this);
            var isActive = $btn.data('settings-tab') === settingsTab;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-selected', isActive ? 'true' : 'false');
        });

        $panel.find('.mjb-settings-section').each(function () {
            var $section = $(this);
            var isActive = $section.data('settings-tab') === settingsTab;
            $section.toggleClass('is-active', isActive);
            if (isActive) {
                $section.removeAttr('hidden');
            } else {
                $section.attr('hidden', 'hidden');
            }
        });

        $panel.find('.mjb-tab-panel--settings').attr('data-active-settings-tab', settingsTab);

        var returnUrl = buildAdminUrl('settings', 1, currentToolsTab, settingsTab);
        $panel.find('input[name="_wp_http_referer"]').val(returnUrl);

        if (pushHistory !== false && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', returnUrl);
        }
    }

    function loadTab(tab, page, pushHistory, toolsTab, settingsTab) {
        page = page || 1;
        toolsTab = toolsTab || currentToolsTab;
        settingsTab = typeof settingsTab !== 'undefined' ? settingsTab : currentSettingsTab;

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
                tools_tab: tab === 'tools' ? toolsTab : '',
                settings_tab: tab === 'settings' ? settingsTab : ''
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
            if (tab === 'settings') {
                var $html = $('<div>').html(response.data.html);
                var fromPanel = $html.find('.mjb-tab-panel--settings').attr('data-active-settings-tab');
                if (fromPanel) {
                    currentSettingsTab = fromPanel;
                } else if (settingsTab) {
                    currentSettingsTab = settingsTab;
                }
            }

            $panel
                .html(response.data.html)
                .attr('data-active-tab', currentTab)
                .attr('data-active-page', currentPage)
                .attr('aria-labelledby', 'mjb-tab-' + currentTab);

            setActiveTabButton(currentTab);

            if (pushHistory !== false && window.history && window.history.pushState) {
                window.history.pushState(
                    null,
                    '',
                    buildAdminUrl(currentTab, currentPage, currentToolsTab, currentSettingsTab)
                );
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
        loadTab(tab, 1, true, tab === 'tools' ? currentToolsTab : 'export', tab === 'settings' ? currentSettingsTab : '');
    });

    $panel.on('click', '.mjb-admin-pagination__btn:not([disabled])', function (event) {
        event.preventDefault();
        var page = parseInt($(this).data('page'), 10);
        if (!page || page === currentPage) {
            return;
        }
        loadTab(currentTab, page, true, currentToolsTab, currentSettingsTab);
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

    // Client-side settings subtabs: keep the full form in the DOM so Save works for all fields.
    $panel.on('click', '.mjb-settings-subtab', function (event) {
        event.preventDefault();
        var settingsTab = $(this).data('settings-tab');
        if (!settingsTab || settingsTab === currentSettingsTab) {
            return;
        }
        activateSettingsSubtab(settingsTab, true);
    });

    window.addEventListener('popstate', function () {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab') || config.default_tab || 'dashboard';
        var page = parseInt(params.get('mjb_page'), 10) || 1;
        var toolsTab = params.get('tools_tab') || 'export';
        var settingsTab = params.get('settings_tab') || '';
        currentToolsTab = toolsTab;
        currentSettingsTab = settingsTab;

        if (tab === 'settings' && currentTab === 'settings' && settingsTab) {
            activateSettingsSubtab(settingsTab, false);
            return;
        }

        loadTab(tab, page, false, toolsTab, settingsTab);
    });

    // Sync JS state if the server already rendered a settings subtab.
    var initialSettings = $panel.find('.mjb-tab-panel--settings').attr('data-active-settings-tab');
    if (initialSettings) {
        currentSettingsTab = initialSettings;
    }

    // --- Delete job modal (Jobs list) ---
    var pendingDeleteJobId = null;
    var $lastDeleteTrigger = null;

    function getDeleteModal() {
        return $panel.find('#mjb-delete-job-modal').first();
    }

    function openDeleteModal($trigger) {
        var $modal = getDeleteModal();
        if (!$modal.length) {
            return;
        }

        pendingDeleteJobId = parseInt($trigger.data('job-id'), 10) || 0;
        $lastDeleteTrigger = $trigger;
        var title = $trigger.data('job-title') || '';
        $modal.find('.mjb-modal__job-title').text(title);
        $modal.prop('hidden', false).attr('aria-hidden', 'false');
        $shell.addClass('mjb-modal-open');
        window.setTimeout(function () {
            $modal.find('#mjb-delete-job-confirm').trigger('focus');
        }, 10);
    }

    function closeDeleteModal() {
        var $modal = getDeleteModal();
        if ($modal.length) {
            $modal.find('#mjb-delete-job-confirm').prop('disabled', false).removeClass('is-busy');
            $modal.prop('hidden', true).attr('aria-hidden', 'true');
        }
        $shell.removeClass('mjb-modal-open');
        pendingDeleteJobId = null;
        if ($lastDeleteTrigger && $lastDeleteTrigger.length) {
            $lastDeleteTrigger.trigger('focus');
        }
        $lastDeleteTrigger = null;
    }

    $panel.on('click', '.mjb-job-delete', function (event) {
        event.preventDefault();
        openDeleteModal($(this));
    });

    $panel.on('click', '#mjb-delete-job-modal [data-mjb-modal-dismiss]', function (event) {
        event.preventDefault();
        closeDeleteModal();
    });

    $panel.on('keydown', function (event) {
        if (event.key === 'Escape' && getDeleteModal().length && !getDeleteModal().prop('hidden')) {
            closeDeleteModal();
        }
    });

    $panel.on('click', '#mjb-delete-job-confirm', function (event) {
        event.preventDefault();
        if (!pendingDeleteJobId || !config.ajax_url || !config.nonce) {
            return;
        }

        var $btn = $(this);
        if ($btn.prop('disabled')) {
            return;
        }
        $btn.prop('disabled', true).addClass('is-busy');

        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mjb_admin_delete_job',
                security: config.nonce,
                job_id: pendingDeleteJobId
            }
        }).done(function (response) {
            if (!response || !response.success) {
                var msg = (response && response.data && response.data.message)
                    ? response.data.message
                    : 'Could not delete the job.';
                window.alert(msg);
                $btn.prop('disabled', false).removeClass('is-busy');
                return;
            }
            closeDeleteModal();
            // Reload jobs list (stay on current page; empty page will show empty state).
            loadTab('jobs', currentPage, true);
        }).fail(function () {
            window.alert('Could not delete the job.');
            $btn.prop('disabled', false).removeClass('is-busy');
        });
    });
});
