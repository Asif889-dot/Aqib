/* ============================================================
   EDIT CLIENT — form behavior
   (reuses the same validation/upload logic as add-client.js,
    with an extra "keep existing" image concept)
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const form           = document.getElementById('editClientForm');
    if (!form) return;

    const cnicInput      = document.getElementById('cnic');
    const whatsappInput  = document.getElementById('whatsapp');
    const submitBtn      = document.getElementById('submitBtn');
    const submitSpinner  = document.getElementById('submitSpinner');
    const submitIcon     = document.getElementById('submitIcon');
    const submitText     = document.getElementById('submitText');
    const alertContainer = document.getElementById('alertContainer');

    // Removal flags (hidden inputs)
    const removeProfileFlag   = document.getElementById('removeProfilePic');
    const removeCnicFrontFlag = document.getElementById('removeCnicFrontPic');
    const removeCnicBackFlag  = document.getElementById('removeCnicBackPic');

    // ============================================================
    // 1. CNIC AUTO-FORMAT
    // ============================================================
    if (cnicInput) {
        cnicInput.addEventListener('input', function () {
            const originalValue  = this.value;
            const selectionStart = this.selectionStart;
            let digits = this.value.replace(/\D/g, '').slice(0, 13);
            let formatted = '';
            if (digits.length > 0)  formatted = digits.slice(0, 5);
            if (digits.length > 5)  formatted += '-' + digits.slice(5, 12);
            if (digits.length > 12) formatted += '-' + digits.slice(12, 13);
            this.value = formatted;
            const lengthDelta = formatted.length - originalValue.length;
            if (selectionStart !== null) {
                const newPos = Math.max(0, selectionStart + lengthDelta);
                this.setSelectionRange(newPos, newPos);
            }
        });
    }

    // ============================================================
    // 2. WHATSAPP FORMAT
    // ============================================================
    if (whatsappInput) {
        whatsappInput.addEventListener('input', function () {
            let value = this.value;
            let digits = value.replace(/\D/g, '');
            if (digits.startsWith('92') && digits.length >= 12)  digits = '0' + digits.slice(2, 12);
            else if (digits.startsWith('3') && !digits.startsWith('03')) digits = '0' + digits;
            digits = digits.slice(0, 11);
            let formatted = digits;
            if (digits.length > 4) formatted = digits.slice(0, 4) + '-' + digits.slice(4);
            this.value = formatted;
        });
    }

    // ============================================================
    // 3. PASSWORD TOGGLE
    // ============================================================
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye-fill', !isPassword);
            icon.classList.toggle('bi-eye-slash-fill', isPassword);
        });
    });

    // ============================================================
    // 4. FILE UPLOADS (with "existing image" awareness)
    // ============================================================
    const uploads = [
        { inputId: 'profile_pic',    previewId: 'profilePreview',    imgId: 'profilePreviewImg',    clearId: 'profileClear',    flagId: 'removeProfilePic',    maxMB: 2 },
        { inputId: 'cnic_front_pic', previewId: 'cnicFrontPreview',  imgId: 'cnicFrontPreviewImg',  clearId: 'cnicFrontClear',  flagId: 'removeCnicFrontPic',  maxMB: 3 },
        { inputId: 'cnic_back_pic',  previewId: 'cnicBackPreview',   imgId: 'cnicBackPreviewImg',   clearId: 'cnicBackClear',   flagId: 'removeCnicBackPic',   maxMB: 3 },
    ];

    uploads.forEach(cfg => {
        const input      = document.getElementById(cfg.inputId);
        const preview    = document.getElementById(cfg.previewId);
        const previewImg = document.getElementById(cfg.imgId);
        const clearBtn   = document.getElementById(cfg.clearId);
        const flagInput  = document.getElementById(cfg.flagId);
        const label      = input?.closest('.upload-box')?.querySelector('.upload-label');
        const originalSrc = previewImg?.src || '';

        if (!input || !label) return;

        // Drag & drop
        label.addEventListener('dragover', e => { e.preventDefault(); label.classList.add('dragover'); });
        label.addEventListener('dragleave', () => label.classList.remove('dragover'));
        label.addEventListener('drop', e => {
            e.preventDefault();
            label.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });

        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
                showAlert('Please upload a JPG, PNG, or WEBP image.', 'danger');
                resetUpload();
                return;
            }
            if (file.size > cfg.maxMB * 1024 * 1024) {
                showAlert(`Image must be smaller than ${cfg.maxMB}MB.`, 'danger');
                resetUpload();
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                previewImg.classList.remove('d-none');
                preview.classList.add('d-none');
                label.classList.add('has-image');
                clearBtn.classList.remove('d-none');
                // Reset remove flag — user is replacing, not removing
                if (flagInput) flagInput.value = '0';
            };
            reader.readAsDataURL(file);
        });

        clearBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Mark for removal on submit
            if (flagInput) flagInput.value = '1';
            // Clear the preview visually
            input.value = '';
            previewImg.src = '';
            previewImg.classList.add('d-none');
            preview.classList.remove('d-none');
            label.classList.remove('has-image');
            clearBtn.classList.add('d-none');
        });

        function resetUpload() {
            input.value = '';
            previewImg.src = originalSrc;
            if (originalSrc) {
                previewImg.classList.remove('d-none');
                preview.classList.add('d-none');
                label.classList.add('has-image');
                clearBtn.classList.remove('d-none');
            } else {
                previewImg.classList.add('d-none');
                preview.classList.remove('d-none');
                label.classList.remove('has-image');
                clearBtn.classList.add('d-none');
            }
        }
    });

    // ============================================================
    // 5. ALERT HELPER
    // ============================================================
    function showAlert(message, type = 'danger') {
        const icon = type === 'danger' ? 'exclamation-triangle-fill' :
                     type === 'success' ? 'check-circle-fill' : 'info-circle-fill';
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-${icon} me-2"></i>
                <div>${message}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ============================================================
    // 6. VALIDATION
    // ============================================================
    function setInvalid(el, message) {
        el.classList.add('is-invalid');
        const feedback = el.closest('.input-group')
            ? el.closest('.input-group').parentElement.querySelector('.invalid-feedback')
            : el.parentElement.querySelector('.invalid-feedback');
        if (feedback && message) feedback.textContent = message;
    }

    function validateForm() {
        let ok = true;
        let firstError = null;

        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        const name = document.getElementById('name');
        if (!name.value.trim() || name.value.trim().length < 3) {
            setInvalid(name, 'Name must be at least 3 characters.');
            ok = false; firstError = firstError || name;
        }

        const father = document.getElementById('father_name');
        if (!father.value.trim() || father.value.trim().length < 3) {
            setInvalid(father, 'Father name must be at least 3 characters.');
            ok = false; firstError = firstError || father;
        }

        const cnic = document.getElementById('cnic');
        if (!/^\d{5}-\d{7}-\d$/.test(cnic.value.trim())) {
            setInvalid(cnic, 'CNIC must be in format 12345-1234567-1.');
            ok = false; firstError = firstError || cnic;
        }

        const wa = document.getElementById('whatsapp');
        const waClean = wa.value.trim().replace(/[\s\-]/g, '');
        if (!/^(?:\+92|0)?3\d{9}$/.test(waClean) && !/^03\d{9}$/.test(waClean)) {
            setInvalid(wa, 'Enter a valid Pakistani mobile (e.g., 0300-1234567).');
            ok = false; firstError = firstError || wa;
        }

        const year = document.getElementById('entry_year');
        if (!year.value) {
            setInvalid(year, 'Please select the entry year.');
            ok = false; firstError = firstError || year;
        }

        const amount = document.getElementById('amount');
        if (!amount.value || parseFloat(amount.value) < 0) {
            setInvalid(amount, 'Enter a valid amount.');
            ok = false; firstError = firstError || amount;
        }

        const email = document.getElementById('iris_email');
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            setInvalid(email, 'Enter a valid email address.');
            ok = false; firstError = firstError || email;
        }

        // Password: only required if user typed something
        const pwd = document.getElementById('iris_password');
        if (pwd.value !== '' && pwd.value.length < 6) {
            setInvalid(pwd, 'Password must be at least 6 characters.');
            ok = false; firstError = firstError || pwd;
        }

        if (!ok && firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus({ preventScroll: true });
            showAlert('Please fix the highlighted fields below.', 'danger');
        }
        return ok;
    }

    // ============================================================
    // 7. SUBMIT
    // ============================================================
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        alertContainer.innerHTML = '';

        if (!validateForm()) return;

        submitBtn.disabled = true;
        submitSpinner.classList.remove('d-none');
        submitIcon.classList.add('d-none');
        submitText.textContent = 'Saving...';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error('Non-JSON:', text);
                throw new Error('Unexpected server response.');
            }

            if (data.success) {
                showAlert(data.message || 'Client updated!', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect || `view.php?id=${form.id.value}&msg=updated`;
                }, 1000);
            } else if (data.errors && typeof data.errors === 'object') {
                Object.entries(data.errors).forEach(([field, msg]) => {
                    const el = form.querySelector(`[name="${field}"]`);
                    if (el) setInvalid(el, msg);
                });
                showAlert(data.message || 'Please fix the errors below.', 'danger');
            } else {
                showAlert(data.message || 'Update failed.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showAlert(err.message || 'Network error.', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitSpinner.classList.add('d-none');
            submitIcon.classList.remove('d-none');
            submitText.textContent = 'Save Changes';
        }
    });
});