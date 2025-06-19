<?php
session_start();
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Project.php';
require_once '../classes/Task.php';

$database = new Database();
$pdo = $database->getConnection();

$user = new User($pdo);
$project = new Project($pdo);
$task = new Task($pdo);

if (!$user->autoLogin()) {
    header('Location: login.php');
    exit();
}

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($project_id === 0) {
    // Redirect or show an error if project_id is not provided
    header('Location: projects.php'); // Or a suitable error page
    exit();
}

// Check if the current user is a supervisor or member of this project
$current_user_id = $_SESSION['user_id'];
$is_supervisor = $project->isUserProjectSupervisor($project_id, $current_user_id);
$is_member = $project->isUserProjectMember($project_id, $current_user_id);

if (!$is_supervisor && !$is_member) {
    // User is neither supervisor nor member, deny access
    header('Location: projects.php'); // Redirect to projects page or an access denied page
    exit();
}

$project_info = $project->getProjectById($project_id);
if (!$project_info) {
    // Project not found
    header('Location: projects.php');
    exit();
}

// Fetch members for the project
$project_members = $project->getProjectMembers($project_id);

// If the current user is a supervisor and not already in the project members list, add them
if ($is_supervisor) {
    $is_current_user_in_members = false;
    foreach ($project_members as $member) {
        if ($member['id'] == $current_user_id) {
            $is_current_user_in_members = true;
            break;
        }
    }
    if (!$is_current_user_in_members) {
        // Add current supervisor to the members list for assignment purposes
        $project_members[] = [
            'id' => $current_user_id,
            'name' => $user_info['name'] . ' (You)', // Indicate it's the current user
            'email' => $user_info['email'] // Assuming email is available
        ];
    }
}

// Fetch tasks for the project
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$assignedUserFilter = isset($_GET['assigned_user_filter']) ? $_GET['assigned_user_filter'] : '';
$project_tasks = $task->getTasksByProjectIdFiltered($project_id, $statusFilter, $assignedUserFilter);

// Prepare data for members tab
$members_data = [];
foreach ($project_members as $member) {
    $member_task_count = $task->getTaskCountForUserFiltered($member['id'], '', '', $project_id); // Get all tasks for each member in this project
    $members_data[] = [
        'id' => $member['id'],
        'name' => $member['name'],
        'task_count' => $member_task_count
    ];
}

// Prepare data for tasks tab
$tasks_data = [];
foreach ($project_tasks as $proj_task) {
    $assigned_user_info = $user->get_info($proj_task['assigned_user_id']);
    $tasks_data[] = [
        'id' => $proj_task['id'],
        'title' => $proj_task['title'],
        'description' => $proj_task['description'],
        'assigned_user_name' => $assigned_user_info ? $assigned_user_info['name'] : 'Unassigned',
        'status' => $proj_task['status']
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Tasks Supervision - <?php echo htmlspecialchars($project_info['title']); ?></title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/dashboard.css?v=1">
    <style>
        :root {
            /* Primary Theme Colors */
            --color-primary: #4F46E5;     /* Indigo 600 */
            --color-secondary: #6366F1;   /* Indigo 500 */
            --color-accent: #F59E0B;      /* Amber 500 */
          
            /* Status Colors */
            --color-success: #10B981;     /* Green 500 */
            --color-warning: #F97316;     /* Orange 500 */
            --color-error: #EF4444;       /* Red 500 */
          
            /* Backgrounds */
            --color-bg-main: #F9FAFB;     /* Light gray */
            --color-bg-card: #FFFFFF;     /* White */
          
            /* Text Colors */
            --color-text-main: #111827;   /* Dark gray */
            --color-text-muted: #6B7280;  /* Gray 500 */
          
            /* Shadow & Radius */
            --shadow-soft: 0 4px 12px rgba(0, 0, 0, 0.05);
            --radius-lg: 12px;
            --radius-md: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--color-bg-main);
            color: var(--color-text-main);
            padding: 0; /* Remove padding from body */
        }


        h1 {
            color: var(--color-primary);
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2.5rem;
            font-weight: 600;
        }

        .table-container {
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow-x: auto;
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            /* Ensure table content doesn't wrap unnecessarily */
            white-space: nowrap;
        }

        thead {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        }

        th {
            padding: 1.5rem 1rem;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background-color: #F3F4F6;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        td {
            padding: 1.5rem 1rem;
            vertical-align: top;
        }

        .employee-name, .task-title {
            font-weight: 600;
            color: var(--color-text-main);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .task-description {
            color: var(--color-text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            max-width: 300px;
        }

        .task-count {
            display: inline-block;
            background: linear-gradient(135deg, var(--color-accent), #FBBF24);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
            min-width: 60px;
            text-align: center;
        }

        .owner-assigned {
            display: inline-block;
            background: linear-gradient(135deg, var(--color-success), #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .owner-unassigned {
            display: inline-block;
            background: linear-gradient(135deg, var(--color-warning), #EA580C);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--color-error), #DC2626);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }

        .btn-assign {
            background: linear-gradient(135deg, var(--color-success), #059669);
            color: white;
        }

        .btn-assign {
            background: linear-gradient(135deg, var(--color-success), #059669);
            color: white;
        }
 
        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
 
        .btn-unassign {
            background: linear-gradient(135deg, #F59E0B, #D97706); /* Amber 500 to Amber 700 */
            color: white;
        }
 
        .btn-unassign:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        @media (max-width: 1024px) {
            .task-description {
                max-width: 200px;
            }
        }

        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            th, td {
                padding: 1rem 0.5rem;
                font-size: 0.9rem;
            }
            
            h1 {
                font-size: 2rem;
            }

            .task-description {
                max-width: none;
            }

            .table-container table {
                min-width: 700px; /* Adjust this value as needed for content to overflow */
            }
        }

        /* Animation for table load */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .table-container {
            animation: fadeInUp 0.6s ease;
        }

        /* Column widths */
        .col-title { width: 25%; }
        .col-description { width: 35%; }
        .col-owner { width: 20%; }
        .col-actions { width: 20%; }

        /* Tabs styling */
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            background: var(--color-bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 0.5rem;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--color-text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: var(--radius-md);
        }

        .tab-button.active {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
            box-shadow: var(--shadow-soft);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--color-bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
        }

        .search-bar input {
            flex-grow: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 0.5rem;
            background: transparent;
            color: var(--color-text-main);
        }

        .search-bar .search-icon {
            color: var(--color-text-muted);
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }

        .new-button {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 1rem;
            display: inline-block;
        }
        .new-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }

        .select-owner {
            padding: 0.75rem 1.25rem;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-md);
            background-color: var(--color-bg-card);
            font-size: 1rem;
            color: var(--color-text-main);
            cursor: pointer;
            margin-bottom: 1.5rem;
            display: block;
            width: fit-content;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.5); /* Black w/ opacity */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Center content vertically */
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background-color: var(--color-bg-card);
            margin: auto;
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            width: 90%;
            max-width: 600px;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease-in-out;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--color-primary);
        }

        .close-button {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-button:hover,
        .close-button:focus {
            color: var(--color-error);
            text-decoration: none;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-main);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="datetime-local"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #D1D5DB;
            border-radius: var(--radius-md);
            font-size: 1rem;
            color: var(--color-text-main);
            background-color: var(--color-bg-main);
            box-sizing: border-box; /* Ensure padding doesn't add to width */
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-actions {
            padding-top: 15px;
            border-top: 1px solid #eee;
            margin-top: 20px;
            text-align: right;
        }

        .form-actions .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .form-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.4);
        }

        .unassigned-task-list {
            list-style: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #E5E7EB;
            border-radius: var(--radius-md);
            background-color: #F9FAFB;
        }

        .unassigned-task-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #E5E7EB;
        }

        .unassigned-task-list li:last-child {
            border-bottom: none;
        }

        .unassigned-task-list li span {
            font-weight: 500;
            color: var(--color-text-main);
            flex-grow: 1;
            margin-right: 10px;
        }

        .unassigned-task-list .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
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
                    <?php
                        require_once "../classes/Notification.php";
                        $notificationManager = new Notification($pdo);
                        $unread_notifications = $notificationManager->getUnreadCount($current_user_id);
                    ?>
                    <?php if ($unread_notifications > 0): ?>
                    <div class="notification-badge"><?= $unread_notifications ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-avatar" onclick="window.location.href='profile.php'">
                    <?php
                        $user_info = $user->get_info($current_user_id);
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
                    ?>
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
    <main style="max-width: 1200px; margin: 2em auto; padding: 20px; border-radius: 15px;">
    <div class="container">
        <h1>Project Tasks Supervision - <?php echo htmlspecialchars($project_info['title']); ?></h1>
        <a href="view_project.php?project_id=<?php echo htmlspecialchars($project_id); ?>" class="btn btn-info" style="margin-bottom: 20px;">Back to Project Overview</a>

        <div class="tabs">
            <button class="tab-button active" onclick="openTab(event, 'Members')">Members</button>
            <button class="tab-button" onclick="openTab(event, 'Tasks')">Tasks</button>
        </div>

        <div id="Members" class="tab-content active">
            <button type="button" class="btn btn-primary" id="newMemberBtn">+ New Member</button>
            <div class="search-bar">
                <input type="text" id="memberSearchInput" placeholder="Search members...">
                <span class="search-icon">🔍</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Tasks Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members_data)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px;">No members found for this project.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members_data as $member): ?>
                                <tr>
                                    <td class="employee-name"><?php echo htmlspecialchars($member['name']); ?></td>
                                    <td><span class="task-count"><?php echo htmlspecialchars($member['task_count']); ?></span></td>
                                    <td class="actions">
                                        <?php if ($member['id'] != $current_user_id): ?>
                                            <button class="btn btn-delete" onclick="deleteEmployee('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>, <?php echo $project_id; ?>)">Delete</button>
                                        <?php else: ?>
                                            <button class="btn btn-delete" disabled title="You cannot delete yourself from the project.">Delete</button>
                                        <?php endif; ?>
                                        <button class="btn btn-info" onclick="showInfo('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>)">Information</button>
                                        <button class="btn btn-assign" onclick="showAssignTasksModal('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>)">Assign Task</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="Tasks" class="tab-content">
            <button class="new-button" id="newTaskBtn">+ New Task</button>
            <div class="search-bar">
                <input type="text" id="taskSearchInput" placeholder="Search tasks...">
                <span class="search-icon">🔍</span>
            </div>
            <select class="select-owner" id="taskOwnerFilter">
                <option value="">All Owners</option>
                <?php foreach ($project_members as $member): ?>
                    <option value="<?php echo htmlspecialchars($member['id']); ?>" <?php echo ($assignedUserFilter == $member['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($member['name']); ?></option>
                <?php endforeach; ?>
                <option value="unassigned" <?php echo ($assignedUserFilter == 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
            </select>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="col-title">Task Title</th>
                            <th class="col-description">Description</th>
                            <th class="col-owner">Owner</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks_data)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px;">No tasks found for this project.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks_data as $proj_task): ?>
                                <tr>
                                    <td>
                                        <div class="task-title"><?php echo htmlspecialchars($proj_task['title']); ?></div>
                                    </td>
                                    <td>
                                        <div class="task-description"><?php echo htmlspecialchars($proj_task['description']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($proj_task['assigned_user_name'] === 'Unassigned'): ?>
                                            <span class="owner-unassigned"><?php echo htmlspecialchars($proj_task['assigned_user_name']); ?></span>
                                        <?php else: ?>
                                            <span class="owner-assigned"><?php echo htmlspecialchars($proj_task['assigned_user_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <button class="btn btn-delete" onclick="deleteTask('<?php echo htmlspecialchars($proj_task['title']); ?>', <?php echo $proj_task['id']; ?>)">Delete</button>
                                        <button class="btn btn-info" onclick="showTaskInfo('<?php echo htmlspecialchars($proj_task['title']); ?>', <?php echo $proj_task['id']; ?>)">Information</button>
                                        <?php if ($proj_task['assigned_user_name'] !== 'Unassigned'): ?>
                                            <button class="btn btn-unassign" onclick="unassignTask('<?php echo htmlspecialchars($proj_task['title']); ?>', <?php echo $proj_task['id']; ?>)">Unassign</button>
                                        <?php else: ?>
                                            <button class="btn btn-assign" onclick="assignMemberToTask('<?php echo htmlspecialchars($proj_task['title']); ?>', <?php echo $proj_task['id']; ?>)">Assign Member</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        // Set default tab to open
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementsByClassName('tab-button')[0].click(); // Clicks the first tab (Members) by default

            // Get elements for invite member modal
            const newMemberBtn = document.getElementById('newMemberBtn');
            const inviteMemberModal = document.getElementById('inviteMemberModal');
            const inviteMemberCloseButtons = document.querySelectorAll('.invite-member-close-button');

            if (newMemberBtn) {
                newMemberBtn.addEventListener('click', () => {
                    inviteMemberModal.classList.add('show');
                });
            }

            inviteMemberCloseButtons.forEach(button => {
                button.addEventListener('click', () => {
                    inviteMemberModal.classList.remove('show');
                });
            });

            window.addEventListener('click', (event) => {
                if (event.target == inviteMemberModal) {
                    inviteMemberModal.classList.remove('show');
                }
            });

            // New Task Modal functionality
            const newTaskBtn = document.getElementById('newTaskBtn');
            const newTaskModal = document.getElementById('newTaskModal');
            const newTaskCloseButtons = document.querySelectorAll('.new-task-close-button');
            const newTaskForm = document.getElementById('newTaskForm');

            if (newTaskBtn) {
                newTaskBtn.addEventListener('click', () => {
                    newTaskModal.classList.add('show');
                });
            }

            newTaskCloseButtons.forEach(button => {
                button.addEventListener('click', () => {
                    newTaskModal.classList.remove('show');
                });
            });

            window.addEventListener('click', (event) => {
                if (event.target == newTaskModal) {
                    newTaskModal.classList.remove('show');
                }
            });

            if (newTaskForm) {
                newTaskForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const data = Object.fromEntries(formData.entries());
                    data.project_id = <?php echo $project_id; ?>; // Ensure project_id is included

                    // Add priority, start_date, and end_date to the data
                    data.priority = document.getElementById('taskPriority').value;
                    data.start_date = document.getElementById('taskStartDate').value;
                    data.end_date = document.getElementById('taskEndDate').value;

                    fetch('../api/create_task.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('Task created successfully!');
                            newTaskModal.classList.remove('show');
                            location.reload(); // Reload to update task list
                        } else {
                            alert('Error creating task: ' + result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while creating the task.');
                    });
                });
            }

            // Search functionality for members
            const memberSearchInput = document.getElementById('memberSearchInput');
            if (memberSearchInput) {
                memberSearchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#Members tbody tr');
                    rows.forEach(row => {
                        const name = row.querySelector('.employee-name').textContent.toLowerCase();
                        if (name.includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Search functionality for tasks
            const taskSearchInput = document.getElementById('taskSearchInput');
            if (taskSearchInput) {
                taskSearchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#Tasks tbody tr');
                    rows.forEach(row => {
                        const title = row.querySelector('.task-title').textContent.toLowerCase();
                        const description = row.querySelector('.task-description').textContent.toLowerCase();
                        const owner = row.querySelector('.owner-assigned, .owner-unassigned').textContent.toLowerCase();
                        if (title.includes(filter) || description.includes(filter) || owner.includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Filter by owner for tasks
            const taskOwnerFilter = document.getElementById('taskOwnerFilter');
            if (taskOwnerFilter) {
                taskOwnerFilter.addEventListener('change', function() {
                    const selectedOwner = this.value;
                    const currentUrl = new URL(window.location.href);
                    if (selectedOwner) {
                        currentUrl.searchParams.set('assigned_user_filter', selectedOwner);
                    } else {
                        currentUrl.searchParams.delete('assigned_user_filter');
                    }
                    window.location.href = currentUrl.toString();
                });
            }

            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
                row.style.animation = 'fadeInUp 0.6s ease forwards';
            });
        });

        function deleteEmployee(name, id, projectId) {
            if (confirm(`Are you sure you want to remove ${name} from this project?`)) {
                fetch('../api/delete_member.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ member_id: id, project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`${name} has been removed from the project successfully!`);
                        location.reload(); // Reload to update member list
                    } else {
                        alert('Failed to remove member: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing the member.');
                });
            }
        }

        function showInfo(name, id) {
            const memberTasksInfoModal = document.getElementById('memberTasksInfoModal');
            memberTasksInfoModal.style.display = 'flex';
            memberTasksInfoModal.classList.add('show');
            document.getElementById('memberTasksInfoName').textContent = name;
            fetchMemberTasks(id, <?php echo $project_id; ?>);
        }

        function fetchMemberTasks(memberId, projectId) {
            fetch(`../api/get_tasks_for_member_in_project.php?member_id=${memberId}&project_id=${projectId}`)
                .then(response => response.json())
                .then(result => {
                    const taskList = document.getElementById('memberTasksList');
                    taskList.innerHTML = '';
                    if (result.success && result.tasks.length > 0) {
                        result.tasks.forEach(task => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <span><strong>${task.title}</strong> (Status: ${task.status})</span>
                                <p>${task.description}</p>
                                <p>Due: ${task.end_date ? new Date(task.end_date).toLocaleString() : 'N/A'}</p>
                            `;
                            taskList.appendChild(li);
                        });
                    } else {
                        taskList.innerHTML = '<li>No tasks found for this member in this project.</li>';
                        if (result.message) {
                            console.warn('API message:', result.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching member tasks:', error);
                    document.getElementById('memberTasksList').innerHTML = '<li>Error loading tasks.</li>';
                });
        }

        let currentMemberIdForAssignment = null;
        let currentMemberNameForAssignment = null;

        function showAssignTasksModal(memberName, memberId) {
            currentMemberIdForAssignment = memberId;
            currentMemberNameForAssignment = memberName;
            const assignAllTasksModal = document.getElementById('assignAllTasksModal');
            assignAllTasksModal.style.display = 'flex';
            assignAllTasksModal.classList.add('show');
            document.getElementById('assignAllTasksMemberName').textContent = memberName;
            fetchUnassignedTasks();
        }

        function fetchUnassignedTasks() {
            const projectId = <?php echo $project_id; ?>;
            fetch(`../api/get_unassigned_tasks.php?project_id=${projectId}`)
                .then(response => response.json())
                .then(result => {
                    const taskList = document.getElementById('unassignedTaskList');
                    taskList.innerHTML = '';
                    if (result.success && result.tasks.length > 0) {
                        result.tasks.forEach(task => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <span>${task.title}</span>
                                <button class="btn btn-assign btn-small" onclick="assignTaskFromModal(${task.id}, '${task.title}')">Assign</button>
                            `;
                            taskList.appendChild(li);
                        });
                    } else {
                        taskList.innerHTML = '<li>No unassigned tasks available.</li>';
                        if (result.message) {
                            console.warn('API message:', result.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching unassigned tasks:', error);
                    document.getElementById('unassignedTaskList').innerHTML = '<li>Error loading tasks.</li>';
                });
        }

        function assignTaskFromModal(taskId, taskTitle) {
            if (currentMemberIdForAssignment) {
                if (confirm(`Assign "${taskTitle}" to ${currentMemberNameForAssignment}?`)) {
                    fetch('../api/assign_task_to_member.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            task_id: taskId,
                            user_id: currentMemberIdForAssignment,
                            project_id: <?php echo $project_id; ?>
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Task "${taskTitle}" assigned to ${currentMemberNameForAssignment} successfully!`);
                            document.getElementById('assignAllTasksModal').classList.remove('show');
                            document.getElementById('assignAllTasksModal').style.display = 'none';
                            location.reload(); // Reload to update task lists
                        } else {
                            alert('Failed to assign task: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error assigning task:', error);
                        alert('An error occurred while assigning the task.');
                    });
                }
            } else {
                alert('No member selected for assignment.');
            }
        }

        function deleteTask(taskTitle, id) {
            if (confirm(`Are you sure you want to delete the task "${taskTitle}"?`)) {
                fetch('../api/delete_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ task_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Task "${taskTitle}" has been deleted successfully!`);
                        location.reload(); // Reload to update task list
                    } else {
                        alert('Failed to delete task: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the task.');
                });
            }
        }

        function showTaskInfo(taskTitle, id) {
            alert(`Showing detailed information for task: "${taskTitle}" (ID: ${id})\n\nThis would typically open a detailed view or modal with complete task information, timeline, attachments, and comments.`);
            // In a real application, you would fetch detailed task info via AJAX
        }
 
        function unassignTask(taskTitle, taskId) {
            if (confirm(`Are you sure you want to unassign the task "${taskTitle}"?`)) {
                fetch('../api/unassign_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ task_id: taskId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Task "${taskTitle}" has been unassigned successfully!`);
                        location.reload(); // Reload to update task list
                    } else {
                        alert('Failed to unassign task: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while unassigning the task.');
                });
            }
        }

        let currentTaskIdForAssignment = null;
        let currentTaskTitleForAssignment = null;

        function assignMemberToTask(taskTitle, taskId) {
            currentTaskIdForAssignment = taskId;
            currentTaskTitleForAssignment = taskTitle;
            document.getElementById('assignSpecificTaskModal').classList.add('show');
            document.getElementById('assignSpecificTaskTitle').textContent = taskTitle;
            // Populate the member dropdown in this modal
            const memberSelect = document.getElementById('assignSpecificTaskMemberSelect');
            memberSelect.innerHTML = '<option value="">Select Member</option>';
            projectMembers.forEach(member => {
                const option = document.createElement('option');
                option.value = member.id;
                option.textContent = member.name;
                memberSelect.appendChild(option);
            });
        }

        function confirmAssignSpecificTask() {
            const memberId = document.getElementById('assignSpecificTaskMemberSelect').value;
            if (!memberId) {
                alert('Please select a member.');
                return;
            }

            if (confirm(`Assign "${currentTaskTitleForAssignment}" to the selected member?`)) {
                fetch('../api/assign_task_to_member.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        task_id: currentTaskIdForAssignment,
                        user_id: memberId,
                        project_id: <?php echo $project_id; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Task "${currentTaskTitleForAssignment}" assigned successfully!`);
                        document.getElementById('assignSpecificTaskModal').classList.remove('show');
                        location.reload(); // Reload to update task lists
                    } else {
                        alert('Failed to assign task: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error assigning task:', error);
                    alert('An error occurred while assigning the task.');
                });
            }
        }
    </script>
    <!-- Invite Member Modal -->
    <div id="inviteMemberModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Invite Member</h2>
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

    <!-- Assign All Tasks Modal (Existing - for assigning to a specific member) -->
    <div id="assignAllTasksModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Tasks to <span id="assignAllTasksMemberName"></span></h2>
                <span class="close-button assign-all-tasks-close-button">&times;</span>
            </div>
            <div class="modal-body">
                <h3>Unassigned Tasks:</h3>
                <ul id="unassignedTaskList" class="unassigned-task-list">
                    <!-- Tasks will be loaded here via JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Assign Unassigned Task Modal (New - for assigning a task to a selected member) -->
    <div id="assignUnassignedTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Unassigned Task</h2>
                <span class="close-button assign-unassigned-task-close-button">&times;</span>
            </div>
            <div class="modal-body">
                <h3>Select a Task and Assign a Member:</h3>
                <ul id="unassignedTasksForAssignmentList" class="unassigned-task-list">
                    <!-- Unassigned tasks with member dropdowns will be loaded here via JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    <!-- Assign Specific Task Modal (New - for assigning a specific task to a member) -->
    <div id="assignSpecificTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Task: <span id="assignSpecificTaskTitle"></span></h2>
                <span class="close-button assign-specific-task-close-button">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="assignSpecificTaskMemberSelect">Assign to Member:</label>
                    <select id="assignSpecificTaskMemberSelect" class="select-owner">
                        <!-- Members will be loaded here via JavaScript -->
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" onclick="confirmAssignSpecificTask()">Assign Task</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Task Modal -->
    <div id="newTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <span class="close-button new-task-close-button">&times;</span>
            </div>
            <form id="newTaskForm">
                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
                <div class="form-group">
                    <label for="taskTitle">Task Title</label>
                    <input type="text" id="taskTitle" name="title" required>
                </div>
                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="description" rows="5"></textarea>
                </div>
                <div class="form-group">
                    <label for="taskPriority">Priority</label>
                    <select id="taskPriority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="taskStartDate">Start Date</label>
                    <input type="datetime-local" id="taskStartDate" name="start_date">
                </div>
                <div class="form-group">
                    <label for="taskEndDate">Due Date</label>
                    <input type="datetime-local" id="taskEndDate" name="end_date">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Member Tasks Info Modal -->
    <div id="memberTasksInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Tasks for <span id="memberTasksInfoName"></span></h2>
                <span class="close-button member-tasks-info-close-button">&times;</span>
            </div>
            <div class="modal-body">
                <ul id="memberTasksList" class="unassigned-task-list">
                    <!-- Member's tasks will be loaded here via JavaScript -->
                </ul>
            </div>
        </div>
    </div>

    </main>
    <script>
        // Pass project members data to JavaScript
        const projectMembers = <?php echo json_encode($project_members); ?>;

        // Assign Specific Task Modal functionality
        const assignSpecificTaskModal = document.getElementById('assignSpecificTaskModal');
        const assignSpecificTaskCloseButtons = document.querySelectorAll('.assign-specific-task-close-button');

        assignSpecificTaskCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                assignSpecificTaskModal.classList.remove('show');
                assignSpecificTaskModal.style.display = 'none';
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target == assignSpecificTaskModal) {
                assignSpecificTaskModal.classList.remove('show');
                assignSpecificTaskModal.style.display = 'none';
            }
        });

        // Member Tasks Info Modal functionality
        const memberTasksInfoModal = document.getElementById('memberTasksInfoModal');
        const memberTasksInfoCloseButtons = document.querySelectorAll('.member-tasks-info-close-button');

        memberTasksInfoCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                memberTasksInfoModal.classList.remove('show');
                memberTasksInfoModal.style.display = 'none';
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target == memberTasksInfoModal) {
                memberTasksInfoModal.classList.remove('show');
                memberTasksInfoModal.style.display = 'none';
            }
        });
    </script>
    <script src="../scripts/script.js?v=10"></script>
</body>
</html>