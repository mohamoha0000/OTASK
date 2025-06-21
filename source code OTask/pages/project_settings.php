<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Settings</title>
    <link rel="stylesheet" href="../style/style.css?v=2">
    <link rel="stylesheet" href="../style/dashboard.css?v=2">
    <link rel="stylesheet" href="../style/project_settings.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        session_start();

        require_once "../classes/Database.php";
        require_once "../classes/User.php";
        require_once "../classes/Project.php";
        require_once "../classes/Notification.php";

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

        $user_info = $user->get_info($user_id);
        $user_name = isset($user_info["name"]) ? $user_info["name"] : "User";
        $unread_notifications = $notificationManager->getUnreadCount($user_id);

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

        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
        $project = null;
        $error_message = '';

        if ($project_id > 0) {
            $project = $projectManager->getProjectById($project_id);
            if (!$project) {
                $error_message = "Project not found.";
            } else {
                // Check if the user is a supervisor of this project
                $is_supervisor = $projectManager->isUserProjectSupervisor($project_id, $user_id);
                if (!$is_supervisor) {
                    $error_message = "You do not have permission to view these settings.";
                    $project = null; // Clear project data if no permission
                }
            }
        } else {
            $error_message = "No project ID provided.";
        }

        // Handle form submission for updating project settings
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_project_settings"])) {
            $project_id_to_update = $_POST["project_id"] ?? 0;
            $new_title = $_POST["project_title"] ?? '';
            $new_description = $_POST["project_description"] ?? '';
            $new_visibility = isset($_POST["project_visibility"]) ? (int)$_POST["project_visibility"] : 0; // Cast to int

            if ($project_id_to_update > 0 && $projectManager->isUserProjectSupervisor($project_id_to_update, $user_id)) {
                if ($projectManager->updateProjectDetails($project_id_to_update, $new_title, $new_description, $new_visibility)) {
                    $_SESSION['success_message'] = "Project settings updated successfully!";
                    header("Location: project_settings.php?project_id=" . htmlspecialchars($project_id_to_update));
                    exit();
                } else {
                    $error_message = "Failed to update project settings.";
                }
            } else {
                $error_message = "You do not have permission to update this project.";
            }
        }

        // Handle project deletion
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_project"])) {
            $project_id_to_delete = $_POST["project_id"] ?? 0;
            if ($project_id_to_delete > 0 && $projectManager->isUserProjectSupervisor($project_id_to_delete, $user_id)) {
                if ($projectManager->deleteProject($project_id_to_delete)) {
                    $_SESSION['success_message'] = "Project deleted successfully!";
                    header("Location: projects.php"); // Redirect to projects list after deletion
                    exit();
                } else {
                    $error_message = "Failed to delete project.";
                }
            } else {
                $error_message = "You do not have permission to delete this project.";
            }
        }
    ?>
    <header class="header fade-in">
        <div class="nav container">
            <a href="dashboard.php" class="logo">OTask</a>
            <div class="menu-toggle" id="mobile-menu">
                <img src="../imgs/Menu.png" alt="Menu" class="hamburger-icon">
            </div>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="mytask.php">My Tasks</a></li>
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
        <div class="settings-card">
            <?php if ($project): ?>
                <h2 class="settings-page-title">Settings</h2>
                <a href="view_project.php?project_id=<?php echo htmlspecialchars($project_id); ?>" class="btn btn-info" style="margin-bottom: 20px;">Back to Project Overview</a>
                <form action="project_settings.php?project_id=<?= htmlspecialchars($project_id) ?>" method="POST">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
                    <div class="settings-info-item">
                        <label for="projectTitle">Project Title</label>
                        <input type="text" id="projectTitle" name="project_title" value="<?= htmlspecialchars($project['title']) ?>" required>
                    </div>
                    <div class="settings-info-item">
                        <label for="projectDescription">Description</label>
                        <textarea id="projectDescription" name="project_description" rows="5"><?= htmlspecialchars($project['description']) ?></textarea>
                    </div>
                    <div class="settings-info-item visibility-section">
                        <span class="visibility-label">VISIBILITY</span>
                        <span class="visibility-status"><?= $project['visibility'] == 1 ? 'PUBLIC' : 'PRIVATE' ?></span>
                        <input type="hidden" id="projectVisibility" name="project_visibility" value="<?= htmlspecialchars($project['visibility']) ?>">
                    </div>
                    <button type="button" class="change-visibility-button" id="changeVisibilityBtn">CHANGE VISIBILITY</button>
                    <button type="submit" name="update_project_settings" class="save-settings-button">Save Changes</button>
                </form>
                <form action="project_settings.php?project_id=<?= htmlspecialchars($project_id) ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.');">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id) ?>">
                    <button type="submit" name="delete_project" class="delete-button">DELETE</button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger" style="color: red; margin-bottom: 15px; text-align: center;"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
        </div>
    </main>

    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- Modals -->
    <div id="visibilityModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Change Project Visibility</h3>
            <p>Current visibility: <span id="currentVisibilityDisplay"></span></p>
            <div class="radio-group">
                <input type="radio" id="visibilityPublic" name="new_visibility" value="1">
                <label for="visibilityPublic">Public</label><br>
                <input type="radio" id="visibilityPrivate" name="new_visibility" value="0">
                <label for="visibilityPrivate">Private</label>
            </div>
            <button id="confirmVisibilityChange">Confirm</button>
        </div>
    </div>

    <script src="../scripts/script.js?v=2"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const changeVisibilityBtn = document.getElementById('changeVisibilityBtn');
            const visibilityModal = document.getElementById('visibilityModal');
            const currentVisibilityDisplay = document.getElementById('currentVisibilityDisplay');
            const projectVisibilityInput = document.getElementById('projectVisibility');
            const confirmVisibilityChangeBtn = document.getElementById('confirmVisibilityChange');
            const visibilityRadios = document.querySelectorAll('input[name="new_visibility"]');
            const visibilityStatusSpan = document.querySelector('.visibility-status');

            // Only proceed if the modal and button exist
            if (changeVisibilityBtn && visibilityModal) {
                const closeButtons = visibilityModal.querySelectorAll('.close-button'); // Now safe to call

                changeVisibilityBtn.addEventListener('click', function() {
                    currentVisibilityDisplay.textContent = (projectVisibilityInput.value == '1' ? 'PUBLIC' : 'PRIVATE');
                    // Set the radio button based on current visibility
                    if (projectVisibilityInput.value == '1') {
                        document.getElementById('visibilityPublic').checked = true;
                    } else {
                        document.getElementById('visibilityPrivate').checked = true;
                    }
                    visibilityModal.classList.add('show'); // Show the modal
                });

                closeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        visibilityModal.classList.remove('show'); // Hide the modal
                    });
                });

                window.addEventListener('click', function(event) {
                    if (event.target == visibilityModal) {
                        visibilityModal.classList.remove('show'); // Hide the modal if clicked outside
                    }
                });

                if (confirmVisibilityChangeBtn) {
                    confirmVisibilityChangeBtn.addEventListener('click', function() {
                        let selectedVisibility = '';
                        for (const radio of visibilityRadios) {
                            if (radio.checked) {
                                selectedVisibility = radio.value;
                                break;
                            }
                        }
                        if (selectedVisibility !== '') {
                            projectVisibilityInput.value = selectedVisibility;
                            visibilityStatusSpan.textContent = (selectedVisibility == '1' ? 'PUBLIC' : 'PRIVATE');
                            visibilityModal.classList.remove('show'); // Hide the modal after confirmation
                        } else {
                            alert('Please select a visibility option.');
                        }
                    });
                }
            }

            <?php if (isset($_SESSION['success_message'])): ?>
                alert("<?= $_SESSION['success_message'] ?>");
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>