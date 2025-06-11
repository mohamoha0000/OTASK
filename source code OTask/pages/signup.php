<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/login.css">
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
            <form action="">
                <label for="">Full Name</label>
                <div class="input-email">
                    <img src="../imgs/Account.png" alt="">
                    <input type="email" placeholder="entre full name ...">
                </div>
                <label for="">Email Address</label>
                <div class="input-email">
                    <img src="../imgs/email.png" alt="">
                    <input type="email" placeholder="entre email ...">
                </div>
                <label for="">Password</label>
                <div class="input-password">
                    <img src="../imgs/Lock.png" alt="">
                    <input type="password" placeholder="entre password ...">
                    <img src="../imgs/Eye.png" alt="">
                </div>
                <label for="">Confirm Password</label>
                <div class="input-password">
                    <img src="../imgs/Lock.png" alt="">
                    <input type="password" placeholder="entre password ...">
                    <img src="../imgs/Eye.png" alt="">
                </div>
                <button>Sing Up</button>
                <h3 class="signup">Do you have an account? <span onclick="window.location.href='login.php'">Login</span></h3>
            </form>
        </div>
    </main>
    <script src="../scripts/script.js"></script>
    <script src="../scripts/signup.js"></script>
</body>
</html>