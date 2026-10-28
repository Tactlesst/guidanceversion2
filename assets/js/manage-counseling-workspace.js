const counselingStatuses=['pending','confirmed','in_progress','completed','cancelled','missed'];
const counselingStatusTitles={pending:'Unassigned appointments',confirmed:'Confirmed appointments',in_progress:'Sessions in progress',completed:'Completed sessions',cancelled:'Cancelled appointments',missed:'Missed appointments'};
const counselingStatusHints={pending:'Awaiting counselor assignment.',confirmed:'Assigned appointments ready for counseling.',in_progress:'Active counseling sessions.',completed:'Finished counseling sessions.',cancelled:'Appointments that were cancelled.',missed:'Appointments marked as missed.'};
let counselingActiveStatus='pending',counselingListPage=1;
const counselingPageSize=10;
function selectCounselingStatus(status){
    if(!counselingStatuses.includes(status))return;
    counselingActiveStatus=status;counselingListPage=1;
    for(const key of counselingStatuses){const active=key===status;document.getElementById('panel-'+key).classList.toggle('hidden',!active);const card=document.getElementById('tab-'+key);card.setAttribute('aria-selected',String(active));}
    document.getElementById('mcActiveTitle').textContent=counselingStatusTitles[status];document.getElementById('mcSelection').textContent=counselingStatusHints[status];refreshCounselingList();
}
function refreshCounselingList(){
    const input=document.getElementById('mcSearch'),search=input.value.trim().toLocaleLowerCase();
    const panel=document.getElementById('panel-'+counselingActiveStatus),rows=[...panel.querySelectorAll('.mc-row')],matching=rows.filter(row=>!search||row.dataset.search.includes(search));
    const totalPages=Math.max(1,Math.ceil(matching.length/counselingPageSize));counselingListPage=Math.max(1,Math.min(counselingListPage,totalPages));
    const start=(counselingListPage-1)*counselingPageSize,visible=new Set(matching.slice(start,start+counselingPageSize));
    for(const row of rows)row.hidden=!visible.has(row);
    for(const row of panel.querySelectorAll('.mc-no-records'))row.hidden=matching.length>0||rows.length>0;
    let noMatch=panel.querySelector('.mc-no-match');if(!noMatch){noMatch=document.createElement('p');noMatch.className='mc-no-match';noMatch.textContent='No appointments match this search in the selected status.';panel.appendChild(noMatch);}noMatch.hidden=!rows.length||matching.length>0;
    document.getElementById('mcShowing').textContent=matching.length+' '+(matching.length===1?'appointment':'appointments');
    document.getElementById('mcPageInfo').textContent=matching.length?'Showing '+(start+1)+'–'+Math.min(start+counselingPageSize,matching.length)+' of '+matching.length+(search?' matching':''):'0 matching appointments';
    document.getElementById('mcPagePosition').textContent=counselingListPage+' / '+totalPages;document.getElementById('mcPrevious').disabled=counselingListPage===1;document.getElementById('mcNext').disabled=counselingListPage===totalPages;
    document.getElementById('mcFilterCount').textContent=search?' (1)':'';document.getElementById('mcClearSearch').hidden=!search;
}
function initializeCounselingWorkspace(){
    const root=document.querySelector('.mc-workspace');if(!root)return;
    for(const action of root.querySelectorAll('.mc-actions button[title]')){if(!action.querySelector('.mc-action-label')){const label=document.createElement('span');label.className='mc-action-label';label.textContent=action.title;action.appendChild(label);}action.type='button';action.setAttribute('aria-label',action.title);}
    root.addEventListener('click',event=>{const button=event.target.closest('[data-purpose]');if(!button)return;document.getElementById('mcPurposeStudent').textContent=button.dataset.student;document.getElementById('mcPurposeText').textContent=button.dataset.purpose;window.PortalDialogs.open(document.getElementById('counselingPurposeModal'));document.querySelector('#counselingPurposeModal .pd-close').focus({preventScroll:true});});
    root.querySelector('.mc-status-cards').addEventListener('keydown',event=>{const card=event.target.closest('[data-counseling-status]');if(!card)return;const index=counselingStatuses.indexOf(card.dataset.counselingStatus);let next;if(event.key==='ArrowRight')next=(index+1)%6;else if(event.key==='ArrowLeft')next=(index+5)%6;else if(event.key==='Home')next=0;else if(event.key==='End')next=5;else return;event.preventDefault();const target=document.getElementById('tab-'+counselingStatuses[next]);target.focus();selectCounselingStatus(counselingStatuses[next]);});
    document.getElementById('mcSearch').addEventListener('input',()=>{counselingListPage=1;refreshCounselingList();});document.getElementById('mcClearSearch').addEventListener('click',()=>{document.getElementById('mcSearch').value='';counselingListPage=1;refreshCounselingList();});
    document.getElementById('mcPrevious').addEventListener('click',()=>{counselingListPage--;refreshCounselingList();});document.getElementById('mcNext').addEventListener('click',()=>{counselingListPage++;refreshCounselingList();});selectCounselingStatus('pending');
}
initializeCounselingWorkspace();
