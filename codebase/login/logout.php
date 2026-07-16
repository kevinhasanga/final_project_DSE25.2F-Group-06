<?php
session_start();
require_once "config.php";

if (isset($_SESSION["login_id"])) {
    $loginId = (int) $_SESSION["login_id"];
    $statement = mysqli_prepare($connection, "UPDATE login_history SET logout_time = NOW() WHERE login_id = ?");
    mysqli_stmt_bind_param($statement, "i", $loginId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

session_destroy();

header("Location: login.php");
exit();
