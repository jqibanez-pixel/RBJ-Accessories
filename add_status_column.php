<?php
// Script to add the missing 'status' column to the feedback table
include 'config.php';

$sql = "ALTER TABLE feedback ADD COLUMN status ENUM('submitted', 'approved', 'rejected') DEFAULT 'submitted'";

if ($conn->query($sql) === TRUE) {
    echo "Status column added successfully to feedback table.";
} else {
    echo "Error adding status column: " . $conn->error;
}

$conn->close();
?>
