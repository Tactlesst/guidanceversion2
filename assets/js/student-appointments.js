(() => {
 'use strict';
 const root=document.getElementById('studentAppointments');if(!root)return;
 const data=JSON.parse(document.getElementById('studentAppointmentsData').textContent),items=data.appointments;
 const search=document.getElementById('saSearch'),status=document.getElementById('saStatus'),from=document.getElementById('saFrom'),to=document.getElementById('saTo'),dialog=document.getElementById('saDetailsModal');
 const rows=new Map([...root.querySelectorAll('[data-sa-row]')].map(row=>[row.dataset.saRow,row]));
 let page=1,current=null,busy=false;const pageSize=10;
 const text=(id,value)=>{document.getElementById(id).textContent=value;};
 function matches(item){const type=status.value,query=search.value.trim().toLocaleLowerCase();return (type==='all'||type===item.status||(type==='upcoming'&&item.upcoming)||(type==='closed'&&['cancelled','missed'].includes(item.status)))&&(!from.value||item.date>=from.value)&&(!to.value||item.date<=to.value)&&(!query||[item.concern,item.counselor,item.date,item.dateLabel,item.statusLabel].join(' ').toLocaleLowerCase().includes(query));}
 function render(){const invalid=Boolean(from.value&&to.value&&from.value>to.value);document.getElementById('saDateError').hidden=!invalid;const filtered=invalid?[]:items.filter(matches),pages=Math.max(1,Math.ceil(filtered.length/pageSize));page=Math.min(page,pages);const visible=new Set(filtered.slice((page-1)*pageSize,page*pageSize).map(item=>String(item.id)));
  for(const [id,row]of rows)row.hidden=!visible.has(id);
  root.querySelectorAll('[data-sa-filter]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.saFilter===status.value)));
  const active=Number(Boolean(search.value.trim()))+Number(status.value!=='all')+Number(Boolean(from.value))+Number(Boolean(to.value));text('saFilterCount',active?'('+active+')':'');
  const scope=status.options[status.selectedIndex].textContent; text('saScope',scope+' · '+filtered.length+' '+(filtered.length===1?'appointment':'appointments')+(from.value||to.value?' · '+(from.value||'Any start date')+' to '+(to.value||'Any end date'):''));
  document.getElementById('saEmpty').hidden=filtered.length>0;document.getElementById('saPagination').hidden=filtered.length===0;
  if(items.length){text('saEmptyTitle','No matching appointments');text('saEmptyText',invalid?'Check the date range above.':'Try a different status, date range or search.');}
  document.getElementById('saEmptyClear').hidden=!items.length||!active;
  text('saPageInfo','Showing '+((page-1)*pageSize+1)+'–'+Math.min(page*pageSize,filtered.length)+' of '+filtered.length+' appointments');text('saPageNumber','Page '+page+' of '+pages);
  document.getElementById('saPrev').disabled=page===1;document.getElementById('saNext').disabled=page===pages;
 }
 function reset(){search.value='';status.value='all';from.value='';to.value='';page=1;render();}
 function openDetails(id){const item=items.find(item=>String(item.id)===id);if(!item)return;current=item;
  text('saDetailsTitle',item.concern+' counseling');text('saDetailsSubtitle',item.followUp?'Follow-up appointment':'Appointment details');text('saDetailsBadge',item.statusLabel);document.getElementById('saDetailsBadge').dataset.saStatus=item.status;
  text('saDetailsDate',item.dateLabel);text('saDetailsTime',item.time);text('saDetailsCounselor',item.counselor);text('saDetailsUrgency',item.urgency);text('saDetailsDescription',item.description||'No concern description provided.');text('saDetailsMessage',item.message);
  document.getElementById('saDetailsFollowUp').hidden=!item.followUp;document.getElementById('saDetailsRescheduled').hidden=!item.original;text('saDetailsOriginal',item.original);text('saDetailsSubmitted',item.submitted);text('saDetailsConfirmed',item.confirmed);document.getElementById('saConfirmedGroup').hidden=!item.confirmed;
  document.getElementById('saCancel').hidden=!item.canCancel;text('saCancellationNote',item.canCancel?'You can cancel this appointment before a counselor is assigned.':['pending','confirmed','in_progress'].includes(item.status)?'A counselor has been assigned. Contact the guidance office to request a change.':'This appointment is part of your counseling history.');document.getElementById('saActionError').hidden=true;
  const calendar=new URL(data.bookUrl,location.href);if(/^\d{4}-\d{2}-\d{2}$/.test(item.date)){calendar.searchParams.set('month',String(Number(item.date.slice(5,7))));calendar.searchParams.set('year',item.date.slice(0,4));}document.getElementById('saCalendar').href=calendar.href;
  window.PortalDialogs.open(dialog);dialog.querySelector('.pd-close').focus({preventScroll:true});
 }
 root.querySelectorAll('[data-sa-filter]').forEach(button=>button.addEventListener('click',()=>{status.value=button.dataset.saFilter;page=1;render();}));
 root.querySelectorAll('[data-sa-details]').forEach(button=>button.addEventListener('click',()=>openDetails(button.dataset.saDetails)));
 for(const input of[search,status,from,to])input.addEventListener(input===search?'input':'change',()=>{page=1;render();});
 document.getElementById('saClear').addEventListener('click',reset);document.getElementById('saEmptyClear').addEventListener('click',reset);
 for(const[id,change]of[['saPrev',-1],['saNext',1]])document.getElementById(id).addEventListener('click',()=>{page+=change;render();document.getElementById('saList').scrollIntoView({block:'start',behavior:'auto'});});
 document.getElementById('saCancel').addEventListener('click',async()=>{if(busy||!current?.canCancel)return;const item=current;busy=true;const button=document.getElementById('saCancel');button.disabled=true;
  try{const result=await window.PortalDialogs.fire({title:'Cancel this appointment?',text:'Your '+item.concern.toLowerCase()+' appointment on '+item.dateLabel+' at '+item.time+' will be cancelled.',icon:'warning',showCancelButton:true,confirmButtonText:'Cancel appointment',cancelButtonText:'Keep appointment'});if(!result.isConfirmed)return;
   const form=document.createElement('form');form.method='post';form.action=new URL(data.bookUrl,location.href).href;for(const[name,value]of Object.entries({_booking_csrf:data.csrf,cancel_appointment:'1',appointment_id:String(item.id)})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);}document.body.append(form);
   try{if(data.inLayout&&window.PortalNavigation)await window.PortalNavigation.submit(form);else form.submit();}finally{form.remove();}
  }catch(error){text('saActionError','Could not submit the cancellation. Please try again.');document.getElementById('saActionError').hidden=false;}finally{busy=false;button.disabled=false;}
 });
 render();
})();
