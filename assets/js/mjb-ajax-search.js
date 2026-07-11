jQuery(document).ready(function ($) {
    var $filterForm = $('#mjb-job-filter');
    var $jobsBoard = $('#mjb-jobs-board');
    var $jobsList = $('#mjb-jobs-list');
    var $loader = $('#mjb-loader-overlay');

    if ($loader.length && !$loader.parent().is('body')) {
        $loader.appendTo('body');
    }

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

    function getSearchCompany() {
        var fromForm = $filterForm.find('input[name="search_company"]').val();
        if (fromForm) {
            return fromForm;
        }

        var fromList = $jobsList.data('search-company');
        if (fromList) {
            return String(fromList);
        }

        // Fall back to pretty path: /jobs/company/{slug}/
        var match = window.location.pathname.match(/\/company\/([^/]+)/i);
        return match ? decodeURIComponent(match[1]) : '';
    }

    function getFilters() {
        return {
            search_keywords: $filterForm.find('input[name="search_keywords"]').val(),
            search_location: $filterForm.find('[name="search_location"]').val(),
            search_category: $filterForm.find('select[name="search_category"]').val(),
            search_type: $filterForm.find('select[name="search_type"]').val(),
            search_company: getSearchCompany()
        };
    }

    function getJobsResults() {
        var $results = $jobsList.find('#mjb-jobs-results');
        return $results.length ? $results : $jobsList;
    }

    function scrollToPageTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function setLoadingState(isLoading) {
        if (!$loader.length) {
            return;
        }

        if (isLoading) {
            $('body').addClass('mjb-jobs-loading');
            $jobsBoard.addClass('mjb-is-loading');
            $loader.addClass('is-active').attr('aria-hidden', 'false');
            return;
        }

        $loader.removeClass('is-active').attr('aria-hidden', 'true');
        $('body').removeClass('mjb-jobs-loading');
        $jobsBoard.removeClass('mjb-is-loading');
    }

    function fetchJobs(page, pushHistory) {
        var filters = getFilters();
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

        setLoadingState(true);

        $.ajax({
            url: mjb_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                getJobsResults().html(response);
                setLoadingState(false);
                scrollToPageTop();

                if (pushHistory !== false && window.history && window.history.pushState) {
                    window.history.pushState(null, '', buildPrettyUrl(filters, page || 1));
                }
            },
            error: function () {
                console.log('Error fetching jobs');
                setLoadingState(false);
            }
        });
    }

    if ($filterForm.length && $jobsBoard.length) {
        $filterForm.on('submit', function (e) {
            e.preventDefault();
            var filters = getFilters();
            window.location.href = buildPrettyUrl(filters, 1);
        });

        $jobsList.on('click', '.mjb-page-link', function (e) {
            e.preventDefault();

            if ($(this).hasClass('is-disabled') || $(this).hasClass('is-active')) {
                return;
            }

            var page = parseInt($(this).data('page'), 10);
            var targetUrl = $(this).data('url');

            if (!page) {
                return;
            }

            if (targetUrl && window.history && window.history.pushState) {
                fetchJobs(page, false);
                window.history.pushState(null, '', targetUrl);
            } else {
                fetchJobs(page, true);
            }
        });
    }
});