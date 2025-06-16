<?php
session_start();
require_once "../classes/Database.php";
require_once "../classes/User.php";

$db = new Database();
$pdo = $db->getConnection();
$user = new User($pdo);

$user->logout();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
exit();
?>