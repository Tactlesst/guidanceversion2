/* Persistent portal shell with disposable, scoped page scripts. */
(() => {
    'use strict';
    const main = document.getElementById('mainContent');
    if (!main || window.PortalNavigation) return;
    const browserFetch=window.fetch.bind(window);
    const nativeFetch=(input,options={})=>{
        const target=new URL(input instanceof Request?input.url:String(input),location.href),method=String(options.method||(input instanceof Request?input.method:'GET')).toUpperCase();
        if(method==='POST'&&target.origin===location.origin&&target.pathname===new URL('layout.php',location.href).pathname){const headers=new Headers(options.headers||(input instanceof Request?input.headers:undefined));headers.set('X-Portal-CSRF',document.querySelector('meta[name="portal-csrf"]')?.content||'');return browserFetch(input,{...options,headers});}
        return browserFetch(input,options);
    };
    const originalAdd = EventTarget.prototype.addEventListener;
    const originalRemove = EventTarget.prototype.removeEventListener;
    const sharedGlobals = new Set(['tailwind', 'Swal', 'Chart', 'bootstrap']);
    const libraries = new Map();
    const route = new URL('layout.php', location.href);
    let runtime = null, request = null, generation = 0, restoringView = null, saving = false, announcementTimer = null;
    let currentURL = location.href;
    const status = document.createElement('div');
    status.className = 'portal-navigation-status'; status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite'); document.body.appendChild(status);

    function isRoute(value) {
        const url = new URL(value, location.href);
        return url.origin === route.origin && url.pathname === route.pathname && !url.searchParams.has('action');
    }
    function samePage(value) {
        return (new URL(value, location.href).searchParams.get('page') || 'dashboard') === (new URL(location.href).searchParams.get('page') || 'dashboard');
    }
    function refresh() { return navigate(location.href, {replace:true, preserveView:true}); }
    async function preparePage(content, head, base, signal) {
        const bodyTemplate=document.createElement('template');bodyTemplate.innerHTML=content;
        const headTemplate=document.createElement('template');headTemplate.innerHTML=head||'';
        const currentStyles=new Set([...main.querySelectorAll('link[rel~="stylesheet"][href]'),...document.querySelectorAll('link[data-portal-page-head][rel~="stylesheet"][href],link[data-portal-page-style]')]);
        const existingStyles=[...document.querySelectorAll('link[rel~="stylesheet"][href]')].filter(link=>!link.hasAttribute('data-portal-style-pending'));
        const kept=new Set(),added=[],sources=new Map(),tasks=[];
        function waitForStyle(link) {
            if(link.sheet)return Promise.resolve();
            return new Promise((resolve,reject)=>{
                const cleanup=()=>{window.clearTimeout(timer);link.removeEventListener('load',loaded);link.removeEventListener('error',failed);signal.removeEventListener('abort',aborted);};
                const loaded=()=>{cleanup();resolve();};
                const failed=()=>{cleanup();reject(new Error('A page stylesheet could not load. Please try again.'));};
                const aborted=()=>{cleanup();reject(new DOMException('Navigation canceled','AbortError'));};
                const timer=window.setTimeout(failed,15000);
                link.addEventListener('load',loaded,{once:true});link.addEventListener('error',failed,{once:true});signal.addEventListener('abort',aborted,{once:true});
                if(signal.aborted)aborted();
            });
        }
        try {
            for(const template of [headTemplate,bodyTemplate]) {
                for(const link of [...template.content.querySelectorAll('link[rel~="stylesheet"][href]')]) {
                    const href=new URL(link.getAttribute('href'),base).href;
                    const existing=existingStyles.find(item=>item.href===href);
                    if(existing){if(currentStyles.has(existing))kept.add(existing);tasks.push(waitForStyle(existing));link.remove();}
                    else {
                        const media=link.getAttribute('media');link.href=href;link.media='not all';link.setAttribute('data-portal-style-pending','');
                        added.push({link,media});const ready=waitForStyle(link);document.head.appendChild(link);tasks.push(ready);
                    }
                }
                for(const script of template.content.querySelectorAll('script[src]')) {
                    const type=script.dataset.portalType||script.getAttribute('type')||'text/javascript';
                    if(!['text/javascript','application/javascript','text/portal-script',''].includes(type))continue;
                    const source=new URL(script.getAttribute('src'),base);
                    if(source.origin!==location.origin || sources.has(source.href))continue;
                    const code=nativeFetch(source.href,{credentials:'same-origin',signal}).then(response=>{if(!response.ok)throw new Error('Could not load a page script.');return response.text();});
                    sources.set(source.href,code);tasks.push(code);
                }
            }
            await Promise.all(tasks);
            return {bodyTemplate,headTemplate,sources,activate(){
                for(const link of kept){link.removeAttribute('data-portal-page-head');link.setAttribute('data-portal-page-style','');document.head.appendChild(link);}
                for(const {link,media} of added){if(media===null)link.removeAttribute('media');else link.media=media;link.removeAttribute('data-portal-style-pending');link.removeAttribute('data-portal-page-head');link.setAttribute('data-portal-page-style','');}
                for(const link of currentStyles)if(!kept.has(link))link.remove();
            },cancel(){for(const {link} of added)link.remove();}};
        } catch(error) {for(const {link} of added)link.remove();throw error;}
    }
    let releaseLayout=null;
    function holdLayout() {
        releaseLayout?.();
        const before={minHeight:main.style.minHeight,overflowAnchor:main.style.overflowAnchor};
        main.style.minHeight=main.getBoundingClientRect().height+'px';main.style.overflowAnchor='none';
        let released=false;
        const release=()=>{if(released)return;released=true;main.style.minHeight=before.minHeight;main.style.overflowAnchor=before.overflowAnchor;if(releaseLayout===release)releaseLayout=null;};
        releaseLayout=release;return release;
    }
    function updateShell(page, title, header) {
        const surveyLink=[...document.querySelectorAll('.portal-nav a[href]')].find(link=>['multiple_intelligence_survey','learning_style_inventory','learning_style_inventory_intermediate'].includes(new URL(link.href).searchParams.get('page')));
        const navigationPage=page==='survey_history'?(surveyLink?new URL(surveyLink.href).searchParams.get('page'):'dashboard'):page==='pds_history'?(document.querySelector('.portal-nav a[href*="page=view_pds"]')?'view_pds':'admin_view_pds'):page;
        document.title = title + ' - SRCB Guidance';
        document.querySelector('.portal-breadcrumb strong').textContent = page === 'dashboard' ? 'Overview' : title;
        if(header) {
            const template=document.createElement('template');template.innerHTML=header;
            const button=template.content.querySelector('#notifBtn'),menu=template.content.querySelector('#notifMenu');
            if(button && menu) {
                document.getElementById('notifBtn').replaceChildren(...button.childNodes);
                document.getElementById('notifBtn').setAttribute('aria-label',button.getAttribute('aria-label'));
                document.getElementById('notifMenu').replaceChildren(...menu.childNodes);
            }
        }
        for (const link of document.querySelectorAll('.portal-nav a[href]')) {
            const target = new URL(link.href).searchParams.get('page') || 'dashboard';
            const active = target === navigationPage || (['fill_pds', 'view_pds'].includes(target) && ['fill_pds', 'view_pds'].includes(navigationPage)) || (target === 'manage_exams' && navigationPage === 'release_results');
            link.classList.toggle('is-active', active && link.classList.contains('portal-nav-link'));
            link.classList.toggle('is-current', active && !link.classList.contains('portal-nav-link'));
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        }
        for (const button of document.querySelectorAll('[data-nav-group]')) {
            const group = document.getElementById(button.getAttribute('aria-controls'));
            const active = Boolean(group.querySelector('[aria-current="page"]'));
            button.classList.toggle('is-active', active);
            if (active) { group.classList.remove('hidden'); button.setAttribute('aria-expanded', 'true'); }
        }
        if (typeof window.setDrawer === 'function') window.setDrawer(false, false);
        if (typeof window.hideMenus === 'function') window.hideMenus();
    }
    function pageRuntime() {
        const globals = new Map(), bindings = Object.create(null), listeners = [], timers = new Set(), intervals = new Set(), createdNodes = new Set(), ready = [];
        const abort = new AbortController(); let disposed = false;
        function remember(name) { if (!globals.has(name)) globals.set(name, Object.getOwnPropertyDescriptor(window, name)); }
        function expose(name, getter, setter) {
            if (sharedGlobals.has(name) || ['window', 'document', 'location', 'history', 'fetch', 'PortalNavigation'].includes(name)) return;
            const previous = Object.getOwnPropertyDescriptor(window, name);
            if (previous && !previous.configurable) return;
            remember(name); bindings[name] = {get:getter, set:setter};
            Object.defineProperty(window, name, {configurable:true, enumerable:true, get:getter, set:setter});
        }
        function listen(target, type, callback, options) {
            if (type === 'DOMContentLoaded' || (target === window && type === 'load')) { ready.push(() => typeof callback === 'function' ? callback.call(target, new Event(type)) : callback.handleEvent(new Event(type))); return; }
            originalAdd.call(target, type, callback, options); listeners.push([target,type,callback,options]);
        }
        function timeout(callback, delay, ...args) {
            const id = window.setTimeout(() => { timers.delete(id); if (!disposed) callback(...args); }, delay); timers.add(id); return id;
        }
        function interval(callback, delay, ...args) { const id = window.setInterval(() => { if (!disposed) callback(...args); }, delay); intervals.add(id); return id; }
        function pageFetch(input, options={}) {
            const signals = [abort.signal, options.signal].filter(Boolean);
            const signal = signals.length > 1 ? AbortSignal.any(signals) : abort.signal;
            return nativeFetch(input, {...options, signal});
        }
        const pageLocation = new Proxy(location, {
            get(target,key) {
                if (key === Symbol.toPrimitive) return () => location.href;
                if (key === 'reload') return refresh;
                if (key === 'assign' || key === 'replace') return value => isRoute(value) ? navigate(value, {replace:key==='replace' || samePage(value), preserveView:samePage(value), force:true}) : location[key](value);
                const value = Reflect.get(target,key); return typeof value === 'function' ? value.bind(target) : value;
            },
            set(target,key,value) { if (key === 'href' && isRoute(value)) { navigate(value,{force:true, replace:samePage(value), preserveView:samePage(value)}); return true; } return Reflect.set(target,key,value); }
        });
        const documentProxy = new Proxy(document, {
            get(target,key) {
                if (key === 'createElement') return (...args) => {const node=target.createElement(...args);createdNodes.add(node);return node;};
                if (key === 'addEventListener') return (type,callback,options) => listen(document,type,callback,options);
                if (key === 'removeEventListener') return (type,callback,options) => originalRemove.call(document,type,callback,options);
                if (key === 'location') return pageLocation;
                const value = Reflect.get(target,key); return typeof value === 'function' ? value.bind(target) : value;
            },
            set(target,key,value) {
                if (String(key).startsWith('on')) { const before=target[key]; target[key]=value; listeners.push([null,()=>{target[key]=before;}]); return true; }
                return Reflect.set(target,key,value);
            }
        });
        let windowProxy;
        const specials = {
            document:documentProxy, location:pageLocation, fetch:pageFetch,
            setTimeout:timeout, clearTimeout:id=>{timers.delete(id);window.clearTimeout(id);},
            setInterval:interval, clearInterval:id=>{intervals.delete(id);window.clearInterval(id);},
            addEventListener:(type,callback,options)=>listen(window,type,callback,options),
            removeEventListener:(type,callback,options)=>originalRemove.call(window,type,callback,options)
        };
        windowProxy = new Proxy(window, {
            get(target,key) {
                if (['window','self','globalThis'].includes(key)) return windowProxy;
                if (key in specials) return specials[key];
                if (bindings[key]) return bindings[key].get();
                const value = Reflect.get(target,key);
                return typeof value === 'function' && ['scrollTo','scrollBy','open','alert','confirm','prompt','getComputedStyle','requestAnimationFrame','cancelAnimationFrame','matchMedia'].includes(key) ? value.bind(target) : value;
            },
            set(target,key,value) {
                if (key === 'location') { pageLocation.href=value; return true; }
                if (key === 'onload') { ready.push(value); return true; }
                if (!sharedGlobals.has(key)) remember(key);
                if (bindings[key]) bindings[key].set(value); else Reflect.set(target,key,value);
                return true;
            }
        });
        return {
            expose, fetch:pageFetch,
            execute(code, source) {
                if (disposed) return;
                // Declarations stay inside a page closure; inline event handlers get temporary accessors.
                const names = new Set();
                const syntax = window.acorn.parse(code,{ecmaVersion:'latest',allowReturnOutsideFunction:true});
                function pattern(node) {
                    if(!node)return;
                    if(node.type==='Identifier')names.add(node.name);
                    else if(node.type==='ObjectPattern')node.properties.forEach(property=>pattern(property.value||property.argument));
                    else if(node.type==='ArrayPattern')node.elements.forEach(pattern);
                    else if(node.type==='AssignmentPattern')pattern(node.left);
                    else if(node.type==='RestElement')pattern(node.argument);
                }
                for(const declaration of syntax.body) {
                    if(['FunctionDeclaration','ClassDeclaration'].includes(declaration.type))pattern(declaration.id);
                    if(declaration.type==='VariableDeclaration')declaration.declarations.forEach(item=>pattern(item.id));
                }
                const scope = new Proxy(Object.create(null), {
                    has:(target,key)=>key!=='__portal' && key!==Symbol.unscopables && !names.has(key),
                    get:(target,key)=>key===Symbol.unscopables?undefined:windowProxy[key],
                    set:(target,key,value)=>{windowProxy[key]=value;return true;}
                });
                const exports = [...names].map(name => `if(typeof ${name}!=="undefined")__portal.expose(${JSON.stringify(name)},()=>${name},value=>{try{${name}=value;}catch(error){}});`).join('\n');
                const program = new Function('__portal', `with(__portal.scope){\n${code}\n${exports}\n}\n//# sourceURL=${source}`);
                program.call(windowProxy, {scope,expose});
            },
            async initialize() {
                while (ready.length && !disposed) await ready.shift()();
            },
            dispose() {
                disposed = true; abort.abort();
                for (const timer of timers) window.clearTimeout(timer);
                for (const timer of intervals) window.clearInterval(timer);
                for (const [target,type,callback,options] of listeners) { if(target) originalRemove.call(target,type,callback,options); else type(); }
                for (const canvas of main.querySelectorAll('canvas')) window.Chart?.getChart(canvas)?.destroy();
                for (const node of createdNodes) if(node.isConnected && !main.contains(node)) node.remove();
                for (const [name, descriptor] of [...globals].reverse()) { if (descriptor) Object.defineProperty(window,name,descriptor); else delete window[name]; }
                window.Swal?.close();
            }
        };
    }
    async function executeScript(script, activeRuntime, base, sources=null) {
        const type = script.dataset.portalType || script.getAttribute('type') || 'text/javascript';
        if (!['text/javascript','application/javascript','text/portal-script',''].includes(type)) return;
        if (script.src) {
            const source = new URL(script.getAttribute('src'),base).href;
            const url = new URL(source);
            const shared = url.origin !== location.origin;
            if (shared) {
                if ([...document.scripts].some(existing=>existing.src===source && existing.type!=='text/portal-script')) return;
                if (!libraries.has(source)) libraries.set(source,new Promise((resolve,reject)=>{
                    const library=document.createElement('script');library.src=source;library.onload=resolve;library.onerror=()=>reject(new Error('Could not load a page library.'));document.head.appendChild(library);
                }));
                await libraries.get(source); return;
            }
            if(sources?.has(source)){activeRuntime.execute(await sources.get(source),url.pathname);return;}
            const response=await activeRuntime.fetch(source);if(!response.ok)throw new Error('Could not load a page script.');
            activeRuntime.execute(await response.text(),url.pathname);
        } else activeRuntime.execute(script.textContent,'portal-page-inline.js');
    }
    async function mount(content, head, base, initial=false, viewState=null, prepared=null) {
        runtime?.dispose(); runtime=pageRuntime(); const activeRuntime=runtime;
        if (!initial) {
            prepared.activate();
            document.querySelectorAll('[data-portal-page-head]').forEach(node=>node.remove());
            const extra=prepared.headTemplate;
            for(const node of [...extra.content.children]) {node.setAttribute('data-portal-page-head','');document.head.appendChild(node);}
            main.replaceChildren(prepared.bodyTemplate.content);
        }
        const scripts=[...document.querySelectorAll('script[data-portal-page-head]'),...main.querySelectorAll('script')];
        const ordered=[...scripts.filter(script=>!script.hasAttribute('defer')),...scripts.filter(script=>script.hasAttribute('defer'))];
        for(const script of ordered) {
            if(runtime!==activeRuntime) return;
            await executeScript(script,activeRuntime,base,prepared?.sources);
        }
        restoringView=viewState;
        try { await activeRuntime.initialize(); } finally { restoringView=null; }
        document.dispatchEvent(new CustomEvent('portal:page-loaded',{detail:{url:base}}));
    }
    function announce(message, error=false) {window.clearTimeout(announcementTimer);status.textContent=message;status.classList.toggle('is-error',error);status.classList.toggle('is-visible',Boolean(message));}
    async function navigate(value,options={}) {
        const url=new URL(value,location.href);
        if(saving && !options.submission){announce('Saving changes…');return false;}
        if(!isRoute(url.href)){location.assign(url.href);return;}
        const position={x:window.scrollX,y:window.scrollY};
        const keepScroll=options.preserveView||options.preserveScroll;
        const viewState=options.preserveView ? (typeof window.capturePortalPageState==='function'?window.capturePortalPageState():window.CounselorWorkspace?.capture()||null) : null;
        const number=++generation;request?.abort();request=new AbortController();
        let release=null;
        main.setAttribute('aria-busy','true');announce(options.submission?'Saving changes…':'Loading…');
        try {
            const response=await nativeFetch(url.href,{method:options.submission?'POST':'GET',body:options.body,headers:{'X-Portal-Navigation':'1','Accept':'application/json'},credentials:'same-origin',signal:request.signal});
            if(number!==generation)return;
            // Authentication/permission redirects remain normal server-controlled navigation.
            if(response.redirected && !isRoute(response.url)){location.assign(response.url);return;}
            if(!response.ok)throw new Error(options.submission?'Could not confirm the save. Check the record before trying again.':'This page could not be opened. Please try again.');
            if(!(response.headers.get('content-type')||'').includes('application/json')) {if(options.submission)throw new Error('Could not confirm the save. Check the record before trying again.');location.assign(response.url||url.href);return;}
            const page=await response.json();
            if(!page.content || !page.page || !page.title)throw new Error('The page response was incomplete.');
            if(page.identity!==document.body.dataset.portalUser){location.assign(response.url);return;}
            if(options.submission && page.feedback?.error){
                if(window.PortalDialogs?.showFeedback){announce('');window.PortalDialogs.showFeedback(page.feedback);}else announce(page.feedback.error,true);
                return false;
            }
            const prepared=await preparePage(page.content,page.head,response.url||url.href,request.signal);
            if(number!==generation){prepared.cancel();return;}
            const destination=options.submission?new URL(response.url):url;
            // Success messages are announced once; keep them out of the address and later refreshes.
            destination.searchParams.delete('success');
            const previousState={...(history.state||{}),portal:true,scroll:scrollY};
            if(!options.pop)history.replaceState(previousState,'',location.href);
            if(!options.pop)history[options.replace?'replaceState':'pushState']({portal:true,scroll:keepScroll?position.y:0},'',destination.href);
            currentURL=destination.href;updateShell(page.page,page.title,page.header);
            release=holdLayout();
            await mount(page.content,page.head,destination.href,false,viewState,prepared);
            if(number!==generation)return;
            if(viewState?.kind==='counselor')window.CounselorWorkspace?.restore(viewState);
            release();
            window.scrollTo({left:keepScroll?position.x:0,top:keepScroll?position.y:options.pop?(history.state?.scroll||0):0,behavior:'instant'});
            main.focus({preventScroll:true});announce('');
            if(options.submission && page.feedback?.success && !window.PortalDialogs?.showFeedback){announce(page.feedback.success);announcementTimer=window.setTimeout(()=>announce(''),5000);}
            return true;
        }catch(error){
            if(error.name==='AbortError')return;
            console.error('Portal navigation:',error);announce(options.submission?'Could not confirm the save. Check the record before trying again.':error.message||'Could not open this page. Please try again.',true);return false;
        }finally{release?.();if(number===generation)main.removeAttribute('aria-busy');}
    }
    async function submit(form,submitter=null) {
        if(saving)return false;
        if(!form.reportValidity())return false;
        const action=new URL(submitter?.getAttribute('formaction')||form.getAttribute('action')||location.href,location.href);
        if(!isRoute(action.href))throw new Error('This form cannot be saved here.');
        const body=new FormData(form);
        body.set('_portal_csrf',document.querySelector('meta[name="portal-csrf"]')?.content||'');
        if(submitter?.name)body.append(submitter.name,submitter.value);
        const returnURL=new URL(location.href);returnURL.searchParams.delete('success');
        body.set('_portal_return','layout.php'+returnURL.search);
        const buttons=[...form.querySelectorAll('button,input[type=submit]')].map(button=>[button,button.disabled]);
        saving=true;form.setAttribute('aria-busy','true');buttons.forEach(([button])=>{button.disabled=true;});
        try {return await navigate(action.href,{submission:true,body,replace:true,preserveView:true});}
        finally {saving=false;form.removeAttribute('aria-busy');buttons.forEach(([button,disabled])=>{button.disabled=disabled;});}
    }
    document.addEventListener('click',event=>{
        if(event.defaultPrevented || event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)return;
        const link=event.target.closest('a[href]');
        if(!link || link.hasAttribute('download') || (link.target && link.target!=='_self') || link.hasAttribute('data-full-navigation'))return;
        const raw=link.getAttribute('href');if(!raw || raw.startsWith('#'))return;
        if(!isRoute(link.href))return;
        event.preventDefault();navigate(link.href,{preserveScroll:samePage(link.href)});
    });
    document.addEventListener('submit',event=>{
        const form=event.target;if(event.defaultPrevented || !(form instanceof HTMLFormElement))return;
        if(form.matches('[data-portal-save]') && form.method.toLowerCase()==='post' && isRoute(form.action)){
            if(event.submitter?.hasAttribute('data-full-navigation'))return;
            event.preventDefault();submit(form,event.submitter);return;
        }
        if(form.method.toLowerCase()!=='get' || !isRoute(form.action))return;
        const url=new URL(form.action);for(const [key,value] of new FormData(form))url.searchParams.set(key,value);
        event.preventDefault();navigate(url.href,{force:true,preserveScroll:samePage(url.href)});
    });
    window.addEventListener('popstate',()=>navigate(location.href,{pop:true,force:true}));
    window.PortalNavigation={navigate,refresh,submit,getRestoreState:()=>restoringView,ready:null};
    history.replaceState({...history.state,portal:true,scroll:scrollY},'',location.href);
    window.PortalNavigation.ready=mount(null,null,location.href,true).catch(error=>{console.error(error);announce('Some page controls could not load. Refresh this page to try again.',true);});
})();
