/* ============================================================
   DASHBOARD JAVASCRIPT
============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ---------- 1. SIDEBAR TOGGLE (mobile) ----------
    const sidebar       = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const toggleBtn     = document.getElementById('toggleSidebar');
    const closeBtn      = document.getElementById('closeSidebar');

    function openSidebar() {
        sidebar?.classList.add('show');
        sidebarOverlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar?.classList.remove('show');
        sidebarOverlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggleBtn?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Auto close sidebar on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1200) closeSidebar();
    });

    // Close on nav link click (mobile)
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1200) closeSidebar();
        });
    });

    // ---------- 2. TABLE SEARCH ----------
    const searchInput = document.getElementById('tableSearch');
    const clientsTable = document.getElementById('clientsTable');

    searchInput?.addEventListener('input', function () {
        const term = this.value.toLowerCase().trim();
        const rows = clientsTable?.querySelectorAll('tbody tr') || [];

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // ---------- 3. PERFORMANCE CHART ----------
    const chartCanvas = document.getElementById('performanceChart');

    if (chartCanvas && typeof Chart !== 'undefined') {
        const ctx = chartCanvas.getContext('2d');

        // Gradient fill
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(78,115,223,0.35)');
        gradient.addColorStop(1, 'rgba(78,115,223,0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                         'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Revenue',
                    data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 41, 38, 48],
                    borderColor: '#4e73df',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#4e73df',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }, {
                    label: 'Clients',
                    data: [8, 12, 10, 15, 17, 20, 22, 24, 23, 28, 30, 34],
                    borderColor: '#1cc88a',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: false,
                    pointRadius: 4,
                    pointBackgroundColor: '#1cc88a',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15,
                            font: { size: 12, weight: '600' },
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 13, weight: '700' },
                        bodyFont: { size: 12 },
                        displayColors: true,
                        usePointStyle: true,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false,
                        },
                        ticks: { color: '#6b7280', font: { size: 11 } },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280', font: { size: 11 } },
                    },
                },
            },
        });
    }

    // ---------- 4. AUTO-DISMISS ALERTS ----------
    setTimeout(() => {
        document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
            el.style.transition = 'opacity .4s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        });
    }, 5000);

    // ---------- 5. SESSION TIMEOUT WATCHDOG (client-side) ----------
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 min
    let idleTimer;
    let lastPing = Date.now();

    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => {
            if (Date.now() - lastPing >= SESSION_TIMEOUT) {
                alert('Your session has expired. You will be redirected to login.');
                window.location.href = 'logout.php?reason=timeout';
            }
        }, SESSION_TIMEOUT);
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();

    // ---------- 6. SMOOTH TABLE ROW CLICK (optional) ----------
    document.querySelectorAll('#clientsTable tbody tr').forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', (e) => {
            // Ignore clicks on buttons
            if (e.target.closest('button')) return;
            // Do something — e.g., open detail modal
            // console.log('Row clicked');
        });
    });

    // ---------- 7. CONSOLE WELCOME ----------
    console.log('%c🚀 Aqibsb Dashboard loaded', 'color:#4e73df;font-size:14px;font-weight:bold;');
});