<?php
session_start();
require_once("connectDB.php"); // ใช้ PDO

// ตรวจสอบว่าปุ่มสมัครสมาชิกถูกกดหรือไม่
if (isset($_POST['sign_up'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $message = "All fields are required";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match";
    } else {
        // ตรวจสอบว่ามี username อยู่ในระบบหรือไม่
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $message = "Username already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'user')");
            $stmt->bindParam(":username", $username);
            $stmt->bindParam(":password", $hashed_password);

            if ($stmt->execute()) {
                $message = "Registration successful! Please login.";
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }
}

// ตรวจสอบว่าปุ่มเข้าสู่ระบบถูกกดหรือไม่
if (isset($_POST['sign_in'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "Username and password are required";
    } else {
        // ตรวจสอบผู้ดูแลระบบ
        if ($username === 'admin@jamoon' && $password === 'jamoon&@jamoon') {
            session_regenerate_id(true);
            $_SESSION['username'] = 'admin@jamoon';
            $_SESSION['role'] = 'admin';
            header("Location: dashboard.php");
            exit();
        }

        // ค้นหาผู้ใช้จากฐานข้อมูล
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel = "icon" href="logo.png">
    <title>Login - Jamoon Laundry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />
    <link rel="stylesheet" href="style.css">
</head>
<body>
 
    <header>
        <h2> Jamoon Laundry !</h2>
    </header>

    <?php if (isset($message)) { echo "<script>alert('$message');</script>"; } ?>
 
    <div class="container">
        <div class="form-container sign-up-container">
            <form method="POST">
                <h1>Create Account</h1>
                <span>or use your username for registration</span>
 
                <div class="infield">
                    <input type="text" placeholder="Username" name="username" required />
                </div>
 
                <div class="infield">
                    <input type="password" placeholder="Password" name="password" required />
                </div>
 
                <div class="infield">
                    <input type="password" placeholder="Confirm Password" name="confirm_password" required />
                </div>
 
                <button type="submit" name="sign_up">Sign Up</button>
            </form>
        </div>
 
        <div class="form-container sign-in-container">
            <form method="POST">
                <h1>Login here</h1>
                <span>or use your username</span>
                <div class="infield">
                    <input type="text" placeholder="Username" name="username" required />
                </div>
                <div class="infield">
                    <input type="password" placeholder="Password" name="password" required />
                </div>
                <button type="submit" name="sign_in">Sign In</button>
            </form>
        </div>
 
        <div class="overlay-container" id="overlayCon">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <img src="logo.png" alt="Jamoon Laundry Logo" class="logo">
                    <p>
                        Welcome back to Jamoon Laundry<br><br> <!-- เพิ่ม <br> อีก 1 ตัว -->
                        ซักสะอาด สะดวก รวดเร็ว 24 ชั่วโมง
                    </p>
                    <button type="button" class="ghost" id="signIn">Sign In</button>
                </div>
               
                <div class="overlay-panel overlay-right">
                    <img src="logo.png" alt="Jamoon Laundry Logo" class="logo">
                    <p>
                        Welcome back to Jamoon Laundry<br><br> <!-- เพิ่ม <br> อีก 1 ตัว -->
                        ซักสะอาด สะดวก รวดเร็ว 24 ชั่วโมง
                    </p>                
                        <button type="button" class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script>
            const signUpButton = document.getElementById('signUp');
            const signInButton = document.getElementById('signIn');
            const container = document.querySelector('.container'); // แก้จาก getElementById เป็น querySelector
 
                signUpButton.addEventListener('click', () => {
                    container.classList.add('right-panel-active');
                });
 
                signInButton.addEventListener('click', () => {
                    container.classList.remove('right-panel-active');
                });
           
            function showAlert(message) {
                document.getElementById("alertMessage").innerText = message;
                document.getElementById("customAlert").style.display = "block";
            }
 
            function closeAlert() {
                document.getElementById("customAlert").style.display = "none";
            }
           
            document.addEventListener("DOMContentLoaded", function() {
                const form = document.querySelector("form");
                const password = document.querySelector("input[name='password']");
                const confirmPassword = document.querySelector("input[name='confirm_password']");
 
                form.addEventListener("submit", function(event) {
                    if (password.value !== confirmPassword.value) {
                        event.preventDefault(); // ยกเลิกการส่งฟอร์ม
                        alert("Passwords do not match! Please try again.");
                    }
                });
            });
    </script>
</body>
</html>