<?php

// Disable error display and reporting for production
ini_set('display_errors', 0);
error_reporting(0);

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$log_file = "sleep_end_update.log";
$completed_tasks = []; // To keep a record of the completed tasks

function logMessage($message, $level = 'info')
{
    global $log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_entry = "$current_date [$level]: $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function addCompletedTask($message)
{
    global $completed_tasks;
    $completed_tasks[] = date("Y-m-d H:i:s") . ": " . $message;
}

$maxRetries = 3;
$retryDelay = 5; // Delay in seconds for retrying after a deadlock
$maxJitter = 2; // Maximum jitter in seconds

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
} else {
    addCompletedTask("Successfully connected to the database.");
}

$conn->autocommit(false);

$total_sleeping_players_sql = "SELECT COUNT(*) as total FROM players_sleeping_log";
$total_sleeping_players_result = $conn->query($total_sleeping_players_sql);
$total_sleeping_players = $total_sleeping_players_result->fetch_assoc()["total"];

$total_players_sql = "
    SELECT player_id
    FROM players_sleeping_log
    WHERE sleep_end_date <= CURDATE()
";
$total_players_result = $conn->query($total_players_sql);
if (!$total_players_result) {
    logMessage("Error executing count query: " . $conn->error, 'error');
    die("Error executing count query: " . $conn->error);
}
$total_players = $total_players_result->num_rows;

$completed_tasks[] = "Total Players to be processed: $total_players";

$total_players_removed = 0;

while ($row = $total_players_result->fetch_assoc()) {
    $player_id = $row['player_id'];
    $retryCount = 0;
    $success = false;

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            $update_status_sql = "
                UPDATE players p
                JOIN player_status ps ON p.species_id = ps.species_id AND ps.player_status_keyword = 'alive'
                SET p.player_status_id = ps.player_status_id
                WHERE p.player_id = $player_id
            ";

            if ($conn->query($update_status_sql) === true) {
                $delete_log_sql = "
                    DELETE FROM players_sleeping_log
                    WHERE player_id = $player_id
                ";

                if ($conn->query($delete_log_sql) === true) {
                    $total_players_removed++;
                    $conn->commit();
                    $success = true;
                    addCompletedTask("Player $player_id: Successfully updated status and removed from sleep.");
                } else {
                    throw new Exception("Error deleting sleeping log for player $player_id: " . $conn->error);
                }
            } else {
                throw new Exception("Error updating player status for player $player_id: " . $conn->error);
            }
        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                $jitter = rand(0, $maxJitter);
                $delay = $retryDelay * pow(2, $retryCount - 1) + $jitter;
                sleep($delay);
                logMessage("Retrying player $player_id due to deadlock. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
            } else {
                logMessage("Exception for player $player_id: " . $e->getMessage(), 'error');
                break;
            }
        }
    }
}

$conn->close();

$report = "\n+---------------------------------------------+\n";
$report .= "|        Sleep End Update Report             |\n";
$report .= "+---------------------------------------------+\n";
$report .= "| Total Sleeping Players: " . str_pad($total_sleeping_players, 16, ' ', STR_PAD_LEFT) . " |\n";
$report .= "| Total Players Processed: " . str_pad($total_players, 14, ' ', STR_PAD_LEFT) . " |\n";
$report .= "| Total Players Removed from Sleep: " . str_pad($total_players_removed, 6, ' ', STR_PAD_LEFT) . " |\n";
$report .= "+---------------------------------------------+\n\n";
$report .= "Completed Tasks:\n";
$report .= "================\n\n";
foreach ($completed_tasks as $task) {
    $report .= "[" . substr($task, 0, 19) . "] " . substr($task, 20) . "\n\n";
}

logMessage("Sleep end update script completed.");
logMessage($report);

echo $report;