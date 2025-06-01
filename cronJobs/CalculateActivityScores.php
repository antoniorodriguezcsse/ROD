<?php

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

// Configuration
$maxRetries = 3;
$retryDelay = 5;
$scoreTimeFrame = 7; // Time frame in days to calculate the activity score
$activitiesRequiredForFullScore = 14; // Number of activities for 100% score
$decayFactor = pow(0.9, 7); // Adjusted decay factor for weekly application
$error_log_file = "player_activity_score_update_errors.txt";
$completed_tasks = [];
$total_processed = 0;
$batchSize = 100;
$testMode = true; // Set to true for testing, false for production

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

// Establishing database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logError("Connection failed: " . $conn->connect_error, $error_log_file);
    die("Connection failed: " . $conn->connect_error);
}
addCompletedTask("Successfully connected to the database.", $completed_tasks);

// Main logic for updating player scores
$offset = 0;
$total_players_to_update = 0;
do {
    $conn->begin_transaction();
    $retryCount = 0;
    $success = false;

    while (!$success && $retryCount < $maxRetries) {
        try {
            // SQL query to select players who haven't been updated in the last 7 days
            $dateCondition = $testMode ? "1" : "p.last_activity_score_update < CURDATE() - INTERVAL $scoreTimeFrame DAY OR p.last_activity_score_update IS NULL";
            $playerScoreSql = "
                SELECT 
                    p.player_id, 
                    p.activity_score AS previous_score,
                    (SELECT COUNT(*) 
                     FROM player_activity_logs pal 
                     WHERE pal.player_id = p.player_id AND pal.date >= CURDATE() - INTERVAL $scoreTimeFrame DAY) AS activity_count
                FROM 
                    players p
                WHERE 
                    $dateCondition
                LIMIT 
                    $batchSize 
                OFFSET 
                    $offset";
            $result = $conn->query($playerScoreSql);

            if ($result->num_rows == 0) {
                break;
            }

            while ($row = $result->fetch_assoc()) {
                $playerId = $row['player_id'];
                $activityCount = $row['activity_count'];

                // Calculate New Score based on Activities
                $activityScore = min(100, ($activityCount / $activitiesRequiredForFullScore) * 100);

                // Apply Decay to Previous Score
                $decayedScore = $row['previous_score'] * $decayFactor;

                // Final Score is the higher of Activity Score or Decayed Score
                $finalScore = max($activityScore, $decayedScore);

                // Update the player's score and last update timestamp
                $updateSql = "UPDATE players SET activity_score = ?, last_activity_score_update = NOW() WHERE player_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("di", $finalScore, $playerId);
                $updateStmt->execute();
                $total_processed++;
            }

            $conn->commit();
            $success = true;
            addCompletedTask("Activity scores updated for " . $result->num_rows . " players in this batch.", $completed_tasks);
            $total_players_to_update += $result->num_rows;
            $offset += $batchSize;
        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                sleep($retryDelay);
            } else {
                logError("Exception: " . $e->getMessage(), $error_log_file);
                break 2;
            }
        }
    }
} while ($success);

$conn->close();

// Generate and log report
$report = "----------------------\nREPORT\n----------------------\n";
$report .= "Total Players Processed for Score Update: $total_players_to_update\n";
foreach ($completed_tasks as $task) {
    $report .= $task . "\n";
}

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;

?>
