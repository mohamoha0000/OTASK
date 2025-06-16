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
        $sql = "INSERT INTO tasks (title, description, start_date, end_date, priority, status, assigned_user_id, created_by_id, created_at, last_mod)
                VALUES (?, ?, ?, ?, ?, 'to_do', ?, ?, NOW(), NOW())";
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
    public function getAllTasksForUser($userId, $searchQuery = '', $statusFilter = '', $projectFilter = '', $daysFilter = '', $startDateFilter = '', $endDateFilter = '', $limit = 10, $offset = 0) {
        $sql = "SELECT id, title, description, start_date, end_date, priority, status, project_id, assigned_user_id
                FROM tasks
                WHERE assigned_user_id = :userId";
        $params = [':userId' => $userId];

        if (!empty($searchQuery)) {
            $sql .= " AND (title LIKE :searchQuery1 OR description LIKE :searchQuery2)";
            $params[':searchQuery1'] = '%' . $searchQuery . '%';
            $params[':searchQuery2'] = '%' . $searchQuery . '%';
        }
        if (!empty($statusFilter)) {
            $sql .= " AND status = :statusFilter";
            $params[':statusFilter'] = $statusFilter;
        }
        if (!empty($projectFilter)) {
            $sql .= " AND project_id = :projectFilter";
            $params[':projectFilter'] = $projectFilter;
        }
        if (!empty($daysFilter)) {
            switch ($daysFilter) {
                case 'today':
                    $sql .= " AND DATE(end_date) = CURDATE()";
                    break;
                case 'next_7_days':
                    $sql .= " AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'overdue':
                    $sql .= " AND status != 'completed' AND end_date IS NOT NULL AND end_date < NOW()";
                    break;
            }
        }
        if (!empty($startDateFilter)) {
            $sql .= " AND DATE(end_date) >= :startDateFilter";
            $params[':startDateFilter'] = $startDateFilter;
        }
        if (!empty($endDateFilter)) {
            $sql .= " AND DATE(end_date) <= :endDateFilter";
            $params[':endDateFilter'] = $endDateFilter;
        }

        // Default ordering: most recently modified or created tasks first
        $sql .= " ORDER BY last_mod DESC, created_at DESC";

        // If any date filters are applied, prioritize ordering by end_date
        if (!empty($daysFilter) || !empty($startDateFilter) || !empty($endDateFilter)) {
            $sql .= ", end_date ASC, priority DESC";
        }

        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTaskCountForUserFiltered($userId, $searchQuery = '', $statusFilter = '', $projectFilter = '', $daysFilter = '', $startDateFilter = '', $endDateFilter = '') {
        $sql = "SELECT COUNT(*) FROM tasks WHERE assigned_user_id = :userId";
        $params = [':userId' => $userId];

        if (!empty($searchQuery)) {
            $sql .= " AND (title LIKE :searchQuery1 OR description LIKE :searchQuery2)";
            $params[':searchQuery1'] = '%' . $searchQuery . '%';
            $params[':searchQuery2'] = '%' . $searchQuery . '%';
        }
        if (!empty($statusFilter)) {
            $sql .= " AND status = :statusFilter";
            $params[':statusFilter'] = $statusFilter;
        }
        if (!empty($projectFilter)) {
            $sql .= " AND project_id = :projectFilter";
            $params[':projectFilter'] = $projectFilter;
        }
        if (!empty($daysFilter)) {
            switch ($daysFilter) {
                case 'today':
                    $sql .= " AND DATE(end_date) = CURDATE()";
                    break;
                case 'next_7_days':
                    $sql .= " AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'overdue':
                    $sql .= " AND status != 'completed' AND end_date IS NOT NULL AND end_date < NOW()";
                    break;
            }
        }
        if (!empty($startDateFilter)) {
            $sql .= " AND DATE(end_date) >= :startDateFilter";
            $params[':startDateFilter'] = $startDateFilter;
        }
        if (!empty($endDateFilter)) {
            $sql .= " AND DATE(end_date) <= :endDateFilter";
            $params[':endDateFilter'] = $endDateFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}