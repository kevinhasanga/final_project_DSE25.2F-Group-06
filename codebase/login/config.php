<?php
// Database details for the local XAMPP server.
$host = "localhost";
$databaseUser = "root";
$databasePassword = "";
$databaseName = "ncc_database";

$connection = mysqli_connect(
    $host,
    $databaseUser,
    $databasePassword,
    $databaseName
);

if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}
