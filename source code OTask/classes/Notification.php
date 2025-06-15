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
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}