<?php
session_start();
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/Task.php';

$database = new Database();
$pdo = $database->getConnection();
$task = new Task($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $project_id = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT);
    $title = filter_var($input['title'] ?? null, FILTER_SANITIZE_STRING);
    $description = filter_var($input['description'] ?? null, FILTER_SANITIZE_STRING);

    // Extract optional fields from input, with default values
    $start_date = filter_var($input['start_date'] ?? null, FILTER_SANITIZE_STRING);
    $end_date = filter_var($input['end_date'] ?? null, FILTER_SANITIZE_STRING);
    $priority = filter_var($input['priority'] ?? 'medium', FILTER_SANITIZE_STRING);

    // Assigned user ID will be NULL for unassigned tasks
    $assigned_user_id = null;

    // Get the current user's ID from the session
    $created_by_id = $_SESSION['user_id'] ?? null;

    if (!$project_id || empty($title) || !$created_by_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: project ID, title, or creator ID.']);
        exit();
    }

    // Call the createTask method, passing null for assigned_user_id
    if ($task->createTaskInProject($project_id, $title, $description, $start_date, $end_date, $priority, $assigned_user_id, $created_by_id)) {
        echo json_encode(['success' => true, 'message' => 'Task created successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create task.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>