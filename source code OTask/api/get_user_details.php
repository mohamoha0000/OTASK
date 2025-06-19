<?php
session_start();
require_once "../classes/Database.php";
require_once "../classes/User.php";
require_once "../classes/Task.php";
require_once "../classes/Project.php";

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
$taskManager = new Task($pdo);
$projectManager = new Project($pdo);

// Check if the logged-in user is an admin
$loggedInUserId = $_SESSION["user_id"];
$user_role = $user->get_role($loggedInUserId);
if ($user_role !== 'admin') {
    $response['message'] = 'Access denied. Admin privileges required.';
    echo json_encode($response);
    exit();
}

if (isset($_GET['user_id'])) {
    $targetUserId = (int)$_GET['user_id'];

    $userInfo = $user->get_info($targetUserId);
    if ($userInfo) {
        $totalTasks = $taskManager->getTotalTasksForUser($targetUserId);
        $projectsJoined = $projectManager->getProjectsJoinedCount($targetUserId);
        $projectsSupervised = $projectManager->getProjectsSupervisedCount($targetUserId);
        $tasksCreated = $taskManager->getTasksCreatedByUser($targetUserId); // New
        $tasksAssigned = $taskManager->getTasksAssignedToUser($targetUserId); // New

        $response['success'] = true;
        $response['user'] = [
            'id' => $userInfo['id'],
            'name' => htmlspecialchars($userInfo['name'] ?? ''),
            'email' => htmlspecialchars($userInfo['email'] ?? ''),
            'created_at' => date('M d, Y', strtotime($userInfo['created_at'] ?? '')),
            'total_tasks' => $totalTasks,
            'projects_joined' => $projectsJoined,
            'projects_supervised' => $projectsSupervised,
            'tasks_created' => $tasksCreated, // New
            'tasks_assigned' => $tasksAssigned // New
        ];
    } else {
        $response['message'] = 'User not found.';
    }
} else {
    $response['message'] = 'User ID not provided.';
}

echo json_encode($response);
?>