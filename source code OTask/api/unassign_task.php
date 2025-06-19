<?php
session_start();
require_once '../classes/Database.php';
require_once '../classes/Task.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$current_user_id = $_SESSION['user_id'];

$database = new Database();
$pdo = $database->getConnection();

$task = new Task($pdo);
$project = new Project($pdo);
$user = new User($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$task_id = isset($input['task_id']) ? (int)$input['task_id'] : 0;

if ($task_id === 0) {
    $response['message'] = 'Invalid task ID.';
    echo json_encode($response);
    exit();
}

$task_info = $task->getTaskById($task_id);
if (!$task_info) {
    $response['message'] = 'Task not found.';
    echo json_encode($response);
    exit();
}

$project_id = $task_info['project_id'];

// Check if the current user is the project supervisor
$is_supervisor = $project->isUserProjectSupervisor($project_id, $current_user_id);

if (!$is_supervisor) {
    $response['message'] = 'Unauthorized: Only the project supervisor can unassign tasks.';
    echo json_encode($response);
    exit();
}

try {
    if ($task->unassignTask($task_id)) {
        $response['success'] = true;
        $response['message'] = 'Task unassigned successfully.';
    } else {
        $response['message'] = 'Failed to unassign task.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error unassigning task: ' . $e->getMessage();
}

echo json_encode($response);
?>