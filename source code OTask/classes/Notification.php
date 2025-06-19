<?php

class Notification {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function createNotification($userId, $type, $title, $message, $relatedId = null, $senderId = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (user_id, sender_id, type, title, message, related_id)
            VALUES (:user_id, :sender_id, :type, :title, :message, :related_id)
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':related_id', $relatedId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getNotificationById($notificationId) {
        $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE id = :id");
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNotificationsForUser($userId, $typeFilter = '', $statusFilter = '', $daysFilter = '', $limit = 10, $offset = 0) {
        $sql = "SELECT id, user_id, sender_id, type, title, message, is_read, created_at, related_id FROM notifications WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if (!empty($typeFilter)) {
            $sql .= " AND type = :type_filter";
            $params[':type_filter'] = $typeFilter;
        }

        if ($statusFilter === 'read') {
            $sql .= " AND is_read = 1";
        } elseif ($statusFilter === 'unread') {
            $sql .= " AND is_read = 0";
        }

        if (!empty($daysFilter)) {
            switch ($daysFilter) {
                case 'today':
                    $sql .= " AND DATE(created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $sql .= " AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
                    break;
                case 'last_7_days':
                    $sql .= " AND created_at >= CURDATE() - INTERVAL 7 DAY";
                    break;
                case 'last_30_days':
                    $sql .= " AND created_at >= CURDATE() - INTERVAL 30 DAY";
                    break;
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNotificationCountForUserFiltered($userId, $typeFilter = '', $statusFilter = '', $daysFilter = '') {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if (!empty($typeFilter)) {
            $sql .= " AND type = :type_filter";
            $params[':type_filter'] = $typeFilter;
        }

        if ($statusFilter === 'read') {
            $sql .= " AND is_read = 1";
        } elseif ($statusFilter === 'unread') {
            $sql .= " AND is_read = 0";
        }

        if (!empty($daysFilter)) {
            switch ($daysFilter) {
                case 'today':
                    $sql .= " AND DATE(created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $sql .= " AND DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
                    break;
                case 'last_7_days':
                    $sql .= " AND created_at >= CURDATE() - INTERVAL 7 DAY";
                    break;
                case 'last_30_days':
                    $sql .= " AND created_at >= CURDATE() - INTERVAL 30 DAY";
                    break;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function markNotificationAsRead($notificationId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deleteNotification($notificationId, $userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }
    public function getTotalNotificationCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        return (int)$stmt->fetchColumn();
    }
}