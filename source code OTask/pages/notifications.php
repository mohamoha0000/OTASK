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

    // Handle marking notification as read
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["mark_read"])) {
        $notificationId = $_POST["notification_id"] ?? null;
        if ($notificationId) {
            $notificationManager->markNotificationAsRead($notificationId, $user_id);
            header("Location: notifications.php");
            exit();
        }
    }

    // Handle deleting notification
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_notification"])) {
        $notificationId = $_POST["notification_id"] ?? null;
        if ($notificationId) {
            $notificationManager->deleteNotification($notificationId, $user_id);
            header("Location: notifications.php");
            exit();
        }
    }

    // Pagination variables
    $notifications_per_page = 5; // Number of notifications to display per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $notifications_per_page;

    // Filtering variables
    $type_filter = $_GET['type'] ?? '';
    $status_filter = $_GET['status'] ?? ''; // 'read' or 'unread'
    $days_filter = $_GET['days'] ?? '';

    // Fetch filtered notifications
    $all_notifications = $notificationManager->getNotificationsForUser(
        $user_id,
        $type_filter,
        $status_filter,
        $days_filter,
        $notifications_per_page,
        $offset
    );
    $total_notifications_count = $notificationManager->getNotificationCountForUserFiltered(
        $user_id,
        $type_filter,
        $status_filter,
        $days_filter
    );
    $total_pages = ceil($total_notifications_count / $notifications_per_page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="../style/dashboard.css?v=5">
    <style>
        .notifications-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
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
            appearance: none;
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

        .notifications-count {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }

        .notification-list {
            display: grid;
            gap: 15px;
        }

        .notification-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item.read {
            background-color: #f0f0f0; /* Lighter background for read notifications */
            border-left-color: #ccc;
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .notification-description {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }

        .notification-date {
            font-size: 12px;
            color: #888;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }

        .notification-actions button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .notification-actions button:hover {
            background-color: rgba(0,0,0,0.05);
        }

        .notification-actions svg {
            width: 20px;
            height: 20px;
            color: #666;
        }

        .notification-actions button.mark-read-btn svg {
            color: #2ecc71; /* Green for mark as read */
        }

        .notification-actions button.delete-btn svg {
            color: #e74c3c; /* Red for delete */
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
        <div class="notifications-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="color: #333; font-size: 28px;">Notifications</h1>
            </div>

            <div class="filter-options">
                <form action="notifications.php" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <select name="type">
                        <option value="">select type</option>
                        <option value="invite_to_project" <?= $type_filter == 'invite_to_project' ? 'selected' : '' ?>>Invite to Project</option>
                        <option value="task_update" <?= $type_filter == 'task_update' ? 'selected' : '' ?>>Task Update</option>
                        <option value="task_deadline" <?= $type_filter == 'task_deadline' ? 'selected' : '' ?>>Task Deadline</option>
                        <option value="admin_message" <?= $type_filter == 'admin_message' ? 'selected' : '' ?>>Admin Message</option>
                    </select>
                    <select name="status">
                        <option value="">select status</option>
                        <option value="unread" <?= $status_filter == 'unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= $status_filter == 'read' ? 'selected' : '' ?>>Read</option>
                    </select>
                    <select name="days">
                        <option value="">select days</option>
                        <option value="today" <?= $days_filter == 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="yesterday" <?= $days_filter == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                        <option value="last_7_days" <?= $days_filter == 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="last_30_days" <?= $days_filter == 'last_30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <button type="button" class="reset-btn" onclick="window.location.href='notifications.php'">Reset</button>
                </form>
            </div>

            <div class="notifications-count"><?= $total_notifications_count ?> Notifications</div>

            <div class="notification-list">
                <?php if (empty($all_notifications)): ?>
                    <p style="color: #666;">No notifications found matching your criteria.</p>
                <?php else: ?>
                    <?php foreach ($all_notifications as $notification): ?>
                    <div class="notification-item <?= $notification['is_read'] ? 'read' : '' ?>">
                        <div class="notification-content">
                            <div class="notification-title">
                                <?= htmlspecialchars($notification['title']) ?>
                            </div>
                            <div class="notification-description">
                                <?= nl2br(htmlspecialchars($notification['message'])) ?>
                            </div>
                            <div class="notification-date">
                                <?= date('M d, Y', strtotime($notification['created_at'])) ?>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                            <form action="notifications.php" method="POST" style="display:inline;">
                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                <button type="submit" name="mark_read" class="mark-read-btn" title="Mark as Read">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                                    </svg>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form action="notifications.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                <button type="submit" name="delete_notification" class="delete-btn" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="notifications.php?page=<?= $i ?>&type=<?= urlencode($type_filter) ?>&status=<?= urlencode($status_filter) ?>&days=<?= urlencode($days_filter) ?>"
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

    <script src="../scripts/script.js?v=7"></script>
</body>
</html>