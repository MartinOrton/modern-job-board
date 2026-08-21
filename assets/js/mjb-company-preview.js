/**
 * Company hover preview on job cards (#18).
 */
jQuery(function ($) {
    var cache = Object.create(null);
    var $tip = null;
    var hideTimer = null;

    function ensureTip() {
        if ($tip && $tip.length) {
            return $tip;
        }
        $tip = $('<div class="mjb-company-preview" role="tooltip" hidden></div>').appendTo('body');
        return $tip;
    }

    function hide() {
        if ($tip) {
            $tip.prop('hidden', true).empty();
        }
    }

    function showAt($el, html) {
        var tip = ensureTip();
        tip.html(html).prop('hidden', false);
        var rect = $el[0].getBoundingClientRect();
        var top = rect.bottom + window.scrollY + 8;
        var left = rect.left + window.scrollX;
        tip.css({ top: top + 'px', left: left + 'px' });
    }

    function render(data) {
        var parts = ['<div class="mjb-company-preview__inner">'];
        if (data.logo) {
            parts.push('<img class="mjb-company-preview__logo" src="' + data.logo + '" alt="" width="40" height="40">');
        }
        parts.push('<div>');
        parts.push('<strong class="mjb-company-preview__name">' + $('<div/>').text(data.name || '').html() + '</strong>');
        if (data.motto) {
            parts.push('<p class="mjb-company-preview__motto">' + $('<div/>').text(data.motto).html() + '</p>');
        }
        var links = [];
        if (data.website) {
            links.push('<a href="' + data.website + '" target="_blank" rel="noopener">Website</a>');
        }
        if (data.linkedin) {
            links.push('<a href="' + data.linkedin + '" target="_blank" rel="noopener">LinkedIn</a>');
        }
        if (data.twitter) {
            links.push('<a href="' + data.twitter + '" target="_blank" rel="noopener">X</a>');
        }
        if (links.length) {
            parts.push('<div class="mjb-company-preview__links">' + links.join(' · ') + '</div>');
        }
        parts.push('</div></div>');
        return parts.join('');
    }

    function fetchPreview(jobId, companyId, done) {
        var key = companyId || ('job:' + jobId);
        if (cache[key]) {
            done(cache[key]);
            return;
        }
        if (!window.mjbCompanyPreview) {
            return;
        }
        $.ajax({
            url: mjbCompanyPreview.ajaxUrl,
            dataType: 'json',
            data: {
                action: 'mjb_company_preview',
                security: mjbCompanyPreview.nonce,
                job_id: jobId || 0,
                company_id: companyId || 0
            }
        }).done(function (res) {
            if (res && res.success && res.data) {
                cache[key] = res.data;
                done(res.data);
            }
        });
    }

    $(document).on('mouseenter focusin', '.mjb-job-card [data-mjb-company-id], .mjb-job-card__company, .mjb-company-link', function () {
        var $el = $(this);
        clearTimeout(hideTimer);
        var companyId = $el.data('mjb-company-id') || $el.closest('[data-mjb-company-id]').data('mjb-company-id') || 0;
        var jobId = $el.closest('[data-job-id]').data('job-id') || $el.closest('.mjb-job-card').data('job-id') || 0;
        fetchPreview(jobId, companyId, function (data) {
            showAt($el, render(data));
        });
    });

    $(document).on('mouseleave focusout', '.mjb-job-card [data-mjb-company-id], .mjb-job-card__company, .mjb-company-link', function () {
        hideTimer = setTimeout(hide, 180);
    });

    $(document).on('mouseenter', '.mjb-company-preview', function () {
        clearTimeout(hideTimer);
    });
    $(document).on('mouseleave', '.mjb-company-preview', hide);
});
