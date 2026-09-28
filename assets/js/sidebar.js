/* ============================================================
   REUSABLE SIDEBAR BEHAVIOR
   Include on every page that uses the sidebar.
============================================================ */

(function () {
    'use strict';

    const sidebar        = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const toggleBtn      = document.getElementById('toggleSidebar');
    const closeBtn       = document.getElementById('closeSidebar');

    if (!sidebar) return; // No sidebar on this page

    const MOBILE_BREAKPOINT = 1200;

    function isMobile() {
        return window.innerWidth < MOBILE_BREAKPOINT;
    }

    function openSidebar() {
        sidebar.classList.add('show');
        sidebarOverlay?.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay?.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggleBtn?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Close on nav link click (mobile only)
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) closeSidebar();
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Auto-close when resizing to desktop
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!isMobile()) closeSidebar();
        }, 150);
    });

    // Console hint
    console.log('%c📌 Sidebar ready', 'color:#4e73df;font-weight:bold;');
})();