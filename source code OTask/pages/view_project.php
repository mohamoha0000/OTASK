<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Project.php";
    require_once "../classes/Notification.php";
    require_once "../classes/Task.php";
    require_once "../classes/Validator.php";

    $db = new Database();
    $pdo = $db->getConnection();

    if(!isset($_SESSION["user_id"])){
        header("Location: login.php");
        exit();
    }
    $user_id = $_SESSION["user_id"];

    $user = new User($pdo);
    $projectManager = new Project($pdo);
    $notificationManager = new Notification($pdo);
    $taskManager = new Task($pdo);

    if(isset($_GET["logout"])){
        $user->logout();
        header("Location: login.php");
        exit();
    }

    $user_info = $user->get_info($user_id);
    $user_name = isset($user_info["name"]) ? $user_info["name"] : "User";

    function getInitials($name) {
        $parts = preg_split("/\s+/", trim($name));
        $initials = "";
        foreach ($parts as $p) {
            if (strlen($p) > 0) {
                $initials .= mb_substr($p, 0, 1);
            }
            if (mb_strlen($initials) >= 2) break;
        }
        return strtoupper($initials);
    }
    
    $unread_notifications = $notificationManager->getUnreadCount($user_id);

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $project = null;
    $tasks = [];
    $error_message = '';

    // Filters for tasks within the project view
    $status_filter = $_GET['status'] ?? '';
    $member_filter = $_GET['member'] ?? $user_id; // New filter for assigned member, default to current user
    $days_filter = $_GET['days'] ?? '';

    $project_members = []; // To store project members for the filter dropdown

    if ($project_id > 0) {
        $project = $projectManager->getProjectById($project_id);
        if (!$project) {
            $error_message = "Project not found.";
        } else {
            // Check if the user is a supervisor or member of this project
            $is_supervisor = $projectManager->isUserProjectSupervisor($project_id, $user_id);
            $is_member = $projectManager->isUserProjectMember($project_id, $user_id);

            if (!$is_supervisor && !$is_member) {
                $error_message = "You do not have permission to view this project.";
                $project = null; // Clear project data if no permission
            } else {
                // Fetch project members for the filter dropdown
                $project_members = $projectManager->getProjectMembers($project_id);
                $all_users = $user->getAllUsers(); // Fetch all users for the edit modal's assigned member dropdown

                // Fetch tasks for this project with filters
                $tasks = $taskManager->getTasksByProjectIdFiltered(
                    $project_id,
                    $status_filter,
                    $member_filter,
                    $days_filter
                );
            }
        }
    } else {
        $error_message = "No project ID provided.";
    }

    // Handle task update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_task"])) {
        $taskId = $_POST["task_id"] ?? null;
        $title = $_POST["task_title"] ?? '';
        $description = $_POST["task_description"] ?? '';
        $startDate = $_POST["start_date"] ?? '';
        $endDate = $_POST["end_date"] ?? '';
        $priority = $_POST["task_priority"] ?? '';
        $status = $_POST["task_status"] ?? '';
        $deliverableLink = $_POST["deliverable_link"] ?? '';
        $assignedUserId = $_POST["assigned_user_id"] ?? null;

        $errors = [];

        // Fetch the existing task to determine permissions and original values
        $task = $taskManager->getTaskById($taskId);

        if (!$task) {
            $errors[] = "Task not found.";
        } else {
            // Determine permissions for the current user on this specific task
            $isProjectTask = ($task['project_id'] !== null);
            $isAssignedUser = ($task['assigned_user_id'] == $user_id);
            $isProjectSupervisor = false;
            if ($isProjectTask) {
                $isProjectSupervisor = $projectManager->isUserProjectSupervisor($task['project_id'], $user_id);
            }
            $isProjectMember = false;
            if ($isProjectTask) {
                $isProjectMember = $projectManager->isUserProjectMember($task['project_id'], $user_id);
            }
            $isNonSupervisorProjectMember = $isProjectTask && $isProjectMember && !$isProjectSupervisor;
            $isAssignedUser = ($task['assigned_user_id'] == $user_id);

            // Apply validation based on permissions
            // Only supervisor can edit all fields
            if ($isProjectSupervisor) {
                if (!Validator::isNotEmpty($title)) {
                    $errors[] = "Task title cannot be empty.";
                }
                if (!Validator::isNotEmpty($startDate)) {
                    $errors[] = "Start Date cannot be empty.";
                }
                if (!Validator::isNotEmpty($endDate)) {
                    $errors[] = "Due Date cannot be empty.";
                }
            }
            // For non-supervisor project members, title, start_date, end_date are not required for update
            // They can only update status and deliverable_link
        }

        if (!Validator::isNotEmpty($taskId)) {
            $errors[] = "Task ID is missing.";
        }

        if (empty($errors)) {
            if ($task) {
                $updateSuccess = false;
                $canEditAllFields = false;
                $canEditStatus = false;
                $canMarkCompleted = false;
                $canChangeAssignedUser = false;

                // Logic for Project Tasks (all tasks in view_project.php are project tasks)
                if ($isProjectSupervisor) {
                    // Project Supervisor: Full permissions
                    $canEditAllFields = true;
                    $canEditStatus = true;
                    $canMarkCompleted = true;
                    $canChangeAssignedUser = true;
                } elseif ($isNonSupervisorProjectMember) {
                    // Project Member: Restricted permissions
                    $canEditAllFields = false; // Cannot edit all fields
                    $canEditStatus = true; // Can edit status
                    $canChangeAssignedUser = false; // Cannot change assigned user
                    // A project member can mark their own assigned task as completed
                    $canMarkCompleted = false; // Non-supervisor project members cannot mark tasks as completed
                } else {
                    // Project Task, but user is not supervisor and not a member
                    $error_message = "You do not have permission to edit this project task.";
                }

                // Apply updates based on permissions
                if ($canEditAllFields) {
                    $updateSuccess = $taskManager->updateTask(
                        $taskId,
                        $title,
                        $description,
                        $startDate,
                        $endDate,
                        $priority,
                        $status,
                        $deliverableLink,
                        $assignedUserId
                    );
                } elseif ($canEditStatus && $isAssignedUser && $isNonSupervisorProjectMember) {
                    // Assigned Project Member: Can only update status and deliverable_link, but not to 'completed'
                    if ($status == 'completed') {
                        $error_message = "You do not have permission to mark this task as completed.";
                        $updateSuccess = false;
                    } else {
                        $updateSuccess = $taskManager->updateTask(
                            $taskId,
                            $task['title'], // Keep original title
                            $task['description'], // Keep original description
                            $task['start_date'], // Keep original start date
                            $task['end_date'], // Keep original end date
                            $task['priority'], // Keep original priority
                            $status, // Update status
                            $deliverableLink, // Update deliverable link
                            $task['assigned_user_id'] // Keep original assigned user
                        );
                    }
                } else {
                    $error_message = "You do not have permission to edit this task.";
                    $updateSuccess = false;
                }

                if ($updateSuccess) {
                    header("Location: view_project.php?project_id=" . htmlspecialchars($project_id) . "&task_updated=success&member=" . htmlspecialchars($assignedUserId));
                    exit();
                } else {
                    if (!isset($error_message)) {
                        $error_message = "Failed to update task.";
                    }
                }
            } else {
                $error_message = "Task not found.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }

    // Handle project exit
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["exit_project"])) {
        $projectIdToExit = $_POST["project_id_to_exit"] ?? 0;
        if ($projectIdToExit > 0) {
            // Ensure the user is a member and not the supervisor before allowing them to leave
            if ($projectManager->isUserProjectMember($projectIdToExit, $user_id) && !$projectManager->isUserProjectSupervisor($projectIdToExit, $user_id)) {
                if ($projectManager->leaveProject($projectIdToExit, $user_id)) {
                    header("Location: projects.php?exit_success=true");
                    exit();
                } else {
                    $error_message = "Failed to exit project.";
                }
            } else {
                $error_message = "You cannot exit this project (you might be the supervisor or not a member).";
            }
        } else {
            $error_message = "Invalid project ID for exit.";
        }
    }

    // Handle invite member
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_invite"])) {
        $invited_email = $_POST["member_email"] ?? '';
        $project_id_invite = $_POST["project_id"] ?? 0;

        $errors = [];

        if (!Validator::isValidEmail($invited_email)) {
            $errors[] = "Invalid email format.";
        }

        if ($project_id_invite <= 0) {
            $errors[] = "Invalid project ID.";
        }

        // Check if the current user is the supervisor of this project
        if (!$projectManager->isUserProjectSupervisor($project_id_invite, $user_id)) {
            $errors[] = "You do not have permission to invite members to this project.";
        }

        if (empty($errors)) {
            $invited_user_info = $user->getUserByEmail($invited_email);

            if (!$invited_user_info) {
                $errors[] = "User with this email does not exist.";
            } else {
                $invited_user_id = $invited_user_info['id'];

                // Check if the invited user is already a member of the project
                if ($projectManager->isUserProjectMember($project_id_invite, $invited_user_id)) {
                    $errors[] = "This user is already a member of the project.";
                } else {
                    // Send invitation notification
                    $notification_type = 'invite_to_project';
                    $notification_message = "You have been invited to join the project: " . htmlspecialchars($project['title']);
                    $notification_link = "notifications.php"; // Link to notifications page where they can accept/decline

                    if ($notificationManager->createNotification($invited_user_id, $notification_type, $notification_message, $notification_link, $project_id_invite, $user_id)) {
                        header("Location: view_project.php?project_id=" . htmlspecialchars($project_id) . "&invite_sent=success");
                        exit();
                    } else {
                        $errors[] = "Failed to send invitation.";
                    }
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            header("Location: view_project.php?project_id=" . htmlspecialchars($project_id) . "&invite_failed=true");
            exit();
        }
    }

    // Helper to map priority/status to CSS classes and labels (copied from mytask.php)
    function priorityClass($prio) {
        switch ($prio) {
            case 'high': return 'priority-high';
            case 'low': return 'priority-low';
            default:     return 'priority-medium';
        }
    }
    function priorityLabel($prio) {
        switch ($prio) {
            case 'high': return 'High';
            case 'low': return 'Low';
            default:    return 'Medium';
        }
    }
    function statusClass($status) {
        switch ($status) {
            case 'in_progress':     return 'status-progress';
            case 'to_do':           return 'status-progress';
            case 'pending_review':  return 'status-review';
            case 'revision_needed': return 'status-review';
            case 'completed':       return 'status-completed';
            default:                return '';
        }
    }
    function statusLabel($status) {
        switch ($status) {
            case 'in_progress':     return 'In Progress';
            case 'to_do':           return 'To Do';
            case 'pending_review':  return 'Pending Review';
            case 'revision_needed': return 'Revision Needed';
            case 'completed':       return 'Completed';
            default:                return ucfirst(str_replace('_', ' ', (string)$status));
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project View: <?= $project ? htmlspecialchars($project['title']) : 'N/A' ?></title>
    <link rel="stylesheet" href="../style/dashboard.css?v=2">
    <style>
        /* Inherit general styles from dashboard.css */
        .project-view-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-green {
            background-color: #28a745; /* Green */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%; /* Make buttons full width */
            margin-bottom: 10px; /* Space between buttons */
        }
        .btn-green:hover {
            background-color: #218838;
        }
        .btn-orange {
            background-color: #fd7e14; /* Orange */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%; /* Make buttons full width */
            margin-bottom: 10px; /* Space between buttons */
        }
        .btn-orange:hover {
            background-color: #e66b00;
        }
        .btn-danger {
            background-color: #dc3545; /* Red for exit button */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%; /* Make buttons full width */
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .project-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0;
        }
        .project-description-box {
            background-color: #f9f9f9;
            border-left: 5px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: #555;
            line-height: 1.6;
            font-size: 15px;
        }
        .tasks-section {
            margin-top: 30px;
        }
        .tasks-section-header {
            display: flex;
            align-items: baseline;
            margin-bottom: 20px;
        }
        .tasks-section-header h2 {
            font-size: 22px;
            color: #333;
            margin-right: 10px;
        }
        .tasks-count {
            font-size: 18px;
            font-weight: bold;
            color: #f39c12;
        }
        .task-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .task-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea; /* Default border, can be overridden by priority */
        }
        .task-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .task-title {
            font-weight: bold;
            font-size: 16px;
        }
        .task-priority {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .priority-high { background: #ffe6e6; color: #e74c3c; }
        .priority-medium { background: #fff3cd; color: #f39c12; }
        .priority-low { background: #d4edda; color: #27ae60; }

        .task-desc {
            margin-bottom: 10px;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }

        .task-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        .task-due {
            font-size: 13px;
            color: #666;
        }
        .task-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-progress { background: #e3f2fd; color: #2196f3; }
        .status-review { background: #fff3e0; color: #ff9800; }
        .status-completed { background: #e8f5e8; color: #4caf50; }

        /* Filter section styles (copied from mytask.php) */
        .search-filter-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .filter-options select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: #fff;
            cursor: pointer;
            outline: none;
            appearance: none; /* Remove default arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%20viewBox%3D%220%200%20292.4%20292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%20197.1L159.1%2069.2c-3.7-3.7-9.7-3.7-13.4%200L5.4%20197.1c-3.7%203.7-3.7%209.7%200%2013.4s9.7%203.7%2013.4%200l130.3-130.3c.4-.4.9-.6%201.4-.6s1%20.2%201.4%20.6l130.3%20130.3c3.7%203.7%209.7%203.7%2013.4%200S290.7%20200.8%20287%20197.1z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 12px auto;
        }

        .filter-options .reset-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .filter-options .reset-btn:hover {
            background: #e74c3c;
        }

        /* Styles for the modal and form elements (copied from mytask.php) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }

        .close-button {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: #000;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="datetime-local"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
        }

        .btn-secondary {
            background-color: #ccc;
            color: #333;
            border: 1px solid #bbb;
        }

        .btn-secondary:hover {
            background-color: #bbb;
        }

        .deliverable-link-input-group {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding-right: 10px;
        }

        .deliverable-link-input-group input {
            border: none;
            flex-grow: 1;
            padding: 10px;
        }

        .deliverable-link-input-group .copy-link-icon {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-left: 5px;
        }

        .edit-task-btn {
            color: #667eea;
            text-decoration: none;
            margin-left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .edit-task-btn:hover {
            color: #764ba2;
        }

        .hidden-project-name {
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <header class="header fade-in">
        <div class="nav container">
            <a href="dashboard.php" class="logo">OTask</a>
            <div class="menu-toggle" id="mobile-menu">
                <img src="../imgs/Menu.png" alt="Menu" class="hamburger-icon">
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="mytask.php">My Tasks</a></li>
                <li><a href="projects.php" class="active">Projects</a></li>
            </ul>
            <div class="user-menu">
                <div class="notification-icon" onclick="window.location.href='notifications.php'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M12 2C10.343 2 9 3.343 9 5v1.07C6.163 7.555 4 10.388 4 14v4l-1 1v1h18v-1l-1-1v-4c0-3.612-2.163-6.445-5-7.93V5c0-1.657-1.343-3-3-3zm1 19h-2a2 2 0 004 0h-2z"/>
                    </svg>
                    <?php if ($unread_notifications > 0): ?>
                    <div class="notification-badge"><?= $unread_notifications ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-avatar" onclick="window.location.href='profile.php'">
                    <?php if (!empty($user_info["profile_picture"])): ?>
                        <?php
                            if (strpos($user_info["profile_picture"], 'data:image') === 0) {
                                $image_src = $user_info["profile_picture"];
                            } else {
                                $image_src = htmlspecialchars($user_info["profile_picture"]);
                            }
                        ?>
                        <img src="<?= $image_src ?>" alt="Profile Picture" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                    <?php else: ?>
                        <?= htmlspecialchars(getInitials($user_name)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <main>
        <div class="project-view-container">
            <?php if ($project): ?>
                <div class="project-header">
                    <div class="project-title-section">
                        <h1>Project: <?= htmlspecialchars($project['title']) ?> </h1>
                        <div class="project-menu-toggle" id="project-menu-toggle">
                            <img src="../imgs/Menu.png" alt="Menu" class="hamburger-icon">
                        </div>
                    </div>
                    <div class="project-desktop-actions">
                        <img src="../imgs/setting.png" alt="Settings" class="project-icon settings-icon-header">
                        <img src="../imgs/Chat Bubble.png" alt="Chat" class="project-icon chat-icon-header">
                        <?php if ($is_supervisor): ?>
                        <button type="button" class="btn btn-primary" id="newTaskBtn">+ New Task</button>
                        <button type="button" class="btn btn-primary" id="newMemberBtn">+ New Member</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="project-description-box">
                    <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                </div>

                <div class="tasks-section">
                    <div class="search-filter-section">
                        <form action="view_project.php" method="GET">
                            <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
                            <div class="filter-options">
                                <select name="status">
                                    <option value="">select status</option>
                                    <option value="to_do" <?= $status_filter == 'to_do' ? 'selected' : '' ?>>To Do</option>
                                    <option value="in_progress" <?= $status_filter == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="pending_review" <?= $status_filter == 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                                    <option value="revision_needed" <?= $status_filter == 'revision_needed' ? 'selected' : '' ?>>Revision Needed</option>
                                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <select name="member">
                                    <option value="">select name member</option>
                                    <?php foreach ($project_members as $member): ?>
                                        <option value="<?= htmlspecialchars($member['id']) ?>" <?= $member_filter == $member['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($member['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="days">
                                    <option value="">select days</option>
                                    <option value="today" <?= $days_filter == 'today' ? 'selected' : '' ?>>Today</option>
                                    <option value="tomorrow" <?= $days_filter == 'tomorrow' ? 'selected' : '' ?>>Tomorrow</option>
                                    <option value="next_7_days" <?= $days_filter == 'next_7_days' ? 'selected' : '' ?>>Next 7 Days</option>
                                    <option value="overdue" <?= $days_filter == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <button type="button" class="reset-btn" onclick="window.location.href='view_project.php?project_id=<?= htmlspecialchars($project_id) ?>'">Reset</button>
                            </div>
                        </form>
                    </div>

                    <div class="tasks-section-header">
                        <h2><?= count($tasks) ?> Tasks</h2>
                    </div>
                    <div class="task-list">
                        <?php if (empty($tasks)): ?>
                            <p style="color: #666;">No tasks found matching your criteria for this project.</p>
                        <?php else: ?>
                            <?php foreach ($tasks as $task):
                                $due = $task['end_date'];
                                $due_str = $due ? date('M d, Y', strtotime($due)) : 'No due date';
                                $prio = $task['priority'];
                                $stat = $task['status'];
                            ?>
                                <div class="task-item">
                                    <div class="task-header">
                                        <div class="task-title">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </div>
                                        <div class="task-priority <?= priorityClass($prio) ?>">
                                            <?= priorityLabel($prio) ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                    <div class="task-desc">
                                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="task-meta">
                                        <div class="task-due">Due: <?= htmlspecialchars($due_str) ?></div>
                                        <div class="task-status <?= statusClass($stat) ?>">
                                            <?= statusLabel($stat) ?>
                                        </div>
                                        <?php if (!empty($task['assigned_user_name'])): ?>
                                            <div class="task-assigned-member">
                                                Assigned to: <?= htmlspecialchars($task['assigned_user_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                            $isProjectSupervisorForTask = $projectManager->isUserProjectSupervisor($project_id, $user_id);
                                            $isProjectMemberForTask = $projectManager->isUserProjectMember($project_id, $user_id);
                                            $isAssignedUserForTask = ($task['assigned_user_id'] == $user_id);

                                            $canShowEditButton = false;
                                            if ($isProjectSupervisorForTask) {
                                                $canShowEditButton = true;
                                            } elseif ($isProjectMemberForTask && $isAssignedUserForTask) {
                                                $canShowEditButton = true;
                                            }
                                        ?>
                                        <?php if ($canShowEditButton): ?>
                                        <a href="#" class="edit-task-btn" data-task-id="<?= htmlspecialchars($task['id']) ?>"
                                           data-task-title="<?= htmlspecialchars($task['title']) ?>"
                                           data-task-description="<?= htmlspecialchars($task['description']) ?>"
                                           data-task-priority="<?= htmlspecialchars($task['priority']) ?>"
                                           data-task-start-date="<?= htmlspecialchars($task['start_date'] ? date('Y-m-d\TH:i', strtotime($task['start_date'])) : '') ?>"
                                           data-task-end-date="<?= htmlspecialchars($task['end_date'] ? date('Y-m-d\TH:i', strtotime($task['end_date'])) : '') ?>"
                                           data-task-status="<?= htmlspecialchars($task['status']) ?>"
                                           data-task-project-id="<?= htmlspecialchars($task['project_id'] ?? '') ?>"
                                           data-task-deliverable-link="<?= htmlspecialchars($task['deliverable_link'] ?? '') ?>"
                                           data-task-assigned-user-id="<?= htmlspecialchars($task['assigned_user_id'] ?? '') ?>"
                                           data-user-role="<?= htmlspecialchars($user_info['role'] ?? 'member') ?>"
                                           data-is-project-supervisor="<?= $isProjectSupervisorForTask ? 'true' : 'false' ?>"
                                           data-is-project-member="<?= $isProjectMemberForTask ? 'true' : 'false' ?>"
                                           data-is-assigned-user="<?= $isAssignedUserForTask ? 'true' : 'false' ?>"
                                           data-is-personal-task="false"
                                           title="Edit">
                                             <span class="hidden-project-name" data-project-name="<?= htmlspecialchars($project['title']) ?>"></span>
                                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                                 <path d="M3 17.25V21h3.75l11-11.03-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                             </svg>
                                         </a>
                                         <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px;"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Edit Task Modal (copied from mytask.php) -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Task</h2>
                <span class="close-button edit-close-button">&times;</span>
            </div>
            <form action="view_project.php?project_id=<?= htmlspecialchars($project_id) ?>" method="POST">
                <input type="hidden" id="editTaskId" name="task_id">
                <div class="form-group" id="editProjectInfo">
                    <label>Project Name: <span id="editProjectName"></span></label>
                    <a id="editProjectLink" href="#" target="_blank" title="View Project">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M10 16.5l6-4.5-6-4.5v9zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        </svg>
                    </a>
                </div>
                <div class="form-group">
                    <label for="editTaskTitle">Task Title</label>
                    <input type="text" id="editTaskTitle" name="task_title" required>
                </div>
                <div class="form-group">
                    <label for="editTaskDescription">Description</label>
                    <textarea id="editTaskDescription" name="task_description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="editTaskStatus">Status</label>
                    <select id="editTaskStatus" name="task_status">
                        <option value="to_do">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="revision_needed">Revision Needed</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editDeliverableLink">Deliverable Link</label>
                    <div class="deliverable-link-input-group">
                        <input type="text" id="editDeliverableLink" name="deliverable_link" placeholder="e.g., Google Drive link">
                        <img src="../imgs/Copy.png" alt="Copy Link" class="copy-link-icon" id="copyDeliverableLink">
                    </div>
                </div>
                <div class="form-group">
                    <label for="editTaskPriority">Priority</label>
                    <select id="editTaskPriority" name="task_priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editStartDate">Start Date</label>
                    <input type="datetime-local" id="editStartDate" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="editDueDate">Due Date</label>
                    <input type="datetime-local" id="editDueDate" name="end_date" required>
                </div>
                <div class="form-group">
                    <label for="editAssignedUser">Assigned To</label>
                    <select id="editAssignedUser" name="assigned_user_id">
                        <?php foreach ($all_users as $member): ?>
                            <option value="<?= htmlspecialchars($member['id']) ?>">
                                <?= htmlspecialchars($member['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_task" class="btn btn-primary">Update Task</button>
                    <button type="button" class="btn btn-secondary edit-close-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>
<!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Settings:</h2>
                <span class="close-button settings-close-button">&times;</span>
            </div>
            <hr style="border: 0; height: 1px; background-color: #eee; margin: 20px 0;">
            <div class="settings-buttons" style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                <?php if ($is_supervisor): ?>
                <a href="Project tasks supervision.php?project_id=<?= htmlspecialchars($project_id) ?>" class="btn-green">Project tasks supervision</a>
                <button type="button" class="btn-orange">Project settings</button>
                <?php endif; ?>
                <?php if (!$is_supervisor): // Only show exit button if not supervisor ?>
                <form action="view_project.php?project_id=<?= htmlspecialchars($project_id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to exit this project?');">
                    <input type="hidden" name="exit_project" value="1">
                    <input type="hidden" name="project_id_to_exit" value="<?= htmlspecialchars($project_id) ?>">
                    <button type="submit" class="btn-danger" id="exitProjectBtn">Exit from project</button>
                </form>
                <?php else: ?>
                <p style="color: #888; font-size: 14px;">Supervisors cannot exit their own projects.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Invite Member Modal -->
    <div id="inviteMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>invite member:</h2>
                <span class="close-button invite-member-close-button">&times;</span>
            </div>
            <form action="view_project.php?project_id=<?= htmlspecialchars($project_id) ?>" method="POST">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
                <div class="form-group">
                    <label for="memberEmail">Email of member</label>
                    <input type="email" id="memberEmail" name="member_email" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="send_invite" class="btn btn-primary">Send Invite</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Project Menu Modal -->
    <div id="projectMenuModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>menu project</h2>
                <span class="close-button project-menu-close-button">&times;</span>
            </div>
            <div class="project-menu-content">
                <div class="project-menu-header-row">
                    <span class="project-menu-title">Project: <?= htmlspecialchars($project['title']) ?></span>
                    <div class="project-menu-icons">
                        <img src="../imgs/setting.png" alt="Settings" class="project-icon settings-icon-modal">
                        <img src="../imgs/Chat Bubble.png" alt="Chat" class="project-icon chat-icon-modal">
                    </div>
                </div>
                <hr class="project-menu-divider">
                <?php if ($is_supervisor): ?>
                <div class="project-menu-buttons">
                    <button type="button" class="btn btn-primary" id="newTaskBtnModal">+ New Task</button>
                    <button type="button" class="btn btn-primary" id="newMemberBtnModal">+ New Member</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['invite_failed']) && isset($_SESSION['form_errors'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const errors = <?= json_encode($_SESSION['form_errors']) ?>;
            if (errors.length > 0) {
                alert("Failed to invite member:\n" + errors.join("\n"));
            }
            <?php unset($_SESSION['form_errors']); // Clear the errors after displaying ?>
        });
    </script>
    <?php endif; ?>

    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <script>
        const currentUserId = <?= json_encode($user_id) ?>;
    </script>
    <script src="../scripts/script.js?v=3"></script>
</body>
</html>