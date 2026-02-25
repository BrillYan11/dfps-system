<?php
// includes/db.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dfps";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");

// --- SELF-HEALING: Ensure new messaging columns exist ---
// Check for is_archived in conversation_participants
$res = $conn->query("SHOW COLUMNS FROM conversation_participants LIKE 'is_archived'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE conversation_participants ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
}

// Check for is_deleted in messages
$res = $conn->query("SHOW COLUMNS FROM messages LIKE 'is_deleted'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE messages ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
}

// Check for srp in produce
$res = $conn->query("SHOW COLUMNS FROM produce LIKE 'srp'");
if ($res->num_rows == 0) {
    $conn->query("ALTER TABLE produce ADD COLUMN srp DECIMAL(10,2) DEFAULT 0.00");
}
?>
