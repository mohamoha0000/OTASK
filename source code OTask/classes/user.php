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
}
?>
