<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Validator.php";
    require_once "../classes/Task.php";
    require_once "../classes/Project.php";
    require_once "../classes/Notification.php";

    $db = new Database();
    $pdo = $db->getConnection();

    // Check for user session
    if(!isset($_SESSION["user_id"])){
        header("Location: login.php");
        exit();
    }
    $user_id = $_SESSION["user_id"];

    // Instantiate all classes
    $user = new User($pdo);
    $taskManager = new Task($pdo);
    $projectManager = new Project($pdo);
    $notificationManager = new Notification($pdo);

    // Handle logout
    if(isset($_GET["logout"])){
        $user->logout();
        header("Location: login.php");
        exit();
    }

    // Fetch basic user info
    $user_info = $user->get_info($user_id);
    $user_name = isset($user_info["name"]) ? $user_info["name"] : "User";
    $user_role = $user->get_role($user_id);

    // Fetch all users for assignment dropdowns
    $all_users = $user->getAllUsers();

    // Handle new task creation
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create_task"])) {
        $title = $_POST["task_title"] ?? '';
        $description = $_POST["task_description"] ?? '';
        $priority = $_POST["task_priority"] ?? 'medium';
        $startDate = $_POST["start_date"] ?? null;
        $endDate = $_POST["end_date"] ?? null;

        $errors = [];

        if (!Validator::isNotEmpty($title)) {
            $errors[] = "Task title cannot be empty.";
        }
        if (!Validator::isNotEmpty($startDate)) {
            $errors[] = "Start Date cannot be empty.";
        }
        if (!Validator::isNotEmpty($endDate)) {
            $errors[] = "Due Date cannot be empty.";
        }

        if (empty($errors)) {
            $taskCreated = $taskManager->createTask(
                $title,
                $description,
                $startDate,
                $endDate,
                $priority,
                $user_id, // assigned_user_id
                $user_id  // created_by_id
            );

            if ($taskCreated) {
                header("Location: mytask.php?task_created=success");
                exit();
            } else {
                $error_message = "Failed to create task.";
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }

    // Handle task update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_task"])) {
        $taskId = $_POST["task_id"] ?? null;
        $title = $_POST["task_title"] ?? '';
        $description = $_POST["task_description"] ?? '';
        $priority = $_POST["task_priority"] ?? 'medium';
        $startDate = $_POST["start_date"] ?? null;
        $endDate = $_POST["end_date"] ?? null;
        $status = $_POST["task_status"] ?? 'to_do';
        $deliverableLink = $_POST["deliverable_link"] ?? null;
        $task = $taskManager->getTaskById($taskId);

        // Fetch assignedUserId AFTER fetching the task
        $assignedUserId = $task['assigned_user_id'] ?? null; // Use null coalescing to prevent errors if $task is null

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

            $canEditAllFields = false;
            if ($isProjectTask) {
                if ($isProjectSupervisor) {
                    $canEditAllFields = true;
                }
            } else { // Personal Task
                if ($isAssignedUser) {
                    $canEditAllFields = true;
                }
            }

            // Apply validation based on permissions
            if ($canEditAllFields) {
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
                $canEditAll = false;
                $canChangeStatusToCompleted = false;

                // Check if it's a personal task or user is assigned
                if ($task['project_id'] === null || $task['assigned_user_id'] == $user_id) {
                    $canEditAll = true;
                    $canChangeStatusToCompleted = true;
                }

                // Check project permissions if it's a project task
                if ($task['project_id'] !== null) {
                    $isSupervisor = $projectManager->isUserProjectSupervisor($task['project_id'], $user_id);
                    $isMember = $projectManager->isUserProjectMember($task['project_id'], $user_id);

                    if ($isSupervisor) {
                        $canEditAll = true;
                        $canChangeStatusToCompleted = true;
                    } elseif ($isMember) {
                        // Members can only change status, but not to 'completed' unless they are the assigned user
                        if ($task['assigned_user_id'] == $user_id) {
                            $canEditAll = false; // Can't edit all fields, only status
                            $canChangeStatusToCompleted = true; // Can complete their own task
                        } else {
                            $canEditAll = false;
                            $canChangeStatusToCompleted = false;
                        }
                    }
                }

                // Apply permissions
                // Determine if the task is a project task
                $isProjectTask = ($task['project_id'] !== null);

                // Determine if the current user is the assigned user for this task
                $isAssignedUser = ($task['assigned_user_id'] == $user_id);

                // Determine if the current user is the supervisor of the project this task belongs to
                $isProjectSupervisor = false;
                if ($isProjectTask) {
                    $isProjectSupervisor = $projectManager->isUserProjectSupervisor($task['project_id'], $user_id);
                }

                // Determine if the current user is a member of the project (but not a supervisor)
                $isProjectMember = false;
                if ($isProjectTask) {
                    $isProjectMember = $projectManager->isUserProjectMember($task['project_id'], $user_id);
                }

                $isNonSupervisorProjectMember = $isProjectTask && $isProjectMember && !$isProjectSupervisor;

                $updateSuccess = false;
                $canEditAllFields = false;
                $canEditStatus = false;
                $canMarkCompleted = false;
                $canChangeAssignedUser = false; // New permission

                if ($isProjectTask) {
                    // Logic for Project Tasks
                    if ($isProjectSupervisor) {
                        // Project Supervisor: Full permissions
                        $canEditAllFields = true;
                        $canEditStatus = true;
                        $canMarkCompleted = true;
                        $canChangeAssignedUser = true;
                    } elseif ($isNonSupervisorProjectMember) {
                        // Non-supervisor Project Member: Restricted permissions
                        $canEditAllFields = false; // Cannot edit all fields
                        $canEditStatus = true; // Can edit status
                        $canChangeAssignedUser = false; // Cannot change assigned user
                        $canMarkCompleted = false; // Cannot mark as completed
                    } else {
                        // Project Task, but user is not supervisor and not a member
                        $error_message = "You do not have permission to edit this project task.";
                    }
                } else {
                    // Logic for Personal Tasks (not associated with a project)
                    if ($isAssignedUser) {
                        // Assigned User for personal task: Full permissions
                        $canEditAllFields = true;
                        $canEditStatus = true;
                        $canMarkCompleted = true;
                        $canChangeAssignedUser = true;
                    } else {
                        // Personal Task, but user is not assigned
                        $error_message = "You do not have permission to edit this personal task.";
                    }
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
                } elseif ($isNonSupervisorProjectMember) {
                    // Non-supervisor project members can only update status and deliverable_link
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
                } elseif ($canEditStatus) {
                    // This block handles cases where only status can be changed (e.g., assigned user for personal task)
                    if ($status == 'completed' && !$canMarkCompleted) {
                        $error_message = "You do not have permission to mark this task as completed.";
                        $updateSuccess = false;
                    } else {
                        $updateSuccess = $taskManager->updateTask(
                            $taskId,
                            $task['title'],
                            $task['description'],
                            $task['start_date'],
                            $task['end_date'],
                            $task['priority'],
                            $status,
                            $deliverableLink,
                            $task['assigned_user_id']
                        );
                    }
                } else {
                    $error_message = "You do not have permission to edit this task.";
                    $updateSuccess = false;
                }

                if ($updateSuccess) {
                    header("Location: mytask.php?task_updated=success");
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

    // Handle task deletion
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_task"])) {
        $taskId = $_POST["task_id"] ?? null;

        if (!Validator::isNotEmpty($taskId)) {
            $error_message = "Task ID is missing for deletion.";
        } else {
            $task = $taskManager->getTaskById($taskId);

            if ($task) {
                // Only allow deletion if it's a personal task and the current user is the assigned user
                if ($task['project_id'] === null && $task['assigned_user_id'] == $user_id) {
                    $deleteSuccess = $taskManager->deleteTask($taskId);

                    if ($deleteSuccess) {
                        header("Location: mytask.php?task_deleted=success");
                        exit();
                    } else {
                        $error_message = "Failed to delete task.";
                    }
                } else {
                    $error_message = "You do not have permission to delete this task or it is not a personal task.";
                }
            } else {
                $error_message = "Task not found.";
            }
        }
    }

    // Function to get initials from name, for avatar
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
    
    // Get notification count using the Notification class
    $unread_notifications = $notificationManager->getUnreadCount($user_id);

    // Helper to map priority/status to CSS classes and labels
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
            default:                return ucfirst(str_replace('_', ' ', $status));
        }
    }

    // Pagination variables
    $tasks_per_page = 5; // Number of tasks to display per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $tasks_per_page;

    // Fetch filtered tasks
    $search_query = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $project_filter = $_GET['project'] ?? '';
    $days_filter = $_GET['days'] ?? '';
    $start_date_filter = $_GET['start_date'] ?? '';
    $end_date_filter = $_GET['end_date'] ?? '';

    $all_tasks = $taskManager->getAllTasksForUser(
        $user_id,
        $search_query,
        $status_filter,
        $project_filter,
        $days_filter,
        $start_date_filter,
        $end_date_filter,
        $tasks_per_page,
        $offset
    );
    $total_tasks_count = $taskManager->getTaskCountForUserFiltered(
        $user_id,
        $search_query,
        $status_filter,
        $project_filter,
        $days_filter,
        $start_date_filter,
        $end_date_filter
    );
    $total_pages = ceil($total_tasks_count / $tasks_per_page);

    // Fetch all projects for the project filter dropdown
    $user_projects = $projectManager->getProjectsForUser($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks</title>
    <link rel="stylesheet" href="../style/dashboard.css?v=5">
    <style>
        /* Additional styles for mytask.php specific elements */
        .my-tasks-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .search-filter-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .search-bar {
            display: flex;
            margin-bottom: 15px;
        }

        .search-bar input {
            flex-grow: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px 0 0 8px;
            font-size: 16px;
            outline: none;
        }

        .search-bar button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .search-bar button:hover {
            background: linear-gradient(45deg, #764ba2, #667eea);
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .filter-options select,
        .filter-options input[type="date"] {
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

        .filter-options input[type="date"] {
            background-image: url('../imgs/Calendar.png'); /* Use the provided image path */
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 24px auto; /* Increased size */
            padding-right: 35px; /* Add padding to make space for the larger icon */
            /* Removed appearance: none to allow native date picker to function */
        }

        /* Ensure the native calendar picker indicator is visible and clickable */
        .filter-options input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0; /* Make the default indicator transparent */
            width: 35px; /* Ensure it has a clickable area */
            cursor: pointer;
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

        .tasks-count {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            text-decoration: none;
            color: #667eea;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .pagination a:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }

        .pagination .current-page {
            background-color: #667eea;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="header fade-in">
        <div class="nav container">
            <a href="dashboard.php" class="logo">OTask</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="mytask.php" class="active">My Tasks</a></li>
                <li><a href="projects.php">Projects</a></li>
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
        <?php if (isset($error_message)): ?>
            <div class="error-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= $error_message ?>
            </div>
        <?php endif; ?>
        <div class="my-tasks-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="color: #333; font-size: 28px;">My Task</h1>
                <a id="newTaskBtn" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/>
                    </svg>
                    New Task
                </a>
            </div>

            <div class="search-filter-section">
                <form action="mytask.php" method="GET">
                    <div class="search-bar">
                        <input type="text" name="search" placeholder="Search tasks..." value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="filter-options">
                        <select name="status">
                            <option value="">select status</option>
                            <option value="to_do" <?= $status_filter == 'to_do' ? 'selected' : '' ?>>To Do</option>
                            <option value="in_progress" <?= $status_filter == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="pending_review" <?= $status_filter == 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="revision_needed" <?= $status_filter == 'revision_needed' ? 'selected' : '' ?>>Revision Needed</option>
                            <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        <select name="project">
                            <option value="">select project</option>
                            <?php foreach ($user_projects as $project): ?>
                                <option value="<?= htmlspecialchars($project['id']) ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['title']) ?>
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
                        <label for="start_date">date between:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date_filter) ?>">
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date_filter) ?>">
                        <button type="button" class="reset-btn" onclick="window.location.href='mytask.php'">Reset</button>
                    </div>
                </form>
            </div>

            <div class="tasks-count"><?= $total_tasks_count ?> Tasks</div>

            <div class="task-list">
                <?php if (empty($all_tasks)): ?>
                    <p style="color: #666;">No tasks found matching your criteria.</p>
                <?php else: ?>
                    <?php foreach ($all_tasks as $task):
                        $due = $task['end_date'];
                        $due_str = $due ? date('M d, Y', strtotime($due)) : 'No due date';
                        $prio = $task['priority'];
                        $stat = $task['status'];
                        
                        $isProjectSupervisorForTask = false;
                        if ($task['project_id'] !== null) {
                            $isProjectSupervisorForTask = $projectManager->isUserProjectSupervisor($task['project_id'], $user_id);
                        }
                    ?>
                    <div class="task-item">
                        <div class="task-header">
                            <div class="task-title">
                                <?= htmlspecialchars($task['title']) ?>
                                <?php if ($task['project_id'] !== null): ?> <span class="project-indicator">(project)</span><?php endif; ?>
                                <?php if ($isProjectSupervisorForTask): ?> <span class="admin-indicator">admin</span><?php endif; ?>
                            </div>
                            <div class="task-priority <?= priorityClass($prio) ?>">
                                <?= priorityLabel($prio) ?>
                            </div>
                        </div>
                        <?php if (!empty($task['description'])): ?>
                        <div class="task-desc" style="margin-bottom:10px; color:#555;">
                            <?= nl2br(htmlspecialchars($task['description'])) ?>
                        </div>
                        <?php endif; ?>
                        <div class="task-meta">
                            <div class="task-due">Due: <?= htmlspecialchars($due_str) ?></div>
                            <div class="task-status <?= statusClass($stat) ?>">
                                <?= statusLabel($stat) ?>
                            </div>
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
                               data-user-role="<?= htmlspecialchars($user_role) ?>"
                               data-is-project-supervisor="<?= $isProjectSupervisorForTask ? 'true' : 'false' ?>"
                               data-is-project-member="<?= $projectManager->isUserProjectMember($task['project_id'] ?? null, $user_id) ? 'true' : 'false' ?>"
                               data-is-personal-task="<?= ($task['project_id'] === null) ? 'true' : 'false' ?>"
                               title="Edit">
<?php
    $project_name = '';
    if ($task['project_id'] !== null) {
        $project_info = $projectManager->getProjectById($task['project_id']);
        if ($project_info) {
            $project_name = htmlspecialchars($project_info['title']);
        }
    }
?>
<span class="hidden-project-name" data-project-name="<?= $project_name ?>"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 17.25V21h3.75l11-11.03-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="mytask.php?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&status=<?= urlencode($status_filter) ?>&project=<?= urlencode($project_filter) ?>&days=<?= urlencode($days_filter) ?>&start_date=<?= urlencode($start_date_filter) ?>&end_date=<?= urlencode($end_date_filter) ?>"
                       class="<?= $i == $current_page ? 'current-page' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- New Task Modal (copied from dashboard.php) -->
    <div id="newTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <span class="close-button">&times;</span>
            </div>
            <form action="mytask.php" method="POST">
                <div class="form-group">
                    <label for="taskTitle">Task Title</label>
                    <input type="text" id="taskTitle" name="task_title" required>
                </div>
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="task_description" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="taskPriority">Priority</label>
                    <select id="taskPriority" name="task_priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="startDate">Start Date</label>
                    <input type="datetime-local" id="startDate" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="dueDate">Due Date</label>
                    <input type="datetime-local" id="dueDate" name="end_date" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="create_task" class="btn btn-primary">Create Task</button>
                    <button type="button" class="btn btn-secondary close-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const currentUserId = <?= json_encode($user_id) ?>;
    </script>
    <script src="../scripts/script.js?v=6"></script>

    <!-- Edit Task Modal (copied from dashboard.php) -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Task</h2>
                <span class="close-button edit-close-button">&times;</span>
            </div>
            <form action="mytask.php" method="POST">
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
                <div class="form-actions">
                    <button type="submit" name="update_task" class="btn btn-primary">Update Task</button>
                    <button type="button" class="btn btn-secondary edit-close-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
</body>
</html>