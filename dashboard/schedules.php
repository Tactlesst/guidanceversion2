<?php
require_once __DIR__ . '/../classes/Schedule.php';
require_once __DIR__ . '/../classes/Holiday.php';
require_once __DIR__ . '/../classes/DailyBookingLimit.php';

$schedule = new Schedule($db);
$holiday = new Holiday($db);
$dailyLimit = new DailyBookingLimit($db);

$msgs = fetchSessionMessages();
$success_message = $msgs['success'];
$error_message = $msgs['error'];

if ($_POST) {
    if (isset($_POST['create_schedule'])) {
        $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->created_by=$_SESSION['user_id']; $schedule->is_active=1;
        if($schedule->create()){$_SESSION['success_message']="Schedule created!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['update_schedule'])) {
        $schedule->id=$_POST['schedule_id']; $schedule->title=$_POST['title']; $schedule->description=$_POST['description']??'';
        $schedule->start_datetime=$_POST['start_date'].' '.($_POST['start_time']??'08:00');
        $schedule->end_datetime=$_POST['end_date'].' '.($_POST['end_time']??'17:00');
        $schedule->event_type=$_POST['event_type']??'event'; $schedule->is_active=1;
        if($schedule->update()){$_SESSION['success_message']="Schedule updated!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['delete_schedule'])) {
        $schedule->id=$_POST['schedule_id'];
        if($schedule->delete()){$_SESSION['success_message']="Schedule deleted!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['set_daily_limit'])) {
        $dailyLimit->setDailyLimit($_POST['limit_date'],(int)$_POST['max_appointments'],$_SESSION['user_id']);
        $_SESSION['success_message']="Daily limit set!";header("Location:layout.php?page=schedules");exit();
    }
    if (isset($_POST['remove_daily_limit'])) {
        $dailyLimit->removeDailyLimit($_POST['limit_date']);
        $_SESSION['success_message']="Daily limit removed!";header("Location:layout.php?page=schedules");exit();
    }
    if (isset($_POST['create_holiday'])) {
        $holiday->name=$_POST['holiday_name']; $holiday->date=$_POST['holiday_date'];
        $holiday->type=$_POST['holiday_type']??'regular'; $holiday->is_recurring=isset($_POST['is_recurring'])?1:0;
        $holiday->year=date('Y',strtotime($_POST['holiday_date']));
        if($holiday->create()){$_SESSION['success_message']="Holiday added!";header("Location:layout.php?page=schedules");exit();}
    }
    if (isset($_POST['delete_holiday'])) {
        $holiday->delete($_POST['holiday_id']);
        $_SESSION['success_message']="Holiday removed!";header("Location:layout.php?page=schedules");exit();
    }
}

$all_schedules=$schedule->getAll(); $current_month=date('m'); $current_year=date('Y');
$upcoming_holidays=$holiday->getUpcomingHolidays(10);
$event_colors=['pds_period'=>'bg-purple-500','entrance_exam'=>'bg-cyan-500','counseling'=>'bg-blue-500','event'=>'bg-green-500','holiday'=>'bg-red-500'];
$event_labels=['pds_period'=>'PDS Period','entrance_exam'=>'Entrance Exam','counseling'=>'Counseling','event'=>'Event','holiday'=>'Holiday'];
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-calendar-alt mr-2 text-primary"></i>Schedule Management</h1>
        <div class="flex gap-2">
            <button onclick="openModal('createScheduleModal')" class="px-3 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary-dark"><i class="fas fa-plus mr-1"></i>Add Schedule</button>
            <button onclick="openModal('dailyLimitModal')" class="px-3 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-clock mr-1"></i>Booking Limits</button>
            <button onclick="openModal('holidayModal')" class="px-3 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600"><i class="fas fa-umbrella-beach mr-1"></i>Holidays</button>
        </div>
    </div>

    <!-- Alerts -->
    <?= renderAlerts($success_message, $error_message) ?>

    <!-- Legend -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <div class="flex flex-wrap gap-4 items-center">
            <span class="text-sm font-medium text-gray-600">Event Types:</span>
            <?php foreach ($event_labels as $type=>$label): $c=$event_colors[$type]??'bg-gray-500'; ?>
            <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded <?= $c ?>"></span><?= $label ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Calendar -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold text-gray-800"><?= date('F Y') ?></h2>
        </div>
        <div class="grid grid-cols-7 text-center text-xs font-medium text-gray-500 border-b">
            <div class="py-2">Sun</div><div class="py-2">Mon</div><div class="py-2">Tue</div><div class="py-2">Wed</div><div class="py-2">Thu</div><div class="py-2">Fri</div><div class="py-2">Sat</div>
        </div>
        <div class="grid grid-cols-7">
        <?php
        $first_day=date('w',strtotime("$current_year-$current_month-01"));
        $days_in_month=date('t',strtotime("$current_year-$current_month-01"));
        $today=date('Y-m-d');
        $events_map=[];
        $sched_list=$schedule->getAll();
        while($s=$sched_list->fetch(PDO::FETCH_ASSOC)){$d=date('Y-m-d',strtotime($s['start_datetime']));$events_map[$d][]=$s;}
        for($i=0;$i<$first_day;$i++) echo '<div class="min-h-[90px] border-b border-r p-1 bg-gray-50"></div>';
        for($day=1;$day<=$days_in_month;$day++):
            $ds=sprintf('%04d-%02d-%02d',$current_year,$current_month,$day);
            $is_today=$ds===$today;
            $is_holiday=$holiday->isHoliday($ds);
            $day_events=$events_map[$ds]??[];
        ?>
            <div class="min-h-[90px] border-b border-r p-1 <?= $is_today?'bg-blue-50':($is_holiday?'bg-red-50':'') ?>">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm font-medium <?= $is_today?'bg-primary text-white px-1.5 py-0.5 rounded-full':'text-gray-700' ?>"><?= $day ?></span>
                    <?php if($is_holiday):?><i class="fas fa-umbrella-beach text-red-400 text-xs"></i><?php endif; ?>
                </div>
                <?php foreach(array_slice($day_events,0,3) as $ev): $ec=$event_colors[$ev['event_type']]??'bg-gray-500'; ?>
                <div class="<?= $ec ?> text-white text-xs px-1 py-0.5 rounded mb-0.5 truncate"><?= htmlspecialchars($ev['title']) ?></div>
                <?php endforeach; ?>
                <?php if(count($day_events)>3):?><div class="text-xs text-gray-400">+<?= count($day_events)-3 ?> more</div><?php endif; ?>
            </div>
        <?php endfor; ?>
        </div>
    </div>

    <!-- Upcoming Holidays -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <h3 class="text-sm font-bold text-gray-700 mb-3"><i class="fas fa-umbrella-beach mr-1 text-red-500"></i>Upcoming Holidays</h3>
        <div class="space-y-2">
        <?php if($upcoming_holidays->rowCount()>0): while($h=$upcoming_holidays->fetch(PDO::FETCH_ASSOC)): ?>
            <div class="flex items-center justify-between py-2 border-b last:border-0">
                <div><span class="text-sm font-medium"><?= htmlspecialchars($h['name']) ?></span><span class="text-xs text-gray-500 ml-2"><?= date('M d, Y',strtotime($h['date'])) ?></span></div>
                <form method="POST" class="inline"><input type="hidden" name="holiday_id" value="<?= $h['id'] ?>"><button type="submit" name="delete_holiday" value="1" class="text-red-400 hover:text-red-600 text-xs" onclick="return confirm('Remove this holiday?')"><i class="fas fa-trash"></i></button></form>
            </div>
        <?php endwhile; else: ?>
            <p class="text-sm text-gray-400">No upcoming holidays</p>
        <?php endif; ?>
        </div>
    </div>

    <!-- Schedules List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b"><h3 class="text-sm font-bold text-gray-700">All Schedules</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-left"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Start</th><th class="px-4 py-3">End</th><th class="px-4 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                <?php $sched_list2=$schedule->getAll(); while($s=$sched_list2->fetch(PDO::FETCH_ASSOC)): $ec=$event_colors[$s['event_type']]??'bg-gray-500'; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($s['title']) ?></td>
                    <td class="px-4 py-3"><span class="<?= $ec ?> text-white text-xs px-2 py-1 rounded capitalize"><?= str_replace('_',' ',$s['event_type']) ?></span></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('M d, Y g:i A',strtotime($s['start_datetime'])) ?></td>
                    <td class="px-4 py-3 text-gray-500 text-xs"><?= date('M d, Y g:i A',strtotime($s['end_datetime'])) ?></td>
                    <td class="px-4 py-3 text-right">
                        <button onclick='editSchedule(<?= json_encode($s) ?>)' class="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Edit"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this schedule?')"><input type="hidden" name="schedule_id" value="<?= $s['id'] ?>"><button type="submit" name="delete_schedule" value="1" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Delete"><i class="fas fa-trash"></i></button></form>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Schedule Modal -->
<div id="createScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="bg-primary text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-plus mr-2"></i>Create Schedule</h3>
            <button onclick="closeModal('createScheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_schedule" value="1">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                <select name="event_type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="event">Event</option><option value="pds_period">PDS Period</option><option value="entrance_exam">Entrance Exam</option><option value="counseling">Counseling</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label><input type="date" name="start_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label><input type="time" name="start_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label><input type="date" name="end_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Time</label><input type="time" name="end_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('createScheduleModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm">Create</button></div>
        </form>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-edit mr-2"></i>Edit Schedule</h3>
            <button onclick="closeModal('editScheduleModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="update_schedule" value="1">
            <input type="hidden" name="schedule_id" id="edit_schedule_id">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Title *</label><input type="text" name="title" id="edit_title" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Description</label><textarea name="description" id="edit_description" rows="2" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                <select name="event_type" id="edit_event_type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="event">Event</option><option value="pds_period">PDS Period</option><option value="entrance_exam">Entrance Exam</option><option value="counseling">Counseling</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label><input type="date" name="start_date" id="edit_start_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label><input type="time" name="start_time" id="edit_start_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label><input type="date" name="end_date" id="edit_end_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">End Time</label><input type="time" name="end_time" id="edit_end_time" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('editScheduleModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Update</button></div>
        </form>
    </div>
</div>

<!-- Daily Booking Limit Modal -->
<div id="dailyLimitModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-yellow-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-clock mr-2"></i>Daily Booking Limits</h3>
            <button onclick="closeModal('dailyLimitModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <form method="POST" class="space-y-3">
                <input type="hidden" name="set_daily_limit" value="1">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="limit_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Max Appointments *</label><input type="number" name="max_appointments" min="1" max="20" value="4" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <button type="submit" class="w-full px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm">Set Limit</button>
            </form>
            <hr>
            <form method="POST" class="space-y-3">
                <input type="hidden" name="remove_daily_limit" value="1">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Remove Limit for Date</label><input type="date" name="limit_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                <button type="submit" class="w-full px-4 py-2 border text-red-600 rounded-lg text-sm hover:bg-red-50">Remove Limit</button>
            </form>
        </div>
    </div>
</div>

<!-- Holiday Modal -->
<div id="holidayModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="bg-red-500 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
            <h3 class="text-lg font-bold"><i class="fas fa-umbrella-beach mr-2"></i>Add Holiday</h3>
            <button onclick="closeModal('holidayModal')" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="create_holiday" value="1">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Holiday Name *</label><input type="text" name="holiday_name" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Date *</label><input type="date" name="holiday_date" required class="w-full px-3 py-2 border rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="holiday_type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="regular">Regular Holiday</option><option value="special">Special Non-Working</option>
                </select>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_recurring" value="1" class="rounded"><label class="text-sm text-gray-700">Recurring (every year)</label></div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModal('holidayModal')" class="px-4 py-2 border rounded-lg text-sm">Cancel</button><button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm">Add Holiday</button></div>
        </form>
    </div>
</div>

<script>
function editSchedule(data){
    document.getElementById('edit_schedule_id').value=data.id;
    document.getElementById('edit_title').value=data.title||'';
    document.getElementById('edit_description').value=data.description||'';
    document.getElementById('edit_event_type').value=data.event_type||'event';
    const sd=new Date(data.start_datetime);
    const ed=new Date(data.end_datetime);
    document.getElementById('edit_start_date').value=sd.toISOString().split('T')[0];
    document.getElementById('edit_start_time').value=sd.toTimeString().slice(0,5);
    document.getElementById('edit_end_date').value=ed.toISOString().split('T')[0];
    document.getElementById('edit_end_time').value=ed.toTimeString().slice(0,5);
    openModal('editScheduleModal');
}
</script>
