<?php
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$pds = new PersonalDataSheet($db);
require_once __DIR__ . '/../../../includes/pds_education_categories.php';
$pds_category_sql = pdsEducationCategorySQL();
$pds_category_keys = ['kinder','elementary','junior_high','senior_high','college'];

// AJAX endpoint for fetching PDS records
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    error_reporting(0);
    ob_end_clean();
    header('Content-Type: application/json');
    
    try {
        $pg = max(1, intval($_GET['p'] ?? 1));
        $per = max(1, min(50, intval($_GET['per'] ?? 10)));
        $off = ($pg - 1) * $per;
        $q = trim($_GET['q'] ?? '');
        $education_level = $_GET['education_level'] ?? '';
        $category = $_GET['category'] ?? '';
        if ($category !== '' && !in_array($category, $pds_category_keys, true)) { throw new InvalidArgumentException('Invalid education category.'); }
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $sort = $_GET['sort'] ?? 'latest';
        
        $w = [];
        $p_arr = [];
        
        if ($q) {
            $w[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR sp.student_id LIKE ? OR p.nickname LIKE ?)";
            $like = "%$q%";
            $p_arr = [$like, $like, $like, $like];
        }
        
        if ($education_level) {
            $w[] = "CONVERT(COALESCE(NULLIF(sp.grade_level, ''), p.grade_level) USING utf8mb4) COLLATE utf8mb4_unicode_ci = ?";
            $p_arr[] = $education_level;
        }
        
        if ($category !== '') { $w[] = "($pds_category_sql) = ?"; $p_arr[] = $category; }

        if ($date_from) {
            $w[] = "p.created_at >= ?";
            $p_arr[] = $date_from;
        }
        
        if ($date_to) {
            $w[] = "p.created_at <= ?";
            $p_arr[] = $date_to;
        }
        
        $where = !empty($w) ? 'WHERE ' . implode(' AND ', $w) : '';
        
        // Count
        $c_query = "SELECT COUNT(*) as total FROM pds p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id $where";
        $c_stmt = $db->prepare($c_query);
        $c_stmt->execute($p_arr);
        $total = $c_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Fetch page
        $order_by = $sort === 'latest' ? 'p.created_at DESC' : 'p.created_at ASC';
        $f_query = "SELECT p.id, p.user_id, p.created_at, p.gender, p.education_level, p.contact_number, p.email as pds_email, p.nickname, u.first_name, u.last_name, u.email, sp.student_id, COALESCE(NULLIF(sp.grade_level, ''), p.grade_level) AS grade_level FROM pds p JOIN users u ON p.user_id = u.id LEFT JOIN student_profiles sp ON u.id = sp.user_id $where ORDER BY $order_by LIMIT $per OFFSET $off";
        $f_stmt = $db->prepare($f_query);
        $f_stmt->execute($p_arr);
        $rows = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['rows' => $rows, 'total' => (int)$total, 'per_page' => (int)$per, 'page' => (int)$pg]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage(), 'rows' => [], 'total' => 0, 'per_page' => 10, 'page' => 1]);
    }
    exit();
}

// Get statistics
$stats = [
    'total' => 0,
    'kinder' => 0,
    'elementary' => 0,
    'junior_high' => 0,
    'senior_high' => 0,
    'college' => 0
];

try {
    $category_counts = $db->query("SELECT $pds_category_sql AS category, COUNT(*) AS total FROM pds p JOIN users u ON p.user_id=u.id LEFT JOIN student_profiles sp ON p.user_id=sp.user_id GROUP BY category")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($category_counts as $category_count) {
        $stats['total'] += (int)$category_count['total'];
        if (in_array($category_count['category'], $pds_category_keys, true)) { $stats[$category_count['category']] = (int)$category_count['total']; }
    }
} catch (Exception $e) {}
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Personal Data Sheets</h1>
        <p class="text-sm text-gray-500">Manage and review student personal data sheets</p>
    </div>

    <!-- Clickable statistics filter the same categories used for these totals. -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4" role="group" aria-label="Filter personal data sheets by education level">
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-blue-500" data-pds-category="" aria-pressed="true" onclick="filterPDSCategory('')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['total'] ?></span><span class="block text-xs text-gray-500">Total Records</span><span class="block text-[10px] text-gray-400">All education levels</span>
        </button>
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-pink-500" data-pds-category="kinder" aria-pressed="false" onclick="filterPDSCategory('kinder')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['kinder'] ?></span><span class="block text-xs text-gray-500">Kinder</span><span class="block text-[10px] text-gray-400">Kindergarten records</span>
        </button>
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-green-500" data-pds-category="elementary" aria-pressed="false" onclick="filterPDSCategory('elementary')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['elementary'] ?></span><span class="block text-xs text-gray-500">Elementary</span><span class="block text-[10px] text-gray-400">Grades 1–6</span>
        </button>
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-yellow-500" data-pds-category="junior_high" aria-pressed="false" onclick="filterPDSCategory('junior_high')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['junior_high'] ?></span><span class="block text-xs text-gray-500">High School</span><span class="block text-[10px] text-gray-400">Grades 7–10</span>
        </button>
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-orange-500" data-pds-category="senior_high" aria-pressed="false" onclick="filterPDSCategory('senior_high')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['senior_high'] ?></span><span class="block text-xs text-gray-500">Senior High</span><span class="block text-[10px] text-gray-400">Grades 11–12</span>
        </button>
        <button type="button" class="pds-summary-card bg-white rounded-xl shadow-sm p-4 text-center border-l-4 border-purple-500" data-pds-category="college" aria-pressed="false" onclick="filterPDSCategory('college')">
            <span class="block text-2xl font-bold text-gray-800"><?= $stats['college'] ?></span><span class="block text-xs text-gray-500">College</span><span class="block text-[10px] text-gray-400">Higher Education</span>
        </button>
    </div>
    <div class="pds-selection-bar"><p id="pdsFilterSelection" role="status" aria-live="polite">Showing: All education levels</p><button type="button" id="resetPDSFilters" onclick="resetPDSFilters()" hidden>Clear all filters</button></div>

    <!-- Search & Filter Options -->
    <details id="pdsFilterOptions" class="pds-filter-options bg-white rounded-xl shadow-sm"><summary><span>Filters<span id="pdsFilterCount"></span></span><small>Search, dates, school year, and sorting</small></summary><div class="pds-filter-body">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 mb-2 md:mb-0">Education level / college year</h3>
        </div>
        <div class="flex flex-wrap gap-2 mb-3" id="educationTabs">
            <button onclick="switchEducationTab('')" id="tab-" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary text-white">All</button>
            <button onclick="switchEducationTab('Kindergarten')" id="tab-Kindergarten" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Kinder</button>
            <button onclick="switchEducationTab('Elementary')" id="tab-Elementary" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Elementary</button>
            <button onclick="switchEducationTab('Junior High')" id="tab-Junior High" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Jr High</button>
            <button onclick="switchEducationTab('Senior High')" id="tab-Senior High" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Sr High</button>
            <button onclick="switchEducationTab('1st Year College')" id="tab-1st Year College" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">1st Year</button>
            <button onclick="switchEducationTab('2nd Year College')" id="tab-2nd Year College" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">2nd Year</button>
            <button onclick="switchEducationTab('3rd Year College')" id="tab-3rd Year College" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">3rd Year</button>
            <button onclick="switchEducationTab('4th Year College')" id="tab-4th Year College" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">4th Year</button>
        </div>
        <div class="pds-filter-fields">
            <div class="pds-search-field"><label for="searchInput">Search students</label>
                <input type="text" id="searchInput" placeholder="Search by name, student ID, or nickname..." class="w-full px-3 py-1.5 border rounded-lg text-xs" onkeyup="debounceSearch()">
            </div>
            <div class="w-36">
                <label for="dateFrom">From date</label><input type="date" id="dateFrom" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="fetchPDSRecords()">
            </div>
            <div class="w-36">
                <label for="dateTo">Through date</label><input type="date" id="dateTo" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="fetchPDSRecords()">
            </div>
            <div class="w-32">
                <label for="sortFilter">Sort order</label><select id="sortFilter" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="fetchPDSRecords()">
                    <option value="latest">Latest First</option>
                    <option value="oldest">Oldest First</option>
                </select>
            </div>
            <div class="w-28">
                <label for="perPageFilter">Rows per page</label><select id="perPageFilter" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="onPerPageChange()">
                    <option value="10">10/page</option>
                    <option value="20">20/page</option>
                    <option value="30">30/page</option>
                    <option value="50">50/page</option>
                </select>
            </div>
        </div>
    </div></details>

    <!-- PDS Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Personal Data Sheets</h3>
            <p class="text-xs text-gray-500" id="recordsInfo">Loading...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">Nickname</th>
                        <th class="px-4 py-3">Gender</th>
                        <th class="px-4 py-3">Education Level</th>
                        <th class="px-4 py-3">Contact Number</th>
                        <th class="px-4 py-3">Grade Level</th>
                        <th class="px-4 py-3">Submitted Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="pdsBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-4 py-3 border-t flex flex-col items-center gap-2">
            <span id="pageInfo" class="text-sm text-gray-500"></span>
            <div class="flex gap-1">
                <button onclick="changePage(-1)" id="prevBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-chevron-left mr-1"></i>Prev</button>
                <span id="pageNums" class="flex gap-1"></span>
                <button onclick="changePage(1)" id="nextBtn" class="px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed">Next<i class="fas fa-chevron-right ml-1"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = 'layout.php?page=admin_view_pds';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;
let currentEducationTab = '';

let currentEducationCategory = '';
let pdsFetchSequence = 0;
const pdsCategoryLabels = {'':'All education levels',kinder:'Kinder',elementary:'Elementary',junior_high:'High School',senior_high:'Senior High',college:'College'};
const pdsBroadLevels = {'Kindergarten':'kinder','Elementary':'elementary','Junior High':'junior_high','Senior High':'senior_high'};

function syncPDSFilterSelection() {
    for (const card of document.querySelectorAll('[data-pds-category]')) {
        card.setAttribute('aria-pressed',String(card.dataset.pdsCategory === currentEducationCategory));
    }
    for (const button of document.querySelectorAll('#educationTabs button')) {
        const level=button.id.slice(4);
        const selected=currentEducationTab ? level===currentEducationTab : level==='' ? !currentEducationCategory : pdsBroadLevels[level]===currentEducationCategory;
        button.classList.toggle('bg-primary',selected);button.classList.toggle('text-white',selected);
        button.classList.toggle('bg-gray-100',!selected);button.classList.toggle('text-gray-700',!selected);
        button.setAttribute('aria-pressed',String(selected));
    }
    const details=[pdsCategoryLabels[currentEducationCategory]];
    let advanced=0;
    if(currentEducationTab){details.push(currentEducationTab);advanced++;}
    const q=document.getElementById('searchInput').value.trim();if(q){details.push('Search: '+q);advanced++;}
    const from=document.getElementById('dateFrom').value,to=document.getElementById('dateTo').value;
    if(from){details.push('From '+from);advanced++;}if(to){details.push('Through '+to);advanced++;}
    if(document.getElementById('sortFilter').value!=='latest'){details.push('Oldest first');advanced++;}
    if(document.getElementById('perPageFilter').value!=='10')advanced++;
    document.getElementById('pdsFilterSelection').textContent='Showing: '+details.join(' · ');
    document.getElementById('pdsFilterCount').textContent=advanced?' ('+advanced+')':'';
    document.getElementById('resetPDSFilters').hidden=!currentEducationCategory&&!advanced;
}

function filterPDSCategory(category) {
    if(!Object.hasOwn(pdsCategoryLabels,category))return;
    currentEducationCategory=category;currentEducationTab='';currentPage=1;
    syncPDSFilterSelection();fetchPDSRecords();
}

function switchEducationTab(level) {
    if(!level){filterPDSCategory('');return;}
    if(pdsBroadLevels[level]){filterPDSCategory(pdsBroadLevels[level]);return;}
    currentEducationTab=level;currentEducationCategory='college';currentPage=1;
    syncPDSFilterSelection();fetchPDSRecords();
}

function resetPDSFilters() {
    for(const id of ['searchInput','dateFrom','dateTo'])document.getElementById(id).value='';
    document.getElementById('sortFilter').value='latest';document.getElementById('perPageFilter').value='10';
    itemsPerPage=10;filterPDSCategory('');
}


function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchPDSRecords(); }, 300);
}

function onPerPageChange() {
    itemsPerPage = parseInt(document.getElementById('perPageFilter').value);
    currentPage = 1;
    fetchPDSRecords();
}

function fetchPDSRecords() {
    const requestId=++pdsFetchSequence;
    syncPDSFilterSelection();
    const q = document.getElementById('searchInput').value;
    const education_level = currentEducationTab;
    const date_from = document.getElementById('dateFrom').value;
    const date_to = document.getElementById('dateTo').value;
    const sort = document.getElementById('sortFilter').value;
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&category=${encodeURIComponent(currentEducationCategory)}&education_level=${encodeURIComponent(education_level)}&date_from=${encodeURIComponent(date_from)}&date_to=${encodeURIComponent(date_to)}&sort=${encodeURIComponent(sort)}`)
    .then(r => { if(!r.ok)throw new Error('Could not load records.');return r.json(); }).then(data => {
        if(requestId!==pdsFetchSequence)return;
        if(data.error)throw new Error(data.error);
        const tbody = document.getElementById('pdsBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No records found</td></tr>';
        } else {
            data.rows.forEach(record => {
                const name = (record.last_name||'') + ', ' + (record.first_name||'');
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.student_id||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.nickname||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.gender||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.education_level||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.contact_number||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(record.grade_level||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">${new Date(record.created_at).toLocaleDateString()}</td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="viewPDS(${record.id})" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200">View</button>
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    }).catch(error=>{
        if(error.name==='AbortError'||requestId!==pdsFetchSequence)return;
        document.getElementById('pdsBody').innerHTML='<tr><td colspan="9" class="p-4 text-center">Could not load records. Select a category to try again.</td></tr>';
        document.getElementById('recordsInfo').textContent='Records could not be loaded.';
    });
}

async function viewPDS(id) {
    try {
        const response=await fetch(`pages/admin/admin_view_pds.php?id=${encodeURIComponent(id)}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});
        if(!response.ok)throw new Error(response.status===403?'You do not have permission to view this personal data sheet.':'The personal data sheet could not be opened.');
        const html=await response.text();const previous=document.getElementById('pdsRecordDialog');if(previous){window.PDSPaper.dispose(previous.querySelector('[data-pds-document]'));window.PortalDialogs.close(previous);previous.remove();}
        const modal=document.createElement('div');modal.id='pdsRecordDialog';modal.className='pd-overlay';modal.setAttribute('data-portal-dialog','');modal.setAttribute('data-managed-dialog','');modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','pdsRecordTitle');
        modal.innerHTML=`<div class="pd-card pd-card-wide pds-paper-modal"><div class="pd-header"><div class="pd-heading-copy"><h2 id="pdsRecordTitle">Personal data sheet</h2><p>Paper form preview · View and print the same document.</p></div><button type="button" class="pd-button" onclick="printPDS(${Number(id)})">Print</button><button type="button" class="pd-close" aria-label="Close personal data sheet" onclick="window.PDSPaper.dispose(this.closest('[data-portal-dialog]').querySelector('[data-pds-document]'));window.PortalDialogs.close(this.closest('[data-portal-dialog]'));this.closest('[data-portal-dialog]').remove()">×</button></div><div class="pd-body pds-paper-modal-body"><div class="pds-document-toolbar"><label>Paper <select data-pds-paper-select aria-label="PDS paper size"><option value="long">Long bond (8.5 × 13 in)</option><option value="a4">A4</option><option value="letter">Letter</option></select></label><button type="button" data-pds-zoom="fit" aria-pressed="true">Fit width</button><button type="button" data-pds-zoom="actual" aria-pressed="false">Actual size</button><span>Missing information stays blank.</span></div><p class="pds-paper-status" data-pds-status role="status"></p><div class="pds-document-viewport">${html}</div></div></div>`;
        document.body.appendChild(modal);window.PortalDialogs.open(modal);modal.querySelector('.pd-close').focus({preventScroll:true});
        await window.PDSPaper.mount(modal.querySelector('[data-pds-document]'),modal);
        return modal;
    }catch(error){officeAlert('Personal data sheet',error.message,'error');return null;}
}
async function printPDS(id) {
    let modal=document.getElementById('pdsRecordDialog');if(!modal||modal.querySelector('[data-pds-document]')?.dataset.pdsId!==String(id))modal=await viewPDS(id);
    if(!modal)return;try{await window.PDSPaper.print(modal.querySelector('[data-pds-document]'));}catch(error){officeAlert('Print personal data sheet',error.message,'error');}
}


function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('recordsInfo').textContent = `${total} records found`;
    document.getElementById('pageInfo').textContent = total === 0 ? 'No records' : `Showing ${start}-${end} of ${total}`;
    document.getElementById('prevBtn').disabled = page <= 1;
    document.getElementById('nextBtn').disabled = page >= totalPages;
    const nums = document.getElementById('pageNums');
    nums.innerHTML = '';
    const maxBtns = 5;
    let sp = Math.max(1, page - Math.floor(maxBtns/2));
    let ep = Math.min(totalPages, sp + maxBtns - 1);
    if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);
    for (let p = sp; p <= ep; p++) {
        const b = document.createElement('button');
        b.textContent = p;
        b.className = p === page ? 'px-2.5 py-1 text-sm rounded-lg bg-primary text-white' : 'px-2.5 py-1 text-sm rounded-lg border hover:bg-gray-50';
        b.onclick = () => { currentPage = p; fetchPDSRecords(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchPDSRecords(); }

// Initial load
fetchPDSRecords();
</script>
