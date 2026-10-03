const toggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.site-header nav');
const header = document.querySelector('.site-header');

toggle?.addEventListener('click', () => {
    const open = document.body.classList.toggle('nav-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
});

nav?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
        document.body.classList.remove('nav-open');
        toggle?.setAttribute('aria-expanded', 'false');
    });
});

window.addEventListener(
    'scroll',
    () => {
        header?.classList.toggle('scrolled', window.scrollY > 20);
    },
    { passive: true }
);

(() => {
    const elements = [...document.querySelectorAll('[data-reveal]')];

    if (!elements.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px',
        }
    );

    elements.forEach((element) => observer.observe(element));
})();

(() => {
    const zone = document.querySelector('[data-public-dropzone]');
    const input = document.querySelector('[data-public-file-input]');
    const selectButton = document.querySelector('[data-public-file-select]');
    const list = document.querySelector('[data-public-file-list]');

    if (!zone || !input || !list) {
        return;
    }

    let files = [];
    const maxFiles = 3;
    const maxSize = 3 * 1024 * 1024;

    const key = (file) => `${file.name}|${file.size}|${file.lastModified}`;
    const allowed = (file) =>
        ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) &&
        file.size <= maxSize;

    const humanSize = (bytes) => {
        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1048576) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(bytes / 1048576).toFixed(1)} MB`;
    };

    const render = () => {
        list.innerHTML = '';

        files.forEach((file, index) => {
            const item = document.createElement('div');
            const icon = document.createElement('span');
            const meta = document.createElement('span');
            const name = document.createElement('strong');
            const size = document.createElement('small');
            const remove = document.createElement('button');

            item.className = 'public-file-item';
            icon.textContent = '▧';
            icon.setAttribute('aria-hidden', 'true');
            meta.className = 'file-meta';
            name.textContent = file.name;
            size.textContent = humanSize(file.size);
            remove.type = 'button';
            remove.className = 'public-file-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Quitar ${file.name}`);

            remove.addEventListener('click', () => {
                files.splice(index, 1);
                sync();
            });

            meta.append(name, size);
            item.append(icon, meta, remove);
            list.append(item);
        });
    };

    const sync = () => {
        const dataTransfer = new DataTransfer();
        files.forEach((file) => dataTransfer.items.add(file));
        input.files = dataTransfer.files;
        render();
    };

    const addFiles = (incoming) => {
        const current = new Set(files.map(key));

        for (const file of [...incoming]) {
            if (files.length >= maxFiles) {
                break;
            }

            if (!allowed(file) || current.has(key(file))) {
                continue;
            }

            files.push(file);
            current.add(key(file));
        }

        sync();
    };

    selectButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        input.click();
    });

    zone.addEventListener('click', (event) => {
        if (!event.target.closest('button')) {
            input.click();
        }
    });

    zone.addEventListener('keydown', (event) => {
        if (
            (event.key === 'Enter' || event.key === ' ') &&
            !event.target.closest('button')
        ) {
            event.preventDefault();
            input.click();
        }
    });

    input.addEventListener('change', () => addFiles(input.files));

    ['dragenter', 'dragover'].forEach((eventName) => {
        zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');
        });
    });

    zone.addEventListener('drop', (event) => {
        addFiles(event.dataTransfer?.files || []);
    });

    zone.addEventListener('paste', (event) => {
        const pastedFiles = [...(event.clipboardData?.files || [])];

        if (!pastedFiles.length) {
            return;
        }

        event.preventDefault();
        addFiles(pastedFiles);
    });
})();

document.querySelectorAll('[data-public-notify]').forEach((element) => {
    if (!window.showNotify) {
        return;
    }

    window.showNotify(
        element.dataset.message || '',
        element.dataset.type || 'info',
        { duration: 5200 }
    );
});

(() => {
    const modal = document.querySelector('[data-site-lightbox-modal]');

    if (!modal) {
        return;
    }

    const image = modal.querySelector('[data-site-lightbox-image]');
    const title = modal.querySelector('[data-site-lightbox-title]');
    const caption = modal.querySelector('[data-site-lightbox-caption]');
    const closeButtons = [...modal.querySelectorAll('[data-site-lightbox-close]')];
    let returnFocus = null;

    const open = (trigger) => {
        returnFocus = trigger;
        image.src = trigger.dataset.lightboxSrc || '';
        image.alt = trigger.dataset.lightboxTitle || 'Vista ampliada';
        title.textContent = trigger.dataset.lightboxTitle || 'Vista ampliada';
        caption.textContent = trigger.dataset.lightboxCaption || '';
        caption.hidden = !caption.textContent.trim();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lightbox-open');

        window.requestAnimationFrame(() => {
            modal.classList.add('is-open');
        });

        window.setTimeout(() => {
            modal
                .querySelector('.site-lightbox-close')
                ?.focus({ preventScroll: true });
        }, 30);
    };

    const close = () => {
        if (modal.hidden) {
            return;
        }

        modal.classList.remove('is-open');
        document.body.classList.remove('lightbox-open');

        window.setTimeout(() => {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            image.src = '';
            returnFocus?.focus?.({ preventScroll: true });
        }, 180);
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-site-lightbox]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        open(trigger);
    });

    closeButtons.forEach((element) => {
        element.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
})();

(() => {
    const links = [...document.querySelectorAll('.nav-section-link[href^="#"]')];

    if (!links.length) {
        return;
    }

    const sections = links
        .map((link) => {
            const id = link.getAttribute('href')?.slice(1) || '';
            const section = id ? document.getElementById(id) : null;
            return section ? { id, link, section } : null;
        })
        .filter(Boolean);

    if (!sections.length) {
        return;
    }

    const setActive = (id) => {
        sections.forEach((item) => {
            const active = item.id === id;
            item.link.classList.toggle('is-active', active);

            if (active) {
                item.link.setAttribute('aria-current', 'true');
            } else {
                item.link.removeAttribute('aria-current');
            }
        });
    };

    let ticking = false;

    const update = () => {
        ticking = false;
        const headerHeight = header?.offsetHeight || 82;
        const marker = headerHeight + Math.min(180, window.innerHeight * 0.28);
        let current = sections[0].id;

        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 8) {
            current = sections[sections.length - 1].id;
        } else {
            sections.forEach((item) => {
                const rect = item.section.getBoundingClientRect();

                if (rect.top <= marker) {
                    current = item.id;
                }
            });
        }

        setActive(current);
    };

    const scheduleUpdate = () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(update);
    };

    links.forEach((link) => {
        link.addEventListener('click', () => {
            const id = link.getAttribute('href')?.slice(1);
            if (id) {
                setActive(id);
            }
        });
    });

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    window.addEventListener('load', scheduleUpdate, { once: true });
    scheduleUpdate();
})();
