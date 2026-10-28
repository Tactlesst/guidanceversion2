(() => {
 'use strict';
 const editor=document.getElementById('pdsEditor'),form=document.getElementById('pdsCompleteForm');if(!editor||!form)return;
 const dirty=()=>form.dispatchEvent(new Event('input',{bubbles:true}));
 function selected(name){return [...form.querySelectorAll(`[name="${name}"],[name="${name}[]"]`)].flatMap(field=>field.tagName==='SELECT'?[...field.selectedOptions].map(option=>option.value):field.type==='checkbox'?(field.checked?[field.value]:[]):[field.value]).map(value=>value.trim().toLowerCase());}
 function conditionalFields(){for(const wrapper of editor.querySelectorAll('[data-other-for]')){const allowed=(wrapper.dataset.otherValues||'other|others').split('|');const active=selected(wrapper.dataset.otherFor).some(value=>allowed.includes(value));wrapper.hidden=!active;for(const field of wrapper.querySelectorAll('input,select,textarea'))field.disabled=!active;}}
 form.addEventListener('change',conditionalFields);conditionalFields();

 for(const picker of editor.querySelectorAll('[data-language-picker]')){
  const menu=picker.querySelector('details'),summary=menu.querySelector('summary'),caption=picker.querySelector('[data-language-summary]'),search=picker.querySelector('[data-language-search]'),rows=[...picker.querySelectorAll('[data-language-option]')],count=picker.querySelector('[data-language-count]'),empty=picker.querySelector('[data-language-no-results]');
  function update(){const values=rows.filter(row=>row.querySelector('input').checked).map(row=>row.querySelector('input').value);caption.textContent=values.length?values.join(', '):'Choose languages';caption.title=values.join(', ');count.textContent=values.length+' selected';}
  function filter(){const query=search.value.trim().toLocaleLowerCase();let visible=0;for(const row of rows){row.hidden=!row.textContent.toLocaleLowerCase().includes(query);if(!row.hidden)visible++;}empty.hidden=visible>0;}
  picker.addEventListener('change',update);search.addEventListener('input',event=>{event.stopPropagation();filter();});search.addEventListener('change',event=>event.stopPropagation());search.addEventListener('keydown',event=>{if(event.key==='Enter')event.preventDefault();});
  picker.querySelector('[data-language-clear]').addEventListener('click',()=>{rows.forEach(row=>row.querySelector('input').checked=false);search.value='';filter();update();form.dispatchEvent(new Event('change',{bubbles:true}));dirty();search.focus({preventScroll:true});});
  menu.addEventListener('toggle',()=>{if(menu.open)search.focus({preventScroll:true});});
  picker.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.open){event.preventDefault();menu.open=false;summary.focus({preventScroll:true});}});
  document.addEventListener('click',event=>{if(!picker.contains(event.target))menu.open=false;});
  update();
 }

 const addressRoot=editor.querySelector('.pe-addresses'),blocks=[...editor.querySelectorAll('[data-address]')],same=editor.querySelector('[data-address-same]');
 const locationEndpoint=new URL(editor.dataset.locationEndpoint,location.href),cache=new Map();
 async function choices(level,scope){const url=new URL(locationEndpoint);url.search=new URLSearchParams({level,...scope});const key=url.href;if(!cache.has(key))cache.set(key,fetch(key,{credentials:'same-origin'}).then(async response=>{const data=await response.json();if(!response.ok)throw new Error(data.error||'Location choices could not load.');return data.items;}).catch(error=>{cache.delete(key);throw error;}));return cache.get(key);}
 function fillSelect(select,items,value=''){select.replaceChildren(new Option('Select / leave blank',''));for(const item of items)select.add(new Option(item.name,item.code));select.value=value;if(value&&select.value!==value)select.add(new Option('Previously saved location — choose a current location',value,true,true));}
 const controllers=[];
 for(const block of blocks){
  const mode=block.querySelector('[data-address-mode]'),legacy=block.querySelector('[data-address-legacy]'),structured=block.querySelector('[data-address-structured]'),status=block.querySelector('[data-address-status]'),fields=[...block.querySelectorAll('[data-address-level]')];let generation=0;
  const scope=()=>Object.fromEntries(fields.map(field=>[field.dataset.addressLevel,field.value]));
  function active(){const copying=block.dataset.address==='permanent'&&same?.checked;const using=mode.value==='structured';legacy.hidden=using;structured.hidden=!using;block.hidden=Boolean(copying);mode.disabled=Boolean(copying);for(const field of legacy.querySelectorAll('textarea'))field.disabled=using||copying;for(const field of structured.querySelectorAll('input,select')){field.disabled=!using||copying;field.required=using&&!copying&&field.tagName==='SELECT';}}
  async function loadFrom(start,saved=[]){const request=++generation;status.textContent='Loading location choices…';
   try{for(let index=start;index<fields.length;index++){const field=fields[index];if(index&&fields[index-1].value===''){fillSelect(field,[]);continue;}const items=await choices(field.dataset.addressLevel,scope());if(request!==generation||!editor.isConnected)return;fillSelect(field,items,saved[index]||'');}status.textContent='';active();}
   catch(error){if(request!==generation)return;status.textContent=error.name==='AbortError'?'':error.message;/* Do not discard the saved address when the catalog cannot load. */}
  }
  fields.forEach((field,index)=>field.addEventListener('change',()=>{for(let next=index+1;next<fields.length;next++)fillSelect(fields[next],[]);loadFrom(index+1);}));
  mode.addEventListener('change',()=>{active();if(mode.value==='structured'&&!fields[0].options.length)loadFrom(0);});
  active();loadFrom(0,fields.map(field=>field.dataset.savedCode||''));controllers.push({active});
 }
 same?.addEventListener('change',()=>controllers.forEach(controller=>controller.active()));

 const padControllers=[];
 const philippinesDate=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
 for(const block of editor.querySelectorAll('[data-signature]')){
  const field=block.dataset.signature,canvas=block.querySelector('canvas'),status=block.querySelector('[data-signature-status]'),live=block.querySelector('[data-signature-live]'),drawn=block.querySelector(`[name="${field}_drawn"]`),confirm=block.querySelector(`[name="${field}_confirm"]`),upload=block.querySelector('[type=file]'),date=form.querySelector(`[name="${field==='student_signature'?'student':'parent'}_date_signed"]`),originalDate=date.value;
  if(!window.SignaturePad){status.textContent='The signature pad could not load. You may upload an image or leave the signature blank and sign the printed form.';continue;}
  const pad=new window.SignaturePad(canvas,{penColor:'#182b43',minWidth:0.7,maxWidth:2.2,throttle:0});let logicalWidth=0,logicalHeight=0;
  function preview(){const empty=pad.isEmpty();live.hidden=empty;if(!empty)live.src=pad.toDataURL('image/png');else live.removeAttribute('src');}
  function invalidate(){drawn.value='';confirm.checked=false;date.value=originalDate;date.readOnly=false;status.textContent='Drawing changed. Confirm and choose Save Signature before saving your PDS.';dirty();}
  function resize(){const rect=canvas.getBoundingClientRect();if(rect.width<1||rect.height<1)return;const width=Math.round(rect.width),height=Math.round(rect.height),ratio=Math.min(2,Math.max(window.devicePixelRatio||1,1));if(logicalWidth===width&&logicalHeight===height)return;const data=pad.toData();if(logicalWidth&&logicalHeight)for(const group of data)for(const point of group.points){const scale=Math.min(width/logicalWidth,height/logicalHeight);point.x*=scale;point.y*=scale;}canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);canvas.getContext('2d').scale(ratio,ratio);pad.clear();pad.fromData(data);logicalWidth=width;logicalHeight=height;preview();}
  const observer=new ResizeObserver(resize);observer.observe(canvas);resize();
  pad.addEventListener('beginStroke',()=>{if(drawn.value)invalidate();});
  pad.addEventListener('afterUpdateStroke',preview);pad.addEventListener('endStroke',()=>{preview();dirty();});
  block.querySelector('[data-signature-clear]').addEventListener('click',()=>{pad.clear();preview();invalidate();status.textContent='Drawing cleared. Your previously saved signature is unchanged until you explicitly replace or remove it.';});
  block.querySelector('[data-signature-undo]').addEventListener('click',()=>{const data=pad.toData();data.pop();pad.fromData(data);preview();invalidate();});
  block.querySelector('[data-signature-save]').addEventListener('click',()=>{
   if(pad.isEmpty()){status.textContent='Draw your signature first.';canvas.focus();return;}
   if(!confirm.checked){status.textContent='The signer must check the authorization box before applying this signature.';confirm.focus();return;}
   const png=pad.toDataURL('image/png');if(png.length>2800000){status.textContent='The drawing is too large. Clear it and try again.';return;}
   drawn.value=png;upload.value='';const remove=block.querySelector(`[name="remove_${field}"]`);if(remove)remove.checked=false;date.value=philippinesDate();date.readOnly=true;status.textContent='Signature confirmed in this form. Save PDS to apply it. Signing date is set automatically in Philippine time.';dirty();
  });
  upload.addEventListener('change',()=>{if(!upload.files?.length)return;pad.clear();preview();drawn.value='';confirm.checked=false;date.value=philippinesDate();date.readOnly=true;status.textContent='The signer must authorize this uploaded signature before you save the PDS.';});
  confirm.addEventListener('change',()=>{if(!confirm.checked&&drawn.value){drawn.value='';date.value=originalDate;date.readOnly=false;status.textContent='Signature authorization withdrawn. Confirm and save the drawing again to apply it.';}});
  form.addEventListener('submit',event=>{const pending=!pad.isEmpty()&&!drawn.value&&!upload.files?.length;const unconfirmed=(drawn.value||upload.files?.length)&&!confirm.checked;if(pending||unconfirmed){event.preventDefault();event.stopImmediatePropagation();status.textContent=pending?'Confirm the drawing with Save Signature, or clear it before saving your PDS.':'The signer must authorize the new signature before saving.';editor.querySelector('[data-pe-step="5"]').click();block.scrollIntoView({block:'center'});confirm.focus();}},true);
  padControllers.push(()=>{observer.disconnect();pad.off();});
 }
 // Portal page changes detach the form. Release canvas observers and pointer listeners.
 const cleanupObserver=new MutationObserver(()=>{if(editor.isConnected)return;cleanupObserver.disconnect();for(const cleanup of padControllers)cleanup();});cleanupObserver.observe(document.body,{childList:true,subtree:true});
})();
