(() => {
    const root=document.getElementById('studentListBuilder');if(!root)return;
    const get=id=>document.getElementById(id),escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fields=['departmentFilter','strandFilter','programFilter','gradeFilter'],columns=[get('listColumn1'),get('listColumn2'),get('listColumn3')];
    const presets={custom:['Student List','','',''],attendance:['Student Attendance','Date','Attendance','Signature'],acknowledgment:['Student Acknowledgment','Item received','Date','Signature'],records:['Student Records']};
    const storageKey='student-list-format:'+document.body.dataset.portalUser;
    let rows=[],groups=[],controller=null,loading=true,searchTimer;
    const columnVisibility = Object.fromEntries(['id','name','blank1','blank2','blank3','email','department','program','grade'].map(key=>[key,true]));
    function selectedColumns(current) {
        const detailed=current.kind==='records';
        const labels=detailed?['Student ID','Student name','Email','Department','Strand / Program','Grade / Year']:['Student ID','Student name',...current.columns];
        const keys=detailed?['id','name','email','department','program','grade']:['id','name','blank1','blank2','blank3'];
        const weights=detailed?[12,25,22,14,14,13]:[15,34,17,17,17];
        const all=keys.map((key,index)=>({key,index,label:labels[index],weight:weights[index],blank:!detailed&&index>=2}));
        let selected=all.filter(column=>columnVisibility[column.key]);
        if(!selected.length){columnVisibility.name=true;selected=all.filter(column=>columnVisibility[column.key]);}
        return {all,selected};
    }
    function syncColumnControls(current,config) {
        const choices=get('listColumnChoices');
        if(!choices.children.length){
            for(const key of Object.keys(columnVisibility)){
                const label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span');
                label.className='tpl-column-choice';label.dataset.columnKey=key;input.type='checkbox';input.id='includeListColumn-'+key;input.dataset.columnKey=key;label.append(input,text);choices.appendChild(label);
            }
            choices.addEventListener('change',event=>{
                const field=event.target;if(!field.dataset.columnKey)return;
                columnVisibility[field.dataset.columnKey]=field.checked;remember();render();
            });
        }
        for(const label of choices.children){
            const column=config.all.find(c=>c.key===label.dataset.columnKey);label.hidden=!column;
            if(!column)continue;
            const field=label.querySelector('input');field.checked=!!columnVisibility[column.key];field.disabled=field.checked&&config.selected.length===1;
            label.querySelector('span').textContent=column.label||'Blank column '+(column.index-1);
        }
        get('listColumnHint').textContent=config.selected.length+' of '+config.all.length+' columns selected. Keep at least one column.';
        columns.forEach((field,index)=>field.disabled=!columnVisibility['blank'+(index+1)]);
        get('templateDescription').textContent=current.kind==='records'?'Only the selected record fields are printed. No approval signatures are included.':'Only the checked columns are printed. Writing columns stay empty for filling in on paper.';
        const label=get('listTemplate').selectedOptions[0].textContent;
        get('templateSummary').textContent=label+' · '+config.selected.length+(config.selected.length===1?' column':' columns')+' · '+(current.grouping==='combined'?'One alphabetical list':'Separate program / strand lists');
    }
    function setEditorOpen(open) {
        get('templateEditorBody').hidden=!open;
        get('templateEditorToggle').setAttribute('aria-expanded',String(open));
        get('templateEditorToggle').textContent=open?'Hide options':'Show options';
    }

    function state(){return {kind:get('listTemplate').value,title:get('listTitle').value.trim()||presets[get('listTemplate').value][0],columns:columns.map(c=>c.value.trim()),grouping:get('listGrouping').value,signatures:get('listTemplate').value!=='records'&&get('listSignatures').checked,visible:{...columnVisibility},editorOpen:!get('templateEditorBody').hidden};}
    function remember(){try{sessionStorage.setItem(storageKey,JSON.stringify(state()));}catch(error){/* Printing remains available without browser storage. */}}
    function describe(){const detailed=get('listTemplate').value==='records';get('templateBlankColumns').hidden=detailed;get('listSignatures').disabled=detailed;get('templateDescription').textContent=detailed?'Detailed records include contact and academic fields. No approval signatures are printed.':'Student ID and name, plus three empty columns for writing on the printed sheet.';}
    function setPreset(){const preset=presets[get('listTemplate').value];get('listTitle').value=preset[0];columns.forEach((c,i)=>c.value=preset[i+1]||'');get('listGrouping').value=get('listTemplate').value==='records'?'combined':'program';get('listSignatures').checked=get('listTemplate').value!=='records';describe();remember();render();}
    function updateDependentFields(){const department=get('departmentFilter').value;for(const id of ['strandFilter','programFilter']){const field=get(id),active=id==='strandFilter'?department==='Senior High School':department==='Higher Education';field.classList.toggle('hidden',!active);if(!active)field.value='';for(const option of field.options){option.hidden=!!(option.value&&department&&option.dataset.department&&option.dataset.department!==department);}}}
    function scope(){const selected=fields.map(id=>get(id)).filter(f=>f.value&&!f.classList.contains('hidden')).map(f=>f.selectedOptions[0]?.textContent||f.value);if(get('searchInput').value.trim())selected.push('Search: '+get('searchInput').value.trim());return selected.join(' · ')||'All students';}
    function name(student){return [student.last_name, [student.first_name,student.middle_name].filter(Boolean).join(' ')].filter(Boolean).join(', ');}
    function cells(student,detailed){return detailed?[student.student_id||'N/A',name(student),student.email||'N/A',student.department||'N/A',student.strand||student.program||'N/A',student.grade_level||'N/A']:[student.student_id||'N/A',name(student),'','',''];}
    function buildGroups(){if(get('listGrouping').value==='combined')return rows.length?[{label:scope(),rows}]:[];const map=new Map();for(const student of rows){const dept=student.department||'No department',sub=dept==='Senior High School'?(student.strand||'Unassigned strand'):dept==='Higher Education'?(student.program||'Unassigned program'):'General';const key=JSON.stringify([dept,sub]);if(!map.has(key))map.set(key,{label:dept+' · '+sub,rows:[]});map.get(key).rows.push(student);}return [...map.values()].sort((a,b)=>a.label.localeCompare(b.label));}
    function render(){
        const openLabels=new Set([...get('studentGroups').querySelectorAll('details[open]')].map(d=>d.dataset.listLabel)),oldLabels=new Set([...get('studentGroups').querySelectorAll('details')].map(d=>d.dataset.listLabel));
        const current=state(),detailed=current.kind==='records',config=selectedColumns(current),labels=config.selected.map(column=>column.label);
        syncColumnControls(current,config);
        groups=buildGroups();get('listPreviewHeading').textContent=current.title;get('listScope').textContent=scope()+' · '+rows.length+' students · '+groups.length+(groups.length===1?' list':' lists');get('printSelectedList').disabled=loading||!rows.length;
        get('studentGroups').innerHTML=groups.map((group,index)=>`<details class="tpl-group" data-list-label="${escape(group.label)}" ${openLabels.has(group.label)||(!oldLabels.has(group.label)&&groups.length===1)?'open':''}><summary><span><strong>${escape(group.label)}</strong><small>${group.rows.length} students</small></span><button type="button" data-print-list="${index}" aria-label="Print ${escape(group.label)}"><i class="fas fa-print" aria-hidden="true"></i> Print this list</button></summary><p class="tpl-preview-note">Previewing ${Math.min(8,group.rows.length)} of ${group.rows.length} students. Printing includes every student in this list.</p><div class="overflow-x-auto"><table class="tpl-sample-table" style="min-width:${Math.max(240,config.selected.length*115)}px" data-cw-no-cards aria-label="Student list preview"><thead><tr>${labels.map((label,i)=>`<th>${escape(label)||'<span class="tpl-blank-label">Blank column '+(config.selected[i].index-1)+'</span>'}</th>`).join('')}</tr></thead><tbody>${group.rows.slice(0,8).map(student=>`<tr>${config.selected.map(column=>`<td${column.blank?' class="tpl-empty-cell"':''}>${escape(cells(student,detailed)[column.index])||'&nbsp;'}</td>`).join('')}</tr>`).join('')}</tbody></table></div></details>`).join('');
    }
    async function load(){
        controller?.abort();controller=new AbortController();const request=controller;loading=true;get('studentListStatus').textContent='Loading students…';get('printSelectedList').disabled=true;
        const params=new URLSearchParams({page:'student_list_report',action:'fetch',q:get('searchInput').value.trim()});for(const id of fields)params.set(id.replace('Filter',''),get(id).value);
        try{const response=await fetch('layout.php?'+params,{signal:request.signal});if(!response.ok)throw new Error('Could not load students.');const data=await response.json();if(data.error||!Array.isArray(data.rows))throw new Error('Could not load students.');if(request!==controller)return;const compare=new Intl.Collator('en',{sensitivity:'base',numeric:true});rows=data.rows.sort((a,b)=>{for(const key of ['last_name','first_name','middle_name']){const diff=compare.compare((a[key]||'').trim(),(b[key]||'').trim());if(diff)return diff;}return compare.compare(String(a.student_id||a.id),String(b.student_id||b.id));});loading=false;get('studentListStatus').textContent=rows.length?'':'No students match these filters.';render();}
        catch(error){if(error.name==='AbortError')return;rows=[];loading=false;get('studentListStatus').textContent='Students could not be loaded. Change a filter or refresh to try again.';render();}
    }
    async function print(index){
        if(loading||!rows.length)return;const current=state(),detailed=current.kind==='records',selected=index===undefined?groups:[groups[index]],config=selectedColumns(current),weight=config.selected.reduce((sum,column)=>sum+column.weight,0),firstBlank=config.selected.findIndex(column=>column.blank);get('printSelectedList').disabled=true;
        const reports=selected.map(group=>({title:current.title,context:group.label,date:'As of '+new Date().toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}),columns:config.selected.map(column=>column.label),widths:config.selected.map(column=>column.weight/weight*100),rows:group.rows.map(student=>config.selected.map(column=>cells(student,detailed)[column.index])),blankFrom:firstBlank<0?Infinity:firstBlank,signatures:current.signatures,signer:root.dataset.signer}));
        try{await window.OfficePrint.print(reports);}catch(error){officeAlert('Print list',error.message||'Could not prepare the print document.','error');}finally{if(get('printSelectedList'))get('printSelectedList').disabled=loading||!rows.length;}
    }
    for(const id of fields)get(id).addEventListener('change',()=>{if(id==='departmentFilter'){get('strandFilter').value='';get('programFilter').value='';}updateDependentFields();load();});
    get('searchInput').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,250);});
    get('listTemplate').addEventListener('change',setPreset);
    for(const field of [get('listTitle'),...columns,get('listGrouping'),get('listSignatures')])field.addEventListener(field.tagName==='SELECT'||field.type==='checkbox'?'change':'input',()=>{remember();render();});
    get('printSelectedList').addEventListener('click',()=>print());get('studentGroups').addEventListener('click',event=>{const button=event.target.closest('[data-print-list]');if(button){event.preventDefault();event.stopPropagation();print(Number(button.dataset.printList));}});
    try{const saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');if(saved&&presets[saved.kind]){get('listTemplate').value=saved.kind;get('listTitle').value=String(saved.title||presets[saved.kind][0]).slice(0,120);columns.forEach((c,i)=>c.value=String(saved.columns?.[i]||'').slice(0,40));get('listGrouping').value=saved.grouping==='combined'?'combined':'program';get('listSignatures').checked=!!saved.signatures;for(const key of Object.keys(columnVisibility))columnVisibility[key]=saved.visible?.[key]!==false;setEditorOpen(saved.editorOpen===true);}}catch(error){/* Use the blank sheet defaults. */}
    get('templateEditorToggle').addEventListener('click',()=>{setEditorOpen(get('templateEditorBody').hidden);remember();});
    const query=new URLSearchParams(location.search);if(presets[query.get('template')]){get('listTemplate').value=query.get('template');setPreset();}get('searchInput').value=query.get('q')||'';get('departmentFilter').value=query.get('department')||'';updateDependentFields();for(const id of fields.slice(1)){const value=query.get(id.replace('Filter',''));if(value)get(id).value=value;}if(query.get('grouping')==='combined')get('listGrouping').value='combined';describe();load();
})();
