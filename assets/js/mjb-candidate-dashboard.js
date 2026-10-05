/**
 * Candidate dashboard: section nav, profile strength, and save-bar state.
 */
(function () {
    'use strict';

    var root = document.querySelector('.mjb-candidate-dashboard');
    if (!root) {
        return;
    }

    var i18n = (window.mjbCandidateDashboard && window.mjbCandidateDashboard.i18n) || {};
    var bioMin = parseInt(root.getAttribute('data-bio-complete') || '20', 10);
    function cvDone() {
        var cvRow = root.querySelector('[data-check="cv"]');
        return !!(cvRow && cvRow.classList.contains('is-done'));
    }

    root.classList.add('is-ready');

    function isResumePreviewUrl(value) {
        try {
            var url = new URL(value, window.location.href);
            return url.origin === window.location.origin && url.searchParams.get('mjb_download') === 'resume';
        } catch (error) {
            return false;
        }
    }

    function motionOk() {
        return !window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function closeAnimatedDialog(dialog) {
        if (!dialog || !dialog.open || typeof dialog.close !== 'function') {
            return;
        }
        if (dialog.classList.contains('is-closing')) {
            return;
        }
        if (!motionOk()) {
            clearConfirmWord(dialog);
            dialog.close();
            return;
        }
        dialog.classList.add('is-closing');
        var finished = false;
        function finish(event) {
            if (event && (event.target !== dialog || event.animationName !== 'mjb-cd-dialog-out')) {
                return;
            }
            if (finished) {
                return;
            }
            finished = true;
            dialog.removeEventListener('animationend', finish);
            dialog.classList.remove('is-closing');
            clearConfirmWord(dialog);
            if (dialog.open) {
                dialog.close();
            }
        }
        dialog.addEventListener('animationend', finish);
        window.setTimeout(function () {
            finish();
        }, 240);
    }

    function closeCvPreview() {
        closeAnimatedDialog(document.getElementById('mjb-cd-cv-dialog'));
    }

    function openCvPreview(button) {
        var dialog = document.getElementById('mjb-cd-cv-dialog');
        var frame = document.getElementById('mjb-cd-cv-frame');
        var fallback = document.getElementById('mjb-cd-cv-fallback');
        var title = document.getElementById('mjb-cd-cv-title');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        var name = button.getAttribute('data-mjb-cv-name') || '';
        var kind = button.getAttribute('data-mjb-cv-kind') || '';
        var url = button.getAttribute('data-mjb-cv-url') || '';
        if (title && name) {
            title.textContent = name;
        }
        dialog.classList.toggle('is-file', kind !== 'pdf' || !isResumePreviewUrl(url));
        if (kind === 'pdf' && isResumePreviewUrl(url)) {
            if (fallback) {
                fallback.hidden = true;
            }
            if (frame) {
                frame.hidden = false;
                frame.src = url;
            }
        } else {
            if (frame) {
                frame.removeAttribute('src');
                frame.hidden = true;
            }
            if (fallback) {
                fallback.hidden = false;
            }
        }
        dialog.classList.remove('is-closing');
        if (!dialog.open) {
            dialog.showModal();
        }
        var closeBtn = dialog.querySelector('[data-mjb-cv-close]');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    root.addEventListener('click', function (event) {
        var view = event.target.closest('#mjb-cd-cv-view');
        if (view) {
            event.preventDefault();
            openCvPreview(view);
            return;
        }
        if (event.target.closest('[data-mjb-cv-close]') || event.target.closest('[data-mjb-dialog-close]')) {
            event.preventDefault();
            closeAnimatedDialog(event.target.closest('dialog'));
        }
    });

    function clearConfirmWord(dialog) {
        var confirmInput = dialog.querySelector('[data-confirm-word]');
        if (!confirmInput) {
            return;
        }
        confirmInput.value = '';
        confirmInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    root.addEventListener('cancel', function (event) {
        var dialog = event.target;
        if (!dialog || !dialog.classList || !dialog.classList.contains('mjb-cd-dialog')) {
            return;
        }
        event.preventDefault();
        closeAnimatedDialog(dialog);
    }, true);

    var cvDialog = document.getElementById('mjb-cd-cv-dialog');
    if (cvDialog) {
        cvDialog.addEventListener('click', function (event) {
            if (event.target === cvDialog) {
                closeCvPreview();
            }
        });
        cvDialog.addEventListener('close', function () {
            var frame = document.getElementById('mjb-cd-cv-frame');
            if (frame) {
                frame.removeAttribute('src');
            }
        });
    }

    function setCvOnFile(on) {
        var fileRow = document.getElementById('mjb-cd-cv-file');
        var chip = document.getElementById('mjb-cd-cv-chip');
        var hint = document.getElementById('mjb-cd-cv-hint');
        var fileInput = document.getElementById('mjb_resume');
        var check = root.querySelector('[data-check="cv"]');
        if (fileRow) {
            fileRow.hidden = !on;
        }
        if (chip) {
            chip.hidden = !on;
        }
        if (hint) {
            hint.textContent = on ? (hint.getAttribute('data-replace') || '') : (hint.getAttribute('data-empty') || '');
        }
        if (fileInput) {
            if (on) {
                fileInput.removeAttribute('required');
                fileInput.removeAttribute('aria-required');
            } else {
                fileInput.setAttribute('required', '');
                fileInput.setAttribute('aria-required', 'true');
            }
        }
        if (check) {
            check.classList.toggle('is-done', !!on);
        }
        score();
    }

    function resetCvDelete() {
        var input = document.getElementById('mjb_cv_delete_confirm');
        var field = document.getElementById('mjb-cd-cv-delete-field');
        var error = document.getElementById('mjb-cd-cv-delete-error');
        var submit = document.getElementById('mjb-cd-cv-delete-submit');
        if (input) {
            input.value = '';
        }
        if (field) {
            field.classList.remove('is-invalid');
        }
        if (error) {
            error.hidden = true;
            error.textContent = '';
        }
        if (submit) {
            submit.disabled = true;
        }
    }

    function syncCvDelete() {
        var input = document.getElementById('mjb_cv_delete_confirm');
        var submit = document.getElementById('mjb-cd-cv-delete-submit');
        if (!input || !submit) {
            return;
        }
        var word = (input.getAttribute('data-confirm-word') || 'delete').toLowerCase();
        submit.disabled = input.value.trim().toLowerCase() !== word;
    }

    function openCvDelete() {
        var dialog = document.getElementById('mjb-cd-cv-delete-dialog');
        var nameEl = document.getElementById('mjb-cd-cv-delete-name');
        var fileName = document.querySelector('#mjb-cd-cv-file .mjb-cd-file-txt b');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return;
        }
        if (nameEl && fileName && fileName.textContent) {
            nameEl.textContent = fileName.textContent;
        }
        resetCvDelete();
        dialog.classList.remove('is-closing');
        if (!dialog.open) {
            dialog.showModal();
        }
        var input = document.getElementById('mjb_cv_delete_confirm');
        if (input) {
            input.focus();
        }
    }

    root.addEventListener('click', function (event) {
        if (event.target.closest('#mjb-cd-cv-delete')) {
            event.preventDefault();
            openCvDelete();
            return;
        }
        if (event.target.closest('#mjb-cd-cv-delete-cancel')) {
            event.preventDefault();
            var dialog = document.getElementById('mjb-cd-cv-delete-dialog');
            resetCvDelete();
            closeAnimatedDialog(dialog);
        }
    });

    var cvDeleteInput = document.getElementById('mjb_cv_delete_confirm');
    if (cvDeleteInput) {
        cvDeleteInput.addEventListener('input', function () {
            var field = document.getElementById('mjb-cd-cv-delete-field');
            var error = document.getElementById('mjb-cd-cv-delete-error');
            if (field) {
                field.classList.remove('is-invalid');
            }
            if (error) {
                error.hidden = true;
            }
            syncCvDelete();
        });
    }

    var cvDeleteForm = document.getElementById('mjb-cd-cv-delete-form');
    if (cvDeleteForm) {
        cvDeleteForm.addEventListener('submit', function (event) {
            var dash = window.mjbCandidateDashboard || {};
            if (!dash.ajaxUrl) {
                return;
            }
            event.preventDefault();
            if (cvDeleteForm.getAttribute('data-busy') === '1') {
                return;
            }
            cvDeleteForm.setAttribute('data-busy', '1');
            var body = new FormData(cvDeleteForm);
            body.set('action', 'mjb_delete_resume');
            body.set('mjb_delete_resume', '1');
            fetch(dash.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                cvDeleteForm.removeAttribute('data-busy');
                var data = payload && payload.data ? payload.data : {};
                if (!payload || !payload.success) {
                    if (data.code === 'error_resume_delete_confirm') {
                        var field = document.getElementById('mjb-cd-cv-delete-field');
                        var error = document.getElementById('mjb-cd-cv-delete-error');
                        if (field) {
                            field.classList.add('is-invalid');
                        }
                        if (error) {
                            error.hidden = false;
                            error.textContent = data.message || '';
                        }
                        return;
                    }
                    closeAnimatedDialog(document.getElementById('mjb-cd-cv-delete-dialog'));
                    showDashboardNotice(data.message || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                    return;
                }
                resetCvDelete();
                closeAnimatedDialog(document.getElementById('mjb-cd-cv-delete-dialog'));
                closeCvPreview();
                setCvOnFile(false);
                showDashboardNotice(data.message, 'success');
            }).catch(function () {
                cvDeleteForm.removeAttribute('data-busy');
                showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
            });
        });
    }

    function text(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function fmt(template, values) {
        var list = values.slice();
        return String(template || '').replace(/%(\d+)\$d|%d/g, function (match, index) {
            if (index) {
                return values[parseInt(index, 10) - 1];
            }
            return list.length ? list.shift() : match;
        });
    }

    function rules() {
        var bio = text('mjb_bio');
        return {
            name: text('mjb_first_name') !== '' && text('mjb_last_name') !== '',
            city: text('mjb_city') !== '',
            phone: text('mjb_phone') !== '' || text('mjb_phone_national') !== '',
            company: text('mjb_experience_company') !== '',
            title: text('mjb_headline') !== '',
            bio: bio.length >= bioMin,
            prefs: true,
            cv: cvDone()
        };
    }

    function setCheck(row, ok) {
        row.classList.toggle('is-done', ok);
        var tick = row.querySelector('.mjb-cd-tick');
        if (tick) {
            tick.classList.toggle('is-ok', ok);
            tick.classList.toggle('is-todo', !ok);
        }
        var note = row.querySelector('.mjb-cd-sr');
        if (note) {
            note.textContent = ok ? (i18n.done || 'done') : (i18n.needed || 'still needed');
        }
        var act = row.querySelector('.mjb-cd-act');
        if (act) {
            if (ok) {
                act.setAttribute('hidden', '');
            } else {
                act.removeAttribute('hidden');
            }
        }
    }

    function fillStrengthSub(left, next) {
        var sub = document.getElementById('mjb-cd-pct-sub');
        if (!sub) {
            return;
        }
        sub.textContent = '';
        if (!left) {
            sub.textContent = i18n.complete || 'Complete — recruiters see your full profile';
            return;
        }
        var word = left === 1 ? (i18n.stepLeft || '1 step') : fmt(i18n.stepsLeft || '%d steps', [left]);
        var label = document.createElement('strong');
        label.textContent = word;
        sub.appendChild(label);
        sub.appendChild(document.createTextNode(' ' + (i18n.left || 'left')));
        if (next) {
            sub.appendChild(document.createTextNode(' · '));
            var link = document.createElement('a');
            link.href = '#' + next;
            link.setAttribute('data-mjb-jump', next);
            var u = document.createElement('span');
            u.className = 'mjb-cd-u';
            u.textContent = i18n.finish || 'Finish now';
            link.appendChild(u);
            sub.appendChild(link);
        }
    }

    function score() {
        var state = rules();
        var done = 0;
        var total = 0;
        var next = '';
        root.querySelectorAll('[data-check]').forEach(function (row) {
            var key = row.getAttribute('data-check');
            var ok = !!state[key];
            total += 1;
            if (ok) {
                done += 1;
            } else if (!next) {
                var act = row.querySelector('.mjb-cd-act');
                next = act ? act.getAttribute('data-mjb-jump') : '';
            }
            setCheck(row, ok);
        });
        var left = total - done;
        var pct = total ? Math.round(done / total * 100) : 0;
        var pctEl = document.getElementById('mjb-cd-pct');
        if (pctEl) {
            pctEl.textContent = pct + '%';
            pctEl.classList.toggle('is-zero', pct <= 0);
        }
        var ring = document.getElementById('mjb-cd-ring');
        if (ring) {
            ring.setAttribute('stroke-dasharray', pct + ' 100');
        }
        var meta = document.getElementById('mjb-cd-chk-meta');
        if (meta) {
            meta.textContent = fmt(i18n.of || '%1$d of %2$d', [done, total]);
        }
        var bar = document.getElementById('mjb-cd-chk-bar');
        if (bar) {
            bar.setAttribute('aria-valuenow', String(done));
            bar.setAttribute('aria-valuemax', String(total));
            bar.setAttribute('aria-label', fmt(i18n.ofLabel || '%1$d of %2$d profile items complete', [done, total]));
            var fill = bar.querySelector('i');
            if (fill) {
                fill.style.setProperty('--w', pct + '%');
            }
        }
        var hint = document.getElementById('mjb-cd-steps');
        if (hint) {
            if (left) {
                hint.hidden = false;
                hint.textContent = left === 1
                    ? (i18n.stepToComplete || '1 step to a complete profile')
                    : fmt(i18n.stepsToComplete || '%d steps to a complete profile', [left]);
            } else {
                hint.hidden = true;
            }
        }
        fillStrengthSub(left, next);
    }

    function setVisibility() {
        var input = document.getElementById('mjb_is_public');
        var on = !!(input && input.checked);
        var label = document.getElementById('mjb-cd-vis');
        var icon = document.getElementById('mjb-cd-vis-ic');
        var sub = document.getElementById('mjb-cd-vis-sub');
        if (label) {
            label.textContent = on ? (i18n.visible || 'Visible') : (i18n.hidden || 'Hidden');
            label.classList.toggle('is-off', !on);
        }
        if (icon) {
            icon.classList.toggle('is-off', !on);
            var shown = icon.querySelector('.mjb-cd-vis-on');
            var hidden = icon.querySelector('.mjb-cd-vis-off');
            if (shown) {
                if (on) {
                    shown.removeAttribute('hidden');
                } else {
                    shown.setAttribute('hidden', '');
                }
            }
            if (hidden) {
                if (on) {
                    hidden.setAttribute('hidden', '');
                } else {
                    hidden.removeAttribute('hidden');
                }
            }
        }
        if (sub) {
            sub.textContent = '';
            sub.appendChild(document.createTextNode(on ? (i18n.visibleSub || '') : (i18n.hiddenSub || '')));
            sub.appendChild(document.createTextNode(' · '));
            var link = document.createElement('a');
            link.href = '#mjb_is_public';
            link.setAttribute('data-mjb-jump', 'mjb_is_public');
            var u = document.createElement('span');
            u.className = 'mjb-cd-u';
            u.textContent = i18n.change || 'Change';
            link.appendChild(u);
            sub.appendChild(link);
        }
    }

    function setDirty(on) {
        var bar = document.getElementById('mjb-cd-savebar');
        var status = document.getElementById('mjb-cd-save-text');
        var save = document.getElementById('mjb-cd-save');
        var discard = document.getElementById('mjb-cd-discard');
        if (!bar) {
            return;
        }
        bar.classList.toggle('is-dirty', on);
        if (status && on) {
            status.textContent = i18n.unsaved || 'You have unsaved changes';
        }
        if (save) {
            save.disabled = !on;
        }
        if (discard) {
            discard.disabled = !on;
        }
    }

    function syncFieldDefaults(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('input, textarea, select').forEach(function (field) {
            if (field.type === 'file' || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                return;
            }
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.defaultChecked = field.checked;
            } else {
                field.defaultValue = field.value;
            }
        });
    }

    function setSaveBusy(button, form, busy) {
        if (!button) {
            return;
        }
        var spin = button.querySelector('.mjb-cd-btn-spin');
        var label = button.querySelector('.mjb-cd-save-label');
        if (busy) {
            if (form) {
                form.setAttribute('data-busy', '1');
                form.setAttribute('aria-busy', 'true');
            }
            button.classList.add('is-busy');
            button.disabled = false;
            if (spin) {
                spin.hidden = false;
            }
            if (label) {
                label.textContent = i18n.saving || 'Saving…';
            }
            return;
        }
        if (form) {
            form.removeAttribute('data-busy');
            form.removeAttribute('aria-busy');
        }
        button.classList.remove('is-busy');
        if (spin) {
            spin.hidden = true;
        }
        if (label) {
            label.textContent = i18n.saveChanges || 'Save changes';
        }
    }

    function applySavedIdentity(data) {
        if (!data) {
            return;
        }
        var welcome = document.getElementById('mjb-cd-welcome');
        if (welcome && data.welcome) {
            welcome.textContent = data.welcome;
        }
        var name = document.getElementById('mjb-cd-me-name');
        if (name && data.display) {
            name.textContent = data.display;
        }
    }

    function commitSavedPhoto(photo) {
        var input = document.getElementById('mjb_photo');
        if (input) {
            input.value = '';
        }
        var pending = document.getElementById('mjb-cd-photo-pending');
        if (pending) {
            pending.hidden = true;
            pending.textContent = '';
        }
        if (root._photoObjectUrl) {
            URL.revokeObjectURL(root._photoObjectUrl);
            root._photoObjectUrl = '';
        }
        setPhotoUploading(false);
        if (!photo) {
            return;
        }
        var saved = document.getElementById('mjb-cd-photo-saved');
        var img = document.getElementById('mjb-cd-photo-img');
        var glyph = document.getElementById('mjb-cd-photo-glyph');
        var photoName = document.getElementById('mjb-cd-photo-name');
        var meta = document.getElementById('mjb-cd-photo-meta');
        var download = document.getElementById('mjb-cd-photo-download');
        if (saved) {
            saved.hidden = false;
        }
        if (img) {
            if (photo.url) {
                img.src = photo.url;
                img.hidden = false;
            } else {
                img.hidden = true;
                img.removeAttribute('src');
            }
        }
        if (glyph) {
            glyph.hidden = !!photo.url;
        }
        if (photoName) {
            photoName.textContent = photo.name || '';
        }
        if (meta) {
            meta.textContent = photo.meta || '';
        }
        if (download && photo.url) {
            download.href = photo.url;
            download.hidden = false;
            if (photo.name) {
                download.setAttribute('download', photo.name);
            }
        }
        var hint = document.getElementById('mjb-cd-photo-hint');
        if (hint) {
            var nextHint = hint.getAttribute('data-replace') || hint.getAttribute('data-default') || '';
            if (nextHint) {
                hint.textContent = nextHint;
                hint.setAttribute('data-default', nextHint);
            }
        }
        var avatar = document.getElementById('mjb-cd-avatar');
        if (avatar && photo.url) {
            var avatarImg = avatar.querySelector('img');
            if (!avatarImg) {
                avatar.textContent = '';
                avatarImg = document.createElement('img');
                avatarImg.alt = '';
                avatar.appendChild(avatarImg);
            }
            avatarImg.src = photo.url;
        }
    }

    function saveProfile(form) {
        if (!form || form.getAttribute('data-busy') === '1') {
            return;
        }
        var dash = window.mjbCandidateDashboard || {};
        var save = document.getElementById('mjb-cd-save');
        var discard = document.getElementById('mjb-cd-discard');
        var body = new FormData(form);
        body.set('action', 'mjb_candidate_profile');
        setSaveBusy(save, form, true);
        if (discard) {
            discard.disabled = true;
        }
        fetch(dash.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            setSaveBusy(save, form, false);
            if (!payload || !payload.success) {
                var failed = payload && payload.data && payload.data.message;
                setDirty(true);
                showDashboardNotice(failed || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                return;
            }
            var data = payload.data || {};
            syncFieldDefaults(form);
            applySavedIdentity(data);
            if (data.photo) {
                commitSavedPhoto(data.photo);
            }
            setDirty(false);
            var status = document.getElementById('mjb-cd-save-text');
            if (status) {
                status.textContent = i18n.saved || 'All changes saved';
            }
            showDashboardNotice(data.message || i18n.saved || 'All changes saved', 'success');
        }).catch(function () {
            setSaveBusy(save, form, false);
            setDirty(true);
            showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
        });
    }

    function countBio() {
        var bio = document.getElementById('mjb_bio');
        var count = document.getElementById('mjb-cd-bio-count');
        if (!bio || !count) {
            return;
        }
        var max = bio.getAttribute('maxlength') || '500';
        count.textContent = String(bio.value || '').length + ' / ' + max;
    }

    function bindHome() {
        var form = document.getElementById('mjb-cd-profile-form');
        if (form && form.getAttribute('data-bound') !== '1') {
            form.setAttribute('data-bound', '1');
            setDirty(false);
            form.addEventListener('input', function () {
                setDirty(true);
                countBio();
                score();
            });
            form.addEventListener('change', function (event) {
                if (event.target && event.target.id === 'mjb_photo') {
                    return;
                }
                if (event.target && event.target.id === 'mjb_is_public') {
                    setVisibility();
                }
                setDirty(true);
                score();
            });
            form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    setDirty(false);
                    var status = document.getElementById('mjb-cd-save-text');
                    if (status) {
                        status.textContent = i18n.discarded || 'Changes discarded';
                    }
                    countBio();
                    score();
                    setVisibility();
                    restoreSavedPhoto();
                }, 0);
            });
            window.addEventListener('beforeunload', function (event) {
                var bar = document.getElementById('mjb-cd-savebar');
                if (bar && bar.classList.contains('is-dirty')) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            form.addEventListener('submit', function (event) {
                var dash = window.mjbCandidateDashboard || {};
                if (!dash.ajaxUrl) {
                    return;
                }
                event.preventDefault();
                saveProfile(form);
            });
        }
        var resumeForm = document.querySelector('.mjb-cd-resume-form');
        if (resumeForm && resumeForm.getAttribute('data-ajax') !== '1') {
            resumeForm.setAttribute('data-ajax', '1');
            resumeForm.addEventListener('submit', function (event) {
                var dash = window.mjbCandidateDashboard || {};
                var resumeInput = document.getElementById('mjb_resume');
                if (!dash.ajaxUrl || !resumeInput || !resumeInput.files || !resumeInput.files[0]) {
                    return;
                }
                event.preventDefault();
                if (resumeForm.getAttribute('data-busy') === '1') {
                    return;
                }
                resumeForm.setAttribute('data-busy', '1');
                var body = new FormData(resumeForm);
                body.set('action', 'mjb_upload_resume');
                body.set('mjb_upload_resume', '1');
                fetch(dash.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    resumeForm.removeAttribute('data-busy');
                    var data = payload && payload.data ? payload.data : {};
                    if (!payload || !payload.success) {
                        showDashboardNotice(data.message || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                        return;
                    }
                    var fileInput = document.getElementById('mjb_resume');
                    if (fileInput) {
                        fileInput.value = '';
                    }
                    var name = resumeForm.parentNode ? resumeForm.parentNode.querySelector('.mjb-cd-file-txt b') : null;
                    if (name && data.name) {
                        name.textContent = data.name;
                    }
                    var downloadLink = document.getElementById('mjb-cd-cv-download');
                    var viewBtn = document.getElementById('mjb-cd-cv-view');
                    if (data.resume_url && downloadLink) {
                        downloadLink.href = data.resume_url;
                    }
                    if (data.resume_url && viewBtn) {
                        var preview = new URL(data.resume_url, window.location.href);
                        preview.searchParams.set('mjb_disposition', 'inline');
                        viewBtn.setAttribute('data-mjb-cv-url', preview.toString());
                        if (data.name) {
                            viewBtn.setAttribute('data-mjb-cv-name', data.name);
                        }
                        viewBtn.setAttribute('data-mjb-cv-kind', String(data.ext || '').toLowerCase() === 'pdf' ? 'pdf' : 'word');
                    }
                    var deleteName = document.getElementById('mjb-cd-cv-delete-name');
                    if (deleteName && data.name) {
                        deleteName.textContent = data.name;
                    }
                    setCvOnFile(true);
                    showDashboardNotice(data.message, 'success');
                }).catch(function () {
                    resumeForm.removeAttribute('data-busy');
                    showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                });
            });
        }
        var photoDelete = document.querySelector('.mjb-cd-photo-delete');
        if (photoDelete && photoDelete.getAttribute('data-ajax') !== '1') {
            photoDelete.setAttribute('data-ajax', '1');
            photoDelete.addEventListener('submit', function (event) {
                var dash = window.mjbCandidateDashboard || {};
                if (!dash.ajaxUrl) {
                    return;
                }
                event.preventDefault();
                var body = new FormData(photoDelete);
                body.set('action', 'mjb_delete_photo');
                body.set('mjb_delete_photo', '1');
                fetch(dash.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    var data = payload && payload.data ? payload.data : {};
                    if (!payload || !payload.success) {
                        showDashboardNotice(data.message || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                        return;
                    }
                    var saved = document.getElementById('mjb-cd-photo-saved');
                    if (saved) {
                        saved.hidden = true;
                    }
                    var photoImg = document.getElementById('mjb-cd-photo-img');
                    if (photoImg) {
                        photoImg.hidden = true;
                        photoImg.removeAttribute('src');
                    }
                    var photoName = document.getElementById('mjb-cd-photo-name');
                    if (photoName) {
                        photoName.textContent = '';
                    }
                    var avatar = document.getElementById('mjb-cd-avatar');
                    if (avatar) {
                        avatar.textContent = data.initials || '';
                    }
                    showDashboardNotice(data.message, 'success');
                }).catch(function () {
                    showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                });
            });
        }
        bindResume();
        bindPhoto();
        if (typeof window.mjbPhoneFieldInit === 'function') {
            window.mjbPhoneFieldInit();
        }
        countBio();
        score();
        setVisibility();
        observeSections();
    }

    var fileDragGuarded = false;

    function dragHasFiles(event) {
        var types = event.dataTransfer && event.dataTransfer.types;
        if (!types) {
            return false;
        }
        for (var i = 0; i < types.length; i++) {
            if (types[i] === 'Files') {
                return true;
            }
        }
        return false;
    }

    function guardFileDrag() {
        if (fileDragGuarded) {
            return;
        }
        fileDragGuarded = true;
        document.addEventListener('dragover', function (event) {
            if (dragHasFiles(event)) {
                event.preventDefault();
            }
        });
        document.addEventListener('drop', function (event) {
            if (dragHasFiles(event)) {
                event.preventDefault();
            }
        });
    }

    function droppedFile(event) {
        var files = event.dataTransfer && event.dataTransfer.files;
        return files && files.length ? files[0] : null;
    }

    function assignFile(input, file) {
        var transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function bindFileHover(drop) {
        ['dragenter', 'dragover'].forEach(function (type) {
            drop.addEventListener(type, function (event) {
                event.preventDefault();
                event.stopPropagation();
                drop.classList.add('is-over');
            });
        });
        drop.addEventListener('dragleave', function (event) {
            event.preventDefault();
            if (event.relatedTarget && drop.contains(event.relatedTarget)) {
                return;
            }
            drop.classList.remove('is-over');
        });
    }

    function bindDropUpload(dropId, inputId, submitName) {
        var drop = document.getElementById(dropId);
        var input = document.getElementById(inputId);
        var form = input ? input.form : null;
        guardFileDrag();
        if (drop && drop.getAttribute('data-bound') !== '1') {
            drop.setAttribute('data-bound', '1');
            bindFileHover(drop);
            drop.addEventListener('drop', function (event) {
                event.preventDefault();
                event.stopPropagation();
                drop.classList.remove('is-over');
                var file = droppedFile(event);
                if (!file || !input) {
                    return;
                }
                assignFile(input, file);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }, true);
        }
        if (input && form && input.getAttribute('data-bound') !== '1') {
            input.setAttribute('data-bound', '1');
            input.addEventListener('change', function () {
                if (input.files && input.files[0]) {
                    var submitter = form.querySelector('[name="' + submitName + '"]');
                    if (typeof form.requestSubmit === 'function' && submitter) {
                        form.requestSubmit(submitter);
                    } else {
                        if (!form.querySelector('input[type="hidden"][name="' + submitName + '"]')) {
                            var hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = submitName;
                            hidden.value = '1';
                            form.appendChild(hidden);
                        }
                        form.submit();
                    }
                }
            });
        }
    }

    function photoKind(file) {
        var type = file && file.type ? file.type : '';
        if (type === 'image/jpeg') {
            return 'JPG';
        }
        if (type === 'image/png') {
            return 'PNG';
        }
        if (type === 'image/webp') {
            return 'WEBP';
        }
        var name = file && file.name ? file.name : '';
        var ext = name.indexOf('.') === -1 ? '' : name.split('.').pop().toLowerCase();
        if (ext === 'jpg' || ext === 'jpeg') {
            return 'JPG';
        }
        if (ext === 'png') {
            return 'PNG';
        }
        if (ext === 'webp') {
            return 'WEBP';
        }
        return '';
    }

    function formatBytes(bytes) {
        var size = bytes || 0;
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1048576) {
            return Math.max(1, Math.round(size / 1024)) + ' KB';
        }
        return (Math.round((size / 1048576) * 10) / 10) + ' MB';
    }

    function setPhotoUploading(on) {
        var drop = document.getElementById('mjb-cd-photo-drop');
        if (!drop) {
            return;
        }
        var status = drop.querySelector('.mjb-cd-drop-status');
        drop.classList.toggle('is-uploading', on);
        drop.setAttribute('aria-busy', on ? 'true' : 'false');
        if (status) {
            status.hidden = !on;
        }
    }

    function restoreSavedPhoto() {
        var pending = document.getElementById('mjb-cd-photo-pending');
        var saved = document.getElementById('mjb-cd-photo-saved');
        var hint = document.getElementById('mjb-cd-photo-hint');
        if (pending) {
            pending.hidden = true;
            pending.textContent = '';
        }
        if (root._photoObjectUrl) {
            URL.revokeObjectURL(root._photoObjectUrl);
            root._photoObjectUrl = '';
        }
        if (saved) {
            var savedImg = document.getElementById('mjb-cd-photo-img');
            var savedName = document.getElementById('mjb-cd-photo-name');
            var hasSaved = (savedImg && savedImg.getAttribute('src')) || (savedName && savedName.textContent.trim() !== '');
            saved.hidden = !hasSaved;
        }
        if (hint) {
            hint.textContent = hint.getAttribute('data-default') || hint.textContent;
        }
        setPhotoUploading(false);
    }

    function profileHasOtherEdits() {
        var form = document.getElementById('mjb-cd-profile-form');
        if (!form) {
            return false;
        }
        var fields = form.querySelectorAll('input, textarea, select');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.type === 'hidden' || field.type === 'file' || field.disabled) {
                continue;
            }
            if ((field.type === 'checkbox' || field.type === 'radio') && field.checked !== field.defaultChecked) {
                return true;
            }
            if (field.type !== 'checkbox' && field.type !== 'radio' && field.value !== field.defaultValue) {
                return true;
            }
        }
        return false;
    }

    function showPendingPhoto(file, kind) {
        var pending = document.getElementById('mjb-cd-photo-pending');
        var saved = document.getElementById('mjb-cd-photo-saved');
        var template = document.getElementById('mjb-cd-photo-row');
        var hint = document.getElementById('mjb-cd-photo-hint');
        if (!pending || !template || !template.content) {
            return;
        }
        pending.textContent = '';
        pending.appendChild(template.content.cloneNode(true));
        var name = pending.querySelector('[data-photo-name]');
        var preview = pending.querySelector('[data-photo-preview]');
        var glyph = pending.querySelector('[data-photo-glyph]');
        var meta = pending.querySelector('[data-photo-meta]');
        var label = file.name || 'Photo';
        if (name) {
            name.textContent = label;
        }
        if (preview && typeof URL !== 'undefined' && URL.createObjectURL) {
            if (root._photoObjectUrl) {
                URL.revokeObjectURL(root._photoObjectUrl);
            }
            root._photoObjectUrl = URL.createObjectURL(file);
            preview.src = root._photoObjectUrl;
            preview.hidden = false;
            if (glyph) {
                glyph.hidden = true;
            }
        }
        if (meta) {
            meta.textContent = kind + ' · ' + formatBytes(file.size) + ' · ' + (i18n.photoReady || 'Ready to save');
        }
        pending.hidden = false;
        if (saved) {
            saved.hidden = true;
        }
        if (hint) {
            hint.textContent = hint.getAttribute('data-default') || hint.textContent;
        }
        var clear = pending.querySelector('#mjb-cd-photo-clear');
        if (clear) {
            clear.addEventListener('click', function () {
                var input = document.getElementById('mjb_photo');
                if (input) {
                    input.value = '';
                }
                restoreSavedPhoto();
                setDirty(profileHasOtherEdits());
                if (!profileHasOtherEdits()) {
                    var status = document.getElementById('mjb-cd-save-text');
                    if (status) {
                        status.textContent = i18n.saved || 'All changes saved';
                    }
                }
            });
        }
    }

    function stagePhoto(file) {
        var input = document.getElementById('mjb_photo');
        var hint = document.getElementById('mjb-cd-photo-hint');
        var drop = document.getElementById('mjb-cd-photo-drop');
        if (drop && drop.classList.contains('is-uploading')) {
            return;
        }
        var kind = photoKind(file);
        if (!kind) {
            if (input) {
                input.value = '';
            }
            if (hint) {
                hint.textContent = i18n.photoInvalid || 'Please choose a JPG, PNG, or WebP image.';
            }
            return;
        }
        if (file.size > 2097152) {
            if (input) {
                input.value = '';
            }
            if (hint) {
                hint.textContent = i18n.photoLarge || 'That photo is larger than 2 MB.';
            }
            return;
        }
        if (input && (!input.files || !input.files[0] || input.files[0] !== file)) {
            assignFile(input, file);
        }
        setPhotoUploading(true);
        window.setTimeout(function () {
            showPendingPhoto(file, kind);
            setPhotoUploading(false);
            setDirty(true);
        }, 700);
    }

    function bindResume() {
        bindDropUpload('mjb-cd-drop', 'mjb_resume', 'mjb_upload_resume');
    }

    function bindPhoto() {
        var drop = document.getElementById('mjb-cd-photo-drop');
        var input = document.getElementById('mjb_photo');
        if (!drop || !input || drop.getAttribute('data-bound') === '1') {
            return;
        }
        drop.setAttribute('data-bound', '1');
        guardFileDrag();
        bindFileHover(drop);
        drop.addEventListener('click', function () {
            input.click();
        });
        drop.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });
        drop.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            drop.classList.remove('is-over');
            if (drop.classList.contains('is-uploading')) {
                return;
            }
            var file = droppedFile(event);
            if (file) {
                stagePhoto(file);
            }
        }, true);
        if (input.getAttribute('data-bound') !== '1') {
            input.setAttribute('data-bound', '1');
            input.addEventListener('change', function () {
                if (input.files && input.files[0]) {
                    stagePhoto(input.files[0]);
                }
            });
        }
    }

    var toggle = document.getElementById('mjb-cd-nav-toggle');
    var nav = document.getElementById('mjb-cd-nav');
    function setNav(open) {
        if (!toggle || !nav) {
            return;
        }
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        nav.classList.toggle('is-open', open);
    }
    if (toggle && nav) {
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setNav(toggle.getAttribute('aria-expanded') !== 'true');
        });
        document.addEventListener('click', function (event) {
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                setNav(false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                setNav(false);
                toggle.focus();
            }
        });
    }

    root.addEventListener('click', function (event) {
        var jump = event.target.closest('[data-mjb-jump]');
        if (jump) {
            event.preventDefault();
            activateLayer('home', jump.getAttribute('data-mjb-jump') || 'mjb-cd-profile');
            return;
        }
        var tab = event.target.closest('#mjb-cd-nav .mjb-cd-tab');
        if (tab) {
            event.preventDefault();
            setNav(false);
            activateLayer(tab.getAttribute('data-layer') || 'home', tab.getAttribute('data-section') || '');
        }
    });

    var tabs = [].slice.call(root.querySelectorAll('#mjb-cd-nav .mjb-cd-tab'));
    function setCurrent(id) {
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-section') === id || tab.getAttribute('href') === '#' + id;
            tab.classList.toggle('is-active', on);
            if (on) {
                tab.setAttribute('aria-current', 'page');
            } else {
                tab.removeAttribute('aria-current');
            }
        });
    }
    var sectionObserver = null;
    function observeSections() {
        if (!('IntersectionObserver' in window)) {
            return;
        }
        if (sectionObserver) {
            sectionObserver.disconnect();
        }
        sectionObserver = new IntersectionObserver(function (entries) {
            var stage = document.getElementById('mjb-cd-stage');
            if (stage && stage.getAttribute('data-active') !== 'home') {
                return;
            }
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    setCurrent(entry.target.id);
                }
            });
        }, { rootMargin: '-35% 0px -55% 0px' });
        tabs.forEach(function (tab) {
            if (tab.getAttribute('data-layer') !== 'home') {
                return;
            }
            var section = document.getElementById(tab.getAttribute('data-section') || '');
            if (section) {
                sectionObserver.observe(section);
            }
        });
    }

    function slidePageToStatus() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    }
    function dismissStatusBox(box) {
        if (!box || box.getAttribute('data-dismissing') === '1') {
            return;
        }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) {
            box.remove();
            return;
        }
        box.setAttribute('data-dismissing', '1');
        box.style.transition = 'none';
        box.style.boxSizing = 'border-box';
        box.style.overflow = 'hidden';
        box.style.height = box.getBoundingClientRect().height + 'px';
        window.requestAnimationFrame(function () {
            box.style.transition = 'height 0.32s cubic-bezier(0.16, 1, 0.3, 1), margin 0.32s cubic-bezier(0.16, 1, 0.3, 1), padding 0.32s cubic-bezier(0.16, 1, 0.3, 1), border-width 0.32s cubic-bezier(0.16, 1, 0.3, 1)';
            box.style.height = '0px';
            box.style.marginTop = '0px';
            box.style.marginBottom = '0px';
            box.style.paddingTop = '0px';
            box.style.paddingBottom = '0px';
            box.style.borderTopWidth = '0px';
            box.style.borderBottomWidth = '0px';
        });
        var finished = false;
        function done(event) {
            if (finished) {
                return;
            }
            if (event && event.propertyName && event.propertyName !== 'height') {
                return;
            }
            finished = true;
            box.remove();
        }
        box.addEventListener('transitionend', done);
        window.setTimeout(function () {
            done();
        }, 420);
    }
    function noticeCloseIcon() {
        return '<svg class="mjb-icon mjb-icon--x" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    }
    function bindNotice(notice) {
        if (!notice || notice.querySelector('.mjb-cd-notice-close')) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'mjb-cd-notice-close';
        button.setAttribute('aria-label', i18n.dismiss || 'Dismiss');
        button.innerHTML = noticeCloseIcon();
        button.addEventListener('click', function () {
            dismissStatusBox(notice);
        });
        notice.appendChild(button);
    }
    function showDashboardNotice(message, type) {
        var stage = document.getElementById('mjb-cd-stage');
        if (!stage || !message) {
            return;
        }
        root.querySelectorAll(':scope > .mjb-message').forEach(function (notice) {
            notice.remove();
        });
        var notice = document.createElement('div');
        var isError = type === 'error';
        notice.className = 'mjb-message ' + (isError ? 'error' : 'success');
        notice.setAttribute('role', isError ? 'alert' : 'status');
        notice.textContent = message;
        stage.parentNode.insertBefore(notice, stage);
        bindNotice(notice);
        slidePageToStatus();
    }
    window.mjbShowDashboardNotice = showDashboardNotice;
    root.querySelectorAll(':scope > .mjb-message').forEach(bindNotice);

    var tip = document.createElement('div');
    tip.id = 'mjb-cd-tip';
    tip.setAttribute('role', 'tooltip');
    tip.hidden = true;
    document.body.appendChild(tip);
    var tipTarget = null;
    function placeTip(el) {
        tip.hidden = false;
        tip.textContent = el.getAttribute('data-tip') || '';
        var rect = el.getBoundingClientRect();
        var width = tip.offsetWidth;
        var height = tip.offsetHeight;
        var left = rect.left + rect.width / 2 - width / 2;
        var top = rect.top - height - 9;
        if (el.getAttribute('data-tip-pos') === 'left') {
            left = rect.left - width - 9;
            top = rect.top + rect.height / 2 - height / 2;
        }
        if (top < 8) {
            top = rect.bottom + 9;
        }
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }
    function hideTip() {
        tipTarget = null;
        tip.hidden = true;
    }
    root.addEventListener('mouseover', function (event) {
        var el = event.target.closest('[data-tip]');
        if (el && el !== tipTarget) {
            tipTarget = el;
            placeTip(el);
        }
    });
    root.addEventListener('mouseout', function (event) {
        var el = event.target.closest('[data-tip]');
        if (el && el === tipTarget) {
            hideTip();
        }
    });
    root.addEventListener('focusin', function (event) {
        var el = event.target.closest('[data-tip]');
        if (el) {
            tipTarget = el;
            placeTip(el);
        }
    });
    root.addEventListener('focusout', hideTip);

    function bindAccount() {
        var accountRoot = document.getElementById('mjb-cd-layer-account');
        if (!accountRoot || accountRoot.getAttribute('data-bound') === '1' || !document.getElementById('mjb-cd-account-form')) {
            return;
        }
        accountRoot.setAttribute('data-bound', '1');

    var accountForm = document.getElementById('mjb-cd-account-form');
    var accountBar = document.getElementById('mjb-cd-account-savebar');
    var accountSave = document.getElementById('mjb-cd-account-save');
    var accountDiscard = document.getElementById('mjb-cd-account-discard');
    var accountText = document.getElementById('mjb-cd-account-save-text');
    var alertHint = document.getElementById('mjb-cd-alert-hint');
    function accountSnapshot() {
        if (!accountForm) {
            return '';
        }
        var parts = [];
        accountForm.querySelectorAll('input').forEach(function (input) {
            if (input.type === 'hidden' || input.type === 'submit' || input.name === 'mjb_account_nonce') {
                return;
            }
            if (input.type === 'checkbox' || input.type === 'radio') {
                parts.push(input.name + '=' + input.value + ':' + (input.checked ? '1' : '0'));
            } else {
                parts.push(input.name + '=' + input.value);
            }
        });
        return parts.join('|');
    }
    var accountClean = accountSnapshot();
    function syncAccountBar() {
        if (!accountBar) {
            return;
        }
        var dirty = accountSnapshot() !== accountClean;
        accountBar.classList.toggle('is-dirty', dirty);
        if (accountSave) {
            accountSave.disabled = !dirty;
        }
        if (accountDiscard) {
            accountDiscard.disabled = !dirty;
        }
        if (accountText) {
            accountText.textContent = dirty ? (i18n.unsaved || 'You have unsaved changes') : (i18n.saved || 'All changes saved');
        }
        if (alertHint && accountForm) {
            var picked = accountForm.querySelector('input[name="mjb_alert_frequency"]:checked');
            var hints = { off: i18n.alertOff, daily: i18n.alertDaily, weekly: i18n.alertWeekly };
            if (picked && hints[picked.value]) {
                alertHint.textContent = hints[picked.value];
            }
        }
    }
    if (accountForm) {
        accountForm.addEventListener('input', syncAccountBar);
        accountForm.addEventListener('change', syncAccountBar);
        accountForm.addEventListener('reset', function () {
            window.setTimeout(syncAccountBar, 0);
        });
    }
    var emailInput = document.getElementById('mjb_account_email');
    var emailChip = document.getElementById('mjb-cd-email-chip');
    if (emailInput && emailChip && !emailChip.hasAttribute('hidden')) {
        emailInput.addEventListener('input', function () {
            var current = (emailInput.getAttribute('data-current-email') || '').toLowerCase();
            if (emailInput.value.trim().toLowerCase() === current) {
                emailChip.removeAttribute('hidden');
            } else {
                emailChip.setAttribute('hidden', '');
            }
        });
    }
    function saveAccount() {
        if (!accountForm || accountForm.getAttribute('data-busy') === '1') {
            return;
        }
        var dash = window.mjbCandidateDashboard || {};
        var pendingAction = accountForm.getAttribute('data-pending-action') || 'save';
        var body = new FormData(accountForm);
        body.set('action', 'mjb_candidate_account');
        body.set('mjb_account_action', pendingAction);
        if (pendingAction === 'save') {
            setSaveBusy(accountSave, accountForm, true);
            if (accountDiscard) {
                accountDiscard.disabled = true;
            }
        } else {
            accountForm.setAttribute('data-busy', '1');
        }
        fetch(dash.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (pendingAction === 'save') {
                setSaveBusy(accountSave, accountForm, false);
            } else {
                accountForm.removeAttribute('data-busy');
            }
            if (!payload || !payload.success) {
                var failed = payload && payload.data && payload.data.message;
                syncAccountBar();
                showDashboardNotice(failed || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                return;
            }
            var data = payload.data || {};
            if (data.code === 'success_email_cancelled') {
                var cancelledBox = document.getElementById('mjb-cd-email-pending');
                if (cancelledBox) {
                    cancelledBox.hidden = true;
                }
                if (emailChip) {
                    emailChip.removeAttribute('hidden');
                }
            }
            if (data.code === 'success_email_pending' && emailInput) {
                var currentEmail = emailInput.getAttribute('data-current-email') || '';
                if (currentEmail) {
                    emailInput.value = currentEmail;
                }
                var pendingBox = document.getElementById('mjb-cd-email-pending');
                var pendingText = document.getElementById('mjb-cd-email-pending-text');
                if (pendingText && data.pending_html) {
                    pendingText.innerHTML = data.pending_html;
                }
                if (pendingBox) {
                    pendingBox.hidden = false;
                }
                if (emailChip) {
                    emailChip.setAttribute('hidden', '');
                }
            }
            syncFieldDefaults(accountForm);
            accountClean = accountSnapshot();
            syncAccountBar();
            showDashboardNotice(data.message || i18n.saved || 'All changes saved', 'success');
        }).catch(function () {
            if (pendingAction === 'save') {
                setSaveBusy(accountSave, accountForm, false);
            } else {
                accountForm.removeAttribute('data-busy');
            }
            syncAccountBar();
            showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
        });
    }
    if (accountForm) {
        accountForm.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            var action = submitter && submitter.name === 'mjb_account_action' ? submitter.value : '';
            if (action && action !== 'save' && action !== 'resend_email' && action !== 'cancel_email') {
                return;
            }
            var dash = window.mjbCandidateDashboard || {};
            if (!dash.ajaxUrl) {
                return;
            }
            event.preventDefault();
            accountForm.setAttribute('data-pending-action', action || 'save');
            saveAccount();
        });
    }

    root.querySelectorAll('.mjb-cd-reveal').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-for'));
            if (!field) {
                return;
            }
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? (i18n.hidePassword || 'Hide password') : (i18n.showPassword || 'Show password'));
            var on = button.querySelector('.mjb-cd-reveal-show');
            var off = button.querySelector('.mjb-cd-reveal-hide');
            if (on && off) {
                on.hidden = show;
                off.hidden = !show;
            }
        });
    });

    var currentPw = document.getElementById('mjb_current_password');
    var currentPwTyped = document.getElementById('mjb_current_password_typed');
    var newPw = document.getElementById('mjb_new_password');
    var passwordForm = document.getElementById('mjb-cd-password-form');
    var meter = document.getElementById('mjb-cd-meter');
    var pwWord = document.getElementById('mjb-cd-pw-word');
    var pwSave = document.getElementById('mjb-cd-password-save');
    function passwordScore(value) {
        var score = 0;
        if (value.length >= 12) {
            score += 1;
        }
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) {
            score += 1;
        }
        if (/\d/.test(value)) {
            score += 1;
        }
        if (/[^A-Za-z0-9]/.test(value)) {
            score += 1;
        }
        return score;
    }
    function passwordBusy() {
        return !!(passwordForm && passwordForm.getAttribute('data-busy') === '1');
    }
    function syncPassword() {
        if (!newPw) {
            return;
        }
        var value = newPw.value || '';
        var score = value === '' ? 0 : passwordScore(value);
        if (meter) {
            meter.setAttribute('data-s', String(score));
        }
        if (pwWord) {
            var words = ['—', i18n.weak || 'Weak', i18n.fair || 'Fair', i18n.good || 'Good', i18n.strong || 'Strong'];
            pwWord.textContent = words[score] || words[0];
        }
        if (pwSave && !passwordBusy()) {
            pwSave.disabled = !currentPw || currentPw.value === '' || value.length < 12;
        }
    }
    function rememberCurrentPassword() {
        if (currentPwTyped && currentPw) {
            currentPwTyped.value = currentPw.value;
        }
    }
    function passwordNodes(which) {
        return {
            field: document.getElementById(which === 'current' ? 'mjb-cd-current-password-field' : 'mjb-cd-new-password-field'),
            err: document.getElementById(which === 'current' ? 'mjb-cd-current-password-err' : 'mjb-cd-new-password-err'),
            input: which === 'current' ? currentPw : newPw
        };
    }
    function setFieldError(which, message) {
        var nodes = passwordNodes(which);
        if (nodes.field) {
            nodes.field.classList.add('is-invalid');
        }
        if (nodes.err) {
            nodes.err.hidden = false;
            nodes.err.textContent = message;
        }
        if (nodes.input) {
            nodes.input.setAttribute('aria-invalid', 'true');
        }
    }
    function clearFieldError(which) {
        var nodes = passwordNodes(which);
        if (nodes.field) {
            nodes.field.classList.remove('is-invalid');
        }
        if (nodes.err) {
            nodes.err.hidden = true;
            nodes.err.textContent = '';
        }
        if (nodes.input) {
            nodes.input.removeAttribute('aria-invalid');
        }
    }
    function passwordStatus() {
        return document.getElementById('mjb-cd-password-status');
    }
    function clearPasswordFeedback() {
        clearFieldError('current');
        clearFieldError('new');
        var status = passwordStatus();
        if (status) {
            status.hidden = true;
            status.textContent = '';
            status.classList.remove('is-error');
        }
    }
    function showPasswordStatus(message, isError) {
        var status = passwordStatus();
        if (!status) {
            return;
        }
        status.hidden = false;
        status.textContent = message;
        status.classList.toggle('is-error', !!isError);
    }
    function setPasswordBusy(busy) {
        if (!passwordForm || !pwSave) {
            return;
        }
        var spin = pwSave.querySelector('.mjb-cd-btn-spin');
        var label = pwSave.querySelector('.mjb-cd-password-label');
        if (busy) {
            passwordForm.setAttribute('data-busy', '1');
            passwordForm.setAttribute('aria-busy', 'true');
            pwSave.classList.add('is-busy');
            pwSave.disabled = false;
            if (spin) {
                spin.hidden = false;
            }
            if (label) {
                label.textContent = i18n.passwordUpdating || 'Updating…';
            }
            return;
        }
        passwordForm.removeAttribute('data-busy');
        passwordForm.removeAttribute('aria-busy');
        pwSave.classList.remove('is-busy');
        if (spin) {
            spin.hidden = true;
        }
        if (label) {
            label.textContent = i18n.passwordUpdate || 'Update password';
        }
        syncPassword();
    }
    function passwordQuiet() {
        return !!(passwordForm && passwordForm.getAttribute('data-quiet') === '1');
    }
    function releasePasswordQuiet() {
        if (passwordForm) {
            passwordForm.removeAttribute('data-quiet');
        }
    }
    function clearSavedPasswordFields() {
        if (passwordForm) {
            passwordForm.setAttribute('data-quiet', '1');
        }
        if (currentPw) {
            currentPw.removeAttribute('data-touched');
            currentPw.value = '';
        }
        if (newPw) {
            newPw.removeAttribute('data-touched');
            newPw.value = '';
        }
        if (currentPwTyped) {
            currentPwTyped.value = '';
        }
        clearFieldError('current');
        clearFieldError('new');
        window.setTimeout(function () {
            if (currentPw) {
                currentPw.removeAttribute('data-touched');
            }
            if (newPw) {
                newPw.removeAttribute('data-touched');
            }
            clearFieldError('current');
            clearFieldError('new');
        }, 0);
    }
    function finishPassword(payload) {
        var data = payload && payload.data ? payload.data : {};
        var code = data.code || '';
        if (payload && payload.success) {
            clearSavedPasswordFields();
            var changed = document.getElementById('mjb-cd-password-changed');
            if (changed && data.changed) {
                changed.hidden = false;
                changed.textContent = data.changed;
            }
            setPasswordBusy(false);
            var status = passwordStatus();
            if (status) {
                status.hidden = true;
                status.textContent = '';
                status.classList.remove('is-error');
            }
            showDashboardNotice(data.message || i18n.passwordUpdated || 'Password updated. You are still signed in on this device.', 'success');
            return;
        }
        setPasswordBusy(false);
        var message = data.message || i18n.passwordFailed || 'The password could not be updated. Try again.';
        if (code === 'error_password_short') {
            setFieldError('new', message);
            if (newPw) {
                newPw.focus();
            }
            return;
        }
        if (code === 'error_current_password' || code === '') {
            setFieldError('current', message);
            if (currentPw) {
                currentPw.focus();
            }
            return;
        }
        showPasswordStatus(message, true);
    }
    function clearStalePasswordNotice() {
        var params;
        try {
            params = new URL(window.location.href).searchParams;
        } catch (error) {
            return;
        }
        var code = params.get('mjb_notice') || '';
        if (code !== 'error_current_password' && code !== 'error_password_short' && code !== 'success_password') {
            return;
        }
        params.delete('mjb_notice');
        var next = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', next);
        }
        var banner = root.querySelector('.mjb-message');
        if (banner) {
            banner.remove();
        }
    }
    if (passwordForm) {
        clearStalePasswordNotice();
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (passwordBusy()) {
                return;
            }
            rememberCurrentPassword();
            clearPasswordFeedback();
            var currentValue = currentPw ? String(currentPw.value || '') : '';
            var newValue = newPw ? String(newPw.value || '').trim() : '';
            var invalid = false;
            if (currentValue.trim() === '') {
                setFieldError('current', i18n.passwordRequired || 'Enter your current password.');
                invalid = true;
            }
            if (newValue.length < 12) {
                setFieldError('new', i18n.passwordShort || 'Use 12 or more characters for your new password.');
                invalid = true;
            }
            if (invalid) {
                if (currentValue.trim() === '' && currentPw) {
                    currentPw.focus();
                } else if (newPw) {
                    newPw.focus();
                }
                return;
            }
            var dash = window.mjbCandidateDashboard || {};
            if (!dash.ajaxUrl) {
                showPasswordStatus(i18n.passwordFailed || 'The password could not be updated. Try again.', true);
                return;
            }
            var body = new FormData(passwordForm);
            body.set('action', 'mjb_candidate_password');
            setPasswordBusy(true);
            fetch(dash.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (response) {
                return response.json().then(function (payload) {
                    finishPassword(payload);
                });
            }).catch(function () {
                setPasswordBusy(false);
                showPasswordStatus(i18n.passwordFailed || 'The password could not be updated. Try again.', true);
            });
        });
    }
    if (currentPw) {
        currentPw.addEventListener('input', function (event) {
            rememberCurrentPassword();
            var value = String(currentPw.value || '').trim();
            if (event.isTrusted && value !== '') {
                releasePasswordQuiet();
                currentPw.setAttribute('data-touched', '1');
            }
            if (value !== '') {
                clearFieldError('current');
            }
            syncPassword();
        });
        currentPw.addEventListener('change', rememberCurrentPassword);
        currentPw.addEventListener('blur', function () {
            if (passwordQuiet() || currentPw.getAttribute('data-touched') !== '1') {
                return;
            }
            if (String(currentPw.value || '').trim() === '') {
                setFieldError('current', i18n.passwordRequired || 'Enter your current password.');
            }
        });
    }
    if (newPw) {
        newPw.addEventListener('input', function (event) {
            var value = String(newPw.value || '').trim();
            if (event.isTrusted && value !== '') {
                releasePasswordQuiet();
                newPw.setAttribute('data-touched', '1');
            }
            if (value.length >= 12 || value === '' || passwordQuiet()) {
                clearFieldError('new');
            } else if (newPw.getAttribute('data-touched') === '1') {
                setFieldError('new', i18n.passwordShort || 'Use 12 or more characters for your new password.');
            }
            syncPassword();
        });
        newPw.addEventListener('blur', function () {
            if (passwordQuiet()) {
                return;
            }
            var value = String(newPw.value || '').trim();
            if (value === '') {
                return;
            }
            newPw.setAttribute('data-touched', '1');
            if (value.length < 12) {
                setFieldError('new', i18n.passwordShort || 'Use 12 or more characters for your new password.');
            }
        });
    }

    var deleteOpen = document.getElementById('mjb-cd-delete-open');
    var deleteDialog = document.getElementById('mjb-cd-delete-dialog');
    var deleteCancel = document.getElementById('mjb-cd-delete-cancel');
    var deleteInput = document.getElementById('mjb_delete_confirm');
    var deleteSubmit = document.getElementById('mjb-cd-delete-submit');
    function syncDelete() {
        if (!deleteInput || !deleteSubmit) {
            return;
        }
        var word = (deleteInput.getAttribute('data-confirm-word') || 'delete').toLowerCase();
        deleteSubmit.disabled = deleteInput.value.trim().toLowerCase() !== word;
    }
    if (deleteOpen && deleteDialog && typeof deleteDialog.showModal === 'function') {
        deleteOpen.addEventListener('click', function () {
            deleteDialog.classList.remove('is-closing');
            deleteDialog.showModal();
            if (deleteInput) {
                deleteInput.focus();
            }
        });
    }
    if (deleteCancel && deleteDialog) {
        deleteCancel.addEventListener('click', function () {
            if (deleteInput) {
                deleteInput.value = '';
            }
            syncDelete();
            closeAnimatedDialog(deleteDialog);
        });
    }
    if (deleteInput) {
        deleteInput.addEventListener('input', syncDelete);
    }
    if (deleteDialog && deleteDialog.getAttribute('data-open') === '1' && typeof deleteDialog.showModal === 'function') {
        deleteDialog.showModal();
    }
    }

    var stage = document.getElementById('mjb-cd-stage');
    var pending = document.getElementById('mjb-cd-layer-pending');
    var dash = window.mjbCandidateDashboard || {};
    var loaded = { home: false, account: false };
    var switchTimer = null;
    var layerRequest = null;
    var spinMs = 420;

    function layerNode(name) {
        return document.getElementById('mjb-cd-layer-' + name);
    }

    ['home', 'account'].forEach(function (name) {
        var node = layerNode(name);
        loaded[name] = !!(node && node.getAttribute('data-loaded') === '1');
    });

    function closeDeleteDialog() {
        var dialog = document.getElementById('mjb-cd-delete-dialog');
        if (dialog && dialog.open && typeof dialog.close === 'function') {
            dialog.close();
        }
    }

    function showPending(html) {
        closeDeleteDialog();
        ['home', 'account'].forEach(function (name) {
            var node = layerNode(name);
            if (!node) {
                return;
            }
            node.hidden = true;
            node.classList.remove('is-active');
        });
        if (pending) {
            pending.hidden = false;
            pending.innerHTML = html || '';
        }
        if (stage) {
            stage.setAttribute('aria-busy', 'true');
        }
    }

    function showLayer(name, sectionId) {
        if (pending) {
            pending.hidden = true;
            pending.innerHTML = '';
        }
        ['home', 'account'].forEach(function (id) {
            var node = layerNode(id);
            if (!node) {
                return;
            }
            var on = id === name;
            node.hidden = !on;
            node.classList.toggle('is-active', on);
        });
        if (stage) {
            stage.setAttribute('data-active', name);
            stage.removeAttribute('aria-busy');
        }
        if (name === 'account') {
            setCurrent('mjb-cd-account');
        } else if (sectionId) {
            setCurrent(sectionId);
        }
        var section = sectionId ? document.getElementById(sectionId) : null;
        if (section && name === 'home' && sectionId !== 'mjb-cd-overview') {
            section.scrollIntoView({ block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (name === 'account') {
                url.searchParams.set('mjb_panel', 'account');
                url.hash = '';
            } else {
                url.searchParams.delete('mjb_panel');
                url.hash = sectionId && sectionId !== 'mjb-cd-overview' ? sectionId : '';
            }
            window.history.replaceState({ mjbLayer: name }, '', url.pathname + url.search + url.hash);
        }
    }

    function activateLayer(name, sectionId) {
        if (name !== 'home' && name !== 'account') {
            name = 'home';
        }
        var current = stage ? stage.getAttribute('data-active') : 'home';
        if (current === name && loaded[name]) {
            if (name === 'home' && sectionId) {
                setCurrent(sectionId);
                var here = document.getElementById(sectionId);
                if (here) {
                    here.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            return;
        }
        if (switchTimer) {
            window.clearTimeout(switchTimer);
            switchTimer = null;
        }
        if (layerRequest) {
            layerRequest.abort();
            layerRequest = null;
        }
        if (loaded[name]) {
            showPending(dash.spinner || '');
            switchTimer = window.setTimeout(function () {
                showLayer(name, sectionId);
            }, spinMs);
            return;
        }
        showPending((dash.skeletons && dash.skeletons[name]) || '');
        var body = new FormData();
        body.append('action', 'mjb_candidate_layer');
        body.append('security', dash.nonce || '');
        body.append('layer', name);
        layerRequest = new AbortController();
        var controller = layerRequest;
        fetch(dash.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            signal: controller.signal
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            var node = layerNode(name);
            if (!json || !json.success || !json.data || typeof json.data.html !== 'string' || !node) {
                showLayer(current, '');
                return;
            }
            node.innerHTML = json.data.html;
            node.setAttribute('data-loaded', '1');
            loaded[name] = true;
            if (name === 'home') {
                bindHome();
            } else {
                bindAccount();
            }
            showLayer(name, sectionId);
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            showLayer(current, '');
        }).finally(function () {
            if (layerRequest === controller) {
                layerRequest = null;
            }
        });
    }

    var signOutRoot = document.getElementById('mjb-cd-layer-account');
    if (signOutRoot && signOutRoot.getAttribute('data-signout') !== '1') {
        signOutRoot.setAttribute('data-signout', '1');
        signOutRoot.addEventListener('submit', function (event) {
            var form = event.target;
            var submitter = event.submitter;
            if (!form || form.id === 'mjb-cd-account-form' || form.id === 'mjb-cd-password-form' || form.id === 'mjb-cd-delete-form') {
                return;
            }
            if (!submitter || submitter.value !== 'sign_out_others') {
                return;
            }
            var dash = window.mjbCandidateDashboard || {};
            if (!dash.ajaxUrl) {
                return;
            }
            event.preventDefault();
            var body = new FormData(form);
            body.set('action', 'mjb_candidate_account');
            body.set('mjb_account_action', 'sign_out_others');
            fetch(dash.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                var data = payload && payload.data ? payload.data : {};
                if (!payload || !payload.success) {
                    showDashboardNotice(data.message || i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
                    return;
                }
                signOutRoot.querySelectorAll('.mjb-cd-sessions .is-other').forEach(function (row) {
                    row.remove();
                });
                if (form.parentNode) {
                    form.parentNode.removeChild(form);
                }
                showDashboardNotice(data.message, 'success');
            }).catch(function () {
                showDashboardNotice(i18n.saveFailed || 'Changes could not be saved. Try again.', 'error');
            });
        });
    }

    if (loaded.home) {
        bindHome();
    }
    if (loaded.account) {
        bindAccount();
    }
})();
