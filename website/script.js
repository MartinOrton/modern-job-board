document.addEventListener('DOMContentLoaded', () => {
    // Reveal Animations
    const observerOptions = {
        root: null,
        rootMargin: '0px 0px -50px 0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                obs.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    // Navbar Scroll Effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 10) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile Menu Toggle
    const mobileToggle = document.getElementById('mobile-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    const iconMenu = mobileToggle.querySelector('.icon-menu');
    const iconClose = mobileToggle.querySelector('.icon-close');

    mobileToggle.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.toggle('open');
        
        if (isOpen) {
            iconMenu.classList.add('hidden');
            iconClose.classList.remove('hidden');
        } else {
            iconMenu.classList.remove('hidden');
            iconClose.classList.add('hidden');
        }
    });

    // Close mobile menu when clicking a link
    document.querySelectorAll('.mobile-link').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
            iconMenu.classList.remove('hidden');
            iconClose.classList.add('hidden');
        });
    });

    // Social Share Button Interactions (Prevent bubbling if clicking share)
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            // In a real app, this would trigger a share action
            console.log('Share clicked:', btn.getAttribute('aria-label'));
        });
    });
});