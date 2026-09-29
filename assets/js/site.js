const toggle=document.querySelector('.nav-toggle'),nav=document.querySelector('.site-header nav');
toggle?.addEventListener('click',()=>{const open=document.body.classList.toggle('nav-open');toggle.setAttribute('aria-expanded',open?'true':'false')});
nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>document.body.classList.remove('nav-open')));
const header=document.querySelector('.site-header');
addEventListener('scroll',()=>header?.classList.toggle('scrolled',scrollY>20),{passive:true});

(()=>{
  const zone=document.querySelector('[data-public-dropzone]');
  const input=document.querySelector('[data-public-file-input]');
  const selectBtn=document.querySelector('[data-public-file-select]');
  const list=document.querySelector('[data-public-file-list]');
  if(!zone||!input||!list)return;
  let files=[];
  const maxFiles=3,maxSize=3*1024*1024;
  const key=f=>`${f.name}|${f.size}|${f.lastModified}`;
  const allowed=f=>['image/jpeg','image/png','image/webp'].includes(f.type)&&f.size<=maxSize;
  const human=n=>n<1024?`${n} B`:n<1048576?`${(n/1024).toFixed(1)} KB`:`${(n/1048576).toFixed(1)} MB`;
  function render(){
    list.innerHTML='';
    files.forEach((file,index)=>{
      const item=document.createElement('div');item.className='public-file-item';
      const icon=document.createElement('span');icon.textContent='▧';icon.setAttribute('aria-hidden','true');
      const meta=document.createElement('span');meta.className='file-meta';
      const strong=document.createElement('strong');strong.textContent=file.name;
      const small=document.createElement('small');small.textContent=human(file.size);
      meta.append(strong,small);
      const remove=document.createElement('button');remove.type='button';remove.className='public-file-remove';remove.textContent='×';remove.setAttribute('aria-label',`Quitar ${file.name}`);remove.addEventListener('click',()=>{files.splice(index,1);sync()});
      item.append(icon,meta,remove);list.append(item);
    });
  }
  function sync(){const dt=new DataTransfer();files.forEach(f=>dt.items.add(f));input.files=dt.files;render()}
  function add(incoming){
    const current=new Set(files.map(key));
    for(const f of [...incoming]){
      if(files.length>=maxFiles)break;
      if(!allowed(f)||current.has(key(f)))continue;
      files.push(f);current.add(key(f));
    }
    sync();
  }
  selectBtn?.addEventListener('click',e=>{e.stopPropagation();input.click()});
  zone.addEventListener('click',e=>{if(!e.target.closest('button'))input.click()});
  zone.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&!e.target.closest('button')){e.preventDefault();input.click()}});
  input.addEventListener('change',()=>add(input.files));
  ['dragenter','dragover'].forEach(name=>zone.addEventListener(name,e=>{e.preventDefault();zone.classList.add('is-dragover')}));
  ['dragleave','drop'].forEach(name=>zone.addEventListener(name,e=>{e.preventDefault();zone.classList.remove('is-dragover')}));
  zone.addEventListener('drop',e=>add(e.dataTransfer?.files||[]));
  zone.addEventListener('paste',e=>{const pasted=[...(e.clipboardData?.files||[])];if(pasted.length){e.preventDefault();add(pasted)}});
})();


document.querySelectorAll('[data-public-notify]').forEach(el=>{if(window.showNotify)showNotify(el.dataset.message||'',el.dataset.type||'info',{duration:5200});});
