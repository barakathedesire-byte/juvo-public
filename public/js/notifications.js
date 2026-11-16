/**
 * In-app notification handling
 * - Fetches unread count periodically
 * - Marks notifications as read
 * - Handles dropdown interactions
 */

(function () {
    'use strict';

    const NOTIFICATION_ENDPOINTS = {
        index: '/notifications',
        unreadCount: '/notifications/unread-count',
        markRead: '/notifications/mark-read',
    };

    let updateInterval = null;
    const UPDATE_INTERVAL_MS = 30000; // 30 seconds

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                ...options.headers,
            },
            credentials: 'same-origin',
            ...options,
        });

        if (!response.ok) {
            if (response.status === 401) {
                return null; // Not authenticated
            }
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    async function updateUnreadCount() {
        try {
            const data = await fetchJson(NOTIFICATION_ENDPOINTS.unreadCount);
            if (data && typeof data.count === 'number') {
                updateCountBadge(data.count);
            }
        } catch (e) {
            console.debug('Notification count update failed:', e);
        }
    }

    function updateCountBadge(count) {
        const badge = document.getElementById('notification-count');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    async function markNotificationsAsRead(ids = []) {
        try {
            await fetchJson(NOTIFICATION_ENDPOINTS.markRead, {
                method: 'POST',
                body: JSON.stringify({ ids }),
            });
            await updateUnreadCount();
        } catch (e) {
            console.error('Mark as read failed:', e);
        }
    }

    function initMarkAllRead() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.mark-all-read');
            if (!btn) return;

            e.preventDefault();
            markNotificationsAsRead([]);
        });
    }

    function initNotificationItemClick() {
        document.addEventListener('click', function (e) {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            const id = item.dataset.id;
            if (id && item.classList.contains('unread')) {
                // Mark as read when clicked
                markNotificationsAsRead([id]);
                item.classList.remove('unread');
                item.classList.add('read');
                const badge = item.querySelector('.badge');
                if (badge) badge.remove();
            }
        });
    }

    function initDropdownClose() {
        // Ensure dropdown doesn't close when clicking inside notification items
        document.addEventListener('click', function (e) {
            const dropdown = e.target.closest('.notification-dropdown');
            if (dropdown) {
                e.stopPropagation();
            }
        });
    }

    function startPeriodicUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(updateUnreadCount, UPDATE_INTERVAL_MS);
        // Initial fetch
        updateUnreadCount();
    }

    function stopPeriodicUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

    // Start when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        initMarkAllRead();
        initNotificationItemClick();
        initDropdownClose();

        // Only start periodic updates if user is logged in
        if (document.querySelector('.notification-bell')) {
            startPeriodicUpdates();
        }
    });

    // Handle page visibility changes
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            updateUnreadCount();
        }
    });

    // Expose for manual triggering
    window.JuvoNotifications = {
        updateCount: updateUnreadCount,
        markAsRead: markNotificationsAsRead,
        start: startPeriodicUpdates,
        stop: stopPeriodicUpdates,
    };
})();
