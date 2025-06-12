<?php
class User {
    private $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function create($name,$email,$password) {
        $stmt = $this->db->prepare("INSERT INTO users (name,email, password) VALUES (?, ?,?)");
        return $stmt->execute([$name,$email,password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION["UserId"]=$user["id"];
            return true;
        }
    
        return false; 
    }

    
    public function not_existe($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return !$stmt->fetch();
    }
}
?>
