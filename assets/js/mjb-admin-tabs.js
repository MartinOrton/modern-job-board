jQuery(function ($) {
    var $shell = $('.mjb-admin-shell');
    var $panel = $('#mjb-admin-panel');
    var $live = $('#mjb-admin-live');
    var $tabButtons = $('.mjb-admin-tabs__btn');
    var config = window.mjb_admin_tabs || {};
    var initialParams = new URLSearchParams(window.location.search);
    var currentTab = $panel.data('active-tab') || config.default_tab || 'dashboard';
    var currentPage = parseInt($panel.data('active-page'), 10) || 1;
    var currentToolsTab = initialParams.get('tools_tab') || 'export';
    var currentSettingsTab = initialParams.get('settings_tab') || '';
    var currentRange = parseRange(initialParams.get('range'));
    var currentListFilter = initialParams.get('mjb_list') || '';
    var currentJobsQ = initialParams.get('mjb_q') || '';
    var currentJobsCompany = initialParams.get('mjb_company') || '';
    var currentJobsCat = initialParams.get('mjb_cat') || '';
    var currentJobsType = initialParams.get('mjb_type') || '';
    var currentJobsOrderby = initialParams.get('mjb_orderby') || 'posted';
    var currentJobsOrder = initialParams.get('mjb_order') || 'desc';
    var currentJobsPer = initialParams.get('mjb_per') || '';
    var currentJobsJob = initialParams.get('mjb_job') || '';
    var currentCompaniesLoc = initialParams.get('mjb_loc') || '';
    var currentResumesExt = initialParams.get('mjb_ext') || '';
    var request = null;
    var pendingNotice = null;

    function prependPanelNotice(message, isError) {
        if (!message) {
            return;
        }
        $panel.find('.mjb-ajax-notice').remove();
        var note = document.createElement('div');
        var text = document.createElement('p');
        note.className = 'notice mjb-ajax-notice ' + (isError ? 'notice-error' : 'notice-success');
        note.setAttribute('role', isError ? 'alert' : 'status');
        text.textContent = message;
        note.appendChild(text);
        if ($panel.get(0)) {
            $panel.get(0).insertBefore(note, $panel.get(0).firstChild);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function reloadAfterMutation(data, isError) {
        var payload = data || {};
        pendingNotice = {
            message: payload.message || '',
            isError: !!isError
        };
        if (payload.tools_tab) {
            currentToolsTab = payload.tools_tab;
        }
        loadTab(payload.tab || currentTab, currentPage || 1, false, currentToolsTab, currentSettingsTab);
    }

    function parseRange(value) {
        var days = parseInt(value, 10);
        if (days === 0 || days === 7 || days === 30 || days === 90) {
            return days;
        }
        return 30;
    }

    function resetJobsQuery() {
        currentJobsQ = '';
        currentJobsCompany = '';
        currentJobsCat = '';
        currentJobsType = '';
        currentJobsOrderby = 'posted';
        currentJobsOrder = 'desc';
        currentJobsPer = '';
        currentJobsJob = '';
        currentCompaniesLoc = '';
        currentResumesExt = '';
    }

    function syncJobsStateFromPanel() {
        var jobs = $panel.find('.mjb-tab-panel--jobs').get(0);
        var companies = $panel.find('.mjb-tab-panel--companies').get(0);
        var applications = $panel.find('.mjb-tab-panel--applications').get(0);
        var resumes = $panel.find('.mjb-tab-panel--resumes').get(0);
        var root = jobs || companies || applications || resumes;
        if (!root) {
            return;
        }
        currentListFilter = root.getAttribute('data-view') || currentListFilter;
        currentJobsQ = root.getAttribute('data-q') || '';
        currentJobsOrderby = root.getAttribute('data-orderby') || 'posted';
        currentJobsOrder = root.getAttribute('data-order') || 'desc';
        currentJobsPer = root.getAttribute('data-per') || '';
        currentPage = parseInt(root.getAttribute('data-page'), 10) || currentPage;
        if (jobs) {
            currentJobsCompany = jobs.getAttribute('data-company') || '';
            if (currentJobsCompany === '0') {
                currentJobsCompany = '';
            }
            currentJobsCat = jobs.getAttribute('data-cat') || '';
            currentJobsType = jobs.getAttribute('data-type') || '';
        }
        if (companies) {
            currentCompaniesLoc = companies.getAttribute('data-loc') || '';
        }
        if (applications) {
            currentJobsJob = applications.getAttribute('data-job') || '';
            if (currentJobsJob === '0') {
                currentJobsJob = '';
            }
        }
        if (resumes) {
            currentResumesExt = resumes.getAttribute('data-ext') || '';
        }
    }

    if (!$shell.length || !$panel.length) {
        return;
    }

    function buildAdminUrl(tab, page, toolsTab, settingsTab, range, listFilter) {
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

        range = typeof range === 'number' ? range : currentRange;
        if (tab === 'dashboard' && range !== 30) {
            url.searchParams.set('range', String(range));
        } else {
            url.searchParams.delete('range');
        }

        listFilter = typeof listFilter === 'string' ? listFilter : currentListFilter;
        if ((tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') && listFilter && listFilter !== 'all') {
            url.searchParams.set('mjb_list', listFilter);
        } else {
            url.searchParams.delete('mjb_list');
        }

        if (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') {
            if (currentJobsQ) {
                url.searchParams.set('mjb_q', currentJobsQ);
            } else {
                url.searchParams.delete('mjb_q');
            }
            if (currentJobsOrderby && currentJobsOrderby !== 'posted') {
                url.searchParams.set('mjb_orderby', currentJobsOrderby);
            } else {
                url.searchParams.delete('mjb_orderby');
            }
            if (currentJobsOrder && currentJobsOrder !== 'desc') {
                url.searchParams.set('mjb_order', currentJobsOrder);
            } else {
                url.searchParams.delete('mjb_order');
            }
            if (currentJobsPer && String(currentJobsPer) !== '20') {
                url.searchParams.set('mjb_per', String(currentJobsPer));
            } else {
                url.searchParams.delete('mjb_per');
            }
        } else {
            url.searchParams.delete('mjb_q');
            url.searchParams.delete('mjb_orderby');
            url.searchParams.delete('mjb_order');
            url.searchParams.delete('mjb_per');
        }

        if (tab === 'jobs' && currentJobsCompany && currentJobsCompany !== '0') {
            url.searchParams.set('mjb_company', currentJobsCompany);
        } else {
            url.searchParams.delete('mjb_company');
        }
        if (tab === 'jobs' && currentJobsCat) {
            url.searchParams.set('mjb_cat', currentJobsCat);
        } else {
            url.searchParams.delete('mjb_cat');
        }
        if (tab === 'jobs' && currentJobsType) {
            url.searchParams.set('mjb_type', currentJobsType);
        } else {
            url.searchParams.delete('mjb_type');
        }
        if (tab === 'companies' && currentCompaniesLoc) {
            url.searchParams.set('mjb_loc', currentCompaniesLoc);
        } else {
            url.searchParams.delete('mjb_loc');
        }

        if (tab === 'applications' && currentJobsJob) {
            url.searchParams.set('mjb_job', currentJobsJob);
        } else {
            url.searchParams.delete('mjb_job');
        }

        if (tab === 'resumes' && currentResumesExt) {
            url.searchParams.set('mjb_ext', currentResumesExt);
        } else {
            url.searchParams.delete('mjb_ext');
        }

        return url.pathname + url.search;
    }

    function setLoadingState(isLoading, tab) {
        if (isLoading) {
            $shell.addClass('mjb-admin-is-loading');
            var html = (config.skeletons && tab && config.skeletons[tab]) ? config.skeletons[tab] : '';
            if (!html && config.skeletons && config.skeletons.jobs) {
                html = config.skeletons.jobs;
            }
            if (html) {
                $panel.addClass('is-swapping');
                $panel.html(html).removeClass('is-swapping');
            }
            if ($live.length) {
                $live.text((config.i18n && config.i18n.loading) || 'Loading section…');
            }
            return;
        }

        if ($live.length) {
            $live.text('');
        }
        $shell.removeClass('mjb-admin-is-loading');
    }

    function setActiveTabButton(tab) {
        $tabButtons.each(function () {
            var $btn = $(this);
            var isActive = $btn.data('tab') === tab;
            $btn.toggleClass('is-active', isActive);
            $btn.attr('aria-selected', isActive ? 'true' : 'false');
            if (isActive) {
                $btn.attr('aria-current', 'page');
            } else {
                $btn.removeAttr('aria-current');
            }
        });
        setActiveWpSubmenu(tab);
    }

    function menuHrefTab(href) {
        if (!href) {
            return null;
        }

        try {
            var url = new URL(href, window.location.origin);
            if (url.searchParams.get('page') !== 'modern-job-board') {
                return null;
            }
            return url.searchParams.get('tab') || config.default_tab || 'dashboard';
        } catch (e) {
            return null;
        }
    }

    function setActiveWpSubmenu(tab) {
        var $top = $('#toplevel_page_modern-job-board');
        if (!$top.length) {
            return;
        }

        var isDefault = tab === (config.default_tab || 'dashboard');
        $top.toggleClass('current', isDefault);
        $top.children('a').first().toggleClass('current', isDefault);

        $top.find('.wp-submenu a').each(function () {
            var hrefTab = menuHrefTab(this.getAttribute('href'));
            if (hrefTab === null) {
                return;
            }

            var isActive = hrefTab === tab;
            var $link = $(this);
            $link.toggleClass('current', isActive);
            $link.parent('li').toggleClass('current', isActive);
            if (isActive) {
                $link.attr('aria-current', 'page');
            } else {
                $link.removeAttr('aria-current');
            }
        });
    }

    function retagSubtab(el, tagName) {
        if (!el || !el.tagName) {
            return el;
        }
        tagName = String(tagName || '').toLowerCase();
        if (el.tagName.toLowerCase() === tagName) {
            return el;
        }
        var next = document.createElement(tagName);
        Array.prototype.forEach.call(el.attributes, function (attr) {
            next.setAttribute(attr.name, attr.value);
        });
        while (el.firstChild) {
            next.appendChild(el.firstChild);
        }
        if (tagName === 'button') {
            next.setAttribute('type', 'button');
            next.removeAttribute('tabindex');
        } else {
            next.removeAttribute('type');
            next.setAttribute('tabindex', '0');
        }
        if (el.parentNode) {
            el.parentNode.replaceChild(next, el);
        }
        return next;
    }

    function activateSettingsSubtab(settingsTab, pushHistory) {
        if (!settingsTab) {
            return;
        }

        currentSettingsTab = settingsTab;

        $panel.find('.mjb-settings-subtab').each(function () {
            var isActive = $(this).attr('data-settings-tab') === settingsTab;
            var el = retagSubtab(this, isActive ? 'span' : 'button');
            var $btn = $(el);
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

        var previousHtml = $panel.html();
        setLoadingState(true, tab);

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
                settings_tab: tab === 'settings' ? settingsTab : '',
                range: tab === 'dashboard' ? currentRange : '',
                mjb_list: (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') ? currentListFilter : '',
                mjb_q: (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') ? currentJobsQ : '',
                mjb_company: tab === 'jobs' ? currentJobsCompany : '',
                mjb_cat: tab === 'jobs' ? currentJobsCat : '',
                mjb_type: tab === 'jobs' ? currentJobsType : '',
                mjb_orderby: (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') ? currentJobsOrderby : '',
                mjb_order: (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') ? currentJobsOrder : '',
                mjb_per: (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') ? currentJobsPer : '',
                mjb_loc: tab === 'companies' ? currentCompaniesLoc : '',
                mjb_job: tab === 'applications' ? currentJobsJob : '',
                mjb_ext: tab === 'resumes' ? currentResumesExt : ''
            }
        }).done(function (response) {
            if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                $panel.html(previousHtml);
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
            if (window.mjbAdminDashboard && typeof window.mjbAdminDashboard.init === 'function') {
                window.mjbAdminDashboard.init($panel.get(0));
            }
            if (window.mjbAdminJobs && typeof window.mjbAdminJobs.init === 'function') {
                window.mjbAdminJobs.init($panel.get(0));
            }
            if (window.mjbAdminCompanies && typeof window.mjbAdminCompanies.init === 'function') {
                window.mjbAdminCompanies.init($panel.get(0));
            }
            if (window.mjbAdminApplications && typeof window.mjbAdminApplications.init === 'function') {
                window.mjbAdminApplications.init($panel.get(0));
            }
            if (window.mjbAdminResumes && typeof window.mjbAdminResumes.init === 'function') {
                window.mjbAdminResumes.init($panel.get(0));
            }
            if (window.mjbAdminSettings && typeof window.mjbAdminSettings.init === 'function') {
                window.mjbAdminSettings.init($panel.get(0));
            }
            syncJobsStateFromPanel();
            if (pendingNotice && pendingNotice.message) {
                prependPanelNotice(pendingNotice.message, pendingNotice.isError);
                pendingNotice = null;
            }

            if (pushHistory !== false && window.history && window.history.pushState) {
                window.history.pushState(
                    null,
                    '',
                    buildAdminUrl(currentTab, currentPage, currentToolsTab, currentSettingsTab)
                );
            }
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                $panel.html(previousHtml);
                if (pendingNotice && pendingNotice.message) {
                    prependPanelNotice(pendingNotice.message, pendingNotice.isError);
                    pendingNotice = null;
                }
            }
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
        currentListFilter = '';
        resetJobsQuery();
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

    $shell.on('click', 'a.mjb-js-tab', function (event) {
        var $link = $(this);
        var tab = $link.data('tab');
        if (!tab) {
            return;
        }
        event.preventDefault();
        currentPage = 1;
        if (tab === 'settings' && $link.data('settings-tab')) {
            currentSettingsTab = String($link.data('settings-tab'));
        }
        if (tab === 'tools' && $link.data('tools-tab')) {
            currentToolsTab = String($link.data('tools-tab'));
        }
        if (typeof $link.attr('data-list-filter') !== 'undefined') {
            currentListFilter = String($link.attr('data-list-filter') || '');
        } else if (tab === 'jobs' || tab === 'companies' || tab === 'applications' || tab === 'resumes') {
            currentListFilter = '';
        }
        if (tab === 'jobs') {
            currentJobsQ = '';
            currentJobsCompany = $link.attr('data-company') || '';
            currentJobsCat = '';
            currentJobsType = '';
            currentJobsOrderby = 'posted';
            currentJobsOrder = 'desc';
            currentCompaniesLoc = '';
        }
        if (tab === 'companies') {
            currentJobsQ = '';
            currentJobsOrderby = 'posted';
            currentJobsOrder = 'desc';
            currentCompaniesLoc = '';
        }
        if (tab === 'applications') {
            currentJobsQ = '';
            currentJobsOrderby = 'posted';
            currentJobsOrder = 'desc';
            currentJobsJob = $link.data('job') ? String($link.data('job')) : '';
        } else {
            currentJobsJob = '';
        }
        if (tab === 'resumes') {
            currentJobsQ = '';
            currentJobsOrderby = 'posted';
            currentJobsOrder = 'desc';
            currentResumesExt = '';
        } else {
            currentResumesExt = '';
        }
        if (tab === currentTab && tab === 'settings' && currentSettingsTab) {
            activateSettingsSubtab(currentSettingsTab, true);
            return;
        }
        loadTab(tab, 1, true, currentToolsTab, currentSettingsTab);
    });

    $panel.on('click', '.mjb-range button[data-range]', function (event) {
        event.preventDefault();
        var days = parseRange($(this).attr('data-range'));
        if (days === currentRange && currentTab === 'dashboard') {
            return;
        }
        currentRange = days;
        currentPage = 1;
        loadTab('dashboard', 1, true);
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
        currentRange = parseRange(params.get('range'));
        currentListFilter = params.get('mjb_list') || '';
        currentJobsQ = params.get('mjb_q') || '';
        currentJobsCompany = params.get('mjb_company') || '';
        currentJobsCat = params.get('mjb_cat') || '';
        currentJobsType = params.get('mjb_type') || '';
        currentJobsOrderby = params.get('mjb_orderby') || 'posted';
        currentJobsOrder = params.get('mjb_order') || 'desc';
        currentJobsPer = params.get('mjb_per') || '';
        currentJobsJob = params.get('mjb_job') || '';
        currentCompaniesLoc = params.get('mjb_loc') || '';
        currentResumesExt = params.get('mjb_ext') || '';

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

    setActiveWpSubmenu(currentTab);

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
        $modal.removeClass('is-closing');
        $modal.prop('hidden', false).attr('aria-hidden', 'false');
        $shell.addClass('mjb-modal-open');
        window.setTimeout(function () {
            $modal.find('#mjb-delete-job-confirm').trigger('focus');
        }, 10);
    }

    function closeDeleteModal() {
        var $modal = getDeleteModal();
        if (!$modal.length || $modal.prop('hidden') || $modal.hasClass('is-closing')) {
            return;
        }
        function hide() {
            $modal.removeClass('is-closing');
            $modal.find('#mjb-delete-job-confirm').prop('disabled', false).removeClass('is-busy');
            $modal.prop('hidden', true).attr('aria-hidden', 'true');
            $shell.removeClass('mjb-modal-open');
            pendingDeleteJobId = null;
            if ($lastDeleteTrigger && $lastDeleteTrigger.length) {
                $lastDeleteTrigger.trigger('focus');
            }
            $lastDeleteTrigger = null;
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            hide();
            return;
        }
        $modal.addClass('is-closing');
        window.setTimeout(hide, 180);
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

    if (window.mjbAdminJobs && typeof window.mjbAdminJobs.init === 'function') {
        window.mjbAdminJobs.init($panel.get(0));
    }
    if (window.mjbAdminCompanies && typeof window.mjbAdminCompanies.init === 'function') {
        window.mjbAdminCompanies.init($panel.get(0));
    }
    if (window.mjbAdminApplications && typeof window.mjbAdminApplications.init === 'function') {
        window.mjbAdminApplications.init($panel.get(0));
    }
    if (window.mjbAdminResumes && typeof window.mjbAdminResumes.init === 'function') {
        window.mjbAdminResumes.init($panel.get(0));
    }
    if (window.mjbAdminSettings && typeof window.mjbAdminSettings.init === 'function') {
        window.mjbAdminSettings.init($panel.get(0));
    }
    syncJobsStateFromPanel();

    window.mjbAdminTabs = {
        reloadJobs: function (opts) {
            opts = opts || {};
            if (typeof opts.list !== 'undefined') {
                currentListFilter = opts.list === 'all' ? '' : String(opts.list || '');
            }
            if (typeof opts.q !== 'undefined') {
                currentJobsQ = String(opts.q || '');
            }
            if (typeof opts.company !== 'undefined') {
                currentJobsCompany = String(opts.company || '');
            }
            if (typeof opts.cat !== 'undefined') {
                currentJobsCat = String(opts.cat || '');
            }
            if (typeof opts.type !== 'undefined') {
                currentJobsType = String(opts.type || '');
            }
            if (typeof opts.orderby !== 'undefined') {
                currentJobsOrderby = String(opts.orderby || 'posted');
            }
            if (typeof opts.order !== 'undefined') {
                currentJobsOrder = String(opts.order || 'desc');
            }
            if (typeof opts.per !== 'undefined') {
                currentJobsPer = String(opts.per || '');
            }
            currentPage = opts.page ? parseInt(opts.page, 10) || 1 : 1;
            loadTab('jobs', currentPage, true);
        },
        reloadCompanies: function (opts) {
            opts = opts || {};
            if (typeof opts.list !== 'undefined') {
                currentListFilter = opts.list === 'all' ? '' : String(opts.list || '');
            }
            if (typeof opts.q !== 'undefined') {
                currentJobsQ = String(opts.q || '');
            }
            if (typeof opts.loc !== 'undefined') {
                currentCompaniesLoc = String(opts.loc || '');
            }
            if (typeof opts.orderby !== 'undefined') {
                currentJobsOrderby = String(opts.orderby || 'posted');
            }
            if (typeof opts.order !== 'undefined') {
                currentJobsOrder = String(opts.order || 'desc');
            }
            if (typeof opts.per !== 'undefined') {
                currentJobsPer = String(opts.per || '');
            }
            currentPage = opts.page ? parseInt(opts.page, 10) || 1 : 1;
            loadTab('companies', currentPage, true);
        },
        reloadApplications: function (opts) {
            opts = opts || {};
            if (typeof opts.list !== 'undefined') {
                currentListFilter = opts.list === 'all' ? '' : String(opts.list || '');
            }
            if (typeof opts.q !== 'undefined') {
                currentJobsQ = String(opts.q || '');
            }
            if (typeof opts.job !== 'undefined') {
                currentJobsJob = String(opts.job || '');
            }
            if (typeof opts.orderby !== 'undefined') {
                currentJobsOrderby = String(opts.orderby || 'posted');
            }
            if (typeof opts.order !== 'undefined') {
                currentJobsOrder = String(opts.order || 'desc');
            }
            if (typeof opts.per !== 'undefined') {
                currentJobsPer = String(opts.per || '');
            }
            currentPage = opts.page ? parseInt(opts.page, 10) || 1 : 1;
            loadTab('applications', currentPage, true);
        },
        reloadResumes: function (opts) {
            opts = opts || {};
            if (typeof opts.list !== 'undefined') {
                currentListFilter = opts.list === 'all' ? '' : String(opts.list || '');
            }
            if (typeof opts.q !== 'undefined') {
                currentJobsQ = String(opts.q || '');
            }
            if (typeof opts.ext !== 'undefined') {
                currentResumesExt = String(opts.ext || '');
            }
            if (typeof opts.orderby !== 'undefined') {
                currentJobsOrderby = String(opts.orderby || 'posted');
            }
            if (typeof opts.order !== 'undefined') {
                currentJobsOrder = String(opts.order || 'desc');
            }
            if (typeof opts.per !== 'undefined') {
                currentJobsPer = String(opts.per || '');
            }
            currentPage = opts.page ? parseInt(opts.page, 10) || 1 : 1;
            loadTab('resumes', currentPage, true);
        }
    };


    $(document).on('submit', '.mjb-tab-panel--tools form, .mjb-tab-panel--custom-fields form, .mjb-setup-form, #mjb-generate-key-form', function (event) {
        var form = this;
        var actionInput = form.querySelector('[name="mjb_action"]');
        var actionName = actionInput ? actionInput.value : '';
        if (event.isDefaultPrevented() || actionName.indexOf('export_') === 0 || typeof window.ajaxurl !== 'string' || window.ajaxurl === '') {
            return;
        }
        if (form.getAttribute('data-busy') === '1') {
            event.preventDefault();
            return;
        }
        event.preventDefault();
        form.setAttribute('data-busy', '1');

        var ajaxAction = 'mjb_admin_tools';
        if (form.classList.contains('mjb-setup-form')) {
            ajaxAction = 'mjb_admin_setup';
        } else if (form.id === 'mjb-generate-key-form') {
            ajaxAction = 'mjb_generate_license_key';
        } else if (form.closest('.mjb-tab-panel--custom-fields')) {
            ajaxAction = 'mjb_admin_custom_field';
        }

        var body = new FormData(form);
        if (form.id === 'mjb-generate-key-form') {
            document.querySelectorAll('[form="mjb-generate-key-form"]').forEach(function (field) {
                if (field.name && !field.disabled) {
                    body.set(field.name, field.value);
                }
            });
        }
        body.set('action', ajaxAction);

        fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            form.removeAttribute('data-busy');
            var data = payload && payload.data ? payload.data : {};
            if (!payload || !payload.success) {
                prependPanelNotice(data.message || 'That action could not be completed.', true);
                return;
            }
            reloadAfterMutation(data, false);
        }).catch(function () {
            form.removeAttribute('data-busy');
            prependPanelNotice('That action could not be completed.', true);
        });
    });

    $(document).on('click', '.mjb-tab-panel--custom-fields a.delete', function (event) {
        var link = this;
        if (event.isDefaultPrevented() || typeof window.ajaxurl !== 'string' || window.ajaxurl === '') {
            return;
        }
        event.preventDefault();
        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            window.location.href = link.href;
            return;
        }
        var index = url.searchParams.get('index');
        var nonce = url.searchParams.get('_wpnonce');
        if (index === null || !nonce) {
            window.location.href = link.href;
            return;
        }
        var body = new FormData();
        body.set('action', 'mjb_admin_custom_field');
        body.set('mjb_delete_custom_field', '1');
        body.set('index', index);
        body.set('_wpnonce', nonce);
        fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            var data = payload && payload.data ? payload.data : {};
            if (!payload || !payload.success) {
                prependPanelNotice(data.message || 'That action could not be completed.', true);
                return;
            }
            reloadAfterMutation(data, false);
        }).catch(function () {
            prependPanelNotice('That action could not be completed.', true);
        });
    });

});
