<?php

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

// Configuration
$maxRetries = 3;
$retryDelay = 5;
$decayFactor = 0.9; // Retains 90% of its value each day
$error_log_file = "player_score_decay_errors.txt";
$completed_tasks = [];
$total_processed = 0;
$batchSize = 100; // Batch size for processing

// Functions for logging errors and completed tasks
function logError($error_message, $error_log_file) {
    $current_date = date("Y-m-d H:i:s");
    $log_message = $current_date . ": " . $error_message . "\n";
    file_put_contents($error_log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

function addCompletedTask($message, &$completed_tasks) {
    $current_date = date("Y-m-d H:i:s");
    $completed_tasks[] = $current_date . ": " . $message;
}

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logError("Connection failed: " . $conn->connect_error, $error_log_file);
    die("Connection failed: " . $conn->connect_error);
}
addCompletedTask("Successfully connected to the database.", $completed_tasks);

// Applying decay to all player scores
$offset = 0;

do {
    $conn->begin_transaction();
    $retryCount = 0;
    $success = false;
    
    while (!$success && $retryCount < $maxRetries) {
        try {
            $fetchSql = "SELECT player_id, activity_score FROM players LIMIT $batchSize OFFSET $offset";
            $result = $conn->query($fetchSql);

            while ($row = $result->fetch_assoc()) {
                $decayedScore = $row['activity_score'] * $decayFactor;

                $updateSql = "UPDATE players SET activity_score = ? WHERE player_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("di", $decayedScore, $row['player_id']);
                $updateStmt->execute();
                $total_processed++;
            }

            $conn->commit();
            $success = true;
            addCompletedTask("Decay applied to $total_processed players in this batch.", $completed_tasks);
            $offset += $batchSize;
        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                sleep($retryDelay);
            } else {
                logError("Exception: " . $e->getMessage(), $error_log_file);
                break 2; // Exit the outer loop
            }
        }
    }
} while ($result->num_rows === $batchSize); // Continue if the batch was full

$conn->close();

// Generate and log report
$report = "----------------------\nREPORT\n----------------------\n";
$report .= "Total Players Processed for Decay: $total_processed\n";
foreach ($completed_tasks as $task) {
    $report .= $task . "\n";
}

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;

?>
