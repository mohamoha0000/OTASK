<?php

class Project {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createProject($title, $description, $supervisorId) {
        $stmt = $this->pdo->prepare("INSERT INTO projects (title, description, supervisor_id) VALUES (?, ?, ?)");
        if ($stmt->execute([$title, $description, $supervisorId])) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }


    public function getProjectsJoinedCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT project_id) 
            FROM project_members 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getActiveProjectsCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT p.id) 
            FROM projects p
            LEFT JOIN project_members pm ON p.id = pm.project_id
            WHERE p.supervisor_id = :uid OR pm.user_id = :uid
        ");
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
    public function isUserProjectSupervisor($projectId, $userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM projects WHERE id = ? AND supervisor_id = ?");
        $stmt->execute([$projectId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isUserProjectMember($projectId, $userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getProjectById($projectId) {
        $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getProjectsForUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT p.id, p.title
            FROM projects p
            LEFT JOIN project_members pm ON p.id = pm.project_id
            WHERE p.supervisor_id = :userId OR pm.user_id = :userId
            ORDER BY p.title ASC
        ");
        $stmt->execute([':userId' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getProjectMemberCount($projectId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ?");
        $stmt->execute([$projectId]);
        return (int)$stmt->fetchColumn();
    }

    public function getAdminProjectsForUser($userId, $search_query = '', $start_date = '', $end_date = '', $limit = 6, $offset = 0) {
        $sql = "SELECT * FROM projects WHERE supervisor_id = :userId";
        $params = [':userId' => $userId];

        if (!empty($search_query)) {
            $sql .= " AND (title LIKE :search_query OR description LIKE :search_query)";
            $params[':search_query'] = '%' . $search_query . '%';
        }
        if (!empty($start_date)) {
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $this->pdo->prepare($sql);
        // Remove limit and offset from params array as they are now directly in the SQL string
        unset($params[':limit']);
        unset($params[':offset']);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdminProjectsCountFiltered($userId, $search_query = '', $start_date = '', $end_date = '') {
        $sql = "SELECT COUNT(*) FROM projects WHERE supervisor_id = :userId";
        $params = [':userId' => $userId];

        if (!empty($search_query)) {
            $sql .= " AND (title LIKE :search_query OR description LIKE :search_query)";
            $params[':search_query'] = '%' . $search_query . '%';
        }
        if (!empty($start_date)) {
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getJoinedProjectsForUser($userId, $search_query = '', $start_date = '', $end_date = '', $limit = 6, $offset = 0) {
        $sql = "
            SELECT p.*
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = :userId AND p.supervisor_id != :userId_check
        ";
        $params = [':userId' => $userId, ':userId_check' => $userId];

        if (!empty($search_query)) {
            $sql .= " AND (p.title LIKE :search_query OR p.description LIKE :search_query)";
            $params[':search_query'] = '%' . $search_query . '%';
        }
        if (!empty($start_date)) {
            $sql .= " AND p.created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $sql .= " AND p.created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $this->pdo->prepare($sql);
        // Remove limit and offset from params array as they are now directly in the SQL string
        unset($params[':limit']);
        unset($params[':offset']);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getJoinedProjectsCountFiltered($userId, $search_query = '', $start_date = '', $end_date = '') {
        $sql = "
            SELECT COUNT(DISTINCT p.id)
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = :userId AND p.supervisor_id != :userId_check
        ";
        $params = [':userId' => $userId, ':userId_check' => $userId];

        if (!empty($search_query)) {
            $sql .= " AND (p.title LIKE :search_query OR p.description LIKE :search_query)";
            $params[':search_query'] = '%' . $search_query . '%';
        }
        if (!empty($start_date)) {
            $sql .= " AND p.created_at >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $sql .= " AND p.created_at <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getProjectMembers($projectId) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.name
            FROM users u
            JOIN project_members pm ON u.id = pm.user_id
            WHERE pm.project_id = :projectId
            UNION
            SELECT u.id, u.name
            FROM users u
            JOIN projects p ON u.id = p.supervisor_id
            WHERE p.id = :projectId
            ORDER BY name ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}