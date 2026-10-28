/* Shared styling and small interactions for portal dialogs. */
(() => {
    'use strict';
    const opened=new Map(),confirming=new WeakSet(),approved=new WeakSet();
    let feedbackTimer=null;
    function synchronize(){for(const [dialog] of opened)if(!dialog.isConnected||dialog.classList.contains('hidden'))opened.delete(dialog);document.documentElement.classList.toggle('portal-dialog-open',opened.size>0);}
    function conceal(dialog){for(const toggle of dialog.querySelectorAll('[data-password-toggle]')){const input=document.getElementById(toggle.dataset.passwordToggle);if(input)input.type='password';toggle.textContent='Show';toggle.setAttribute('aria-pressed','false');}}
    function open(dialog){if(!dialog)return;const trigger=document.activeElement;conceal(dialog);dialog.classList.remove('hidden');if(!opened.has(dialog))opened.set(dialog,trigger);const body=dialog.querySelector('.pd-body');if(body)body.scrollTop=0;synchronize();}
    function close(dialog){if(!dialog)return;const trigger=opened.get(dialog);conceal(dialog);dialog.classList.add('hidden');opened.delete(dialog);synchronize();if(trigger?.isConnected)trigger.focus({preventScroll:true});}
    function fire(...args){
        const options=typeof args[0]==='object'?{...args[0]}:{title:args[0],text:args[1],icon:args[2]};
        const danger=/archive|delete|remove|restore selected/i.test(options.confirmButtonText||'');
        return window.Swal.fire({...options,heightAuto:false,scrollbarPadding:false,buttonsStyling:false,reverseButtons:true,
            customClass:{...options.customClass,popup:'pd-alert'+(danger?' pd-alert-danger':''),actions:'pd-alert-actions',confirmButton:'pd-alert-confirm',cancelButton:'pd-alert-cancel'},
            showClass:{popup:''},hideClass:{popup:''}});
    }
    function showFeedback(feedback){
        const error=typeof feedback?.error==='string'?feedback.error:'';
        const success=typeof feedback?.success==='string'?feedback.success:'';
        if(!error&&!success)return;
        window.clearTimeout(feedbackTimer);
        // Allow the page to restore its scroll and focus before focusing the popup.
        feedbackTimer=window.setTimeout(()=>fire({title:error?'Could not save changes':'Saved successfully',text:error||success,icon:error?'error':'success',confirmButtonText:'OK'}),0);
    }
    function readPageFeedback(){
        window.clearTimeout(feedbackTimer);
        const node=document.querySelector('[data-portal-feedback]');if(!node)return;
        let feedback;try{feedback=JSON.parse(node.textContent);}catch(error){node.remove();return;}
        node.remove();
        const url=new URL(window.location.href);
        if(url.searchParams.has('success')){url.searchParams.delete('success');window.history.replaceState(window.history.state,'',url.href);}
        showFeedback(feedback);
    }
    document.addEventListener('click',event=>{
        const toggle=event.target.closest('[data-password-toggle]');
        if(toggle){const input=document.getElementById(toggle.dataset.passwordToggle);if(!input)return;const reveal=input.type==='password';input.type=reveal?'text':'password';toggle.textContent=reveal?'Hide':'Show';toggle.setAttribute('aria-pressed',String(reveal));return;}
        const closeButton=event.target.closest('[data-dialog-close]');
        if(closeButton){close(document.getElementById(closeButton.dataset.dialogClose));return;}
        if(event.target.matches('[data-portal-dialog]'))close(event.target);
    });
    document.addEventListener('keydown',event=>{
        const dialog=[...opened.keys()].at(-1);if(window.Swal?.isVisible()||!dialog?.hasAttribute('data-managed-dialog'))return;
        if(event.key==='Escape'){close(dialog);event.preventDefault();}
        if(event.key==='Tab'){const items=[...dialog.querySelectorAll('button,input,a[href],select,textarea')].filter(el=>el.offsetParent!==null&&!el.disabled);if(!items.length)return;if(event.shiftKey&&document.activeElement===items[0]){items.at(-1).focus();event.preventDefault();}else if(!event.shiftKey&&document.activeElement===items.at(-1)){items[0].focus();event.preventDefault();}}
    });
    document.addEventListener('submit',event=>{
        const form=event.target;if(event.defaultPrevented||!form.matches('[data-confirm-title]'))return;
        if(approved.has(form)){approved.delete(form);return;}
        event.preventDefault();if(confirming.has(form))return;confirming.add(form);
        const submitter=event.submitter;
        fire({title:form.dataset.confirmTitle,text:form.dataset.confirmMessage,icon:'warning',showCancelButton:true,cancelButtonText:'Cancel',confirmButtonText:form.dataset.confirmLabel||'Continue'})
            .then(result=>{if(!result.isConfirmed)return;if(window.PortalNavigation)return window.PortalNavigation.submit(form,submitter);approved.add(form);form.requestSubmit(submitter);})
            .finally(()=>confirming.delete(form));
    });
    document.addEventListener('portal:page-loaded',()=>{synchronize();readPageFeedback();});
    window.PortalDialogs={open,close,fire,showFeedback};
})();
