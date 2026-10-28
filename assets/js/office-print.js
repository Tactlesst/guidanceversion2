/* Shared, local print document: no tabs, browser margin labels, or external services. */
(() => {
    const css = `@page{size:A4 portrait;margin:0}html,body{margin:0;padding:0;background:#fff;color:#111}body{font:10pt/1.35 Arial,sans-serif}.office-print-page{box-sizing:border-box;width:210mm;height:296mm;padding:12mm;break-after:page;page-break-after:always}.office-print-page:last-child{break-after:auto;page-break-after:auto}.print-heading{text-align:center;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:14px}.print-heading h1{font-size:14pt;margin:0 0 4px}.print-heading h2{font-size:12pt;margin:6px 0}.print-heading p{font-size:9pt;margin:3px 0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #111;padding:5px 6px;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#f0f0f0;font-weight:bold}td.blank-cell{height:22px}.print-end{margin-top:12px}.print-total{text-align:right;font-weight:bold;margin:0}.print-signatures{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:28px;text-align:center;font-size:9pt}.signature-line{border-top:1px solid #777;padding-top:5px;margin:25px 12px 0}.print-signatures p{margin:2px 0}.print-signatures small{font-size:8pt}`;
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function documentHTML(reports) {
        return `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${escape(reports[0]?.documentTitle||'Student List')}</title><style>${css}</style></head><body>${reports.map(report => `<article class="office-report"><header class="print-heading"><h1>St. Rita's College of Balingasag</h1><p>Guidance and Counseling Office</p><h2>${escape(report.title)}</h2><p>${escape(report.context)}</p><p>${escape(report.date)}</p></header><table><colgroup>${report.widths.map(w=>`<col style="width:${w}%">`).join('')}</colgroup><thead><tr>${report.columns.map(c=>`<th>${escape(c) || '&nbsp;'}</th>`).join('')}</tr></thead><tbody>${report.rows.map(row=>`<tr>${row.map((cell,i)=>`<td${i>=report.blankFrom?' class="blank-cell"':''}>${escape(cell) || '&nbsp;'}</td>`).join('')}</tr>`).join('')}</tbody></table><footer class="print-end"><p class="print-total">${escape(report.totalLabel||'Total students')}: ${report.rows.length}</p>${report.signatures?`<div class="print-signatures"><div class="signature-line"><p><strong>${escape(report.signer)}</strong></p><p>Guidance Counselor</p><small>Guidance and Counseling Office</small></div><div class="signature-line"><p>_____________________</p><p>School Administrator</p></div></div>`:''}</footer></article>`).join('')}</body></html>`;
    }
    function paginate(doc) {
        const reports = [...doc.querySelectorAll('.office-report')];
        if (!reports.length) throw new Error('No report to print.');
        const pages = doc.createElement('div');doc.body.replaceChildren(pages);
        for (const report of reports) {
            const header=report.querySelector('.print-heading'),source=report.querySelector('table'),end=report.querySelector('.print-end');
            const rows=[...source.tBodies[0].rows];let page,body,bottom,first=true;
            function addPage(){page=doc.createElement('section');page.className='office-print-page';pages.appendChild(page);if(first){page.appendChild(header);first=false;}const table=source.cloneNode(false);table.append(source.querySelector('colgroup').cloneNode(true),source.tHead.cloneNode(true));body=doc.createElement('tbody');table.appendChild(body);page.appendChild(table);bottom=page.getBoundingClientRect().bottom-parseFloat(doc.defaultView.getComputedStyle(page).paddingBottom)-1;}
            addPage();
            for(const row of rows){body.appendChild(row);if(row.getBoundingClientRect().bottom>bottom){row.remove();if(!body.rows.length)throw new Error('A row is too tall for this paper size.');addPage();body.appendChild(row);if(row.getBoundingClientRect().bottom>bottom)throw new Error('A row is too tall for this paper size.');}}
            page.appendChild(end);
            if(end.getBoundingClientRect().bottom>bottom){end.remove();if(body.rows.length<2)throw new Error('The approval area cannot fit with its student row.');const last=body.lastElementChild;last.remove();addPage();body.appendChild(last);page.appendChild(end);if(end.getBoundingClientRect().bottom>bottom)throw new Error('The approval area cannot fit with its student row.');}
        }
    }
    async function print(reports) {
        if(document.getElementById('officePrintFrame'))return;
        if(!reports.length||reports.some(r=>!r.rows.length))throw new Error('Choose a list with students before printing.');
        const previous=document.activeElement,frame=document.createElement('iframe');frame.id='officePrintFrame';frame.title='Student list print document';frame.setAttribute('aria-hidden','true');frame.tabIndex=-1;frame.style.cssText='position:fixed;left:-10000px;top:0;width:1024px;height:768px;border:0';
        function cleanup(){document.removeEventListener('portal:page-loaded',cleanup);frame.remove();if(previous?.isConnected)previous.focus({preventScroll:true});}
        await new Promise((resolve,reject)=>{frame.addEventListener('load',async()=>{try{if(frame.contentDocument.fonts)await frame.contentDocument.fonts.ready;paginate(frame.contentDocument);frame.contentWindow.addEventListener('afterprint',cleanup,{once:true});frame.contentWindow.focus();frame.contentWindow.print();resolve();}catch(error){cleanup();reject(error);}},{once:true});frame.srcdoc=documentHTML(reports);document.body.appendChild(frame);document.addEventListener('portal:page-loaded',cleanup,{once:true});});
    }
    window.OfficePrint={print,documentHTML,paginate};
})();
