/* Adapt legacy office pages to the shared workspace without replacing their record handlers. */
(() => {
    'use strict';
    let observer=null,root=null,visibleDialogs=new WeakSet();
    const decorated=new WeakSet();let fieldNumber=0;
    const hints={add:'Enter the details below. Fields marked * are required.',edit:'Review the existing details before saving your changes.',view:'Review the information for this record.',security:'Check the selected record before continuing.'};
    function annotateTable(table){
        if(table.hasAttribute('data-cw-no-cards'))return;
        const heads=[...table.querySelectorAll(':scope > thead > tr')];
        if(heads.length!==1)return;
        const labels=[...heads[0].children].map((th,index)=>th.textContent.trim()||(index===0?'Select':'Actions'));
        const simple=labels.length>=4&&labels.length<=10&&![...heads[0].children].some(th=>th.colSpan>1||th.rowSpan>1);
        if(simple){table.setAttribute('data-cw-card-table','');for(const row of table.querySelectorAll(':scope > tbody > tr')){const cells=[...row.children];if(cells.length!==labels.length)continue;cells.forEach((td,i)=>{if(!td.hasAttribute('data-label'))td.setAttribute('data-label',labels[i]);});}}
        if(!simple&&labels.length>=4&&!decorated.has(table)){const wrap=table.parentElement;if(wrap){wrap.style.overflowX='auto';wrap.style.maxWidth='100%';const note=document.createElement('p');note.className='cw-table-hint cw-screen-only';note.textContent='On a smaller screen, scroll this table sideways to see all columns.';wrap.after(note);}decorated.add(table);}
        if(!table.querySelector('caption')){const caption=document.createElement('caption');caption.className='cw-screen-label';caption.textContent=(root?.querySelector('.cw-page-header h1')?.textContent||'Office records')+' table';table.prepend(caption);}
    }
    function annotateFields(scope){
        for(const label of scope.querySelectorAll('label:not([for])')){
            if(label.querySelector('input,select,textarea'))continue;
            const field=label.parentElement?.querySelector('input:not([type=hidden]),select,textarea');
            if(!field)continue;field.id=field.id||'cw-field-'+(++fieldNumber);label.htmlFor=field.id;label.setAttribute('data-cw-linked-label','');
        }
        const names={departmentFilter:'Department',strandFilter:'SHS strand',programFilter:'College program',gradeFilter:'Grade / year level',gradeLevelFilter:'Grade / year level',statusFilter:'Status',schoolYearFilter:'School year',yearFilter:'School year',filter_ses:'SES category'};
        for(const field of scope.querySelectorAll('input:not([type=hidden]),select,textarea')){
            if(field.getAttribute('aria-label')||field.id&&scope.querySelector('label[for="'+CSS.escape(field.id)+'"]')||field.closest('label'))continue;
            const name=names[field.id]||field.placeholder||field.name?.replaceAll('_',' ')||'Filter records';field.setAttribute('aria-label',name.charAt(0).toUpperCase()+name.slice(1));
            if(field.tagName==='SELECT'&&!field.closest('.pd-overlay')){const label=document.createElement('label');label.className='cw-inline-field';label.setAttribute('data-cw-linked-label','');field.id=field.id||'cw-field-'+(++fieldNumber);label.htmlFor=field.id;const text=document.createElement('span');text.textContent=name;field.before(label);label.append(text,field);}
        }
        for(const label of scope.querySelectorAll('label[data-cw-linked-label]')){const field=document.getElementById(label.htmlFor);if(!field)continue;const hidden=field.classList.contains('hidden')||field.style.display==='none';if(label.classList.contains('hidden')!==hidden)label.classList.toggle('hidden',hidden);}
    }
    function decorateModal(dialog){
        if(dialog.hasAttribute('data-portal-dialog'))return;
        const card=dialog.firstElementChild,heading=card?.querySelector('h2,h3');if(!card||!heading)return;
        const header=heading.parentElement;
        if(header===card||!header.querySelector('button'))return;
        const wasWide=/max-w-(?:3xl|4xl|5xl|6xl)/.test(card.className);
        dialog.classList.add('pd-overlay');dialog.setAttribute('data-portal-dialog','');dialog.setAttribute('data-managed-dialog','');dialog.setAttribute('data-cw-dialog','');
        const title=heading.textContent.toLowerCase();const tone=/edit|update|reschedule|assign/.test(title)?'edit':/view|details|history|results/.test(title)?'view':/holiday|limit/.test(title)?'security':'add';
        dialog.dataset.dialogTone=tone==='view'?'add':tone;dialog.setAttribute('role','dialog');dialog.setAttribute('aria-modal','true');heading.id=heading.id||dialog.id+'Title';dialog.setAttribute('aria-labelledby',heading.id);
        card.classList.add('pd-card');card.setAttribute('data-cw-dialog-card','');if(wasWide)card.classList.add('pd-card-wide');header.classList.add('pd-header');header.setAttribute('data-cw-dialog-header','');
        const subtitle=document.createElement('small');subtitle.textContent=hints[tone]||hints.add;heading.appendChild(subtitle);
        for(const button of header.querySelectorAll('button')){button.type='button';button.classList.add('pd-close');button.setAttribute('aria-label','Close '+heading.childNodes[0].textContent.trim()+' dialog');}
        const form=[...card.children].find(n=>n.tagName==='FORM');
        if(form){
            let footer=[...form.children].reverse().find(node=>node.children.length>0&&node.querySelector('button[type=submit],button:not([type]),input[type=submit]')&&node.querySelector('button[type=button],button[onclick]')&&!node.querySelector('input:not([type=hidden]),select,textarea'));
            if(!footer){footer=document.createElement('div');for(const node of [...form.children])if(node.matches('button,input[type=submit]'))footer.appendChild(node);if(!footer.children.length)footer=null;}
            const body=document.createElement('div');body.className='pd-body';
            for(const child of [...form.children])if(child!==footer&&!child.matches('input[type=hidden]'))body.appendChild(child);
            form.appendChild(body);if(footer){footer.classList.add('pd-footer');form.appendChild(footer);}
        }else{
            const content=[...card.children].find(node=>node!==header);if(content)content.classList.add('pd-body');
        }
        for(const button of card.querySelectorAll('button:not(.pd-close)')){button.classList.add('pd-button');if(button.type==='submit'||button.matches('[class*=bg-primary],[class*=bg-green],[class*=bg-blue]'))button.classList.add('pd-button-primary');}
    }
    function syncModal(dialog){
        if(!dialog.classList.contains('pd-overlay'))dialog.classList.add('pd-overlay');
        const card=dialog.querySelector('[data-cw-dialog-card]'),header=dialog.querySelector('[data-cw-dialog-header]');
        if(card&&!card.classList.contains('pd-card'))card.classList.add('pd-card');if(header&&!header.classList.contains('pd-header'))header.classList.add('pd-header');
        if(dialog.classList.contains('hidden')){if(visibleDialogs.has(dialog)){visibleDialogs.delete(dialog);window.PortalDialogs?.close(dialog);}}
        else if(!visibleDialogs.has(dialog)){visibleDialogs.add(dialog);window.PortalDialogs?.open(dialog);const first=[...dialog.querySelectorAll('.pd-body input:not([type=hidden]),.pd-body select,.pd-body textarea')].find(field=>field.offsetParent!==null)||dialog.querySelector('.pd-close');if(first&&!dialog.contains(document.activeElement))first.focus({preventScroll:true});}
    }
    function tabButtons(){return root?[...root.querySelectorAll('[id^="tab-"]')].filter(button=>root.querySelector('#'+CSS.escape('panel-'+button.id.slice(4)))):[];}
    function syncTabs(){for(const button of tabButtons()){const panel=root.querySelector('#'+CSS.escape('panel-'+button.id.slice(4)));const active=!panel.classList.contains('hidden');button.setAttribute('role','tab');button.setAttribute('aria-controls',panel.id);button.setAttribute('aria-selected',String(active));button.parentElement.setAttribute('role','tablist');button.parentElement.setAttribute('aria-label','Record categories');panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',button.id);}}
    function decorate(){
        if(!root)return;
        for(const table of root.querySelectorAll('.cw-content table'))annotateTable(table);
        for(const dialog of root.querySelectorAll('.cw-content div[id].fixed.inset-0'))decorateModal(dialog);
        for(const dialog of root.querySelectorAll('[data-cw-dialog]'))syncModal(dialog);
        annotateFields(root);syncTabs();
        for(const form of root.querySelectorAll('form')){
            const controls=[form,...form.querySelectorAll('button[onclick]')];
            for(const control of controls){const attribute=control===form?'onsubmit':'onclick';const code=control.getAttribute(attribute)||'';const match=code.match(/^\s*return confirm\((['"])(.*?)\1\);?\s*$/);if(!match)continue;
                form.dataset.confirmTitle=match[2];form.dataset.confirmMessage='Review the selected record before continuing.';form.dataset.confirmLabel=/remove/i.test(match[2])?'Remove':/delete/i.test(match[2])?'Delete':'Confirm';control.removeAttribute(attribute);
            }
        }
        for(const button of root.querySelectorAll('button[title]')){if(button.classList.contains('pd-close'))continue;if(!button.getAttribute('aria-label'))button.setAttribute('aria-label',button.title);if(!button.textContent.trim()&&button.title){const span=document.createElement('span');span.className='cw-action-label';span.textContent=button.title;button.appendChild(span);}}
    }
    function setup(){
        observer?.disconnect();root=document.querySelector('.cw-workspace');if(!root)return;
        const legacy=root.querySelector('[data-cw-legacy-title]'),actions=root.querySelector('#workspacePageActions');
        if(legacy&&actions&&legacy.parentElement.classList.contains('flex')){
            const parent=legacy.parentElement;for(const node of [...parent.children])if(node!==legacy)actions.appendChild(node);parent.setAttribute('data-cw-old-heading','');
        }
        for(const form of root.querySelectorAll('form[method=get],form[method=GET]')){form.classList.add('cw-filter-bar');if(form.querySelector('input[name=page]'))form.action='layout.php';}
        decorate();
        observer=new MutationObserver(records=>{
            if(records.some(record=>record.type==='childList')){observer.disconnect();decorate();observer.observe(root,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style']});}
            else {observer.disconnect();for(const dialog of root.querySelectorAll('[data-cw-dialog]'))syncModal(dialog);annotateFields(root);syncTabs();observer.observe(root,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style']});}
        });
        observer.observe(root,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style']});
    }
    function capture(){if(!root)return null;const selected=tabButtons().find(button=>button.getAttribute('aria-selected')==='true');return {kind:'counselor',page:root.dataset.workspacePage,tab:selected?.id||null};}
    function restore(state){if(!root||root.dataset.workspacePage!==state?.page)return;const button=state.tab&&document.getElementById(state.tab);if(button)button.click();}
    document.addEventListener('portal:page-loaded',setup);
    window.CounselorWorkspace={capture,restore};
})();
