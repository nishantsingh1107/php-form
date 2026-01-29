<?php
    function require_login(): void {
        if (!isset($_SESSION['user_id'])) {
            api_error("Unauthorized", 401);
        }
    }
?>