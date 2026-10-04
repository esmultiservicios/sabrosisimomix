const AdminOverlayManager = window.AdminOverlayManager = (() => {
  const sidebar = document.querySelector('[data-sidebar]');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
  const quickFind = document.querySelector('[data-admin-find]');
  const quickFindInput = document.querySelector('[data-admin-find-input]');
  const quickFindTriggers = [...document.querySelectorAll('[data-admin-find-open]')];
  let quickFindReturnFocus = null;

  const setSidebarState = (open) => {
    sidebar?.classList.toggle('open', open);
    sidebarBackdrop?.classList.toggle('show', open);
    sidebarToggle?.setAttribute('aria-expanded', String(open));
    sidebarToggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };

  const closeSide = () => setSidebarState(false);

  const closeTopbarMenus = (except = null) => {
    document.querySelectorAll('.admin-header details[open]').forEach((menu) => {
      if (menu !== except) menu.open = false;
    });
  };

  const closeAdminFind = ({ restoreFocus = false } = {}) => {
    const wasOpen = quickFind && !quickFind.hidden;
    if (quickFind) quickFind.hidden = true;
    document.body.classList.remove('admin-find-open');
    if (quickFindInput) quickFindInput.value = '';
    quickFindTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));

    if (restoreFocus && wasOpen && quickFindReturnFocus?.isConnected) {
      window.setTimeout(() => quickFindReturnFocus.focus({ preventScroll: true }), 0);
    }
  };

  const openSide = () => {
    closeTopbarMenus();
    closeAdminFind();
    setSidebarState(true);
  };

  const openAdminFind = (trigger = null) => {
    closeSide();
    closeTopbarMenus();
    quickFindReturnFocus = trigger || (typeof document.activeElement?.focus === 'function' ? document.activeElement : null);
    if (quickFind) quickFind.hidden = false;
    document.body.classList.add('admin-find-open');
    quickFindTriggers.forEach((button) => button.setAttribute('aria-expanded', 'true'));
    window.setTimeout(() => quickFindInput?.focus(), 30);
  };

  return Object.freeze({
    closeSide,
    closeTopbarMenus,
    closeAdminFind,
    openSide,
    openAdminFind,
    isSideOpen: () => sidebar?.classList.contains('open') ?? false,
  });
})();

window.addEventListener('orientationchange',()=> {
  window.requestAnimationFrame(()=> {
    AdminOverlayManager.closeSide();
    AdminOverlayManager.closeTopbarMenus();
    AdminOverlayManager.closeAdminFind();
  });
});

(()=> {
  const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)]; const toggle=q('[data-sidebar-toggle]'),back=q('[data-sidebar-backdrop]');
  toggle?.addEventListener('click',()=> {
    if(AdminOverlayManager.isSideOpen())AdminOverlayManager.closeSide();else AdminOverlayManager.openSide();
  }
  );back?.addEventListener('click',AdminOverlayManager.closeSide);qa('.admin-sidebar a').forEach(a=>a.addEventListener('click',AdminOverlayManager.closeSide)); qa('.profile-menu').forEach(d=>document.addEventListener('click',e=> {
    if(d.open&&!d.contains(e.target))d.open=false
  }
  )); qa('.action-menu').forEach(menu=> {
    menu.addEventListener('toggle',()=> {
      if(!menu.open)return;qa('.action-menu[open]').forEach(other=> {
        if(other!==menu)other.open=false
      }
      );const nav=q('nav',menu);menu.classList.remove('drop-up');if(nav) {
        const rect=nav.getBoundingClientRect();if(rect.bottom>window.innerHeight-12)menu.classList.add('drop-up');
      }
    }
    );q('nav',menu)?.addEventListener('click',e=> {
      if(e.target.closest('a,button'))menu.open=false;
    }
    );
  }
  ); qa('[data-stat]').forEach(el=> {
    const target=parseInt(el.dataset.stat||el.textContent,10)||0;let start=0;const dur=500,t0=performance.now();function tick(t) {
      const p=Math.min(1,(t-t0)/dur);el.textContent=Math.round(target*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(tick)
    }
    requestAnimationFrame(tick)
  }
  ); qa('.animate-in').forEach((el,i)=> {
    el.style.animationDelay=Math.min(i*45,360)+'ms'
  }
  ); const flash=q('[data-flash-message]');if(flash&&window.showNotify) {
    showNotify(flash.dataset.flashMessage||'',flash.dataset.flashType||'info');
  }
  qa('[data-inline-notify]').forEach(el=>{
    if(window.showNotify) showNotify(el.dataset.message||'',el.dataset.type||'info',{duration:6000});
  });
  const modal=q('[data-image-modal]'),mimg=q('[data-modal-image]'),mcap=q('[data-modal-caption]');function openModal(src,cap='') {
    if(!modal||!mimg)return;mimg.src=src;mcap.textContent=cap;modal.classList.add('open');modal.setAttribute('aria-hidden','false')
  }
  function closeModal() {
    modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');if(mimg)mimg.src=''
  }
  qa('[data-preview-src]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.previewSrc||'',b.dataset.previewCaption||'')));document.addEventListener('click',e=>{const img=e.target.closest('img.preview-thumb,.current-upload-preview img,.premium-upload-preview img');if(img){e.preventDefault();openModal(img.currentSrc||img.src,img.alt||'Vista ampliada');}});q('[data-modal-close]')?.addEventListener('click',closeModal);modal?.addEventListener('click',e=> {
    if(e.target===modal||e.target.classList.contains('admin-image-modal__backdrop'))closeModal()
  }
  );document.addEventListener('keydown',e=> {
    if(e.key==='Escape')closeModal()
  }
  ); function initUpload(zone) {
    const input=q('input[type=file]',zone);
    const preview=q('[data-upload-preview]',zone);
    const name=q('[data-upload-name]',zone);
    const fileList=q('[data-upload-file-list]',zone);
    const chooseButton=q('[data-upload-choose]',zone);
    if(!input)return;

    const multiple=input.multiple;
    const maxFiles=Math.max(1,Number.parseInt(input.dataset.maxFiles||'12',10)||12);
    const maxTotalBytes=Math.max(0,Number.parseInt(input.dataset.maxTotalBytes||'0',10)||0);
    const allowedExtensions=(input.dataset.allowedExtensions||'')
      .split(',')
      .map(value=>value.trim().toLowerCase())
      .filter(Boolean);
    const accepts=(input.getAttribute('accept')||'')
      .split(',')
      .map(value=>value.trim().toLowerCase())
      .filter(Boolean);
    let files=[];
    let dragDepth=0;

    const notify=(message)=> {
      if(window.showNotify)showNotify(message,'warning');
    };
    const fileKey=(file)=>[file.name,file.size,file.lastModified].join(':');
    const extensionOf=(file)=>file.name.includes('.')?file.name.split('.').pop().toLowerCase():'';
    const formatBytes=(bytes)=>bytes>=1048576
      ?(bytes/1048576).toFixed(2)+' MB'
      :(bytes/1024).toFixed(1)+' KB';
    const allowed=(file)=> {
      if(allowedExtensions.length)return allowedExtensions.includes(extensionOf(file));
      if(!accepts.length)return true;
      return accepts.some(rule=> {
        if(rule.startsWith('.'))return extensionOf(file)===rule.slice(1);
        if(rule.endsWith('/*'))return file.type.toLowerCase().startsWith(rule.slice(0,-1));
        return file.type.toLowerCase()===rule;
      });
    };
    const removeAt=(index)=> {
      files.splice(index,1);
      sync();
    };

    function render() {
      if(preview) {
        preview.innerHTML='';
        files.forEach((file,index)=> {
          const card=document.createElement('div');
          card.className='upload-preview-item';
          const remove=document.createElement('button');
          remove.type='button';
          remove.textContent='×';
          remove.setAttribute('aria-label','Remove '+file.name);
          remove.addEventListener('click',()=>removeAt(index));

          if(file.type.startsWith('image/')) {
            const image=document.createElement('img');
            image.alt='Preview of '+file.name;
            const url=URL.createObjectURL(file);
            image.src=url;
            image.addEventListener('load',()=>URL.revokeObjectURL(url),{once:true});
            card.append(image);
          } else if(file.type.startsWith('video/')) {
            const video=document.createElement('video');
            video.muted=true;
            video.playsInline=true;
            video.preload='metadata';
            const url=URL.createObjectURL(file);
            video.src=url;
            video.addEventListener('loadedmetadata',()=>URL.revokeObjectURL(url),{once:true});
            card.append(video);
          } else {
            const badge=document.createElement('div');
            badge.className='upload-file-badge';
            badge.textContent=extensionOf(file).toUpperCase()||'FILE';
            card.append(badge);
          }
          card.append(remove);
          preview.append(card);
        });
      }

      if(name)name.textContent=files.length
        ?files.length+' file'+(files.length>1?'s':'')+' selected · '+formatBytes(files.reduce((sum,file)=>sum+file.size,0))
        :(input.dataset.emptyLabel||'No attachments selected.');

      if(fileList) {
        fileList.innerHTML='';
        if(!files.length) {
          const empty=document.createElement('p');
          empty.className='reply-file-empty';
          empty.textContent='No attachments selected.';
          fileList.append(empty);
        }
        files.forEach((file,index)=> {
          const item=document.createElement('article');
          item.className='reply-file-item';
          const icon=document.createElement('span');
          icon.className='reply-file-icon';
          icon.setAttribute('aria-hidden','true');
          icon.textContent=extensionOf(file).toUpperCase()||'FILE';
          const copy=document.createElement('div');
          const fileName=document.createElement('strong');
          const fileSize=document.createElement('small');
          fileName.textContent=file.name;
          fileSize.textContent=formatBytes(file.size);
          copy.append(fileName,fileSize);
          const remove=document.createElement('button');
          remove.type='button';
          remove.className='button danger small';
          remove.textContent='Remove';
          remove.setAttribute('aria-label','Remove '+file.name);
          remove.addEventListener('click',()=>removeAt(index));
          item.append(icon,copy,remove);
          fileList.append(item);
        });
      }
    }

    function sync() {
      const transfer=new DataTransfer();
      files.forEach(file=>transfer.items.add(file));
      input.files=transfer.files;
      render();
    }

    function add(list) {
      const incoming=[...list];
      if(!incoming.length)return;
      const invalid=incoming.filter(file=>!allowed(file));
      if(invalid.length)notify('One or more files use a format that is not allowed.');
      const existing=new Set(files.map(fileKey));
      let next=multiple?[...files]:[];
      let limitExceeded=false;
      let sizeExceeded=false;

      incoming.filter(allowed).forEach(file=> {
        if(existing.has(fileKey(file)))return;
        if(!multiple&&next.length)next=[];
        if(next.length>=maxFiles) {
          limitExceeded=true;
          return;
        }
        const nextSize=next.reduce((sum,current)=>sum+current.size,0)+file.size;
        if(maxTotalBytes&&nextSize>maxTotalBytes) {
          sizeExceeded=true;
          return;
        }
        existing.add(fileKey(file));
        next.push(file);
      });

      files=next;
      sync();
      if(limitExceeded)notify('You can attach up to '+maxFiles+' files.');
      if(sizeExceeded)notify('Attachments may use at most '+formatBytes(maxTotalBytes)+' combined.');
    }

    input.addEventListener('change',()=>add(input.files));
    chooseButton?.addEventListener('click',event=> {
      event.stopPropagation();
      input.click();
    });
    zone.addEventListener('dragenter',event=> {
      event.preventDefault();
      dragDepth++;
      zone.classList.add('dragover');
    });
    zone.addEventListener('dragover',event=> {
      event.preventDefault();
      if(event.dataTransfer)event.dataTransfer.dropEffect='copy';
      zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave',()=> {
      dragDepth=Math.max(0,dragDepth-1);
      if(!dragDepth)zone.classList.remove('dragover');
    });
    zone.addEventListener('drop',event=> {
      event.preventDefault();
      dragDepth=0;
      zone.classList.remove('dragover');
      add(event.dataTransfer?.files||[]);
    });
    zone.addEventListener('paste',event=> {
      const clipboardFiles=[...(event.clipboardData?.files||[])];
      if(!clipboardFiles.length)return;
      event.preventDefault();
      add(clipboardFiles);
    });
    zone.addEventListener('click',event=> {
      if(event.target.closest('button')||event.target===input)return;
      input.click();
    });
    zone.addEventListener('keydown',event=> {
      if((event.key==='Enter'||event.key===' ')&&!event.target.closest('button,input,select,textarea')) {
        event.preventDefault();
        input.click();
      }
    });
    render();
  }
  qa('[data-upload-zone]').forEach(initUpload);
  qa('[data-response-composer]').forEach(form=> {
    const editor=q('[data-rich-editor]',form);
    const content=q('[data-rich-editor-content]',form);
    const input=q('[data-rich-editor-input]',form);
    const error=q('[data-rich-editor-error]',form);
    if(!editor||!content||!input)return;
    const sync=()=> {
      input.value=content.innerHTML.trim();
      const hasText=(content.textContent||'').replace(/\u00a0/g,' ').trim()!=='';
      editor.classList.toggle('invalid',!hasText&&form.dataset.submitted==='1');
      if(error)error.hidden=hasText||form.dataset.submitted!=='1';
      return hasText;
    };
    qa('[data-editor-command]',editor).forEach(button=>button.addEventListener('click',()=> {
      content.focus();
      document.execCommand(button.dataset.editorCommand||'',false,button.dataset.editorValue||null);
      sync();
    }));
    content.addEventListener('input',sync);
    content.addEventListener('paste',event=> {
      event.preventDefault();
      const text=event.clipboardData?.getData('text/plain')||'';
      document.execCommand('insertText',false,text);
    });
    form.addEventListener('submit',event=> {
      form.dataset.submitted='1';
      if(!sync()) {
        event.preventDefault();
        content.focus();
        if(window.showNotify)showNotify('Write a response before sending.','warning');
      }
    });
  });
  qa('[data-project-media-select]').forEach(select=> {
    const preview=select.closest('.project-media-fields')?.querySelector('[data-project-media-preview]');
    const image=preview?.querySelector('img');
    const update=()=> {
      const source=select.options[select.selectedIndex]?.dataset.previewSrc||'';
      if(!preview||!image)return;
      preview.hidden=source==='';
      image.src=source;
    };
    select.addEventListener('change',update);
    update();
  }); qa('[data-method-select]').forEach(sel=> {
    const form=sel.closest('form');const update=()=> {
      qa('.email-method-panel[data-method]',form).forEach(panel=> {
        const active=panel.dataset.method===sel.value;
        panel.hidden=!active;
        qa('input,select,textarea,button',panel).forEach(control=> {
          control.disabled=!active;
          if(control.matches('select'))control.nextElementSibling?.querySelector('.cms-select-button')?.toggleAttribute('disabled',!active);
        });
      });
      const summary=q('[data-method-summary]',form);
      if(summary)summary.textContent=sel.value==='GRAPH'
        ?'Use a Microsoft 365 mailbox through Microsoft Graph.'
        :'Use an authenticated SMTP server.';
    }
    ;sel.addEventListener('change',update);update()
  }
  ); qa('form[data-swal-confirm]').forEach(form=>form.addEventListener('submit',async e=> {
    if(form.dataset.swalApproved==='1')return;
    const expected=form.dataset.confirmText;
    const confirmation=q('[name=confirmation]',form);
    if(expected&&confirmation&&confirmation.value.trim()!==expected) {
      e.preventDefault();
      e.stopImmediatePropagation();
      confirmation.focus();
      if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');
      return;
    }
    e.preventDefault();const result=window.Swal?await Swal.fire( {
      icon:'warning',title:form.dataset.swalConfirm||'Confirm action',text:form.dataset.swalText||'Please confirm this action.',showCancelButton:true,confirmButtonText:form.dataset.swalConfirmText||'Yes, continue',cancelButtonText:'Cancel',allowOutsideClick:false
    }
    ): {
      isConfirmed:false
    }
    ;if(result.isConfirmed) {
      form.dataset.swalApproved='1';if(form.requestSubmit)form.requestSubmit();else form.submit();
    }
  }
  )); qa('[data-confirm-text]').forEach(form=>form.addEventListener('submit',e=> {
    const expected=form.dataset.confirmText,input=q('[name=confirmation]',form);
    if(input)input.setCustomValidity('');
    if(input&&input.value.trim()!==expected) {
      e.preventDefault();
      input.setCustomValidity('Type '+expected+' exactly to continue.');
      input.focus();
      if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');
    }
  }
  )); // Live content editor preview while typing.
  qa('.cms-form [name]').forEach(field=>field.addEventListener('input',()=> {
    const iframe=q('.live-preview-panel iframe');if(!iframe||!field.name)return;try {
      const doc=iframe.contentDocument;doc?.querySelectorAll('[data-content-key="'+CSS.escape(field.name)+'"]').forEach(el=>el.textContent=field.value);
    } catch(err) {
    }
  }
  )); // Secure logout confirmation.
  qa('[data-logout-confirm]').forEach(link=>link.addEventListener('click',async e=> {
    e.preventDefault();const href=link.getAttribute('href');const result=window.Swal?await Swal.fire( {
      icon:'question',eyebrow:'CERRAR SESIÓN',title:'¿Deseas cerrar sesión?',text:'Tu sesión administrativa se cerrará de forma segura. Puedes cancelar y continuar trabajando.',showCancelButton:true,confirmButtonText:'Sí, cerrar sesión',cancelButtonText:'Cancelar',allowOutsideClick:false
    }
    ): {
      isConfirmed:false
    }
    ;if(result.isConfirmed)window.location.href=href;
  }
  )); qa('[data-sortable-list]').forEach(list => {
    let dragged = null;
    let pointerDragged = null;

    const cards = () => qa('[data-section-card]', list);
    const sync = () => cards().forEach((card, index) => {
      const value = (index + 1) * 10;
      const input = q('[data-sort-order]', card);
      const label = q('[data-order-label]', card);
      const up = q('[data-move-section="up"]', card);
      const down = q('[data-move-section="down"]', card);
      if (input) input.value = value;
      if (label) label.textContent = value;
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === cards().length - 1;
    });

    const placeAtPointer = (card, clientY) => {
      const elements = document.elementsFromPoint(window.innerWidth / 2, clientY);
      const target = elements.map(element => element.closest?.('[data-section-card]')).find(Boolean);
      if (!target || target === card || target.parentElement !== list) return;
      const rect = target.getBoundingClientRect();
      list.insertBefore(card, clientY < rect.top + rect.height / 2 ? target : target.nextSibling);
      sync();
    };

    cards().forEach(card => {
      card.addEventListener('dragstart', event => {
        if (event.target.closest('input, select, label, button:not(.drag-handle)')) {
          event.preventDefault();
          return;
        }
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
      });
      card.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        dragged = null;
        sync();
      });
      card.addEventListener('dragover', event => {
        event.preventDefault();
        if (!dragged || dragged === card) return;
        const rect = card.getBoundingClientRect();
        list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? card : card.nextSibling);
      });

      qa('[data-move-section]', card).forEach(button => button.addEventListener('click', () => {
        const direction = button.dataset.moveSection;
        const sibling = direction === 'up' ? card.previousElementSibling : card.nextElementSibling;
        if (!sibling) return;
        if (direction === 'up') list.insertBefore(card, sibling);
        else list.insertBefore(sibling, card);
        sync();
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }));

      const visibility = q('[data-section-visibility]', card);
      visibility?.addEventListener('change', () => {
        const label = q('[data-section-visibility-label]', card);
        if (label) label.textContent = visibility.checked ? 'Visible' : 'Hidden';
      });

      const handle = q('.drag-handle', card);
      handle?.addEventListener('pointerdown', event => {
        if (event.pointerType === 'mouse') return;
        pointerDragged = card;
        card.classList.add('dragging');
        handle.setPointerCapture(event.pointerId);
        event.preventDefault();
      });
      handle?.addEventListener('pointermove', event => {
        if (pointerDragged !== card) return;
        placeAtPointer(card, event.clientY);
        event.preventDefault();
      });
      const finishPointerSort = event => {
        if (pointerDragged !== card) return;
        card.classList.remove('dragging');
        pointerDragged = null;
        if (handle.hasPointerCapture(event.pointerId)) handle.releasePointerCapture(event.pointerId);
        sync();
      };
      handle?.addEventListener('pointerup', finishPointerSort);
      handle?.addEventListener('pointercancel', finishPointerSort);
    });

    sync();
  });
}
)();
// Premium accessible select UI. The original select remains the submitted value.
(()=> {
  const wrappers=[];
  const closeSelect=(wrap,restoreFocus=false)=> {
    if(!wrap?.classList.contains('open'))return;
    wrap.classList.remove('open');
    const button=wrap.querySelector('.cms-select-button');
    button?.setAttribute('aria-expanded','false');
    if(restoreFocus)button?.focus();
  };
  const closeAll=(except=null)=>wrappers.forEach(wrap=> {
    if(wrap!==except)closeSelect(wrap);
  });

  document.querySelectorAll('select:not([multiple])').forEach((select,index)=> {
    if(select.dataset.customSelectReady==='1')return;
    select.dataset.customSelectReady='1';
    select.classList.add('cms-native-select');

    const wrap=document.createElement('div');
    wrap.className='cms-select';
    const button=document.createElement('button');
    button.type='button';
    button.className='cms-select-button';
    button.id='cms-select-button-'+index;
    button.setAttribute('aria-haspopup','listbox');
    button.setAttribute('aria-expanded','false');
    const labelElement=select.labels?.[0]||null;
    const label=labelElement?.textContent?.replace(/\s+/g,' ').trim();
    if(label)button.setAttribute('aria-label',label);
    if(labelElement)labelElement.htmlFor=button.id;

    const list=document.createElement('div');
    list.className='cms-select-list';
    list.setAttribute('role','listbox');
    list.id='cms-select-'+index;
    button.setAttribute('aria-controls',list.id);

    const searchWrap=document.createElement('div');
    searchWrap.className='cms-select-search-wrap';
    const search=document.createElement('input');
    search.type='search';
    search.className='cms-select-search';
    search.placeholder='Buscar opción…';
    search.autocomplete='off';
    search.setAttribute('aria-label','Buscar opción');
    searchWrap.appendChild(search);
    list.appendChild(searchWrap);

    select.insertAdjacentElement('afterend',wrap);
    wrap.append(button,list);
    wrappers.push(wrap);

    const items=[];
    const enabledIndexes=()=>items.map((item,i)=>(item.disabled||item.hidden)?-1:i).filter(i=>i>=0);
    const focusItem=indexToFocus=>items[indexToFocus]?.focus();
    const openSelect=()=> {
      if(select.disabled)return;
      closeAll(wrap);
      wrap.classList.add('open');
      button.setAttribute('aria-expanded','true');
      const enabled=enabledIndexes();
      const initial=enabled.includes(select.selectedIndex)?select.selectedIndex:enabled[0];
      search.value='';
      items.forEach(item=>item.hidden=false);
      if(initial!==undefined){
        window.requestAnimationFrame(()=>search.focus({preventScroll:true}));
      }
    };
    const selectIndex=optionIndex=> {
      const option=select.options[optionIndex];
      if(!option||option.disabled)return;
      select.selectedIndex=optionIndex;
      select.dispatchEvent(new Event('change',{bubbles:true}));
      closeSelect(wrap,true);
    };
    const moveFocus=(current,direction)=> {
      const enabled=enabledIndexes();
      if(!enabled.length)return;
      const position=Math.max(0,enabled.indexOf(current));
      const next=(position+direction+enabled.length)%enabled.length;
      focusItem(enabled[next]);
    };
    const sync=()=> {
      const selected=select.options[select.selectedIndex];
      button.textContent=selected?selected.textContent:'Select an option';
      button.disabled=select.disabled;
      items.forEach((item,i)=>item.setAttribute('aria-selected',String(i===select.selectedIndex)));
    };

    [...select.options].forEach((option,optionIndex)=> {
      const item=document.createElement('button');
      item.type='button';
      item.className='cms-select-option';
      item.setAttribute('role','option');
      item.setAttribute('tabindex','-1');
      item.textContent=option.textContent;
      item.disabled=option.disabled;
      item.addEventListener('click',()=>selectIndex(optionIndex));
      item.addEventListener('keydown',event=> {
        if(event.key==='ArrowDown'||event.key==='ArrowUp') {
          event.preventDefault();
          moveFocus(optionIndex,event.key==='ArrowDown'?1:-1);
        } else if(event.key==='Home'||event.key==='End') {
          event.preventDefault();
          const enabled=enabledIndexes();
          focusItem(event.key==='Home'?enabled[0]:enabled[enabled.length-1]);
        } else if(event.key==='Enter'||event.key===' ') {
          event.preventDefault();
          selectIndex(optionIndex);
        } else if(event.key==='Escape') {
          event.preventDefault();
          closeSelect(wrap,true);
        } else if(event.key==='Tab') {
          closeSelect(wrap);
        }
      });
      items.push(item);
      list.appendChild(item);
    });

    const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
    const filterOptions=()=> {
      const term=normalize(search.value);
      items.forEach(item=> {
        item.hidden=!!term&&!normalize(item.textContent).includes(term);
      });
    };

    search.addEventListener('input',filterOptions);
    search.addEventListener('keydown',event=> {
      if(event.key==='Escape'){
        event.preventDefault();
        closeSelect(wrap,true);
      }else if(event.key==='ArrowDown'){
        event.preventDefault();
        const enabled=enabledIndexes();
        if(enabled.length)focusItem(enabled[0]);
      }
    });

    button.addEventListener('click',event=> {
      event.stopPropagation();
      if(wrap.classList.contains('open'))closeSelect(wrap,true);else openSelect();
    });
    button.addEventListener('keydown',event=> {
      if(event.key==='ArrowDown'||event.key==='ArrowUp') {
        event.preventDefault();
        openSelect();
      } else if(event.key==='Escape') {
        closeSelect(wrap,true);
      }
    });
    select.addEventListener('change',sync);
    select.form?.addEventListener('reset',()=>window.setTimeout(sync,0));
    sync();
  });

  document.addEventListener('click',event=> {
    if(!event.target.closest('.cms-select'))closeAll();
  });
  document.addEventListener('keydown',event=> {
    if(event.key==='Escape')wrappers.forEach(wrap=>closeSelect(wrap));
  });
})();
// Simple progressive disclosure panels used by user/approval forms.
document.querySelectorAll('[data-toggle-panel]').forEach(btn=>btn.addEventListener('click',()=> {
  const el=document.getElementById(btn.dataset.togglePanel||'');if(!el)return;el.classList.toggle('is-collapsed');if(!el.classList.contains('is-collapsed'))el.scrollIntoView( {
    behavior:'smooth',block:'start'
  }
  );
}
));
// Last opened wins across every menu and overlay in the admin header.
document.querySelectorAll('.admin-header details').forEach(menu=>menu.addEventListener('toggle',()=> {
  if(!menu.open)return;
  AdminOverlayManager.closeSide();
  AdminOverlayManager.closeAdminFind();
  AdminOverlayManager.closeTopbarMenus(menu);
}
));
// Appearance mini live preview.
(()=> {
  const form=document.querySelector('[data-appearance-form]'),box=document.querySelector('[data-style-preview]');
  if(!form||!box)return;
  const heading=box.querySelector('h2'),para=box.querySelector('p');
  const fontStack=v=>v==='System'?'system-ui, sans-serif':'"'+v+'", sans-serif';
  const update=()=> {
    const g=n=>form.querySelector('[name="'+n+'"]')?.value;if(heading) {
      heading.style.fontFamily=fontStack(g('font_heading_family')||'Manrope');heading.style.fontSize=Math.max(24,Math.min(80,Number(g('font_h1_desktop')||40)))+'px';heading.style.fontWeight=g('heading_weight')||800
    }
    if(para) {
      para.style.fontFamily=fontStack(g('font_body_family')||'DM Sans');para.style.fontSize=Math.max(14,Math.min(24,Number(g('font_body_size')||16)))+'px';para.style.lineHeight=g('line_height_body')||1.7
    }
  }
  ;form.querySelectorAll('input,select').forEach(el=>el.addEventListener('input',update));update()
}
)();
// Quick Find: helps non-technical users jump to the right admin area.
(() => {
  const overlay = document.querySelector('[data-admin-find]');
  const input = document.querySelector('[data-admin-find-input]');
  const results = document.querySelector('[data-admin-find-results]');
  if (!overlay || !input || !results) return;
  const links = [...document.querySelectorAll('.admin-sidebar a[href]')].map((link) => ( {
    href: link.getAttribute('href'), label: (link.textContent || '').replace(/\s+/g, ' ').trim(),
  }
  )); const aliases = {
    appearance: 'theme colors typography fonts banner header navigation menu design', settings: 'logo favicon whatsapp maintenance contact social branding', media: 'images videos files documents upload library', gallery: 'projects case studies cases portfolio photos images', content: 'text titles paragraphs landing copy editor publish draft', users: 'accounts team staff administrators login', roles: 'permissions access security roles', estimates: 'requests quotes customers leads follow up', email: 'smtp graph messages mail',
  }
  ; const render = (query = '') => {
    const term = query.trim().toLowerCase(); const matches = links.filter((item) => {
      const key = (item.href || '').replace('.php', '').toLowerCase(); const searchable = `${item.label} ${key} ${aliases[key] || ''}`.toLowerCase(); return term === '' || searchable.includes(term);
    }
    ); results.innerHTML = ''; if (!matches.length) {
      const empty = document.createElement('div'); empty.className = 'admin-find-empty'; empty.textContent = 'No matching admin area. Try a simpler word.'; results.appendChild(empty); return;
    }
    matches.slice(0, 10).forEach((item) => {
      const link = document.createElement('a'); link.href = item.href; link.innerHTML = `<span>${item.label}</span><b>Open →</b>`; results.appendChild(link);
    }
    );
  }
  ; const open = (trigger = null) => {
    render(''); AdminOverlayManager.openAdminFind(trigger);
  }
  ; const close = (restoreFocus = false) => {
    AdminOverlayManager.closeAdminFind({ restoreFocus });
  }
  ; document.querySelectorAll('[data-admin-find-open]').forEach((button) => {
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-haspopup', 'dialog');
    button.addEventListener('click', () => open(button));
  }
  ); document.querySelectorAll('[data-admin-find-close]').forEach((button) => {
    button.addEventListener('click', () => close(true));
  }
  ); input.addEventListener('input', () => render(input.value)); document.addEventListener('keydown', (event) => {
    const editable = event.target.matches('input, textarea, select, [contenteditable="true"]'); if (event.key === '/' && !editable) {
      event.preventDefault(); open();
    }
    if (event.key === 'Escape' && !overlay.hidden) {
      close(true);
    }
  }
  );
}
)();
// Warn users before leaving a form with unsaved changes.
(() => {
  const forms = [...document.querySelectorAll('[data-unsaved-form]')]; if (!forms.length) return; let dirty = false; forms.forEach((form) => {
    const markDirty = () => {
      dirty = true; form.classList.add('has-unsaved-changes');
    }
    ; form.querySelectorAll('input, textarea, select').forEach((field) => {
      if (field.type === 'hidden') return; field.addEventListener('input', markDirty); field.addEventListener('change', markDirty);
    }
    ); form.addEventListener('submit', () => {
      dirty = false; form.classList.remove('has-unsaved-changes');
    }
    );
  }
  ); window.addEventListener('beforeunload', (event) => {
    if (!dirty) return; event.preventDefault(); event.returnValue = '';
  }
  );
}
)();
// Keep color picker and HEX input synchronized in Appearance.
(() => {
  document.querySelectorAll('.color-input-wrap').forEach((wrap) => {
    const picker = wrap.querySelector('[data-color-picker]'); const text = wrap.querySelector('[data-color-text]'); if (!picker || !text) return; picker.addEventListener('input', () => {
      text.value = picker.value.toLowerCase(); text.dispatchEvent(new Event('input', {
        bubbles: true
      }
      ));
    }
    ); text.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
        picker.value = text.value;
      }
    }
    );
  }
  );
}
)();

/* ==========================================================
   PHASE 1 - Focused landing page section editor
   ========================================================== */
(() => {
  const switcher = document.querySelector('[data-section-switcher]');
  if (!switcher) return;

  const tabs = Array.from(switcher.querySelectorAll('[data-section-tab]'));
  const contentEditors = Array.from(document.querySelectorAll('[data-content-editor]'));
  const moduleEditors = Array.from(document.querySelectorAll('[data-module-editor]'));
  const title = document.querySelector('[data-active-section-title]');
  const description = document.querySelector('[data-active-section-description]');
  const previewName = document.querySelector('[data-preview-section-name]');
  const previewFrame = document.querySelector('[data-section-preview-frame]');
  const previewOpen = document.querySelector('[data-preview-open]');
  const returnInputs = Array.from(document.querySelectorAll('[data-return-section], [data-return-section-copy]'));
  const previousButton = document.querySelector('[data-section-previous]');
  const nextButton = document.querySelector('[data-section-next]');
  const savebar = document.querySelector('[data-content-savebar]');
  const aboutArtworkPanel = document.querySelector('[data-about-artwork-panel]');
  const deviceButtons = Array.from(document.querySelectorAll('[data-preview-device]'));
  const previewStage = document.querySelector('[data-preview-stage]');

  let activeKey = tabs.find(tab => tab.classList.contains('is-active'))?.dataset.sectionTab || tabs[0]?.dataset.sectionTab || 'home';

  const activeTabIndex = () => tabs.findIndex(tab => tab.dataset.sectionTab === activeKey);

  const focusPreviewSection = (anchor) => {
    if (!previewFrame || !anchor) return;

    const base = '../?preview=1&draft=1#' + encodeURIComponent(anchor);
    previewFrame.src = base;
    if (previewOpen) previewOpen.href = base;
  };

  const selectSection = (key, updateUrl = true) => {
    const tab = tabs.find(item => item.dataset.sectionTab === key);
    if (!tab) return;

    // Keep the administrator exactly where they are while changing sections.
    // Different editor heights must not make the page jump and lose context.
    const preservedScrollY = window.scrollY;

    activeKey = key;

    tabs.forEach(item => {
      const isActive = item === tab;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    contentEditors.forEach(editor => {
      const isActive = editor.dataset.contentEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    moduleEditors.forEach(editor => {
      const isActive = editor.dataset.moduleEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    const isEditableContent = contentEditors.some(editor => editor.dataset.contentEditor === key);
    if (savebar) savebar.hidden = !isEditableContent;
    if (aboutArtworkPanel) aboutArtworkPanel.hidden = key !== 'about';

    const sectionTitle = tab.dataset.sectionTitle || '';
    const sectionDescription = tab.dataset.sectionDescription || '';
    const sectionAnchor = tab.dataset.sectionAnchor || key;

    if (title) title.textContent = sectionTitle;
    if (description) description.textContent = sectionDescription;
    if (previewName) previewName.textContent = sectionTitle;
    returnInputs.forEach(input => { input.value = key; });

    focusPreviewSection(sectionAnchor);

    if (updateUrl && window.history?.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.set('section', key);
      window.history.replaceState({}, '', url.toString());
    }

    requestAnimationFrame(() => {
      window.scrollTo({ top: preservedScrollY, left: 0, behavior: 'auto' });
    });
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => selectSection(tab.dataset.sectionTab || 'home'));
  });

  previousButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index <= 0 ? tabs.length - 1 : index - 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  nextButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index >= tabs.length - 1 ? 0 : index + 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  deviceButtons.forEach(button => {
    button.addEventListener('click', () => {
      const device = button.dataset.previewDevice || 'desktop';
      deviceButtons.forEach(item => {
        const isActive = item === button;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      if (previewStage) previewStage.dataset.previewStage = device;
    });
  });

  document.querySelectorAll('[data-section-content-form] input, [data-section-content-form] textarea').forEach(field => {
    field.addEventListener('input', () => {
      const state = document.querySelector('[data-section-state="' + CSS.escape(activeKey) + '"]');
      if (!state) return;
      state.textContent = 'Unsaved';
      state.classList.remove('published', 'managed');
      state.classList.add('draft');
    });
  });

  selectSection(activeKey, false);
})();

// Copy one encrypted SMTP or Graph connection to several email purposes.
(() => {
  const modal = document.querySelector('[data-email-copy-modal]');
  const form = modal?.querySelector('[data-email-copy-form]');
  if (!modal || !form) return;

  const sourceId = form.querySelector('[data-email-copy-source-id]');
  const sourceMethod = form.querySelector('[data-email-copy-method]');
  const sourcePurpose = form.querySelector('[data-email-copy-purpose]');
  const sourceEmail = form.querySelector('[data-email-copy-email]');
  const activateCopies = form.querySelector('[name="activate_copies"]');
  const targets = [...form.querySelectorAll('[data-email-copy-target]')];
  let sourceType = 0;
  let returnFocus = null;

  const setAllTargets = (checked) => {
    targets.forEach(target => {
      const input = target.querySelector('input[type="checkbox"]');
      if (!input || input.disabled) return;
      input.checked = checked;
    });
  };

  const close = () => {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('email-copy-modal-open');
    returnFocus?.focus();
  };

  const syncConfirmation = () => {
    form.dataset.swalText = activateCopies?.checked
      ? 'Selected purposes will use this same sender connection. Existing active connections for those purposes will become inactive.'
      : 'The copied connections will be saved inactive. Existing active connections will remain unchanged.';
  };

  document.querySelectorAll('[data-email-copy-open]').forEach(button => {
    button.addEventListener('click', () => {
      returnFocus = button;
      sourceType = Number(button.dataset.sourceType || 0);
      sourceId.value = button.dataset.sourceId || '';
      sourceMethod.textContent = button.dataset.sourceMethod || 'SMTP';
      sourcePurpose.textContent = button.dataset.sourcePurpose || 'Source purpose';
      sourceEmail.textContent = button.dataset.sourceEmail || '';
      if (activateCopies) activateCopies.checked = true;

      targets.forEach(target => {
        const input = target.querySelector('input[type="checkbox"]');
        const isSource = Number(target.dataset.typeId || 0) === sourceType;
        target.classList.toggle('is-source', isSource);
        if (input) {
          input.disabled = isSource;
          input.checked = false;
        }
      });

      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('email-copy-modal-open');
      syncConfirmation();
      window.setTimeout(() => {
        form.querySelector('input[type="checkbox"]:not(:disabled)')?.focus();
      }, 30);
    });
  });

  modal.querySelectorAll('[data-email-copy-close]').forEach(button => {
    button.addEventListener('click', close);
  });
  modal.querySelector('[data-email-copy-select-all]')?.addEventListener('click', () => setAllTargets(true));
  modal.querySelector('[data-email-copy-clear]')?.addEventListener('click', () => setAllTargets(false));
  activateCopies?.addEventListener('change', syncConfirmation);

  form.addEventListener('submit', event => {
    if (!sourceId.value || Number(sourceId.value) < 1) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (window.showNotify) showNotify('The source email configuration could not be identified. Close this window and try again.', 'error');
      return;
    }

    const hasTarget = targets.some(target => {
      const input = target.querySelector('input[type="checkbox"]');
      return input && !input.disabled && input.checked;
    });
    if (hasTarget) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (window.showNotify) showNotify('Select at least one destination purpose.', 'warning');
  }, true);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !modal.hidden) close();
  });
})();

// Force a fresh server capability request and show immediate feedback.
(() => {
  const form = document.querySelector('[data-server-recheck]');
  if (!form) return;

  const button = form.querySelector('[data-recheck-button]');
  const label = form.querySelector('[data-recheck-label]');
  const token = form.querySelector('[data-recheck-token]');

  form.addEventListener('submit', () => {
    if (token) token.value = String(Date.now());
    if (button) {
      button.disabled = true;
      button.classList.add('is-checking');
      button.setAttribute('aria-busy', 'true');
    }
    if (label) label.textContent = 'Checking server...';
  });
})();


// Premium table action dropdowns. One aligned trigger, local icons, viewport-safe menu.
(()=> {
  const menus=[];
  let active=null;

  const iconSvg=(kind)=> {
    const map={
      edit:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17.25V20h2.75L17.81 8.94l-2.75-2.75L4 17.25Zm15.71-10.04a1 1 0 0 0 0-1.42l-1.5-1.5a1 1 0 0 0-1.42 0l-1.17 1.17 2.75 2.75 1.34-1.34Z"/></svg>',
      delete:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 21a2 2 0 0 1-2-2V7h14v12a2 2 0 0 1-2 2H7Zm10-16H7l1-2h8l1 2Zm-8 4v8h2V9H9Zm4 0v8h2V9h-2Z"/></svg>',
      view:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 10 6-.5.8-4.5 6-10 6S2.5 11.8 2 11c.5-.8 4.5-6 10-6Zm0 2c-3.7 0-6.7 3.1-7.7 4 1 1 4 4 7.7 4s6.7-3 7.7-4c-1-1-4-4-7.7-4Zm0 1.5A2.5 2.5 0 1 1 12 13a2.5 2.5 0 0 1 0-4.5Z"/></svg>',
      default:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>'
    };
    return map[kind]||map.default;
  };

  const closeMenu=(entry)=> {
    if(!entry?.menu.classList.contains('open'))return;
    entry.menu.classList.remove('open');
    entry.trigger.setAttribute('aria-expanded','false');
    if(active===entry)active=null;
  };
  const closeAll=()=>menus.forEach(closeMenu);

  const positionMenu=(entry)=> {
    const r=entry.trigger.getBoundingClientRect();
    const menu=entry.menu;
    const pad=12;
    const mw=Math.min(Math.max(menu.offsetWidth||200,200),270);
    let left=r.right-mw;
    left=Math.max(pad,Math.min(left,window.innerWidth-mw-pad));
    const mh=menu.offsetHeight||120;
    let top=r.bottom+7;
    if(top+mh>window.innerHeight-pad && r.top-mh-7>pad) top=r.top-mh-7;
    menu.style.left=left+'px';
    menu.style.top=Math.max(pad,top)+'px';
  };

  document.querySelectorAll('.data-table td .actions, .request-card .actions').forEach((group,index)=> {
    if(group.dataset.actionMenuReady==='1')return;
    const nodes=[...group.children].filter(el=>!el.matches('input[type="hidden"]'));
    if(!nodes.length)return;
    group.dataset.actionMenuReady='1';
    group.classList.add('cms-actions-host');

    const trigger=document.createElement('button');
    trigger.type='button';
    trigger.className='cms-action-trigger';
    trigger.innerHTML='<span class="cms-action-trigger__label">Acciones</span><span class="cms-action-trigger__chevron" aria-hidden="true">▾</span>';
    trigger.setAttribute('aria-haspopup','menu');
    trigger.setAttribute('aria-expanded','false');
    trigger.setAttribute('aria-controls','cms-action-menu-'+index);

    const menu=document.createElement('div');
    menu.className='cms-action-menu';
    menu.id='cms-action-menu-'+index;
    menu.setAttribute('role','menu');
    document.body.append(menu);

    nodes.forEach(node=> {
      const actionable=node.matches('a,button')?node:node.querySelector('a,button');
      if(actionable){
        const raw=(actionable.textContent||'').trim();
        const label=raw.toLowerCase();
        const kind=label.includes('eliminar')||label.includes('borrar')?'delete':label.includes('editar')?'edit':label.includes('ver')||label.includes('abrir')?'view':'default';
        const icon=document.createElement('span');
        icon.className='action-icon action-icon--'+kind;
        icon.innerHTML=iconSvg(kind);
        actionable.prepend(icon);
        if(kind==='delete') actionable.classList.add('action-danger');
        actionable.setAttribute('role','menuitem');
        actionable.setAttribute('tabindex','-1');
      }
      menu.append(node);
    });
    group.replaceChildren(trigger);
    const entry={trigger,menu};
    menus.push(entry);

    trigger.addEventListener('click',event=> {
      event.stopPropagation();
      const opening=!menu.classList.contains('open');
      closeAll();
      if(opening){
        menu.classList.add('open');
        trigger.setAttribute('aria-expanded','true');
        active=entry;
        positionMenu(entry);
        const first=menu.querySelector('a,button');
        if(first){first.setAttribute('tabindex','0');first.focus({preventScroll:true});}
      }
    });
    menu.addEventListener('click',event=> {
      if(event.target.closest('a,button')) window.setTimeout(()=>closeMenu(entry),0);
    });
  });

  document.addEventListener('click',event=> {
    if(!event.target.closest('.cms-action-menu')&&!event.target.closest('.cms-action-trigger'))closeAll();
  });
  document.addEventListener('keydown',event=> {
    if(event.key==='Escape'&&active){const t=active.trigger;closeAll();t.focus();}
  });
  window.addEventListener('resize',()=>{if(active)positionMenu(active)});
  document.addEventListener('scroll',()=>{if(active)positionMenu(active)},true);
})();


// One password visibility style across every authenticated admin form.
(() => {
  document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (input.closest('.admin-password-wrap')) return;
    const parent = input.parentElement;
    if (!parent) return;
    const wrap = document.createElement('span');
    wrap.className = 'admin-password-wrap';
    parent.insertBefore(wrap, input);
    wrap.appendChild(input);
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'admin-password-toggle';
    toggle.textContent = 'Mostrar';
    toggle.setAttribute('aria-label', 'Mostrar contraseña');
    toggle.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.textContent = show ? 'Ocultar' : 'Mostrar';
      toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
    wrap.appendChild(toggle);
  });
})();
