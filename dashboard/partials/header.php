<?php
$unread_count = $unread_count ?? 0;
$display_name = trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
$display_role = strtoupper(str_replace('_', ' ', (string)($role ?? '')));
?>
<header class="fixed top-0 left-0 right-0 h-14 bg-white border-b border-gray-100 z-40">
    <div class="h-full flex items-center justify-between px-4 lg:pl-6 lg:pr-6 lg:ml-64">
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="lg:hidden text-primary text-xl w-10 h-10 rounded-lg hover:bg-gray-50 transition-colors" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>

        </div>

        <div class="flex items-center gap-2">
            <div class="relative">
                <button id="notifBtn" class="w-10 h-10 rounded-lg hover:bg-gray-50 transition-colors text-gray-600" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ((int)$unread_count > 0): ?>
                        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                            <?= (int)$unread_count > 99 ? '99+' : (int)$unread_count ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div id="notifMenu" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <div class="font-semibold text-sm text-gray-800">Notifications</div>
                        <div class="text-[11px] text-gray-400">No notification system wired yet</div>
                    </div>
                    <div class="p-4 text-sm text-gray-500">You're all caught up.</div>
                </div>
            </div>

            <div class="relative">
                <button id="accountBtn" class="flex items-center gap-2 pl-2 pr-3 h-10 rounded-lg hover:bg-gray-50 transition-colors" aria-label="Account menu">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-xs font-bold text-white">
                        <?= $initials ?>
                    </div>
                    <div class="hidden sm:flex flex-col items-start leading-tight">
                        <div class="text-xs font-semibold text-gray-800 max-w-[160px] truncate"><?= htmlspecialchars($display_name ?: 'User') ?></div>
                        <div class="text-[10px] text-gray-400 uppercase"><?= htmlspecialchars($display_role) ?></div>
                    </div>
                    <i class="fas fa-chevron-down text-[10px] text-gray-400 hidden sm:block"></i>
                </button>
                <div id="accountMenu" class="hidden absolute right-0 mt-2 w-56 bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden">
                    <a href="layout.php?page=profile" class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-user text-gray-400"></i><span>My Profile</span>
                    </a>
                    <div class="h-px bg-gray-100"></div>
                    <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
