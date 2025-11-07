<?php
session_start();
$_SESSION['user'] = 'guest';
$_SESSION['is_logged_in'] = false;

echo "<script>
    localStorage.setItem('isLoggedIn', 'false');
    localStorage.setItem('username', 'ผู้เยี่ยมชม');
    window.location.href = 'index.html';
</script>";
exit;
?>