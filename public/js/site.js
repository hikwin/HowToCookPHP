/* site.js - HowToCookViewer interactive enhancements */

document.addEventListener('DOMContentLoaded', () => {
    // ── Lucide icons ─────────────────────────────────────────────────────────
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // ── Search form: focus effect ─────────────────────────────────────────────
    const searchInputs = document.querySelectorAll('input[type="search"], input[name="q"]');
    searchInputs.forEach(el => {
        el.addEventListener('focus', () => el.closest('.input-group')?.classList.add('focused'));
        el.addEventListener('blur',  () => el.closest('.input-group')?.classList.remove('focused'));
    });

    // ── Recipe card hover tilt (subtle) ───────────────────────────────────────
    const cards = document.querySelectorAll('.recipe-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const cx   = rect.left + rect.width / 2;
            const cy   = rect.top  + rect.height / 2;
            const dx   = (e.clientX - cx) / (rect.width / 2);
            const dy   = (e.clientY - cy) / (rect.height / 2);
            card.style.transform = `translateY(-3px) rotateX(${-dy * 3}deg) rotateY(${dx * 3}deg)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    });

    // ── Smooth scroll for anchor links ────────────────────────────────────────
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const id = a.getAttribute('href').slice(1);
            const el = document.getElementById(id);
            if (el) {
                e.preventDefault();
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Back-to-top button ────────────────────────────────────────────────────
    const fab = document.createElement('button');
    fab.className = 'btn btn-primary back-to-top d-none';
    fab.innerHTML = '↑';
    fab.setAttribute('aria-label', '回到顶部');
    Object.assign(fab.style, {
        position:     'fixed',
        bottom:       '2rem',
        right:        '2rem',
        width:        '44px',
        height:       '44px',
        borderRadius: '50%',
        zIndex:       '1000',
        fontSize:     '1.2rem',
        padding:      '0',
        boxShadow:    '0 4px 16px rgba(0,0,0,.2)',
        transition:   'opacity .3s, transform .3s',
    });
    document.body.appendChild(fab);

    window.addEventListener('scroll', () => {
        if (window.scrollY > 400) {
            fab.classList.remove('d-none');
        } else {
            fab.classList.add('d-none');
        }
    }, { passive: true });

    fab.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // ── Toast auto-dismiss ────────────────────────────────────────────────────
    document.querySelectorAll('.alert.fade').forEach(alert => {
        setTimeout(() => {
            if (window.bootstrap?.Alert) {
                new bootstrap.Alert(alert).close();
            }
        }, 6000);
    });
});
