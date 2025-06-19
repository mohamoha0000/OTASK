<?php
session_start();
require_once '../classes/Database.php';
require_once '../classes/Task.php';
require_once '../classes/Project.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'tasks' => []];

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

if (!isset($_GET['project_id'])) {
    $response['message'] = 'Project ID not provided.';
    echo json_encode($response);
    exit();
}

$project_id = (int)$_GET['project_id'];

// Check if the current user is a supervisor or member of this project
$is_supervisor = $project->isUserProjectSupervisor($project_id, $current_user_id);
$is_member = $project->isUserProjectMember($project_id, $current_user_id);

if (!$is_supervisor && !$is_member) {
    $response['message'] = 'Access denied. You are not a member or supervisor of this project.';
    echo json_encode($response);
    exit();
}

try {
    $unassigned_tasks = $task->getUnassignedTasksByProjectId($project_id);
    $response['success'] = true;
    $response['tasks'] = $unassigned_tasks;
    echo json_encode($response);
} catch (Exception $e) {
    $response['message'] = 'Error fetching unassigned tasks: ' . $e->getMessage();
    echo json_encode($response);
}
?>