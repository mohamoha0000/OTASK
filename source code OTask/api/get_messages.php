<?php 
    session_start();

    require_once '../classes/Database.php';
    require_once '../classes/Project.php';
    require_once '../classes/User.php';

    $database = new Database();
    $pdo = $database->getConnection();
    $project = new Project($pdo);
    $user = new User($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id_project = $_GET["project_id"];

        if ($project->isUserProjectMember($id_project, $_SESSION["user_id"])) {
            $stmt = $pdo->query("SELECT sender_id, message, sent_at FROM chat_messages WHERE project_id = $id_project ORDER BY id DESC LIMIT 20");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach (array_reverse($messages) as $msg) {
                $senderName = htmlspecialchars($user->get_info($msg['sender_id'])["name"]);
                $message = htmlspecialchars($msg['message']);

                $style = ($_SESSION['user_id'] == $msg['sender_id']) ? ' style="color:green;"' : '';
                echo "<p$style><strong>$senderName:</strong> $message</p>";
            }
        } else {
            echo "<p style=\"color:red;\">You don't have permission</p>";
        }
    }
?>
