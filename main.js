// Hidden Panel Logic
let inputKeys = '';
const secretCode = '12345';
window.addEventListener('keydown', (e) => {
    inputKeys += e.key;
    if (inputKeys.length > secretCode.length) inputKeys = inputKeys.substring(inputKeys.length - secretCode.length);
    if (inputKeys === secretCode) {
        const panel = document.getElementById('control-panel');
        if (panel) {
            panel.style.display = 'block';
            setTimeout(() => panel.classList.add('visible'), 10);
        }
        inputKeys = '';
    }
});

function togglePanel(show) {
    const panel = document.getElementById('control-panel');
    if (!panel) return;
    if (show) {
        panel.style.display = 'block';
        setTimeout(() => panel.classList.add('visible'), 10);
    } else {
        panel.classList.remove('visible');
        setTimeout(() => panel.style.display = 'none', 400);
    }
}

function setupPublicNav() {
    const toggle = document.getElementById('nav-menu-toggle');
    const drawer = document.getElementById('site-nav-drawer');
    const overlay = document.getElementById('nav-drawer-overlay');
    const closeBtn = document.getElementById('nav-drawer-close');
    if (!toggle || !drawer || !overlay) {
        return;
    }

    function setOpen(open) {
        document.body.classList.toggle('nav-drawer-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        drawer.classList.toggle('is-open', open);
        overlay.classList.toggle('is-active', open);
        overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
        if ('inert' in drawer) {
            drawer.inert = !open;
        }
    }

    toggle.addEventListener('click', () => {
        setOpen(!drawer.classList.contains('is-open'));
    });
    overlay.addEventListener('click', () => setOpen(false));
    if (closeBtn) {
        closeBtn.addEventListener('click', () => setOpen(false));
    }
    drawer.querySelectorAll('a.nav-page-link').forEach((a) => {
        a.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            setOpen(false);
        }
    });

    var sticky = document.querySelector('.nav-sticky-cta');
    if (sticky) {
        document.body.classList.add('has-sticky-cta');
        if (sticky.getAttribute('data-sticky-desktop') === '1') {
            document.body.classList.add('has-sticky-desktop');
        }
        if (sticky.classList.contains('nav-sticky-cta--full') || sticky.getAttribute('data-sticky-layout') === 'full') {
            document.body.classList.add('has-sticky-layout-full');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupPublicNav();
});
