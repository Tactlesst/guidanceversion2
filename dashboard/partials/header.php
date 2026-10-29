<?php
require_once __DIR__.'/../../classes/notifications/NotificationInbox.php';
$_SESSION['notification_csrf']=$_SESSION['notification_csrf']??bin2hex(random_bytes(32));$header_notifications=[];$unread_count=0;
try{$headerInbox=new NotificationInbox($db);$headerFeed=$headerInbox->feed((int)$user_info['id']);$header_notifications=$headerFeed['preview'];$unread_count=$headerFeed['unread_count'];}catch(Throwable $e){}
?>
<header class="portal-header">
    <div class="portal-header-left">
        <button type="button" id="sidebarToggle" class="portal-icon-button portal-menu-toggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
        <div class="portal-breadcrumb"><span>Guidance office</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong><?=htmlspecialchars($page==='dashboard'?'Overview':$page_title)?></strong></div>
    </div>
    <div class="portal-header-actions">
        <span class="portal-today"><?=date('D, M j')?></span>
        <div class="portal-menu-anchor">
            <button type="button" id="notifBtn" class="portal-icon-button" aria-label="Notifications (<?=$unread_count?> unread)" aria-controls="notifMenu" aria-expanded="false"><i class="far fa-bell" aria-hidden="true"></i><?php if($unread_count):?><span class="pn-count"><?=$unread_count>99?'99+':$unread_count?></span><?php endif;?></button>
            <div id="notifMenu" class="portal-popover portal-notifications hidden"><div class="pn-popover-head"><div><strong>Notifications</strong><span data-notification-count><?=$unread_count?> unread</span></div><button type="button" class="pn-small-button" data-notification-read-all>Mark all as read</button></div><div class="pn-preview-list" data-notification-preview><?php foreach($header_notifications as $notification)echo NotificationInbox::item($notification);if(!$header_notifications):?><p class="pn-empty">You’re all caught up. New updates will appear here.</p><?php endif;?></div><a class="pn-popover-footer" href="layout.php?page=notifications">View all notifications</a></div>
        </div>
        <div class="portal-menu-anchor">
            <button type="button" id="accountBtn" class="portal-account-button" aria-label="Account menu" aria-controls="accountMenu" aria-expanded="false"><span class="portal-avatar"><?=htmlspecialchars($initials)?></span><span class="portal-account-name"><?=htmlspecialchars($first_name)?></span><i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            <div id="accountMenu" class="portal-popover portal-account-menu hidden"><div class="portal-popover-title"><strong><?=htmlspecialchars($first_name.' '.$last_name)?></strong></div><a href="layout.php?page=profile"><i class="far fa-user" aria-hidden="true"></i> My profile</a><a href="../auth/logout.php"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a></div>
        </div>
    </div>
</header>
