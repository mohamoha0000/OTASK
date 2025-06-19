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

$database = new Database();
$pdo = $database->getConnection();

$user = new User($pdo);
$project = new Project($pdo);
$task = new Task($pdo);

$current_user_id = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

$task_id = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$assigned_user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$project_id = isset($input['project_id']) ? (int)$input['project_id'] : 0;

if ($task_id === 0 || $assigned_user_id === 0 || $project_id === 0) {
    $response['message'] = 'Invalid task ID, user ID, or project ID provided.';
    echo json_encode($response);
    exit();
}

// Check if the current user is a supervisor of this project
$is_supervisor = $project->isUserProjectSupervisor($project_id, $current_user_id);

if (!$is_supervisor) {
    $response['message'] = 'Access denied. Only project supervisors can assign tasks.';
    echo json_encode($response);
    exit();
}

// Verify the task belongs to the project
$task_info = $task->getTaskById($task_id);
if (!$task_info || (int)$task_info['project_id'] !== $project_id) {
    $response['message'] = 'Task not found or does not belong to this project.';
    echo json_encode($response);
    exit();
}

// Verify the assigned user is a member of the project
$is_member = $project->isUserProjectMember($project_id, $assigned_user_id);
if (!$is_member) {
    $response['message'] = 'Assigned user is not a member of this project.';
    echo json_encode($response);
    exit();
}

try {
    if ($task->assignTaskToUser($task_id, $assigned_user_id)) {
        $response['success'] = true;
        $response['message'] = 'Task assigned successfully.';
    } else {
        $response['message'] = 'Failed to assign task.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error assigning task: ' . $e->getMessage();
}

echo json_encode($response);
?>