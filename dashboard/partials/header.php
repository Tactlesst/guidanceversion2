<?php
$header_notifications=[];$unread_count=0;
try {
    $n=$db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$n->execute([$uid]);$unread_count=(int)$n->fetchColumn();
    $n=$db->prepare('SELECT title,message,created_at,is_read FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5');$n->execute([$uid]);$header_notifications=$n->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){}
?>
<header class="portal-header">
    <div class="portal-header-left">
        <button type="button" id="sidebarToggle" class="portal-icon-button portal-menu-toggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
        <div class="portal-breadcrumb"><span>Guidance office</span><i class="fas fa-chevron-right" aria-hidden="true"></i><strong><?=htmlspecialchars($page==='dashboard'?'Overview':$page_title)?></strong></div>
    </div>
    <div class="portal-header-actions">
        <span class="portal-today"><?=date('D, M j')?></span>
        <div class="portal-menu-anchor">
            <button type="button" id="notifBtn" class="portal-icon-button" aria-label="Notifications<?=$unread_count?' ('.$unread_count.' unread)':''?>" aria-controls="notifMenu" aria-expanded="false"><i class="far fa-bell" aria-hidden="true"></i><?php if($unread_count):?><span class="portal-notification-dot"></span><?php endif;?></button>
            <div id="notifMenu" class="portal-popover portal-notifications hidden">
                <div class="portal-popover-title"><strong>Notifications</strong><span><?=$unread_count?> unread</span></div>
                <?php foreach($header_notifications as $notification):?><div class="portal-notification-item"><strong><?=htmlspecialchars($notification['title'])?></strong><p><?=htmlspecialchars($notification['message'])?></p><time><?=date('M j, g:i A',strtotime($notification['created_at']))?></time></div><?php endforeach;?>
                <?php if(!$header_notifications):?><p class="portal-popover-empty">You’re all caught up. New updates will appear here.</p><?php endif;?>
            </div>
        </div>
        <div class="portal-menu-anchor">
            <button type="button" id="accountBtn" class="portal-account-button" aria-label="Account menu" aria-controls="accountMenu" aria-expanded="false"><span class="portal-avatar"><?=htmlspecialchars($initials)?></span><span class="portal-account-name"><?=htmlspecialchars($first_name)?></span><i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            <div id="accountMenu" class="portal-popover portal-account-menu hidden"><div class="portal-popover-title"><strong><?=htmlspecialchars($first_name.' '.$last_name)?></strong></div><a href="layout.php?page=profile"><i class="far fa-user" aria-hidden="true"></i> My profile</a><a href="../auth/logout.php"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a></div>
        </div>
    </div>
</header>
