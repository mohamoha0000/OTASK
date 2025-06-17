<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Validator.php";
    require_once "../classes/Project.php";
    require_once "../classes/Notification.php";
    require_once "../classes/Task.php"; // Required for task count in projects

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
    $projectManager = new Project($pdo);
    $notificationManager = new Notification($pdo);
    $taskManager = new Task($pdo); // For getting task count per project

    // Handle logout
    if(isset($_GET["logout"])){
        $user->logout();
        header("Location: login.php");
        exit();
    }

    // Handle project creation
    if (isset($_POST['create_project'])) {
        $project_title = trim($_POST['project_title']);
        $project_description = trim($_POST['project_description']);

        if (empty($project_title)) {
            $error_message = "Project title cannot be empty.";
        } else {
            $new_project_id = $projectManager->createProject($project_title, $project_description, $user_id);
            if ($new_project_id) {
                // Optionally add the supervisor as a member automatically
                // $projectManager->addProjectMember($new_project_id, $user_id);
                header("Location: projects.php?success=Project created successfully!");
                exit();
            } else {
                $error_message = "Failed to create project.";
            }
        }
    }

    // Fetch basic user info
    $user_info = $user->get_info($user_id);
    $user_name = isset($user_info["name"]) ? $user_info["name"] : "User";

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

    // Pagination variables
    $projects_per_page = 6; // Number of projects to display per page

    // Filters
    $search_query = $_GET['search'] ?? '';
    $start_date_filter = $_GET['start_date'] ?? '';
    $end_date_filter = $_GET['end_date'] ?? '';

    // Projects Admin (Supervisor)
    $current_page_admin = isset($_GET['page_admin']) ? (int)$_GET['page_admin'] : 1;
    $offset_admin = ($current_page_admin - 1) * $projects_per_page;
    $admin_projects = $projectManager->getAdminProjectsForUser(
        $user_id,
        $search_query,
        $start_date_filter,
        $end_date_filter,
        $projects_per_page,
        $offset_admin
    );
    $total_admin_projects_count = $projectManager->getAdminProjectsCountFiltered(
        $user_id,
        $search_query,
        $start_date_filter,
        $end_date_filter
    );
    $total_pages_admin = ceil($total_admin_projects_count / $projects_per_page);

    // Projects Joined (Member)
    $current_page_joined = isset($_GET['page_joined']) ? (int)$_GET['page_joined'] : 1;
    $offset_joined = ($current_page_joined - 1) * $projects_per_page;
    $joined_projects = $projectManager->getJoinedProjectsForUser(
        $user_id,
        $search_query,
        $start_date_filter,
        $end_date_filter,
        $projects_per_page,
        $offset_joined
    );
    $total_joined_projects_count = $projectManager->getJoinedProjectsCountFiltered(
        $user_id,
        $search_query,
        $start_date_filter,
        $end_date_filter
    );
    $total_pages_joined = ceil($total_joined_projects_count / $projects_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects</title>
    <link rel="stylesheet" href="../style/dashboard.css?v=5">
    <style>
        /* Additional styles for projects.php specific elements */
        .my-projects-container {
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

        .filter-options input[type="date"] {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background-color: #fff;
            cursor: pointer;
            outline: none;
            background-image: url('../imgs/Calendar.png');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 24px auto;
            padding-right: 35px;
        }

        .filter-options input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            width: 35px;
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

        .projects-section {
            margin-top: 30px;
        }

        .projects-section-header {
            display: flex;
            align-items: baseline;
            margin-bottom: 20px;
        }

        .projects-section-header h2 {
            font-size: 22px;
            color: #333;
            margin-right: 10px;
        }

        .projects-count {
            font-size: 18px;
            font-weight: bold;
            color: #f39c12; /* Orange color from image */
        }

        .project-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .project-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid #667eea; /* Admin projects */
        }

        .project-item.joined {
            border-top: 4px solid #2ecc71; /* Joined projects */
        }

        .project-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        .project-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .project-description {
            font-size: 14px;
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #666;
        }

        .project-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .project-meta-item svg {
            width: 16px;
            height: 16px;
            color: #888;
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
        <div class="my-projects-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="color: #333; font-size: 28px;">My Projects</h1>
                <a id="newProjectBtn" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/>
                    </svg>
                    New Project
                </a>
            </div>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px;"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" style="color: green; margin-bottom: 15px;"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <div class="search-filter-section">
                <form action="projects.php" method="GET">
                    <div class="search-bar">
                        <input type="text" name="search" placeholder="Search projects..." value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="filter-options">
                        <label for="start_date">date between:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date_filter) ?>">
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date_filter) ?>">
                        <button type="button" class="reset-btn" onclick="window.location.href='projects.php'">Reset</button>
                    </div>
                </form>
            </div>

            <div class="projects-section">
                <div class="projects-section-header">
                    <h2>Projects admin</h2>
                    <span class="projects-count"><?= $total_admin_projects_count ?> Projects</span>
                </div>
                <div class="project-list">
                    <?php if (empty($admin_projects)): ?>
                        <p style="color: #666;">No projects found where you are an administrator.</p>
                    <?php else: ?>
                        <?php foreach ($admin_projects as $project):
                            $member_count = $projectManager->getProjectMemberCount($project['id']);
                            $task_count = $taskManager->getTaskCountForProject($project['id']);
                        ?>
                        <div class="project-item">
                            <div class="project-title"><?= htmlspecialchars($project['title']) ?></div>
                            <div class="project-description"><?= nl2br(htmlspecialchars($project['description'])) ?></div>
                            <div class="project-meta">
                                <div class="project-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-4 0c1.66 0 2.99-1.34 2.99-3S13.66 5 12 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-4 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm8 4H8c-2.76 0-5 2.24-5 5v2h18v-2c0-2.76-2.24-5-5-5z"/>
                                    </svg>
                                    <?= $member_count ?> members
                                </div>
                                <div class="project-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                    </svg>
                                    <?= $task_count ?> tasks
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages_admin; $i++): ?>
                        <a href="projects.php?page_admin=<?= $i ?>&search=<?= urlencode($search_query) ?>&start_date=<?= urlencode($start_date_filter) ?>&end_date=<?= urlencode($end_date_filter) ?>"
                           class="<?= $i == $current_page_admin ? 'current-page' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="projects-section">
                <div class="projects-section-header">
                    <h2>Projects joined</h2>
                    <span class="projects-count"><?= $total_joined_projects_count ?> Projects</span>
                </div>
                <div class="project-list">
                    <?php if (empty($joined_projects)): ?>
                        <p style="color: #666;">No projects found where you are a member.</p>
                    <?php else: ?>
                        <?php foreach ($joined_projects as $project):
                            $member_count = $projectManager->getProjectMemberCount($project['id']);
                            $task_count = $taskManager->getTaskCountForProject($project['id']);
                        ?>
                        <div class="project-item joined">
                            <div class="project-title"><?= htmlspecialchars($project['title']) ?></div>
                            <div class="project-description"><?= nl2br(htmlspecialchars($project['description'])) ?></div>
                            <div class="project-meta">
                                <div class="project-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-4 0c1.66 0 2.99-1.34 2.99-3S13.66 5 12 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-4 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm8 4H8c-2.76 0-5 2.24-5 5v2h18v-2c0-2.76-2.24-5-5-5z"/>
                                    </svg>
                                    <?= $member_count ?> members
                                </div>
                                <div class="project-meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                    </svg>
                                    <?= $task_count ?> tasks
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages_joined; $i++): ?>
                        <a href="projects.php?page_joined=<?= $i ?>&search=<?= urlencode($search_query) ?>&start_date=<?= urlencode($start_date_filter) ?>&end_date=<?= urlencode($end_date_filter) ?>"
                           class="<?= $i == $current_page_joined ? 'current-page' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- New Project Modal (Placeholder for now) -->
    <div id="newProjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Project</h2>
                <span class="close-button">&times;</span>
            </div>
            <form action="#" method="POST">
                <div class="form-group">
                    <label for="projectTitle">Project Title</label>
                    <input type="text" id="projectTitle" name="project_title" required>
                </div>
                <div class="form-group">
                    <label for="projectDescription">Description</label>
                    <textarea id="projectDescription" name="project_description" rows="4"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="create_project" class="btn btn-primary">Create Project</button>
                    <button type="button" class="btn btn-secondary close-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../scripts/script.js?v=6"></script>
    <script>
        // Modal functionality for New Project
        const newProjectBtn = document.getElementById('newProjectBtn');
        const newProjectModal = document.getElementById('newProjectModal');
        const closeButtons = newProjectModal.querySelectorAll('.close-button');

        newProjectBtn.addEventListener('click', () => {
            newProjectModal.classList.add('show');
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                newProjectModal.classList.remove('show');
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target == newProjectModal) {
                newProjectModal.classList.remove('show');
            }
        });
    </script>
</body>
</html>