/**
 * Inline (field-level) validation for Modern Job Board forms.
 *
 * Behaviour (not post-submit-only):
 * - Required asterisks + short legend when the form has required fields
 * - On blur: validate once the field has been focused (touched)
 * - On input/change: revalidate after the field is touched
 * - On submit: validate every visible field and focus the first error
 *
 * Covers application, job, registration, dashboard, and auth card forms.
 */
(function () {
    'use strict';

    var FORM_SELECTOR = '.mjb-application-form, .mjb-job-form, .mjb-form, .mjb-auth-card-form';
    var FIELD_SELECTOR = 'input, select, textarea';
    var SKIP_TYPES = {
        submit: true,
        button: true,
        reset: true,
        hidden: true,
        image: true
    };

    var i18n = (typeof mjbFormValidation !== 'undefined' && mjbFormValidation) ? mjbFormValidation : {};
    var MSG_REQUIRED = i18n.required || 'This field is required.';
    var MSG_EMAIL = i18n.email || 'Please enter a valid email address.';
    var MSG_URL = i18n.url || 'Please enter a valid URL.';
    var MSG_NUMBER = i18n.number || 'Please enter a valid number.';
    var MSG_MINLENGTH = i18n.minlength || 'Please enter at least %d characters.';
    var MSG_MAXLENGTH = i18n.maxlength || 'Please enter no more than %d characters.';
    var MSG_PATTERN = i18n.pattern || 'Please match the requested format.';
    var MSG_PASSWORD_MATCH = i18n.passwordMatch || 'Passwords do not match.';
    var MSG_LEGEND = i18n.requiredLegend || 'Required fields are marked with *';

    /** @type {WeakMap<Element, boolean>} */
    var touchedFields = typeof WeakMap !== 'undefined' ? new WeakMap() : null;

    function isTouched(field) {
        if (!field) {
            return false;
        }
        if (touchedFields) {
            return !!touchedFields.get(field);
        }
        return field.getAttribute('data-mjb-touched') === '1';
    }

    function markTouched(field) {
        if (!field) {
            return;
        }
        if (touchedFields) {
            touchedFields.set(field, true);
        } else {
            field.setAttribute('data-mjb-touched', '1');
        }
    }

    function formatMessage(template, n) {
        return String(template || '').replace(/%d/g, String(n));
    }

    function isVisible(el) {
        if (!el || el.disabled) {
            return false;
        }
        if (el.closest && el.closest('.mjb-hp-field, [aria-hidden="true"]')) {
            return false;
        }
        if (el.closest && el.closest('.mjb-is-hidden')) {
            return false;
        }
        // offsetParent is null for display:none (except fixed); also check computed style.
        if (el.offsetParent === null) {
            var style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            // Still in a hidden ancestor with display:none
            var parent = el.parentElement;
            while (parent && parent !== document.body) {
                if (parent.classList && parent.classList.contains('mjb-is-hidden')) {
                    return false;
                }
                var ps = window.getComputedStyle(parent);
                if (ps.display === 'none') {
                    return false;
                }
                parent = parent.parentElement;
            }
        }
        return true;
    }

    function isSkippable(field) {
        if (!field || field.nodeType !== 1) {
            return true;
        }
        var type = (field.getAttribute('type') || field.type || '').toLowerCase();
        if (SKIP_TYPES[type]) {
            return true;
        }
        if (field.name && field.name.indexOf('mjb_hp') === 0) {
            return true;
        }
        if (field.classList && field.classList.contains('mjb-hp-field')) {
            return true;
        }
        return false;
    }

    function getFieldGroup(field) {
        return field.closest(
            'p, .mjb-field, .mjb-form-field, .mjb-profile-resume-option, .mjb-auth-card-fields__row > p'
        ) || field.parentElement;
    }

    function getErrorId(field) {
        var id = field.id || field.name || 'field';
        return 'mjb-error-' + String(id).replace(/[^a-zA-Z0-9_-]/g, '-');
    }

    function clearFieldError(field) {
        if (!field) {
            return;
        }
        field.classList.remove('mjb-field-invalid');
        field.removeAttribute('aria-invalid');

        var describedby = field.getAttribute('aria-describedby');
        var errorId = getErrorId(field);
        if (describedby) {
            var parts = describedby.split(/\s+/).filter(function (part) {
                return part && part !== errorId;
            });
            if (parts.length) {
                field.setAttribute('aria-describedby', parts.join(' '));
            } else {
                field.removeAttribute('aria-describedby');
            }
        }

        var group = getFieldGroup(field);
        if (group) {
            group.classList.remove('mjb-field-has-error');
            var existing = group.querySelectorAll('.mjb-field-error');
            for (var i = 0; i < existing.length; i++) {
                if (existing[i].getAttribute('data-mjb-for') === (field.id || field.name || '')) {
                    existing[i].parentNode.removeChild(existing[i]);
                }
            }
        }

        // Fallback: remove error by id
        var byId = document.getElementById(errorId);
        if (byId && byId.parentNode) {
            byId.parentNode.removeChild(byId);
        }
    }

    function showFieldError(field, message) {
        clearFieldError(field);

        field.classList.add('mjb-field-invalid');
        field.setAttribute('aria-invalid', 'true');

        var errorId = getErrorId(field);
        var group = getFieldGroup(field);
        if (group) {
            group.classList.add('mjb-field-has-error');
        }

        var error = document.createElement('span');
        error.className = 'mjb-field-error';
        error.id = errorId;
        error.setAttribute('role', 'alert');
        error.setAttribute('data-mjb-for', field.id || field.name || '');
        error.textContent = message;

        // Prefer end of the field group so messages sit under the whole control block
        // (matches Elementor: error appears at the field, not as a browser tooltip).
        if (
            group &&
            (group.tagName === 'P' ||
                (group.classList &&
                    (group.classList.contains('mjb-field') ||
                        group.classList.contains('mjb-form-field'))))
        ) {
            group.appendChild(error);
        } else if (field.parentNode) {
            if (field.nextSibling) {
                field.parentNode.insertBefore(error, field.nextSibling);
            } else {
                field.parentNode.appendChild(error);
            }
        }

        var describedby = field.getAttribute('aria-describedby');
        if (describedby) {
            if ((' ' + describedby + ' ').indexOf(' ' + errorId + ' ') === -1) {
                field.setAttribute('aria-describedby', describedby + ' ' + errorId);
            }
        } else {
            field.setAttribute('aria-describedby', errorId);
        }
    }

    function isEmptyValue(field) {
        var type = (field.getAttribute('type') || field.type || '').toLowerCase();
        var tag = field.tagName.toLowerCase();

        if (type === 'checkbox' || type === 'radio') {
            if (type === 'radio' && field.name) {
                var group = field.form
                    ? field.form.querySelectorAll('input[type="radio"][name="' + cssEscape(field.name) + '"]')
                    : [];
                for (var i = 0; i < group.length; i++) {
                    if (group[i].checked && isVisible(group[i])) {
                        return false;
                    }
                }
                return true;
            }
            return !field.checked;
        }

        if (type === 'file') {
            return !field.files || field.files.length === 0;
        }

        if (tag === 'select') {
            var val = field.value;
            return val === null || val === undefined || String(val).trim() === '';
        }

        // Passwords: treat as empty only if literally empty (do not trim).
        if (type === 'password') {
            return String(field.value || '') === '';
        }

        return String(field.value || '').trim() === '';
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/"/g, '\\"');
    }

    function isValidEmail(value) {
        // Practical email check (not full RFC).
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function isValidUrl(value) {
        try {
            var url = new URL(value);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    function isPasswordConfirmField(field) {
        var name = (field.name || '').toLowerCase();
        var id = (field.id || '').toLowerCase();
        return (
            name.indexOf('password_confirm') !== -1 ||
            name.indexOf('pass_confirm') !== -1 ||
            name === 'pass2' ||
            name.indexOf('password2') !== -1 ||
            id.indexOf('password-confirm') !== -1 ||
            id.indexOf('password_confirm') !== -1
        );
    }

    function isPrimaryPasswordField(field) {
        var name = (field.name || '').toLowerCase();
        var id = (field.id || '').toLowerCase();
        if (isPasswordConfirmField(field)) {
            return false;
        }
        return (
            name === 'mjb_password' ||
            name === 'pass1' ||
            name === 'password' ||
            (name.indexOf('password') !== -1 && name.indexOf('confirm') === -1) ||
            (id.indexOf('password') !== -1 && id.indexOf('confirm') === -1)
        );
    }

    function findPasswordConfirmField(form, passwordField) {
        if (!form) {
            return null;
        }
        var candidates = form.querySelectorAll('input[type="password"]');
        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i] !== passwordField && isPasswordConfirmField(candidates[i])) {
                return candidates[i];
            }
        }
        return (
            form.querySelector(
                'input[name="mjb_password_confirm"], input[name="pass2"], input[id*="password-confirm"], input[id*="password_confirm"]'
            ) || null
        );
    }

    function findPrimaryPasswordField(form, confirmField) {
        if (!form) {
            return null;
        }
        var byName = form.querySelector(
            'input[name="mjb_password"], input[name="pass1"], input[name="password"]'
        );
        if (byName && byName !== confirmField) {
            return byName;
        }
        var candidates = form.querySelectorAll('input[type="password"]');
        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i] !== confirmField && isPrimaryPasswordField(candidates[i])) {
                return candidates[i];
            }
        }
        return null;
    }

    function validateField(field, options) {
        options = options || {};
        if (isSkippable(field) || !isVisible(field)) {
            clearFieldError(field);
            return true;
        }

        var type = (field.getAttribute('type') || field.type || '').toLowerCase();
        var required = field.required || field.getAttribute('aria-required') === 'true';
        var rawValue = String(field.value || '');
        var value = type === 'password' ? rawValue : rawValue.trim();

        if (required && isEmptyValue(field)) {
            // Only show one error per radio group.
            if (type === 'radio' && field.name && field.form) {
                var radios = field.form.querySelectorAll(
                    'input[type="radio"][name="' + cssEscape(field.name) + '"]'
                );
                var firstVisible = null;
                for (var r = 0; r < radios.length; r++) {
                    if (isVisible(radios[r])) {
                        firstVisible = radios[r];
                        break;
                    }
                }
                if (firstVisible && firstVisible !== field) {
                    clearFieldError(field);
                    return false;
                }
            }
            showFieldError(field, MSG_REQUIRED);
            return false;
        }

        if (!isEmptyValue(field)) {
            if (type === 'email' && !isValidEmail(value)) {
                showFieldError(field, MSG_EMAIL);
                return false;
            }
            if (type === 'url' && !isValidUrl(value)) {
                showFieldError(field, MSG_URL);
                return false;
            }
            if (type === 'number') {
                var num = Number(value);
                if (isNaN(num)) {
                    showFieldError(field, MSG_NUMBER);
                    return false;
                }
                if (field.min !== '' && field.min !== undefined && num < Number(field.min)) {
                    showFieldError(field, MSG_NUMBER);
                    return false;
                }
                if (field.max !== '' && field.max !== undefined && num > Number(field.max)) {
                    showFieldError(field, MSG_NUMBER);
                    return false;
                }
            }

            var minLengthAttr = field.getAttribute('minlength');
            if (minLengthAttr !== null && minLengthAttr !== '') {
                var minLength = parseInt(minLengthAttr, 10);
                if (!isNaN(minLength) && rawValue.length < minLength) {
                    showFieldError(field, formatMessage(MSG_MINLENGTH, minLength));
                    return false;
                }
            }

            var maxLengthAttr = field.getAttribute('maxlength');
            if (maxLengthAttr !== null && maxLengthAttr !== '') {
                var maxLength = parseInt(maxLengthAttr, 10);
                // Ignore browser default-ish huge maxlengths.
                if (!isNaN(maxLength) && maxLength > 0 && maxLength < 1000000 && rawValue.length > maxLength) {
                    showFieldError(field, formatMessage(MSG_MAXLENGTH, maxLength));
                    return false;
                }
            }

            var pattern = field.getAttribute('pattern');
            if (pattern) {
                try {
                    var re = new RegExp('^(?:' + pattern + ')$');
                    if (!re.test(rawValue)) {
                        var title = field.getAttribute('title');
                        showFieldError(field, title || MSG_PATTERN);
                        return false;
                    }
                } catch (e) {
                    // Invalid pattern attribute — skip client pattern check.
                }
            }

            // Password confirmation match.
            if (type === 'password' && isPasswordConfirmField(field) && field.form) {
                var primary = findPrimaryPasswordField(field.form, field);
                if (primary && String(primary.value || '') !== rawValue) {
                    showFieldError(field, MSG_PASSWORD_MATCH);
                    return false;
                }
            }
        }

        clearFieldError(field);

        // When the primary password changes, re-check confirm if it was already touched.
        if (
            !options.skipPair &&
            type === 'password' &&
            isPrimaryPasswordField(field) &&
            field.form
        ) {
            var confirm = findPasswordConfirmField(field.form, field);
            if (confirm && (isTouched(confirm) || confirm.classList.contains('mjb-field-invalid'))) {
                validateField(confirm, { skipPair: true });
            }
        }

        return true;
    }

    function getLabelForField(field) {
        if (field.id) {
            var byFor = field.form
                ? field.form.querySelector('label[for="' + cssEscape(field.id) + '"]')
                : document.querySelector('label[for="' + cssEscape(field.id) + '"]');
            if (byFor) {
                return byFor;
            }
        }
        var parentLabel = field.closest('label');
        if (parentLabel) {
            return parentLabel;
        }
        var group = getFieldGroup(field);
        if (group) {
            return group.querySelector('label');
        }
        return null;
    }

    function ensureRequiredMark(field) {
        if (isSkippable(field) || !field.required) {
            return;
        }
        var label = getLabelForField(field);
        if (!label || label.querySelector('.mjb-required-mark')) {
            return;
        }
        // Skip labels that only wrap a checkbox/radio control (no standalone field label).
        var onlyControl =
            label.querySelector('input[type="checkbox"], input[type="radio"]') &&
            label.querySelectorAll('input, select, textarea').length === 1 &&
            String(label.textContent || '').trim().length < 2;
        if (onlyControl) {
            return;
        }
        var mark = document.createElement('span');
        mark.className = 'mjb-required-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = '*';
        label.appendChild(document.createTextNode(' '));
        label.appendChild(mark);
    }

    function refreshRequiredMarks(form) {
        var fields = form.querySelectorAll(FIELD_SELECTOR);
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (isSkippable(field)) {
                continue;
            }
            var label = getLabelForField(field);
            if (!label) {
                continue;
            }
            var mark = label.querySelector('.mjb-required-mark');
            if (field.required && isVisible(field)) {
                if (!mark) {
                    ensureRequiredMark(field);
                }
            } else if (mark && !field.required) {
                // Only remove JS-managed marks when field is no longer required.
                mark.parentNode.removeChild(mark);
            }
        }
    }

    function ensureRequiredLegend(form) {
        if (form.querySelector('.mjb-form-required-note')) {
            return;
        }
        var hasRequired = false;
        var fields = form.querySelectorAll(FIELD_SELECTOR);
        for (var i = 0; i < fields.length; i++) {
            if (!isSkippable(fields[i]) && fields[i].required) {
                hasRequired = true;
                break;
            }
        }
        if (!hasRequired) {
            return;
        }

        var note = document.createElement('p');
        note.className = 'mjb-form-required-note';
        var mark = document.createElement('span');
        mark.className = 'mjb-required-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = '*';
        note.appendChild(mark);
        note.appendChild(document.createTextNode(' ' + (i18n.requiredLegendShort || MSG_LEGEND || 'Required fields')));

        // Insert after honeypot / nonce / hidden inputs, before first visible field group.
        var firstFieldGroup = null;
        var children = form.children;
        for (var c = 0; c < children.length; c++) {
            var child = children[c];
            if (
                child.classList &&
                (child.classList.contains('mjb-hp-field') ||
                    child.classList.contains('mjb-form-required-note') ||
                    child.classList.contains('mjb-auth-apply-note'))
            ) {
                continue;
            }
            if (
                child.tagName === 'INPUT' &&
                (child.type === 'hidden' || (child.name && child.name.indexOf('_nonce') !== -1))
            ) {
                continue;
            }
            if (
                child.tagName === 'P' ||
                child.tagName === 'FIELDSET' ||
                (child.classList &&
                    (child.classList.contains('mjb-field') ||
                        child.classList.contains('mjb-form-field') ||
                        child.classList.contains('mjb-auth-card-fields') ||
                        child.classList.contains('mjb-form-section')))
            ) {
                firstFieldGroup = child;
                break;
            }
        }
        if (firstFieldGroup) {
            form.insertBefore(note, firstFieldGroup);
        } else {
            form.insertBefore(note, form.firstChild);
        }
    }

    function validateForm(form) {
        var fields = form.querySelectorAll(FIELD_SELECTOR);
        var firstInvalid = null;
        var valid = true;
        var seenRadios = {};

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (isSkippable(field) || !isVisible(field)) {
                clearFieldError(field);
                continue;
            }

            // Submit validates every field; mark as touched so messages stick while editing.
            markTouched(field);

            var type = (field.getAttribute('type') || field.type || '').toLowerCase();
            if (type === 'radio' && field.name) {
                if (seenRadios[field.name]) {
                    continue;
                }
                seenRadios[field.name] = true;
            }

            if (!validateField(field)) {
                valid = false;
                if (!firstInvalid) {
                    firstInvalid = field;
                }
            }
        }

        if (firstInvalid) {
            try {
                firstInvalid.focus({ preventScroll: false });
            } catch (e) {
                firstInvalid.focus();
            }
            if (typeof firstInvalid.scrollIntoView === 'function') {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        return valid;
    }

    function shouldLiveValidate(field) {
        return (
            isTouched(field) ||
            field.classList.contains('mjb-field-invalid') ||
            (getFieldGroup(field) && getFieldGroup(field).classList.contains('mjb-field-has-error'))
        );
    }

    function bindFieldEvents(form) {
        // Live revalidation after the field has been blurred (or already shows an error).
        // Avoids "invalid email" on the first character before the user leaves the field.
        form.addEventListener(
            'input',
            function (event) {
                var field = event.target;
                if (!field || isSkippable(field)) {
                    return;
                }
                if (shouldLiveValidate(field)) {
                    validateField(field);
                }
            },
            true
        );

        form.addEventListener(
            'change',
            function (event) {
                var field = event.target;
                if (!field || isSkippable(field)) {
                    return;
                }
                markTouched(field);
                // Re-check required marks when dynamic scripts toggle required.
                window.setTimeout(function () {
                    refreshRequiredMarks(form);
                    validateField(field);
                }, 0);
            },
            true
        );

        form.addEventListener(
            'blur',
            function (event) {
                var field = event.target;
                if (!field || isSkippable(field)) {
                    return;
                }
                if (field.matches && field.matches(FIELD_SELECTOR)) {
                    markTouched(field);
                    // Always validate on blur — including empty required fields.
                    validateField(field);
                }
            },
            true
        );
    }

    function enhanceForm(form) {
        if (!form || form.getAttribute('data-mjb-validation') === '1') {
            return;
        }
        form.setAttribute('data-mjb-validation', '1');
        form.setAttribute('novalidate', 'novalidate');
        form.classList.add('mjb-has-field-validation');

        ensureRequiredLegend(form);
        refreshRequiredMarks(form);
        bindFieldEvents(form);

        form.addEventListener('submit', function (event) {
            refreshRequiredMarks(form);
            if (!validateForm(form)) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            if (form.classList.contains('mjb-job-form') && cfg().ajaxUrl) {
                event.preventDefault();
                saveJobForm(form);
            }
        });

        // Job form and resume toggle set `required` after load / on change.
        window.addEventListener('load', function () {
            ensureRequiredLegend(form);
            refreshRequiredMarks(form);
        });
        window.setTimeout(function () {
            ensureRequiredLegend(form);
            refreshRequiredMarks(form);
        }, 50);
    }

    function init() {
        var forms = document.querySelectorAll(FORM_SELECTOR);
        for (var i = 0; i < forms.length; i++) {
            enhanceForm(forms[i]);
        }
    }

    function cfg() {
        return window.mjbFormValidation || {};
    }

    function slideWindowToTop() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }

    function placeStatusNotice(message, isError) {
        if (!message) {
            return;
        }
        if (document.querySelector('.mjb-candidate-dashboard') && typeof window.mjbShowDashboardNotice === 'function') {
            window.mjbShowDashboardNotice(message, isError ? 'error' : 'success');
            return;
        }
        var host = document.querySelector('.mjb-portal-dashboard')
            || document.querySelector('.mjb-candidate-dashboard')
            || document.querySelector('.mjb-application-area')
            || document.querySelector('.mjb-single-main')
            || document.body;
        host.querySelectorAll(':scope > .mjb-message').forEach(function (notice) {
            notice.remove();
        });
        var notice = document.createElement('div');
        notice.className = 'mjb-message ' + (isError ? 'error' : 'success');
        notice.setAttribute('role', isError ? 'alert' : 'status');
        notice.textContent = message;
        var stage = host.id === 'mjb-candidate-dashboard' || host.classList.contains('mjb-candidate-dashboard')
            ? document.getElementById('mjb-cd-stage')
            : null;
        if (stage && stage.parentNode === host) {
            host.insertBefore(notice, stage);
        } else {
            host.insertBefore(notice, host.firstChild);
        }
        slideWindowToTop();
    }

    function saveJobForm(form) {
        if (!form || form.getAttribute('data-busy') === '1') {
            return;
        }
        var button = form.querySelector('[name="mjb_submit_job"]');
        var original = button ? button.value : '';
        form.setAttribute('data-busy', '1');
        if (button) {
            button.value = cfg().saving || 'Saving…';
        }
        var body = new FormData(form);
        body.set('action', 'mjb_save_job');
        body.set('mjb_submit_job', '1');
        fetch(cfg().ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            form.removeAttribute('data-busy');
            if (button && original) {
                button.value = original;
            }
            var data = payload && payload.data ? payload.data : {};
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (!payload || !payload.success) {
                placeStatusNotice(data.message || cfg().saveFailed || 'Changes could not be saved. Try again.', true);
                return;
            }
            if (data.job_id && !form.querySelector('[name="job_id"]')) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'job_id';
                hidden.value = String(data.job_id);
                form.appendChild(hidden);
            }
            if (button && data.job_id) {
                button.value = cfg().updateJob || 'Update Job';
            }
            placeStatusNotice(data.message || cfg().updateJob || 'Update Job', false);
        }).catch(function () {
            form.removeAttribute('data-busy');
            if (button && original) {
                button.value = original;
            }
            placeStatusNotice(cfg().saveFailed || 'Changes could not be saved. Try again.', true);
        });
    }

    function inlineAction(link) {
        var kind = link.getAttribute('data-mjb-inline');
        var url;
        try {
            url = new URL(link.href, window.location.origin);
        } catch (error) {
            return;
        }
        var body = new FormData();
        body.set('_wpnonce', url.searchParams.get('_wpnonce') || '');
        if (kind === 'apply') {
            var applyMatch = url.pathname.match(/\/apply\/([a-f0-9]+)/i);
            body.set('action', 'mjb_apply_to_job');
            body.set('token', applyMatch ? applyMatch[1] : (url.searchParams.get('mjb_a') || ''));
        } else if (kind === 'save') {
            var saveMatch = url.pathname.match(/\/save\/(\d+)/);
            body.set('action', 'mjb_toggle_saved_job');
            body.set('job_id', saveMatch ? saveMatch[1] : (url.searchParams.get('job_id') || ''));
        } else {
            return;
        }
        if (link.getAttribute('data-busy') === '1') {
            return;
        }
        link.setAttribute('data-busy', '1');
        fetch(cfg().ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            link.removeAttribute('data-busy');
            var data = payload && payload.data ? payload.data : {};
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (!payload || !payload.success) {
                placeStatusNotice(data.message || cfg().saveFailed || 'Changes could not be saved. Try again.', true);
                return;
            }
            if (kind === 'apply' && data.applied) {
                var applyLabel = link.querySelector('span');
                if (applyLabel) {
                    applyLabel.textContent = cfg().applied || 'Applied';
                }
                link.setAttribute('aria-disabled', 'true');
                link.removeAttribute('href');
            }
            if (kind === 'save') {
                var saveLabel = link.querySelector('span');
                var saved = !!data.saved;
                link.classList.toggle('is-active', saved);
                if (saveLabel) {
                    saveLabel.textContent = saved ? (cfg().savedJob || 'Saved') : (cfg().saveJob || 'Save');
                }
                var item = link.closest('.mjb-cd-item');
                if (item && !saved) {
                    var list = item.parentNode;
                    item.remove();
                    if (list && !list.querySelector('.mjb-cd-item')) {
                        var count = document.querySelector('#mjb-cd-saved .mjb-cd-count');
                        if (count) {
                            count.textContent = '0';
                        }
                    }
                }
            }
            placeStatusNotice(data.message || '', false);
        }).catch(function () {
            link.removeAttribute('data-busy');
            placeStatusNotice(cfg().saveFailed || 'Changes could not be saved. Try again.', true);
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('[data-mjb-inline]') : null;
        if (!link || !cfg().ajaxUrl || link.getAttribute('aria-disabled') === 'true') {
            if (link && link.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
            }
            return;
        }
        event.preventDefault();
        inlineAction(link);
    });

    function slideToActionStatus() {
        var params;
        try {
            params = new URL(window.location.href).searchParams;
        } catch (error) {
            return;
        }
        if (!params.get('mjb_notice')) {
            return;
        }
        var box = document.querySelector('.mjb-message.success, .mjb-message.error, .mjb-message.warning, .mjb-message.info');
        if (!box || box.hasAttribute('hidden')) {
            return;
        }
        if (window.history && 'scrollRestoration' in window.history) {
            window.history.scrollRestoration = 'manual';
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    if (document.readyState === 'complete') {
        slideToActionStatus();
    } else {
        window.addEventListener('load', slideToActionStatus);
    }

    // Expose for dynamic forms / tests.
    window.mjbFormValidationInit = init;
})();
