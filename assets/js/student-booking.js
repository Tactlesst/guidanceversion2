(() => {
 'use strict';
 const root=document.getElementById('studentBookingWorkspace');if(!root)return;
 const data=JSON.parse(document.getElementById('studentBookingData').textContent),modal=document.getElementById('bookingModal'),form=document.getElementById('bookingForm'),dateInput=document.getElementById('appointmentDate'),timeSelect=document.getElementById('appointmentTime');
 const status=document.getElementById('scbBookingError'),slots=document.getElementById('timeSlots'),note=document.getElementById('dateInfoText'),search=document.getElementById('scbSearch'),category=document.getElementById('scbCategory');
 let selected=data.selected,view='calendar',busy=false;
 const el=(tag,text='',className='')=>{const node=document.createElement(tag);node.textContent=text;if(className)node.className=className;return node;};
 const formatDate=date=>new Date(date+'T12:00:00').toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
 const statusLabel=value=>String(value||'').replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
 const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const fire=options=>window.PortalDialogs?window.PortalDialogs.fire(options):window.Swal.fire(options);
 const isActive=appointment=>['pending','confirmed','in_progress'].includes(appointment.status);
 function times(date){return data.slots.map(slot=>{const booked=Number(data.booked[date]?.[slot.value]||0);return {...slot,booked,available:booked<data.maxPerTime};});}
 function matches(day){const query=search.value.trim().toLocaleLowerCase(),type=category.value;
  const categoryMatch=type==='all'||(type==='available'&&day.available)||(type==='my_appointment'&&day.mine.length)||(type==='counseling'&&(data.availability[day.date]||[]).length)||(type==='holiday'&&day.holiday)||(type==='school_closure'&&day.closure)||day.events.some(event=>event.event_type===type);
  const text=[formatDate(day.date),day.date,day.reason,...day.events.map(event=>event.event_name),...day.mine.map(appointment=>statusLabel(appointment.status))].join(' ').toLocaleLowerCase();return Boolean(categoryMatch&&(!query||text.includes(query)));
 }
 function renderFilters(){let matched=0;const list=document.getElementById('scbList');list.replaceChildren();
  for(const day of Object.values(data.days)){const found=matches(day);root.querySelector(`[data-scb-date="${day.date}"]`).classList.toggle('scb-filtered',!found);if(!found)continue;matched++;
   const button=el('button','','scb-agenda-row');button.type='button';button.dataset.scbSelect=day.date;const stamp=el('span','','scb-agenda-date');stamp.append(el('strong',String(day.day)),el('small',new Date(day.date+'T12:00:00').toLocaleDateString('en-US',{month:'short'})));const copy=el('span','','scb-agenda-copy');copy.append(el('strong',formatDate(day.date)),el('small',day.events.length?day.events.map(event=>event.event_name).join(' · '):day.reason));button.append(stamp,copy,el('span',day.mine.length?'My appointment':(day.available?'Available':day.closed?'Closed':'No slots'),'scb-agenda-tag'));button.addEventListener('click',()=>select(day.date,true));list.append(button);
  }
  const active=Number(Boolean(search.value.trim()))+Number(category.value!=='all');document.getElementById('scbFilterCount').textContent=active?'('+active+')':'';document.getElementById('scbScope').textContent=active?'Showing '+matched+' matching dates':'Showing all dates';document.getElementById('scbNoMatches').hidden=matched>0;
 }
 function renderSelected(){const day=data.days[selected];if(!day)return;
  document.getElementById('scbSelectedHeading').textContent=new Date(selected+'T12:00:00').toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
  root.querySelectorAll('[data-scb-date]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.scbDate===selected)));
  const state=document.getElementById('scbDayStatus');state.replaceChildren(el('p',day.reason,day.available?'scb-day-available':'scb-day-note'));
  const mine=document.getElementById('scbMyAppointments');mine.replaceChildren();
  for(const appointment of day.mine){const card=el('div','','scb-own-appointment');card.append(el('strong','Your appointment'),el('span',statusLabel(appointment.status)+' · '+(data.slots.find(slot=>slot.value===appointment.appointment_time)?.label||appointment.appointment_time)));const button=el('button','View details','scb-text-button');button.type='button';button.addEventListener('click',()=>viewAppointment(appointment,selected));card.append(button);mine.append(card);}
  const events=document.getElementById('scbSelectedEvents');events.replaceChildren();
  if(!day.events.length)events.append(el('p','No school events listed for this date.','scb-empty-small'));
  for(const event of day.events){const card=el('div','','scb-selected-entry');card.append(el('strong',event.event_name||'School event'),el('small',data.labels[event.event_type]||statusLabel(event.event_type)));events.append(card);}
  const capacity=document.getElementById('scbCapacity'),card=el('div','','scb-capacity-card');card.append(el('span','Counseling appointments'),el('strong',String(day.capacity.remaining_slots)+' remaining'),el('small',day.capacity.current_count+' booked / '+day.capacity.max_limit+' daily limit'));
  const meter=el('progress');meter.max=Math.max(1,Number(day.capacity.max_limit));meter.value=Math.min(meter.max,Number(day.capacity.current_count));meter.setAttribute('aria-label','Daily counseling booking capacity');card.append(meter);capacity.replaceChildren(card);
  if(day.available)capacity.append(el('p',day.free+' time '+(day.free===1?'option':'options')+' during regular office hours.','scb-empty-small'));
  const button=root.querySelector('.scb-book-selected');button.disabled=Boolean(data.active||!day.available);button.textContent=data.active?'Active appointment on file':day.available?'Choose a time':'Booking unavailable';
 }
 function select(date,focus=false){if(!data.days[date])return;selected=date;renderSelected();if(focus&&window.innerWidth<1150)document.getElementById('scbSelectedHeading').scrollIntoView({block:'start',behavior:'smooth'});}
 function selectTime(value){timeSelect.value=value;slots.querySelectorAll('button[data-time]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.time===value)));}
 function loadSlots(date,preferred=''){timeSelect.replaceChildren(new Option('Select a time',''));slots.replaceChildren();document.getElementById('timeSlotsContainer').hidden=!date;
  const day=data.days[date];if(!date){note.textContent='Choose an available date from the calendar.';return;}
  if(!day){note.textContent='Use the calendar month arrows to browse availability for that date, then book from that month.';return;}
  if(!day.available||data.active){note.textContent=data.active?'You already have an active appointment.':day.reason+'. Please choose another date.';return;}
  note.textContent=formatDate(date)+' · '+day.capacity.remaining_slots+' daily '+(day.capacity.remaining_slots===1?'place':'places')+' remaining.';
  for(const slot of times(date)){const button=el('button',slot.label,'scb-time-slot');button.type='button';button.dataset.time=slot.value;button.disabled=!slot.available;button.setAttribute('aria-pressed','false');button.append(el('small',!slot.available?'Booked':'Available'));if(slot.available){timeSelect.add(new Option(slot.label,slot.value));button.addEventListener('click',()=>selectTime(slot.value));}slots.append(button);}
  if(preferred&&[...timeSelect.options].some(option=>option.value===preferred))selectTime(preferred);
 }
 function openBooking(){if(data.active)return;const date=data.days[selected]?.available?selected:'';dateInput.value=date;loadSlots(date);status.hidden=true;if(window.PortalDialogs)window.PortalDialogs.open(modal);else modal.classList.remove('hidden');dateInput.focus({preventScroll:true});}
 function closeBooking(){if(window.PortalDialogs)window.PortalDialogs.close(modal);else modal.classList.add('hidden');}
 async function cancelAppointment(appointment){const result=await fire({title:'Cancel this appointment?',text:'You can book again after cancellation. An assigned appointment must be managed through the guidance office.',icon:'warning',showCancelButton:true,confirmButtonText:'Cancel appointment',cancelButtonText:'Keep appointment'});if(!result.isConfirmed)return;
  const cancellation=document.createElement('form');cancellation.method='post';cancellation.action=new URL(data.bookUrl,location.href).href;
  for(const [name,value] of Object.entries({_booking_csrf:data.csrf,cancel_appointment:'1',appointment_id:String(appointment.id)})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;cancellation.append(input);}document.body.append(cancellation);
  try{if(window.PortalNavigation&&data.inLayout)await window.PortalNavigation.submit(cancellation);else cancellation.submit();}finally{cancellation.remove();}
 }
 async function viewAppointment(appointment,date){const canCancel=isActive(appointment)&&!appointment.advocate_id;const result=await fire({title:'Your counseling appointment',html:'<dl class="scb-appointment-details"><div><dt>Date</dt><dd>'+escape(formatDate(date))+'</dd></div><div><dt>Time</dt><dd>'+escape(data.slots.find(slot=>slot.value===appointment.appointment_time)?.label||appointment.appointment_time)+'</dd></div><div><dt>Status</dt><dd>'+escape(statusLabel(appointment.status))+'</dd></div><div><dt>Concern</dt><dd>'+escape(statusLabel(appointment.concern_type))+'</dd></div></dl>',showCancelButton:true,cancelButtonText:'Close',confirmButtonText:'My appointments',showDenyButton:canCancel,denyButtonText:'Cancel appointment',customClass:{denyButton:'scb-alert-danger'}});
  if(result.isConfirmed){if(window.PortalNavigation&&data.inLayout)window.PortalNavigation.navigate(new URL(data.appointmentsUrl,location.href).href);else location.assign(data.appointmentsUrl);}else if(result.isDenied&&canCancel)await cancelAppointment(appointment);
 }
 root.querySelectorAll('[data-scb-date]').forEach(button=>button.addEventListener('click',()=>select(button.dataset.scbDate)));
 root.querySelectorAll('[data-scb-book]').forEach(button=>button.addEventListener('click',openBooking));
 root.querySelectorAll('[data-scb-view]').forEach(button=>button.addEventListener('click',()=>{view=button.dataset.scbView;document.getElementById('scbCalendar').hidden=view!=='calendar';document.getElementById('scbList').hidden=view!=='list';root.querySelectorAll('[data-scb-view]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));}));
 search.addEventListener('input',renderFilters);category.addEventListener('change',renderFilters);document.getElementById('scbReset').addEventListener('click',()=>{search.value='';category.value='all';renderFilters();});
 dateInput.addEventListener('change',()=>{loadSlots(dateInput.value);if(data.days[dateInput.value])select(dateInput.value);});timeSelect.addEventListener('change',()=>selectTime(timeSelect.value));
 if(!window.PortalDialogs){modal.querySelectorAll('[data-dialog-close]').forEach(button=>button.addEventListener('click',closeBooking));modal.addEventListener('click',event=>{if(event.target===modal)closeBooking();});document.addEventListener('keydown',event=>{if(event.key==='Escape')closeBooking();});}
 form.addEventListener('submit',async event=>{event.preventDefault();if(busy)return;const day=data.days[dateInput.value],slot=times(dateInput.value).find(item=>item.value===timeSelect.value);if(data.active||!day?.available||!slot?.available){status.textContent=data.active?'You already have an active appointment.':'Choose an available date and time.';status.hidden=false;return;}if(!form.reportValidity())return;busy=true;
  try{const result=await fire({title:'Confirm your appointment',html:'<dl class="scb-appointment-details"><div><dt>Date</dt><dd>'+escape(formatDate(dateInput.value))+'</dd></div><div><dt>Time</dt><dd>'+escape(slot.label)+'</dd></div><div><dt>Concern</dt><dd>'+escape(statusLabel(form.elements.concern_type.value))+'</dd></div><div><dt>Urgency</dt><dd>'+escape(statusLabel(form.elements.urgency_level.value))+'</dd></div></dl>',icon:'question',showCancelButton:true,confirmButtonText:'Book appointment',cancelButtonText:'Keep editing'});if(!result.isConfirmed)return;if(window.PortalNavigation&&data.inLayout)await window.PortalNavigation.submit(form);else form.submit();}finally{busy=false;}
 });
 renderFilters();renderSelected();
 if(data.error&&!data.active){if(window.PortalDialogs)window.PortalDialogs.open(modal);else modal.classList.remove('hidden');loadSlots(dateInput.value,data.preferredTime);status.textContent=data.error;status.hidden=false;}
})();
