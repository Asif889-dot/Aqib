/* ============================================================
   ALL CLIENTS — list page behavior
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const config = window.CLIENTS_CONFIG || {};
    const deleteModal    = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteClientNameEl = document.getElementById('deleteClientName');
    const confirmDeleteBtn   = document.getElementById('confirmDeleteBtn');
    const deleteSpinner  = document.getElementById('deleteSpinner');

    let currentDeleteId   = null;
    let currentDeleteRow  = null;

    // ============================================================
    // 1. DELETE — open modal
    // ============================================================
    document.querySelectorAll('.btn-action-delete').forEach(btn => {
        btn.addEventListener('click', function () {
            currentDeleteId  = this.dataset.id;
            currentDeleteRow = this.closest('tr');
            const name = this.dataset.name || 'this client';

            deleteClientNameEl.textContent = name;
            deleteModal.show();
        });
    });

    // ============================================================
    // 2. DELETE — confirm
    // ============================================================
    confirmDeleteBtn?.addEventListener('click', async function () {
        if (!currentDeleteId) return;

        // Loading
        this.disabled = true;
        deleteSpinner.classList.remove('d-none');

        try {
            const formData = new FormData();
            formData.append('id', currentDeleteId);

            const res = await fetch(config.deleteUrl || '../../backend/clients/delete.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error('Non-JSON response:', text);
                throw new Error('Unexpected server response.');
            }

            if (data.success) {
                // Animate row out
                if (currentDeleteRow) {
                    currentDeleteRow.classList.add('row-deleting');
                    setTimeout(() => {
                        currentDeleteRow.remove();
                        // If table is now empty, reload to show empty state
                        const rows = document.querySelectorAll('.clients-table tbody tr');
                        if (rows.length === 0) {
                            window.location.reload();
                        }
                    }, 400);
                }
                deleteModal.hide();

                // Show floating toast
                showToast(data.message || 'Client deleted.', 'success');
            } else {
                showToast(data.message || 'Failed to delete client.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Network error.', 'danger');
        } finally {
            this.disabled = false;
            deleteSpinner.classList.add('d-none');
            currentDeleteId = null;
            currentDeleteRow = null;
        }
    });

    // ============================================================
    // 3. FLOATING TOAST
    // ============================================================
    function showToast(message, type = 'success') {
        const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
        const bg   = type === 'success' ? '#1cc88a' : '#e74a3b';

        const toast = document.createElement('div');
        toast.className = 'clients-toast';
        toast.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            background: #fff;
            border-left: 4px solid ${bg};
            border-radius: 10px;
            padding: 14px 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            font-weight: 500;
            font-size: 14px;
            transform: translateX(120%);
            transition: transform .35s cubic-bezier(.34, 1.56, .64, 1);
            max-width: 380px;
        `;
        toast.innerHTML = `
            <i class="bi bi-${icon}" style="color:${bg};font-size:18px;"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
        });

        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    // ============================================================
    // 4. KEYBOARD SHORTCUT: Focus search on "/" key
    // ============================================================
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'SELECT') {
            e.preventDefault();
            document.querySelector('input[name="q"]')?.focus();
        }
    });

    // ============================================================
    // 5. AUTO-SUBMIT FILTERS ON DROPDOWN CHANGE
    // ============================================================
    document.querySelectorAll('.panel select').forEach(sel => {
        sel.addEventListener('change', function () {
            this.closest('form')?.submit();
        });
    });

});