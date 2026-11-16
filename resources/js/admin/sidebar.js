const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const content = document.getElementById('content');

const MOBILE_BREAKPOINT = 991;

function isMobile() {
    return window.matchMedia('(max-width: ' + MOBILE_BREAKPOINT + 'px)').matches;
}

if (sidebarToggle && sidebar && content) {
    sidebarToggle.addEventListener('click', function () {
        if (isMobile()) {
            // On small screens the sidebar is an off-canvas drawer.
            sidebar.classList.toggle('mobile-open');
            return;
        }

        sidebar.classList.toggle('collapsed');
        content.classList.toggle('collapsed');
    });

    document.addEventListener('click', function (event) {
        if (!isMobile() || !sidebar.classList.contains('mobile-open')) {
            return;
        }

        if (sidebar.contains(event.target) || sidebarToggle.contains(event.target)) {
            return;
        }

        sidebar.classList.remove('mobile-open');
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            sidebar.classList.remove('mobile-open');
        }
    });
}

const SIDEBAR_SCROLL_KEY = 'juvo.admin.sidebar.scroll';

function restoreSidebarScroll() {
    if (!sidebar) return;
    const saved = window.sessionStorage.getItem(SIDEBAR_SCROLL_KEY);
    if (saved === null) return;
    const top = parseInt(saved, 10);
    if (Number.isFinite(top) && top !== sidebar.scrollTop) {
        sidebar.scrollTop = top;
    }
}

function persistSidebarScroll() {
    if (!sidebar) return;
    window.sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebar.scrollTop));
}

if (sidebar) {
    restoreSidebarScroll();
    sidebar.addEventListener('scroll', persistSidebarScroll, { passive: true });
    window.addEventListener('pagehide', persistSidebarScroll);
    window.addEventListener('pageshow', restoreSidebarScroll);
    window.addEventListener('load', restoreSidebarScroll);
}
