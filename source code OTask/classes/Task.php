<?php

class Task {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

 
    public function getTaskCountForUser($userId, $status) {
        switch ($status) {
            case 'active':
                $sql = "SELECT COUNT(*) FROM tasks WHERE assigned_user_id = ? AND status != 'completed'";
                $params = [$userId];
                break;
            case 'completed':
                $sql = "SELECT COUNT(*) FROM tasks WHERE assigned_user_id = ? AND status = 'completed'";
                $params = [$userId];
                break;
            case 'overdue':
                $sql = "SELECT COUNT(*) FROM tasks WHERE assigned_user_id = ? AND status != 'completed' AND end_date IS NOT NULL AND end_date < NOW()";
                $params = [$userId];
                break;
            default:
                return 0;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getRecentTasksForUser($userId, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT id, title, description, start_date, end_date, priority, status, project_id, assigned_user_id
            FROM tasks
            WHERE assigned_user_id = ?
            ORDER BY last_mod DESC, created_at DESC
            LIMIT ?
        ");
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function createTask($title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById) {
        $sql = "INSERT INTO tasks (title, description, start_date, end_date, priority, status, assigned_user_id, created_by_id)
                VALUES (?, ?, ?, ?, ?, 'to_do', ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById]);
    }
    public function updateTask($taskId, $title, $description, $startDate, $endDate, $priority, $status, $deliverableLink, $assignedUserId) {
        $sql = "UPDATE tasks SET title = ?, description = ?, start_date = ?, end_date = ?, priority = ?, status = ?, deliverable_link = ?, assigned_user_id = ?, last_mod = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$title, $description, $startDate, $endDate, $priority, $status, $deliverableLink, $assignedUserId, $taskId]);
    }

    public function getTaskById($taskId) {
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}