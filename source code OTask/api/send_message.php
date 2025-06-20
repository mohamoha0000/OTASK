<?php
    session_start();

    require_once '../classes/Database.php';
    require_once '../classes/Project.php';

    $database = new Database();
    $pdo = $database->getConnection();
    $project = new Project($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_project=$_POST["project_id"];
        if($project->isUserProjectMember($id_project,$_SESSION["user_id"])){
            $message = $_POST['message'];
            $stmt = $pdo->prepare("INSERT INTO chat_messages (project_id, sender_id,message) VALUES (?, ?,?)");
            $stmt->execute([$id_project,$_SESSION["user_id"],$message]);
        }
    }
?>
