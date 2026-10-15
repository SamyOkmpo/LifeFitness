<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        try {
            localStorage.removeItem('fl_auth_token');
            localStorage.removeItem('fl_auth_user');
        } catch(e) {}
        window.location.href = 'login.php';
    </script>
</head>
<body></body>
</html>
