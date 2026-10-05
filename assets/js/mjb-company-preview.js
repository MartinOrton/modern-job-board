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

    function escapeAttr(value) {
        return $('<div/>').text(value || '').html().replace(/"/g, '&quot;');
    }

    function wrapLink(html, url, className) {
        if (!url) {
            return html;
        }
        return '<a class="' + className + '" href="' + escapeAttr(url) + '">' + html + '</a>';
    }

    function iconLink(href, iconHtml, label) {
        if (!href || !iconHtml) {
            return '';
        }
        return '<a class="mjb-company-preview__icon" href="' + escapeAttr(href)
            + '" target="_blank" rel="noopener noreferrer" title="' + escapeAttr(label)
            + '" aria-label="' + escapeAttr(label) + '">' + iconHtml + '</a>';
    }

    function render(data) {
        var url = data.url || '';
        var icons = (window.mjbCompanyPreview && mjbCompanyPreview.icons) || {};
        var labels = (window.mjbCompanyPreview && mjbCompanyPreview.i18n) || {};
        var parts = ['<div class="mjb-company-preview__inner">'];
        if (data.logo) {
            parts.push(wrapLink(
                '<img class="mjb-company-preview__logo" src="' + escapeAttr(data.logo) + '" alt="" width="40" height="40">',
                url,
                'mjb-company-preview__logo-link'
            ));
        }
        parts.push('<div>');
        parts.push(wrapLink(
            '<strong class="mjb-company-preview__name">' + $('<div/>').text(data.name || '').html() + '</strong>',
            url,
            'mjb-company-preview__name-link'
        ));
        if (data.motto) {
            parts.push('<p class="mjb-company-preview__motto">' + $('<div/>').text(data.motto).html() + '</p>');
        }
        var links = [
            iconLink(data.website, icons.website, labels.website || 'Website'),
            iconLink(data.linkedin, icons.linkedin, labels.linkedin || 'LinkedIn'),
            iconLink(data.twitter, icons.twitter, labels.twitter || 'X')
        ].filter(Boolean);
        if (links.length) {
            parts.push('<div class="mjb-company-preview__links">' + links.join('') + '</div>');
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
                return;
            }
            hide();
        }).fail(function () {
            hide();
        });
    }

    function previewTarget($el) {
        return $el.closest('.mjb-job-card__company, [data-mjb-company-id], .mjb-company-link');
    }

    function previewIds($el) {
        var $host = previewTarget($el);
        var $card = $el.closest('.mjb-job-card');
        var companyId = parseInt($host.attr('data-mjb-company-id') || $el.closest('[data-mjb-company-id]').attr('data-mjb-company-id') || '0', 10) || 0;
        var jobId = parseInt($host.attr('data-job-id') || $card.attr('data-job-id') || '0', 10) || 0;
        return { companyId: companyId, jobId: jobId, $host: $host.length ? $host : $el };
    }

    function openPreview($el) {
        var ids = previewIds($el);
        if (!ids.companyId && !ids.jobId) {
            return;
        }
        clearTimeout(hideTimer);
        showAt(ids.$host, '<div class="mjb-company-preview__skeleton" aria-hidden="true">'
            + '<span class="mjb-skeleton mjb-company-preview__skeleton-logo"></span>'
            + '<div class="mjb-company-preview__skeleton-copy">'
            + '<span class="mjb-skeleton mjb-skeleton--company"></span>'
            + '<span class="mjb-skeleton mjb-skeleton--excerpt mjb-skeleton--excerpt-short"></span>'
            + '</div></div>');
        fetchPreview(ids.jobId, ids.companyId, function (data) {
            showAt(ids.$host, render(data));
        });
    }

    $(document).on('mouseenter focusin', '.mjb-job-card [data-mjb-company-id], .mjb-job-card__company, .mjb-company-link', function () {
        openPreview($(this));
    });

    $(document).on('mouseleave focusout', '.mjb-job-card [data-mjb-company-id], .mjb-job-card__company, .mjb-company-link', function () {
        hideTimer = setTimeout(hide, 180);
    });

    $(document).on('mouseenter', '.mjb-company-preview', function () {
        clearTimeout(hideTimer);
    });
    $(document).on('mouseleave', '.mjb-company-preview', hide);
});
