// js/realtime.js

document.addEventListener('DOMContentLoaded', function() {
    const NOTIFICATION_POLL_INTERVAL = 5000; // 5 seconds
    const MESSAGE_POLL_INTERVAL = 3000;      // 3 seconds

    // Determine base path (where 'action/' directory is)
    // If we are in a subfolder like 'buyer/', we need '../'
    let basePath = '';
    const path = window.location.pathname;
    if (path.includes('/buyer/') || path.includes('/farmer/') || path.includes('/da/') || path.includes('/profile/') || path.includes('/header/') || path.includes('/footer/')) {
        basePath = '../';
    }

    // Poll for System Alerts (Broadcasts) state
    let globalLastNotifId = -1; // Use -1 to indicate uninitialized

    // Update Notification Badge
    function updateNotificationBadge() {
        fetch(basePath + 'action/Notification/get_unread_count.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                const badges = document.querySelectorAll('.header-item.position-relative a[href*="notification.php"] .badge, a.position-relative[href*="notification.php"] .badge');
                // Target bell icon specifically
                const bellLinks = document.querySelectorAll('a[href*="notification.php"]');
                updateBadgeForElements(bellLinks, data.unread_count);
            })
            .catch(err => console.error('Error fetching notification count:', err));
    }

    // Update Message Badge
    function updateMessageBadge() {
        fetch(basePath + 'action/Message/get_unread_count.php')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                const msgLinks = document.querySelectorAll('a[href*="message.php"]');
                updateBadgeForElements(msgLinks, data.unread_count);
            })
            .catch(err => console.error('Error fetching message count:', err));
    }

    function updateBadgeForElements(links, count) {
        links.forEach(link => {
            let badge = link.querySelector('.badge');
            if (count > 0) {
                const displayCount = count > 99 ? '99+' : count;
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge rounded-pill bg-danger';
                    link.appendChild(badge);
                }
                badge.textContent = displayCount;
                badge.style.display = 'inline-block';
            } else if (badge) {
                badge.style.display = 'none';
            }
        });
    }

    // Polling for counts
    setInterval(() => {
        updateNotificationBadge();
        updateMessageBadge();
        fetchConversations();
        pollSystemAlerts();
    }, NOTIFICATION_POLL_INTERVAL);
    
    updateNotificationBadge();
    updateMessageBadge();
    pollSystemAlerts();

    // Poll for System Alerts (Broadcasts)
    function initializeLastNotifId() {
        fetch(basePath + 'action/Notification/get_new_notifications.php?last_id=0')
            .then(r => r.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    globalLastNotifId = data.notifications[data.notifications.length - 1].id;
                } else {
                    globalLastNotifId = 0; // Ready to receive from scratch
                }
            })
            .catch(err => {
                console.error('Error initializing notifications:', err);
                globalLastNotifId = 0; // Fallback to 0 to at least try polling
            });
    }
    initializeLastNotifId();

    function pollSystemAlerts() {
        if (globalLastNotifId === -1) return; // Wait for initialization

        fetch(basePath + `action/Notification/get_new_notifications.php?last_id=${globalLastNotifId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text(); // Get as text first to check if it is JSON
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response from get_new_notifications.php:', text);
                    throw e;
                }
            })
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        // Update the last ID so we don't show the same alert twice
                                                    if (notif.id > globalLastNotifId) {
                                                        globalLastNotifId = notif.id;
                                                        // Only show modal for SYSTEM_ALERT or ANNOUNCEMENT, or any type except NEW_MESSAGE
                                                        if (notif.type === 'SYSTEM_ALERT' || notif.type === 'ANNOUNCEMENT' || notif.type !== 'NEW_MESSAGE') {
                                                            showSystemAlertModal(notif);
                                                        }
                                                    }                    });
                }
            })
            .catch(err => console.error('Error polling system alerts:', err));
    }

    function showSystemAlertModal(notif) {
        const modalEl = document.getElementById('systemAlertModal');
        if (!modalEl) return;

        const titleEl = document.getElementById('alertTitle');
        const bodyEl = document.getElementById('alertBody');
        const linkEl = document.getElementById('alertLink');

        if (titleEl) titleEl.textContent = notif.title;
        if (bodyEl) bodyEl.innerHTML = notif.body.replace(/\n/g, '<br>');
        
        if (linkEl) {
            if (notif.link && notif.link !== 'javascript:void(0)') {
                // If it's already a mark_read link, use it. Otherwise wrap it.
                if (notif.link.includes('mark_read.php')) {
                    linkEl.href = notif.link;
                } else {
                    linkEl.href = basePath + `action/Notification/mark_read.php?id=${notif.id}&redirect=${encodeURIComponent(notif.link)}`;
                }
                linkEl.style.display = 'inline-block';
            } else {
                linkEl.style.display = 'none';
            }
        }

        // Mark as read in background via AJAX
        fetch(basePath + `action/Notification/mark_read.php?id=${notif.id}`)
            .then(() => updateNotificationBadge())
            .catch(err => console.error('Error marking as read:', err));

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Refresh Conversation List
    function fetchConversations() {
        const convScroll = document.querySelector('.conversations-scroll');
        if (!convScroll) return;

        const view = convScroll.getAttribute('data-view') || 'active';
        const selectedId = convScroll.getAttribute('data-selected');

        fetch(basePath + `action/Message/get_conversations.php?view=${view}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.conversations) {
                    renderConversations(data.conversations, convScroll, selectedId, view);
                }
            })
            .catch(err => console.error('Error fetching conversations:', err));
    }

    function renderConversations(conversations, container, selectedId, view) {
        if (conversations.length === 0) {
            container.innerHTML = `<div class="text-center text-muted p-4"><small>No ${view} conversations.</small></div>`;
            return;
        }

        let html = '';
        conversations.forEach(conv => {
            const isActive = (selectedId == conv.conversation_id);
            const dispMsg = conv.last_message_deleted ? 'Message unsent' : (conv.last_message || 'No messages yet');
            const unreadBadge = conv.unread_count > 0 ? `<span class="badge rounded-pill bg-danger" style="font-size: 0.65rem;">${conv.unread_count}</span>` : '';
            const msgClass = (conv.last_message_deleted ? 'fst-italic' : '') + (conv.unread_count > 0 ? ' fw-bold text-dark' : '');
            
            // Profile Picture or Icon
            let avatarContent = '';
            if (conv.participant_profile_picture) {
                avatarContent = `<img src="${basePath}${conv.participant_profile_picture}" class="w-100 h-100" style="object-fit: cover;" onerror="this.parentElement.innerHTML='<i class=\'bi bi-person-circle\' style=\'font-size: 1.5rem;\'></i>'">`;
            } else {
                avatarContent = `<i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>`;
            }

            // Basic HTML escaping for safety
            const fullName = (conv.first_name + ' ' + conv.last_name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const safeMsg = dispMsg.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            html += `
                <a href="message.php?conv_id=${conv.conversation_id}&view=${view}" class="conv-item ${isActive ? 'active' : ''}">
                    <div class="conv-avatar overflow-hidden">${avatarContent}</div>
                    <div class="conv-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="conv-name text-truncate" style="max-width: 140px;">${fullName}</div>
                            ${unreadBadge}
                        </div>
                        <div class="conv-last-msg ${msgClass.trim()}">${safeMsg}</div>
                    </div>
                </a>
            `;
        });
        container.innerHTML = html;
    }

    // Update Notification List if on notification page
    const notificationList = document.querySelector('.notification-list');
    if (notificationList) {
        // Find current last ID
        let lastNotifId = 0;
        // In the original PHP, we didn't add the ID to the DOM.
        // We'll need to update the PHP to add it, or just start polling from 0 and handle duplicates (not ideal).
        // Let's assume we'll update the PHP to add data-id.
        
        const getNewNotifications = () => {
            const items = notificationList.querySelectorAll('.notification-item[data-id]');
            let currentLastId = 0;
            items.forEach(item => {
                const id = parseInt(item.getAttribute('data-id'));
                if (id > currentLastId) currentLastId = id;
            });

            fetch(basePath + `action/Notification/get_new_notifications.php?last_id=${currentLastId}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return response.text();
                })
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON from get_new_notifications.php (list):', text);
                        throw e;
                    }
                })
                .then(data => {
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.reverse().forEach(notif => {
                            appendNotification(notif);
                        });
                        // Remove "no notifications" message if it exists
                        const emptyMsg = notificationList.querySelector('.text-center.text-muted');
                        if (emptyMsg) emptyMsg.remove();
                    }
                })
                .catch(err => console.error('Error fetching new notifications:', err));
        };

        function getIconClass(type) {
            switch (type) {
                case 'NEW_MESSAGE': return 'bi-chat-dots-fill';
                case 'INTEREST_ACCEPTED': return 'bi-check-circle-fill';
                case 'POST_UPDATE': return 'bi-arrow-up-circle-fill';
                case 'ANNOUNCEMENT': return 'bi-megaphone-fill';
                case 'SYSTEM_ALERT': return 'bi-exclamation-triangle-fill';
                default: return 'bi-info-circle-fill';
            }
        }

        function appendNotification(notif) {
            // Skip rendering NEW_MESSAGE notifications
            if (notif.type === 'NEW_MESSAGE') {
                return;
            }
            const item = document.createElement('div');
            item.className = `notification-item ${!notif.is_read ? 'notification-unread' : ''} clickable`;
            item.setAttribute('data-id', notif.id);
            item.setAttribute('data-title', notif.title);
            item.setAttribute('data-body', notif.body);
            
            // Re-calculate viewLink for the attribute
            const viewLink = notif.link ? `${basePath}action/Notification/mark_read.php?id=${notif.id}&redirect=${encodeURIComponent(notif.link)}` : '';
            item.setAttribute('data-link', viewLink);
            
            const timeStr = new Date(notif.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });

            item.innerHTML = `
                <div class="notification-icon">
                    <i class="bi ${getIconClass(notif.type)}"></i>
                </div>
                <div class="notification-content">
                    <h6 class="mb-0">${notif.title}</h6>
                    <p class="mb-0 text-truncate" style="max-width: 500px;">${notif.body}</p>
                    <span class="time">${timeStr}</span>
                </div>
                <div class="notification-actions">
                    <a href="${viewLink}" class="btn btn-sm btn-primary ${!notif.link ? 'disabled' : ''}">View</a>
                    <a href="${basePath}action/Notification/dismiss.php?id=${notif.id}" class="btn btn-sm btn-outline-secondary" title="Dismiss"><i class="bi bi-x"></i></a>
                </div>
            `;
            notificationList.prepend(item);
        }

        setInterval(getNewNotifications, NOTIFICATION_POLL_INTERVAL);

        // Click handler for notification items to show in modal
        notificationList.addEventListener('click', function(e) {
            const item = e.target.closest('.notification-item');
            if (!item) return;

            // If they clicked the Dismiss button (outline-secondary), let it happen normally
            if (e.target.closest('.btn-outline-secondary')) return;

            // If they clicked the 'View' button (btn-primary)
            const viewBtn = e.target.closest('.btn-primary');
            if (viewBtn) {
                const link = item.getAttribute('data-link');
                // If the link points to something other than notification.php, let the redirect happen
                if (link && !link.includes('redirect=notification.php')) {
                    return; // Follow the link
                }
            }

            // Otherwise, show the modal
            e.preventDefault();
            const notif = {
                id: item.getAttribute('data-id'),
                title: item.getAttribute('data-title'),
                body: item.getAttribute('data-body'),
                link: item.getAttribute('data-link')
            };

            // Use the same modal logic as real-time alerts
            showSystemAlertModal(notif);
            
            // Mark as read in UI immediately
            item.classList.remove('notification-unread');
        });
    }

    // Handle Real-time Messages if on message page
    const messageContainer = document.getElementById('message-container');
    if (messageContainer) {
        let convId = messageContainer.getAttribute('data-conv-id');
        if (!convId) {
            const urlParams = new URLSearchParams(window.location.search);
            convId = urlParams.get('conv_id');
        }
        
        if (convId) {
            function fetchNewMessages() {
                const currentLastId = messageContainer.getAttribute('data-last-id') || 0;
                
                fetch(basePath + `action/Message/get_new_messages.php?conv_id=${convId}&last_id=${currentLastId}&update_read=true`)
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        // Handle new messages
                        if (data.messages && data.messages.length > 0) {
                            data.messages.forEach(msg => {
                                appendMessage(msg);
                                messageContainer.setAttribute('data-last-id', msg.id);
                            });
                            messageContainer.scrollTop = messageContainer.scrollHeight;
                        }

                        // Handle deleted messages sync
                        if (data.deleted_ids && data.deleted_ids.length > 0) {
                            data.deleted_ids.forEach(id => {
                                updateDeletedMessageUI(id);
                            });
                        }
                    })
                    .catch(err => console.error('Error fetching messages:', err));
            }

            function updateDeletedMessageUI(messageId) {
                const row = document.querySelector(`.message-row[data-id="${messageId}"]`);
                if (!row) return;

                const body = row.querySelector('.message-body');
                if (body && !body.classList.contains('message-deleted')) {
                    const isSent = row.classList.contains('sent');
                    body.classList.add('message-deleted');
                    body.innerHTML = isSent ? "You unsent a message" : "Message unsent";
                    
                    // Remove the trash icon button if it exists
                    const actions = row.querySelector('.message-actions');
                    if (actions) actions.remove();
                }
            }

            // Handle Unsend button clicks via AJAX
            messageContainer.addEventListener('click', function(e) {
                const btn = e.target.closest('.action-icon-btn');
                if (btn && btn.href.includes('action/Message/delete.php')) {
                    e.preventDefault();
                    if (confirm('Unsend this message?')) {
                        const url = new URL(btn.href);
                        url.searchParams.set('ajax', 'true');
                        
                        fetch(url.toString())
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const row = btn.closest('.message-row');
                                    if (row) {
                                        const msgId = row.getAttribute('data-id');
                                        updateDeletedMessageUI(msgId);
                                    }
                                }
                            })
                            .catch(err => console.error('Error unsending message:', err));
                    }
                }
            });

            function appendMessage(msg) {
                // Check if message already exists (to avoid duplicates if polling overlaps)
                if (document.querySelector(`.message-row[data-id="${msg.id}"]`)) return;

                const isSent = (typeof currentUserId !== 'undefined') ? (msg.sender_id == currentUserId) : false;
                const row = document.createElement('div');
                row.className = `message-row ${isSent ? 'sent' : 'received'}`;
                row.setAttribute('data-id', msg.id);
                
                let avatarHtml = '';
                if (!isSent) {
                    let avatarContent = '';
                    if (typeof participantProfilePicture !== 'undefined' && participantProfilePicture) {
                        avatarContent = `<img src="${basePath}${participantProfilePicture}" class="w-100 h-100" style="object-fit: cover;">`;
                    } else {
                        avatarContent = `<i class="bi bi-person-circle" style="font-size: 1.2rem;"></i>`;
                    }
                    avatarHtml = `<div class="message-avatar overflow-hidden">${avatarContent}</div>`;
                }

                const time = new Date(msg.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });

                row.innerHTML = `
                    ${avatarHtml}
                    <div class="message ${isSent ? 'sent' : 'received'}">
                        <div class="message-body ${msg.is_deleted ? 'message-deleted' : ''}">
                            ${msg.is_deleted ? (isSent ? "You unsent a message" : "Message unsent") : msg.body.replace(/\n/g, '<br>')}
                        </div>
                        ${(!msg.is_deleted && isSent) ? `
                            <div class="message-actions">
                                <a href="${basePath}action/Message/delete.php?message_id=${msg.id}&conv_id=${convId}" class="action-icon-btn" title="Unsend" onclick="return confirm('Unsend this message?')"><i class="bi bi-trash"></i></a>
                            </div>
                        ` : ''}
                        <div class="message-time">${time}</div>
                    </div>
                `;
                messageContainer.appendChild(row);
            }

            setInterval(fetchNewMessages, MESSAGE_POLL_INTERVAL);
        }
    }
});
