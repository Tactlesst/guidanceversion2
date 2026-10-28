const BASE='layout.php?page=counseling_history';
let currentPage=1,itemsPerPage=10,searchTimer,currentStatusTab='',historyRequestId=0,historyController=null,detailsController=null;
const historyStatusLabels={'':'All appointments',pending:'Pending',confirmed:'Confirmed',in_progress:'In progress',completed:'Completed',cancelled:'Cancelled',missed:'No show',no_show:'No show',rescheduled:'Rescheduled',follow_up_scheduled:'Follow-up scheduled'};
function historyText(value){return value===null||value===undefined?'':String(value);}
function historyNode(tag,className,text){const node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=historyText(text);return node;}
function historyStatus(value){const badge=historyNode('span','ch-status',historyStatusLabels[value]||historyText(value).replaceAll('_',' ')||'Not set');badge.dataset.status=value||'';return badge;}
function historyDate(value){if(!value)return 'Not set';const date=new Date(value.slice(0,10)+'T12:00:00');return Number.isNaN(date.getTime())?'Not set':date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});}
function historyTime(value){if(!value)return 'Time not set';const date=new Date('2000-01-01T'+value);return Number.isNaN(date.getTime())?'Time not set':date.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});}
function purposePreview(value,maximum=110){const full=historyText(value).replace(/\s+/g,' ').trim();if(full.length<=maximum)return {text:full,truncated:false};let preview=full.slice(0,maximum);const space=preview.lastIndexOf(' ');if(space>=maximum*.6)preview=preview.slice(0,space);return {text:preview+'…',truncated:true};}
function syncHistoryFilters(){
    for(const card of document.querySelectorAll('[data-history-status]'))card.setAttribute('aria-pressed',String(card.dataset.historyStatus===currentStatusTab));
    for(const tab of document.querySelectorAll('#statusTabs button')){const selected=tab.id==='tab-'+currentStatusTab;tab.classList.toggle('bg-primary',selected);tab.classList.toggle('text-white',selected);tab.classList.toggle('bg-gray-100',!selected);tab.classList.toggle('text-gray-700',!selected);tab.setAttribute('aria-pressed',String(selected));}
    const labels=[historyStatusLabels[currentStatusTab]||'All appointments'];let count=Number(Boolean(currentStatusTab));
    for(const [id,label] of [['searchInput','Search'],['gradeLevelFilter','Grade / year'],['dateFrom','From'],['dateTo','Through']]){const value=document.getElementById(id).value.trim();if(value){labels.push(label+': '+value);count++;}}
    if(document.getElementById('sortFilter').value!=='latest'){labels.push('Oldest first');count++;}
    document.getElementById('historyFilterSelection').textContent='Showing: '+labels.join(' · ');document.getElementById('historyFilterCount').textContent=count?' ('+count+')':'';document.getElementById('resetHistoryFilters').hidden=!count;
}
function switchStatusTab(status){if(!Object.hasOwn(historyStatusLabels,status))return;clearTimeout(searchTimer);currentStatusTab=status;currentPage=1;fetchCounselingHistory();}
function historyFiltersChanged(){clearTimeout(searchTimer);currentPage=1;fetchCounselingHistory();}
function resetHistoryFilters(){for(const id of ['searchInput','dateFrom','dateTo','gradeLevelFilter'])document.getElementById(id).value='';document.getElementById('sortFilter').value='latest';switchStatusTab('');}
function debounceSearch(){clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentPage=1;fetchCounselingHistory();},300);}
function historyEmpty(message,error=false){const body=document.getElementById('counselingBody');body.replaceChildren();const row=historyNode('tr'),cell=historyNode('td','ch-empty'),icon=historyNode('i','fas '+(error?'fa-circle-exclamation':'fa-comments'));icon.setAttribute('aria-hidden','true');cell.colSpan=6;cell.append(icon,historyNode('strong','',error?'Could not load sessions':'No sessions found'),historyNode('p','',message));if(error){const retry=historyNode('button','ch-retry','Try again');retry.type='button';retry.addEventListener('click',fetchCounselingHistory);cell.append(retry);}row.append(cell);body.append(row);}
function renderHistoryRows(rows){
    const body=document.getElementById('counselingBody');body.replaceChildren();
    if(!rows.length){historyEmpty('Try another search or clear the filters.');return;}
    for(const session of rows){
        const row=historyNode('tr','ch-row'),name=[session.last_name,session.first_name].filter(Boolean).join(', ')||'Student';row.dataset.sessionId=session.id;
        const student=historyNode('td','ch-student'),identity=historyNode('div','ch-identity'),avatar=historyNode('span','ch-avatar',[session.first_name,session.last_name].filter(Boolean).map(part=>part[0]).join('').toUpperCase()),copy=historyNode('div');avatar.setAttribute('aria-hidden','true');copy.append(historyNode('strong','',name));const meta=historyNode('div','ch-student-meta');meta.append(historyNode('span','',session.student_id||'No student ID'),historyNode('span','',session.grade_level||'Grade not set'));copy.append(meta);identity.append(avatar,copy);student.append(identity);
        const date=historyNode('td','ch-date');date.setAttribute('data-label','Session date');date.append(historyNode('strong','',historyDate(session.appointment_date)),historyNode('span','',historyTime(session.appointment_time)));
        const purpose=historyNode('td','ch-purpose');purpose.setAttribute('data-label','Purpose');if(session.concern_type)purpose.append(historyNode('span','ch-concern-type',historyText(session.concern_type).replaceAll('_',' ')));const preview=purposePreview(session.concern_description);purpose.append(historyNode('p','ch-purpose-preview',preview.text||'No description provided.'));
        if(preview.truncated){const read=historyNode('button','ch-read-purpose','Read purpose');read.type='button';read.setAttribute('aria-label','Read full purpose for '+name);read.addEventListener('click',()=>viewSession(session.id,true));purpose.append(read);}
        const counselor=historyNode('td','ch-counselor',session.counselor_name||'Not assigned');counselor.setAttribute('data-label','Counselor');if(!session.counselor_name)counselor.classList.add('ch-muted');
        const status=historyNode('td','ch-status-cell');status.append(historyStatus(session.status));
        const actions=historyNode('td','ch-actions'),view=historyNode('button','ch-view','View session');view.type='button';view.setAttribute('aria-label','View session for '+name);view.addEventListener('click',()=>viewSession(session.id));actions.append(view);
        row.append(student,date,purpose,counselor,status,actions);body.append(row);
    }
}
async function fetchCounselingHistory(){
    syncHistoryFilters();const requestId=++historyRequestId;historyController?.abort();historyController=new AbortController();
    const params=new URLSearchParams({action:'fetch',p:currentPage,per:itemsPerPage,q:document.getElementById('searchInput').value,status:currentStatusTab,grade_level:document.getElementById('gradeLevelFilter').value,date_from:document.getElementById('dateFrom').value,date_to:document.getElementById('dateTo').value,sort:document.getElementById('sortFilter').value});
    const body=document.getElementById('counselingBody');body.setAttribute('aria-busy','true');
    try{const response=await fetch(BASE+'&'+params,{signal:historyController.signal});if(!response.ok)throw new Error('Request failed');const data=await response.json();if(requestId!==historyRequestId)return;if(data.error)throw new Error('Request failed');renderHistoryRows(data.rows||[]);currentPage=data.page;renderPagination(data.total,data.per_page,data.page);}
    catch(error){if(requestId!==historyRequestId||error.name==='AbortError')return;historyEmpty('Please try again. Your records have not changed.',true);renderPagination(0,itemsPerPage,1);document.getElementById('recordsInfo').textContent='Sessions could not be loaded';}
    finally{if(requestId===historyRequestId)body.removeAttribute('aria-busy');}
}
function closeModalDirect(){detailsController?.abort();window.PortalDialogs.close(document.getElementById('appointmentModal'));return false;}
function closeModal(){closeModalDirect();}
async function viewSession(id,focusPurpose=false){
    detailsController?.abort();const controller=new AbortController();detailsController=controller;
    const modal=document.getElementById('appointmentModal'),content=document.getElementById('modalContent');content.replaceChildren(historyNode('p','ch-detail-loading','Loading session details…'));window.PortalDialogs.open(modal);document.getElementById('closeModalBtn').focus({preventScroll:true});
    try{const response=await fetch(BASE+'&action=get_details&id='+encodeURIComponent(id),{signal:controller.signal});if(!response.ok)throw new Error('Request failed');const data=await response.json();if(controller!==detailsController||!modal.isConnected||modal.classList.contains('hidden'))return;if(data.error||!data.appointment)throw new Error('Record unavailable');renderSessionDetails(data.appointment);if(focusPurpose){const purpose=document.getElementById('chFullPurpose');purpose.scrollIntoView({block:'start'});purpose.focus({preventScroll:true});}}
    catch(error){if(error.name==='AbortError'||controller!==detailsController)return;content.replaceChildren(historyNode('p','ch-detail-loading','This session could not be loaded. Close this window and try again.'));}
}
function renderSessionDetails(session){
    const content=document.getElementById('modalContent');content.replaceChildren();
    const heading=historyNode('div','ch-detail-student'),copy=historyNode('div');copy.append(historyNode('strong','',[session.first_name,session.last_name].filter(Boolean).join(' ')||'Student'),historyNode('p','',[session.student_id||'No student ID',session.grade_level||'Grade not set'].join(' · ')));heading.append(copy,historyStatus(session.status));content.append(heading);
    const grid=historyNode('dl','ch-detail-grid');for(const [label,value] of [['Session date',historyDate(session.appointment_date)],['Time',historyTime(session.appointment_time)],['Counselor',session.counselor_name||'Not assigned'],['Email',session.email||'Not provided']]){const field=historyNode('div');field.append(historyNode('dt','',label),historyNode('dd','',value));grid.append(field);}content.append(grid);
    const purpose=historyNode('section','ch-full-purpose');purpose.id='chFullPurpose';purpose.tabIndex=-1;purpose.setAttribute('aria-labelledby','chPurposeTitle');purpose.append(historyNode('h3','','Full purpose'));purpose.firstElementChild.id='chPurposeTitle';if(session.concern_type)purpose.append(historyNode('span','ch-concern-type',historyText(session.concern_type).replaceAll('_',' ')));purpose.append(historyNode('p','',session.concern_description||'No description provided.'));content.append(purpose);
    const timestamps=historyNode('details','ch-detail-meta'),summary=historyNode('summary','','Record information');timestamps.append(summary);for(const [label,value] of [['Booked',session.created_at],['Last updated',session.updated_at]]){const p=historyNode('p');p.append(historyNode('span','',label),historyNode('strong','',value?historyDate(value)+' · '+historyTime(value.slice(11)):'Not available'));timestamps.append(p);}content.append(timestamps);
}
function renderPagination(total,perPage,page){
    const totalPages=Math.max(1,Math.ceil(total/perPage)),start=total?(page-1)*perPage+1:0,end=Math.min(page*perPage,total);document.getElementById('recordsInfo').textContent=total?'Showing '+start+'–'+end+' of '+total+' sessions':'No matching sessions';document.getElementById('pageInfo').textContent=total?'Page '+page+' of '+totalPages:'0 sessions';document.getElementById('prevBtn').disabled=page<=1;document.getElementById('nextBtn').disabled=page>=totalPages;
    const nums=document.getElementById('pageNums');nums.replaceChildren();let first=Math.max(1,page-2),last=Math.min(totalPages,first+4);if(last-first<4)first=Math.max(1,last-4);for(let p=first;p<=last;p++){const button=historyNode('button','',p);button.type='button';button.setAttribute('aria-label','Session page '+p);if(p===page)button.setAttribute('aria-current','page');button.addEventListener('click',()=>{currentPage=p;fetchCounselingHistory();});nums.append(button);}
}
function changePage(delta){currentPage=Math.max(1,currentPage+delta);fetchCounselingHistory();}
fetchCounselingHistory();
