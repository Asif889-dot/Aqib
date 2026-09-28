/* ============================================================
   ADD CLIENT PAGE — form validation & file uploads
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const form           = document.getElementById('addClientForm');
    const cnicInput      = document.getElementById('cnic');
    const whatsappInput  = document.getElementById('whatsapp');
    const submitBtn      = document.getElementById('submitBtn');
    const submitSpinner  = document.getElementById('submitSpinner');
    const submitIcon     = document.getElementById('submitIcon');
    const submitText     = document.getElementById('submitText');
    const alertContainer = document.getElementById('alertContainer');

    // ============================================================
    // 1. CNIC AUTO-FORMAT — 12345-1234567-1
    //    - Only digits allowed
    //    - Hyphens inserted automatically as you type
    //    - Backspace-friendly (doesn't re-add hyphens aggressively)
    //    - Max 13 digits total
    // ============================================================
    if (cnicInput) {
        cnicInput.addEventListener('input', function () {
            // Preserve cursor position when possible
            const originalValue  = this.value;
            const selectionStart = this.selectionStart;

            // Extract only digits
            let digits = this.value.replace(/\D/g, '').slice(0, 13);

            // Build formatted value
            let formatted = '';
            if (digits.length > 0)  formatted = digits.slice(0, 5);
            if (digits.length > 5)  formatted += '-' + digits.slice(5, 12);
            if (digits.length > 12) formatted += '-' + digits.slice(12, 13);

            this.value = formatted;

            // Adjust cursor: if user is deleting a hyphen, skip it
            const lengthDelta = formatted.length - originalValue.length;
            if (selectionStart !== null) {
                const newPos = Math.max(0, selectionStart + lengthDelta);
                this.setSelectionRange(newPos, newPos);
            }

            // Real-time validation feedback
            validateCnicField(this);
        });

        // Validate on blur too
        cnicInput.addEventListener('blur', function () {
            validateCnicField(this);
        });
    }

    function validateCnicField(el) {
        const value = el.value.trim();
        // Empty is "not yet invalid" (let submit handle that)
        if (value === '') {
            el.classList.remove('is-invalid');
            return;
        }
        // Partial entry — clear invalid until complete
        if (value.length < 15) {
            el.classList.remove('is-invalid');
            return;
        }
        // Full 15-char CNIC — check format
        if (/^\d{5}-\d{7}-\d$/.test(value)) {
            el.classList.remove('is-invalid');
        } else {
            el.classList.add('is-invalid');
            const feedback = el.closest('.input-group')?.parentElement?.querySelector('.invalid-feedback');
            if (feedback) feedback.textContent = 'CNIC must be in format 12345-1234567-1.';
        }
    }

    // ============================================================
    // 2. PAKISTANI PHONE FORMAT — 0300-1234567 or +92 300 1234567
    //    - Accepts: 03001234567, +923001234567, 923001234567, 3001234567
    //    - Displays as: 0300-1234567
    //    - Only allows digits and a leading +
    // ============================================================
    if (whatsappInput) {
        whatsappInput.addEventListener('input', function () {
            const originalValue  = this.value;
            const selectionStart = this.selectionStart;

            // Handle +92 prefix specially
            let value = this.value;
            let hasPlus92 = value.trim().startsWith('+92');

            // Extract digits only
            let digits = value.replace(/\D/g, '');

            // Normalize to 11-digit local format (03XXXXXXXXX)
            // Case 1: starts with 92 + 10 digits → 0 + last 10 digits
            if (digits.startsWith('92') && digits.length >= 12) {
                digits = '0' + digits.slice(2, 12);
            }
            // Case 2: starts with 3 (no leading 0) → prepend 0
            else if (digits.startsWith('3') && !digits.startsWith('03')) {
                digits = '0' + digits;
            }
            // Case 3: already 03XXXXXXXXX
            // (or partial entry — leave as typed)

            // Limit to 11 digits
            digits = digits.slice(0, 11);

            // Build formatted value: 0300-1234567
            let formatted = digits;
            if (digits.length > 4) {
                formatted = digits.slice(0, 4) + '-' + digits.slice(4);
            }

            this.value = formatted;

            // Adjust cursor
            const lengthDelta = formatted.length - originalValue.length;
            if (selectionStart !== null) {
                const newPos = Math.max(0, selectionStart + lengthDelta);
                this.setSelectionRange(newPos, newPos);
            }

            validateWhatsappField(this);
        });

        whatsappInput.addEventListener('blur', function () {
            validateWhatsappField(this);
        });
    }

    function validateWhatsappField(el) {
        const value = el.value.trim().replace(/[\s\-]/g, '');

        // Empty → not yet invalid
        if (value === '') {
            el.classList.remove('is-invalid');
            return;
        }

        // Full 11-digit Pakistani mobile: 03XXXXXXXXX
        if (/^03\d{9}$/.test(value)) {
            el.classList.remove('is-invalid');
            return;
        }

        // Partial entry → keep neutral
        if (value.length < 11) {
            el.classList.remove('is-invalid');
            return;
        }

        // Invalid full entry
        el.classList.add('is-invalid');
        const feedback = el.closest('.input-group')?.parentElement?.querySelector('.invalid-feedback');
        if (feedback) feedback.textContent = 'Enter a valid Pakistani mobile (e.g., 0300-1234567).';
    }

    // ============================================================
    // 3. PASSWORD TOGGLE
    // ============================================================
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye-fill', !isPassword);
            icon.classList.toggle('bi-eye-slash-fill', isPassword);
        });
    });

    // ============================================================
    // 4. FILE UPLOAD PREVIEW
    // ============================================================
    const uploads = [
        { inputId: 'profile_pic',    previewId: 'profilePreview',    imgId: 'profilePreviewImg',    clearId: 'profileClear',    maxMB: 2 },
        { inputId: 'cnic_front_pic', previewId: 'cnicFrontPreview',  imgId: 'cnicFrontPreviewImg',  clearId: 'cnicFrontClear',  maxMB: 3 },
        { inputId: 'cnic_back_pic',  previewId: 'cnicBackPreview',   imgId: 'cnicBackPreviewImg',   clearId: 'cnicBackClear',   maxMB: 3 },
    ];

    uploads.forEach(cfg => {
        const input      = document.getElementById(cfg.inputId);
        const preview    = document.getElementById(cfg.previewId);
        const previewImg = document.getElementById(cfg.imgId);
        const clearBtn   = document.getElementById(cfg.clearId);
        const label      = input?.closest('.upload-box')?.querySelector('.upload-label');

        if (!input || !label) return;

        // Drag & drop
        label.addEventListener('dragover', (e) => {
            e.preventDefault();
            label.classList.add('dragover');
        });
        label.addEventListener('dragleave', () => label.classList.remove('dragover'));
        label.addEventListener('drop', (e) => {
            e.preventDefault();
            label.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });

        // On file change
        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            // Validate type
            if (!['image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(file.type)) {
                showAlert('Please upload a JPG, PNG, or WEBP image.', 'danger');
                resetUpload();
                return;
            }
            // Validate size
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
            };
            reader.readAsDataURL(file);
        });

        // Clear
        clearBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            resetUpload();
        });

        function resetUpload() {
            input.value = '';
            previewImg.src = '';
            previewImg.classList.add('d-none');
            preview.classList.remove('d-none');
            label.classList.remove('has-image');
            clearBtn.classList.add('d-none');
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
    // 6. VALIDATION HELPERS
    // ============================================================
    function setInvalid(el, message) {
        el.classList.add('is-invalid');
        const feedback = el.closest('.input-group')
            ? el.closest('.input-group').parentElement.querySelector('.invalid-feedback')
            : el.parentElement.querySelector('.invalid-feedback');
        if (feedback && message) feedback.textContent = message;
    }

    function setValid(el) {
        el.classList.remove('is-invalid');
    }

    function validateForm() {
        let ok = true;
        let firstError = null;

        // Clear previous
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        // Name
        const name = document.getElementById('name');
        if (!name.value.trim() || name.value.trim().length < 3) {
            setInvalid(name, 'Name must be at least 3 characters.');
            ok = false; firstError = firstError || name;
        }

        // Father name
        const father = document.getElementById('father_name');
        if (!father.value.trim() || father.value.trim().length < 3) {
            setInvalid(father, 'Father name must be at least 3 characters.');
            ok = false; firstError = firstError || father;
        }

        // CNIC — strict 12345-1234567-1
        const cnic = document.getElementById('cnic');
        const cnicRegex = /^\d{5}-\d{7}-\d$/;
        if (!cnicRegex.test(cnic.value.trim())) {
            setInvalid(cnic, 'CNIC must be in format 12345-1234567-1.');
            ok = false; firstError = firstError || cnic;
        }

        // WhatsApp — accept 03001234567 or 0300-1234567 or +923001234567
        const wa = document.getElementById('whatsapp');
        const waClean = wa.value.trim().replace(/[\s\-]/g, '');
        if (!/^(?:\+92|0)?3\d{9}$/.test(waClean) && !/^03\d{9}$/.test(waClean)) {
            setInvalid(wa, 'Enter a valid Pakistani mobile (e.g., 0300-1234567).');
            ok = false; firstError = firstError || wa;
        }

        // Entry year
        const year = document.getElementById('entry_year');
        if (!year.value) {
            setInvalid(year, 'Please select the entry year.');
            ok = false; firstError = firstError || year;
        }

        // Amount
        const amount = document.getElementById('amount');
        if (!amount.value || parseFloat(amount.value) < 0) {
            setInvalid(amount, 'Enter a valid amount.');
            ok = false; firstError = firstError || amount;
        }

        // IRIS email
        const email = document.getElementById('iris_email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value.trim())) {
            setInvalid(email, 'Enter a valid email address.');
            ok = false; firstError = firstError || email;
        }

        // IRIS password
        const pwd = document.getElementById('iris_password');
        if (pwd.value.length < 6) {
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

    // Real-time: clear invalid state on input
    form.querySelectorAll('.form-control, .form-select').forEach(el => {
        el.addEventListener('input',  () => {
            // Skip cnic and whatsapp — they have their own validators
            if (el.id === 'cnic' || el.id === 'whatsapp') return;
            setValid(el);
        });
        el.addEventListener('change', () => {
            if (el.id === 'cnic' || el.id === 'whatsapp') return;
            setValid(el);
        });
    });

    // ============================================================
    // 7. FORM SUBMISSION (AJAX)
    // ============================================================
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        alertContainer.innerHTML = '';

        if (!validateForm()) return;

        // Loading state
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
                console.error('Server returned non-JSON:', text);
                throw new Error('Unexpected server response. Check console.');
            }

            if (data.success) {
                showAlert(data.message || 'Client added successfully!', 'success');
                form.reset();
                // Clear previews
                document.querySelectorAll('.upload-label').forEach(l => l.classList.remove('has-image'));
                document.querySelectorAll('.upload-preview').forEach(p => p.classList.remove('d-none'));
                document.querySelectorAll('[id$="PreviewImg"]').forEach(img => {
                    img.classList.add('d-none');
                    img.src = '';
                });
                document.querySelectorAll('.upload-box ~ .btn, .upload-box .btn').forEach(b => {
                    if (b.classList.contains('btn-outline-danger')) b.classList.add('d-none');
                });

                // Redirect after delay
                if (data.redirect) {
                    setTimeout(() => window.location.href = data.redirect, 1400);
                }
            } else if (data.errors && typeof data.errors === 'object') {
                // Field-level errors from backend
                Object.entries(data.errors).forEach(([field, msg]) => {
                    const el = form.querySelector(`[name="${field}"]`);
                    if (el) setInvalid(el, msg);
                });
                showAlert(data.message || 'Please fix the errors below.', 'danger');
            } else {
                showAlert(data.message || 'Failed to add client.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showAlert(err.message || 'Network error. Please try again.', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitSpinner.classList.add('d-none');
            submitIcon.classList.remove('d-none');
            submitText.textContent = 'Save Client';
        }
    });
});