(() => {
    'use strict';

    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'H2', 'H3', 'A']);
    const formatButtons = [
        ['bold', '<strong>B</strong>', 'Negrita'],
        ['italic', '<em>I</em>', 'Cursiva'],
        ['underline', '<u>U</u>', 'Subrayado'],
        ['strikeThrough', '<s>S</s>', 'Tachado']
    ];

    const normalizeUrl = (value) => {
        const url = String(value || '').trim();
        if (!url) return '';
        if (/^(https?:|mailto:|tel:|\/|#)/i.test(url)) return url;
        if (/^[\w.-]+\.[a-z]{2,}(?:\/.*)?$/i.test(url)) return `https://${url}`;
        return '';
    };

    const sanitize = (html) => {
        const tpl = document.createElement('template');
        tpl.innerHTML = String(html || '');
        const walk = (node) => {
            [...node.childNodes].forEach((child) => {
                if (child.nodeType === Node.COMMENT_NODE) {
                    child.remove();
                    return;
                }
                if (child.nodeType !== Node.ELEMENT_NODE) return;
                if (!allowedTags.has(child.tagName)) {
                    const fragment = document.createDocumentFragment();
                    while (child.firstChild) fragment.appendChild(child.firstChild);
                    child.replaceWith(fragment);
                    walk(node);
                    return;
                }
                const hrefBeforeCleanup = child.tagName === 'A' ? normalizeUrl(child.getAttribute('href') || '') : '';
                [...child.attributes].forEach((attr) => child.removeAttribute(attr.name));
                if (child.tagName === 'A' && hrefBeforeCleanup) {
                    child.setAttribute('href', hrefBeforeCleanup);
                    child.setAttribute('target', '_blank');
                    child.setAttribute('rel', 'noopener noreferrer');
                }
                walk(child);
            });
        };
        walk(tpl.content);
        return tpl.innerHTML.trim();
    };

    const plainToHtml = (value) => {
        const text = String(value || '').replace(/\r\n?/g, '\n');
        if (!text) return '';
        if (/<\/?(?:p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|h2|h3|a)\b/i.test(text)) return sanitize(text);
        const escape = (input) => input.replace(/[&<>]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[char]));
        return text.split(/\n{2,}/).map((block) => `<p>${escape(block).replace(/\n/g, '<br>')}</p>`).join('');
    };

    const button = (command, html, label, value = '') => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'cms-rte__button';
        el.dataset.rteCommand = command;
        if (value) el.dataset.rteValue = value;
        el.setAttribute('aria-label', label);
        el.title = label;
        el.innerHTML = html;
        return el;
    };

    const group = (...items) => {
        const el = document.createElement('div');
        el.className = 'cms-rte__group';
        items.forEach((item) => el.appendChild(item));
        return el;
    };

    const enhance = (textarea) => {
        if (!textarea || textarea.dataset.rteReady === '1') return;
        if (textarea.matches('[data-rich-editor-input], .code-area, [data-rte-off]')) {
            if (textarea.matches('.code-area')) textarea.classList.add('cms-code-textarea');
            return;
        }

        textarea.dataset.rteReady = '1';
        const wasRequired = textarea.required;
        if (wasRequired) textarea.required = false;
        textarea.classList.add('cms-rte-source');

        const editor = document.createElement('div');
        editor.className = `cms-rte${textarea.dataset.rteSize === 'compact' ? ' cms-rte--compact' : ''}`;
        editor.dataset.cmsRichText = '';

        const toolbar = document.createElement('div');
        toolbar.className = 'cms-rte__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Formato del texto');

        toolbar.appendChild(group(...formatButtons.map(([cmd, html, label]) => button(cmd, html, label))));
        toolbar.appendChild(group(
            button('insertUnorderedList', '• Lista', 'Lista con viñetas'),
            button('insertOrderedList', '1. Lista', 'Lista numerada'),
            button('formatBlock', '❝', 'Cita', 'blockquote')
        ));
        const linkToggle = document.createElement('button');
        linkToggle.type = 'button';
        linkToggle.className = 'cms-rte__link-toggle';
        linkToggle.innerHTML = '🔗';
        linkToggle.setAttribute('aria-label', 'Agregar enlace');
        linkToggle.title = 'Agregar enlace';
        toolbar.appendChild(group(
            linkToggle,
            button('undo', '↶', 'Deshacer'),
            button('redo', '↷', 'Rehacer'),
            button('removeFormat', 'Tx', 'Limpiar formato')
        ));

        const linkPanel = document.createElement('div');
        linkPanel.className = 'cms-rte__link-panel';
        const linkInput = document.createElement('input');
        linkInput.type = 'url';
        linkInput.className = 'cms-rte__link-input';
        linkInput.placeholder = 'https://ejemplo.com';
        linkInput.setAttribute('aria-label', 'URL del enlace');
        const linkApply = document.createElement('button');
        linkApply.type = 'button';
        linkApply.className = 'cms-rte__link-apply';
        linkApply.textContent = 'Aplicar';
        const linkCancel = document.createElement('button');
        linkCancel.type = 'button';
        linkCancel.className = 'cms-rte__link-cancel';
        linkCancel.textContent = 'Cancelar';
        linkPanel.append(linkInput, linkApply, linkCancel);
        toolbar.appendChild(linkPanel);

        const content = document.createElement('div');
        content.className = 'cms-rte__content';
        content.contentEditable = 'true';
        content.setAttribute('role', 'textbox');
        content.setAttribute('aria-multiline', 'true');
        content.setAttribute('aria-label', textarea.getAttribute('aria-label') || textarea.name || 'Editor de texto enriquecido');
        content.dataset.placeholder = textarea.getAttribute('placeholder') || 'Escribe aquí…';
        content.innerHTML = plainToHtml(textarea.value);

        const footer = document.createElement('div');
        footer.className = 'cms-rte__footer';
        footer.innerHTML = '<span><strong>Editor enriquecido</strong> · formato seguro</span><span data-rte-count>0 caracteres</span>';
        const count = footer.querySelector('[data-rte-count]');

        editor.append(toolbar, content, footer);
        textarea.insertAdjacentElement('afterend', editor);

        let savedRange = null;
        const rememberSelection = () => {
            const selection = window.getSelection();
            if (selection && selection.rangeCount && content.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
        };
        const restoreSelection = () => {
            if (!savedRange) return;
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        };
        const sync = () => {
            const cleaned = sanitize(content.innerHTML);
            textarea.value = cleaned;
            if (count) count.textContent = `${(content.innerText || '').trim().length} caracteres`;
        };
        const run = (command, value = null) => {
            content.focus();
            restoreSelection();
            document.execCommand(command, false, value);
            rememberSelection();
            sync();
        };

        toolbar.addEventListener('mousedown', (event) => {
            if (event.target.closest('button')) event.preventDefault();
        });
        toolbar.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-rte-command]');
            if (!btn) return;
            run(btn.dataset.rteCommand, btn.dataset.rteValue || null);
        });
        linkToggle.addEventListener('click', () => {
            rememberSelection();
            linkPanel.classList.toggle('is-open');
            if (linkPanel.classList.contains('is-open')) setTimeout(() => linkInput.focus(), 0);
        });
        linkCancel.addEventListener('click', () => {
            linkInput.value = '';
            linkPanel.classList.remove('is-open');
            content.focus();
            restoreSelection();
        });
        linkApply.addEventListener('click', () => {
            const url = normalizeUrl(linkInput.value);
            if (!url) {
                linkInput.focus();
                return;
            }
            restoreSelection();
            run('createLink', url);
            content.querySelectorAll('a').forEach((a) => {
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
            });
            linkInput.value = '';
            linkPanel.classList.remove('is-open');
            sync();
        });
        content.addEventListener('input', () => {
            editor.classList.remove('is-invalid');
            sync();
        });
        content.addEventListener('keyup', rememberSelection);
        content.addEventListener('mouseup', rememberSelection);
        content.addEventListener('paste', (event) => {
            event.preventDefault();
            const text = event.clipboardData?.getData('text/plain') || '';
            document.execCommand('insertText', false, text);
            sync();
        });
        content.addEventListener('blur', sync);
        textarea.form?.addEventListener('submit', (event) => {
            sync();
            if (wasRequired && !(content.innerText || '').replace(/\s+/g, '')) {
                event.preventDefault();
                editor.classList.add('is-invalid');
                content.focus();
                if (typeof window.showNotify === 'function') {
                    window.showNotify('warning', 'Completa el campo de texto antes de continuar.');
                }
            }
        });
        sync();
    };

    const init = (root = document) => {
        root.querySelectorAll('textarea').forEach(enhance);
    };

    document.addEventListener('DOMContentLoaded', () => init());
    window.CMSRichText = { init, sanitize };
})();
