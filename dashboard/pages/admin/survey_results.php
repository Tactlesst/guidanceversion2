<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__ . '/../../../classes/PersonalDataSheet.php';

$pds = new PersonalDataSheet($db);
require_once __DIR__.'/../../../includes/assessment_result_queries.php';

// AJAX endpoint for fetching survey results
if (isset($_GET['action']) && $_GET['action']==='fetch') {
    if(ob_get_level())ob_end_clean();header('Content-Type: application/json');
    try {
        $pg=max(1,(int)($_GET['p']??1));$per=max(1,min(50,(int)($_GET['per']??10)));
        $type=(string)($_GET['type']??'all');$search=trim((string)($_GET['q']??''));
        $year=is_string($_GET['school_year']??null)?$_GET['school_year']:'';[$query,$params]=assessmentResultQuery($type,$search,$year);
        $count=$db->prepare("SELECT COUNT(*) FROM ($query) assessment_rows");$count->execute($params);$total=(int)$count->fetchColumn();
        $pg=min($pg,max(1,(int)ceil($total/$per)));$off=($pg-1)*$per;
        $statement=$db->prepare("SELECT * FROM ($query) assessment_rows ORDER BY sort_date DESC,user_id DESC,mi_id DESC,ls_id DESC LIMIT $per OFFSET $off");
        $statement->execute($params);
        $rows=$statement->fetchAll(PDO::FETCH_ASSOC);[$summary,$summaryParams]=assessmentResultQuery('all',$search,$year);$summaryStmt=$db->prepare("SELECT assessment_type,COUNT(*) AS total FROM ($summary) yearly_summary GROUP BY assessment_type");$summaryStmt->execute($summaryParams);$yearStats=['total'=>0,'multiple_intelligence'=>0,'learning_style'=>0,'learning_style_intermediate'=>0];foreach($summaryStmt->fetchAll(PDO::FETCH_ASSOC) as $summaryRow){$yearStats[$summaryRow['assessment_type']]=(int)$summaryRow['total'];$yearStats['total']+=(int)$summaryRow['total'];}echo json_encode(['rows'=>$rows,'total'=>$total,'per_page'=>$per,'page'=>$pg,'stats'=>$yearStats],JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Exception $e){
        error_log('Survey results: '.$e->getMessage());
        echo json_encode(['error'=>'Assessment results could not be loaded. Check your filters and try again.','rows'=>[],'total'=>0,'per_page'=>10,'page'=>1]);
    }
    exit;
}

require_once __DIR__.'/../../../includes/assessment_profile.php';
if(in_array($_GET['action']??'',['get_details','print_profile'],true)){
    if(ob_get_level())ob_end_clean();header('Cache-Control: private, no-store');
    try{
        $type=is_string($_GET['type']??null)?$_GET['type']:'';$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Choose a valid assessment.');
        $profile=AssessmentProfile::load($db,$type,(int)$id);
        if($_GET['action']==='print_profile'&&$role==='guidance_advocate'){require_once __DIR__.'/../../../classes/ReportApproval.php';(new ReportApproval($db))->consume((int)$uid,(int)($_GET['approval_id']??0),'assessment','print',['type'=>$type,'id'=>(int)$id]);}
        if(function_exists('staffActivityInteraction'))staffActivityInteraction('survey_results',$_GET['action']==='print_profile'?'print_assessment':'view_assessment');
        if($_GET['action']==='print_profile'){
            require_once __DIR__.'/../../../includes/learner_profile_paper.php';
            require_once __DIR__.'/../../../includes/pds_paper.php';
            header('Content-Type: text/html; charset=utf-8');header('X-Frame-Options: SAMEORIGIN');
            echo LearnerProfilePaper::render($profile,AssessmentProfile::learner($db,$profile,($_SESSION['role']??'')==='admin'),PDSPaperDocument::baseUrl());
        }else{header('Content-Type: application/json');echo json_encode($profile,JSON_INVALID_UTF8_SUBSTITUTE);}
    }catch(Throwable $e){
        error_log('Assessment profile: '.$e->getMessage());
        if(($_GET['action']??'')==='print_profile'){http_response_code(404);header('Content-Type: text/html; charset=utf-8');echo '<p>This assessment could not be loaded. Close the print preview and try again.</p>';}
        else{header('Content-Type: application/json');echo json_encode(['error'=>'Assessment not found or unavailable.']);}
    }
    exit;
}

$surveyYears=$db->query('SELECT DISTINCT school_year FROM (SELECT school_year FROM multiple_intelligence_survey UNION ALL SELECT school_year FROM learning_style_inventory UNION ALL SELECT school_year FROM learning_style_inventory_intermediate) recorded_years ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);
$surveySelectedYear=is_string($_GET['school_year']??null)?$_GET['school_year']:'';
// Counts describe the same assessment records returned by each filter.
$stats=['total'=>0,'multiple_intelligence'=>0,'learning_style'=>0,'learning_style_intermediate'=>0];
try {
    [$summaryQuery]=assessmentResultQuery();
    foreach($db->query("SELECT assessment_type,COUNT(*) AS total FROM ($summaryQuery) assessment_rows GROUP BY assessment_type")->fetchAll(PDO::FETCH_ASSOC) as $row){$stats[$row['assessment_type']]=(int)$row['total'];$stats['total']+=(int)$row['total'];}
}catch(Exception $e){error_log('Survey summary: '.$e->getMessage());}
?>

<link rel="stylesheet" href="../assets/css/assessment-profile-viewer.css?v=20261028-1">
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Student Assessment Results</h1>
        <p class="text-sm text-gray-500">Grades 11–12 &amp; College: Multiple Intelligence · Grades 1–3: Learning Style · Grades 4–10: Learning Style (Intermediate)</p>
    </div>

<div class="record-summary-grid" role="group" aria-label="Filter results by assessment type"><button type="button" class="record-summary-card" data-survey-type="all" data-summary-count="<?= (int)$stats['total'] ?>" aria-pressed="true" aria-controls="surveyBody" onclick="selectSurveyType('all')" style="--record-accent:#32699b"><span class="record-summary-number"><?= (int)$stats['total'] ?></span><span class="record-summary-label">Total Assessments</span><span class="record-summary-hint">All completed assessments</span></button>
<button type="button" class="record-summary-card" data-survey-type="multiple_intelligence" data-summary-count="<?= (int)$stats['multiple_intelligence'] ?>" aria-pressed="false" aria-controls="surveyBody" onclick="selectSurveyType('multiple_intelligence')" style="--record-accent:#3176a5"><span class="record-summary-number"><?= (int)$stats['multiple_intelligence'] ?></span><span class="record-summary-label">Multiple Intelligence</span><span class="record-summary-hint">Grades 11–12 &amp; College</span></button>
<button type="button" class="record-summary-card" data-survey-type="learning_style" data-summary-count="<?= (int)$stats['learning_style'] ?>" aria-pressed="false" aria-controls="surveyBody" onclick="selectSurveyType('learning_style')" style="--record-accent:#21866f"><span class="record-summary-number"><?= (int)$stats['learning_style'] ?></span><span class="record-summary-label">Learning Style</span><span class="record-summary-hint">Grades 1–3 inventories</span></button>
<button type="button" class="record-summary-card" data-survey-type="learning_style_intermediate" data-summary-count="<?= (int)$stats['learning_style_intermediate'] ?>" aria-pressed="false" aria-controls="surveyBody" onclick="selectSurveyType('learning_style_intermediate')" style="--record-accent:#8055a5"><span class="record-summary-number"><?= (int)$stats['learning_style_intermediate'] ?></span><span class="record-summary-label">Learning Style (Intermediate)</span><span class="record-summary-hint">Grades 4–10 inventories</span></button></div>
<div class="record-filter-selection"><p id="surveyFilterSelection" role="status">Showing: All assessments</p><button type="button" id="resetSurveyFilters" onclick="resetSurveyFilters()" hidden>Clear all filters</button></div>
<details class="record-filter-options" id="surveyFilterOptions"><summary><span>Filters<span id="surveyFilterCount"></span></span><small>School year, search, assessment type and page size</small></summary><div class="record-filter-body"><div class="record-filter-tabs" id="assessmentTabs" role="group" aria-label="Assessment type">
            <button type="button" onclick="selectSurveyType('all')" id="tab-all" class="px-4 py-2 text-xs font-medium rounded-lg bg-primary text-white cursor-pointer hover:bg-primary/90">All</button>
            <button type="button" onclick="selectSurveyType('multiple_intelligence')" id="tab-multiple_intelligence" class="px-4 py-2 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 cursor-pointer">Multiple Intelligence</button>
            <button type="button" onclick="selectSurveyType('learning_style')" id="tab-learning_style" class="px-4 py-2 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 cursor-pointer">Learning Style</button>
            <button type="button" onclick="selectSurveyType('learning_style_intermediate')" id="tab-learning_style_intermediate" class="px-4 py-2 text-xs font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 cursor-pointer">Learning Style (Intermediate)</button>
        </div><div class="record-filter-fields"><label class="record-filter-search">Search students<input type="search" id="searchInput" placeholder="Search by name, student ID or email" oninput="debounceSearch()"></label><label>School year<select id="surveyYearFilter" onchange="currentPage=1;fetchSurveyResults()"><option value="">All school years</option><?php foreach($surveyYears as $year):$yearValue=$year===''?'legacy':$year;?><option value="<?=htmlspecialchars($yearValue,ENT_QUOTES)?>" <?=$surveySelectedYear===$yearValue?'selected':''?>><?=htmlspecialchars($year?:'Not recorded',ENT_QUOTES)?></option><?php endforeach;?></select></label><label>Rows per page<select id="perPageFilter" class="w-full px-3 py-1.5 border rounded-lg text-xs" onchange="onPerPageChange()">
                    <option value="10">10/page</option>
                    <option value="20">20/page</option>
                    <option value="30">30/page</option>
                    <option value="50">50/page</option>
                </select></label></div></div></details>
    <!-- Assessment Results Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h3 class="text-sm font-semibold text-gray-700">Assessment Results</h3>
            <p class="text-xs text-gray-500" id="recordsInfo">Loading...</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left">
                    <tr>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Student ID</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Grade Level</th>
                        <th class="px-4 py-3">Assessments Completed</th>
                        <th class="px-4 py-3">School Year</th><th class="px-4 py-3">Submission Date</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="surveyBody" class="divide-y divide-gray-100"></tbody>
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

<!-- Consistent profile viewer for standard, intermediate, and MI assessments. -->
<div id="assessmentModal" class="pd-overlay hidden assessment-profile-modal" data-portal-dialog data-managed-dialog data-assessment-type="ls" role="dialog" aria-modal="true" aria-labelledby="modalTitle"><div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i id="assessmentProfileIcon" class="fas fa-graduation-cap"></i></span><div class="pd-heading-copy"><p class="ap-eyebrow">ASSESSMENT RESULTS</p><h2 id="modalTitle">Learning style profile</h2><p>Results from the completed assessment.</p></div><button type="button" class="pd-close" aria-label="Close assessment profile" onclick="closeAssessmentModal()">×</button></header><div class="pd-body" id="modalContent" aria-live="polite"></div><footer class="pd-footer ap-footer"><span id="assessmentPrintStatus" role="status"></span><div><button type="button" class="pd-button" onclick="closeAssessmentModal()">Close</button><button type="button" class="pd-button pd-button-primary" id="assessmentPrintButton" onclick="printAssessment()" disabled><i class="fas fa-print" aria-hidden="true"></i>Print learner’s profile</button></div></footer></div></div>


<script>
const BASE = 'layout.php?page=survey_results';
let currentPage = 1;
let itemsPerPage = 10;
let searchTimer;
let currentTab = 'all';

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

const surveyTypeLabels={all:'All assessments',multiple_intelligence:'Multiple Intelligence',learning_style:'Learning Style',learning_style_intermediate:'Learning Style (Intermediate)'};
let surveyRequestId=0;
function syncSurveyFilters(){
    for(const card of document.querySelectorAll('[data-survey-type]'))card.setAttribute('aria-pressed',String(card.dataset.surveyType===currentTab));
    for(const tab of document.querySelectorAll('#assessmentTabs button')){const selected=tab.id==='tab-'+currentTab;tab.classList.toggle('bg-primary',selected);tab.classList.toggle('text-white',selected);tab.classList.toggle('bg-gray-100',!selected);tab.classList.toggle('text-gray-700',!selected);tab.setAttribute('aria-pressed',String(selected));}
    const details=[surveyTypeLabels[currentTab]];let count=0;const year=document.getElementById('surveyYearFilter').value;if(year){details.push(year==='legacy'?'School year not recorded':year);count++;}const q=document.getElementById('searchInput').value.trim();
    if(q){details.push('Search: '+q);count++;}if(itemsPerPage!==10){details.push(itemsPerPage+' per page');count++;}
    document.getElementById('surveyFilterSelection').textContent='Showing: '+details.join(' · ');
    document.getElementById('surveyFilterCount').textContent=count?' ('+count+')':'';
    document.getElementById('resetSurveyFilters').hidden=currentTab==='all'&&!count;
}
function selectSurveyType(tab){
    if(!Object.hasOwn(surveyTypeLabels,tab))return;
    clearTimeout(searchTimer);currentTab=tab;currentPage=1;fetchSurveyResults();
}
function resetSurveyFilters(){document.getElementById('surveyYearFilter').value='';document.getElementById('searchInput').value='';document.getElementById('perPageFilter').value='10';itemsPerPage=10;selectSurveyType('all');}


function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { currentPage = 1; fetchSurveyResults(); }, 300);
}

function onPerPageChange() {
    itemsPerPage = parseInt(document.getElementById('perPageFilter').value);
    currentPage = 1;
    fetchSurveyResults();
}

function fetchSurveyResults() {
    syncSurveyFilters();
    const requestId=++surveyRequestId;
    const q = document.getElementById('searchInput').value;
    
    fetch(BASE + `&action=fetch&p=${currentPage}&per=${itemsPerPage}&q=${encodeURIComponent(q)}&type=${encodeURIComponent(currentTab)}&school_year=${encodeURIComponent(document.getElementById('surveyYearFilter').value)}`)
    .then(r => {if(!r.ok)throw new Error('Request failed');return r.json();}).then(data => {
        if(requestId!==surveyRequestId)return;
        if(data.error)throw new Error(data.error);if(data.stats)for(const card of document.querySelectorAll('[data-survey-type]')){const key=card.dataset.surveyType==='all'?'total':card.dataset.surveyType;card.querySelector('.record-summary-number').textContent=String(data.stats[key]||0);card.dataset.summaryCount=String(data.stats[key]||0);}
        const tbody = document.getElementById('surveyBody');
        tbody.innerHTML = '';
        
        if (!data.rows || data.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No assessment results found</td></tr>';
        } else {
            data.rows.forEach(result => {
                const name = (result.last_name||'') + ', ' + (result.first_name||'');
                
                // Determine which assessments are completed
                const assessments = [];
                if (result.mi_id) {
                    assessments.push('<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Multiple Intelligence</span>');
                }
                if (result.ls_id) {
                    if (result.ls_level === 'intermediate') {
                        assessments.push('<span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs">Learning Style (Intermediate)</span>');
                    } else {
                        assessments.push('<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Learning Style</span>');
                    }
                }
                
                // Get the latest submission date
                const miDate = result.mi_date ? new Date(result.mi_date) : null;
                const lsDate = result.ls_date ? new Date(result.ls_date) : null;
                let latestDate = null;
                if (miDate && lsDate) {
                    latestDate = miDate > lsDate ? miDate : lsDate;
                } else {
                    latestDate = miDate || lsDate;
                }
                
                tbody.innerHTML += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${esc(name)}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.student_id||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.department||'N/A')}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.grade_level||'N/A')}</td>
                        <td class="px-4 py-3">${assessments.length > 0 ? assessments.join(' ') : '<span class="text-gray-400">No assessments</span>'}</td>
                        <td class="px-4 py-3 text-gray-500">${esc(result.school_year||'Not recorded')}</td><td class="px-4 py-3 text-gray-400 text-xs">${latestDate ? latestDate.toLocaleDateString() : 'N/A'}</td>
                        <td class="px-4 py-3 text-right">
                            ${result.mi_id ? `<button onclick="viewResult('mi', ${result.mi_id})" class="px-2 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 mr-1">MI</button>` : ''}
                            ${result.ls_id ? `<button onclick="viewResult('${result.ls_level==='intermediate'?'ls_intermediate':'ls'}', ${result.ls_id})" class="px-2 py-1 text-sm bg-green-100 text-green-700 rounded hover:bg-green-200">LS</button>` : ''}
                        </td>
                    </tr>
                `;
            });
        }
        
        renderPagination(data.total, data.per_page, data.page);
    }).catch(error=>{
        if(requestId!==surveyRequestId||error.name==='AbortError')return;
        renderPagination(0,itemsPerPage,1);
        document.getElementById('surveyBody').innerHTML='<tr><td colspan="8" class="px-4 py-8 text-center text-red-400">Unable to load assessments. Please try again.</td></tr>';
    });
}

function renderPagination(total, perPage, page) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage + 1;
    const end = Math.min(page * perPage, total);
    document.getElementById('recordsInfo').textContent = total === 0 ? 'No records' : `Showing ${start} to ${end} of ${total} results`;
    document.getElementById('pageInfo').textContent = total === 0 ? 'No records' : `Page ${page} of ${totalPages}`;
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
        b.onclick = () => { currentPage = p; fetchSurveyResults(); };
        nums.appendChild(b);
    }
}

function changePage(delta) { currentPage += delta; fetchSurveyResults(); }

// Initial load
fetchSurveyResults();
</script>

<script src="../assets/js/assessment-profile-viewer.js?v=20261029-annual"></script>

<?php $openKind=is_string($_GET['open_type']??null)?$_GET['open_type']:'';$openId=filter_var($_GET['open_id']??0,FILTER_VALIDATE_INT);if(in_array($openKind,['mi','ls','ls_intermediate'],true)&&$openId>0):?><script>requestAnimationFrame(()=>viewResult(<?=json_encode($openKind)?>,<?=(int)$openId?>));</script><?php endif;?>
