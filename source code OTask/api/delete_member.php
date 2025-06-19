<?php
session_start();
require_once '../classes/Database.php';
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

$project = new Project($pdo);
$user = new User($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$member_id = isset($input['member_id']) ? (int)$input['member_id'] : 0;
$project_id = isset($input['project_id']) ? (int)$input['project_id'] : 0;

if ($member_id === 0 || $project_id === 0) {
    $response['message'] = 'Invalid member ID or project ID.';
    echo json_encode($response);
    exit();
}

// Check if the current user is the project supervisor
$is_supervisor = $project->isUserProjectSupervisor($project_id, $current_user_id);

if (!$is_supervisor) {
    $response['message'] = 'Unauthorized: Only the project supervisor can remove members.';
    echo json_encode($response);
    exit();
}

// Prevent supervisor from deleting themselves from the project
if ($current_user_id === $member_id) {
    $response['message'] = 'You cannot remove yourself as the project supervisor.';
    echo json_encode($response);
    exit();
}

// Check if the member to be deleted is the supervisor of the project
$project_info = $project->getProjectById($project_id);
if ($project_info && $project_info['supervisor_id'] == $member_id) {
    $response['message'] = 'Cannot remove the project supervisor. Assign a new supervisor first or delete the project.';
    echo json_encode($response);
    exit();
}

// Attempt to remove the member from the project
if ($project->leaveProject($project_id, $member_id)) {
    $response['success'] = true;
    $response['message'] = 'Member removed successfully.';
} else {
    $response['message'] = 'Failed to remove member. Member might not be part of this project or is the supervisor.';
}

echo json_encode($response);
?>