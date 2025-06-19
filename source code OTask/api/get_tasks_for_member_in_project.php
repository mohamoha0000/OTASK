<?php
header('Content-Type: application/json');
session_start();

require_once '../classes/Database.php';
require_once '../classes/Task.php';

$database = new Database();
$pdo = $database->getConnection();
$task = new Task($pdo);

$response = ['success' => false, 'message' => '', 'tasks' => []];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($member_id === 0 || $project_id === 0) {
    $response['message'] = 'Invalid member ID or project ID.';
    echo json_encode($response);
    exit();
}

try {
    // Assuming a method like getTasksForMemberInProject exists or can be adapted
    // For now, we can use getAllTasksForUser and filter by project_id
    $tasks = $task->getAllTasksForUser($member_id, '', '', $project_id);

    $response['success'] = true;
    $response['tasks'] = $tasks;
} catch (Exception $e) {
    $response['message'] = 'Error fetching tasks: ' . $e->getMessage();
}

echo json_encode($response);
?>