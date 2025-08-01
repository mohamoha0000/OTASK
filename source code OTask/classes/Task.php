<?php

class Task {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

 
    public function getTotalTasksForUser($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getTasksCreatedByUser($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE created_by_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
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
            SELECT id, title, description, start_date, end_date, priority, status, project_id, assigned_user_id, deliverable_link
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

    // Admin Dashboard Methods
    public function getTotalTaskCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tasks");
        return (int)$stmt->fetchColumn();
    }

    public function getCompletedTaskCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'");
        return (int)$stmt->fetchColumn();
    }

    public function getOverdueTaskCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'completed' AND end_date IS NOT NULL AND end_date < NOW()");
        return (int)$stmt->fetchColumn();
    }

    public function createTask($title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById) {
        $sql = "INSERT INTO tasks (title, description, start_date, end_date, priority, status, assigned_user_id, created_by_id, created_at, last_mod)
                VALUES (?, ?, ?, ?, ?, 'to_do', ?, ?, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById]);
    }

    public function createTaskInProject($projectId, $title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById) {
        $sql = "INSERT INTO tasks (project_id, title, description, start_date, end_date, priority, status, assigned_user_id, created_by_id, created_at, last_mod)
                VALUES (?, ?, ?, ?, ?, ?, 'to_do', ?, ?, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        // assigned_user_id can be NULL, so we pass it directly.
        return $stmt->execute([$projectId, $title, $description, $startDate, $endDate, $priority, $assignedUserId, $createdById]);
    }
    public function updateTask($taskId, $title, $description, $startDate, $endDate, $priority, $status, $deliverableLink, $assignedUserId) {
        $sql = "UPDATE tasks SET title = ?, description = ?, start_date = ?, end_date = ?, priority = ?, status = ?, deliverable_link = ?, assigned_user_id = ?, last_mod = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$title, $description, $startDate, $endDate, $priority, $status, $deliverableLink, $assignedUserId, $taskId]);
    }

    public function deleteTask($taskId) {
        $sql = "DELETE FROM tasks WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$taskId]);
    }

    public function getTaskById($taskId) {
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllTasksForUser($userId, $searchQuery = '', $statusFilter = '', $projectFilter = '', $daysFilter = '', $startDateFilter = '', $endDateFilter = '', $limit = 10, $offset = 0) {
        $sql = "SELECT id, title, description, start_date, end_date, priority, status, project_id, assigned_user_id, deliverable_link
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
                case 'tomorrow':
                    $sql .= " AND DATE(end_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
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
                case 'tomorrow':
                    $sql .= " AND DATE(end_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
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
    public function getTaskCountForProject($projectId) {
        $sql = "SELECT COUNT(*) FROM tasks WHERE project_id = :projectId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);
        return (int)$stmt->fetchColumn();
    }

    public function getTasksByProjectId($projectId) {
        $sql = "SELECT * FROM tasks WHERE project_id = :projectId ORDER BY end_date ASC, priority DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTasksByProjectIdFiltered($projectId, $statusFilter = '', $assignedUserFilter = '', $daysFilter = '') {
        $sql = "SELECT t.*, u.name as assigned_user_name
                FROM tasks t
                LEFT JOIN users u ON t.assigned_user_id = u.id
                WHERE t.project_id = :projectId";
        $params = [':projectId' => $projectId];

        if (!empty($statusFilter)) {
            $sql .= " AND t.status = :statusFilter";
            $params[':statusFilter'] = $statusFilter;
        }
        if ($assignedUserFilter === 'unassigned') {
            $sql .= " AND t.assigned_user_id IS NULL";
        } elseif (!empty($assignedUserFilter)) {
            $sql .= " AND t.assigned_user_id = :assignedUserFilter";
            $params[':assignedUserFilter'] = $assignedUserFilter;
        }
        if (!empty($daysFilter)) {
            switch ($daysFilter) {
                case 'today':
                    $sql .= " AND DATE(t.end_date) = CURDATE()";
                    break;
                case 'tomorrow':
                    $sql .= " AND DATE(t.end_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'next_7_days':
                    $sql .= " AND t.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'overdue':
                    $sql .= " AND t.status != 'completed' AND t.end_date IS NOT NULL AND t.end_date < NOW()";
                    break;
            }
        }

        $sql .= " ORDER BY t.end_date ASC, t.priority DESC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnassignedTasksByProjectId($projectId) {
        $sql = "SELECT id, title, description FROM tasks WHERE project_id = :projectId AND assigned_user_id IS NULL ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignTaskToUser($taskId, $userId) {
        $sql = "UPDATE tasks SET assigned_user_id = ?, status = 'to_do', last_mod = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId, $taskId]);
    }
 
    public function unassignTask($taskId) {
        $sql = "UPDATE tasks SET assigned_user_id = NULL, status = 'to_do', last_mod = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$taskId]);
    }

   public function getTasksAssignedToUser($userId) {
       $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_user_id = ?");
       $stmt->execute([$userId]);
       return (int)$stmt->fetchColumn();
   }
}