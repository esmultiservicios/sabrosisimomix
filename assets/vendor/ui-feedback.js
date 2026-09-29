(() => {
  'use strict';

  const ICONS = { success: '✓', danger: '!', error: '!', warning: '!', info: 'i', question: '?' };

  function ensureToastHost() {
    let host = document.querySelector('[data-notify-host]');
    if (!host) {
      host = document.createElement('div');
      host.className = 'cms-notify-host';
      host.dataset.notifyHost = '1';
      host.setAttribute('aria-live', 'polite');
      host.setAttribute('aria-atomic', 'false');
      document.body.append(host);
    }
    return host;
  }

  window.showNotify = function showNotify(message, type = 'info', options = {}) {
    if (!message) return null;
    if (!['success','danger','error','warning','info'].includes(type)) type = 'info';

    const host = ensureToastHost();
    const toast = document.createElement('article');
    toast.className = `cms-notify cms-notify--${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'cms-notify__icon';
    icon.textContent = ICONS[type] || ICONS.info;

    const copy = document.createElement('div');
    copy.className = 'cms-notify__copy';
    const title = document.createElement('strong');
    title.textContent = options.title || ({ success:'Listo', danger:'Acción importante', error:'Ocurrió un problema', warning:'Atención', info:'Información' }[type]);
    const text = document.createElement('span');
    text.textContent = String(message);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'cms-notify__close';
    close.setAttribute('aria-label', 'Cerrar notificación');
    close.textContent = '×';

    copy.append(title, text);
    toast.append(icon, copy, close);
    host.append(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    const duration = Math.max(1800, Number(options.duration ?? (type === 'error' ? 6500 : 4200)));
    let timer = window.setTimeout(remove, duration);
    function remove() {
      if (!toast.isConnected) return;
      toast.classList.remove('is-visible');
      window.setTimeout(() => toast.remove(), 220);
    }
    close.addEventListener('click', () => { window.clearTimeout(timer); remove(); });
    toast.addEventListener('mouseenter', () => window.clearTimeout(timer));
    toast.addEventListener('mouseleave', () => { timer = window.setTimeout(remove, 1800); });
    return toast;
  };

  function ensureDialog() {
    let modal = document.querySelector('[data-cms-dialog]');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'cms-dialog';
    modal.dataset.cmsDialog = '1';
    modal.hidden = true;
    modal.innerHTML = `
      <div class="cms-dialog__backdrop" data-dialog-close></div>
      <section class="cms-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="cmsDialogTitle">
        <button type="button" class="cms-dialog__x" data-dialog-close aria-label="Cerrar">×</button>
        <div class="cms-dialog__symbol" data-dialog-icon></div>
        <div class="cms-dialog__body">
          <p class="cms-dialog__eyebrow" data-dialog-eyebrow>CONFIRMACIÓN</p>
          <h2 id="cmsDialogTitle" data-dialog-title></h2>
          <div class="cms-dialog__message" data-dialog-message></div>
        </div>
        <div class="cms-dialog__actions" data-dialog-actions></div>
      </section>`;
    document.body.append(modal);
    return modal;
  }

  let dialogResolve = null;
  let lastFocused = null;

  function closeDialog(result = { isConfirmed: false, isDismissed: true }) {
    const modal = document.querySelector('[data-cms-dialog]');
    if (!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('cms-dialog-open');
    window.setTimeout(() => {
      modal.hidden = true;
      const resolve = dialogResolve;
      dialogResolve = null;
      if (resolve) resolve(result);
      if (lastFocused?.isConnected) lastFocused.focus({ preventScroll: true });
    }, 170);
  }

  window.CMSDialog = {
    close: closeDialog,
    async open(options = {}) {
      const modal = ensureDialog();
      lastFocused = document.activeElement;
      const title = modal.querySelector('[data-dialog-title]');
      const message = modal.querySelector('[data-dialog-message]');
      const icon = modal.querySelector('[data-dialog-icon]');
      const actions = modal.querySelector('[data-dialog-actions]');
      const eyebrow = modal.querySelector('[data-dialog-eyebrow]');

      const type = options.icon || options.type || 'info';
      modal.dataset.type = type;
      title.textContent = options.title || 'Confirmación';
      eyebrow.textContent = options.eyebrow || (type === 'warning' ? 'ATENCIÓN' : 'CONFIRMACIÓN');
      icon.textContent = ICONS[type] || ICONS.info;

      message.innerHTML = '';
      if (options.html) {
        const holder = document.createElement('div');
        holder.innerHTML = options.html;
        message.append(...holder.childNodes);
      } else {
        const p = document.createElement('p');
        p.textContent = options.text || '';
        message.append(p);
      }

      actions.innerHTML = '';
      if (options.showCancelButton) {
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'cms-dialog__button cms-dialog__button--secondary';
        cancel.textContent = options.cancelButtonText || 'Cancelar';
        cancel.addEventListener('click', () => closeDialog({ isConfirmed: false, isDismissed: true }));
        actions.append(cancel);
      }

      const confirm = document.createElement('button');
      confirm.type = 'button';
      confirm.className = `cms-dialog__button cms-dialog__button--${type === 'error' || type === 'warning' ? 'danger' : 'primary'}`;
      confirm.textContent = options.confirmButtonText || 'Aceptar';
      confirm.addEventListener('click', () => closeDialog({ isConfirmed: true, isDismissed: false }));
      actions.append(confirm);

      modal.querySelectorAll('[data-dialog-close]').forEach(el => {
        el.onclick = () => {
          if (options.allowOutsideClick === false && el.classList.contains('cms-dialog__backdrop')) return;
          closeDialog({ isConfirmed: false, isDismissed: true });
        };
      });

      modal.hidden = false;
      document.body.classList.add('cms-dialog-open');
      requestAnimationFrame(() => modal.classList.add('is-open'));
      window.setTimeout(() => confirm.focus({ preventScroll: true }), 40);
      return await new Promise(resolve => { dialogResolve = resolve; });
    }
  };

  window.Swal = window.Swal || {};
  window.Swal.fire = (options = {}) => window.CMSDialog.open(options);

  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const modal = document.querySelector('[data-cms-dialog]');
    if (!modal || modal.hidden) return;
    closeDialog({ isConfirmed: false, isDismissed: true });
  });

  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-premium-confirm]');
    if (!form || form.dataset.confirmApproved === '1') return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const result = await window.Swal.fire({
      icon: form.dataset.confirmIcon || 'warning',
      title: form.dataset.confirmTitle || '¿Confirmar acción?',
      text: form.dataset.confirmMessage || 'Esta acción requiere confirmación.',
      showCancelButton: true,
      confirmButtonText: form.dataset.confirmOk || 'Sí, continuar',
      cancelButtonText: form.dataset.confirmCancel || 'Cancelar',
      allowOutsideClick: false
    });
    if (result.isConfirmed) {
      form.dataset.confirmApproved = '1';
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    }
  }, true);
})();
