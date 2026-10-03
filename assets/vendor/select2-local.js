(() => {
    'use strict';

    const instances = [];

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const closeInstance = (instance, restoreFocus = false) => {
        if (!instance?.wrap.classList.contains('is-open')) {
            return;
        }

        instance.wrap.classList.remove('is-open', 'is-dropup');
        instance.button.setAttribute('aria-expanded', 'false');
        instance.search.value = '';
        instance.filter('');

        if (restoreFocus) {
            instance.button.focus({ preventScroll: true });
        }
    };

    const closeAll = (except = null) => {
        instances.forEach((instance) => {
            if (instance !== except) {
                closeInstance(instance);
            }
        });
    };

    const enhance = (select, index) => {
        if (select.dataset.select2LocalReady === '1' || select.multiple) {
            return;
        }

        select.dataset.select2LocalReady = '1';
        select.classList.add('select2-local-native');

        const wrap = document.createElement('div');
        wrap.className = 'select2-local';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'select2-local__button';
        button.id = `select2-local-button-${index}`;
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');

        const dropdown = document.createElement('div');
        dropdown.className = 'select2-local__dropdown';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'select2-local__search-wrap';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'select2-local__search';
        search.placeholder = 'Buscar opción…';
        search.autocomplete = 'off';
        search.setAttribute('aria-label', 'Buscar opción');

        const options = document.createElement('div');
        options.className = 'select2-local__options';
        options.id = `select2-local-list-${index}`;
        options.setAttribute('role', 'listbox');
        button.setAttribute('aria-controls', options.id);

        const empty = document.createElement('div');
        empty.className = 'select2-local__empty';
        empty.textContent = 'No se encontraron opciones.';
        empty.hidden = true;

        searchWrap.appendChild(search);
        dropdown.append(searchWrap, options, empty);
        wrap.append(button, dropdown);
        select.insertAdjacentElement('afterend', wrap);

        const optionButtons = [];

        const sync = () => {
            const selected = select.options[select.selectedIndex];
            button.textContent = selected?.textContent || 'Selecciona una opción';
            button.disabled = select.disabled;

            optionButtons.forEach((optionButton, optionIndex) => {
                optionButton.setAttribute(
                    'aria-selected',
                    String(optionIndex === select.selectedIndex)
                );
            });
        };

        const selectIndex = (optionIndex) => {
            const option = select.options[optionIndex];

            if (!option || option.disabled) {
                return;
            }

            select.selectedIndex = optionIndex;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closeInstance(instance, true);
        };

        const filter = (term) => {
            const query = normalize(term);
            let visible = 0;

            optionButtons.forEach((optionButton) => {
                const match = !query || normalize(optionButton.textContent).includes(query);
                optionButton.hidden = !match;

                if (match) {
                    visible += 1;
                }
            });

            empty.hidden = visible > 0;
        };

        const open = () => {
            if (select.disabled) {
                return;
            }

            closeAll(instance);

            const rect = wrap.getBoundingClientRect();
            const availableBelow = window.innerHeight - rect.bottom;
            const availableAbove = rect.top;

            wrap.classList.toggle(
                'is-dropup',
                availableBelow < 340 && availableAbove > availableBelow
            );
            wrap.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');

            window.requestAnimationFrame(() => {
                search.focus({ preventScroll: true });
                search.select();
            });
        };

        [...select.options].forEach((option, optionIndex) => {
            const optionButton = document.createElement('button');
            optionButton.type = 'button';
            optionButton.className = 'select2-local__option';
            optionButton.textContent = option.textContent;
            optionButton.disabled = option.disabled;
            optionButton.setAttribute('role', 'option');
            optionButton.setAttribute('aria-selected', 'false');

            optionButton.addEventListener('click', () => selectIndex(optionIndex));
            optionButtons.push(optionButton);
            options.appendChild(optionButton);
        });

        const instance = {
            select,
            wrap,
            button,
            search,
            filter,
        };

        instances.push(instance);

        button.addEventListener('click', (event) => {
            event.stopPropagation();

            if (wrap.classList.contains('is-open')) {
                closeInstance(instance, true);
            } else {
                open();
            }
        });

        button.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }

            if (event.key === 'Escape') {
                closeInstance(instance, true);
            }
        });

        search.addEventListener('input', () => filter(search.value));
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeInstance(instance, true);
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                optionButtons.find((item) => !item.hidden && !item.disabled)?.focus();
            }
        });

        optionButtons.forEach((optionButton, optionIndex) => {
            optionButton.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeInstance(instance, true);
                    return;
                }

                if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                    return;
                }

                event.preventDefault();
                const visible = optionButtons.filter((item) => !item.hidden && !item.disabled);
                const current = visible.indexOf(optionButton);
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                const next = (current + direction + visible.length) % visible.length;
                visible[next]?.focus();
            });
        });

        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => window.setTimeout(sync, 0));
        sync();
    };

    document.querySelectorAll('select:not([multiple]):not([data-native-select])')
        .forEach(enhance);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.select2-local')) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });

    window.addEventListener('resize', () => closeAll(), { passive: true });
})();
