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
    openModal('editUserModal');
}

function openResetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    openModal('resetPasswordModal');
}

function archiveUser(id, name) {
    Swal.fire({ title: 'Archive User?', html: `Are you sure you want to archive <strong>${name}</strong>?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Archive', cancelButtonText: 'Cancel' })
    .then(r => { 
        if (r.isConfirmed) {
            fetch(BASE+'&action=archive&id='+id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Archived!','User has been archived.','success').then(()=>location.reload());
                } else {
                    Swal.fire('Error', data.error || 'Failed to archive user', 'error');
                }
            })
            .catch(err => {
                console.error('Archive error:', err);
                Swal.fire('Error', 'Failed to archive user. Check console for details.', 'error');
            });
        }
    });
}

function unarchiveUser(id, name) {
    Swal.fire({ title: 'Restore User?', html: `Restore <strong>${name}</strong>?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#16a34a', confirmButtonText: 'Restore' })
    .then(r => { 
        if (r.isConfirmed) {
            fetch(BASE+'&action=unarchive&id='+id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Restored!','User has been restored.','success').then(()=>location.reload());
                } else {
                    Swal.fire('Error', data.error || 'Failed to restore user', 'error');
                }
            })
            .catch(err => {
                console.error('Restore error:', err);
                Swal.fire('Error', 'Failed to restore user. Check console for details.', 'error');
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
            Swal.fire('Error', data.error || 'Failed to toggle status', 'error');
        }
    })
    .catch(err => {
        console.error('Toggle status error:', err);
        Swal.fire('Error', 'Failed to toggle status. Check console for details.', 'error');
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

function onRoleFilterChange() {
    currentFilter = ''; // Clear the missing_id filter when role changes
    activePage = 1;
    fetchActiveUsers();
}

function onStatusFilterChange() {
    activePage = 1;
    archivedPage = 1;
    fetchActiveUsers();
    const isArchivedVisible = !document.getElementById('panel-archived').classList.contains('hidden');
    if (isArchivedVisible) fetchArchivedUsers();
}

function onLetterFilterChange() {
    activePage = 1;
    archivedPage = 1;
    fetchActiveUsers();
    const isArchivedVisible = !document.getElementById('panel-archived').classList.contains('hidden');
    if (isArchivedVisible) fetchArchivedUsers();
}

function onItemsPerPageChange() {
    const source = event.target.id;
    const value = parseInt(event.target.value);
    itemsPerPage = value;
    
    // Sync pagination selectors
    document.getElementById('activeItemsPerPage').value = value;
    document.getElementById('archivedItemsPerPage').value = value;
    
    activePage = 1;
    archivedPage = 1;
    fetchActiveUsers();
    const isArchivedVisible = !document.getElementById('panel-archived').classList.contains('hidden');
    if (isArchivedVisible) fetchArchivedUsers();
}

function fetchActiveUsers() {
    const q = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const letter = document.getElementById('letterFilter').value;
    const url = BASE + `&action=fetch_active&p=${activePage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&filter=${encodeURIComponent(currentFilter)}&letter=${encodeURIComponent(letter)}&status=${encodeURIComponent(status)}&_t=${new Date().getTime()}`;
    fetch(url).then(r=>r.json()).then(data => {
        const tbody = document.getElementById('activeUsersBody');
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No users found</td></tr>';
        } else {
            let html = '';
            data.rows.forEach(u => {
                const rc = RC_MAP[u.role]||'gray';
                const name = (u.last_name||'') + ', ' + (u.first_name||'') + (u.middle_name ? ' ' + u.middle_name : '');
                const escName = (u.first_name||'') + ' ' + (u.last_name||'');
                html += `<tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><input type="checkbox" class="user-checkbox rounded" data-id="${u.id}" onchange="updateSelectedCount()"></td>
                    <td class="px-4 py-3 font-medium break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-${rc}-100 text-${rc}-700 capitalize">${u.role.replace(/_/g,' ')}</span></td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.student_id||'—')}</td>
                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs ${u.is_active?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}">${u.is_active?'Active':'Inactive'}</span></td>
                    <td class="px-4 py-3 text-gray-400 text-xs">${new Date(u.created_at).toLocaleDateString()}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-1">
                            <button type="button" onclick='editUser(${JSON.stringify(u).replace(/'/g,"&#39;")})' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" onclick="toggleStatus(${u.id})" class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded" title="Toggle"><i class="fas fa-power-off"></i></button>
                            <button type="button" onclick="openResetPassword(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="p-1.5 text-orange-600 hover:bg-orange-50 rounded" title="Reset Password"><i class="fas fa-key"></i></button>
                            <button type="button" onclick="archiveUser(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Archive"><i class="fas fa-archive"></i></button>
                        </div>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
        renderPagination('active', data.total, data.per_page, data.page);
        updateSelectedCount();
        // Sync pagination selector
        document.getElementById('activeItemsPerPage').value = data.per_page;
    }).catch(err => {
        console.error('Error fetching active users:', err);
        document.getElementById('activeUsersBody').innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-red-400">Error loading users</td></tr>';
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

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
        Swal.fire('No Selection', 'Please select at least one user to archive.', 'warning');
        return;
    }
    
    Swal.fire({
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
                    Swal.fire('Archived!', `${ids.length} user(s) have been archived.`, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'Failed to archive users', 'error');
                }
            }).catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Failed to archive users', 'error');
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
        Swal.fire('No Selection', 'Please select at least one user to restore.', 'warning');
        return;
    }
    
    Swal.fire({
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
                    Swal.fire('Restored!', `${ids.length} user(s) have been restored.`, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'Failed to restore users', 'error');
                }
            }).catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Failed to restore users', 'error');
            });
        }
    });
}

function fetchArchivedUsers() {
    const q = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const letter = document.getElementById('letterFilter').value;
    const url = BASE + `&action=fetch_archived&p=${archivedPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&letter=${encodeURIComponent(letter)}&status=${encodeURIComponent(status)}&_t=${new Date().getTime()}`;
    fetch(url).then(r => {
        if (!r.ok) throw new Error(`HTTP error! status: ${r.status}`);
        return r.json();
    }).then(data => {
        const tbody = document.getElementById('archivedUsersBody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No archived users</td></tr>';
        } else {
            let html = '';
            data.rows.forEach(u => {
                const name = (u.last_name||'') + ', ' + (u.first_name||'');
                const escName = (u.first_name||'') + ' ' + (u.last_name||'');
                html += `<tr class="hover:bg-gray-50 opacity-70">
                    <td class="px-4 py-3"><input type="checkbox" class="archived-checkbox rounded" data-id="${u.id}" onchange="updateArchivedSelectedCount()"></td>
                    <td class="px-4 py-3 break-words">${esc(name)}</td>
                    <td class="px-4 py-3 text-gray-500 break-all">${esc(u.email||'—')}</td>
                    <td class="px-4 py-3 capitalize text-gray-500">${u.role.replace(/_/g,' ')}</td>
                    <td class="px-4 py-3 text-right"><button type="button" onclick="unarchiveUser(${u.id},'${esc(escName).replace(/'/g,"\\'")}')" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200"><i class="fas fa-undo mr-1"></i>Restore</button></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }
        renderPagination('archived', data.total, data.per_page, data.page);
        updateArchivedSelectedCount();
        // Sync pagination selector
        document.getElementById('archivedItemsPerPage').value = data.per_page;
    }).catch(err => {
        console.error('Error fetching archived users:', err);
        const tbody = document.getElementById('archivedUsersBody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-400">Error loading archived users</td></tr>';
        }
    });
}

function renderPagination(prefix, total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    
    const pageInfoEl = document.getElementById(prefix + 'PageInfo');
    const prevBtnEl = document.getElementById(prefix + 'PrevBtn');
    const nextBtnEl = document.getElementById(prefix + 'NextBtn');
    const pageNumsEl = document.getElementById(prefix + 'PageNums');
    
    if (pageInfoEl) pageInfoEl.textContent = total === 0 ? 'No records' : `Showing ${start}-${end} of ${total}`;
    if (prevBtnEl) prevBtnEl.disabled = page <= 1;
    if (nextBtnEl) nextBtnEl.disabled = page >= totalPages;
    
    if (pageNumsEl) {
        pageNumsEl.innerHTML = '';
        const maxBtns = 5;
        let sp = Math.max(1, page - Math.floor(maxBtns/2));
        let ep = Math.min(totalPages, sp + maxBtns - 1);
        if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);
        
        for (let p = sp; p <= ep; p++) {
            const b = document.createElement('button');
            b.textContent = p;
            b.className = p === page ? 'px-2.5 py-1 text-sm rounded-lg bg-primary text-white' : 'px-2.5 py-1 text-sm rounded-lg border hover:bg-gray-50';
            b.onclick = () => {
                if (prefix === 'active') { activePage = p; fetchActiveUsers(); }
                else { archivedPage = p; fetchArchivedUsers(); }
            };
            pageNumsEl.appendChild(b);
        }
    }
}

function activeChangePage(delta) { activePage += delta; fetchActiveUsers(); }
function archivedChangePage(delta) { archivedPage += delta; fetchArchivedUsers(); }

function switchUserTab(tab) {
    ['active', 'archived', 'imports'].forEach(t => {
        const panel = document.getElementById('panel-' + t);
        if (panel) {
            if (t === tab) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        }
        const btn = document.getElementById('tab-' + t);
        if (btn) {
            if (t === tab) {
                btn.classList.remove('text-gray-600');
                btn.classList.add('bg-primary', 'text-white');
            } else {
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('text-gray-600');
            }
        }
    });
    
    if (tab === 'archived') { 
        archivedPage = 1; 
        fetchArchivedUsers(); 
    } else if (tab === 'active') { 
        activePage = 1; 
        fetchActiveUsers(); 
    } else if (tab === 'imports') {
        // Imports tab - no fetch needed
    }
    
    // Update URL to preserve filter when switching tabs
    const url = new URL(window.location);
    if (currentFilter) {
        url.searchParams.set('filter', currentFilter);
    } else {
        url.searchParams.delete('filter');
    }
    window.history.replaceState({}, '', url);
}

// Init - wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    toggleStudentFields();
    // Force fresh data fetch on page load
    fetchActiveUsers();
});
