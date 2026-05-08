# Dashboard Structure Documentation

## Overview
The guidanceversion2 system uses a modular, role-based architecture with a central layout wrapper.

---

## Main Files

### Core Files
- **`layout.php`** - Main wrapper that includes sidebar, header, and page content
- **`partials/sidebar.php`** - Role-based sidebar navigation
- **`partials/header.php`** - Top navigation bar with notifications and profile

### Legacy Files (Can be removed)
- `super_admin_dashboard.php` - Old standalone super admin dashboard
- `admin_dashboard.php` - Old standalone admin dashboard
- `guidance_dashboard.php` - Old standalone guidance dashboard
- `student_dashboard.php` - Old standalone student dashboard
- `examinee_dashboard.php` - Old standalone examinee dashboard

---

## Folder Structure

```
guidanceversion2/
├── dashboard/
│   ├── layout.php                    # Main wrapper
│   ├── partials/
│   │   ├── sidebar.php              # Sidebar component
│   │   └── header.php               # Header component
│   ├── pages/
│   │   ├── index.php                # Main dashboard (all roles)
│   │   ├── profile.php              # User profile
│   │   ├── admin/                   # Super Admin & Admin pages
│   │   ├── student/                 # Student-specific pages (future)
│   │   ├── examinee/                # Examinee-specific pages
│   │   │   ├── book_exam.php        # Book entrance exam
│   │   │   ├── view_exam_results.php # View exam results
│   │   │   └── view_application.php  # View application status
│   │   └── reports/                 # Reports & Analytics (future)
│
├── pds/                             # Personal Data Sheet Module
│   ├── fill_pds.php                 # Fill/Edit PDS form
│   └── view_pds.php                 # View completed PDS
│
├── counseling/                      # Counseling Module
│   ├── book_appointment.php         # Book counseling appointment
│   └── view_appointments.php        # View appointment history
│
├── entrance_exam/                   # Entrance Exam Module
│   ├── book_exam.php                # Book entrance exam
│   ├── view_exam_results.php        # View exam results
│   └── view_application.php         # View application status
│
├── surveys/                         # Surveys Module
│   ├── multiple_intelligence_survey.php  # MI Survey form
│   └── survey_thankyou.php          # Survey results page
│
├── auth/                            # Authentication
│   ├── login.php                    # Login page
│   ├── register.php                 # Registration
│   └── logout.php                   # Logout handler
│
├── classes/                         # PHP Classes
├── ajax/                            # AJAX handlers
├── cron/                            # Cron jobs
└── database/                        # Database migrations
```

---

## Role-Based Access

### Super Admin
**Sidebar Menu:**
- Dashboard
- User Management
- Academic Settings
- Backup & Restore
- System Logs

**Pages:**
- `pages/index.php` (dashboard home)
- `pages/admin/user_management.php`
- `pages/admin/academic_settings.php`
- `pages/admin/backup_restore.php`
- `pages/admin/system_logs.php`

---

### Admin / Guidance Counselor
**Sidebar Menu:**
- Dashboard
- Student Management
- Counseling Management
- Entrance Exam Appointments
- Announcements
- Schedule
- Daily Booking Limits
- System Settings (Admin only)
- Reports & Analytics

**Pages:**
- `pages/index.php` (dashboard home)
- `pages/admin/student_records.php`
- `pages/admin/manage_counseling.php`
- `pages/admin/manage_exams.php`
- `pages/admin/manage_announcements.php`
- `pages/admin/schedules.php`
- `pages/admin/daily_limits.php`
- `pages/admin/system_settings.php` (Admin only)
- `pages/reports/counseling_reports.php`
- `pages/reports/olsat_reports.php`

---

### Guidance Advocate
**Sidebar Menu:**
- Dashboard
- Student Management
- Counseling Management
- Entrance Exam Appointments
- Announcements
- Schedule
- Daily Booking Limits
- Reports & Analytics

**Pages:**
- Same as Admin but without System Settings

---

### Student
**Sidebar Menu:**
- Dashboard
- Personal Data Sheet
- Counseling Services (dropdown)
  - Book Appointment
  - View Appointments
- Multiple Intelligence Survey (conditional: Grade 11, 12, College 1-4)

**Pages:**
- `pages/index.php` (dashboard home)
- `pds/fill_pds.php`
- `pds/view_pds.php`
- `counseling/book_appointment.php`
- `counseling/view_appointments.php`
- `surveys/multiple_intelligence_survey.php`
- `surveys/survey_thankyou.php`

---

### Examinee
**Sidebar Menu:**
- Dashboard
- Book Entrance Exam (or View Exam Results if completed)
- My Application Status

**Pages:**
- `pages/index.php` (dashboard home)
- `pages/examinee/book_exam.php`
- `pages/examinee/view_exam_results.php`
- `pages/examinee/view_application.php`

---

## URL Structure

All pages are accessed through the layout wrapper:

```
http://localhost/guidanceversion2/dashboard/layout.php?page=PAGE_NAME
```

### Examples:
- Dashboard: `layout.php?page=dashboard`
- User Management: `layout.php?page=user_management`
- Fill PDS: `layout.php?page=fill_pds`
- Book Appointment: `layout.php?page=book_appointment`
- MI Survey: `layout.php?page=multiple_intelligence_survey`

---

## How It Works

1. **User logs in** → `auth/login.php`
2. **Redirects to** → `dashboard/layout.php`
3. **Layout.php:**
   - Checks user session and role
   - Includes `partials/sidebar.php` (shows role-specific menu)
   - Includes `partials/header.php` (top bar)
   - Includes the requested page content based on `?page=` parameter
4. **Page content** is rendered inside the layout wrapper

---

## Adding New Pages

### For Admin Pages:
1. Create file in `dashboard/pages/admin/your_page.php`
2. Add to `$pages` array in `layout.php`
3. Add to `$file_map` array in `layout.php`
4. Add menu item in `partials/sidebar.php` under admin section

### For Student Pages:
1. Create file in appropriate module folder (pds, counseling, surveys, etc.)
2. Add to `$pages` and `$file_map` in `layout.php`
3. Add menu item in `partials/sidebar.php` under student section

### For Examinee Pages:
1. Create file in `dashboard/pages/examinee/` folder
2. Add to `$pages` and `$file_map` in `layout.php`
3. Add menu item in `partials/sidebar.php` under examinee section

---

## Database Tables

### Multiple Intelligence Survey
- Table: `multiple_intelligence_survey`
- Migration: `database/migrations/create_multiple_intelligence_survey_table.sql`

---

## Notes

- All pages included in layout.php should NOT have full HTML structure (no `<html>`, `<head>`, `<body>` tags)
- Pages should start with PHP logic and then output content
- Use `IN_LAYOUT` constant check for security
- Session and database connection are available via `$db` and `$uid` variables
- Role checking is handled by layout.php

---

## Maintenance

### To Clean Up:
You can safely delete these legacy standalone dashboard files:
- `super_admin_dashboard.php`
- `admin_dashboard.php`
- `guidance_dashboard.php`
- `student_dashboard.php`
- `examinee_dashboard.php`

They are no longer used since everything now goes through `layout.php`.
