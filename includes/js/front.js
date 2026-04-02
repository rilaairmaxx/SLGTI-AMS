/* ── Navbar scroll effect ── */
const nav = document.querySelector('.slgti-nav');
if (nav) {
    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
}

/* ── Mobile nav toggle ── */
const navToggle  = document.getElementById('navToggle');
const mobileMenu = document.getElementById('mobileMenu');
const navClose   = document.getElementById('navClose');

function openMobileNav() {
    if (!mobileMenu) return;
    mobileMenu.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeMobileNav() {
    if (!mobileMenu) return;
    mobileMenu.style.display = 'none';
    document.body.style.overflow = '';
}

if (navToggle)  navToggle.addEventListener('click', openMobileNav);
if (navClose)   navClose.addEventListener('click',  closeMobileNav);

/* Close on Escape */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeMobileNav();
});

/* ── Scroll-reveal ── */
const revealEls = document.querySelectorAll('.reveal');
if (revealEls.length) {
    const io = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    revealEls.forEach(el => io.observe(el));
}

/* ── Active nav link on scroll ── */
const sections = document.querySelectorAll('section[id]');
const navLinks  = document.querySelectorAll('.nav-link-custom[href^="#"]');

function updateActiveLink() {
    let current = '';
    sections.forEach(sec => {
        if (window.scrollY >= sec.offsetTop - 100) current = sec.id;
    });
    navLinks.forEach(link => {
        link.classList.toggle('active', link.getAttribute('href') === '#' + current);
    });
}

window.addEventListener('scroll', updateActiveLink, { passive: true });

/* ── Mobile menu touch improvements ── */
if (mobileMenu) {
    mobileMenu.addEventListener('touchmove', function(e) {
        if (e.target.tagName !== 'A') {
            e.preventDefault();
        }
    }, { passive: false });
}
