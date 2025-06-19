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

    // Check if user is admin
    $user_role = $user->get_role($user_id);
    if ($user_role !== 'admin') {
        header("Location: dashboard.php"); // Redirect non-admins
        exit();
    }

    // Handle logout
    if(isset($_GET["logout"])){
        $user->logout();
        header("Location: login.php");
        exit();
    }

    // Fetch basic user info
    $user_info = $user->get_info($user_id);
    $user_name = isset($user_info["name"]) ? $user_info["name"] : "Admin";

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
    
    // Admin Dashboard Specific Data
    // These will need to be implemented in the respective classes or new methods
    $total_tasks_admin = $taskManager->getTotalTaskCount(); // Assuming this method exists or will be created
    $completed_tasks_admin = $taskManager->getCompletedTaskCount(); // Assuming this method exists or will be created
    $total_projects_admin = $projectManager->getTotalProjectCount(); // Assuming this method exists or will be created
    $overdue_tasks_admin = $taskManager->getOverdueTaskCount(); // Assuming this method exists or will be created
    $total_messages_admin = $notificationManager->getTotalNotificationCount(); // Assuming this method exists or will be created
    $completion_rate_admin = ($total_tasks_admin > 0) ? round(($completed_tasks_admin / $total_tasks_admin) * 100) : 0;

    // Handle search and pagination for users
    $users_per_page = 5; // Number of users per page
    $search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if (!empty($search_term)) {
        $total_users = $user->countSearchUsers($search_term);
        $total_pages = ceil($total_users / $users_per_page);
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $users_per_page;
        $paginated_users = $user->searchUsers($search_term, $users_per_page, $offset);
    } else {
        $all_users = $user->getAllUsers(); // Get all users to count total
        $total_users = count($all_users);
        $total_pages = ceil($total_users / $users_per_page);
        $current_page = max(1, min($current_page, $total_pages));
        $offset = ($current_page - 1) * $users_per_page;
        $paginated_users = array_slice($all_users, $offset, $users_per_page);
    }

    // 1. Get notification count using the Notification class
    $unread_notifications = $notificationManager->getUnreadCount($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../style/dashboard.css?v=8">
    <link rel="stylesheet" href="../style/admin_dashboard.css?v=8"> <!-- New CSS for admin dashboard -->
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
                <li><a href="projects.php">Projects</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="dashboardadmin.php" class="active">Admin Dashboard</a></li>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1 style="color: #fff; font-size: 28px;">Welcome back, <?= htmlspecialchars($user_name) ?>!</h1>
            <a id="sendNotificationBtn" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/>
                </svg>
                Send Notification
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-grid">
            <div class="stats-card">
                <div class="stats-number" style="color:#667EEA;"><?= $total_tasks_admin ?></div>
                <div class="stats-label">Total Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#2ECC71;"><?= $completed_tasks_admin ?></div>
                <div class="stats-label">Completed Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#F39C12;"><?= $total_projects_admin ?></div>
                <div class="stats-label">Projects</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#E74C3C;"><?= $overdue_tasks_admin ?></div>
                <div class="stats-label">Overdue Tasks</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#9B59B6;"><?= $total_messages_admin ?></div>
                <div class="stats-label">Total Messages</div>
            </div>
            <div class="stats-card">
                <div class="stats-number" style="color:#F39C12;"><?= $completion_rate_admin ?>%</div>
                <div class="stats-label">Completion Rate</div>
            </div>
        </div>

        <!-- Search Users and User List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Search users...</div>
                <div class="search-box">
                    <input type="text" id="userSearchInput" placeholder="Search users...">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                    </svg>
                </div>
            </div>
            <div class="user-list">
                <?php if (empty($paginated_users)): ?>
                    <p style="color: #666;">No users found.</p>
                <?php else: ?>
                    <?php foreach ($paginated_users as $user_item): ?>
                    <div class="user-item">
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($user_item['name'] ?? '') ?></div>
                            <div class="user-email"><?= htmlspecialchars($user_item['email'] ?? '') ?></div>
                            <div class="user-created">creat at: <?= date('M d, Y', strtotime($user_item['created_at'] ?? '')) ?></div>
                        </div>
                        <button type="button" class="edit-user-btn" data-user-id="<?= htmlspecialchars($user_item['id']) ?>" title="Edit User">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3 17.25V21h3.75l11-11.03-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- Pagination -->
            <div class="pagination" id="userPagination">
                <?php
                $pagination_base_url = '?';
                if (!empty($search_term)) {
                    $pagination_base_url .= 'search_term=' . urlencode($search_term) . '&';
                }
                ?>
                <?php if ($current_page > 1): ?>
                    <a href="<?= $pagination_base_url ?>page=<?= $current_page - 1 ?>" class="pagination-arrow">&larr;</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?= $pagination_base_url ?>page=<?= $i ?>" class="<?= ($i === $current_page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?= $pagination_base_url ?>page=<?= $current_page + 1 ?>" class="pagination-arrow">&rarr;</a>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer style="text-align:center; padding:20px 0; color:#fff;">
        &copy; <?= date('Y') ?> OTask. All rights reserved.
    </footer>

    <!-- Send Notification Modal (Placeholder) -->
    <div id="sendNotificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Send Notification</h2>
                <span class="close-button">&times;</span>
            </div>
            <form action="#" method="POST">
                <div class="form-group">
                    <label for="notificationRecipient">Recipient</label>
                    <select id="notificationRecipient" name="recipient_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($all_users as $user_option): ?>
                            <option value="<?= htmlspecialchars($user_option['id']) ?>"><?= htmlspecialchars($user_option['name']) ?> (<?= htmlspecialchars($user_option['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notificationMessage">Message</label>
                    <textarea id="notificationMessage" name="message" rows="4" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="send_notification" class="btn btn-primary">Send</button>
                    <button type="button" class="btn btn-secondary close-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User: <span id="editUserName"></span></h2>
                <span class="close-button">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Email:</strong> <span id="editUserEmail"></span></p>
                <p><strong>Created At:</strong> <span id="editUserCreatedAt"></span></p>
                <p><strong>Total Tasks:</strong> <span id="editUserTotalTasks"></span></p>
                <p><strong>Projects Joined:</strong> <span id="editUserProjectsJoined"></span></p>
                <p><strong>Projects Supervised:</strong> <span id="editUserProjectsSupervised"></span></p>
                <p><strong>Tasks Created:</strong> <span id="editUserTasksCreated"></span></p>
                <p><strong>Tasks Assigned:</strong> <span id="editUserTasksAssigned"></span></p>
                <input type="hidden" id="editUserId">
            </div>
            <div class="modal-footer">
                <button id="deleteUserBtn" class="btn btn-danger">Delete User</button>
                <button type="button" class="btn btn-secondary close-button">Close</button>
            </div>
        </div>
    </div>

    <script src="../scripts/script.js?v=13"></script>
    <script>
        // JavaScript for modal functionality (similar to dashboard.php)
        document.addEventListener('DOMContentLoaded', function() {
            const sendNotificationBtn = document.getElementById('sendNotificationBtn');
            const sendNotificationModal = document.getElementById('sendNotificationModal');
            const closeButtons = sendNotificationModal.querySelectorAll('.close-button');

            sendNotificationBtn.addEventListener('click', function() {
                sendNotificationModal.classList.add('show');
            });

            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    sendNotificationModal.classList.remove('show');
                });
            });

            window.addEventListener('click', function(event) {
                if (event.target == sendNotificationModal) {
                    sendNotificationModal.classList.remove('show');
                }
            });

            // Server-side user search
            const userSearchInput = document.getElementById('userSearchInput');
            const userListContainer = document.querySelector('.user-list');
            const userPaginationContainer = document.getElementById('userPagination');

            let searchTimeout;

            if (userSearchInput) {
                userSearchInput.addEventListener('keyup', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        fetchUsers(userSearchInput.value, 1); // Always go to page 1 on new search
                    }, 300); // Debounce search to prevent too many requests
                });
            }

            // Function to fetch users via AJAX
            async function fetchUsers(searchTerm, page) {
                try {
                    const url = `dashboardadmin.php?page=${page}&search_term=${encodeURIComponent(searchTerm)}`;
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
                        }
                    });
                    const html = await response.text();
                    console.log("Raw HTML response:", html); // Log the raw HTML response

                    // Create a temporary div to parse the HTML response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;

                    // Extract the updated user list and pagination
                    const newUserList = tempDiv.querySelector('.user-list').innerHTML;
                    const newPagination = tempDiv.querySelector('.pagination').innerHTML;

                    // Update the DOM
                    userListContainer.innerHTML = newUserList;
                    userPaginationContainer.innerHTML = newPagination;

                    // Re-attach event listeners for edit buttons on new user items
                    attachEditUserListeners();
                } catch (error) {
                    console.error('Error fetching users:', error);
                    alert('An error occurred while fetching users.');
                }
            }

            // Function to attach event listeners to edit user buttons
            function attachEditUserListeners() {
                const editUserBtns = document.querySelectorAll('.edit-user-btn');
                editUserBtns.forEach(button => {
                    button.removeEventListener('click', handleEditUserClick); // Remove existing to prevent duplicates
                    button.addEventListener('click', handleEditUserClick);
                });
            }

            // Handler for edit user button clicks
            function handleEditUserClick(event) {
                event.preventDefault();
                event.stopPropagation();
                const userId = this.dataset.userId;
                fetchUserDetails(userId);
                document.getElementById('editUserModal').classList.add('show');
            }

            // Function to attach event listeners to pagination links
            function attachPaginationListeners() {
                const paginationLinks = userPaginationContainer.querySelectorAll('a');
                paginationLinks.forEach(link => {
                    link.removeEventListener('click', handlePaginationClick); // Prevent duplicate listeners
                    link.addEventListener('click', handlePaginationClick);
                });
            }

            // Handler for pagination link clicks
            function handlePaginationClick(event) {
                event.preventDefault();
                const url = new URL(this.href);
                const page = url.searchParams.get('page');
                const searchTerm = url.searchParams.get('search_term') || '';
                fetchUsers(searchTerm, page);
            }

            // Initial attachment of listeners
            attachEditUserListeners();
            attachPaginationListeners(); // Attach pagination listeners on initial load

            // Edit User Modal functionality
            const editUserModal = document.getElementById('editUserModal');
            const editUserCloseButtons = editUserModal.querySelectorAll('.close-button');
            const editUserBtns = document.querySelectorAll('.edit-user-btn');
            const deleteUserBtn = document.getElementById('deleteUserBtn');

            editUserBtns.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation(); // Prevent event from bubbling up to window
                    const userId = this.dataset.userId;
                    fetchUserDetails(userId);
                    editUserModal.classList.add('show');
                });
            });

            editUserCloseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    editUserModal.classList.remove('show');
                });
            });

            window.addEventListener('click', function(event) {
                if (event.target == editUserModal) {
                    editUserModal.classList.remove('show');
                }
            });

            // Prevent clicks inside the edit user modal content from closing the modal
            const editUserModalContent = editUserModal.querySelector('.modal-content');
            if (editUserModalContent) {
                editUserModalContent.addEventListener('click', function(event) {
                    event.stopPropagation();
                });
            }

            async function fetchUserDetails(userId) {
                try {
                    const response = await fetch(`../api/get_user_details.php?user_id=${userId}`);
                    const data = await response.json();

                    if (data.success) {
                        document.getElementById('editUserName').textContent = data.user.name;
                        document.getElementById('editUserEmail').textContent = data.user.email;
                        document.getElementById('editUserCreatedAt').textContent = data.user.created_at;
                        document.getElementById('editUserTotalTasks').textContent = data.user.total_tasks;
                        document.getElementById('editUserProjectsJoined').textContent = data.user.projects_joined;
                        document.getElementById('editUserProjectsSupervised').textContent = data.user.projects_supervised;
                        document.getElementById('editUserTasksCreated').textContent = data.user.tasks_created;
                        document.getElementById('editUserTasksAssigned').textContent = data.user.tasks_assigned;
                        document.getElementById('editUserId').value = data.user.id;
                    } else {
                        alert('Error fetching user details: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while fetching user details.');
                }
            }

            if (deleteUserBtn) {
                deleteUserBtn.addEventListener('click', async function() {
                    const userIdToDelete = document.getElementById('editUserId').value;
                    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        try {
                            const formData = new FormData();
                            formData.append('user_id', userIdToDelete);

                            const response = await fetch('../api/delete_user.php', {
                                method: 'POST',
                                body: formData
                            });
                            const data = await response.json();

                            if (data.success) {
                                alert(data.message);
                                editUserModal.style.display = 'none';
                                // Reload the page or update the user list
                                location.reload();
                            } else {
                                alert('Error deleting user: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the user.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// If it's an AJAX request, only output the user list and pagination
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ob_clean(); // Clean any previous output
    ?>
    <div class="user-list">
        <?php if (empty($paginated_users)): ?>
            <p style="color: #666;">No users found.</p>
        <?php else: ?>
            <?php foreach ($paginated_users as $user_item): ?>
            <div class="user-item">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($user_item['name'] ?? '') ?></div>
                    <div class="user-email"><?= htmlspecialchars($user_item['email'] ?? '') ?></div>
                    <div class="user-created">creat at: <?= date('M d, Y', strtotime($user_item['created_at'] ?? '')) ?></div>
                </div>
                <button type="button" class="edit-user-btn" data-user-id="<?= htmlspecialchars($user_item['id']) ?>" title="Edit User">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 17.25V21h3.75l11-11.03-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="pagination" id="userPagination">
        <?php
        $pagination_base_url = '?';
        if (!empty($search_term)) {
            $pagination_base_url .= 'search_term=' . urlencode($search_term) . '&';
        }
        ?>
        <?php if ($current_page > 1): ?>
            <a href="<?= $pagination_base_url ?>page=<?= $current_page - 1 ?>" class="pagination-arrow">&larr;</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?= $pagination_base_url ?>page=<?= $i ?>" class="<?= ($i === $current_page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?>
            <a href="<?= $pagination_base_url ?>page=<?= $current_page + 1 ?>" class="pagination-arrow">&rarr;</a>
        <?php endif; ?>
    </div>
    <?php
    exit(); // Stop further processing for AJAX requests
}
?>