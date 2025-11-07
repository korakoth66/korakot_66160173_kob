<?php
session_start();
require_once 'config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM userss WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user['username'];
        $_SESSION['is_logged_in'] = true;
        
        echo "<script>
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('username', '" . $user['username'] . "');
            window.location.href = 'index.html';
        </script>";
        exit;
    } else {
        $message = "❌ ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบ | ระบบข้อมูลนิสิต</title>
<style>
body {
  font-family: "Kanit", sans-serif;
  background: linear-gradient(135deg, #FFF8F0 0%, #FFF5EE 100%);
  display:flex; justify-content:center; align-items:center;
  height:100vh;
}
.container {
  background:#fff; padding:40px;
  border-radius:16px; box-shadow:0 8px 30px rgba(0,0,0,0.1);
  width:380px; text-align:center;
}
input {
  width:90%; padding:15px; margin:12px 0;
  border:2px solid #FAEBD7; border-radius:10px;
  font-family: "Kanit", sans-serif;
  transition: all 0.3s;
}
input:focus {
  outline: none;
  border-color: #F4A460;
  box-shadow: 0 0 0 3px rgba(244, 164, 96, 0.2);
}
button {
  width:95%; padding:15px; margin-top:15px;
  border:none; border-radius:10px;
  background:#8B4513; color:#fff; cursor:pointer;
  font-family: "Kanit", sans-serif;
  font-weight: 500;
  transition: background 0.3s, transform 0.2s;
}
button:hover {
  background:#654321;
  transform: translateY(-2px);
}
.guest {background:#7f8c8d;}
.guest:hover {background:#5a6268;}
.message {color:#e74c3c; margin-top:15px; font-size:14px;}
.back-btn {
  display: inline-block;
  margin-top: 20px;
  color: #8B4513;
  text-decoration: none;
  font-weight: 500;
}
.back-btn:hover {
  text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
  <h2 style="color: #8B4513; margin-bottom: 30px;">เข้าสู่ระบบ</h2>
  <form method="POST">
      <input type="text" name="username" placeholder="ชื่อผู้ใช้" required><br>
      <input type="password" name="password" placeholder="รหัสผ่าน" required><br>
      <button type="submit" name="login">เข้าสู่ระบบ</button>
  </form>

  <form action="register.php" method="get">
      <button type="submit">สร้างบัญชีใหม่</button>
  </form>

  <form action="guest.php" method="post">
      <button class="guest" type="submit">เข้าสู่ระบบแบบผู้เยี่ยมชม</button>
  </form>

  <a href="index.html" class="back-btn">
      <i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก
  </a>

  <div class="message"><?= htmlspecialchars($message) ?></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>