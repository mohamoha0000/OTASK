<?php
session_start();
require_once "../classes/Database.php";
require_once "../classes/User.php";

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["user_id"])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$db = new Database();
$pdo = $db->getConnection();

$user = new User($pdo);

// Check if the logged-in user is an admin
$loggedInUserId = $_SESSION["user_id"];
$user_role = $user->get_role($loggedInUserId);
if ($user_role !== 'admin') {
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userIdToDelete = (int)$_POST['user_id'];

    // Prevent admin from deleting themselves
    if ($userIdToDelete === $loggedInUserId) {
        $response['message'] = 'You cannot delete your own admin account.';
        echo json_encode($response);
        exit();
    }

    if ($user->deleteUser($userIdToDelete)) {
        $response['success'] = true;
        $response['message'] = 'User deleted successfully.';
    } else {
        $response['message'] = 'Failed to delete user.';
    }
} else {
    $response['message'] = 'Invalid request or user ID not provided.';
}

echo json_encode($response);
?>