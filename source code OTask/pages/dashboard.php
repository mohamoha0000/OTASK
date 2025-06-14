<?php 
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Validator.php";
    require_once "../classes/Mailer.php";

    $db = new Database();
    $pdo = $db->getConnection();
    $user = new User($pdo);
    if(!isset($_SESSION["user_id"])){
        header("Location:login.php");
    }
    if(isset($_GET["logout"])){
        $user->logout();
        header("Location:login.php");
    }

    $user_info=$user->get_info($_SESSION["user_id"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>welcome <?=$user_info["name"];?></h1>
    <a href="dashboard.php?logout=1">logout</a>
</body>
</html>