function userAlert(...args){return window.PortalDialogs?window.PortalDialogs.fire(...args):Swal.fire(...args);}
let userRowsRequest = {active:0,archived:0};
let userActionPopover = null;
let userDialogFocus = null;
let activeUserDialog = null;
const userRoleNames = {super_admin:'Superadmin',admin:'Admin / Counselor',guidance_advocate:'Guidance advocate',student:'Student',examinee:'Examinee'};
// User Management JavaScript
const BASE = 'layout.php?page=user_management';
let searchTimer;
let activePage = 1;
let archivedPage = 1;
let itemsPerPage = 10;
let currentFilter = new URLSearchParams(window.location.search).get('filter') || '';
const RC_MAP = {'super_admin':'purple','admin':'red','guidance_advocate':'green','student':'blue','examinee':'yellow'};

function toggleStudentFields() {
    const role = document.getElementById('createRole').value;
    document.getElementById('studentIdField').style.display = role==='student' ? 'grid' : 'none';
    document.getElementById('create_email').required=role!=='student';
    document.getElementById('positionField').style.display = ['admin','guidance_advocate','super_admin'].includes(role) ? 'block' : 'none';
}

function toggleExamineeConversion() {
    document.getElementById('examineeFields').classList.toggle('hidden', !document.getElementById('convertExaminee').checked);
}

function fillExamineeData() {
    const sel = document.getElementById('examineeSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('create_first_name').value = opt.dataset.first || '';
        document.getElementById('create_middle_name').value = opt.dataset.middle || '';
        document.getElementById('create_last_name').value = opt.dataset.last || '';
        document.getElementById('create_email').value = opt.dataset.email || '';
        document.getElementById('createRole').value = 'student';
        toggleStudentFields();
    }
}

function editUser(data) {
    document.getElementById('edit_user_id').value = data.id;
    document.getElementById('edit_first_name').value = data.first_name || '';
    document.getElementById('edit_middle_name').value = data.middle_name || '';
    document.getElementById('edit_last_name').value = data.last_name || '';
    document.getElementById('edit_email').value = data.email || '';
    document.getElementById('edit_role').value = data.role || 'student';
    document.getElementById('edit_student_id').value = data.student_id || '';
    document.getElementById('edit_is_active').checked = data.is_active == 1;
    openUserDialog('editUserModal');
}

function openResetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    openUserDialog('resetPasswordModal');
}

function archiveUser(id, name) {
    userAlert({ title: 'Archive User?', html: `Are you sure you want to archive <strong>${esc(name)}</strong>?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Archive', cancelButtonText: 'Cancel' })
    .then(r => { 
        if (r.isConfirmed) {
            fetch(BASE+'&action=archive&id='+id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    userAlert('Archived!','User has been archived.','success').then(()=>location.reload());
                } else {
                    userAlert('Error', data.error || 'Failed to archive user', 'error');
                }
            })
            .catch(err => {
                console.error('Archive error:', err);
                userAlert('Error', 'Failed to archive user. Check console for details.', 'error');
            });
        }
    });
}

function unarchiveUser(id, name) {
    userAlert({ title: 'Restore User?', html: `Restore <strong>${esc(name)}</strong>?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#16a34a', confirmButtonText: 'Restore' })
    .then(r => { 
        if (r.isConfirmed) {
            fetch(BASE+'&action=unarchive&id='+id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    userAlert('Restored!','User has been restored.','success').then(()=>location.reload());
                } else {
                    userAlert('Error', data.error || 'Failed to restore user', 'error');
                }
            })
            .catch(err => {
                console.error('Restore error:', err);
                userAlert('Error', 'Failed to restore user. Check console for details.', 'error');
            });
        }
    });
}

function toggleStatus(id) {
    console.log('Toggle status called for user ID:', id);
    const url = BASE+'&action=toggle_status&id='+id;
    console.log('Fetching URL:', url);
    
    fetch(url)
    .then(r => {
        console.log('Response received:', r);
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        return r.json();
    })
    .then(data => {
        console.log('Data received:', data);
        if (data.success) {
            // Force reload with timestamp to prevent caching
            window.location.href = BASE + '&t=' + new Date().getTime();
        } else {
            userAlert('Error', data.error || 'Failed to toggle status', 'error');
        }
    })
    .catch(err => {
        console.error('Toggle status error:', err);
        userAlert('Error', 'Failed to toggle status. Check console for details.', 'error');
    });
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { 
        activePage = 1; 
        archivedPage = 1;
        const isArchivedVisible = !document.getElementById('panel-archived').classList.contains('hidden');
        fetchActiveUsers();
        if (isArchivedVisible) fetchArchivedUsers();
    }, 300);
}

function onRoleFilterChange() {currentFilter='';refreshUserFilters();}

function onStatusFilterChange() {refreshUserFilters();}

function onLetterFilterChange() {refreshUserFilters();}

function onItemsPerPageChange(event) {itemsPerPage=Number(event.target.value);document.getElementById('activeItemsPerPage').value=itemsPerPage;document.getElementById('archivedItemsPerPage').value=itemsPerPage;refreshUserFilters();}

function fetchActiveUsers() { return fetchUserPage('active'); }

function esc(value) {return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAllUsers');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectedCount').textContent = count;
    const btn = document.getElementById('bulkArchiveBtn');
    if (count === 0) {
        btn.classList.add('hidden');
    } else {
        btn.classList.remove('hidden');
    }
}

function bulkArchiveUsers() {
    const checkboxes = document.querySelectorAll('.user-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.dataset.id);
    
    if (ids.length === 0) {
        userAlert('No Selection', 'Please select at least one user to archive.', 'warning');
        return;
    }
    
    userAlert({
        title: 'Archive Users?',
        html: `Are you sure you want to archive <strong>${ids.length}</strong> user(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Archive',
        cancelButtonText: 'Cancel'
    }).then(r => {
        if (r.isConfirmed) {
            fetch(BASE + '&action=bulk_archive', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    userAlert('Archived!', `${ids.length} user(s) have been archived.`, 'success').then(() => location.reload());
                } else {
                    userAlert('Error', data.error || 'Failed to archive users', 'error');
                }
            }).catch(err => {
                console.error('Error:', err);
                userAlert('Error', 'Failed to archive users', 'error');
            });
        }
    });
}

function toggleSelectAllArchived() {
    const selectAll = document.getElementById('selectAllArchived');
    const checkboxes = document.querySelectorAll('.archived-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateArchivedSelectedCount();
}

function updateArchivedSelectedCount() {
    const checkboxes = document.querySelectorAll('.archived-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('archivedSelectedCount').textContent = count;
    const btn = document.getElementById('bulkUnarchiveBtn');
    if (count === 0) {
        btn.classList.add('hidden');
    } else {
        btn.classList.remove('hidden');
    }
}

function bulkUnarchiveUsers() {
    const checkboxes = document.querySelectorAll('.archived-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.dataset.id);
    
    if (ids.length === 0) {
        userAlert('No Selection', 'Please select at least one user to restore.', 'warning');
        return;
    }
    
    userAlert({
        title: 'Restore Users?',
        html: `Are you sure you want to restore <strong>${ids.length}</strong> user(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Restore',
        cancelButtonText: 'Cancel'
    }).then(r => {
        if (r.isConfirmed) {
            fetch(BASE + '&action=bulk_unarchive', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids })
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    userAlert('Restored!', `${ids.length} user(s) have been restored.`, 'success').then(() => location.reload());
                } else {
                    userAlert('Error', data.error || 'Failed to restore users', 'error');
                }
            }).catch(err => {
                console.error('Error:', err);
                userAlert('Error', 'Failed to restore users', 'error');
            });
        }
    });
}

function fetchArchivedUsers() { return fetchUserPage('archived'); }

function renderPagination(prefix,total,perPage,page) {
    const totalPages=Math.max(1,Math.ceil(total/perPage));
    document.getElementById(prefix+'PageInfo').textContent=total===0?'No accounts to display':'Showing '+((page-1)*perPage+1)+'–'+Math.min(page*perPage,total)+' of '+Number(total).toLocaleString()+' accounts';
    document.getElementById(prefix+'PrevBtn').disabled=page<=1;document.getElementById(prefix+'NextBtn').disabled=page>=totalPages;
    document.getElementById(prefix+'PageIndicator').textContent=page+' / '+totalPages;
    const container=document.getElementById(prefix+'PageNums');container.replaceChildren();
    const start=Math.max(1,Math.min(page-2,totalPages-4)),end=Math.min(totalPages,start+4);
    for(let n=start;n<=end;n++){const button=document.createElement('button');button.type='button';button.textContent=n;button.className='um-page-button'+(n===page?' is-current':'');button.setAttribute('aria-label','Page '+n);if(n===page)button.setAttribute('aria-current','page');button.onclick=()=>{if(prefix==='active'){activePage=n;fetchActiveUsers();}else{archivedPage=n;fetchArchivedUsers();}};container.appendChild(button);}
}

function activeChangePage(delta) { activePage += delta; fetchActiveUsers(); }
function archivedChangePage(delta) { archivedPage += delta; fetchArchivedUsers(); }

function switchUserTab(tab,resetPage=true) {
    closeUserActions();
    for(const name of ['active','archived','imports']){const selected=name===tab;document.getElementById('panel-'+name).classList.toggle('hidden',!selected);const button=document.getElementById('tab-'+name);button.className='um-tab'+(selected?' is-active':'');button.setAttribute('aria-selected',String(selected));button.tabIndex=selected?0:-1;}
    document.getElementById('userFilters').classList.toggle('hidden',tab==='imports');
    let loading=Promise.resolve();
    if(tab==='archived'){if(resetPage)archivedPage=1;loading=fetchArchivedUsers();}else if(tab==='active'){if(resetPage)activePage=1;loading=fetchActiveUsers();}
    const url=new URL(location.href);if(currentFilter)url.searchParams.set('filter',currentFilter);else url.searchParams.delete('filter');
    if(tab==='active')url.searchParams.delete('tab');else url.searchParams.set('tab',tab);
    history.replaceState({...history.state},'',url);
    return loading;
}

// Init - wait for DOM to be ready
document.addEventListener('DOMContentLoaded', initializeUserManagement);

function fetchUserPage(kind) {
    const sequence=++userRowsRequest[kind], archived=kind==='archived';
    const body=document.getElementById(archived?'archivedUsersBody':'activeUsersBody');
    if(!body)return;
    const query=new URLSearchParams({action:archived?'fetch_archived':'fetch_active',p:archived?archivedPage:activePage,per:itemsPerPage,q:document.getElementById('searchInput').value,role:document.getElementById('roleFilter').value,status:document.getElementById('statusFilter').value,letter:document.getElementById('letterFilter').value,_t:Date.now()});
    if(!archived)query.set('filter',currentFilter);
    body.setAttribute('aria-busy','true');
    closeUserActions(); syncUserFilters();
    return fetch(BASE+'&'+query).then(response=>{if(!response.ok)throw new Error('Request failed');return response.json();}).then(data=>{
        if(sequence!==userRowsRequest[kind] || !body.isConnected)return;
        if(!Array.isArray(data.rows))throw new Error('Invalid user response');
        renderUserRows(body,data.rows,archived);
        renderPagination(kind,data.total,data.per_page,data.page);
        if(archived)archivedPage=data.page;else activePage=data.page;
        document.getElementById(archived?'selectAllArchived':'selectAllUsers').checked=false;
        document.getElementById(kind+'ItemsPerPage').value=data.per_page;
        if(archived)updateArchivedSelectedCount();else updateSelectedCount();
    }).catch(error=>{
        if(sequence!==userRowsRequest[kind] || !body.isConnected || error.name==='AbortError')return;
        body.innerHTML='<tr><td colspan="7" class="um-empty"><strong>Could not load accounts</strong><p>Please try your search again.</p></td></tr>';
    }).finally(()=>{if(sequence===userRowsRequest[kind] && body.isConnected)body.removeAttribute('aria-busy');});
}

function renderUserRows(body,rows,archived) {
    body.replaceChildren();
    if(!rows.length){body.innerHTML='<tr><td colspan="7" class="um-empty"><span class="um-empty-icon"><i class="fas fa-users" aria-hidden="true"></i></span><strong>'+(archived?'No archived accounts found':'No matching accounts')+'</strong><p>Try a different search or reset your filters.</p></td></tr>';return;}
    for(const user of rows){
        const name=[user.first_name,user.middle_name,user.last_name].filter(Boolean).join(' ');
        const initials=((user.first_name||'')[0]||'')+((user.last_name||'')[0]||'');
        const role=userRoleNames[user.role]||'User';
        const enabled=Number(user.is_active)===1;
        const date=new Date(String(user.created_at).replace(' ','T'));
        const dateLabel=isNaN(date)?'—':date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        const row=document.createElement('tr');
        row.innerHTML='<td class="um-check-cell"></td><td class="um-account-cell"><div class="um-account"><span class="um-user-avatar" aria-hidden="true">'+esc(initials.toUpperCase())+'</span><span class="um-account-text"><strong>'+esc(name)+'</strong><small>'+esc(user.email||'No email address')+'</small></span></div></td><td data-label="Role"><span class="um-role-badge um-role-'+(Object.hasOwn(userRoleNames,user.role)?user.role:'user')+'">'+esc(role)+'</span></td><td data-label="Student ID"><span class="um-id">'+esc(user.student_id||'—')+'</span></td><td data-label="Status"><span class="um-status '+(archived?'is-archived':enabled?'':'is-inactive')+'">'+(archived?'Archived':enabled?'Active':'Inactive')+'</span></td><td data-label="Joined"><time class="um-date">'+esc(dateLabel)+'</time></td><td class="um-actions-cell"><div class="um-row-actions"></div></td>';
        const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.className=archived?'archived-checkbox':'user-checkbox';checkbox.dataset.id=user.id;checkbox.setAttribute('aria-label','Select '+name);checkbox.onchange=archived?updateArchivedSelectedCount:updateSelectedCount;row.firstElementChild.appendChild(checkbox);
        const actions=row.querySelector('.um-row-actions');
        const primary=document.createElement('button');primary.type='button';primary.className=archived?'um-restore':'um-edit';primary.textContent=archived?'Restore':'Edit details';primary.setAttribute('aria-label',(archived?'Restore ':'Edit ')+name);primary.onclick=()=>archived?unarchiveUser(user.id,name):editUser(user);actions.appendChild(primary);
        if(!archived){const more=document.createElement('button');more.type='button';more.className='um-more';more.innerHTML='<span>More</span><i class="fas fa-chevron-down" aria-hidden="true"></i>';more.setAttribute('aria-label','More actions for '+name);more.setAttribute('aria-haspopup','menu');more.setAttribute('aria-expanded','false');more.onclick=event=>openUserActions(event,user);actions.appendChild(more);}
        body.appendChild(row);
    }
}

function openUserActions(event,user) {
    event.stopPropagation();
    const trigger=event.currentTarget;
    if(userActionPopover?.trigger===trigger){closeUserActions();return;}
    closeUserActions();
    const popup=document.createElement('div');popup.className='um-action-popover';popup.setAttribute('role','menu');popup.setAttribute('aria-label','Account actions');
    const name=[user.first_name,user.last_name].filter(Boolean).join(' ');
    const actions=[['fa-key','Reset password',()=>openResetPassword(user.id,name)],['fa-power-off',Number(user.is_active)===1?'Deactivate account':'Activate account',()=>toggleStatus(user.id)],['fa-box-archive','Archive account',()=>archiveUser(user.id,name)]];
    for(const [icon,label,action]of actions){const button=document.createElement('button');button.type='button';button.setAttribute('role','menuitem');button.innerHTML='<i class="fas '+icon+'" aria-hidden="true"></i><span>'+label+'</span>';button.onclick=()=>{closeUserActions(true);action();};popup.appendChild(button);}
    document.body.appendChild(popup);
    const rect=trigger.getBoundingClientRect();
    popup.style.left=Math.max(12,Math.min(innerWidth-popup.offsetWidth-12,rect.right-popup.offsetWidth))+'px';
    popup.style.top=Math.max(12,Math.min(innerHeight-popup.offsetHeight-12,rect.bottom+5))+'px';
    userActionPopover={popup,trigger};trigger.setAttribute('aria-expanded','true');popup.querySelector('button').focus();
}

function closeUserActions(restore=false) {
    if(!userActionPopover)return;
    const {popup,trigger}=userActionPopover;popup.remove();trigger.setAttribute('aria-expanded','false');userActionPopover=null;if(restore && trigger.isConnected)trigger.focus();
}

function syncUserFilters() {
    const value=document.getElementById('roleFilter').value;
    document.querySelectorAll('[data-um-role]').forEach(card=>{const selected=card.dataset.umRole===value;card.classList.toggle('is-selected',selected);card.setAttribute('aria-pressed',String(selected));});
    document.getElementById('umMissingIdNotice').classList.toggle('hidden',currentFilter!=='missing_id');
    const labels={'':'All accounts',student:'Students',examinee:'Examinees',guidance_advocate:'Guidance advocates',admin:'Admins / Counselors',super_admin:'Superadmins'};
    const descriptions={'':'All roles',student:'Enrolled students',examinee:'Entrance exam applicants',guidance_advocate:'Staff who support counseling',admin:'Guidance office administrators',super_admin:'System and account administrators'};
    document.querySelector('.um-workspace').dataset.role=value;
    document.getElementById('umListTitle-active').textContent=value?(labels[value]+' — current accounts'):'All current accounts';
    document.getElementById('umListHint-active').textContent=descriptions[value]+'. Active users can sign in; inactive users cannot.';
    document.getElementById('umListTitle-archived').textContent=value?(labels[value]+' — archived accounts'):'All archived accounts';
    document.getElementById('umListHint-archived').textContent='Archived accounts cannot sign in. Restore an account to make it available again.';

}

function chooseUserRole(role) {for(const id of ['searchInput','statusFilter','letterFilter'])document.getElementById(id).value='';document.getElementById('roleFilter').value=role;onRoleFilterChange();}

function refreshUserFilters() {activePage=1;archivedPage=1;fetchActiveUsers();if(!document.getElementById('panel-archived').classList.contains('hidden'))fetchArchivedUsers();}

function clearUserFilters() {currentFilter='';for(const id of ['searchInput','roleFilter','statusFilter','letterFilter'])document.getElementById(id).value='';refreshUserFilters();}

function toggleUserFilters(button) {const fields=document.getElementById('umFilterFields');const open=fields.classList.toggle('is-open');button.setAttribute('aria-expanded',String(open));}

function capturePortalPageState() {
    return {kind:'users',activePage,archivedPage,itemsPerPage,currentFilter,
        tab:['active','archived','imports'].find(tab=>!document.getElementById('panel-'+tab).classList.contains('hidden'))||'active',
        fields:Object.fromEntries(['searchInput','roleFilter','statusFilter','letterFilter'].map(id=>[id,document.getElementById(id).value])),
        filtersOpen:document.getElementById('umFilterFields').classList.contains('is-open')};
}
function restorePortalPageState(state) {
    activePage=state.activePage;archivedPage=state.archivedPage;itemsPerPage=state.itemsPerPage;currentFilter=state.currentFilter;
    for(const id of ['searchInput','roleFilter','statusFilter','letterFilter'])document.getElementById(id).value=state.fields[id]||'';
    document.getElementById('umFilterFields').classList.toggle('is-open',state.filtersOpen);
    document.querySelector('.um-filter-toggle')?.setAttribute('aria-expanded',String(state.filtersOpen));
    syncUserFilters();return switchUserTab(state.tab,false);
}

function openUserDialog(id) {
    closeUserActions();
    const dialog=document.getElementById(id);if(!dialog)return;
    userDialogFocus=document.activeElement;activeUserDialog=dialog;dialog.classList.remove('hidden');window.PortalDialogs?.open(dialog);
    const first=dialog.querySelector('.pd-body input:not([type=hidden]):not([type=checkbox]),.pd-body select,.pd-body button');first?.focus({preventScroll:true});
}

function closeUserDialog(id) {
    const dialog=document.getElementById(id);dialog?.classList.add('hidden');window.PortalDialogs?.close(dialog);activeUserDialog=null;
    if(userDialogFocus?.isConnected)userDialogFocus.focus({preventScroll:true});userDialogFocus=null;
}

function initializeUserManagement() {
    toggleStudentFields();
    document.querySelectorAll('.um-modal label:not([for])').forEach((label,index)=>{const control=label.parentElement.querySelector('input:not([type=hidden]),select');if(control && !label.contains(control)){control.id=control.id||'um-dialog-field-'+index;label.htmlFor=control.id;}});
    document.querySelectorAll('.um-modal').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)closeUserDialog(dialog.id);}));
    document.addEventListener('click',event=>{if(userActionPopover && !userActionPopover.popup.contains(event.target))closeUserActions();});
    window.addEventListener('resize',()=>closeUserActions());
    window.addEventListener('scroll',()=>closeUserActions(),true);
    document.addEventListener('keydown',event=>{
        if(window.Swal?.isVisible())return;
        if(event.key==='Escape'){if(userActionPopover){closeUserActions(true);event.preventDefault();}else if(activeUserDialog){closeUserDialog(activeUserDialog.id);event.preventDefault();}}
        if(userActionPopover && ['ArrowDown','ArrowUp'].includes(event.key)){const items=[...userActionPopover.popup.querySelectorAll('button')];const index=items.indexOf(document.activeElement);items[(index+(event.key==='ArrowDown'?1:-1)+items.length)%items.length].focus();event.preventDefault();}
        if(activeUserDialog && event.key==='Tab'){const items=[...activeUserDialog.querySelectorAll('button,input:not([type=hidden]),select,a[href]')].filter(el=>el.offsetParent!==null && !el.disabled);if(event.shiftKey && document.activeElement===items[0]){event.preventDefault();items.at(-1).focus();}else if(!event.shiftKey && document.activeElement===items.at(-1)){event.preventDefault();items[0].focus();}}
    });
    document.querySelectorAll('.um-tab').forEach(button=>button.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;const tabs=['active','archived','imports'];let index=tabs.indexOf(button.id.slice(4));index=event.key==='Home'?0:event.key==='End'?2:(index+(event.key==='ArrowRight'?1:-1)+3)%3;switchUserTab(tabs[index]);document.getElementById('tab-'+tabs[index]).focus();event.preventDefault();}));
    const saved=window.PortalNavigation?.getRestoreState();
    if(saved?.kind==='users')return restorePortalPageState(saved);
    syncUserFilters();
    const initial=new URLSearchParams(location.search).get('tab');if(['archived','imports'].includes(initial))return switchUserTab(initial);else return fetchActiveUsers();
}
