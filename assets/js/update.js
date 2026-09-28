/* ============================================================
   UPDATE FROM GITHUB — page behavior
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    const config = window.UPDATE_CONFIG || {};

    const pullBtn      = document.getElementById('pullBtn');
    const pullSpinner  = document.getElementById('pullSpinner');
    const pullIcon     = document.getElementById('pullIcon');
    const pullText     = document.getElementById('pullText');

    const fetchBtn     = document.getElementById('fetchBtn');
    const fetchSpinner = document.getElementById('fetchSpinner');
    const fetchIcon    = document.getElementById('fetchIcon');
    const fetchText    = document.getElementById('fetchText');

    const diagBtn      = document.getElementById('diagBtn');
    const diagSpinner  = document.getElementById('diagSpinner');
    const diagIcon     = document.getElementById('diagIcon');
    const diagText     = document.getElementById('diagText');

    const outputConsole = document.getElementById('outputConsole');
    const outputBody    = document.getElementById('outputBody');
    const closeConsole  = document.getElementById('closeConsole');
    const dnsFixHint    = document.getElementById('dnsFixHint');

    function getSelectedMode() {
        const checked = document.querySelector('input[name="pullMode"]:checked');
        return checked ? checked.value : 'ff_only';
    }

    function setLoading(btn, spinner, icon, textEl, loadingText) {
        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        textEl.textContent = loadingText;
    }
    function clearLoading(btn, spinner, icon, textEl, normalText) {
        btn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
        textEl.textContent = normalText;
    }

    function showConsole(text, success) {
        outputBody.textContent = text || '(no output)';
        outputConsole.classList.remove('d-none');
        outputConsole.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        outputConsole.style.borderColor = success ? '#1cc88a' : '#e74a3b';
        outputConsole.style.boxShadow = success
            ? '0 0 0 3px rgba(28,200,138,.15)'
            : '0 0 0 3px rgba(231,74,59,.15)';
    }

    async function runAction(action, mode) {
        const formData = new FormData();
        formData.append('action', action);
        if (mode) formData.append('mode', mode);

        try {
            const res = await fetch(config.pullUrl || '../../backend/admin/pull.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });

            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error('Invalid JSON from server:\n' + text.substring(0, 800));
            }

            showConsole(data.output || data.message, !!data.success);

            // Show DNS-fix hint if the specific error occurred
            if (data.dns_error) {
                dnsFixHint.style.display = 'block';
                dnsFixHint.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                dnsFixHint.style.display = 'none';
            }

            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 2500);
            } else {
                showToast(data.message || 'Operation failed.', 'danger');
            }
        } catch (err) {
            console.error(err);
            showConsole(err.message, false);
            showToast(err.message || 'Network error.', 'danger');
        }
    }

    // ---------- Pull ----------
    pullBtn?.addEventListener('click', async function () {
        const mode = getSelectedMode();

        if (mode === 'hard_reset') {
            if (!confirm('⚠️ HARD RESET will discard ALL local code changes.\n\nUntracked files (uploads, logs) are preserved.\n\nContinue?')) return;
        }
        if (mode === 'ff_merge') {
            if (!confirm('Fetch + Merge may create merge conflicts.\n\nContinue?')) return;
        }

        setLoading(pullBtn, pullSpinner, pullIcon, pullText, 'Pulling...');
        try {
            await runAction('pull', mode);
        } finally {
            clearLoading(pullBtn, pullSpinner, pullIcon, pullText, 'Pull From GitHub');
        }
    });

    // ---------- Fetch ----------
    fetchBtn?.addEventListener('click', async function () {
        setLoading(fetchBtn, fetchSpinner, fetchIcon, fetchText, 'Fetching...');
        try {
            await runAction('fetch');
        } finally {
            clearLoading(fetchBtn, fetchSpinner, fetchIcon, fetchText, 'Fetch Only');
        }
    });

    // ---------- Diagnostics ----------
    diagBtn?.addEventListener('click', async function () {
        setLoading(diagBtn, diagSpinner, diagIcon, diagText, 'Running...');
        try {
            const formData = new FormData();
            formData.append('action', 'diagnostic');

            const res = await fetch(config.diagUrl || '../../backend/admin/pull.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });
            const data = await res.json();

            if (data.success && data.results) {
                let out = '';
                for (const [key, r] of Object.entries(data.results)) {
                    const status = r.ok ? '✅' : '❌';
                    out += `${status} ${key}\n${'─'.repeat(50)}\n${r.out}\n\n`;
                }
                showConsole(out, true);

                const lsRemote = data.results.ls_remote || {};
                const isDnsError = !lsRemote.ok &&
                    /getaddrinfo|unable to access|Could not resolve/i.test(lsRemote.out || '');
                dnsFixHint.style.display = isDnsError ? 'block' : 'none';
            } else {
                showConsole(data.message || 'Diagnostics failed.', false);
            }
        } catch (err) {
            showConsole(err.message, false);
        } finally {
            clearLoading(diagBtn, diagSpinner, diagIcon, diagText, 'Run Diagnostics');
        }
    });

    // ---------- Close console ----------
    closeConsole?.addEventListener('click', () => {
        outputConsole.classList.add('d-none');
    });

    // ---------- Toast ----------
    function showToast(message, type = 'success') {
        const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
        const bg   = type === 'success' ? '#1cc88a' : '#e74a3b';

        const toast = document.createElement('div');
        toast.style.cssText = `
            position:fixed;top:24px;right:24px;background:#fff;
            border-left:4px solid ${bg};border-radius:10px;
            padding:14px 18px;box-shadow:0 10px 30px rgba(0,0,0,.15);
            display:flex;align-items:center;gap:10px;z-index:9999;
            font-weight:500;font-size:14px;
            transform:translateX(120%);
            transition:transform .35s cubic-bezier(.34,1.56,.64,1);
            max-width:380px;`;
        toast.innerHTML = `
            <i class="bi bi-${icon}" style="color:${bg};font-size:18px;"></i>
            <span>${message}</span>`;
        document.body.appendChild(toast);

        requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }
});