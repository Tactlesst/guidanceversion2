<?php
$in_layout = defined('IN_LAYOUT');

if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/core/session.php';
    require_once '../classes/counseling/CounselingAppointment.php';
    require_once '../classes/system/SystemSettings.php';
    require_once '../classes/notifications/Notification.php';
    require_once '../classes/identity/User.php';
    checkLogin();
    checkRole(['student']);
    
    $database = new Database();
    $db = $database->getConnection();
} else {
    require_once __DIR__.'/../classes/counseling/CounselingAppointment.php';
    require_once __DIR__.'/../classes/system/SystemSettings.php';
    require_once __DIR__.'/../classes/notifications/Notification.php';
    require_once __DIR__.'/../classes/identity/User.php';
    // $db is already available from layout.php
}

$_SESSION['booking_csrf']=$_SESSION['booking_csrf']??bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&(!is_string($_POST['_booking_csrf']??null)||!hash_equals($_SESSION['booking_csrf'],$_POST['_booking_csrf']))){http_response_code(403);echo '<p>Refresh the booking page before submitting an appointment.</p>';return;}
// Check system settings
$settings = new SystemSettings($db);
if(!$settings->isCounselingEnabled()) {
    $redirect = $in_layout ? '../dashboard/layout.php?page=dashboard&error=counseling_disabled' : '../dashboard/index.php?error=counseling_disabled';
    header("Location: $redirect");
    exit();
}

$user_info = getUserInfo();
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Check if user already has an active appointment (for booking restriction only, not for viewing)
$counseling = new CounselingAppointment($db);
$has_active_appointment = $counseling->hasActiveAppointment($user_info['id']);

// Handle cancel appointment
if (isset($_POST['cancel_appointment']) && $_POST['cancel_appointment']) {
    $appointment_id = $_POST['appointment_id'];
    $counseling = new CounselingAppointment($db);
    
    // Verify the appointment belongs to the current user
    $stmt = $db->prepare("SELECT id, user_id, status, assigned_advocate_id AS advocate_id FROM counseling_appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appt && $appt['user_id'] == $user_info['id']) {
        // Check if counselor is assigned
        if (!empty($appt['advocate_id'])) {
            $error_message = "Cannot cancel an appointment that has been assigned to a counselor. Please contact the guidance office for assistance.";
        }
        // Only allow cancellation of pending, confirmed, or in_progress appointments
        elseif (in_array($appt['status'], ['pending', 'confirmed', 'in_progress'])) {
            if ($counseling->cancelAppointment($appointment_id)) {
                $_SESSION['success_message'] = "Your appointment has been cancelled successfully.";
                // Redirect to prevent form resubmission
                $redirect_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';
                if($in_layout&&function_exists('portalSaveRedirect'))$redirect_url=portalSaveRedirect($redirect_url);
                header("Location: $redirect_url");
                exit();
            } else {
                $error_message = "Failed to cancel your appointment. Please try again.";
            }
        } else {
            $error_message = "Cannot cancel an appointment that is already " . $appt['status'] . ".";
        }
    } else {
        $error_message = "Invalid appointment.";
    }
}

// Handle POST for booking appointment
if($_POST && empty($_POST['cancel_appointment'])) {
    // Prevent booking if user already has an active appointment
    if($has_active_appointment) {
        $error_message = "You already have an active appointment. Please complete or cancel your current appointment before booking a new one.";
    } else {
        $counseling->user_id = $user_info['id'];
        $counseling->appointment_date = $_POST['appointment_date'] ?? '';
        $counseling->appointment_time = $_POST['appointment_time'] ?? '';
        $counseling->concern_type = $_POST['concern_type'] ?? '';
        $counseling->concern_description = $_POST['concern_description'] ?? '';
        $counseling->urgency_level = $_POST['urgency_level'] ?? '';
        $counseling->nature_of_contact = 'walk-in';
        $counseling->session_duration = 60;
        $counseling->original_appointment_id = null;

        // Student appointments must be planned at least one calendar day ahead.
        if(strtotime($counseling->appointment_date) <= strtotime(date('Y-m-d'))) {
            $error_message = "Appointments must be planned in advance. Please choose tomorrow or a later date.";
        } elseif (empty($counseling->concern_type) || empty($counseling->concern_description) || empty($counseling->urgency_level)) {
            $error_message = "Please fill in all required fields.";
        } else {
            try{$appointment_id=$counseling->createWithDailyLimit();}
            catch(PDOException $e){error_log('Counseling booking: '.$e->getMessage());$appointment_id=false;$error_message='Your appointment could not be saved. Please try again.';}
            catch(InvalidArgumentException|RuntimeException $e){$appointment_id=false;$error_message=$e->getMessage();}
            if($appointment_id) {
                $_SESSION['success_message'] = "Your counseling appointment has been booked successfully! You will be notified once a counselor is assigned.";
                
                // Redirect to prevent form resubmission
                $redirect_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';
                if($in_layout&&function_exists('portalSaveRedirect'))$redirect_url=portalSaveRedirect($redirect_url);
                header("Location: $redirect_url");
                exit();
            } else {
                $error_message = $error_message ?: "Failed to submit your appointment request. Please try again.";
            }
        }
    }
}

// Get calendar data
$current_month = date('n');
$current_year = date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
$year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
if($month<1||$month>12)$month=$current_month;if($year<1970||$year>2100)$year=$current_year;

// Get user's existing appointments for calendar display
$my_appointments = [];
try {
    $my_appts_stmt = $db->prepare("SELECT id, appointment_date, appointment_time, status, concern_type, urgency_level, assigned_advocate_id AS advocate_id FROM counseling_appointments WHERE user_id = ? AND appointment_date >= CURDATE() ORDER BY appointment_date");
    $my_appts_stmt->execute([$user_info['id']]);
    while ($row = $my_appts_stmt->fetch(PDO::FETCH_ASSOC)) {
        $my_appointments[$row['appointment_date']][] = $row;
    }
} catch (Exception $e) {}

// Get counselor availability schedules for the month
$counselor_availability = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $avail_stmt = $db->prepare("SELECT DATE(start_datetime) as avail_date, TIME(start_datetime) as start_time, TIME(end_datetime) as end_time FROM schedules WHERE event_type = 'counseling' AND DATE(start_datetime) BETWEEN ? AND ? AND is_active = 1");
    $avail_stmt->execute([$start_date, $end_date]);
    while ($row = $avail_stmt->fetch(PDO::FETCH_ASSOC)) {
        $counselor_availability[$row['avail_date']][] = [
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time']
        ];
    }
} catch (Exception $e) {}

// Get booked appointments (other students) for availability display
$booked_slots = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $booked_slots = $counseling->slotCountsForRange(date('Y-m-01',strtotime($start_date)),$end_date);
} catch (Exception $e) {}

$daily_capacity=[];
try{$daily_capacity=(new DailyBookingLimit($db))->getCapacityForRange(date('Y-m-01',strtotime($start_date)),$end_date);}catch(Throwable $e){error_log('Counseling daily capacity: '.$e->getMessage());}

$school_closure_dates=(new SchoolClosure($db))->calendar(date('Y-m-01',strtotime($start_date)),$end_date);

require_once __DIR__.'/../classes/scheduling/Holiday.php';
$calendarHolidays=(new Holiday($db))->getCalendarHolidays((int)$month,(int)$year);
$holiday_dates=array_column($calendarHolidays,'date');

// Mark every day of a holiday range closed, matching server-side booking validation.
$holidayRanges=$db->prepare("SELECT DATE(start_datetime) AS start_date, DATE(end_datetime) AS end_date FROM schedules WHERE event_type='holiday' AND is_active=1 AND DATE(start_datetime)<=? AND DATE(end_datetime)>=?");
$holidayRanges->execute([$end_date,date('Y-m-01',strtotime($start_date))]);
foreach($holidayRanges->fetchAll(PDO::FETCH_ASSOC) as $range){
    $cursor=max($range['start_date'],date('Y-m-01',strtotime($start_date)));$last=min($range['end_date'],$end_date);
    while($cursor<=$last){$holiday_dates[]=$cursor;$cursor=date('Y-m-d',strtotime($cursor.' +1 day'));}
}
$holiday_dates=array_values(array_unique($holiday_dates));

// Stats
$events_this_month = 0;
$todays_events = 0;
$holidays_this_month = count($holiday_dates);
try {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE MONTH(event_date) = ? AND YEAR(event_date) = ?");
        $stmt->execute([$month, $year]);
        $events_this_month = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE MONTH(start_datetime) = ? AND YEAR(start_datetime) = ? AND is_active = 1");
            $stmt->execute([$month, $year]);
            $events_this_month = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e2) { $events_this_month = 0; }
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE event_date = CURDATE()");
        $stmt->execute();
        $todays_events = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM schedules WHERE DATE(start_datetime) = CURDATE() AND is_active = 1");
            $stmt->execute();
            $todays_events = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e2) { $todays_events = 0; }
    }
} catch (Exception $e) {}

// Get events and holidays for calendar
$calendar_events = [];
try {
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Use the same schedule records as the admin calendar
    try {
        $stmt = $db->prepare("SELECT DATE(start_datetime) as event_date, title as event_name, event_type FROM schedules WHERE DATE(start_datetime) BETWEEN ? AND ? AND is_active = 1");
        $stmt->execute([$start_date, $end_date]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $calendar_events[$row['event_date']][] = $row;
        }
    } catch (Exception $e) {
        // Fall back to legacy events if schedules are unavailable
        try {
            $stmt = $db->prepare("SELECT event_date, event_name, event_type FROM events WHERE event_date BETWEEN ? AND ?");
            $stmt->execute([$start_date, $end_date]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $calendar_events[$row['event_date']][] = $row;
            }
        } catch (Exception $e2) {}
    }
    
    foreach($calendarHolidays as $holiday){$calendar_events[$holiday['date']][]=['event_name'=>$holiday['name'],'event_type'=>'holiday'];}

} catch (Exception $e) {}

// Calendar generation
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_weekday = date('w', $first_day);
$month_name = date('F Y', $first_day);

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
$book_url = $in_layout ? 'layout.php?page=book_appointment' : 'book_appointment.php';

require __DIR__.'/../includes/counseling/booking_view.php';
