<?php
// Profile content partial — loaded by layout.php
// Redirect to existing profile page (standalone) until converted
?>
<div class="flex items-center justify-between mb-5">
    <h1 class="text-xl font-bold text-primary"><i class="fas fa-user mr-2"></i>My Profile</h1>
</div>
<div class="bg-white rounded-xl shadow-sm p-6">
    <?php
    $sp = null;
    try { $s = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?"); $s->execute([$uid]); $sp = $s->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    $u = null;
    try { $u_stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $u_stmt->execute([$uid]); $u = $u_stmt->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    ?>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-4">
            <h3 class="font-bold text-primary text-sm border-b pb-2">Account Information</h3>
            <div><label class="text-xs font-medium text-gray-500">Email</label><p class="text-sm"><?= htmlspecialchars($u['email']??'—') ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Role</label><p class="text-sm"><?= ucfirst(str_replace('_',' ',$u['role']??'—')) ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Member Since</label><p class="text-sm"><?= date('F j, Y', strtotime($u['created_at']??'now')) ?></p></div>
        </div>
        <?php if($sp): ?>
        <div class="space-y-4">
            <h3 class="font-bold text-primary text-sm border-b pb-2">Academic Information</h3>
            <div><label class="text-xs font-medium text-gray-500">Student ID</label><p class="text-sm"><?= htmlspecialchars($sp['student_id']??'—') ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Department</label><p class="text-sm"><?= htmlspecialchars($sp['department']??'—') ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Grade Level</label><p class="text-sm"><?= htmlspecialchars($sp['grade_level']??'—') ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Program/Strand</label><p class="text-sm"><?= htmlspecialchars(($sp['program']??'') ?: ($sp['strand']??'—')) ?></p></div>
            <div><label class="text-xs font-medium text-gray-500">Contact</label><p class="text-sm"><?= htmlspecialchars($sp['contact_number']??'—') ?></p></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-6 pt-4 border-t">
        <a href="profile.php" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors"><i class="fas fa-edit mr-1"></i>Edit Profile</a>
    </div>
</div>
