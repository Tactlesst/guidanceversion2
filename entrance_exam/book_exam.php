<?php
$in_layout = defined('IN_LAYOUT');
if (!$in_layout) {
    require_once '../config/database.php';
    require_once '../includes/session.php';
    checkLogin();
    checkRole(['examinee']);
}
$user_info = getUserInfo();
$dashboard_url = $in_layout ? 'layout.php?page=dashboard' : '../dashboard/index.php';
?>
<?php if (!$in_layout): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Entrance Exam - SRCB Guidance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
</head>
<body class="min-h-screen bg-gray-50">
<?php include '../dashboard/sidebar.php'; ?>
<div class="lg:ml-64 pt-14 lg:pt-0">
<?php endif; ?>
<div class="max-w-2xl mx-auto p-5">
    <h1 class="text-xl font-bold text-primary mb-5"><i class="fas fa-clipboard-list mr-2"></i>Book Entrance Exam</h1>
    <div class="bg-white rounded-xl shadow-sm p-10 text-center">
        <i class="fas fa-clipboard-list text-4xl text-gray-200 mb-3"></i>
        <p class="text-gray-400 text-sm">Entrance Exam booking — Content coming soon.</p>
        <a href="<?= $dashboard_url ?>" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-primary text-primary text-sm font-semibold hover:bg-primary hover:text-white transition-colors"><i class="fas fa-arrow-left"></i>Back to Dashboard</a>
    </div>
</div>
<?php if (!$in_layout): ?>
</div>
</body>
</html>
<?php endif; ?>
