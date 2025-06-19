<?php
class User {
    private $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function create($name,$email,$password) {
        $stmt = $this->db->prepare("INSERT INTO users (name,email, password) VALUES (?,?,?)");
        return $stmt->execute([$name,$email,password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $this->creat_cookie($user['id']);
            // $token = bin2hex(random_bytes(32));
            // $hashedToken = hash('sha256', $token);
            // $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            // $stmt->execute([$hashedToken, $user['id']]);

            // setcookie('remember_me', $token, time() + (86400 * 15), "/", "", false, true); //15 days

            return true;
        }
    
        return false; 
    }
    public function creat_cookie($id){
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$hashedToken, $id]);

        setcookie('remember_me', $token, time() + (86400 * 15), "/", "", false, true); //15 days
    }
    public function autoLogin() {
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];
            $hashedToken = hash('sha256', $token);

            $stmt = $this->db->prepare("SELECT * FROM users WHERE remember_token = ?");
            $stmt->execute([$hashedToken]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                return true;
            }
        }elseif(isset($_SESSION['user_id'])) return true;
        return false;
    }

    public function logout() {
        setcookie("remember_me", "", time() - 3600, "/");
        session_destroy();
    }

    public function not_existe($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return !$stmt->fetch();
    }

    public function get_info($id){
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function get_role($id){
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function getAllUsers() {
        $stmt = $this->db->prepare("SELECT id, name, email, created_at FROM users ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchUsers($searchTerm, $limit, $offset) {
        $searchTerm = '%' . $searchTerm . '%';
        $stmt = $this->db->prepare("SELECT id, name, email, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name ASC LIMIT ? OFFSET ?");
        // Explicitly cast limit and offset to integer to prevent SQL syntax errors
        $stmt->bindValue(1, $searchTerm);
        $stmt->bindValue(2, $searchTerm);
        $stmt->bindValue(3, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSearchUsers($searchTerm) {
        $searchTerm = '%' . $searchTerm . '%';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE name LIKE ? OR email LIKE ?");
        $stmt->execute([$searchTerm, $searchTerm]);
        return $stmt->fetchColumn();
    }

    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();

            // Unassign tasks from this user
            $stmtTasks = $this->db->prepare("UPDATE tasks SET assigned_user_id = NULL WHERE assigned_user_id = ?");
            $stmtTasks->execute([$userId]);

            // Remove user from project_members
            $stmtMembers = $this->db->prepare("DELETE FROM project_members WHERE user_id = ?");
            $stmtMembers->execute([$userId]);

            // Transfer ownership of projects where this user is supervisor (or delete them, depending on policy)
            // For now, let's set supervisor_id to NULL or a default admin ID if available.
            // A more robust solution might involve transferring to another admin or deleting projects.
            // For simplicity, let's set to NULL, which might require UI handling for unassigned projects.
            $stmtProjects = $this->db->prepare("UPDATE projects SET supervisor_id = NULL WHERE supervisor_id = ?");
            $stmtProjects->execute([$userId]);

            // Delete notifications sent to or from this user
            $stmtNotifications = $this->db->prepare("DELETE FROM notifications WHERE sender_id = ? OR recipient_id = ?");
            $stmtNotifications->execute([$userId, $userId]);

            // Finally, delete the user
            $stmtUser = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error deleting user: " . $e->getMessage());
            return false;
        }
    }

    public function getUserByEmail($email) {
        $stmt = $this->db->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        if ($excludeUserId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    public function update($userId, $field, $newValue) {
        // Basic validation to ensure only allowed fields are updated
        $allowedFields = ['name', 'password', 'profile_picture'];
        if (!in_array($field, $allowedFields)) {
            return false;
        }

        // Hash password if the field is 'password'
        if ($field === 'password') {
            $newValue = password_hash($newValue, PASSWORD_DEFAULT);
        }

        $stmt = $this->db->prepare("UPDATE users SET {$field} = ? WHERE id = ?");
        return $stmt->execute([$newValue, $userId]);
    }
}
?>
