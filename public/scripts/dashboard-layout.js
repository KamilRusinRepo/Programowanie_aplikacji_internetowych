window.FlashMindTheme?.applyTheme?.(window.FlashMindTheme.getTheme());

const sidebarStorageKey = 'flashmindSidebarCollapsed';
const dashboardShell = document.querySelector('.dashboard-shell');

const applySidebarState = (collapsed) => {
    if (!dashboardShell) return;
    dashboardShell.classList.toggle('is-sidebar-collapsed', collapsed);
    const toggle = dashboardShell.querySelector('[data-sidebar-toggle]');
    if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
};

try {
    applySidebarState(window.localStorage.getItem(sidebarStorageKey) === '1');
} catch (error) {
    applySidebarState(false);
}

document.addEventListener('click', (e) => {
    const sidebarToggle = e.target.closest('[data-sidebar-toggle]');
    if (sidebarToggle) {
        const collapsed = !dashboardShell?.classList.contains('is-sidebar-collapsed');
        applySidebarState(collapsed);
        try {
            window.localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
        } catch (error) {
            // Sidebar still toggles for the current page.
        }
        document.querySelectorAll('.dashboard-sidebar-user-wrap.popover-open').forEach((w) => {
            w.classList.remove('popover-open');
        });
        return;
    }

    const toggle = e.target.closest('[data-user-toggle]');

    if (toggle) {
        const wrap = toggle.closest('.dashboard-sidebar-user-wrap');
        if (!wrap) return;
        wrap.classList.toggle('popover-open');
        return;
    }

    if (e.target.closest('.dashboard-sidebar-user-wrap')) {
        return;
    }

    document.querySelectorAll('.dashboard-sidebar-user-wrap.popover-open').forEach((w) => {
        w.classList.remove('popover-open');
    });
});

document.addEventListener('click', (e) => {
    if (e.target.matches('.user-logout-btn')) {
        e.preventDefault();
        window.location.assign('/logout');
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
        document.querySelectorAll('.dashboard-sidebar-user-wrap.popover-open').forEach((w) => {
            w.classList.remove('popover-open');
        });
    }
});
