<?php
include 'includes/db.php';

// Add profile columns to users table
$columns = [
    'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL",
    'bio' => "ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL",
    'additional_details' => "ALTER TABLE users ADD COLUMN additional_details TEXT DEFAULT NULL"
];

foreach ($columns as $name => $sql) {
    $res = $conn->query("SHOW COLUMNS FROM users LIKE '$name'");
    if ($res->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "Column '$name' added successfully.<br>";
        } else {
            echo "Error adding column '$name': " . $conn->error . "<br>";
        }
    } else {
        echo "Column '$name' already exists.<br>";
    }
}

// Create tasks table for Gantt chart
$create_tasks_sql = "
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    progress INT DEFAULT 0,
    status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($create_tasks_sql)) {
    echo "Table 'tasks' created successfully or already exists.<br>";
} else {
    echo "Error creating table 'tasks': " . $conn->error . "<br>";
}
?>
