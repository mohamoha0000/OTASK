<?php

class Project {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
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
}