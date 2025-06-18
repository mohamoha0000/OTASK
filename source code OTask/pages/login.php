<?php
    session_start();

    require_once "../classes/Database.php";
    require_once "../classes/User.php";
    require_once "../classes/Validator.php";
    require_once "../classes/Mailer.php";

    $db = new Database();
    $pdo = $db->getConnection();
    $user = new User($pdo);
    if($user->autoLogin()){
        header("Location:dashboard.php");
    }
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
        unset($_SESSION["tempemail"]);
    }

    if (isset($_POST['send'])) {
        if(!isset($_SESSION["tempemail"])){
            $email = $_POST['email'];
            if(Validator::isValidEmail($email)&&!$user->not_existe($email)){
                $_SESSION["tempemail"]["email"]=$email;
                $_SESSION["tempemail"]["code"]=(string)rand(100000,999999);
                $_SESSION["tempemail"]["time"]=time();

                try {
                    $mailer->send($_SESSION["tempemail"]["email"], "OTask code verification email", "code is :{$_SESSION["tempemail"]['code']}");
                    $errors["code"]="We have sent you the code via email";
                } catch (Exception $e) {
                    echo "error in send code verifiction email" . $e->getMessage();
                }

            }else{
                $errors["email"]="email not existe";
            }
        }else{
            $time=time()-$_SESSION["tempemail"]["time"];
            if($time>60){
                $_SESSION["tempemail"]["time"]=time();
                try {
                    $mailer->send($_SESSION["tempemail"]["email"], "OTask code verification email", "code is :{$_SESSION["tempemail"]['code']}");
                    $errors["code"]="We have sent you the code via email";
                } catch (Exception $e) {
                    echo "error in send code verifiction email" . $e->getMessage();
                }
            }else{
                $errors["code"]="wait ".(60-$time)." scond";
            }
        }
    }

    if(isset($_POST["login"])){
        if(isset($_SESSION["tempemail"])){
            if($_SESSION["tempemail"]["code"]==$_POST["code"]){
                $id=$user->getUserByEmail($_SESSION["tempemail"]["email"])["id"];
                $user->creat_cookie($id);
                $_SESSION['user_id'] = $id;
                unset($_SESSION["tempemail"]);
                if(isset($_SESSION["tempuser"])){unset($_SESSION["tempuser"]);}
                header("Location:profile.php");
            }else{
                $errors["code"]="wrong code verifiction";
            }
        }else{
            $email = $_POST['email'];
            $password = $_POST['password'];
            $errors=[];
            $valide=[
                'email' => Validator::isValidEmail($email),
                'password' => Validator::isValidPassword($password)
            ];
            if (!in_array(false, $valide, true)) {
                if($user->login($email, $password)){
                    if(isset($_SESSION["tempuser"])){unset($_SESSION["tempuser"]);}
                    header("Location:dashboard.php");
                }else{
                    $errors["password"]="password or email incorect";
                }
            }else{
                if($valide["password"]==false){
                    $errors["password"]="At least 6 characters";
                }
                if($valide["email"]==false){
                    $errors["email"]="pleze entre valide email";
                }
            }
        }
    }
    if(isset($_SESSION["tempemail"])){
        $_POST["email"]=$_SESSION["tempemail"]["email"];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/login.css?v=1">
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
                <label for="email">Email Address</label>
                <div class="input-email">
                    <img src="../imgs/email.png" alt="">
                    <input type="email" id="email" name="email" placeholder="entre email ..." value="<?php if(isset($_POST["email"])) echo $_POST["email"] ?>" <?php if(isset($_SESSION["tempemail"])) echo "disabled" ?>>
                    <span style="color:red"><?php if(isset($errors["email"])) echo $errors["email"] ?></span>
                </div>
                <?php if(isset($_SESSION["tempemail"])):?>
                    <label id="label_pss" for="code">code email</label>
                    <div class="input-password">
                        <img src="../imgs/Lock.png" alt="">
                        <input type="text"  id="code" name="code" placeholder="entre code ..." value="<?php if(isset($_POST["code"])) echo $_POST["code"] ?>">
                        <span style="color:red"><?php if(isset($errors["code"])) echo $errors["code"] ?></span>
                        <span></span>
                    </div>
                    <div style="display:flex;justify-content: space-between;"><button style="color:white;width:45%" id="send" name="send">Send code</button><button style="color:red;width:45%;" name="logout">logout</button></div>
                    <span style="color:#F97316;" name="time_scondes" id="time_scondes"><?php if(isset($_SESSION["tempemail"]["time"])&& (60-(time()-$_SESSION["tempemail"]["time"]))>0) echo 60-(time()-$_SESSION["tempemail"]["time"]) ?></span>
                <?php else:?>
                    <label id="label_pss" for="password">Password</label>
                    <div class="input-password">
                        <img src="../imgs/Lock.png" alt="">
                        <input type="password"  id="password" name="password" placeholder="entre password ..." value="<?php if(isset($_POST["password"])) echo $_POST["password"] ?>">
                        <span style="color:red"><?php if(isset($errors["password"])) echo $errors["password"] ?></span>
                        <img id="togglePassword" src="../imgs/Eye.png" alt="">
                    </div>
                    <h3 class="foget">Forgot Password?</h3>
                <?php endif;?>
                <button id="login" name="login">Log in</button>
                <button id="back" onclick="window.location.href='login.php'" style="display:none;">back</button>
                <h3 class="signup">Don't have an account? <span onclick="window.location.href='signup.php'">Sign Up</span></h3>
            </form>
        </div>
    </main>
    <script src="../scripts/script.js"></script>
    <script src="../scripts/login.js?v=1"></script>
    <script>
        if(<?php if(isset($_POST["send"])&& !isset($_SESSION["tempemail"])) echo "true"; else echo "false";?>){
            password.style.display="none";
            forget.style.display="none";
            label_pss.style.display="none";
            back.style.display="block";
            login.textContent="Send code";
            login.setAttribute("name","send");
        }
    </script>
</body>
</html>