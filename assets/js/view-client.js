/* ============================================================
   VIEW CLIENT PAGE
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const config = window.VIEW_CONFIG || {};

    // ============================================================
    // 1. COPY-TO-CLIPBOARD
    // ============================================================
    document.querySelectorAll('.copyable').forEach(el => {
        const btn = el.querySelector('.btn-copy');
        if (!btn) return;

        btn.addEventListener('click', async function (e) {
            e.stopPropagation();
            const text = el.dataset.copy || el.textContent.trim();

            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // Fallback for older browsers
                const tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); } catch {}
                tmp.remove();
            }

            // Visual feedback
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'bi bi-check-lg';
            btn.classList.add('copied');
            btn.title = 'Copied!';

            setTimeout(() => {
                icon.className = originalClass;
                btn.classList.remove('copied');
                btn.title = 'Copy';
            }, 1500);
        });
    });

    // ============================================================
    // 2. IRIS PASSWORD REVEAL
    // ============================================================
    const btnReveal     = document.getElementById('btnRevealPassword');
    const passwordMasked = document.getElementById('passwordMasked');
    const passwordPlain  = document.getElementById('passwordPlain');
    const revealIcon    = document.getElementById('revealIcon');
    const revealText    = document.getElementById('revealText');
    const revealHint    = document.getElementById('revealHint');

    let revealed = false;
    let lastPassword = '';

    btnReveal?.addEventListener('click', async function () {
        if (revealed) {
            // Hide again — no server call needed
            passwordMasked.classList.remove('d-none');
            passwordPlain.classList.add('d-none');
            revealIcon.className = 'bi bi-eye-fill';
            revealText.textContent = 'Reveal';
            btnReveal.classList.remove('revealed');
            revealed = false;
            return;
        }

        const clientId = this.dataset.id;
        if (!clientId) return;

        // Loading state
        this.disabled = true;
        revealIcon.className = 'bi bi-hourglass-split';

        try {
            const formData = new FormData();
            formData.append('id', clientId);

            const res = await fetch(config.decryptUrl || '../../backend/clients/decrypt.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error('Non-JSON:', text);
                throw new Error('Unexpected server response.');
            }

            if (data.success && data.password !== undefined) {
                lastPassword = data.password;
                passwordPlain.textContent = data.password;

                passwordMasked.classList.add('d-none');
                passwordPlain.classList.remove('d-none');

                revealIcon.className = 'bi bi-eye-slash-fill';
                revealText.textContent = 'Hide';
                btnReveal.classList.add('revealed');
                revealed = true;

                // Update hint
                if (revealHint) {
                    revealHint.innerHTML =
                        '<i class="bi bi-check-circle-fill text-success"></i> ' +
                        'Password revealed and logged.';
                }

                // Auto-copy option — uncomment if desired
                // navigator.clipboard.writeText(data.password);

                showToast('Password decrypted. This action was logged.', 'success');
            } else {
                showToast(data.message || 'Could not decrypt password.', 'danger');
                revealIcon.className = 'bi bi-eye-fill';
            }
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Network error.', 'danger');
            revealIcon.className = 'bi bi-eye-fill';
        } finally {
            this.disabled = false;
        }
    });

    // ============================================================
    // 3. LIGHTBOX
    // ============================================================
    const lightbox        = document.getElementById('lightbox');
    const lightboxImg     = document.getElementById('lightboxImg');
    const lightboxCaption = document.getElementById('lightboxCaption');
    const lightboxClose   = document.getElementById('lightboxClose');

    function openLightbox(src, caption) {
        lightboxImg.src = src;
        lightboxCaption.textContent = caption || '';
        lightbox.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        lightbox.classList.remove('show');
        document.body.style.overflow = '';
        setTimeout(() => { lightboxImg.src = ''; }, 300);
    }

    document.querySelectorAll('[data-lightbox]').forEach(el => {
        el.addEventListener('click', function () {
            openLightbox(
                this.dataset.lightbox,
                this.dataset.caption || this.alt || ''
            );
        });
    });

    lightboxClose?.addEventListener('click', closeLightbox);
    lightbox?.addEventListener('click', function (e) {
        if (e.target === lightbox) closeLightbox();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && lightbox?.classList.contains('show')) {
            closeLightbox();
        }
    });

    // ============================================================
    // 4. TOAST HELPER
    // ============================================================
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const icon = type === 'success' ? 'check-circle-fill'
                   : type === 'danger'  ? 'exclamation-triangle-fill'
                   : 'info-circle-fill';

        const toast = document.createElement('div');
        toast.className = `view-toast ${type}`;
        toast.innerHTML = `
            <i class="bi bi-${icon}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('show'));

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

});