<script>
// Fotoğraf seçilince kalite servisine sor. Kontrol sürerken ya da hata/kapalı servis durumunda engellemez; yalnız tamam=false iken uyarır.
const EK2_KALITE_URL=@json(route('doctor.records.kalite'));
function ek2Alan(form,ad){let h=form.querySelector('input[name="'+ad+'"]');if(!h){h=document.createElement('input');h.type='hidden';h.name=ad;form.appendChild(h)}return h}
async function ek2Kalite(input,sayfa,alan){
 const f=input.files&&input.files[0], form=input.form, kutu=input.parentElement, hid=ek2Alan(form,alan);
 const hedef=input.closest('.step')?.querySelector('.next')||form.querySelector('button:not([type=button])');
 kutu.querySelectorAll('.kalite-kutu,.kalite-durum').forEach(e=>e.remove()); hid.value=''; if(hedef)hedef.disabled=false;
 if(!f){const img=kutu.querySelector('img.preview');if(img)img.hidden=true;return}
 const d=document.createElement('p');d.className='muted kalite-durum';d.textContent='Fotoğraf kontrol ediliyor…';kutu.appendChild(d);
 let j=null;
 try{const fd=new FormData();fd.append('image',f);fd.append('sayfa',sayfa);fd.append('_token',form.querySelector('[name=_token]').value);
  const r=await fetch(EK2_KALITE_URL,{method:'POST',body:fd,headers:{Accept:'application/json'},credentials:'same-origin'});if(r.ok)j=await r.json()}catch(e){}
 d.remove(); if(input.files[0]!==f)return;
 if(!j||j.tamam!==false)return;
 const nedenler=(j.nedenler||[]).map(String);
 const box=document.createElement('div');box.className='kalite-kutu';box.style.cssText='margin-top:12px;padding:12px;border:1px solid #b42318;background:#fdecea;border-radius:10px';
 const b=document.createElement('strong');b.style.color='#b42318';b.textContent='Fotoğrafta sorun var:';box.appendChild(b);
 const ul=document.createElement('ul');ul.style.cssText='margin:6px 0 10px;padding-left:20px;color:#b42318';
 nedenler.forEach(n=>{const li=document.createElement('li');li.textContent=n;ul.appendChild(li)});box.appendChild(ul);
 const row=document.createElement('div');row.style.cssText='display:flex;gap:8px';
 const tekrar=document.createElement('button');tekrar.type='button';tekrar.className='btn light';tekrar.textContent='Yeniden çek';
 const devam=document.createElement('button');devam.type='button';devam.className='btn danger';devam.textContent='Yine de kullan';
 tekrar.onclick=()=>{input.value='';box.remove();hid.value='';const img=kutu.querySelector('img.preview');if(img){img.hidden=true;img.removeAttribute('src')}if(hedef)hedef.disabled=true};
 devam.onclick=()=>{hid.value=nedenler.join(', ').slice(0,500);box.remove();if(hedef)hedef.disabled=false};
 row.append(tekrar,devam);box.appendChild(row);kutu.appendChild(box);
 if(hedef)hedef.disabled=true;
}
</script>
