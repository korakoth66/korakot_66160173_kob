<?php
require_once 'config.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $code = trim($_POST['code']);

    if ($code !== 'geography') {
        $message = "❌ รหัสยืนยันไม่ถูกต้อง";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM userss WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $message = "⚠️ ชื่อผู้ใช้นี้ถูกใช้แล้ว";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO userss (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $hashed]);
            $message = "✅ สมัครสมาชิกสำเร็จ! <a href='login.php'>กลับไปล็อกอิน</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครสมาชิก</title>
<style>
body {
  font-family:"Segoe UI",sans-serif;
  background:#f7c120ff;
  display:flex; justify-content:center; align-items:center;
  height:100vh;
  background: linear-gradient(135deg, #f8fbff 0%, #fcf6daff 100%);
}
.container {
  background:#fff; padding:30px; border-radius:12px;
  box-shadow:0 2px 10px rgba(0,0,0,0.1);
  width:320px; text-align:center;
}
input {
  width:90%; padding:10px; margin:8px 0;
  border:1px solid #ccc; border-radius:6px;
}
button {
  width:95%; padding:10px; margin-top:10px;
  border:none; border-radius:6px;
  background:#f7c120ff; color:#fff; cursor:pointer;
  transition: background 0.3s;
}
button:hover {background:#f7c120ff;}
.message {margin-top:10px; color:red;}
.back-btn {
  display: inline-block;
  margin-top: 15px;
  color: #f7c120ff;
  text-decoration: none;
}
.back-btn:hover {
  text-decoration: underline;
}
</style>
</head>
<body>
<div class="container">
  <h2>สร้างบัญชีใหม่</h2>
  <form method="POST">
      <input type="text" name="username" placeholder="ชื่อผู้ใช้" required><br>
      <input type="password" name="password" placeholder="รหัสผ่าน" required><br>
      <input type="text" name="code" placeholder="รหัสยืนยัน" required><br>
      <button type="submit">สมัครสมาชิก</button>
  </form>
  
  <a href="index.html" class="back-btn">
      <i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก
  </a>
  
  <div class="message"><?= $message ?></div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>