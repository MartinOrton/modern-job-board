jQuery(document).ready(function ($) {
    var $filterForm = $('#mjb-job-filter');
    var $jobsList = $('#mjb-jobs-list');
    var $loader = $('.mjb-loader');

    function slugifyKeyword(keyword) {
        return $.trim(keyword)
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
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
        if (filters.search_keywords) {
            parts.push('keyword', slugifyKeyword(filters.search_keywords));
        }
        if (page && parseInt(page, 10) > 1) {
            parts.push('page', String(page));
        }

        return base + (parts.length ? parts.join('/') + '/' : '');
    }

    function getFilters() {
        return {
            search_keywords: $filterForm.find('input[name="search_keywords"]').val(),
            search_location: $filterForm.find('[name="search_location"]').val(),
            search_category: $filterForm.find('select[name="search_category"]').val(),
            search_type: $filterForm.find('select[name="search_type"]').val()
        };
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
            page: page || 1,
            posts_per_page: $jobsList.data('posts-per-page') || 10
        };

        $loader.removeClass('mjb-is-hidden');
        $jobsList.addClass('mjb-is-loading');

        $.ajax({
            url: mjb_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                $jobsList.html(response);
                $jobsList.removeClass('mjb-is-loading');
                $loader.addClass('mjb-is-hidden');

                if (pushHistory !== false && window.history && window.history.pushState) {
                    window.history.pushState(null, '', buildPrettyUrl(filters, page || 1));
                }
            },
            error: function () {
                console.log('Error fetching jobs');
                $jobsList.removeClass('mjb-is-loading');
                $loader.addClass('mjb-is-hidden');
            }
        });
    }

    if ($filterForm.length) {
        $filterForm.on('submit', function (e) {
            e.preventDefault();
            var filters = getFilters();
            window.location.href = buildPrettyUrl(filters, 1);
        });

        $jobsList.on('click', '.mjb-page-link', function (e) {
            e.preventDefault();

            var page = parseInt($(this).data('page'), 10);
            var targetUrl = $(this).data('url');

            if (!page || $(this).hasClass('is-active')) {
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