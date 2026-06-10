jQuery(document).ready(function ($) {
    var $filterForm = $('#mjb-job-filter');
    var $jobsList = $('#mjb-jobs-list');
    var $loader = $('.mjb-loader');

    if ($filterForm.length) {
        $filterForm.on('submit', function (e) {
            e.preventDefault();

            var data = {
                action: 'mjb_filter_jobs',
                security: mjb_ajax.nonce,
                search_keywords: $filterForm.find('input[name="search_keywords"]').val(),
                search_location: $filterForm.find('[name="search_location"]').val(),
                search_category: $filterForm.find('select[name="search_category"]').val(),
                search_type: $filterForm.find('select[name="search_type"]').val()
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
        });
    }
});
