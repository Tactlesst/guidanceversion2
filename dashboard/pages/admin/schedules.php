<?php
if(!defined('IN_LAYOUT')){http_response_code(403);exit;}
require_once __DIR__ . '/../../../classes/Schedule.php';
require_once __DIR__ . '/../../../classes/Holiday.php';
require_once __DIR__ . '/../../../classes/DailyBookingLimit.php';

require_once __DIR__.'/../../../classes/SchoolClosure.php';
$schoolClosure=new SchoolClosure($db);
$_SESSION['school_closure_token']=$_SESSION['school_closure_token']??bin2hex(random_bytes(32));
$schedule = new Schedule($db);
$holiday = new Holiday($db);
$dailyLimit = new DailyBookingLimit($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

require_once __DIR__.'/../../../includes/schedule_planning_rules.php';
require_once __DIR__.'/../../../includes/schedule_booking_overview.php';
$minimum_schedule_date=(new DateTimeImmutable('tomorrow',new DateTimeZone('Asia/Manila')))->format('Y-m-d');
if(isset($_POST['create_schedule'])||isset($_POST['update_schedule'])){
    $planning_error=validateScheduleWrite($_POST,$db);
    if($planning_error){$_SESSION['error_message']=$planning_error;header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit;}
}

if ($_POST) {
    try {
    if (isset($_POST['create_schedule'])) {
        $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->created_by=$_SESSION['user_id']; $schedule->is_active=1;
        if($schedule->create()){if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']="Schedule created!";header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();}
    }
    if (isset($_POST['update_schedule'])) {
        $schedule->id=$_POST['schedule_id']; $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->is_active=1;
        if($schedule->update()){if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']="Schedule updated!";header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();}
    }
    if (isset($_POST['delete_schedule'])) {
        $schedule->id=$_POST['schedule_id'];
        if($schedule->delete()){if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']="Schedule deleted!";header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();}
    }
    if (isset($_POST['set_daily_limit']) || isset($_POST['remove_daily_limit'])) {
        [$booking_service,$limit_date,$limit_value]=validateScheduleBookingLimit($_POST,$minimum_schedule_date);
        $setting_limit=isset($_POST['set_daily_limit']);
        if($booking_service==='counseling'){
            if($setting_limit)$dailyLimit->setDailyLimit($limit_date,$limit_value,$_SESSION['user_id']);
            else $dailyLimit->removeDailyLimit($limit_date);
        }else{
            // Update capacity only: preserve previously configured exam times.
            $limit_stmt=$db->prepare($setting_limit?'INSERT INTO entrance_exam_daily_limits (date,max_appointments,set_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE max_appointments=VALUES(max_appointments),set_by=VALUES(set_by),updated_at=CURRENT_TIMESTAMP':'DELETE FROM entrance_exam_daily_limits WHERE date=?');
            $limit_stmt->execute($setting_limit?[$limit_date,$limit_value,$_SESSION['user_id']]:[$limit_date]);
        }
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']=($booking_service==='counseling'?'Counseling':'Entrance exam').' daily limit '.($setting_limit?'saved.':'removed.');
        $_SESSION['schedule_booking_date']=$limit_date;
        header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();
    }
    if(isset($_POST['create_school_closure'])||isset($_POST['reopen_school_closure'])){
        if(!in_array($_SESSION['role']??'',['admin','guidance_advocate','super_admin'],true))throw new InvalidArgumentException('Only guidance staff can manage school closures.');
        $token=$_POST['school_closure_token']??'';
        if(!is_string($token)||!hash_equals($_SESSION['school_closure_token'],$token))throw new InvalidArgumentException('Your form has expired. Reload the calendar and try again.');
        if(isset($_POST['create_school_closure'])){
            $schoolClosure->create($_POST,(int)$_SESSION['user_id']);
            $_SESSION['schedule_booking_date']=$_POST['closure_start'];
            if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']='School marked closed. New events and bookings are blocked for the selected dates. Existing appointments are kept.';
        }else{
            if(!$schoolClosure->reopen($_POST['closure_id']??'',(int)$_SESSION['user_id']))throw new InvalidArgumentException('This closure has already been reopened or is unavailable.');
            if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']='School closure reopened. Sundays, holidays, and booking limits still apply.';
        }
        header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit;
    }
    if (isset($_POST['create_holiday'])) {
        $holiday->name=$_POST['holiday_name']; $holiday->date=$_POST['holiday_date'];
        $holiday->type=$_POST['holiday_type']??'regular'; $holiday->is_recurring=isset($_POST['is_recurring'])?1:0;
        $holiday->year=date('Y',strtotime($_POST['holiday_date']));
        if($holiday->create()){if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']="Holiday added!";header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();}
    }
    if (isset($_POST['delete_holiday'])) {
        $holiday->delete($_POST['holiday_id']);
        if(function_exists('staffActivitySaved'))staffActivitySaved();$_SESSION['success_message']="Holiday removed!";header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit();
    }
    }catch(Throwable $e){error_log('Schedule action: '.$e->getMessage());$_SESSION['error_message']=$e instanceof InvalidArgumentException?$e->getMessage():'The change could not be saved. Please check the details and try again.';header('Location: '.portalSaveRedirect('layout.php?page=schedules'));exit;}
}

// Calendar navigation
$current_month = date('n'); $current_year = date('Y');
$month = max(1,min(12,isset($_GET['month']) ? (int)$_GET['month'] : $current_month));
$year = max(1900,min(2100,isset($_GET['year']) ? (int)$_GET['year'] : $current_year));
$first_day_ts = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day_ts);
$start_weekday = date('w', $first_day_ts);
$month_name = date('F Y', $first_day_ts);
$prev_month = $month - 1; $prev_year = $year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1; $next_year = $year; if ($next_month > 12) { $next_month = 1; $next_year++; }

// Build events map for the month with role-based filtering
$events_map = [];
$current_user_id = $_SESSION['user_id'] ?? null;
$current_role = $_SESSION['role'] ?? '';

$sched_list = $schedule->getAll();
while($s = $sched_list->fetch(PDO::FETCH_ASSOC)) {
    // Role-based filtering
    $show_schedule = false;
    
    if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'guidance_advocate') {
        // Admins and guidance advocates see all schedules
        $show_schedule = true;
    } elseif ($current_role === 'examinee') {
        // Examinees see only entrance exam schedules
        $show_schedule = ($s['event_type'] === 'entrance_exam');
    } elseif ($current_role === 'student') {
        // Students see PDS periods and general events
        $show_schedule = ($s['event_type'] === 'pds_period' || $s['event_type'] === 'event');
    } elseif ($current_role === 'faculty') {
        // Faculty see all schedules (similar to admins)
        $show_schedule = true;
    }
    
    // Counseling events: only show if created by current user (for all roles except admin)
    if ($s['event_type'] === 'counseling' && $current_role !== 'super_admin' && $current_role !== 'admin' && $current_role !== 'guidance_advocate') {
        $show_schedule = ($s['created_by'] == $current_user_id);
    }
    
    if ($show_schedule) {
        // Get creator name
        $creator_stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $creator_stmt->execute([$s['created_by']]);
        $creator = $creator_stmt->fetch(PDO::FETCH_ASSOC);
        $s['created_by_name'] = $creator['name'] ?? 'Unknown';
        
        // Add event to all dates from start to end
        $start_date = date('Y-m-d', strtotime($s['start_datetime']));
        $end_date = date('Y-m-d', strtotime($s['end_datetime']));
        
        $current = max($start_date,sprintf('%04d-%02d-01',$year,$month));
        $display_end=min($end_date,date('Y-m-t',mktime(0,0,0,$month,1,$year)));
        while ($current <= $display_end) {
            $events_map[$current][] = $s;
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }
    }
}

// Build holiday lookup
$holiday_dates = [];
$start_date = "$year-$month-01"; $end_date = date('Y-m-t', strtotime($start_date));
try { foreach($holiday->getCalendarHolidays($month,$year) as $hr){$holiday_dates[$hr['date']]=['id'=>$hr['id'],'name'=>$hr['name'],'is_recurring'=>$hr['is_recurring']];} } catch (Exception $e) {error_log('Calendar holidays: '.$e->getMessage());}

$school_closure_rules=$schoolClosure->getAll();
$school_closure_dates=$schoolClosure->calendar(date('Y-m-01',$first_day_ts),$end_date);
$school_closures_by_id=[];foreach($school_closure_rules as $entry)$school_closures_by_id[$entry['id']]=$entry;

$schedule_holiday_rules=$db->query('SELECT name,date,is_recurring FROM holidays')->fetchAll(PDO::FETCH_ASSOC);

// Build daily limits map
$limits_map = [];
try { $l_stmt = $db->prepare("SELECT date, max_appointments FROM daily_booking_limits WHERE date BETWEEN ? AND ?"); $l_stmt->execute([$start_date, $end_date]); while ($lr = $l_stmt->fetch(PDO::FETCH_ASSOC)) { $limits_map[$lr['date']] = (int)$lr['max_appointments']; } } catch (Exception $e) {}

$booking_overview=[];
try{$booking_overview=scheduleBookingOverview($db,date('Y-m-01',$first_day_ts),$end_date,$limits_map);}catch(Throwable $e){error_log('Calendar booking overview: '.$e->getMessage());}

// Count each event once, even when it spans several days.
$month_events=[];foreach($events_map as $rows)foreach($rows as $event)$month_events[$event['id']]=$event;
$schedules_this_month=count($month_events);$holidays_this_month=count($holiday_dates);
$exam_limit_dates=array_keys(array_filter($booking_overview,static fn($day)=>$day['entrance_exam']['custom']));
$limits_this_month=count(array_unique(array_merge(array_keys($limits_map),$exam_limit_dates)));

$upcoming_holidays = $holiday->getUpcomingHolidays(10);
$event_colors = ['pds_period'=>'bg-purple-500','entrance_exam'=>'bg-cyan-500','counseling'=>'bg-blue-500','event'=>'bg-green-500','holiday'=>'bg-red-500'];
$event_labels = ['pds_period'=>'PDS Period','entrance_exam'=>'Entrance Exam','counseling'=>'Counselor Availability','event'=>'Event','holiday'=>'Holiday'];
$today_str = date('Y-m-d');

$sch_e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$sch_initial_date=substr($today_str,0,7)===sprintf('%04d-%02d',$year,$month)?$today_str:sprintf('%04d-%02d-01',$year,$month);
$booking_selected_date=$_SESSION['schedule_booking_date']??'';unset($_SESSION['schedule_booking_date']);
if(is_string($booking_selected_date)&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$booking_selected_date)&&isset($booking_overview[$booking_selected_date]))$sch_initial_date=$booking_selected_date;
$sch_agenda=[];
foreach($month_events as $event){$sch_agenda[]=['id'=>$event['id'],'date'=>max(substr($event['start_datetime'],0,10),sprintf('%04d-%02d-01',$year,$month)),'type'=>$event['event_type'],'title'=>$event['title'],'subtitle'=>date('M j, g:i A',strtotime($event['start_datetime'])).' – '.date('M j, g:i A',strtotime($event['end_datetime']))];}
foreach($holiday_dates as $date=>$entry)$sch_agenda[]=['date'=>$date,'type'=>'holiday','title'=>$entry['name'],'subtitle'=>'Holiday · bookings closed'];
foreach($school_closure_dates as $date=>$entry)$sch_agenda[]=['id'=>$entry['id'],'date'=>$date,'type'=>'school_closure','title'=>'School closed','subtitle'=>$entry['reason']];
foreach($limits_map as $date=>$limit)$sch_agenda[]=['date'=>$date,'type'=>'daily_limit','title'=>'Counseling daily limit','subtitle'=>$limit.' appointments'];
foreach($booking_overview as $date=>$day){
    if($day['entrance_exam']['custom'])$sch_agenda[]=['date'=>$date,'type'=>'daily_limit','title'=>'Entrance exam daily limit','subtitle'=>$day['entrance_exam']['limit'].' appointments'];
    if($day['counseling']['booked']+$day['entrance_exam']['booked'])$sch_agenda[]=['date'=>$date,'type'=>'daily_booking','title'=>'Daily bookings','subtitle'=>$day['counseling']['booked'].' counseling · '.$day['entrance_exam']['booked'].' entrance exam'];
}
usort($sch_agenda,static fn($a,$b)=>strcmp($a['date'],$b['date'])?:strcmp($a['title'],$b['title']));

// Handle view_schedule parameter for auto-opening view modal
$auto_view_schedule = null;
if (isset($_GET['view_schedule'])) {
    try {
        $auto_stmt = $db->prepare("SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name FROM schedules s LEFT JOIN users u ON s.created_by = u.id WHERE s.id = ?");
        $auto_stmt->execute([$_GET['view_schedule']]);
        $auto_view_schedule = $auto_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $auto_view_schedule = null; }
}
?>

<link rel="stylesheet" href="../assets/css/schedules-workspace.css?v=20261028-8">
<link rel="stylesheet" href="../assets/css/calendar-focus.css?v=20261028-1">
<link rel="stylesheet" href="../assets/css/school-closures.css?v=20261028-1">
<div class="sch-workspace" id="scheduleWorkspace" data-view="calendar" data-selected-date="<?=$sch_initial_date?>">
    <div class="flex items-center justify-between"><h1>Schedule Management</h1><div class="sch-header-actions"><button type="button" class="sch-primary" onclick="openEventTypeSelection('<?=max($sch_initial_date,$minimum_schedule_date)?>')">＋ Add event</button><button type="button" onclick="openScheduleBookings()">Daily bookings</button><details class="sch-tools"><summary>Booking tools</summary><div><button type="button" onclick="openSchoolClosure()">Mark school closed</button><button type="button" onclick="openScheduleBookings()">Daily bookings &amp; limits</button><button type="button" onclick="openModal('holidayModal')">Add holiday</button><button type="button" onclick="openAllSchedules()">All schedules</button></div></details></div></div>
    <?php if($success_message||$error_message):?><script type="application/json" data-portal-feedback><?=json_encode(['success'=>$success_message,'error'=>$error_message],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script><?php endif;?>
    <div class="sch-summary"><div><span class="sch-stat-symbol" data-tone="events">▦</span><div><strong><?=$schedules_this_month?></strong><span>Events this month</span><small>Each event counted once</small></div></div><div><span class="sch-stat-symbol" data-tone="holiday">☼</span><div><strong><?=$holidays_this_month?></strong><span>Holiday dates</span><small>Booking closures</small></div></div><div><span class="sch-stat-symbol" data-tone="limit">◷</span><div><strong><?=$limits_this_month?></strong><span>Days with custom limits</span><small>Daily booking capacity</small></div></div></div>
    <details class="sch-filters"><summary><span>Filters <span id="schFilterCount"></span></span><small>Search and event category</small></summary><div class="sch-filter-fields"><label>Search events<input type="search" id="schSearch" placeholder="Search by title or category"></label><label>Category<select id="schCategory"><option value="all">All categories</option><?php foreach($event_labels as $type=>$label):?><option value="<?=$sch_e($type)?>"><?=$sch_e($label)?></option><?php endforeach;?><option value="daily_limit">Daily booking limits</option><option value="daily_booking">Daily bookings</option><option value="school_closure">School closed</option></select></label><button type="button" id="schResetFilters">Clear filters</button></div></details>
    <div class="sch-selection"><p id="schFilterSelection" role="status">Showing all categories for <?=$sch_e($month_name)?></p><span>Planning starts tomorrow. Sundays and holidays are closed for events.</span></div>
    <div class="sch-columns"><section class="sch-calendar-card" aria-label="Monthly schedule"><header class="sch-month-header"><div><p class="sch-eyebrow">OFFICE CALENDAR</p><h2><?=$sch_e($month_name)?></h2><?php if(!$schedules_this_month):?><p class="sch-month-note">No events scheduled this month.</p><?php endif;?></div><div class="sch-month-actions"><nav aria-label="Choose calendar month"><a href="layout.php?page=schedules&amp;month=<?=$prev_month?>&amp;year=<?=$prev_year?>" aria-label="Previous month">‹</a><a href="layout.php?page=schedules" class="sch-today">Today</a><a href="layout.php?page=schedules&amp;month=<?=$next_month?>&amp;year=<?=$next_year?>" aria-label="Next month">›</a></nav><div class="sch-view-switch" role="group" aria-label="Schedule view"><button type="button" data-schedule-view="calendar" aria-pressed="true" aria-controls="schCalendar">Calendar</button><button type="button" data-schedule-view="agenda" aria-pressed="false" aria-controls="schAgenda">List</button></div></div></header>
    <div class="sch-calendar-scroll" id="schCalendar"><div class="sch-calendar"><div class="sch-weekdays" aria-hidden="true"><?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day_name):?><span><?=$day_name?></span><?php endforeach;?></div><div class="sch-calendar-days">
    <?php for($index=0;$index<$start_weekday;$index++):?><div class="sch-day sch-day-empty" aria-hidden="true"></div><?php endfor;?>
    <?php for($day=1;$day<=$days_in_month;$day++):$ds=sprintf('%04d-%02d-%02d',$year,$month,$day);$dayEvents=$events_map[$ds]??[];$holidayEntry=$holiday_dates[$ds]??null;$dayBookings=$booking_overview[$ds]??null;$bookedCount=$dayBookings?$dayBookings['counseling']['booked']+$dayBookings['entrance_exam']['booked']:0;$isToday=$ds===$today_str;$schoolClosureEntry=$school_closure_dates[$ds]??null;$isClosed=date('w',strtotime($ds))==='0'||$holidayEntry||$schoolClosureEntry;?>
    <div class="sch-day <?=$schoolClosureEntry?'sch-school-is-closed':''?> <?=$isClosed?'sch-is-closed':''?> <?=$isToday?'sch-is-today':''?> <?=$ds<=$today_str?'sch-is-past':''?>" data-calendar-date="<?=$ds?>"><button type="button" class="sch-day-number" data-date-selector="1" data-select-date="<?=$ds?>" aria-label="<?=date('F j, Y',strtotime($ds))?>, <?=count($dayEvents)?> events<?=$isClosed?', closed for new events':''?>" <?=$isToday?'aria-current="date"':''?>><span><?=$day?></span><?php if($isToday):?><small>Today</small><?php elseif($isClosed):?><small class="sch-closed-label">Closed</small><?php endif;?></button><div class="sch-day-items">
    <?php if($schoolClosureEntry):?><button type="button" class="sch-event" data-entry-type="school_closure" data-closure-id="<?=(int)$schoolClosureEntry['id']?>" title="School closed: <?=$sch_e($schoolClosureEntry['reason'])?>">School closed</button><?php endif;?>
    <?php if($holidayEntry):?><button type="button" class="sch-event" data-entry-type="holiday" data-holiday-date="<?=$ds?>" title="<?=$sch_e($holidayEntry['name'])?>"><?=$sch_e($holidayEntry['name'])?></button><?php endif;?>
    <?php foreach(array_slice($dayEvents,0,2) as $event):?><button type="button" class="sch-event" data-entry-type="<?=$sch_e($event['event_type'])?>" data-schedule-id="<?=(int)$event['id']?>" title="<?=$sch_e($event['title'])?>"><?=$sch_e($event['title'])?></button><?php endforeach;?>
    <?php if(count($dayEvents)>2):?><button type="button" class="sch-more" data-select-date="<?=$ds?>">+<?=count($dayEvents)-2?> more</button><?php endif;?>
    <?php if($bookedCount):?><button type="button" class="sch-booking-badge" data-entry-type="daily_booking" data-booking-date="<?=$ds?>" data-entry-title="<?=$dayBookings['counseling']['booked']?> counseling · <?=$dayBookings['entrance_exam']['booked']?> entrance exam" aria-label="<?=date('F j, Y',strtotime($ds))?>: <?=$bookedCount?> <?=$bookedCount===1?'booking':'bookings'?>. View daily bookings and limits."><?=$bookedCount?> booked</button><?php endif;?>
    <?php if($dayBookings && $dayBookings['entrance_exam']['custom']):?><span class="sch-limit" data-entry-type="daily_limit">Exam limit: <?=$dayBookings['entrance_exam']['limit']?></span><?php endif;?>
    <?php if(isset($limits_map[$ds])):?><span class="sch-limit" data-entry-type="daily_limit">Counseling limit: <?=(int)$limits_map[$ds]?></span><?php endif;?></div></div><?php endfor;?>
    <?php for($index=($start_weekday+$days_in_month)%7;$index>0&&$index<7;$index++):?><div class="sch-day sch-day-empty" aria-hidden="true"></div><?php endfor;?>
    </div></div></div>
    <div class="sch-agenda" id="schAgenda" aria-label="Events in this month"><div class="sch-agenda-list"><?php foreach($sch_agenda as $entry):?><button type="button" class="sch-agenda-row" data-entry-type="<?=$sch_e($entry['type'])?>" data-entry-title="<?=$sch_e(mb_strtolower($entry['title']))?>" <?php if($entry['type']==='school_closure'):?>data-closure-id="<?=(int)$entry['id']?>"<?php elseif($entry['type']==='holiday'):?>data-holiday-date="<?=$entry['date']?>"<?php elseif(in_array($entry['type'],['daily_limit','daily_booking'],true)):?>data-booking-date="<?=$entry['date']?>"<?php else:?>data-schedule-id="<?=(int)$entry['id']?>"<?php endif;?>><span class="sch-agenda-date"><strong><?=date('d',strtotime($entry['date']))?></strong><small><?=date('M',strtotime($entry['date']))?></small></span><span class="sch-agenda-copy"><strong><?=$sch_e($entry['title'])?></strong><small><?=$sch_e($entry['subtitle'])?></small></span><span class="sch-type-label"><?=$sch_e($entry['type']==='daily_limit'?'Daily limit':($entry['type']==='daily_booking'?'Bookings':($entry['type']==='school_closure'?'School closed':($event_labels[$entry['type']]??'Event'))))?></span><span aria-hidden="true">›</span></button><?php endforeach;?></div><p class="sch-empty" id="schAgendaEmpty" <?=count($sch_agenda)?'hidden':''?>>No matching entries in this month.</p></div>
    <footer class="sch-calendar-footer"><div class="sch-calendar-guide" aria-label="Calendar legend"><span><i class="sch-key sch-key-today" aria-hidden="true"></i> Today</span><span><i class="sch-key sch-key-selected" aria-hidden="true"></i> Selected</span><span><i class="sch-key" aria-hidden="true"></i> Closed</span></div><span class="sch-calendar-tip">Select a date for full event and booking details. Colored marks indicate entries.</span></footer></section>
    <aside class="sch-day-panel"><div class="sch-day-panel-heading"><p class="sch-eyebrow">SELECTED DAY</p><h3 id="schSelectedHeading"><?=date('l, F j',strtotime($sch_initial_date))?></h3></div><p class="sch-closed-note" id="schClosedDayNote" role="status" hidden></p><div id="schSelectedEntries" aria-live="polite"></div><section class="sch-day-bookings" aria-label="Daily bookings for selected date"><h4>Daily bookings</h4><div id="schSelectedBookings" class="sch-booking-cards" aria-live="polite"></div><button type="button" class="sch-booking-open" id="schSelectedBookingButton">View bookings &amp; limits</button></section><button type="button" class="sch-primary sch-add-selected" id="schAddSelected">＋ Add event on this date</button><button type="button" class="sch-mark-closed" id="schMarkSelectedClosed">Mark school closed</button><details class="sch-help"><summary>Calendar guide</summary><p>Select an event to view or edit it. Use booking tools to manage holidays and daily limits. Switch to List for longer event titles and a simpler mobile view.</p></details></aside></div>
</div>
<script type="application/json" id="schWorkspaceData"><?=json_encode(['events'=>$events_map,'holidays'=>$holiday_dates,'limits'=>$limits_map,'bookings'=>$booking_overview,'closures'=>$school_closure_dates,'closuresById'=>$school_closures_by_id,'month'=>$month_name,'today'=>$today_str,'labels'=>array_merge($event_labels,['daily_booking'=>'Daily bookings','daily_limit'=>'Daily booking limits','school_closure'=>'School closed'])],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?></script>


<!-- Browse all dates without leaving the calendar. -->
<div id="allSchedulesModal" class="pd-overlay hidden sch-all-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="schAllTitle" aria-describedby="schAllDescription">
    <div class="pd-card pd-card-wide">
        <header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-calendar-days"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">SCHEDULE DIRECTORY</p><h2 id="schAllTitle">All schedules</h2><p id="schAllDescription">Find activities across all dates, then view or manage their details.</p></div><button type="button" class="pd-close" aria-label="Close all schedules" data-dialog-close="allSchedulesModal">×</button></header>
        <div class="pd-body sal-body">
            <div class="sal-toolbar"><div class="pd-field sal-search"><label for="schAllSearch">Search schedules</label><div><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input id="schAllSearch" type="search" placeholder="Search by title or event type" autocomplete="off"></div></div><span class="sal-total" id="schAllTotal"></span></div>
            <details class="sal-filters"><summary><span><i class="fas fa-sliders" aria-hidden="true"></i> Filter schedules <span id="schAllFilterCount"></span></span><span aria-hidden="true">⌄</span></summary><div class="sal-filter-fields"><div class="pd-field"><label for="schAllType">Event type</label><select id="schAllType"><option value="all">All types</option><option value="event">Event</option><option value="pds_period">PDS period</option><option value="entrance_exam">Entrance exam</option><option value="counseling">Counselor availability</option></select></div><div class="pd-field"><label for="schAllPeriod">Dates</label><select id="schAllPeriod"><option value="all">All dates</option><option value="upcoming">Today &amp; upcoming</option><option value="past">Past schedules</option></select></div><button type="button" id="schAllReset">Clear filters</button></div></details>
            <div class="sal-table-wrap"><table class="sal-table" data-cw-no-cards><caption class="sal-sr-only">All guidance office schedules, newest start date first</caption><thead><tr><th scope="col">Schedule</th><th scope="col">Type</th><th scope="col">Date &amp; time</th><th scope="col">Actions</th></tr></thead><tbody id="allSchedulesTableBody"></tbody></table></div>
            <div id="schAllEmpty" class="sal-empty" hidden><i class="fas fa-calendar" aria-hidden="true"></i><h3>No schedules found</h3><p>Try a different title or clear the filters.</p><button type="button" onclick="resetAllScheduleFilters()">Clear search &amp; filters</button></div>
        </div>
        <footer class="pd-footer sal-footer" id="paginationControls"><p id="paginationInfo" role="status" aria-live="polite"></p><nav id="paginationButtons" aria-label="Schedule pages"></nav></footer>
    </div>
</div>


<!-- Day Events Modal -->
<div id="dayEventsModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-calendar-day mr-2"></i>Events for <span id="dayEventsDate"></span></h3>
            <button onclick="closeModal('dayEventsModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="dayEventsList" class="p-4 space-y-2">
            <!-- Events will be populated here -->
        </div>
    </div>
</div>

<!-- Event Type Selection Modal -->
<div id="eventTypeSelectionModal" class="pd-overlay hidden" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="scheduleTypeTitle">
    <div class="pd-card pd-card-wide"><header class="pd-header"><div class="pd-heading-copy"><h2 id="scheduleTypeTitle">What would you like to schedule?</h2><p>Choose a type, then enter the event details.</p></div><button type="button" class="pd-close" aria-label="Close event type selector" onclick="window.PortalDialogs.close(document.getElementById('eventTypeSelectionModal'))">×</button></header>
    <div class="pd-body"><input type="hidden" id="selected_date"><p class="sch-selector-date">Selected date <strong id="selected_date_display"></strong></p><div class="sch-type-options">
    <button type="button" class="sch-type-option" data-type="event" onclick="selectEventType('event')"><span class="sch-type-icon" aria-hidden="true"><i class="fas fa-calendar"></i></span><span><strong>Event</strong><small>School activities and general events</small></span><span aria-hidden="true">›</span></button>
    <button type="button" class="sch-type-option" data-type="pds_period" onclick="selectEventType('pds_period')"><span class="sch-type-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span><span><strong>PDS period</strong><small>Personal Data Sheet submission dates</small></span><span aria-hidden="true">›</span></button>
    <button type="button" class="sch-type-option" data-type="entrance_exam" onclick="selectEventType('entrance_exam')"><span class="sch-type-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span><span><strong>Entrance exam</strong><small>Entrance examination schedules</small></span><span aria-hidden="true">›</span></button>
    <button type="button" class="sch-type-option" data-type="counseling" onclick="selectEventType('counseling')"><span class="sch-type-icon" aria-hidden="true"><i class="fas fa-user-clock"></i></span><span><strong>Counselor availability</strong><small>Available counseling time blocks</small></span><span aria-hidden="true">›</span></button>
    <button type="button" class="sch-type-option" data-type="school_closure" onclick="openSchoolClosure(document.getElementById('selected_date').value)"><span class="sch-type-icon" aria-hidden="true"><i class="fas fa-school-lock"></i></span><span><strong>School closed</strong><small>Block new events and appointment bookings for these dates</small></span><span aria-hidden="true">›</span></button>
    </div></div></div>
</div>

<!-- Create Schedule Modal -->
<div id="createScheduleModal" class="pd-overlay hidden sch-create-modal" data-portal-dialog data-managed-dialog data-event-type="event" role="dialog" aria-modal="true" aria-labelledby="createScheduleTitle" aria-describedby="createScheduleIntro">
    <div class="pd-card sch-create-card">
        <header id="createScheduleModalHeader" class="pd-header"><div class="pd-heading-copy"><span class="sce-eyebrow">NEW SCHEDULE</span><h2 id="createScheduleTitle">Create event</h2><p id="createScheduleIntro">Plan the activity and set its start and end.</p></div><button type="button" class="pd-close" aria-label="Close create event" onclick="window.PortalDialogs.close(document.getElementById('createScheduleModal'))">×</button></header>
        <form data-portal-save method="POST" id="createScheduleForm">
            <input type="hidden" name="create_schedule" value="1">
            <div class="pd-body sce-body">
                <div class="sce-type"><span class="sce-type-icon" aria-hidden="true"><i id="create_event_type_icon" class="fas fa-calendar"></i></span><div><label for="create_event_type">Event type</label><select name="event_type" id="create_event_type" required><option value="event">Event</option><option value="pds_period">PDS period</option><option value="entrance_exam">Entrance exam</option><option value="counseling">Counselor availability</option></select><small id="create_event_type_label">School activities and general events</small></div></div>
                <div class="pd-field sce-title-field"><label for="create_title">Event title <span class="sce-required" aria-hidden="true">*</span></label><input type="text" name="title" id="create_title" maxlength="200" required placeholder="e.g. Grade 11 guidance orientation" autocomplete="off"></div>
                <div class="sce-timing-heading"><h3>When is it happening?</h3><p>From tomorrow onward · No Sundays or holidays</p></div>
                <div class="sce-date-cards">
                    <fieldset class="sce-date-card"><legend><span class="sce-date-dot" aria-hidden="true"></span>Starts</legend><div class="sce-date-fields"><div class="pd-field"><label for="create_start_date">Start date <span class="sce-required" aria-hidden="true">*</span></label><input type="date" name="start_date" id="create_start_date" min="<?=$minimum_schedule_date?>" required></div><div class="pd-field"><label for="create_start_time">Start time <span class="sce-required" aria-hidden="true">*</span></label><input type="time" name="start_time" id="create_start_time" value="08:00" required></div></div></fieldset>
                    <fieldset class="sce-date-card"><legend><span class="sce-date-dot sce-date-dot-end" aria-hidden="true"></span>Ends</legend><div class="sce-date-fields"><div class="pd-field"><label for="create_end_date">End date <span class="sce-required" aria-hidden="true">*</span></label><input type="date" name="end_date" id="create_end_date" min="<?=$minimum_schedule_date?>" required></div><div class="pd-field"><label for="create_end_time">End time <span class="sce-required" aria-hidden="true">*</span></label><input type="time" name="end_time" id="create_end_time" value="17:00" required></div></div></fieldset>
                </div>
                <p class="sce-timing-error" id="create_schedule_timing_error" role="status" hidden></p>
                <details class="sce-description"><summary><span><i class="fas fa-align-left" aria-hidden="true"></i> Add a description</span><small>Optional</small></summary><div class="pd-field"><label for="create_description">Description</label><textarea name="description" id="create_description" rows="3" placeholder="Add context or instructions for this schedule."></textarea></div></details>
                <p class="sce-required-note"><span aria-hidden="true">*</span> Required fields</p>
            </div>
            <footer class="pd-footer sce-footer"><div class="sce-range"><span class="sce-range-label">SCHEDULE</span><span id="create_schedule_range" role="status">Select the dates and times.</span></div><div class="sce-actions"><button type="button" class="pd-button" onclick="window.PortalDialogs.close(document.getElementById('createScheduleModal'))">Cancel</button><button type="submit" class="pd-button pd-button-primary" id="create_schedule_submit"><i class="fas fa-plus" aria-hidden="true"></i><span>Create event</span></button></div></footer>
        </form>
    </div>
</div>


<!-- Schedule details use the same dialog shell as the calendar editors. -->
<div id="viewScheduleModal" class="pd-overlay hidden sch-detail-modal" data-portal-dialog data-managed-dialog data-event-type="event" role="dialog" aria-modal="true" aria-labelledby="view_title" aria-describedby="viewScheduleSubtitle">
    <div class="pd-card">
        <header class="pd-header" id="viewScheduleModalHeader"><span class="pd-heading-icon" aria-hidden="true"><i id="view_schedule_icon" class="fas fa-calendar"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">SCHEDULE DETAILS</p><h2 id="view_title"></h2><p id="viewScheduleSubtitle">Review the activity and its scheduled dates.</p></div><button type="button" class="pd-close" aria-label="Close schedule details" data-dialog-close="viewScheduleModal">×</button></header>
        <div class="pd-body svd-body"><input type="hidden" id="view_schedule_id">
            <div class="svd-badges"><span id="view_event_type" class="svd-type"></span><span id="view_schedule_status" class="svd-status"></span><span id="view_schedule_span" class="svd-span"></span></div>
            <section aria-label="Schedule dates"><div class="svd-section-heading"><h3>When it's happening</h3><span>Philippine time</span></div><div class="svd-dates"><div class="svd-date-card"><p><span class="svd-dot" aria-hidden="true"></span>Starts</p><time id="view_start_datetime"></time><strong id="view_start_time"></strong></div><div class="svd-date-card"><p><span class="svd-dot svd-dot-end" aria-hidden="true"></span>Ends</p><time id="view_end_datetime"></time><strong id="view_end_time"></strong></div></div></section>
            <section class="svd-description"><h3>Description</h3><p id="view_description"></p></section>
            <div class="svd-creator"><span id="view_creator_initials" aria-hidden="true"></span><div><small>Created by</small><strong id="view_created_by"></strong></div></div>
        </div>
        <form data-portal-save id="deleteScheduleForm" method="POST" hidden><input type="hidden" name="delete_schedule" value="1"><input type="hidden" id="delete_schedule_id" name="schedule_id"></form>
        <footer class="pd-footer svd-footer"><button type="button" class="pd-button svd-delete" onclick="deleteScheduleFromView(document.getElementById('view_schedule_id').value)"><i class="fas fa-trash-can" aria-hidden="true"></i>Delete</button><div><button type="button" class="pd-button" data-dialog-close="viewScheduleModal">Close</button><button id="viewEditBtn" type="button" class="pd-button pd-button-primary" onclick="editSchedule(currentScheduleData)"><i class="fas fa-pen" aria-hidden="true"></i>Edit schedule</button></div></footer>
    </div>
</div>


<!-- View Holiday Modal -->
<div id="viewHolidayModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="bg-red-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-umbrella-beach mr-2"></i>View Holiday</h3>
            <button onclick="closeModal('viewHolidayModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="view_holiday_id">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Holiday Name</label>
                    <div id="view_holiday_name" class="text-lg font-semibold text-gray-800"></div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Date</label>
                    <div id="view_holiday_date" class="text-sm text-gray-700"></div>
                </div>
            </div>
            <form data-portal-save id="deleteHolidayForm" method="POST" class="hidden">
                <input type="hidden" name="delete_holiday" value="1">
                <input type="hidden" id="delete_holiday_id" name="holiday_id">
            </form>
            <div class="flex justify-end gap-3 pt-4 border-t">
                <button type="button" onclick="closeModal('viewHolidayModal')" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50">Close</button>
                <button type="button" onclick="deleteHolidayFromView(document.getElementById('view_holiday_id').value)" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit an existing calendar entry without leaving the workspace. -->
<div id="editScheduleModal" class="pd-overlay hidden sch-edit-modal" data-portal-dialog data-managed-dialog data-event-type="event" role="dialog" aria-modal="true" aria-labelledby="editScheduleTitle" aria-describedby="editScheduleSubtitle">
    <div class="pd-card">
        <header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-pen"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">UPDATE SCHEDULE</p><h2 id="editScheduleTitle">Edit schedule</h2><p id="editScheduleSubtitle">Review the event details before saving your changes.</p></div><button type="button" class="pd-close" aria-label="Close edit schedule" data-dialog-close="editScheduleModal">×</button></header>
        <form data-portal-save method="POST" id="editScheduleForm"><input type="hidden" name="update_schedule" value="1"><input type="hidden" name="schedule_id" id="edit_schedule_id">
            <div class="pd-body sed-body">
                <div class="sed-type"><span class="pd-heading-icon" aria-hidden="true"><i id="edit_type_icon" class="fas fa-calendar"></i></span><div class="pd-field"><label for="edit_event_type">Event type <span aria-hidden="true">*</span></label><select name="event_type" id="edit_event_type" required><option value="event">Event</option><option value="pds_period">PDS period</option><option value="entrance_exam">Entrance exam</option><option value="counseling">Counselor availability</option></select><small id="edit_type_hint"></small></div></div>
                <div class="pd-field sed-title"><label for="edit_title">Event title <span aria-hidden="true">*</span></label><input type="text" name="title" id="edit_title" maxlength="200" required autocomplete="off"></div>
                <div class="svd-section-heading sed-heading"><h3>When is it happening?</h3><span>Philippine time</span></div><p class="sed-planning-rule">From tomorrow onward. Sundays and holidays are closed for events.</p>
                <div class="sed-dates"><fieldset><legend><span class="svd-dot" aria-hidden="true"></span>Starts</legend><div class="sed-date-fields"><div class="pd-field"><label for="edit_start_date">Start date <span aria-hidden="true">*</span></label><input type="date" name="start_date" id="edit_start_date" min="<?=$minimum_schedule_date?>" required></div><div class="pd-field"><label for="edit_start_time">Start time <span aria-hidden="true">*</span></label><input type="time" name="start_time" id="edit_start_time" required></div></div></fieldset><fieldset><legend><span class="svd-dot svd-dot-end" aria-hidden="true"></span>Ends</legend><div class="sed-date-fields"><div class="pd-field"><label for="edit_end_date">End date <span aria-hidden="true">*</span></label><input type="date" name="end_date" id="edit_end_date" min="<?=$minimum_schedule_date?>" required></div><div class="pd-field"><label for="edit_end_time">End time <span aria-hidden="true">*</span></label><input type="time" name="end_time" id="edit_end_time" required></div></div></fieldset></div>
                <p id="edit_schedule_closure_error" class="sch-closed-note sed-error" role="status" hidden></p>
                <details class="sed-description" id="edit_description_details"><summary><span><i class="fas fa-align-left" aria-hidden="true"></i> Description</span><small>Optional</small></summary><div class="pd-field"><label for="edit_description">Description</label><textarea name="description" id="edit_description" rows="3" placeholder="Add context or instructions for this schedule."></textarea></div></details>
                <p class="sed-required"><span aria-hidden="true">*</span> Required fields</p>
            </div>
            <footer class="pd-footer sed-footer"><button type="button" class="pd-button" data-dialog-close="editScheduleModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-check" aria-hidden="true"></i>Save changes</button></footer>
        </form>
    </div>
</div>


<!-- Daily bookings and capacity: viewing is available for every calendar date. -->
<div id="dailyLimitModal" class="pd-overlay hidden sch-bookings-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="schBookingsTitle" aria-describedby="schBookingsDescription">
    <div class="pd-card">
        <header class="pd-header"><div><p class="sch-eyebrow">BOOKING CAPACITY</p><h2 id="schBookingsTitle">Daily bookings &amp; limits</h2><p id="schBookingsDescription">Review bookings and plan each service's daily capacity.</p></div><button type="button" class="pd-close" aria-label="Close daily bookings" onclick="window.PortalDialogs.close(document.getElementById('dailyLimitModal'))">×</button></header>
        <div class="pd-body">
            <div class="pd-field"><label for="schBookingDate">Booking date</label><input type="date" id="schBookingDate" min="<?=sprintf('%04d-%02d-01',$year,$month)?>" max="<?=date('Y-m-t',$first_day_ts)?>" value="<?=$sch_initial_date?>" required><small>Showing dates in <?=$sch_e($month_name)?>. Use the calendar arrows to change month.</small></div>
            <div id="schModalBookings" class="sch-booking-cards" aria-live="polite"></div>
            <p id="schBookingClosure" class="sch-booking-notice" hidden></p>
            <p class="sch-booking-footnote">Cancelled appointments are excluded. Capacity counts follow each service's booking rules; available times also depend on the schedule.</p>
            <form data-portal-save method="POST" id="schBookingLimitForm" class="sch-booking-editor">
                <input type="hidden" name="set_daily_limit" value="1"><input type="hidden" name="limit_date" id="schLimitDate" value="<?=$sch_initial_date?>">
                <h3>Set daily limit</h3>
                <div class="sch-booking-fields"><div class="pd-field"><label for="schLimitService">Service</label><select name="booking_service" id="schLimitService"><option value="counseling">Counseling</option><option value="entrance_exam">Entrance exam</option></select></div><div class="pd-field"><label for="schLimitValue">Maximum appointments</label><input type="number" name="max_appointments" id="schLimitValue" min="1" max="20" value="4" required><small id="schLimitHelp">Counseling allows 1–20 appointments.</small></div></div>
                <p id="schLimitReadOnly" class="sch-booking-notice" hidden>Today and past dates are for viewing. Choose tomorrow or later to change a limit.</p>
                <button type="submit" class="pd-button pd-button-primary" id="schLimitSave">Save daily limit</button>
            </form>
            <form data-portal-save method="POST" id="schBookingRemoveForm" class="sch-booking-remove"><input type="hidden" name="remove_daily_limit" value="1"><input type="hidden" name="limit_date" id="schRemoveLimitDate"><input type="hidden" name="booking_service" id="schRemoveLimitService"><p id="schRemoveLimitHint"></p><button type="submit" class="pd-button" id="schLimitRemove">Remove custom limit</button></form>
        </div>
        <footer class="pd-footer"><button type="button" class="pd-button" onclick="window.PortalDialogs.close(document.getElementById('dailyLimitModal'))">Close</button></footer>
    </div>
</div>


<!-- School closure dates block new events and appointments without removing existing records. -->
<div id="schoolClosureModal" class="pd-overlay hidden sch-school-closure" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="schoolClosureTitle">
    <div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-school-lock"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">SCHOOL AVAILABILITY</p><h2 id="schoolClosureTitle">Mark school closed</h2><p>Close a date or date range for new events and appointments.</p></div><button type="button" class="pd-close" aria-label="Close school closure form" data-dialog-close="schoolClosureModal">×</button></header>
    <form data-portal-save method="POST" id="schoolClosureForm"><input type="hidden" name="create_school_closure" value="1"><input type="hidden" name="school_closure_token" value="<?=$sch_e($_SESSION['school_closure_token'])?>"><div class="pd-body">
        <div class="pd-callout pd-callout-warning"><strong>No new events or bookings</strong>Counseling and entrance exam bookings will be closed for these dates. Existing events and appointments are kept; review them separately.</div>
        <div class="pd-field"><label for="schoolClosureReason">Reason <span aria-hidden="true">*</span></label><input type="text" name="closure_reason" id="schoolClosureReason" maxlength="100" required placeholder="e.g. Classes suspended due to weather" autocomplete="off"></div>
        <div class="pd-grid scd-fields"><div class="pd-field"><label for="schoolClosureStart">From <span aria-hidden="true">*</span></label><input type="date" name="closure_start" id="schoolClosureStart" min="<?=$minimum_schedule_date?>" required></div><div class="pd-field"><label for="schoolClosureEnd">Through <span aria-hidden="true">*</span></label><input type="date" name="closure_end" id="schoolClosureEnd" min="<?=$minimum_schedule_date?>" required></div></div>
        <p class="pd-hint">For one day, use the same date in both fields. A school closure does not repeat every year.</p>
        <p class="scd-required">* Required fields</p>
    </div><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="schoolClosureModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-lock" aria-hidden="true"></i>Mark school closed</button></footer></form></div>
</div>
<div id="viewSchoolClosureModal" class="pd-overlay hidden sch-school-closure" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="viewSchoolClosureTitle">
    <div class="pd-card"><header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-school-lock"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">SCHOOL CLOSED</p><h2 id="viewSchoolClosureTitle">School closure</h2><p>New events and bookings are blocked for these dates.</p></div><button type="button" class="pd-close" aria-label="Close school closure details" data-dialog-close="viewSchoolClosureModal">×</button></header><div class="pd-body"><h3 class="scd-label">Reason</h3><p id="viewSchoolClosureReason" class="scd-value"></p><h3 class="scd-label">Closed dates</h3><p id="viewSchoolClosureDates" class="scd-value"></p><p class="pd-callout pd-callout-warning">Reopening removes this closure for its entire date range. Sundays, holidays, and booking limits will still apply.</p></div><form method="POST" data-portal-save id="schoolClosureReopenForm" data-confirm-title="Reopen these school dates?" data-confirm-message="This removes the school closure for its entire date range. Sundays, holidays, and booking limits still apply." data-confirm-label="Reopen dates"><input type="hidden" name="reopen_school_closure" value="1"><input type="hidden" name="school_closure_token" value="<?=$sch_e($_SESSION['school_closure_token'])?>"><input type="hidden" name="closure_id" id="schoolClosureReopenId"><footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="viewSchoolClosureModal">Close</button><button type="submit" class="pd-button pd-button-primary">Reopen dates</button></footer></form></div>
</div>


<!-- Holiday booking closures. -->
<div id="holidayModal" class="pd-overlay hidden sch-holiday-modal" data-portal-dialog data-managed-dialog role="dialog" aria-modal="true" aria-labelledby="schHolidayTitle" aria-describedby="schHolidayDescription">
    <div class="pd-card">
        <header class="pd-header"><span class="pd-heading-icon" aria-hidden="true"><i class="fas fa-calendar-xmark"></i></span><div class="pd-heading-copy"><p class="sch-eyebrow">BOOKING CLOSURE</p><h2 id="schHolidayTitle">Add holiday</h2><p id="schHolidayDescription">Mark a holiday on the calendar and close bookings for that date.</p></div><button type="button" class="pd-close" aria-label="Close add holiday" data-dialog-close="holidayModal">×</button></header>
        <form data-portal-save method="POST" id="schHolidayForm"><input type="hidden" name="create_holiday" value="1">
            <div class="pd-body">
                <div class="shd-callout"><i class="fas fa-circle-info" aria-hidden="true"></i><span><strong>Bookings will be closed</strong>This holiday will appear on the office calendar.</span></div>
                <div class="pd-field"><label for="schHolidayName">Holiday name <span aria-hidden="true">*</span></label><input type="text" name="holiday_name" id="schHolidayName" required maxlength="100" placeholder="e.g. Independence Day" autocomplete="off"></div>
                <div class="pd-grid shd-grid"><div class="pd-field"><label for="schHolidayDate">Date <span aria-hidden="true">*</span></label><input type="date" name="holiday_date" id="schHolidayDate" required><small class="pd-hint">Choose the date bookings will close.</small></div><div class="pd-field"><label for="schHolidayType">Holiday type <span aria-hidden="true">*</span></label><select name="holiday_type" id="schHolidayType" required><option value="regular">Regular holiday</option><option value="special_non_working">Special non-working holiday</option></select></div></div>
                <label class="shd-recurring" for="schHolidayRecurring"><input type="checkbox" name="is_recurring" id="schHolidayRecurring" value="1"><span><strong>Repeat every year</strong><small>Use the same month and day each year. Leave unchecked for a one-time holiday.</small></span><i class="fas fa-repeat" aria-hidden="true"></i></label>
                <p class="shd-required"><span aria-hidden="true">*</span> Required fields</p>
            </div>
            <footer class="pd-footer"><button type="button" class="pd-button" data-dialog-close="holidayModal">Cancel</button><button type="submit" class="pd-button pd-button-primary"><i class="fas fa-plus" aria-hidden="true"></i> Add holiday</button></footer>
        </form>
    </div>
</div>


<script>
const schoolClosureRules=<?=json_encode($school_closure_rules,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const scheduleMinimumDate=<?=json_encode($minimum_schedule_date)?>;
const scheduleHolidayRules=<?=json_encode($schedule_holiday_rules,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
let currentScheduleData = null;
let currentHolidayData = null;
let eventsByDate = {};
let allSchedulesData = [];
let currentSchedulePage = 1;
const itemsPerPage = 5;

// Store events by date for the "+more" functionality
<?php foreach($events_map as $date => $events): ?>
eventsByDate['<?= $date ?>'] = <?= json_encode($events,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
<?php endforeach; ?>

// Store all schedules data for pagination
<?php 
$sched_list3=$schedule->getAll(); 
while($s=$sched_list3->fetch(PDO::FETCH_ASSOC)) {
    $show_schedule = false;
    if ($current_role === 'super_admin' || $current_role === 'admin' || $current_role === 'guidance_advocate') {
        $show_schedule = true;
    } elseif ($current_role === 'examinee') {
        $show_schedule = ($s['event_type'] === 'entrance_exam');
    } elseif ($current_role === 'student') {
        $show_schedule = ($s['event_type'] === 'pds_period' || $s['event_type'] === 'event');
    } elseif ($current_role === 'faculty') {
        $show_schedule = true;
    }
    if ($s['event_type'] === 'counseling' && $current_role !== 'super_admin' && $current_role !== 'admin' && $current_role !== 'guidance_advocate') {
        $show_schedule = ($s['created_by'] == $current_user_id);
    }
    if ($show_schedule) {
        $s['created_by_name'] = trim(($s['first_name']??'').' '.($s['last_name']??'')) ?: 'Unknown';
        echo "allSchedulesData.push(" . json_encode($s,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) . ");\n";
    }
}
?>

// Sort by newest first
allSchedulesData.sort(function(a, b) { return new Date(b.start_datetime) - new Date(a.start_datetime); });

function filteredAllSchedules(){
    const search=document.getElementById('schAllSearch').value.trim().toLocaleLowerCase(),type=document.getElementById('schAllType').value,period=document.getElementById('schAllPeriod').value;
    const labels={event:'Event',pds_period:'PDS period',entrance_exam:'Entrance exam',counseling:'Counselor availability'};
    const today=JSON.parse(document.getElementById('schWorkspaceData').textContent).today;
    return allSchedulesData.filter(s=>(type==='all'||s.event_type===type)&&(!search||(s.title+' '+(labels[s.event_type]||s.event_type)).toLocaleLowerCase().includes(search))&&(period==='all'||(period==='past'?s.end_datetime.slice(0,10)<today:s.end_datetime.slice(0,10)>=today)));
}
function populateAllSchedulesModal(){
    const body=document.getElementById('allSchedulesTableBody'),info=document.getElementById('paginationInfo'),pages=document.getElementById('paginationButtons');
    const labels={event:'Event',pds_period:'PDS period',entrance_exam:'Entrance exam',counseling:'Counselor availability'},icons={event:'fa-calendar',pds_period:'fa-file-lines',entrance_exam:'fa-clipboard-list',counseling:'fa-comments'};
    const rows=filteredAllSchedules(),total=rows.length,totalPages=Math.max(1,Math.ceil(total/itemsPerPage));currentSchedulePage=Math.min(Math.max(1,currentSchedulePage),totalPages);
    const offset=(currentSchedulePage-1)*itemsPerPage,today=JSON.parse(document.getElementById('schWorkspaceData').textContent).today;
    const node=(tag,className,text)=>{const e=document.createElement(tag);if(className)e.className=className;if(text!==undefined)e.textContent=text;return e;};
    body.replaceChildren();
    for(const schedule of rows.slice(offset,offset+itemsPerPage)){
        const row=node('tr','sal-row'),title=node('td','sal-title'),type=node('td','sal-type'),when=node('td','sal-when'),actions=node('td','sal-actions');row.dataset.scheduleRow=schedule.id;
        const titleLine=node('div','sal-title-line'),symbol=node('span','sal-symbol');symbol.dataset.type=schedule.event_type;const icon=node('i','fas '+(icons[schedule.event_type]||'fa-calendar'));icon.setAttribute('aria-hidden','true');symbol.append(icon);titleLine.append(symbol,node('strong','',schedule.title));title.append(titleLine);
        const lifecycle=node('span','sal-lifecycle',schedule.end_datetime.slice(0,10)<today?'Past schedule':schedule.start_datetime.slice(0,10)>today?'Upcoming':'In progress');title.append(lifecycle);
        const badge=node('span','sal-type-badge',labels[schedule.event_type]||'Event');badge.dataset.type=schedule.event_type;type.append(badge);
        for(const [label,value] of [['Starts',schedule.start_datetime],['Ends',schedule.end_datetime]]){const date=new Date(value.replace(' ','T')),line=node('div','sal-date-line'),copy=node('span');line.append(node('small','',label));copy.append(node('strong','',date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})),node('span','',date.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})));line.append(copy);when.append(line);}
        const group=node('div','sal-action-group'),view=node('button','sal-view','View'),edit=node('button','sal-edit','Edit'),form=node('form','sal-delete'),id=node('input'),action=node('input'),del=node('button','sal-delete-button');
        view.type=edit.type='button';view.setAttribute('aria-label','View '+schedule.title);edit.setAttribute('aria-label','Edit '+schedule.title);view.addEventListener('click',()=>{window.PortalDialogs.close(document.getElementById('allSchedulesModal'));viewSchedule(schedule);});edit.addEventListener('click',()=>{window.PortalDialogs.close(document.getElementById('allSchedulesModal'));editSchedule(schedule);});
        form.method='POST';form.setAttribute('data-portal-save','');form.dataset.confirmTitle='Delete this schedule?';form.dataset.confirmMessage='This removes “'+schedule.title+'” from the calendar.';form.dataset.confirmLabel='Delete schedule';
        id.type=action.type='hidden';id.name='schedule_id';id.value=schedule.id;action.name='delete_schedule';action.value='1';del.type='submit';del.title='Delete '+schedule.title;del.setAttribute('aria-label','Delete '+schedule.title);const trash=node('i','fas fa-trash-can');trash.setAttribute('aria-hidden','true');del.append(trash,node('span','sal-delete-text','Delete'));form.append(id,action,del);group.append(view,edit,form);actions.append(group);
        row.append(title,type,when,actions);body.append(row);
    }
    document.getElementById('schAllTotal').textContent=allSchedulesData.length+' '+(allSchedulesData.length===1?'schedule':'schedules');
    const filterCount=Number(document.getElementById('schAllType').value!=='all')+Number(document.getElementById('schAllPeriod').value!=='all');document.getElementById('schAllFilterCount').textContent=filterCount?'('+filterCount+')':'';
    document.getElementById('schAllEmpty').hidden=total>0;document.querySelector('#allSchedulesModal .sal-table-wrap').hidden=total===0;
    info.textContent=total?'Showing '+(offset+1)+'–'+Math.min(offset+itemsPerPage,total)+' of '+total+(total!==allSchedulesData.length?' matching':''):'0 matching schedules';
    pages.replaceChildren();
    const pageButton=(label,page,disabled,className)=>{const button=node('button',className||'',label);button.type='button';button.disabled=disabled;button.addEventListener('click',()=>{currentSchedulePage=page;populateAllSchedulesModal();document.querySelector('#allSchedulesModal .pd-body').scrollTop=0;});return button;};
    const previous=pageButton('Previous',currentSchedulePage-1,currentSchedulePage===1,'sal-page-prev');previous.setAttribute('aria-label','Previous schedule page');pages.append(previous);
    for(let page=Math.max(1,currentSchedulePage-2);page<=Math.min(totalPages,currentSchedulePage+2);page++){const button=pageButton(String(page),page,false,'sal-page-number');button.setAttribute('aria-label','Schedule page '+page);if(page===currentSchedulePage)button.setAttribute('aria-current','page');pages.append(button);}
    pages.append(node('span','sal-page-position',currentSchedulePage+' / '+totalPages));const next=pageButton('Next',currentSchedulePage+1,currentSchedulePage===totalPages,'sal-page-next');next.setAttribute('aria-label','Next schedule page');pages.append(next);
}
function resetAllScheduleFilters(){document.getElementById('schAllSearch').value='';document.getElementById('schAllType').value='all';document.getElementById('schAllPeriod').value='all';currentSchedulePage=1;populateAllSchedulesModal();}
function openAllSchedules(){resetAllScheduleFilters();window.PortalDialogs.open(document.getElementById('allSchedulesModal'));}
function initializeAllSchedules(){for(const id of ['schAllSearch','schAllType','schAllPeriod'])document.getElementById(id).addEventListener(id==='schAllSearch'?'input':'change',()=>{currentSchedulePage=1;populateAllSchedulesModal();});document.getElementById('schAllReset').addEventListener('click',resetAllScheduleFilters);}


function changeSchedulePage(page){
    const url = new URL(window.location.href);
    url.searchParams.set('schedule_page', page);
    window.location.href = url.toString();
}

function showDayEvents(date){
    const events = eventsByDate[date] || [];
    const dateObj = new Date(date+'T12:00:00');
    document.getElementById('dayEventsDate').textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    const listContainer = document.getElementById('dayEventsList');
    listContainer.innerHTML = '';
    
    if(events.length === 0){
        listContainer.innerHTML = '<div class="text-gray-500 text-center py-4">No events for this day</div>';
    } else {
        const eventColors = {
            'event': 'bg-green-500',
            'pds_period': 'bg-purple-500',
            'entrance_exam': 'bg-cyan-500',
            'counseling': 'bg-blue-500'
        };
        
        events.forEach(ev => {
            const color = eventColors[ev.event_type] || 'bg-gray-500';
            const startTime = new Date(ev.start_datetime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const endTime = new Date(ev.end_datetime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            
            const eventDiv = document.createElement('div');
            eventDiv.className = 'p-3 rounded-lg border hover:shadow-md cursor-pointer transition-shadow';
            eventDiv.style.borderLeft = '4px solid';
            eventDiv.style.borderLeftColor = color.replace('bg-', '').replace('-500', '');
            eventDiv.onclick = () => {
                closeModal('dayEventsModal');
                viewSchedule(ev);
            };
            
            eventDiv.innerHTML = `
                <div class="font-semibold text-gray-800">${ev.title}</div>
                <div class="text-xs text-gray-600">${startTime} - ${endTime}</div>
                <div class="text-xs text-gray-500 mt-1">${ev.event_type.replace('_', ' ').toUpperCase()}</div>
            `;
            
            listContainer.appendChild(eventDiv);
        });
    }
    
    openModal('dayEventsModal');
}

function scheduleClosureReason(date){
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date))return '';
    if(new Date(date+'T12:00:00').getDay()===0)return 'Events cannot be scheduled on Sundays.';
    const closure=schoolClosureRules.find(row=>row.start_date<=date&&row.end_date>=date);if(closure)return 'School closed: '+closure.reason+'. No events can be added.';
    const holiday=scheduleHolidayRules.find(row=>row.date===date||(Number(row.is_recurring)===1&&row.date<=date&&row.date.slice(5)===date.slice(5)));
    return holiday?'Events cannot be scheduled on this holiday: '+holiday.name+'.':'';
}
function scheduleClosedRange(start,end){
    if(!start||!end||end<start)return '';
    for(let day=new Date(start+'T12:00:00');day<=new Date(end+'T12:00:00');day.setDate(day.getDate()+1)){
        const date=day.getFullYear()+'-'+String(day.getMonth()+1).padStart(2,'0')+'-'+String(day.getDate()).padStart(2,'0');
        const reason=scheduleClosureReason(date);if(reason)return reason+' Choose a range that excludes Sundays and holidays.';
    }
    return '';
}
function refreshEditScheduleClosures(){
    const start=document.getElementById('edit_start_date'),end=document.getElementById('edit_end_date'),from=document.getElementById('edit_start_time'),through=document.getElementById('edit_end_time');
    end.min=start.value&&start.value>scheduleMinimumDate?start.value:scheduleMinimumDate;
    const closure=scheduleClosedRange(start.value,end.value);
    const complete=start.value&&end.value&&from.value&&through.value;
    const timing=complete&&end.value+'T'+through.value<=start.value+'T'+from.value?'The end date and time must be after the start.':'';
    const message=closure||timing;end.setCustomValidity(closure);through.setCustomValidity(message);
    const hint=document.getElementById('edit_schedule_closure_error');hint.hidden=!message;hint.textContent=message;
}
function openEventTypeSelection(date){
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||date<scheduleMinimumDate){officeAlert({title:'Choose a future date',text:'Schedules must start tomorrow or later.',icon:'info'});return;}
    const closed=scheduleClosureReason(date);if(closed){officeAlert({title:'Date closed for events',text:closed,icon:'info'});return;}
    document.getElementById('selected_date').value = date;
    const dateObj = new Date(date+'T12:00:00');
    document.getElementById('selected_date_display').textContent = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    const modal=document.getElementById('eventTypeSelectionModal');
    window.PortalDialogs.open(modal);
    modal.querySelector('.sch-type-option').focus({preventScroll:true});
}

function selectEventType(eventType){
    window.PortalDialogs.close(document.getElementById('eventTypeSelectionModal'));
    const date = document.getElementById('selected_date').value;
    if(date<scheduleMinimumDate||scheduleClosureReason(date))return;
    document.getElementById('create_event_type').value = eventType;
    const startDateInput = document.getElementById('create_start_date');
    const endDateInput = document.getElementById('create_end_date');
    if(startDateInput) startDateInput.value = date;
    if(endDateInput) endDateInput.value = date;
    updateCreateModalColor(eventType);
    window.PortalDialogs.open(document.getElementById('createScheduleModal'));
    document.getElementById('create_title').focus({preventScroll:true});
}

function updateCreateModalColor(eventType){
    const options={
        event:{label:'Create event',hint:'School activities and general events',icon:'fa-calendar',placeholder:'e.g. Grade 11 guidance orientation'},
        pds_period:{label:'Create PDS period',hint:'Personal Data Sheet submission dates',icon:'fa-file-alt',placeholder:'e.g. First semester PDS submission'},
        entrance_exam:{label:'Schedule entrance exam',hint:'Entrance examination schedules',icon:'fa-clipboard-list',placeholder:'e.g. Incoming college entrance exam'},
        counseling:{label:'Set counselor availability',hint:'Available counseling time blocks',icon:'fa-user-clock',placeholder:'e.g. Morning counseling availability'}
    };
    const type=Object.hasOwn(options,eventType)?eventType:'event',option=options[type],modal=document.getElementById('createScheduleModal');
    modal.dataset.eventType=type;
    document.getElementById('create_event_type').value=type;
    document.getElementById('createScheduleTitle').textContent=option.label;
    document.getElementById('create_event_type_label').textContent=option.hint;
    document.getElementById('create_event_type_icon').className='fas '+option.icon;
    document.getElementById('create_title').placeholder=option.placeholder;
    document.querySelector('#create_schedule_submit span').textContent=option.label;
    refreshCreateScheduleTiming();
}

function refreshCreateScheduleTiming(){
    const start=document.getElementById('create_start_date'),end=document.getElementById('create_end_date'),startTime=document.getElementById('create_start_time'),endTime=document.getElementById('create_end_time');
    end.min=start.value&&start.value>scheduleMinimumDate?start.value:scheduleMinimumDate;
    const complete=start.value&&end.value&&startTime.value&&endTime.value;
    const invalid=complete&&(end.value+'T'+endTime.value<=start.value+'T'+startTime.value);
    const closure=scheduleClosedRange(start.value,end.value);
    const message=closure||(invalid?'The end date and time must be after the start.':'');
    endTime.setCustomValidity(message);
    end.setCustomValidity(closure);
    const error=document.getElementById('create_schedule_timing_error');error.hidden=!message;if(error.textContent!==message)error.textContent=message;
    let summary='Select the dates and times.';
    if(complete&&!invalid&&!closure){const dateOptions={month:'short',day:'numeric'},timeOptions={hour:'numeric',minute:'2-digit'};
        const from=new Date(start.value+'T'+startTime.value),through=new Date(end.value+'T'+endTime.value);
        summary=from.toLocaleDateString('en-US',dateOptions)+' · '+from.toLocaleTimeString('en-US',timeOptions)+' – '+(start.value!==end.value?through.toLocaleDateString('en-US',dateOptions)+' · ':'')+through.toLocaleTimeString('en-US',timeOptions);
    }
    const output=document.getElementById('create_schedule_range');if(output.textContent!==summary)output.textContent=summary;
}

function initializeCreateScheduleModal(){
    document.getElementById('create_event_type').addEventListener('change',event=>updateCreateModalColor(event.target.value));
    for(const id of ['create_start_date','create_end_date','create_start_time','create_end_time'])document.getElementById(id).addEventListener('input',refreshCreateScheduleTiming);
    document.getElementById('create_start_date').addEventListener('change',()=>{const start=document.getElementById('create_start_date'),end=document.getElementById('create_end_date');if(start.value&&end.value&&end.value<start.value)end.value=start.value;refreshCreateScheduleTiming();});
    updateCreateModalColor('event');
}


function viewSchedule(data){
    currentScheduleData=data;
    document.getElementById('view_schedule_id').value=data.id;
    document.getElementById('view_title').textContent=data.title||'Untitled schedule';
    const description=typeof data.description==='string'?data.description.trim():'';
    const descriptionNode=document.getElementById('view_description');descriptionNode.textContent=description||'No description provided.';descriptionNode.dataset.empty=String(!description);
    for(const [prefix,value] of [['start',data.start_datetime],['end',data.end_datetime]]){
        const date=new Date(value.replace(' ','T')),element=document.getElementById('view_'+prefix+'_datetime');
        element.textContent=date.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});element.setAttribute('datetime',value.replace(' ','T'));
        document.getElementById('view_'+prefix+'_time').textContent=date.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
    }
    const creator=(data.created_by_name||[data.first_name,data.last_name].filter(Boolean).join(' ')||'Unknown').trim();
    document.getElementById('view_created_by').textContent=creator;
    document.getElementById('view_creator_initials').textContent=creator.split(/\s+/).filter(Boolean).slice(0,2).map(part=>part[0]).join('').toUpperCase();
    const today=JSON.parse(document.getElementById('schWorkspaceData').textContent).today;
    document.getElementById('view_schedule_status').textContent=data.end_datetime.slice(0,10)<today?'Past schedule':data.start_datetime.slice(0,10)>today?'Upcoming':'Today';
    document.getElementById('view_schedule_span').textContent=data.start_datetime.slice(0,10)===data.end_datetime.slice(0,10)?'Single day':'Multi-day';
    updateViewModalColor(data.event_type||'event');
    window.PortalDialogs.open(document.getElementById('viewScheduleModal'));
    document.querySelector('#viewScheduleModal .pd-close').focus({preventScroll:true});
}
function updateViewModalColor(eventType){
    const types={event:{label:'Event',icon:'fa-calendar'},pds_period:{label:'PDS period',icon:'fa-file-lines'},entrance_exam:{label:'Entrance exam',icon:'fa-clipboard-list'},counseling:{label:'Counselor availability',icon:'fa-comments'}};
    const option=types[eventType]||types.event;
    document.getElementById('viewScheduleModal').dataset.eventType=eventType;
    document.getElementById('view_event_type').textContent=option.label;
    document.getElementById('view_schedule_icon').className='fas '+option.icon;
}
function refreshEditScheduleType(){
    const type=document.getElementById('edit_event_type').value;
    const types={event:{icon:'fa-calendar',hint:'School activities and general events'},pds_period:{icon:'fa-file-lines',hint:'Personal Data Sheet submission dates'},entrance_exam:{icon:'fa-clipboard-list',hint:'Entrance examination schedules'},counseling:{icon:'fa-comments',hint:'Available counseling time blocks'}};
    const option=types[type]||types.event;document.getElementById('editScheduleModal').dataset.eventType=type;
    document.getElementById('edit_type_hint').textContent=option.hint;document.getElementById('edit_type_icon').className='fas '+option.icon;
}
function initializeEditScheduleModal(){
    for(const id of ['edit_start_date','edit_end_date','edit_start_time','edit_end_time'])document.getElementById(id).addEventListener('input',refreshEditScheduleClosures);
    document.getElementById('edit_event_type').addEventListener('change',refreshEditScheduleType);
}


function viewHoliday(data){
    currentHolidayData = data;
    document.getElementById('view_holiday_id').value=data.id;
    document.getElementById('view_holiday_name').textContent=data.name||'';
    document.getElementById('view_holiday_date').textContent=new Date(data.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    openModal('viewHolidayModal');
}

function editSchedule(data){
    document.getElementById('edit_schedule_id').value=data.id;
    document.getElementById('edit_title').value=data.title||'';
    document.getElementById('edit_description').value=data.description||'';
    document.getElementById('edit_event_type').value=data.event_type||'event';
    const sd=new Date(data.start_datetime);
    const ed=new Date(data.end_datetime);
    document.getElementById('edit_start_date').value=(data.start_datetime||'').slice(0,10);
    document.getElementById('edit_start_time').value=(data.start_datetime||'').slice(11,16);
    document.getElementById('edit_end_date').value=(data.end_datetime||'').slice(0,10);
    document.getElementById('edit_end_time').value=(data.end_datetime||'').slice(11,16);
    document.getElementById('edit_description_details').open=Boolean((data.description||'').trim());
    refreshEditScheduleType();refreshEditScheduleClosures();
    window.PortalDialogs.close(document.getElementById('viewScheduleModal'));
    window.PortalDialogs.open(document.getElementById('editScheduleModal'));
    document.getElementById('edit_title').focus({preventScroll:true});
}

function deleteScheduleFromView(scheduleId){
    officeAlert({title:'Delete this schedule?',text:'This removes the selected calendar entry.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete schedule',cancelButtonText:'Cancel'}).then(result=>{if(result.isConfirmed){
        document.getElementById('delete_schedule_id').value=scheduleId;
        window.PortalNavigation.submit(document.getElementById('deleteScheduleForm'));
    }});
}

function deleteHolidayFromView(holidayId){
    officeAlert({title:'Delete this holiday?',text:'This removes the selected holiday from the calendar.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete holiday',cancelButtonText:'Cancel'}).then(result=>{if(result.isConfirmed){
        document.getElementById('delete_holiday_id').value=holidayId;
        window.PortalNavigation.submit(document.getElementById('deleteHolidayForm'));
    }});
}

function quickCreateSchedule(date){openEventTypeSelection(date);}

function openSchoolClosure(date){
    date=date||document.getElementById('scheduleWorkspace').dataset.selectedDate;
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||date<scheduleMinimumDate)date=scheduleMinimumDate;
    window.PortalDialogs.close(document.getElementById('eventTypeSelectionModal'));
    document.getElementById('schoolClosureStart').value=date;document.getElementById('schoolClosureEnd').value=date;
    refreshSchoolClosureDates();window.PortalDialogs.open(document.getElementById('schoolClosureModal'));document.getElementById('schoolClosureReason').focus({preventScroll:true});
}
function refreshSchoolClosureDates(){
    const start=document.getElementById('schoolClosureStart'),end=document.getElementById('schoolClosureEnd');end.min=start.value||scheduleMinimumDate;
    end.setCustomValidity(start.value&&end.value&&end.value<start.value?'The end date must be on or after the start date.':'');
}
function viewSchoolClosure(data){
    document.getElementById('viewSchoolClosureReason').textContent=data.reason;
    const format=date=>new Date(date+'T12:00:00').toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric',year:'numeric'});
    document.getElementById('viewSchoolClosureDates').textContent=format(data.start_date)+(data.end_date!==data.start_date?' – '+format(data.end_date):'');
    document.getElementById('schoolClosureReopenId').value=data.id;window.PortalDialogs.open(document.getElementById('viewSchoolClosureModal'));
}
for(const id of ['schoolClosureStart','schoolClosureEnd'])document.getElementById(id).addEventListener('input',refreshSchoolClosureDates);

initializeCreateScheduleModal();
initializeAllSchedules();
initializeEditScheduleModal();

// Auto-open view modal if view_schedule parameter is present
<?php if($auto_view_schedule): ?>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleData = <?= json_encode($auto_view_schedule,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    if(scheduleData && scheduleData.id){
        viewSchedule(scheduleData);
        // Remove the view_schedule parameter from URL to prevent re-opening on refresh
        const url = new URL(window.location.href);
        url.searchParams.delete('view_schedule');
        window.history.replaceState({}, '', url);
    }
});
<?php endif; ?>
</script>

<script src="../assets/js/schedules-workspace.js?v=20261028-10">
</script>

<link rel="stylesheet" href="../assets/css/portal-calendars.css?v=20261029-shared">
