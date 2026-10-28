(() => {
    if(window.PDSPaper)return;
    const contexts=new WeakMap(),active=new Set();
    const sizes={long:['215.9mm','330.2mm'],a4:['210mm','297mm'],letter:['215.9mm','279.4mm']};
    const imageReady=root=>Promise.all([...root.querySelectorAll('img')].map(img=>img.complete?Promise.resolve():new Promise(resolve=>{img.addEventListener('load',resolve,{once:true});img.addEventListener('error',resolve,{once:true});})));
    function fit(root){const ctx=contexts.get(root);if(!ctx)return;const available=root.parentElement.clientWidth-4;for(const stage of root.children){const sheet=stage.firstElementChild;if(!sheet)continue;sheet.style.transform='';const width=sheet.offsetWidth,height=sheet.offsetHeight,scale=ctx.actual?1:Math.min(1,available/width);sheet.style.transform=`scale(${scale})`;stage.style.width=width*scale+'px';stage.style.height=height*scale+'px';}}
    function paginate(root){
        const ctx=contexts.get(root);if(!ctx)return;
        const measure=document.createElement('div');measure.className='pds-measure-host';const holder=document.createElement('div');holder.className='pds-form-document';holder.dataset.paper=root.dataset.paper;measure.appendChild(holder);document.body.appendChild(measure);
        const pages=[];
        try{for(const source of ctx.sources){let page,content,bottom;const create=(continued=false)=>{page=source.cloneNode(false);page.className='pds-sheet';content=document.createElement('div');content.className='pds-page-content';page.appendChild(content);const footer=source.querySelector('.pds-page-footer').cloneNode(true);page.appendChild(footer);holder.appendChild(page);pages.push(page);if(continued){const heading=document.createElement('div');heading.className='pds-continuation-title';heading.textContent='Personal Data Sheet — continuation';content.appendChild(heading);}bottom=footer.getBoundingClientRect().top-8;};create();
            for(const original of source.querySelector('.pds-page-content').children){const block=original.cloneNode(true);content.appendChild(block);if(content.getBoundingClientRect().bottom<=bottom)continue;block.remove();if(content.children.length)create(true);content.appendChild(block);if(content.getBoundingClientRect().bottom<=bottom)continue;
                const table=block.querySelector('table[data-pds-repeat]');if(!table)throw new Error('A long field needs more space. Select a larger paper size or review the recorded text.');
                const rows=[...table.tBodies[0].rows];table.tBodies[0].replaceChildren();let currentBlock=block,currentBody=table.tBodies[0];
                for(const row of rows){currentBody.appendChild(row);if(content.getBoundingClientRect().bottom<=bottom)continue;row.remove();if(!currentBody.rows.length)throw new Error('A recorded row cannot fit on the selected paper.');const next=currentBlock.cloneNode(true);next.querySelector('tbody').replaceChildren();create(true);content.appendChild(next);currentBlock=next;currentBody=next.querySelector('tbody');currentBody.appendChild(row);if(content.getBoundingClientRect().bottom>bottom)throw new Error('A recorded row cannot fit on the selected paper.');}
            }
        }
        pages.forEach((page,index)=>{page.querySelector('[data-pds-page-number]').textContent='Page '+(index+1)+' of '+pages.length;});
        root.replaceChildren(...pages.map(page=>{const stage=document.createElement('div');stage.className='pds-page-stage';stage.appendChild(page);return stage;}));
        }finally{measure.remove();}
        fit(root);
    }
    async function mount(root,scope=root.parentElement){
        if(contexts.has(root)){fit(root);return;}
        const ctx={sources:[...root.querySelectorAll(':scope > .pds-sheet')].map(sheet=>sheet.cloneNode(true)),actual:false,observer:null};contexts.set(root,ctx);active.add(root);
        const status=scope.querySelector('[data-pds-status]');
        const prepare=async()=>{try{await imageReady(root);if(document.fonts)await document.fonts.ready;paginate(root);if(status)status.textContent='';}catch(error){if(status)status.textContent=error.message;throw error;}};
        scope.querySelector('[data-pds-paper-select]')?.addEventListener('change',async event=>{root.dataset.paper=event.target.value;try{await prepare();}catch(error){/* Keep the message visible and printing blocked. */}});
        for(const button of scope.querySelectorAll('[data-pds-zoom]'))button.addEventListener('click',()=>{ctx.actual=button.dataset.pdsZoom==='actual';for(const item of scope.querySelectorAll('[data-pds-zoom]'))item.setAttribute('aria-pressed',String(item===button));fit(root);});
        let lastWidth=0;ctx.observer=new ResizeObserver(()=>{const width=root.parentElement.clientWidth;if(width!==lastWidth){lastWidth=width;fit(root);}});ctx.observer.observe(root.parentElement);
        await prepare();
    }
    async function print(root){
        if(document.getElementById('pdsPrintFrame'))return;
        if(!root)throw new Error('Open a personal data sheet before printing.');
        if(!contexts.has(root))await mount(root);else paginate(root);
        const cssUrl=new URL(root.dataset.pdsStyle,location.href),response=await fetch(cssUrl);if(!response.ok)throw new Error('The form stylesheet could not load.');const css=await response.text();
        const frame=document.createElement('iframe');frame.id='pdsPrintFrame';frame.title='Personal data sheet print document';frame.setAttribute('aria-hidden','true');frame.tabIndex=-1;frame.style.cssText='position:fixed;left:-20000px;top:0;width:1100px;height:900px;border:0';
        const paper=sizes[root.dataset.paper]||sizes.long,previous=document.activeElement;
        function cleanup(){document.removeEventListener('portal:page-loaded',cleanup);frame.remove();if(previous?.isConnected)previous.focus({preventScroll:true});}
        await new Promise((resolve,reject)=>{frame.addEventListener('load',async()=>{try{await imageReady(frame.contentDocument);if(frame.contentDocument.fonts)await frame.contentDocument.fonts.ready;frame.contentWindow.addEventListener('afterprint',cleanup,{once:true});frame.contentWindow.focus();frame.contentWindow.print();resolve();}catch(error){cleanup();reject(error);}},{once:true});frame.srcdoc='<!doctype html><html><head><meta charset="utf-8"><title>Personal Data Sheet</title><style>'+css+'@page{size:'+paper.join(' ')+';margin:0}</style></head><body>'+root.outerHTML+'</body></html>';document.body.appendChild(frame);document.addEventListener('portal:page-loaded',cleanup,{once:true});});
    }
    document.addEventListener('portal:page-loaded',()=>{for(const root of active)if(!root.isConnected){contexts.get(root)?.observer?.disconnect();active.delete(root);}});
    function dispose(root){const ctx=contexts.get(root);ctx?.observer?.disconnect();active.delete(root);contexts.delete(root);}
    window.PDSPaper={mount,print,dispose};
})();
