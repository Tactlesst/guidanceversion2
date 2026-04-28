/**
 * Real-time Notification System
 * Checks for new notifications every 30 seconds and updates the UI
 */

class RealTimeNotifications {
    constructor() {
        this.checkInterval = 30000; // 30 seconds
        this.lastUnreadCount = 0;
        this.isActive = true;
        this.intervalId = null;
        
        this.init();
    }
    
    init() {
        // Start checking for notifications
        this.startChecking();
        
        // Pause when tab is not visible to save resources
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseChecking();
            } else {
                this.resumeChecking();
            }
        });
        
        // Initial check
        this.checkNotifications();
    }
    
    startChecking() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        
        this.intervalId = setInterval(() => {
            this.checkNotifications();
        }, this.checkInterval);
        
        this.isActive = true;
    }
    
    pauseChecking() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        this.isActive = false;
    }
    
    resumeChecking() {
        if (!this.isActive) {
            this.startChecking();
            this.checkNotifications(); // Check immediately when resuming
        }
    }
    
    async checkNotifications() {
        try {
            const response = await fetch('../api/check_notifications.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.updateNotificationBadge(data.unread_count);
                
                // Show notification popup for new notifications
                if (data.unread_count > this.lastUnreadCount && this.lastUnreadCount > 0) {
                    this.showNewNotificationAlert(data.latest_notifications[0]);
                }
                
                this.lastUnreadCount = data.unread_count;
            }
            
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }
    
    updateNotificationBadge(unreadCount) {
        // Update notification badge in navbar
        const notificationBadges = document.querySelectorAll('.notification-badge, .badge-notification');
        
        notificationBadges.forEach(badge => {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'inline-block';
                
                // Add pulse animation for new notifications
                if (unreadCount > this.lastUnreadCount) {
                    badge.classList.add('pulse-animation');
                    setTimeout(() => {
                        badge.classList.remove('pulse-animation');
                    }, 2000);
                }
            } else {
                badge.style.display = 'none';
            }
        });
        
        // Update notification icon
        const notificationIcons = document.querySelectorAll('.notification-icon');
        notificationIcons.forEach(icon => {
            if (unreadCount > 0) {
                icon.classList.add('has-notifications');
            } else {
                icon.classList.remove('has-notifications');
            }
        });
    }
    
    showNewNotificationAlert(notification) {
        if (!notification || notification.is_read == 1) return;
        
        // Create notification toast
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas fa-bell text-primary"></i>
                </div>
                <div class="toast-body">
                    <div class="toast-title">${notification.title}</div>
                    <div class="toast-message">${notification.message}</div>
                    <div class="toast-time">${notification.time_ago}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Add styles if not already added
        this.addToastStyles();
        
        // Add to page
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
    
    addToastStyles() {
        if (document.getElementById('notification-toast-styles')) return;
        
        const styles = document.createElement('style');
        styles.id = 'notification-toast-styles';
        styles.textContent = `
            .notification-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border-left: 4px solid #4361ee;
                min-width: 320px;
                max-width: 400px;
                animation: slideInRight 0.3s ease-out;
            }
            
            .toast-content {
                display: flex;
                align-items: flex-start;
                padding: 16px;
                gap: 12px;
            }
            
            .toast-icon {
                font-size: 20px;
                margin-top: 2px;
            }
            
            .toast-body {
                flex: 1;
            }
            
            .toast-title {
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 4px;
                font-size: 14px;
            }
            
            .toast-message {
                color: #6b7280;
                font-size: 13px;
                line-height: 1.4;
                margin-bottom: 4px;
            }
            
            .toast-time {
                color: #9ca3af;
                font-size: 11px;
            }
            
            .toast-close {
                background: none;
                border: none;
                color: #9ca3af;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
            }
            
            .toast-close:hover {
                background: #f3f4f6;
                color: #6b7280;
            }
            
            .pulse-animation {
                animation: pulse 1s ease-in-out 2;
            }
            
            .notification-icon.has-notifications {
                color: #4361ee !important;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    // Method to manually trigger notification check
    checkNow() {
        this.checkNotifications();
    }
    
    // Method to stop all checking (for cleanup)
    destroy() {
        this.pauseChecking();
    }
}

// Initialize real-time notifications when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize for students, guidance advocates, admins, and super admins
    const userRole = document.body.getAttribute('data-user-role');
    if (userRole && ['student', 'guidance_advocate', 'admin', 'super_admin'].includes(userRole)) {
        window.realTimeNotifications = new RealTimeNotifications();
        
        // Add manual refresh button functionality
        const refreshButtons = document.querySelectorAll('.refresh-notifications');
        refreshButtons.forEach(button => {
            button.addEventListener('click', () => {
                window.realTimeNotifications.checkNow();
            });
        });
    }
});
