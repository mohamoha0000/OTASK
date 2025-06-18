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

// Fetch tasks for the project
$project_tasks = $task->getTasksByProjectIdFiltered($project_id); // Using the filtered method for now, can add filters later

// Prepare data for members tab
$members_data = [];
foreach ($project_members as $member) {
    $member_task_count = $task->getTaskCountForUser($member['id'], 'active'); // Get active tasks for each member
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
    <link rel="stylesheet" href="../style/style.css"> <!-- Assuming a common style.css -->
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
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
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
            overflow: hidden;
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Project Tasks Supervision</h1>

        <div class="tabs">
            <button class="tab-button active" onclick="openTab(event, 'Members')">Members</button>
            <button class="tab-button" onclick="openTab(event, 'Tasks')">Tasks</button>
        </div>

        <div id="Members" class="tab-content active">
            <button class="new-button">+ New Members</button>
            <div class="search-bar">
                <input type="text" placeholder="Search members...">
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
                                        <button class="btn btn-delete" onclick="deleteEmployee('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>)">Delete</button>
                                        <button class="btn btn-info" onclick="showInfo('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>)">Information</button>
                                        <button class="btn btn-assign" onclick="assignTaskToMember('<?php echo htmlspecialchars($member['name']); ?>', <?php echo $member['id']; ?>)">Assign Task</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="Tasks" class="tab-content">
            <button class="new-button">+ New Task</button>
            <div class="search-bar">
                <input type="text" placeholder="Search tasks...">
                <span class="search-icon">🔍</span>
            </div>
            <select class="select-owner">
                <option value="">Select Owner</option>
                <?php foreach ($project_members as $member): ?>
                    <option value="<?php echo htmlspecialchars($member['id']); ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                <?php endforeach; ?>
                <option value="unassigned">Unassigned</option>
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
                                        <?php if ($proj_task['assigned_user_name'] === 'Unassigned'): ?>
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

            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
                row.style.animation = 'fadeInUp 0.6s ease forwards';
            });
        });

        function deleteEmployee(name, id) {
            if (confirm(`Are you sure you want to delete ${name}?`)) {
                alert(`${name} (ID: ${id}) has been deleted successfully!`);
                // In a real application, you would send an AJAX request to a PHP script to delete the employee
                // Example: fetch('api/delete_employee.php', { method: 'POST', body: JSON.stringify({ id: id }) })
                // .then(response => response.json())
                // .then(data => { if(data.success) { location.reload(); } });
            }
        }

        function showInfo(name, id) {
            alert(`Showing information for ${name} (ID: ${id})\n\nThis would typically open a detailed view or modal with employee information.`);
            // In a real application, you would fetch detailed info via AJAX
        }

        function assignTaskToMember(name, id) {
            const taskName = prompt(`Enter the task to assign to ${name}:`);
            if (taskName && taskName.trim()) {
                alert(`Task "${taskName}" has been assigned to ${name} (ID: ${id})!`);
                // In a real application, you would send an AJAX request to assign the task
            }
        }

        function deleteTask(taskTitle, id) {
            if (confirm(`Are you sure you want to delete the task "${taskTitle}"?`)) {
                alert(`Task "${taskTitle}" (ID: ${id}) has been deleted successfully!`);
                // In a real application, you would send an AJAX request to delete the task
            }
        }

        function showTaskInfo(taskTitle, id) {
            alert(`Showing detailed information for task: "${taskTitle}" (ID: ${id})\n\nThis would typically open a detailed view or modal with complete task information, timeline, attachments, and comments.`);
            // In a real application, you would fetch detailed task info via AJAX
        }

        function assignMemberToTask(taskTitle, taskId) {
            const memberId = prompt(`Enter the member ID to assign to "${taskTitle}":`); // Or use a dropdown/modal for selection
            if (memberId && memberId.trim()) {
                alert(`Task "${taskTitle}" (ID: ${taskId}) has been assigned to member ID: ${memberId}!`);
                // In a real application, you would send an AJAX request to assign the member to the task
            }
        }
    </script>
</body>
</html>