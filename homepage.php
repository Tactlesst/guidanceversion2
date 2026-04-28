<?php
require_once __DIR__ . '/config/database.php';

$db = null;
$homepage_announcement = null;
$announcements = [];

try {
    $db = (new Database())->getConnection();
    
    // Featured homepage announcement
    $stmt = $db->prepare("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
                          FROM announcements a 
                          LEFT JOIN users u ON a.created_by = u.id 
                          WHERE a.is_active = 1 AND a.location = '__HOMEPAGE__' 
                          ORDER BY a.updated_at DESC, a.created_at DESC LIMIT 1");
    $stmt->execute();
    $homepage_announcement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Latest announcements
    $stmt = $db->prepare("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
                          FROM announcements a 
                          LEFT JOIN users u ON a.created_by = u.id 
                          WHERE a.is_active = 1 AND a.target_audience IN ('all_users', 'all_students') 
                          ORDER BY a.created_at DESC LIMIT 20");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silent fail - page still works without DB
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRCB Guidance - Announcements</title>
    <link rel="icon" type="image/x-icon" href="assets/images/srcblogo.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{primary:'#163269','primary-dark':'#3a56c4'}}}}</script>
    <style>
        .announcement-message { white-space: pre-wrap; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">

<!-- Hero Section -->
<header class="bg-gradient-to-r from-primary to-blue-600 text-white py-14">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="assets/images/srcblogo.png" alt="SRCB" class="w-14 h-14 rounded-full bg-white object-cover shadow-lg" onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight">St. Rita's College of Balingasag</h1>
                        <p class="text-blue-100 text-sm font-medium">Guidance Announcements</p>
                    </div>
                </div>
                <p class="text-blue-100 text-sm mt-2">View updates and official announcements from the Guidance Office.</p>
            </div>
            <div class="flex gap-2">
                <a href="auth/login.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg text-sm font-semibold transition-colors border border-white/30">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login
                </a>
                <a href="auth/register.php" class="px-4 py-2 bg-white text-primary hover:bg-gray-100 rounded-lg text-sm font-bold transition-colors shadow-lg">
                    <i class="fas fa-user-plus mr-1"></i>Entrance Exam Registration
                </a>
            </div>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Featured Announcement -->
    <?php if (!empty($homepage_announcement)): ?>
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 border-l-4 border-primary">
        <div class="flex items-start gap-4">
            <div class="text-primary text-2xl mt-1"><i class="fas fa-bullhorn"></i></div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($homepage_announcement['title']) ?></h2>
                    <span class="px-2 py-0.5 bg-blue-50 text-primary text-xs font-semibold rounded border border-blue-100">Featured</span>
                </div>
                <div class="text-gray-700 announcement-message leading-relaxed"><?= htmlspecialchars($homepage_announcement['message']) ?></div>
                <?php if (!empty($homepage_announcement['updated_at'])): ?>
                <p class="text-xs text-gray-400 mt-3">Updated <?= date('M j, Y', strtotime($homepage_announcement['updated_at'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Latest Announcements -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-extrabold text-gray-900">Latest Announcements</h2>
        <a href="auth/login.php" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm font-semibold transition-colors shadow">
            <i class="fas fa-lock mr-1"></i>Go to Dashboard
        </a>
    </div>

    <?php if (empty($announcements)): ?>
    <div class="bg-white rounded-2xl shadow-sm p-8 text-center">
        <div class="text-gray-400">
            <i class="fas fa-inbox text-4xl mb-3"></i>
            <p>No announcements available right now.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($announcements as $a): ?>
        <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                <h3 class="text-base font-bold text-gray-900"><?= htmlspecialchars($a['title']) ?></h3>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($a['event_type'])): ?>
                    <span class="px-2 py-0.5 bg-blue-50 text-primary text-xs font-semibold rounded border border-blue-100">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $a['event_type']))) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($a['event_date'])): ?>
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded border border-gray-200">
                        <i class="fas fa-calendar mr-1"></i><?= date('M j, Y', strtotime($a['event_date'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-gray-700 text-sm announcement-message leading-relaxed"><?= htmlspecialchars($a['message']) ?></div>
            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                <div class="text-xs text-gray-400">
                    Posted <?= date('M j, Y', strtotime($a['created_at'])) ?>
                    <?php if (!empty($a['creator_name'])): ?>by <?= htmlspecialchars($a['creator_name']) ?><?php endif; ?>
                </div>
                <?php if (!empty($a['location']) && $a['location'] !== '__HOMEPAGE__'): ?>
                <span class="text-xs text-gray-400"><i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($a['location']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <div class="flex items-center gap-2">
                <img src="assets/images/srcblogo.png" alt="SRCB" class="w-8 h-8 rounded-full object-cover" onerror="this.style.display='none'">
                <span>&copy; <?= date('Y') ?> St. Rita's College of Balingasag. All rights reserved.</span>
            </div>
            <div class="flex gap-4">
                <a href="auth/login.php" class="hover:text-primary transition-colors">Login</a>
                <a href="auth/register.php" class="hover:text-primary transition-colors">Register</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
