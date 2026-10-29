const academicSections={
    department:{label:'Departments',singular:'department',name:'Department name',hint:'The main divisions of your school. Programs, strands, and grade levels belong to a department.',examples:'Examples: Junior High School, Senior High School, Higher Education',table:'academic_departments',parent:false,description:true,example:'e.g., Higher Education'},
    program:{label:'College programs',singular:'college program',name:'Program name',hint:'Courses or degrees offered by the college department. Assign each program to its department.',examples:'Examples: BSIT, BSED, BS Business Administration',table:'academic_programs',parent:true,description:true,example:'e.g., BS Information Technology'},
    strand:{label:'SHS strands',singular:'SHS strand',name:'Strand name',hint:'Study strands offered to senior high school students. Assign each strand to its department.',examples:'Examples: STEM, ABM, HUMSS',table:'academic_strands',parent:true,description:true,example:'e.g., STEM'},
    grade:{label:'Grade & year levels',singular:'grade / year level',name:'Grade / year level',hint:'The levels students enroll in, from basic education grades to college year levels.',examples:'Examples: Grade 7, Grade 11, 1st Year College',table:'academic_grade_levels',parent:true,description:false,example:'e.g., Grade 11 or 1st Year College'}
};
let academicSection='department',academicPage=1,academicPageSize=10,academicDialog=null,academicRestoreFocus=null;
function academicEscape(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
function academicFilteredRows(){
    const text=document.getElementById('academicSearch').value.trim().toLocaleLowerCase();
    const status=document.getElementById('academicStatus').value;
    const parent=document.getElementById('academicDepartment').value;
    return (academicSettingsPayload[academicSection]||[]).filter(item=>{
        const haystack=[item.name,item.description,item.department_name].filter(Boolean).join(' ').toLocaleLowerCase();
        return (!text||haystack.includes(text))&&(status===''||String(item.is_active)===status)&&(!academicSections[academicSection].parent||!parent||String(item.department_id)===parent);
    });
}
function selectAcademicSection(section,saveURL=true){
    if(!Object.hasOwn(academicSections,section))return;
    academicSection=section;academicPage=1;
    if(saveURL) for(const id of ['academicSearch','academicStatus','academicDepartment'])document.getElementById(id).value='';
    const config=academicSections[section];
    for(const tab of document.querySelectorAll('[data-ac-section]')){const selected=tab.dataset.acSection===section;tab.classList.toggle('is-selected',selected);tab.setAttribute('aria-selected',String(selected));tab.tabIndex=selected?0:-1;}
    document.getElementById('academicPanel').setAttribute('aria-labelledby','academicTab-'+section);
    document.getElementById('academicListTitle').textContent=config.label;
    document.getElementById('academicListHint').textContent=config.hint;
    document.getElementById('academicListExamples').textContent=config.examples;
    document.getElementById('academicPanel').dataset.section=section;
    document.getElementById('academicAddLabel').textContent='Add '+config.singular;
    document.getElementById('academicSearch').placeholder='Search '+config.label.toLowerCase();
    document.getElementById('academicDepartmentFilter').classList.toggle('hidden',!config.parent);
    document.getElementById('academicCaption').textContent=config.label+' records';
    document.getElementById('academicTableHead').innerHTML='<th scope="col">'+config.name+'</th>'+(config.parent?'<th scope="col">Department</th>':'')+'<th scope="col">Status</th><th scope="col" class="ac-actions-cell">Actions</th>';
    renderAcademicRecords();
    if(saveURL){const url=new URL(location.href);url.searchParams.set('section',section);history.replaceState({...history.state},'',url);}
}
function filterAcademicRecords(){academicPage=1;renderAcademicRecords();}
function resetAcademicFilters(){for(const id of ['academicSearch','academicStatus','academicDepartment'])document.getElementById(id).value='';filterAcademicRecords();}
function resizeAcademicPage(){academicPageSize=Number(document.getElementById('academicPageSize').value);academicPage=1;renderAcademicRecords();}
function changeAcademicPage(delta){const total=Math.max(1,Math.ceil(academicFilteredRows().length/academicPageSize));academicPage=Math.max(1,Math.min(total,academicPage+delta));renderAcademicRecords();}
function renderAcademicRecords(){
    const config=academicSections[academicSection],rows=academicFilteredRows(),pages=Math.max(1,Math.ceil(rows.length/academicPageSize));
    academicPage=Math.max(1,Math.min(academicPage,pages));
    const start=(academicPage-1)*academicPageSize,shown=rows.slice(start,start+academicPageSize),body=document.getElementById('academicTableBody');
    body.replaceChildren();
    for(const item of shown){
        const active=Number(item.is_active)===1,row=document.createElement('tr');
        row.innerHTML='<td class="ac-record"><strong>'+academicEscape(item.name)+'</strong>'+(config.description?'<p>'+academicEscape(item.description||'No description added')+'</p>':'')+'</td>'+(config.parent?'<td data-label="Department" class="ac-department">'+academicEscape(item.department_name||'Not assigned')+'</td>':'')+'<td data-label="Status"><span class="ac-status '+(active?'':'is-inactive')+'">'+(active?'Active':'Inactive')+'</span></td><td class="ac-actions-cell"><div class="ac-row-actions"></div></td>';
        const actions=row.querySelector('.ac-row-actions'),edit=document.createElement('button'),toggle=document.createElement('button');
        edit.type='button';edit.className='ac-edit';edit.textContent='Edit';edit.setAttribute('aria-label','Edit '+item.name);edit.onclick=()=>openAcademicEdit(item);
        toggle.type='button';toggle.className='ac-toggle '+(active?'':'is-inactive');toggle.textContent=active?'Deactivate':'Activate';toggle.setAttribute('aria-label',(active?'Deactivate ':'Activate ')+item.name);toggle.onclick=()=>confirmAcademicStatus(item,active?0:1);
        actions.append(edit,toggle);body.appendChild(row);
    }
    if(!shown.length){const row=document.createElement('tr');row.innerHTML='<td colspan="'+(config.parent?4:3)+'" class="ac-empty"><span><i class="fas fa-folder-open" aria-hidden="true"></i></span><strong>No '+config.label.toLowerCase()+' found</strong><p>Try a different search or reset the filters.</p></td>';body.appendChild(row);}
    document.getElementById('academicCount').textContent=rows.length+' '+(rows.length===1?'record':'records');
    document.getElementById('academicPageInfo').textContent=rows.length?'Showing '+(start+1)+'–'+Math.min(start+academicPageSize,rows.length)+' of '+rows.length+' records':'No records to display';
    document.getElementById('academicPageNumber').textContent=academicPage+' / '+pages;
    document.getElementById('academicPrevious').disabled=academicPage===1;
    document.getElementById('academicNext').disabled=academicPage===pages;
}
function configureAcademicDialog(mode,item=null){
    const config=academicSections[academicSection],form=document.getElementById('academicForm-'+mode);form.reset();
    document.getElementById('academicType-'+mode).value=academicSection;
    document.getElementById('academicDialogTitle-'+mode).textContent=(mode==='add'?'Add ':'Edit ')+config.singular;
    document.getElementById('academicNameLabel-'+mode).textContent=config.name+' *';
    document.getElementById('academicName-'+mode).placeholder=config.example;
    document.getElementById('academicParentField-'+mode).classList.toggle('hidden',!config.parent);
    document.getElementById('academicDescriptionField-'+mode).classList.toggle('hidden',!config.description);
    document.getElementById('academicParent-'+mode).required=academicSection==='grade';
    document.getElementById('academicParentRequired-'+mode).classList.toggle('hidden',academicSection!=='grade');
    document.getElementById('academicSave-'+mode).textContent=mode==='add'?'Add '+config.singular:'Save changes';
    document.getElementById('academicDialogHint-'+mode).textContent=(mode==='add'?'Create a new ':'Update this ')+config.singular+'. '+config.examples;
    if(item){document.getElementById('academicId-edit').value=item.id;document.getElementById('academicName-edit').value=item.name||'';document.getElementById('academicParent-edit').value=item.department_id||'';document.getElementById('academicDescription-edit').value=item.description||'';}
    academicRestoreFocus=document.activeElement;academicDialog=document.getElementById('academicDialog-'+mode);academicDialog.classList.remove('hidden');window.PortalDialogs?.open(academicDialog);document.getElementById('academicName-'+mode).focus({preventScroll:true});
}
function openAcademicAdd(){configureAcademicDialog('add');}
function openAcademicEdit(item){configureAcademicDialog('edit',item);}
function closeAcademicDialog(){if(!academicDialog)return;academicDialog.classList.add('hidden');window.PortalDialogs?.close(academicDialog);academicDialog=null;if(academicRestoreFocus?.isConnected)academicRestoreFocus.focus({preventScroll:true});academicRestoreFocus=null;}
function confirmAcademicStatus(item,status){
    const config=academicSections[academicSection],verb=status===1?'Activate':'Deactivate';
    const prompt=window.PortalDialogs?window.PortalDialogs.fire:Swal.fire.bind(Swal);
    prompt({title:verb+' '+config.singular+'?',text:verb+' “'+item.name+'”? '+(status===0?'The record will remain in the system.':''),icon:'question',showCancelButton:true,confirmButtonText:verb,cancelButtonText:'Cancel',confirmButtonColor:'#163269',cancelButtonColor:'#64748b',heightAuto:false,scrollbarPadding:false}).then(result=>{
        if(!result.isConfirmed)return;
        const form=document.createElement('form');form.method='post';form.action='layout.php?page=academic_settings';
        for(const [name,value]of Object.entries({action:'toggle_status',table:config.table,id:item.id,status})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.appendChild(input);}
        document.body.appendChild(form);
        if(window.PortalNavigation)window.PortalNavigation.submit(form).finally(()=>form.remove());else form.submit();
    });
}
function capturePortalPageState(){
    return {kind:'academics',section:academicSection,page:academicPage,pageSize:academicPageSize,
        fields:Object.fromEntries(['academicSearch','academicStatus','academicDepartment'].map(id=>[id,document.getElementById(id).value]))};
}
function restorePortalPageState(state){
    selectAcademicSection(state.section,false);
    for(const id of ['academicSearch','academicStatus','academicDepartment'])document.getElementById(id).value=state.fields[id]||'';
    academicPageSize=state.pageSize;academicPage=state.page;document.getElementById('academicPageSize').value=state.pageSize;
    renderAcademicRecords();
}
function initializeAcademicSettings(){
    document.querySelectorAll('.ac-modal').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)closeAcademicDialog();}));
    document.addEventListener('keydown',event=>{
        if(window.Swal?.isVisible())return;
        if(event.key==='Escape'&&academicDialog){closeAcademicDialog();event.preventDefault();}
        if(event.key==='Tab'&&academicDialog){const items=[...academicDialog.querySelectorAll('button,input:not([type=hidden]),select,textarea')].filter(el=>el.offsetParent!==null&&!el.disabled);if(event.shiftKey&&document.activeElement===items[0]){items.at(-1).focus();event.preventDefault();}else if(!event.shiftKey&&document.activeElement===items.at(-1)){items[0].focus();event.preventDefault();}}
    });
    document.querySelectorAll('[data-ac-section]').forEach(tab=>tab.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;const sections=Object.keys(academicSections);let index=sections.indexOf(academicSection);index=event.key==='Home'?0:event.key==='End'?3:(index+(event.key==='ArrowRight'?1:-1)+4)%4;selectAcademicSection(sections[index]);document.getElementById('academicTab-'+sections[index]).focus();event.preventDefault();}));
    const saved=window.PortalNavigation?.getRestoreState();if(saved?.kind==='academics')return restorePortalPageState(saved);
    const section=new URLSearchParams(location.search).get('section');selectAcademicSection(Object.hasOwn(academicSections,section)?section:'department',false);
}
document.addEventListener('DOMContentLoaded',initializeAcademicSettings);
