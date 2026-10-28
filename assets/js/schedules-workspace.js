(() => {
    const root=document.getElementById('scheduleWorkspace');if(!root)return;
    const data=JSON.parse(document.getElementById('schWorkspaceData').textContent),events=new Map();
    for(const rows of Object.values(data.events))for(const row of rows)events.set(String(row.id),row);
    let selected=root.dataset.selectedDate,category='all',search='';
    const searchInput=root.querySelector('#schSearch'),categoryInput=root.querySelector('#schCategory');
    const localDate=value=>new Date(value.slice(0,10)+'T12:00:00');
    const readable=value=>localDate(value).toLocaleDateString('en-US',{month:'short',day:'numeric'});
    const period=event=>readable(event.start_datetime)+(event.start_datetime.slice(0,10)!==event.end_datetime.slice(0,10)?' – '+readable(event.end_datetime):'')+' · '+event.start_datetime.slice(11,16)+' – '+event.end_datetime.slice(11,16);
    const matches=(type,title)=> (category==='all'||category===type)&&(!search||(title+' '+(data.labels[type]||type)).toLocaleLowerCase().includes(search));
    function setView(view){root.dataset.view=view;for(const button of root.querySelectorAll('[data-schedule-view]'))button.setAttribute('aria-pressed',String(button.dataset.scheduleView===view));}
    function bookingCards(holder,date){
        holder.replaceChildren();
        const day=data.bookings[date];
        if(!day){const error=document.createElement('p');error.className='sch-booking-notice';error.textContent='Booking totals could not be loaded for this date. Please reload and try again.';holder.appendChild(error);return;}
        for(const service of ['counseling','entrance_exam']){
            const stats=day[service],card=document.createElement('section'),header=document.createElement('header'),title=document.createElement('h4'),tag=document.createElement('small'),count=document.createElement('div'),number=document.createElement('strong'),label=document.createElement('span'),limit=document.createElement('p'),remaining=document.createElement('p');
            card.className='sch-booking-card';card.dataset.service=service;
            title.textContent=service==='counseling'?'Counseling':'Entrance exam';tag.textContent=stats.custom?'Custom limit':service==='counseling'?'Default limit':'Not configured';header.append(title,tag);
            count.className='sch-booking-count';number.textContent=stats.booked;label.textContent='booked';count.append(number,label);limit.textContent='Daily limit: '+(stats.limit===null?'Not set':stats.limit);
            card.append(header,count,limit);
            if(stats.limit!==null){const meter=document.createElement('progress');meter.max=Math.max(1,stats.limit);meter.value=Math.min(stats.used,stats.limit);meter.setAttribute('aria-label',title.textContent+' capacity used');card.appendChild(meter);remaining.textContent=stats.used+' counted toward limit · '+Math.max(0,stats.limit-stats.used)+' capacity remaining';remaining.dataset.full=String(stats.used>=stats.limit);}
            else remaining.textContent='Set a limit and an entrance exam schedule to enable booking.';
            card.appendChild(remaining);holder.appendChild(card);
        }
    }
    const bookingDate=document.getElementById('schBookingDate'),bookingService=document.getElementById('schLimitService'),bookingValue=document.getElementById('schLimitValue');
    function syncBookingModal(){
        const date=bookingDate.value,day=data.bookings[date],service=bookingService.value;
        bookingCards(document.getElementById('schModalBookings'),date);
        const editable=!!day&&date>data.today,stats=day&&day[service];
        document.getElementById('schLimitDate').value=date;document.getElementById('schRemoveLimitDate').value=date;document.getElementById('schRemoveLimitService').value=service;
        bookingValue.max=service==='entrance_exam'?'15':'20';bookingValue.value=stats&&stats.limit!==null?stats.limit:service==='entrance_exam'?5:4;
        bookingValue.disabled=!editable;document.getElementById('schLimitSave').disabled=!editable;document.getElementById('schLimitReadOnly').hidden=editable;
        document.getElementById('schLimitHelp').textContent=(service==='entrance_exam'?'Entrance exams allow 1–15':'Counseling allows 1–20')+' appointments.';
        document.getElementById('schBookingRemoveForm').hidden=!editable||!stats?.custom;
        document.getElementById('schRemoveLimitHint').textContent=service==='counseling'?'Removing the custom limit restores the default of 4.':'Removing this limit stops new exam bookings for this date.';
        const closure=document.getElementById('schBookingClosure'),school=data.closures[date];closure.hidden=!data.holidays[date]&&!school;closure.textContent=school?'School closed: '+school.reason+'. New bookings are blocked.':data.holidays[date]?'Holiday: '+data.holidays[date].name+'. Bookings are closed.':'';
    }
    window.openScheduleBookings=function(date){bookingDate.value=date||selected;syncBookingModal();window.PortalDialogs.open(document.getElementById('dailyLimitModal'));};
    bookingDate.addEventListener('change',()=>{syncBookingModal();if(data.bookings[bookingDate.value])chooseDate(bookingDate.value);});
    bookingService.addEventListener('change',syncBookingModal);
    document.getElementById('schMarkSelectedClosed').addEventListener('click',()=>openSchoolClosure(selected));
    document.getElementById('schSelectedBookingButton').addEventListener('click',()=>window.openScheduleBookings(selected));

    function chooseDate(date){
        selected=date;root.dataset.selectedDate=date;
        for(const day of root.querySelectorAll('[data-calendar-date]')){const active=day.dataset.calendarDate===date;day.classList.toggle('sch-day-selected',active);day.querySelector('[data-select-date]').setAttribute('aria-pressed',String(active));}
        root.querySelector('#schSelectedHeading').textContent=localDate(date).toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
        bookingCards(root.querySelector('#schSelectedBookings'),date);
        const holder=root.querySelector('#schSelectedEntries');holder.replaceChildren();
        function item(type,title,detail,action){if(!matches(type,title))return;const button=document.createElement(action?'button':'div');if(action)button.type='button';button.className='sch-selected-entry';button.dataset.entryType=type;const heading=document.createElement('strong'),text=document.createElement('small');heading.textContent=title;text.textContent=detail;button.append(heading,text);if(action)button.addEventListener('click',action);holder.appendChild(button);}
        const school=data.closures[date];if(school)item('school_closure','School closed',school.reason,()=>viewSchoolClosure(school));
        const holiday=data.holidays[date];if(holiday)item('holiday',holiday.name,'Holiday · bookings closed',()=>viewHoliday({...holiday,date}));
        if(Object.hasOwn(data.limits,date))item('daily_limit','Counseling daily limit',data.limits[date]+' appointments',()=>window.openScheduleBookings(date));
        for(const event of data.events[date]||[])item(event.event_type,event.title,period(event),()=>viewSchedule(event));
        if(!holder.children.length){const empty=document.createElement('p');empty.className='sch-empty';empty.textContent=search||category!=='all'?'No entries match these filters on this date.':'No events scheduled for this date.';holder.appendChild(empty);}
        const closed=new Date(date+'T12:00:00').getDay()===0||Boolean(data.holidays[date])||Boolean(data.closures[date]);
        const note=root.querySelector('#schClosedDayNote');note.hidden=!closed;note.textContent=closed?(data.closures[date]?'School closed: '+data.closures[date].reason+'. New events and bookings are blocked.':data.holidays[date]?'Holiday: '+data.holidays[date].name+'. No events can be added.':'Sunday — closed for events.'):'';
        root.querySelector('#schMarkSelectedClosed').hidden=date<=data.today||Boolean(data.closures[date]);
        const add=root.querySelector('#schAddSelected');add.hidden=date<=data.today||closed;add.onclick=()=>openEventTypeSelection(date);
    }
    function filter(){
        category=categoryInput.value;search=searchInput.value.trim().toLocaleLowerCase();
        for(const entry of root.querySelectorAll('#schCalendar [data-entry-type]')){let title=entry.dataset.entryTitle||entry.textContent;const event=entry.dataset.scheduleId&&events.get(entry.dataset.scheduleId);if(event)title=event.title;entry.hidden=!matches(entry.dataset.entryType,title);}
        let count=0;for(const entry of root.querySelectorAll('.sch-agenda-row')){entry.hidden=!matches(entry.dataset.entryType,entry.dataset.entryTitle);if(!entry.hidden)count++;}
        root.querySelector('#schAgendaEmpty').hidden=count>0;
        const applied=Number(category!=='all')+Number(Boolean(search));root.querySelector('#schFilterCount').textContent=applied?'('+applied+')':'';
        root.querySelector('#schFilterSelection').textContent='Showing: '+(category==='all'?'All categories':categoryInput.selectedOptions[0].textContent)+(search?' · Search: '+searchInput.value.trim():'')+' · '+data.month;
        root.querySelector('#schResetFilters').hidden=!applied;chooseDate(selected);
    }
    root.addEventListener('click',event=>{
        const view=event.target.closest('[data-schedule-view]');if(view){setView(view.dataset.scheduleView);return;}
        const closure=event.target.closest('[data-closure-id]');if(closure){const entry=data.closuresById[closure.dataset.closureId];if(entry)viewSchoolClosure(entry);return;}
        const booking=event.target.closest('[data-booking-date]');if(booking){chooseDate(booking.dataset.bookingDate);window.openScheduleBookings(booking.dataset.bookingDate);return;}
        const target=event.target.closest('[data-schedule-id],[data-holiday-date],[data-select-date]')||event.target.closest('[data-calendar-date]');if(!target||!root.contains(target))return;
        if(target.dataset.scheduleId){const entry=events.get(target.dataset.scheduleId);if(entry)viewSchedule(entry);}
        else if(target.dataset.holidayDate){const date=target.dataset.holidayDate;viewHoliday({...data.holidays[date],date});}
        else {
            const date=target.dataset.selectDate||target.dataset.calendarDate;chooseDate(date);

        }
    });
    searchInput.addEventListener('input',filter);categoryInput.addEventListener('change',filter);root.querySelector('#schResetFilters').addEventListener('click',()=>{searchInput.value='';categoryInput.value='all';filter();});
    setView('calendar');filter();
})();
