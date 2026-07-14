<?php
$servername = "sql312.infinityfree.com";
$username = "if0_42404472";
$password = "ir9eEWKAUiU";
$database = "if0_42404472_sdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
