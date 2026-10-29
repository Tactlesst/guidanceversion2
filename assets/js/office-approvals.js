(()=>{'use strict';if(window.OfficeApprovals){window.OfficeApprovals.apply();return;}
const config=()=>{const node=document.getElementById('officeApprovalConfig');return node?JSON.parse(node.textContent):null;};
const selectors='[data-rs-export],#phPrint,#srPdsPrint,#assessmentPrintButton,#printSelectedList,[data-print-list]';
let state=null,requesting=false,polling=null,pollTimer=null,tickTimer=null,cooldownUntil=0,known={},owner=null,ready=false,notice=null,paintQueued=false;
const baselines=new WeakMap(),managed=new WeakSet();
function isAdvocate(){return config()?.role==='guidance_advocate';}
function remaining(){return Math.max(0,Math.ceil((cooldownUntil-Date.now())/1000));}
function blocked(){return requesting||!!state?.pending||remaining()>0;}
function reason(){return requesting?'Sending your request…':state?.pending?'Request #'+state.pending.id+' is awaiting the counselor/admin.':remaining()?'Please wait '+remaining()+' seconds before another request.':'';}
function widget(){let node=document.getElementById('oaRequestWindow');if(node)return node;node=document.createElement('aside');node.id='oaRequestWindow';node.className='oa-request-window';node.setAttribute('aria-label','Report approval updates');node.innerHTML='<button type="button" class="oa-request-close" aria-label="Dismiss approval update">×</button><span class="oa-request-symbol" aria-hidden="true">◷</span><div class="oa-request-copy"><strong data-oa-title></strong><p data-oa-message role="status" aria-live="polite" aria-atomic="true"></p><a href="layout.php?page=approvals">View my requests →</a></div>';node.querySelector('button').addEventListener('click',()=>{notice=null;node.hidden=true;});document.body.append(node);return node;}
function show(title,message,tone='pending'){notice={title,message,tone};paint();}
function paint(){
 if(!isAdvocate()){document.getElementById('oaRequestWindow')?.remove();return;}
 const stop=blocked();for(const button of document.querySelectorAll(selectors)){
  if(stop){if(!managed.has(button)){if(!baselines.has(button))baselines.set(button,button.disabled);managed.add(button);}if(!button.disabled)button.disabled=true;button.setAttribute('aria-disabled','true');button.dataset.approvalWaiting='1';}
  else if(managed.has(button)){button.disabled=!!baselines.get(button);button.removeAttribute('aria-disabled');delete button.dataset.approvalWaiting;managed.delete(button);baselines.delete(button);}
 }
 if(state?.pending)notice={title:'Awaiting approval',message:'Request #'+state.pending.id+' is with the counselor/admin. You can continue working while you wait.',tone:'pending'};
 if(remaining()&&!state?.pending)notice={title:'Request rejected',message:'You can send another request in '+remaining()+' '+(remaining()===1?'second.':'seconds.'),tone:'rejected'};
 if(notice){const node=widget();node.hidden=false;node.dataset.tone=notice.tone;const title=node.querySelector('[data-oa-title]'),message=node.querySelector('[data-oa-message]');if(title.textContent!==notice.title)title.textContent=notice.title;if(message.textContent!==notice.message)message.textContent=notice.message;const symbol=notice.tone==='approved'?'✓':notice.tone==='rejected'?'×':'◷';if(node.querySelector('.oa-request-symbol').textContent!==symbol)node.querySelector('.oa-request-symbol').textContent=symbol;}
 if(remaining()&&!tickTimer)tickTimer=setInterval(()=>{if(!remaining()){clearInterval(tickTimer);tickTimer=null;if(notice?.tone==='rejected')notice={title:'You can request again',message:'The waiting period is over. Review the requested report and try again when ready.',tone:'ready'};}paint();},250);
}
function saveKnown(){try{sessionStorage.setItem('srcb-approval-status:'+owner,JSON.stringify(known));}catch(e){}}
function receive(result){if(result.approval_required){state={...(state||{}),pending:{id:result.request_id,status:'pending'}};known[result.request_id]='pending';saveKnown();show('Request sent','The counselor/admin will review your request.');}if(result.retry_after)cooldownUntil=Date.now()+result.retry_after*1000;if(result.pending_id){state={...(state||{}),pending:{id:result.pending_id,status:'pending'}};}paint();}
async function refresh(){
 if(!isAdvocate()||document.hidden)return;if(polling)return polling;const uid=config().uid;
 polling=(async()=>{try{const response=await fetch('approval_status.php',{credentials:'same-origin',cache:'no-store'});const next=await response.json();if(!response.ok||!next.ok||!isAdvocate()||config().uid!==uid)return;
  state=next;ready=true;cooldownUntil=Date.now()+next.retry_after*1000;const rows=[...(next.recent||[]),...(next.active||[])];for(const row of rows){const prior=known[row.id];if(prior&&prior!==row.status){if(row.status==='approved')show('Request approved','Request #'+row.id+' is approved. Open the same report and use its matching Print or Export button.','approved');else if(row.status==='rejected')show('Request rejected','Request #'+row.id+' was rejected. Review the decision in My report approvals.','rejected');else if(row.status==='used'&&notice?.tone==='approved')show('Approved output prepared','Request #'+row.id+' has been used for one output. Request another approval when you need another print or export.','ready');else if(row.status==='revoked')show('Approval withdrawn','Request #'+row.id+' is no longer available. Check My report approvals.','rejected');}known[row.id]=row.status;}
  state=next;ready=true;cooldownUntil=Date.now()+next.retry_after*1000;saveKnown();paint();
 }catch(error){/* Keep a pending request locked until a confirmed decision arrives. */}finally{polling=null;schedule();}})();return polling;
}
function schedule(){clearTimeout(pollTimer);if(isAdvocate()&&!document.hidden)pollTimer=setTimeout(refresh,2000);}
async function begin(){if(!isAdvocate())return;if(requesting)throw new Error('Your request is already being sent.');if(!ready)await refresh();if(blocked()){show('Please wait',reason());throw new Error(reason());}for(const el of document.querySelectorAll(selectors))if(!managed.has(el)&&!baselines.has(el))baselines.set(el,el.disabled);requesting=true;paint();}
function end(){requesting=false;paint();refresh();}
async function authorize(resource,scope){const c=config();if(!c||c.role!=='guidance_advocate')return {ok:true};await begin();try{const response=await fetch('approval_actions.php',{method:'POST',credentials:'same-origin',body:new URLSearchParams({csrf:c.csrf,resource,format:'print',scope:JSON.stringify(scope)})});const result=await response.json();receive(result);if(result.approval_required)throw new Error(result.message);if(!response.ok||!result.ok)throw new Error(result.error||'This printout needs counselor/admin approval.');return result;}finally{end();}}
function apply(){const c=config();if(!c||c.role!=='guidance_advocate')return;const root=document.querySelector('.cw-workspace');if(!root)return;const page=root.dataset.workspacePage;
 if(page==='schedules'){
  root.querySelectorAll('[onclick*="openEditModal"],[onclick*="deleteSchedule"],[onclick*="deleteHoliday"],[onclick*="openHolidayModal"],[onclick*="openClosureModal"],[onclick*="reopenClosure"],[data-schedule-edit],[data-schedule-delete],[onclick*="editSchedule"],[onclick*="openSchoolClosure"],[onclick*="holidayModal"],#schMarkSelectedClosed').forEach(n=>{n.hidden=true;n.disabled=true;});
  root.querySelectorAll('form').forEach(form=>{if(form.querySelector('[name="update_schedule"],[name="delete_schedule"],[name="set_daily_limit"],[name="remove_daily_limit"],[name="create_holiday"],[name="delete_holiday"],[name="create_school_closure"],[name="reopen_school_closure"]')){form.querySelectorAll('input,select,textarea,button').forEach(n=>n.disabled=true);form.hidden=true;}});
 }
}

function mount(){apply();if(!isAdvocate()){paint();return;}const uid=config().uid;if(owner!==uid){owner=uid;state=null;ready=false;known={};try{known=JSON.parse(sessionStorage.getItem('srcb-approval-status:'+owner)||'{}');}catch(e){}}paint();refresh();schedule();}
document.addEventListener('click',event=>{const button=event.target.closest(selectors);if(!button||!isAdvocate())return;if(blocked()){event.preventDefault();event.stopImmediatePropagation();paint();}else baselines.set(button,button.disabled);},true);
const observer=new MutationObserver(mutations=>{if(!isAdvocate()||paintQueued||!mutations.some(m=>m.type==='childList'||m.target.matches?.(selectors)))return;paintQueued=true;queueMicrotask(()=>{paintQueued=false;paint();});});observer.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['disabled']});
window.OfficeApprovals={authorize,config,apply,begin,end,receive,refresh,paint};document.addEventListener('portal:page-loaded',mount);document.addEventListener('visibilitychange',()=>{clearTimeout(pollTimer);if(!document.hidden)refresh();});if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();})();
