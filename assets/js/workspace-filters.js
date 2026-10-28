/* Compact filters keep each page's own field handlers and server forms. */
(() => {
    let serial=0;
    const fieldNames={departmentFilter:'Department',strandFilter:'Strand / program',programFilter:'Program',gradeFilter:'Grade / year',gradeLevelFilter:'Grade / year',dateFrom:'From date',dateTo:'To date',dateFromFilter:'From date',dateToFilter:'To date',sortFilter:'Sort order',perPageFilter:'Rows per page',filter_ses:'SES category',status:'Status',search:'Search'};
    function setup(){
        const root=document.querySelector('.cw-workspace');if(!root)return;
        const candidates=new Set(root.querySelectorAll('.cw-content form[method="GET" i]'));
        for(const anchor of root.querySelectorAll('#searchInput,#departmentFilter,#dateFromFilter')){if(anchor.closest('form,.pd-overlay,[data-template-editor]'))continue;const host=anchor.closest('div.flex.flex-wrap');if(host)candidates.add(host);}
        for(const host of candidates){
            if(host.dataset.wfReady)continue;
            const fields=[...host.querySelectorAll('input:not([type=hidden]),select')];if(!fields.length)continue;
            host.dataset.wfReady='1';host.classList.add('wf-widget');
            const form=host.tagName==='FORM'?host:null,toolbar=document.createElement('div'),panel=document.createElement('div'),chips=document.createElement('div');toolbar.className='wf-toolbar';panel.className='wf-panel';panel.id='workspaceFilters-'+(++serial);panel.hidden=true;chips.className='wf-chips';chips.setAttribute('aria-label','Selected filters');
            const search=fields.find(f=>f.type==='search'||f.id==='searchInput'||f.name==='search');
            const baseline=new Map(fields.map(f=>[f,f.tagName==='SELECT'?( [...f.options].find(o=>o.value===''||o.value==='all')?.value??f.options[0]?.value??''):'' ]));
            const names=new Map(fields.map(f=>[f,fieldNames[f.id]||fieldNames[f.name]||f.getAttribute('aria-label')||f.name?.replaceAll('_',' ')||'Filter']));
            function topItem(field){let item=field;while(item.parentElement!==host&&item.parentElement)item=item.parentElement;return item;}
            for(const field of fields){const item=topItem(field);if(field===search){item.classList.add('wf-search');toolbar.appendChild(item);}else{if(field.getAttribute('aria-label')==='Filter records')field.setAttribute('aria-label',names.get(field));if(item===field){const label=document.createElement('label');label.className='wf-field';const span=document.createElement('span');span.textContent=names.get(field);label.append(span,field);panel.appendChild(label);}else{if(!field.closest('label')&&!item.querySelector('label')){const label=document.createElement('label');field.id=field.id||'workspace-filter-'+(++serial);label.htmlFor=field.id;label.textContent=names.get(field);label.className='wf-control-name';field.before(label);}panel.appendChild(item);}}}
            for(const item of [...host.children]){if(item.matches('button,input[type=submit]'))toolbar.appendChild(item);else if(item.matches('a')&&/reset|clear/i.test(item.textContent))item.remove();}
            const toggle=document.createElement('button');toggle.type='button';toggle.className='wf-toggle';toggle.setAttribute('aria-controls',panel.id);toggle.setAttribute('aria-expanded','false');toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));});
            if(panel.children.length)toolbar.insertBefore(toggle,toolbar.children[search?1:0]||null);
            const clear=document.createElement('button');clear.type='button';clear.className='wf-clear';clear.textContent='Clear all';
            function notify(field){if(typeof window.currentPage==='number')window.currentPage=1;if(form){form.requestSubmit();return;}const type=field.hasAttribute('onkeyup')?'keyup':field===search||field.hasAttribute('oninput')?'input':'change';field.dispatchEvent(type==='keyup'?new KeyboardEvent(type,{key:'Backspace',bubbles:true}):new Event(type,{bubbles:true}));}
            function update(){chips.replaceChildren();let count=0;for(const field of fields){if(field.value===baseline.get(field)||!field.value)continue;const chip=document.createElement('button');chip.type='button';chip.className='wf-chip';const value=field.tagName==='SELECT'?field.selectedOptions[0]?.textContent:field.value;chip.textContent=names.get(field)+': '+value+' ×';chip.setAttribute('aria-label','Remove '+names.get(field)+' filter');chip.addEventListener('click',()=>{field.value=baseline.get(field);notify(field);update();});chips.appendChild(chip);if(field!==search)count++;}toggle.textContent='Filters'+(count?' ('+count+')':'');clear.hidden=!chips.children.length;if(chips.children.length)chips.appendChild(clear);chips.hidden=!chips.children.length;}
            clear.addEventListener('click',()=>{for(const field of fields)field.value=baseline.get(field);notify(fields.find(f=>f.id==='departmentFilter')||fields.find(f=>f!==search)||search);update();});
            host.append(toolbar,panel,chips);host.addEventListener('change',()=>{if(typeof window.currentPage==='number')window.currentPage=1;},true);host.addEventListener('change',update);host.addEventListener('input',update);update();
        }
    }
    document.addEventListener('portal:page-loaded',setup);
})();
