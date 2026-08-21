/**
 * Multi-step registration wizard (candidate + employer).
 * Show/hide panels; final step submits via AJAX (FormData). No-JS falls back to full POST.
 */
(function () {
    'use strict';

    var cfg = typeof mjbRegistrationWizard !== 'undefined' ? mjbRegistrationWizard : null;
    if (!cfg || !cfg.ajaxUrl) {
        return;
    }

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.prototype.slice.call(root.querySelectorAll(sel));
    }

    function isEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }

    function showFieldError(field, message) {
        if (!field) {
            return;
        }
        field.classList.add('mjb-field-invalid');
        field.setAttribute('aria-invalid', 'true');
        var group = field.closest('p, .mjb-field, .mjb-form-field') || field.parentElement;
        if (!group) {
            return;
        }
        group.classList.add('mjb-field-has-error');
        var existing = group.querySelector('.mjb-field-error[data-mjb-wizard]');
        if (existing) {
            existing.textContent = message;
            return;
        }
        var el = document.createElement('span');
        el.className = 'mjb-field-error';
        el.setAttribute('data-mjb-wizard', '1');
        el.setAttribute('role', 'alert');
        el.textContent = message;
        group.appendChild(el);
    }

    function clearFieldError(field) {
        if (!field) {
            return;
        }
        field.classList.remove('mjb-field-invalid');
        field.removeAttribute('aria-invalid');
        var group = field.closest('p, .mjb-field, .mjb-form-field') || field.parentElement;
        if (!group) {
            return;
        }
        group.classList.remove('mjb-field-has-error');
        var existing = group.querySelectorAll('.mjb-field-error[data-mjb-wizard]');
        for (var i = 0; i < existing.length; i++) {
            existing[i].parentNode.removeChild(existing[i]);
        }
    }

    function clearPanelErrors(panel) {
        qsa(panel, 'input, select, textarea').forEach(clearFieldError);
    }

    function validatePanel(panel, form) {
        clearPanelErrors(panel);
        var ok = true;
        var firstInvalid = null;
        var fields = qsa(panel, 'input, select, textarea');

        fields.forEach(function (field) {
            if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                return;
            }
            if (field.closest('.mjb-hp-field')) {
                return;
            }

            var type = (field.getAttribute('type') || field.type || '').toLowerCase();
            var required = field.required || field.getAttribute('aria-required') === 'true';
            var value = type === 'password' ? String(field.value || '') : String(field.value || '').trim();

            if (type === 'file') {
                if (required && (!field.files || field.files.length === 0)) {
                    showFieldError(field, cfg.i18n.required || 'This field is required.');
                    ok = false;
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }
                }
                return;
            }

            if (required && value === '') {
                showFieldError(field, cfg.i18n.required || 'This field is required.');
                ok = false;
                if (!firstInvalid) {
                    firstInvalid = field;
                }
                return;
            }

            if (value !== '' && type === 'email' && !isEmail(value)) {
                showFieldError(field, cfg.i18n.email || 'Please enter a valid email address.');
                ok = false;
                if (!firstInvalid) {
                    firstInvalid = field;
                }
                return;
            }

            var minLength = field.getAttribute('minlength');
            if (value !== '' && minLength && value.length < parseInt(minLength, 10)) {
                var msg = (cfg.i18n.minlength || 'Please enter at least %d characters.').replace(
                    '%d',
                    minLength
                );
                showFieldError(field, msg);
                ok = false;
                if (!firstInvalid) {
                    firstInvalid = field;
                }
            }
        });

        // Password match within panel.
        var pass = qs(panel, 'input[name="mjb_password"]');
        var confirm = qs(panel, 'input[name="mjb_password_confirm"]');
        if (pass && confirm && pass.value && confirm.value && pass.value !== confirm.value) {
            showFieldError(confirm, cfg.i18n.passwordMatch || 'Passwords do not match.');
            ok = false;
            if (!firstInvalid) {
                firstInvalid = confirm;
            }
        }

        if (firstInvalid) {
            try {
                firstInvalid.focus({ preventScroll: false });
            } catch (e) {
                firstInvalid.focus();
            }
        }

        return ok;
    }

    function setStep(form, stepIndex) {
        var panels = qsa(form, '.mjb-wizard-panel');
        var steps = qsa(form, '.mjb-wizard-progress__step');
        var total = panels.length;
        if (stepIndex < 0) {
            stepIndex = 0;
        }
        if (stepIndex > total - 1) {
            stepIndex = total - 1;
        }

        form.setAttribute('data-mjb-step', String(stepIndex));

        panels.forEach(function (panel, i) {
            var active = i === stepIndex;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        steps.forEach(function (step, i) {
            step.classList.toggle('is-current', i === stepIndex);
            step.classList.toggle('is-complete', i < stepIndex);
            if (i === stepIndex) {
                step.setAttribute('aria-current', 'step');
            } else {
                step.removeAttribute('aria-current');
            }
        });

        var backBtn = qs(form, '[data-mjb-wizard-back]');
        var nextBtn = qs(form, '[data-mjb-wizard-next]');
        var submitBtn = qs(form, '[data-mjb-wizard-submit]');

        if (backBtn) {
            backBtn.hidden = stepIndex === 0;
            backBtn.disabled = stepIndex === 0;
        }
        if (nextBtn) {
            nextBtn.hidden = stepIndex >= total - 1;
        }
        if (submitBtn) {
            submitBtn.hidden = stepIndex < total - 1;
        }

        // Focus first field in active panel.
        var activePanel = panels[stepIndex];
        if (activePanel) {
            var focusable = qs(
                activePanel,
                'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
            );
            if (focusable) {
                window.setTimeout(function () {
                    try {
                        focusable.focus({ preventScroll: true });
                    } catch (e2) {
                        focusable.focus();
                    }
                }, 30);
            }
        }

        return stepIndex;
    }

    function setBusy(form, busy) {
        form.classList.toggle('is-submitting', !!busy);
        qsa(form, 'button, input[type="submit"]').forEach(function (btn) {
            btn.disabled = !!busy;
        });
        var status = qs(form, '.mjb-wizard-status');
        if (status) {
            status.hidden = !busy;
            status.textContent = busy ? cfg.i18n.submitting || 'Creating your account…' : '';
        }
    }

    function showFormError(form, message) {
        var box = qs(form, '.mjb-wizard-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'mjb-wizard-error mjb-message error';
            box.setAttribute('role', 'alert');
            var actions = qs(form, '.mjb-wizard-actions');
            if (actions) {
                form.insertBefore(box, actions);
            } else {
                form.appendChild(box);
            }
        }
        box.hidden = !message;
        box.textContent = message || '';
    }

    function checkEmailAvailable(email) {
        if (!cfg.checkEmailAction || !email) {
            return Promise.resolve({ ok: true });
        }
        var body = new FormData();
        body.append('action', cfg.checkEmailAction);
        body.append('email', email);
        body.append('nonce', cfg.nonce || '');
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (json) {
                return unwrapWpAjax(json);
            })
            .catch(function () {
                return { ok: true };
            });
    }

    function unwrapWpAjax(json) {
        if (json && typeof json === 'object' && Object.prototype.hasOwnProperty.call(json, 'success')) {
            if (json.success) {
                return Object.assign({ ok: true }, json.data || {});
            }
            return Object.assign({ ok: false }, json.data || {});
        }
        return json || {};
    }

    function submitForm(form) {
        showFormError(form, '');
        setBusy(form, true);

        var formData = new FormData(form);
        formData.append('action', form.getAttribute('data-mjb-ajax-action') || cfg.action);
        formData.append('mjb_ajax', '1');

        // Ensure submit flag is present for PHP handlers.
        var audience = form.getAttribute('data-mjb-audience');
        if (audience === 'candidate') {
            formData.append('mjb_register_candidate', '1');
        } else if (audience === 'employer') {
            formData.append('mjb_register_employer', '1');
        }

        // reCAPTCHA response if present.
        if (typeof grecaptcha !== 'undefined' && grecaptcha.getResponse) {
            try {
                var token = grecaptcha.getResponse();
                if (token) {
                    formData.set('g-recaptcha-response', token);
                }
            } catch (e) {
                /* ignore */
            }
        }

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (json) {
                setBusy(form, false);
                var data = unwrapWpAjax(json);
                if (data.ok && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                var message =
                    data.message ||
                    cfg.i18n.failed ||
                    'Registration failed. Please try again.';
                showFormError(form, message);

                // Jump to account step if email error.
                if (data.code === 'error_email_exists' || data.code === 'error_invalid_email') {
                    var panels = qsa(form, '.mjb-wizard-panel');
                    setStep(form, panels.length - 1);
                    var emailField = qs(form, 'input[name="mjb_email"]');
                    if (emailField) {
                        showFieldError(emailField, message);
                        emailField.focus();
                    }
                }
            })
            .catch(function () {
                setBusy(form, false);
                showFormError(form, cfg.i18n.network || 'Network error. Please try again.');
            });
    }

    function enhance(form) {
        if (!form || form.getAttribute('data-mjb-wizard') === '1') {
            return;
        }
        form.setAttribute('data-mjb-wizard', '1');
        form.classList.add('mjb-registration-wizard');

        var panels = qsa(form, '.mjb-wizard-panel');
        if (panels.length < 2) {
            return;
        }

        function currentStep() {
            var n = parseInt(form.getAttribute('data-mjb-step') || '0', 10);
            return isNaN(n) ? 0 : n;
        }

        setStep(form, 0);

        form.addEventListener('click', function (event) {
            var next = event.target.closest('[data-mjb-wizard-next]');
            var back = event.target.closest('[data-mjb-wizard-back]');
            if (next) {
                event.preventDefault();
                var step = currentStep();
                var panel = panels[step];
                if (!validatePanel(panel, form)) {
                    return;
                }
                setStep(form, step + 1);
                return;
            }
            if (back) {
                event.preventDefault();
                showFormError(form, '');
                setStep(form, currentStep() - 1);
            }
        });

        form.addEventListener('submit', function (event) {
            // Only intercept when wizard is active and JS is ready.
            event.preventDefault();
            var step = currentStep();
            var panel = panels[step];
            if (!validatePanel(panel, form)) {
                return;
            }

            // On final step, optionally check email then submit.
            var emailField = qs(form, 'input[name="mjb_email"]');
            var email = emailField ? emailField.value.trim() : '';

            setBusy(form, true);
            checkEmailAvailable(email).then(function (res) {
                if (res && res.ok === false) {
                    setBusy(form, false);
                    var msg = res.message || cfg.i18n.emailExists || 'That email is already registered.';
                    showFormError(form, msg);
                    if (emailField) {
                        showFieldError(emailField, msg);
                        emailField.focus();
                    }
                    return;
                }
                submitForm(form);
            });
        });
    }

    function init() {
        qsa(document, 'form.mjb-registration-form[data-mjb-audience]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
            if (typeof window.mjbPhoneFieldInit === 'function') {
                window.mjbPhoneFieldInit();
            }
        });
    } else {
        init();
        if (typeof window.mjbPhoneFieldInit === 'function') {
            window.mjbPhoneFieldInit();
        }
    }

    window.mjbRegistrationWizardInit = init;
})();
