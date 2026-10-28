(()=>{'use strict';
if(window.ApprovalBoardLive){window.ApprovalBoardLive.mount();return;}
let root=null,page=1,revision='',timer=null,controller=null,listeners=null,sequence=0,saving=false,errorKind=null;
const endpoint='approval_feed.php',interval=5000;
function schedule(){clearTimeout(timer);timer=null;if(root?.isConnected&&!document.hidden&&!saving)timer=setTimeout(()=>refresh(),interval);}
function status(message){const target=root?.querySelector('[data-approval-live-status]');if(target)target.textContent=message;}
function clearError(kind=null){if(kind&&errorKind!==kind)return;const target=root?.querySelector('[data-approval-live-error]');if(target){target.hidden=true;target.textContent='';}errorKind=null;}
function failure(message,kind='connection'){const target=root?.querySelector('[data-approval-live-error]');if(target){target.hidden=false;target.textContent=message;}errorKind=kind;}
function patch(html){
    const holder=root.querySelector('[data-approval-list]'),template=document.createElement('template');template.innerHTML=html;
    const incoming=template.content.querySelector('[data-approval-items]'),items=holder.querySelector('[data-approval-items]');
    if(!incoming||!items)throw new Error('The approval list could not be updated. Refresh this page.');
    const active=document.activeElement,activeCard=active?.closest('[data-approval-request]');
    const focus=activeCard&&holder.contains(active)?{id:activeCard.dataset.approvalRequest,name:active.name,start:active.selectionStart,end:active.selectionEnd}:null;
    const old=new Map([...items.children].map(el=>[el.dataset.approvalRequest||'empty',el]));const ordered=[];
    for(const fresh of [...incoming.children]){
        const existing=old.get(fresh.dataset.approvalRequest||'empty');
        if(existing&&fresh.dataset.approvalRevision&&existing.dataset.approvalRevision===fresh.dataset.approvalRevision){ordered.push(existing);continue;}
        if(existing){const detail=existing.querySelector('details'),nextDetail=fresh.querySelector('details');if(detail&&nextDetail)nextDetail.open=detail.open;
            const note=existing.querySelector('[name="note"]'),nextNote=fresh.querySelector('[name="note"]');if(note&&nextNote&&note.value!==note.defaultValue)nextNote.value=note.value;
        }ordered.push(fresh);
    }
    const retained=new Set(ordered);for(const child of [...items.children])if(!retained.has(child))child.remove();
    let cursor=items.firstElementChild;for(const item of ordered){if(item===cursor)cursor=cursor.nextElementSibling;else items.insertBefore(item,cursor);}
    const pagination=holder.querySelector('.oa-pagination'),newPagination=template.content.querySelector('.oa-pagination');
    if(pagination&&newPagination&&pagination.outerHTML!==newPagination.outerHTML)pagination.replaceWith(newPagination);
    if(focus&&(!active.isConnected||document.activeElement!==active)){const card=items.querySelector('[data-approval-request="'+Number(focus.id)+'"]'),field=card?.querySelector('[name="note"]');if(field&&focus.name==='note'){field.focus({preventScroll:true});if(focus.start!==null)field.setSelectionRange(focus.start,focus.end);}}
}
function paint(data,{updateURL=false}={}){
    if(!data.unchanged&&typeof data.html==='string')patch(data.html);
    page=data.page;revision=data.revision;root.dataset.approvalPage=String(page);root.dataset.approvalRevision=revision;root.dataset.approvalCsrf=data.csrf;
    root.querySelectorAll('input[name="csrf"]').forEach(input=>input.value=data.csrf);
    const total=root.querySelector('[data-approval-total]');if(total)total.textContent=data.total+' '+(data.total===1?'request':'requests');
    if(updateURL){const url=new URL(location.href);if(page===1)url.searchParams.delete('p');else url.searchParams.set('p',String(page));history.replaceState({...history.state},'',url);}
}
async function refresh({targetPage=page,manual=false,updateURL=false}={}){
    if(!root?.isConnected||document.hidden||saving)return;
    clearTimeout(timer);controller?.abort();const target=root,seq=++sequence;const request=new AbortController();controller=request;let timedOut=false;
    const timeout=setTimeout(()=>{timedOut=true;request.abort();},15000);
    if(manual){clearError();status('Refreshing approvals…');}
    try{
        const query=new URLSearchParams({p:String(targetPage),since:targetPage===page?revision:''});
        const response=await fetch(endpoint+'?'+query,{credentials:'same-origin',cache:'no-store',signal:request.signal});const data=await response.json();
        if(target!==root||!target.isConnected||seq!==sequence)return;
        if(!response.ok||data.error)throw new Error(data.error||'Approvals could not refresh. Retrying automatically.');
        paint(data,{updateURL:updateURL||data.page!==targetPage});clearError('connection');
        if(manual||!data.unchanged)status('Approvals updated · Live updates every 5 seconds.');
    }catch(error){if(target===root&&target.isConnected&&seq===sequence&&(error.name!=='AbortError'||timedOut))failure(timedOut?'The refresh took too long. Retrying automatically.':error.message);}
    finally{clearTimeout(timeout);if(controller===request)controller=null;if(seq===sequence)schedule();}
}
async function decide(form,submitter){
    if(saving||!form.reportValidity())return;const button=submitter||form.querySelector('button[name="decision"]');if(!button?.value)return;
    const target=root,body=new URLSearchParams(new FormData(form));body.set('decision',button.value);body.set('csrf',root.dataset.approvalCsrf);body.set('p',String(page));
    controller?.abort();controller=null;++sequence;clearTimeout(timer);saving=true;clearError();status('Saving decision…');form.setAttribute('aria-busy','true');
    const controls=[...root.querySelectorAll('.oa-decide button')].map(el=>[el,el.disabled]);controls.forEach(([el])=>el.disabled=true);let needsRefresh=false;
    try{
        const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',body});const data=await response.json();
        if(!response.ok||data.error)throw new Error(data.error||'The decision could not be confirmed. Refresh the list before trying again.');
        if(target===root&&target.isConnected){const note=form.querySelector('[name="note"]');if(note)note.defaultValue=note.value;paint(data);status(data.message+' Live updates are on.');const state=root.querySelector('[data-approval-request="'+Number(body.get('approval_id'))+'"] .oa-status');if(state){state.tabIndex=-1;state.focus({preventScroll:true});}}window.PortalNotifications?.refresh();
    }catch(error){if(target===root&&target.isConnected){failure(error.message,'decision');status('Check the latest request status before trying again.');needsRefresh=true;}}
    finally{saving=false;form.removeAttribute('aria-busy');controls.forEach(([el,disabled])=>{if(el.isConnected)el.disabled=disabled;});schedule();if(needsRefresh)refresh();else if(target!==root&&root?.isConnected)refresh();}
}
function mount(){
    const next=document.getElementById('approvalLiveBoard');if(next===root&&next?.isConnected){schedule();return;}
    clearTimeout(timer);controller?.abort();controller=null;++sequence;listeners?.abort();listeners=null;root=next;errorKind=null;
    if(!root)return;page=Number(root.dataset.approvalPage)||1;revision=root.dataset.approvalRevision||'';listeners=new AbortController();const signal=listeners.signal;
    root.addEventListener('submit',event=>{const form=event.target.closest('.oa-decide');if(!form)return;event.preventDefault();event.stopPropagation();decide(form,event.submitter);},{capture:true,signal});
    root.addEventListener('click',event=>{
        const manual=event.target.closest('[data-approval-refresh]');if(manual){event.preventDefault();refresh({manual:true});return;}
        const link=event.target.closest('.oa-pagination a');if(!link||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        event.preventDefault();event.stopPropagation();const p=Number(new URL(link.href,location.href).searchParams.get('p'))||1;refresh({targetPage:p,manual:true,updateURL:true});
    },{capture:true,signal});
    refresh();
}
document.addEventListener('portal:page-loaded',mount);document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(document.hidden){controller?.abort();controller=null;++sequence;}else if(root?.isConnected)refresh();});
window.ApprovalBoardLive={mount,refresh};mount();})();
