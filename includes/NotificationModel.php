<?php
// includes/NotificationModel.php

class NotificationModel {

    /**
     * Creates a new notification for a user.
     *
     * @param mysqli $conn The database connection object.
     * @param int $user_id The ID of the user to notify.
     * @param string $type A category for the notification (e.g., 'NEW_MESSAGE', 'INTEREST_RECEIVED').
     * @param string $title The title of the notification.
     * @param string $body The main content of the notification.
     * @param string|null $link An optional URL for the notification to link to.
     * @return bool True on success, false on failure.
     */
    public static function createNotification(mysqli $conn, int $user_id, string $type, string $title, string $body, ?string $link = null): bool {
        $sql = "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("issss", $user_id, $type, $title, $body, $link);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Fetches all notifications for a specific user.
     *
     * @param mysqli $conn The database connection object.
     * @param int $user_id The ID of the user.
     * @return array An array of notification rows.
     */
    public static function getNotificationsForUser(mysqli $conn, int $user_id): array {
        $sql = "SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notifications;
    }
    
    /**
     * Marks a single notification as read.
     *
     * @param mysqli $conn
     * @param int $notification_id
     * @param int $user_id
     * @return bool
     */
    public static function markAsRead(mysqli $conn, int $notification_id, int $user_id): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Marks all unread notifications for a user as read.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @return bool
     */
    public static function markAllAsRead(mysqli $conn, int $user_id): bool {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    /**
     * Deletes a single notification.
     *
     * @param mysqli $conn
     * @param int $notification_id
     * @param int $user_id
     * @return bool
     */
    public static function dismissNotification(mysqli $conn, int $notification_id, int $user_id): bool {
        $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Deletes all notifications for a user.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @return bool
     */
    public static function clearAllNotifications(mysqli $conn, int $user_id): bool {
        $sql = "DELETE FROM notifications WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    
    /**
     * Counts unread notifications for a user.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @return int
     */
    public static function countUnread(mysqli $conn, int $user_id): int {
        $sql = "SELECT COUNT(id) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'NEW_MESSAGE'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['unread_count'] ?? 0;
    }
}
