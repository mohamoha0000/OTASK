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
        exit(); // It's good practice to exit after a header redirect
    }
    $user_id = $_SESSION["user_id"]; // Get user_id from session

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
                header("Location: dashboard.php?task_created=success");
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
                    header("Location: dashboard.php?task_updated=success");
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
    
    // 1. Get notification count using the Notification class
    $unread_notifications = $notificationManager->getUnreadCount($user_id);

    // 2. Get task stats using the Task class
    $active_tasks = $taskManager->getTaskCountForUser($user_id, 'active');
    $completed_tasks = $taskManager->getTaskCountForUser($user_id, 'completed');
    $overdue_tasks = $taskManager->getTaskCountForUser($user_id, 'overdue');

    // Calculate completion rate
    $total_tasks = $active_tasks + $completed_tasks;
    $completion_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;

    // 3. Get project stats using the Project class
    $projects_joined = $projectManager->getProjectsJoinedCount($user_id);
    $active_projects = $projectManager->getActiveProjectsCount($user_id);

    // 4. Get recent tasks using the Task class
    $recent_tasks = $taskManager->getRecentTasksForUser($user_id, 5);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>dashboard</title>
    <link rel="stylesheet" href="../style/dashboard.css?v=5">
</head>
<body>
    <header class="header fade-in">
        <div class="nav container">
            <a href="dashboard.php" class="logo">OTask</a>
            <div class="menu-toggle" id="mobile-menu">
                <img src="../imgs/Menu.png" alt="Menu" class="hamburger-icon">
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="mytask.php">My Tasks</a></li>
                <li><a href="projects.php">Projects</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="dashboardadmin.php">Admin Dashboard</a></li>
                <?php endif; ?>
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
                            // Check if the profile picture is a base64 string or a URL
                            if (strpos($user_info["profile_picture"], 'data:image') === 0) {
                                $image_src = $user_info["profile_picture"];
                            } else {
                                // Assuming it's a URL if not base64
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="color: #fff; font-size: 28px;">Welcome back, <?= htmlspecialchars($user_name) ?>!</h1>
            <a id="newTaskBtn" class="btn btn-primary">
                <!-- plus icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/>
                </svg>
                New Task
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-grid">
            <div class="stats-card">
                <div class="stats-number" style="color:#667EEA;"><?= $active_tasks ?></div>
                <div class="stats-label">Active Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#2ECC71;"><?= $completed_tasks ?></div>
                <div class="stats-label">Completed Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#F39C12;"><?= $active_projects ?></div>
                <div class="stats-label">Active Projects</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#E74C3C;"><?= $overdue_tasks ?></div>
                <div class="stats-label">Overdue Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#9B59B6;"><?= $projects_joined ?></div>
                <div class="stats-label">Projects Joined</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#F39C12;"><?= $completion_rate ?>%</div>
                <div class="stats-label">Completion Rate</div>
            </div>
        </div>

        <!-- Recent Tasks -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Recent Tasks</div>
            </div>
            <div class="task-list">
                <?php if (empty($recent_tasks)): ?>
                    <p style="color: #666;">No recent tasks found.</p>
                <?php else: ?>
                    <?php foreach ($recent_tasks as $task):
                        $due = $task['end_date'];
                        $due_str = $due ? date('M d, Y H:i', strtotime($due)) : 'No due date';
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
                                <!-- pencil/edit icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 17.25V21h3.75l11-11.03-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- New Task Modal -->
    <div id="newTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Task</h2>
                <span class="close-button">&times;</span>
            </div>
            <form action="dashboard.php" method="POST">
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
    <script src="../scripts/script.js?v=7"></script>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Task</h2>
                <span class="close-button edit-close-button">&times;</span>
            </div>
            <form action="dashboard.php" method="POST">
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
                        <?php foreach ($all_users as $user_option): ?>
                            <option value="<?= htmlspecialchars($user_option['id']) ?>"><?= htmlspecialchars($user_option['name']) ?></option>
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
</body>
</html>