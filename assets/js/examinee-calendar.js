(() => {
    'use strict';
    const root=document.getElementById('ex-booking'),dataNode=document.getElementById('ex-calendar-data');
    if(!root||!dataNode)return;
    const data=JSON.parse(dataNode.textContent),form=root.querySelector('#ex-book-form'),title=root.querySelector('#ex-date-title'),message=root.querySelector('#ex-date-message'),options=root.querySelector('#ex-slot-options'),dateInput=root.querySelector('#ex-date-input'),timeInput=root.querySelector('#ex-time-input');
    function choose(date){
        const day=data.days[date];if(!day)return;
        root.querySelectorAll('[data-ex-date]').forEach(button=>{const on=button.dataset.exDate===date;button.classList.toggle('selected',on);button.setAttribute('aria-pressed',String(on));});
        title.textContent=day.label;message.textContent=day.message;dateInput.value=date;timeInput.value='';options.replaceChildren();form.hidden=data.blocked||!day.slots.length;
        if(day.slots.length&&!data.blocked)message.textContent=day.capacity===null?'Booking is open. No attendee limit has been set for this date.':day.remaining+' '+(day.remaining===1?'place':'places')+' available for examination on this date.';
        if(!day.scheduled.length){const empty=document.createElement('p');empty.className='ex-session-empty';empty.textContent='No entrance exam is scheduled for this date.';options.append(empty);}
        for(const session of day.scheduled){
            const bookable=session.bookable&&!data.blocked,card=document.createElement(bookable?'label':'article');card.className='ex-session-card'+(bookable?' is-bookable':'');
            if(bookable){const radio=document.createElement('input');radio.type='radio';radio.name='schedule_id';radio.value=String(session.schedule_id);radio.required=true;radio.setAttribute('form','ex-book-form');radio.addEventListener('change',()=>{if(radio.checked)timeInput.value=session.value;});if(day.slots.length===1){radio.checked=true;timeInput.value=session.value;}card.append(radio);}
            const name=document.createElement('strong');name.textContent=session.title;const time=document.createElement('span');time.className='ex-session-time';time.textContent=session.label;const status=document.createElement('span');status.className='ex-session-status';status.textContent=data.blocked?'You already have an active or completed examination.':(session.bookable?'Scheduled exam session · Reserve your place':session.reason);card.append(name,time,status);options.append(card);
        }
    }
    root.querySelectorAll('[data-ex-date]').forEach(button=>button.addEventListener('click',()=>choose(button.dataset.exDate)));
    form.addEventListener('submit',()=>{if(form.checkValidity()){const button=form.querySelector('button[type=submit]');if(button){button.disabled=true;button.textContent='Reserving…';}}});
    choose(data.initial);
})();
