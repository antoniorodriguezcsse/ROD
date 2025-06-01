<?php

// Enable error reporting for better debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$error_log_file = "essence_update_errors.txt";
$completed_tasks = []; // To keep a record of the completed tasks
$test_mode = false;

// Function to log errors to a file and echo them for debugging purposes
function logError($error_message, $sql_error = '') {
    global $error_log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_entry = $current_date . ": " . $error_message;
    if ($sql_error) {
        $log_entry .= " - SQL Error: " . $sql_error;
    }
    $log_entry .= "\n";

    file_put_contents($error_log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

// Function to add a completed task with timestamp
function addCompletedTask($message) {
    global $completed_tasks;
    $current_date = date("Y-m-d H:i:s");
    $completed_tasks[] = $current_date . ": " . $message;
}

// Establishing the database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    logError("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
} else {
    addCompletedTask("Successfully connected to the database.");
}

$conn->autocommit(FALSE);

$maxRetries = 3;
$retryDelay = 5;

$alive_condition = "ps.player_status_keyword = 'alive'";
$date_check = $test_mode ? "" : "AND (players.last_essence_updated IS NULL OR DATE(players.last_essence_updated) != CURDATE())";
$count_sql = "
    SELECT COUNT(*) as total 
    FROM players
    JOIN player_status ps ON players.player_status_id = ps.player_status_id
    WHERE $alive_condition
    $date_check
";
$count_result = $conn->query($count_sql);
$total_players = $count_result->fetch_assoc()["total"];

$batches = ceil($total_players / 1000);

$completed_tasks[] = date("Y-m-d H:i:s") . ": Total Players to be processed: $total_players in $batches batches.";

for ($batch = 0; $batch < $batches; $batch++) {
    $retryCount = 0;
    $success = false;
    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();
            $update_essence_sql = "
                UPDATE players 
                JOIN (
                    SELECT player_id
                    FROM players
                    JOIN player_status ps ON players.player_status_id = ps.player_status_id
                    WHERE $alive_condition
                    $date_check
                    LIMIT 1000 OFFSET " . ($batch * 1000) . "
                ) AS subquery ON players.player_id = subquery.player_id
                SET players.player_essence = players.player_essence + 0.25, players.last_essence_updated = NOW()
            ";

            if ($conn->query($update_essence_sql) === TRUE) {
                $players_updated = $conn->affected_rows;
                $conn->commit();
                $success = true;
                addCompletedTask("Batch $batch: Successfully updated essence for $players_updated players.");
            } else {
                throw new Exception("Error updating player essence: " . $conn->error);
            }
        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                sleep($retryDelay);
            } else {
                logError("Exception in batch $batch: " . $e->getMessage(), $conn->error);
                break;
            }
        }
    }
}

$conn->close();

$report = "----------------------\nREPORT\n----------------------\n";
foreach ($completed_tasks as $task) {
    $report .= $task . "\n";
}

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;

?>
