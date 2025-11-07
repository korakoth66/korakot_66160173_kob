<?php
session_start();
session_destroy();

echo "<script>
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('username');
    window.location.href = 'index.html';
</script>";
exit;
?>