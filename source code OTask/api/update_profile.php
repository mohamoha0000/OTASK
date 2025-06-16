<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../classes/Database.php";
require_once "../classes/User.php";
require_once "../classes/Validator.php"; // Include Validator class

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["user_id"])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION["user_id"];

$db = new Database();
$pdo = $db->getConnection();
$user = new User($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $field = $data['field'] ?? '';
    $newValue = $data['newValue'] ?? '';

    if (empty($field)) {
        $response['message'] = 'Missing field.';
        echo json_encode($response);
        exit();
    }

    if ($field === 'name' && empty(trim($newValue))) {
        $response['message'] = 'Name cannot be empty.';
        echo json_encode($response);
        exit();
    }

    if ($field === 'password' && empty($newValue)) {
        $response['message'] = 'New password cannot be empty.';
        echo json_encode($response);
        exit();
    }

    if ($field === 'password') {
        if (!Validator::isValidPassword($newValue)) {
            $response['message'] = 'Password must be at least 6 characters long.';
            echo json_encode($response);
            exit();
        }
    }

    if ($field === 'profilePic') {
        // Handle base64 image upload
        $base64_image = $newValue;
        // Validate base64 string (optional, but good practice)
        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,/', $base64_image)) {
            $response['message'] = 'Invalid image format.';
            echo json_encode($response);
            exit();
        }

        // You might want to save the image to a file and store the path in the DB,
        // or store the base64 string directly if your TEXT column can handle it.
        // For simplicity, let's assume storing base64 directly for now.
        // In a real application, saving to a file is generally better for performance and database size.
        if ($user->update($user_id, 'profile_picture', $base64_image)) {
            $response['success'] = true;
            $response['message'] = 'Profile picture updated successfully.';
        } else {
            $response['message'] = 'Failed to update profile picture.';
        }
    } else {
        if ($user->update($user_id, $field, $newValue)) {
            $response['success'] = true;
            $response['message'] = ucfirst($field) . ' updated successfully.';
            // Update session variable if name is changed
            if ($field === 'name') {
                $_SESSION['user_name'] = $newValue;
            }
        } else {
            $response['message'] = 'Failed to update ' . $field . '. Please check your input.';
        }
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>