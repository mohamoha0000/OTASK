<?php 
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Validator.php";
    require_once "../classes/Mailer.php";

    $db = new Database();
    $pdo = $db->getConnection();
    $user = new User($pdo);

    $encoded = "eHNtdHBzaWItNWExYzdjZjhkYWFhOTVlODgwOWM1ZTNiY2M2MmYyZTljZDU0MjQ0YTE0ZmJhYzEwZGU5YzEwY2Q1MTY2NzU4YS1QNll2VkFVbk5NYjRJa3BU";
    $smtp_password = base64_decode($encoded);
    $mailer = new Mailer(
        "smtp-relay.brevo.com",  
        587,                   
        "8f5763001@smtp-brevo.com",  
        $smtp_password,             
        "moham3iof@gmail.com"  
    );

    if(isset($_POST["logout"])){
        unset($_SESSION["tempuser"]);
    }

    if (isset($_POST['send'])) {
        $time=time()-$_SESSION["tempuser"]["time"];
        if($time>60){
            $_SESSION["tempuser"]["time"]=time();
            try {
                $mailer->send($_SESSION["tempuser"]["email"], "OTask code verification email", "code is :{$_SESSION["tempuser"]['code']}");
                $errors["code"]="We have sent you the code via email";
            } catch (Exception $e) {
                echo "error in send code verifiction email" . $e->getMessage();
            }
        }else{
            $errors["code"]="wait ".(60-$time)." scond";
        }
    }
    
    if (isset($_POST['singup'])) {
        if(isset($_SESSION["tempuser"])){
            if($_SESSION["tempuser"]["code"]==$_POST["code"]){
                if($user->create($_SESSION["tempuser"]["name"],$_SESSION["tempuser"]["email"],$_SESSION["tempuser"]["password"])){
                    unset($_SESSION["tempuser"]);
                    header("Location:../index.html");
                }
                else{
                    unset($_SESSION["tempuser"]);
                    echo "alert('somting wrong try later')";
                }
            }else{
                $errors["code"]="wrong code verifiction";
            }
        }else{
            $name = $_POST['name'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $confirm_pass = $_POST['confirm_pass'];
            $errors=[];
            $valide=[
                'name' => Validator::isValidUsername($name),
                'email' => Validator::isValidEmail($email),
                'password' => Validator::isValidPassword($password),
                "not_existe" => $user->not_existe($email),
                "confirm_pass"=> $confirm_pass==$password,
            ];
            if (!in_array(false, $valide, true)) {
                $_SESSION["tempuser"]=$_POST;
                $_SESSION["tempuser"]["code"]=(string)rand(100000,999999);
                $_SESSION["tempuser"]["time"]=time();
                try {
                    $mailer->send($email, "OTask code verification email", "code is :{$_SESSION["tempuser"]['code']}");
                } catch (Exception $e) {
                    echo "error in send code verifiction email" . $e->getMessage();
                }
            }else {
                    if($valide["name"]==false){
                        $errors["name"]="pleze entre valide name";
                    }
                    if($valide["email"]==false){
                        $errors["email"]="pleze entre valide email";
                    }
                    if($valide["not_existe"]==false){
                        $errors["email"]="email is a ready existe";
                    }
                    if($valide["password"]==false){
                        $errors["password"]="At least 6 characters";
                    }
                    if($valide["confirm_pass"]==false){
                        $errors["confirm_pass"]="wrong confirm password";
                    }
                }
            }
        }


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../style/style.css?v=1">
    <link rel="stylesheet" href="../style/login.css?v=2">
</head>
<body>
    <img class="bg" src="../imgs/loginbg.png" alt="">
    <header>
        <h1 onclick="window.location.href='../index.html'">OTask</h1>
    </header>
    <main>
        <div class="message">
            <h2>With </h2>
            <h2>OTask,</h2>
            <h2>organize</h2>
            <h2>your life</h2>
        </div>
        <div class="form">
            <h3 class="title">OTask login account</h3>
            <form method="post">
                <?php if(!isset($_SESSION["tempuser"])): ?>
                <label for="name">Full Name</label>
                <div class="input-name">
                    <img src="../imgs/Account.png" alt="">
                    <input type="text" name="name" id="name" placeholder="entre full name ..." value="<?php if(isset($_POST["name"])) echo $_POST["name"] ?>">
                    <span style="color:red"><?php if(isset($errors["name"])) echo $errors["name"] ?></span>
                </div>
                <label for="email">Email Address</label>
                <div class="input-email">
                    <img src="../imgs/email.png" alt="">
                    <input type="email" name="email" id="email" placeholder="entre email ..." value="<?php if(isset($_POST["email"])) echo $_POST["email"] ?>">
                    <span style="color:red"><?php if(isset($errors["email"])) echo $errors["email"] ?></span>
                </div>
                <label for="password">Password</label>
                <div class="input-password">
                    <img src="../imgs/Lock.png" alt="">
                    <input type="password" name="password" id="password" placeholder="entre password ..." value="<?php if(isset($_POST["password"])) echo $_POST["password"] ?>">
                    <span style="color:red"><?php if(isset($errors["password"])) echo $errors["password"] ?></span>
                    <img id="togglePassword" src="../imgs/Hide.png" alt="">
                </div>
                <label for="confirm_pass">Confirm Password</label>
                <div class="input-password">
                    <img src="../imgs/Lock.png" alt="">
                    <input type="password" name="confirm_pass" id="confirm_pass" placeholder="entre password ..." value="<?php if(isset($_POST["confirm_pass"])) echo $_POST["confirm_pass"] ?>">
                    <span style="color:red"><?php if(isset($errors["confirm_pass"])) echo $errors["confirm_pass"] ?></span>
                    <img id="togglePassword" src="../imgs/Hide.png" alt="">
                </div>
                <?php endif;?>
                <?php if(isset($_SESSION["tempuser"])):?>
                    <label for="confirm_pass">We have sent you the code via email</label>
                    <span><?php echo $_SESSION["tempuser"]["email"] ?></span>
                    <div class="input-password">
                        <img src="../imgs/Lock.png" alt="">
                        <input type="text" name="code" id="confirm_pass" placeholder="entre code verification ..." value="<?php if(isset($_POST["code"])) echo $_POST["code"] ?>">
                        <span style="color:red"><?php if(isset($errors["code"])) echo $errors["code"] ?></span>
                        <span></span>
                    </div>
                <div style="display:flex;justify-content: space-between;"><button style="color:white;width:45%" id="send" name="send">send code</button><button style="color:red;width:45%;" name="logout">logout</button></div>
                <span style="color:#F97316;" name="time_scondes" id="time_scondes"><?php if(isset($_SESSION["tempuser"]["time"])&& (60-(time()-$_SESSION["tempuser"]["time"]))>0) echo 60-(time()-$_SESSION["tempuser"]["time"]) ?></span>
                <?php endif; ?>
                <button name="singup">Sing Up</button>
                <h3 class="signup">Do you have an account? <span onclick="window.location.href='login.php'">Login</span></h3>
            </form>
        </div>
    </main>
    <script src="../scripts/script.js?v=1"></script>
    <script src="../scripts/signup.js?v=4"></script>
</body>
</html>