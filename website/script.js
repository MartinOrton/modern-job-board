document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-demo-path]').forEach((link) => {
        const path = link.getAttribute('data-demo-path') || '/jobs/';
        link.setAttribute('href', path.startsWith('/') ? path : `/${path}`);
    });

    // Docs reference: mobile TOC + scrollspy
    const docsTocToggle = document.getElementById('docs-toc-toggle');
    const docsSidebarNav = document.getElementById('docs-sidebar-nav');
    if (docsTocToggle && docsSidebarNav) {
        docsTocToggle.addEventListener('click', () => {
            const open = docsSidebarNav.classList.toggle('is-open');
            docsTocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        docsSidebarNav.querySelectorAll('a[href^="#"]').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 959px)').matches) {
                    docsSidebarNav.classList.remove('is-open');
                    docsTocToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    const docsSections = document.querySelectorAll('.docs-section[id], .docs-endpoint[id]');
    const docsNavLinks = document.querySelectorAll('.docs-sidebar-nav a[href^="#"]');
    if (docsSections.length && docsNavLinks.length) {
        const setActiveDocsLink = (id) => {
            docsNavLinks.forEach((a) => {
                const href = a.getAttribute('href') || '';
                a.classList.toggle('is-active', href === `#${id}`);
            });
        };
        const docsObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && entry.target.id) {
                        setActiveDocsLink(entry.target.id);
                    }
                });
            },
            { rootMargin: '-20% 0px -65% 0px', threshold: 0 }
        );
        docsSections.forEach((section) => docsObserver.observe(section));
        if (location.hash) {
            setActiveDocsLink(location.hash.slice(1));
        }
    }
    // FAQ accordion (home page) - CSS grid height + icon rotation handle motion
    document.querySelectorAll('[data-faq-accordion]').forEach((list) => {
        list.querySelectorAll('.faq-item').forEach((item) => {
            const btn = item.querySelector('.faq-question');
            const panel = item.querySelector('.faq-panel') || item.querySelector('.faq-answer');
            if (!btn || !panel) {
                return;
            }

            const setOpen = (el, open) => {
                el.classList.toggle('is-open', open);
                const elBtn = el.querySelector('.faq-question');
                const elPanel = el.querySelector('.faq-panel') || el.querySelector('.faq-answer');
                if (elBtn) {
                    elBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
                if (elPanel) {
                    elPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
                }
            };

            btn.addEventListener('click', () => {
                const willOpen = !item.classList.contains('is-open');

                list.querySelectorAll('.faq-item.is-open').forEach((openItem) => {
                    if (openItem !== item) {
                        setOpen(openItem, false);
                    }
                });

                setOpen(item, willOpen);
            });
        });
    });

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

    // Subtle press feedback for interactive controls (adds a short CSS hook)
    document.querySelectorAll('.btn, .share-btn, .back-to-top, .mobile-toggle').forEach((el) => {
        el.addEventListener('pointerdown', () => el.classList.add('is-pressed'));
        const clearPress = () => el.classList.remove('is-pressed');
        el.addEventListener('pointerup', clearPress);
        el.addEventListener('pointerleave', clearPress);
        el.addEventListener('pointercancel', clearPress);
    });

    // Hero job rows: keyboard-friendly focus outline via class
    document.querySelectorAll('.job-item').forEach((item) => {
        item.setAttribute('tabindex', '0');
        item.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                item.classList.toggle('is-active');
            }
        });
    });

    // Navbar scroll effect + layout offsets for sticky sidebar / content hero
    const navbar = document.getElementById('navbar');
    const root = document.documentElement;

    const isDemoNavbar = navbar && navbar.classList.contains('navbar--demo');

    /**
     * Convert a CSS length to pixels. Never treat "2.5rem" as 2.5px.
     * Only bare "Npx" values are trusted without a probe measure.
     */
    const readCssLengthPx = (value, fallback = 0) => {
        if (value == null) {
            return fallback;
        }
        const trimmed = String(value).trim();
        if (!trimmed) {
            return fallback;
        }
        // Resolved px only (e.g. "80px"). "2.5rem" must go through the probe.
        if (/^-?[\d.]+px$/i.test(trimmed)) {
            const px = parseFloat(trimmed);
            return Number.isNaN(px) ? fallback : px;
        }
        // Resolve rem/em/%/calc()/var() via a temporary element.
        const probe = document.createElement('div');
        probe.style.cssText = [
            'position:absolute',
            'visibility:hidden',
            'pointer-events:none',
            'height:0',
            'width:0',
            'padding:0',
            'border:0',
            'margin:0',
            `top:${trimmed}`,
        ].join(';');
        document.body.appendChild(probe);
        const px = parseFloat(getComputedStyle(probe).top);
        document.body.removeChild(probe);
        return Number.isNaN(px) ? fallback : px;
    };

    const updateNavbarLayout = () => {
        if (!navbar) {
            return;
        }

        if (isDemoNavbar || window.scrollY > 10) {
            navbar.classList.add('scrolled');
            document.body.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('scrolled');
            document.body.classList.remove('navbar-scrolled');
        }

        const adminBar = document.getElementById('wpadminbar');
        const adminBarHeight = adminBar ? adminBar.offsetHeight : 0;
        const navbarHeight = navbar.offsetHeight;
        const headerOffset = adminBarHeight + navbarHeight;
        // --mjb-hero-edge is typically "2.5rem" (~40px) — must resolve, not parseFloat.
        const heroEdge = readCssLengthPx(
            getComputedStyle(root).getPropertyValue('--mjb-hero-edge'),
            40
        );
        // Pixel value so JS scroll math matches sticky Filter box top.
        const contentStickyTop = headerOffset + heroEdge;

        root.style.setProperty('--mjb-admin-bar-height', `${adminBarHeight}px`);
        root.style.setProperty('--mjb-navbar-height', `${navbarHeight}px`);
        root.style.setProperty('--mjb-header-offset', `${headerOffset}px`);
        root.style.setProperty('--mjb-content-sticky-top', `${contentStickyTop}px`);
    };

    updateNavbarLayout();
    window.addEventListener('scroll', updateNavbarLayout, { passive: true });
    window.addEventListener('resize', updateNavbarLayout);

    // Mobile Menu Toggle
    const mobileToggle = document.getElementById('mobile-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    const closeMobileMenu = () => {
        if (!mobileMenu || !mobileToggle) {
            return;
        }

        mobileMenu.classList.remove('open');
        mobileToggle.setAttribute('aria-expanded', 'false');

        const iconMenu = mobileToggle.querySelector('.icon-menu');
        const iconClose = mobileToggle.querySelector('.icon-close');
        if (iconMenu) {
            iconMenu.classList.remove('hidden');
        }
        if (iconClose) {
            iconClose.classList.add('hidden');
        }
    };

    if (mobileToggle && mobileMenu) {
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.setAttribute('aria-controls', 'mobile-menu');

        mobileToggle.addEventListener('click', () => {
            const isOpen = mobileMenu.classList.toggle('open');
            mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            const iconMenu = mobileToggle.querySelector('.icon-menu');
            const iconClose = mobileToggle.querySelector('.icon-close');

            if (iconMenu && iconClose) {
                if (isOpen) {
                    iconMenu.classList.add('hidden');
                    iconClose.classList.remove('hidden');
                } else {
                    iconMenu.classList.remove('hidden');
                    iconClose.classList.add('hidden');
                }
            }
        });

        // Close on any mobile drawer link (fallback + assigned menus).
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenu);
        });
    }

    // Social Share Button Interactions (Prevent bubbling if clicking share)
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
        });
    });

    // Back to Top functionality
    const backToTopBtn = document.getElementById('back-to-top');

    if (backToTopBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 500) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        }, { passive: true });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Scroll offset matches sticky Filter box top (resolved pixels only).
    const getContentStickyOffset = () => {
        // Prefer live sticky sidebar top when present — source of truth for alignment.
        const sidebar = document.querySelector('.mjb-sidebar');
        if (sidebar) {
            const sidebarTop = parseFloat(getComputedStyle(sidebar).top);
            if (!Number.isNaN(sidebarTop) && sidebarTop >= 0) {
                return sidebarTop;
            }
        }

        const styles = getComputedStyle(root);
        const sticky = parseFloat(styles.getPropertyValue('--mjb-content-sticky-top'));
        if (!Number.isNaN(sticky) && sticky > 0) {
            return sticky;
        }
        const header = parseFloat(styles.getPropertyValue('--mjb-header-offset'));
        if (!Number.isNaN(header) && header > 0) {
            return header;
        }
        return navbar ? navbar.offsetHeight : 80;
    };

    const scrollToTarget = (targetElement, behavior = 'smooth') => {
        if (!targetElement) {
            return;
        }
        // Ensure navbar offset vars are current before measuring.
        updateNavbarLayout();
        const offset = getContentStickyOffset();
        const top = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
        window.scrollTo({
            top: Math.max(0, top),
            behavior
        });
    };

    const resolveHashTarget = (href) => {
        if (!href || href === '#') {
            return null;
        }
        let hash = '';
        if (href.startsWith('#')) {
            hash = href;
        } else {
            try {
                const url = new URL(href, window.location.href);
                if (url.pathname.replace(/\/+$/, '') !== window.location.pathname.replace(/\/+$/, '')) {
                    return null; // different page — let the browser navigate
                }
                hash = url.hash;
            } catch (err) {
                return null;
            }
        }
        if (!hash || hash === '#') {
            return null;
        }
        try {
            return document.querySelector(hash);
        } catch (err) {
            return null;
        }
    };

    // Smooth scroll for in-page anchors (hash-only and same-path + hash).
    document.querySelectorAll('a[href*="#"]').forEach((anchor) => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            const targetElement = resolveHashTarget(href);
            if (!targetElement) {
                return;
            }
            e.preventDefault();
            const hash = href.includes('#') ? `#${href.split('#').pop()}` : href;
            if (history.pushState) {
                history.pushState(null, '', hash);
            }
            scrollToTarget(targetElement, 'smooth');
        });
    });

    // Initial load / back-forward with a hash.
    if (window.location.hash && window.location.hash !== '#') {
        const initialTarget = resolveHashTarget(window.location.hash);
        if (initialTarget) {
            // Wait a frame so layout/fonts settle, then snap with correct offset.
            window.requestAnimationFrame(() => {
                scrollToTarget(initialTarget, 'auto');
            });
        }
    }
});
