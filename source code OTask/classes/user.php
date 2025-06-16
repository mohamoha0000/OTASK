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

            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$hashedToken, $user['id']]);

            setcookie('remember_me', $token, time() + (86400 * 15), "/", "", false, true); //15 days

            return true;
        }
    
        return false; 
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
        $stmt = $this->db->prepare("SELECT id, name FROM users ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
