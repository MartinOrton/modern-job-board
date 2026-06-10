jQuery(document).ready(function ($) {
    var $filterForm = $('#mjb-job-filter');
    var $jobsList = $('#mjb-jobs-list');
    var $loader = $('.mjb-loader');

    function fetchJobs(page) {
        var data = {
            action: 'mjb_filter_jobs',
            security: mjb_ajax.nonce,
            search_keywords: $filterForm.find('input[name="search_keywords"]').val(),
            search_location: $filterForm.find('[name="search_location"]').val(),
            search_category: $filterForm.find('select[name="search_category"]').val(),
            search_type: $filterForm.find('select[name="search_type"]').val(),
            page: page || 1,
            posts_per_page: $jobsList.data('posts-per-page') || 10
        };

        $loader.show();
        $jobsList.css('opacity', '0.5');

        $.ajax({
            url: mjb_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                $jobsList.html(response);
                $jobsList.css('opacity', '1');
                $loader.hide();
            },
            error: function () {
                console.log('Error fetching jobs');
                $jobsList.css('opacity', '1');
                $loader.hide();
            }
        });
    }

    if ($filterForm.length) {
        $filterForm.on('submit', function (e) {
            e.preventDefault();
            fetchJobs(1);
        });

        $jobsList.on('click', '.mjb-page-link', function (e) {
            e.preventDefault();
            var page = parseInt($(this).data('page'), 10);
            if (!page || $(this).hasClass('is-active')) {
                return;
            }
            fetchJobs(page);
        });
    }
});