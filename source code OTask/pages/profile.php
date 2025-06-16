<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link rel="stylesheet" href="../style/style.css?v=1">
    <link rel="stylesheet" href="../style/dashboard.css?v=1">
    <link rel="stylesheet" href="../style/profile.css?v=1">
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
        require_once "../classes/Notification.php";

        $db = new Database();
        $pdo = $db->getConnection();

        if(!isset($_SESSION["user_id"])){
            header("Location: login.php");
            exit();
        }
        $user_id = $_SESSION["user_id"];

        $user = new User($pdo);
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
    ?>
    <header class="header fade-in">
        <div class="nav container">
            <a href="dashboard.php" class="logo">OTask</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#">My Tasks</a></li>
                <li><a href="#">Projects</a></li>
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
        <div class="profile-card">
                <h2 class="profile-page-title">profile page</h2>
                <div class="profile-picture-section">
                    <div class="profile-picture-large">
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
                    <i class="fas fa-pencil-alt edit-icon" id="edit-profile-pic"></i>
                </div>
                <div class="profile-info-item profile-name-container">
                    <span class="profile-name"><?= htmlspecialchars($user_name) ?></span>
                    <i class="fas fa-pencil-alt edit-icon" id="edit-name"></i>
                </div>
                <div class="profile-info-item">
                    <label for="email">email</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user_info["email"]) ?>" readonly>
                </div>
                <div class="profile-info-item">
                    <label for="password">Password</label>
                    <input type="password" id="password" value="************" readonly>
                    <i class="fas fa-pencil-alt edit-icon" id="edit-password"></i>
                </div>
                <button class="logout-button" id="logout-button">LOGOUT</button>
            </div>
    </main>

    </main>

    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- Modals will be added here later -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3 id="modal-title">Edit Field</h3>
            <div class="modal-input-group" id="modal-input-group">
                <input type="text" id="modal-input" placeholder="Enter new value">
                <span class="password-toggle" id="modal-input-password-toggle" style="display: none;">
                    <img src="../imgs/Eye.png" alt="Show/Hide Password">
                </span>
            </div>
            <div class="modal-input-group" id="modal-confirm-password-group" style="display: none;">
                <input type="password" id="modal-confirm-input" placeholder="Confirm new password">
                <span class="password-toggle" id="modal-confirm-password-toggle">
                    <img src="../imgs/Eye.png" alt="Show/Hide Password">
                </span>
            </div>
            <input type="file" id="modal-file-input" accept="image/*" style="display: none;">
            <button id="modal-update-button">Update</button>
        </div>
    </div>

    <div id="logout-confirm-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Are you sure you want to logout?</h3>
            <button id="confirm-logout-yes">Yes</button>
            <button id="confirm-logout-no">No</button>
        </div>
    </div>

    <script src="../scripts/script.js?v=1"></script>
    <script src="../scripts/profile.js?v=1"></script>
</body>
</html>