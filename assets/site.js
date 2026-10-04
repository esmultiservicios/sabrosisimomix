const header=document.querySelector('.site-header'),menuBtn=document.querySelector('.menu-btn'),nav=document.querySelector('.main-nav'),progress=document.querySelector('.scroll-progress span');
menuBtn?.addEventListener('click',()=> {
  const open=nav.classList.toggle('open');menuBtn.setAttribute('aria-expanded',String(open));
}
);
function scrollToTarget(hash) {
  const target=document.querySelector(hash);
  if(!target)return;
  const offset=(header?.offsetHeight||0)+8;
  const top=target.getBoundingClientRect().top+window.scrollY-offset;
  window.scrollTo( {
    top,behavior:'smooth'
  }
  );
}
document.querySelectorAll('[data-scroll][href^="#"]').forEach(link=>link.addEventListener('click',event=> {
  const hash=link.getAttribute('href');if(!hash||hash==='#')return;event.preventDefault();nav?.classList.remove('open');menuBtn?.setAttribute('aria-expanded','false');scrollToTarget(hash);history.replaceState(null,'',hash);
}
));
const sections=['home','about','services','gallery','areas','estimate','contact'].map(id=>document.getElementById(id)).filter(Boolean),navLinks=[...document.querySelectorAll('.main-nav a[href^="#"]')];
const spy=new IntersectionObserver(entries=> {
  const visible=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(!visible)return;navLinks.forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='#'+visible.target.id));
}
, {
  rootMargin:'-22% 0px -58% 0px',threshold:[0,.15,.35,.6]
}
);
sections.forEach(s=>spy.observe(s));
const revealObserver=new IntersectionObserver(entries=>entries.forEach(entry=> {
  if(entry.isIntersecting) {
    entry.target.classList.add('visible');revealObserver.unobserve(entry.target);
  }
}
), {
  threshold:.08
}
);
document.querySelectorAll('.reveal').forEach(el=>revealObserver.observe(el));
function updateProgress() {
  if(!progress)return;
  const max=document.documentElement.scrollHeight-window.innerHeight;
  progress.style.width=(max>0?Math.min(100,window.scrollY/max*100):0)+'%';
}
window.addEventListener('scroll',updateProgress, {
  passive:true
}
);
updateProgress();
const upload=document.querySelector('[data-public-upload]'),fileInput=upload?.querySelector('input[type=file]'),preview=upload?.querySelector('[data-public-upload-preview]');
let selected=[];
function syncPublicFiles() {
  if(!fileInput)return;
  const dt=new DataTransfer();
  selected.forEach(f=>dt.items.add(f));
  fileInput.files=dt.files;
  renderPublicFiles()
}
function renderPublicFiles() {
  if(!preview)return;
  preview.innerHTML='';
  selected.forEach((f,i)=> {
    const u=URL.createObjectURL(f),d=document.createElement('div');
    d.className='public-upload-item';
    d.innerHTML='<img alt="Selected project image"><button type="button" aria-label="Remove image">×</button>';
    d.querySelector('img').src=u;
    d.querySelector('button').addEventListener('click',e=> {
      e.stopPropagation();selected.splice(i,1);syncPublicFiles()
    }
    );preview.appendChild(d)
  }
  )
}
function addPublicFiles(list) {
  const imgs=[...list].filter(f=>/^image\/(jpeg|png|webp)$/.test(f.type));
  selected=[...selected,...imgs].slice(0,8);
  syncPublicFiles()
}
fileInput?.addEventListener('change',()=> {
  selected=[...fileInput.files].slice(0,8);syncPublicFiles()
}
);
upload?.addEventListener('click',e=> {
  if(e.target.closest('button'))return;if(e.target!==fileInput)fileInput.click()
}
);
upload?.addEventListener('dragover',e=> {
  e.preventDefault();upload.classList.add('dragover')
}
);
upload?.addEventListener('dragleave',()=>upload.classList.remove('dragover'));
upload?.addEventListener('drop',e=> {
  e.preventDefault();upload.classList.remove('dragover');addPublicFiles(e.dataTransfer.files)
}
);
upload?.addEventListener('paste',e=> {
  const files=[...e.clipboardData.items].filter(i=>i.kind==='file').map(i=>i.getAsFile()).filter(Boolean);if(files.length) {
    e.preventDefault();addPublicFiles(files)
  }
}
);
const light=document.querySelector('[data-site-lightbox]'),lightImg=document.querySelector('[data-site-lightbox-img]'),lightCap=document.querySelector('[data-site-lightbox-caption]');
document.querySelectorAll('[data-gallery-src]').forEach(b=>b.addEventListener('click',()=> {
  lightImg.src=b.dataset.gallerySrc||'';lightCap.textContent=b.dataset.galleryTitle||'';light.classList.add('open');light.setAttribute('aria-hidden','false')
}
));
function closeLight() {
  light?.classList.remove('open');
  light?.setAttribute('aria-hidden','true');
  if(lightImg)lightImg.src=''
}
document.querySelector('[data-site-lightbox-close]')?.addEventListener('click',closeLight);
light?.addEventListener('click',e=> {
  if(e.target===light)closeLight()
}
);
document.addEventListener('keydown',e=> {
  if(e.key==='Escape')closeLight()
}
);
const form=document.getElementById('estimateForm'),toast=document.getElementById('toast');
const emailInput=form?.querySelector('input[name="email"]');
const emailStatus=form?.querySelector('[data-email-status]');
const commonEmailCorrections= {
  'gmail.con':'gmail.com',
  'gmail.co':'gmail.com',
  'gmail.cmo':'gmail.com',
  'gmial.com':'gmail.com',
  'gmai.com':'gmail.com',
  'gamil.com':'gmail.com',
  'hotmal.com':'hotmail.com',
  'hotmai.com':'hotmail.com',
  'hotmail.con':'hotmail.com',
  'outlook.con':'outlook.com',
  'outlok.com':'outlook.com',
  'outloo.com':'outlook.com',
  'yahoo.con':'yahoo.com',
  'yaho.com':'yahoo.com',
  'icloud.con':'icloud.com'
};
let emailValidationTimer=null;
let emailValidationRequest=null;
let emailValidationState='idle';
let lastValidatedEmail='';

function showToast(message) {
  if(!toast)return;
  toast.textContent=message;
  toast.classList.add('show');
  window.setTimeout(()=>toast.classList.remove('show'),5200)
}

function setEmailStatus(state,message='',suggestion='') {
  emailValidationState=state;
  if(!emailStatus||!emailInput)return;

  emailStatus.className='email-validation-status';
  emailStatus.replaceChildren();
  emailInput.removeAttribute('aria-invalid');

  if(state==='idle')return;

  emailStatus.classList.add(`is-${state}`);
  const text=document.createElement('span');
  text.textContent=message;
  emailStatus.appendChild(text);

  if(state==='invalid'||state==='suggestion') {
    emailInput.setAttribute('aria-invalid','true')
  }

  if(suggestion) {
    const useSuggestion=document.createElement('button');
    useSuggestion.type='button';
    useSuggestion.className='email-suggestion-button';
    useSuggestion.textContent=`Use ${suggestion}`;
    useSuggestion.addEventListener('click',()=> {
      emailInput.value=suggestion;
      emailInput.focus();
      validateEmailField(true)
    });
    emailStatus.appendChild(useSuggestion)
  }
}

function localEmailSuggestion(email) {
  const at=email.lastIndexOf('@');
  if(at<1)return '';
  const local=email.slice(0,at);
  const domain=email.slice(at+1).toLowerCase();
  const correctedDomain=commonEmailCorrections[domain];
  return correctedDomain?`${local}@${correctedDomain}`:''
}

async function validateEmailField(force=false) {
  if(!emailInput)return true;

  const email=emailInput.value.trim();
  if(email==='') {
    lastValidatedEmail='';
    setEmailStatus('idle');
    return true
  }

  if(email!==emailInput.value||!emailInput.checkValidity()||/\s/.test(email)) {
    setEmailStatus('invalid','Enter a valid email address. Example: name@company.com');
    return false
  }

  const suggestion=localEmailSuggestion(email);
  if(suggestion) {
    setEmailStatus('suggestion',`Did you mean ${suggestion}?`,suggestion);
    return false
  }

  if(!force&&lastValidatedEmail===email&&emailValidationState==='valid')return true;

  emailValidationRequest?.abort();
  emailValidationRequest=new AbortController();
  setEmailStatus('checking','Checking email...');

  try {
    const payload=new FormData();
    payload.append('email',email);
    const response=await fetch('email-validate.php', {
      method:'POST',
      body:payload,
      headers: {
        'X-Requested-With':'XMLHttpRequest'
      },
      signal:emailValidationRequest.signal
    });
    const result=await response.json();

    if(result.valid) {
      lastValidatedEmail=email;
      setEmailStatus('valid',result.message||'Valid email');
      return true
    }

    const state=result.status==='suggestion'?'suggestion':'invalid';
    setEmailStatus(
      state,
      result.message||'Review the email address and try again.',
      result.suggestion||''
    );
    return false
  } catch(error) {
    if(error.name==='AbortError')return false;

    // A temporary validation-service outage must not block a real customer.
    setEmailStatus('neutral','The address will be verified when you submit the form.');
    return true
  }
}

emailInput?.addEventListener('input',()=> {
  window.clearTimeout(emailValidationTimer);
  lastValidatedEmail='';
  const email=emailInput.value.trim();

  if(email==='') {
    setEmailStatus('idle');
    return
  }

  const suggestion=localEmailSuggestion(email);
  if(suggestion) {
    setEmailStatus('suggestion',`Did you mean ${suggestion}?`,suggestion);
    return
  }

  if(!emailInput.checkValidity()||/\s/.test(email)) {
    setEmailStatus('invalid','Enter a valid email address. Example: name@company.com');
    return
  }

  setEmailStatus('checking','Checking email...');
  emailValidationTimer=window.setTimeout(()=>validateEmailField(),550)
});

emailInput?.addEventListener('blur',()=> {
  if(emailInput.value.trim()!=='')validateEmailField()
});

form?.addEventListener('submit',async event=> {
  event.preventDefault();

  const emailIsValid=await validateEmailField(true);
  if(!emailIsValid) {
    emailInput?.focus();
    showToast('Review the email address before sending your request.');
    return
  }

  const button=form.querySelector('button[type="submit"]');
  const originalText=button.textContent;
  button.disabled=true;
  button.textContent='Sending...';

  try {
    const response=await fetch('estimate-submit.php', {
      method:'POST',
      body:new FormData(form),
      headers: {
        'X-Requested-With':'XMLHttpRequest'
      }
    });
    const result=await response.json();
    showToast(result.message||'Request received.');

    if(result.ok) {
      form.reset();
      selected=[];
      renderPublicFiles();
      lastValidatedEmail='';
      setEmailStatus('idle');
      if(window.turnstile)window.turnstile.reset()
    }
  } catch(error) {
    showToast('We could not send the request. Please contact us by phone or WhatsApp.')
  } finally {
    button.disabled=false;
    button.textContent=originalText
  }
});
if(location.hash&&document.querySelector(location.hash))setTimeout(()=>scrollToTarget(location.hash),100);
