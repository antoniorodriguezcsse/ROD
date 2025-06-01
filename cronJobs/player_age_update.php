<?php

// Disable error display and reporting for production
ini_set('display_errors', 0);
error_reporting(0);

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$log_file = "age_update.log";
$completed_tasks = []; // To keep a record of the completed tasks

// Test Mode Toggle
$test_mode = false;

// Configuration for age updates based on player status and sleeping levels
$config = [
    'alive' => ['increment' => 1, 'days_since_last_update' => 1], // Update daily
    'sleeping' => [
        1 => ['increment' => 0, 'days_since_last_update' => 1,], // non-age update every day for level 1
        2 => ['increment' => 1, 'days_since_last_update' => 2], // age update every 2 days for level 2
        3 => ['increment' => 0, 'days_since_last_update' => 1], // non-age update every day for level 3
        4 => ['increment' => 1, 'days_since_last_update' => 2], // age update every 2 days for level 4
    ],
    // You can add more statuses here with different increments and update frequencies
];

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

function handleStatusUpdate($conn, $status, $settings, $level = null)
{
    global $completed_tasks, $maxRetries, $retryDelay, $maxJitter, $test_mode;

    $levelCondition = $level !== null ? "AND player_status.sleep_level = $level" : "";
    $testModeCondition = $test_mode ? "" : "AND (players.last_age_updated IS NULL OR DATEDIFF(CURDATE(), players.last_age_updated) >= {$settings['days_since_last_update']})";

    $total_players_sql = "
        SELECT COUNT(*) as total
        FROM players
        JOIN player_status ON players.player_status_id = player_status.player_status_id
        WHERE player_status.player_status_keyword = '$status'
        $levelCondition
        $testModeCondition
    ";
    $total_players_result = $conn->query($total_players_sql);
    if (!$total_players_result) {
        logMessage("Error executing count query: " . $conn->error, 'error');
        return;
    }
    $total_players = $total_players_result->fetch_assoc()["total"];

    $batches = ceil($total_players / 1000);
    addCompletedTask("Total Players to be processed for status $status: $total_players in $batches batches.");

    for ($batch = 0; $batch < $batches; $batch++) {
        $retryCount = 0;
        $success = false;

        while (!$success && $retryCount < $maxRetries) {
            try {
                $conn->begin_transaction();
                $update_age_sql = "
                    UPDATE players
                    JOIN (
                        SELECT player_id
                        FROM players
                        JOIN player_status ON players.player_status_id = player_status.player_status_id
                        WHERE player_status.player_status_keyword = '$status'
                        $levelCondition
                        $testModeCondition
                        LIMIT 1000 OFFSET " . ($batch * 1000) . "
                    ) AS subquery ON players.player_id = subquery.player_id
                    SET players.player_age = players.player_age + {$settings['increment']}, players.last_age_updated = CURDATE()
                ";

                if ($conn->query($update_age_sql) === true) {
                    $players_updated = $conn->affected_rows;
                    $conn->commit();
                    $success = true;
                    addCompletedTask("Status $status - Batch $batch: Successfully updated ages for $players_updated players.");
                } else {
                    throw new Exception("Error updating player ages: " . $conn->error);
                }
            } catch (Exception $e) {
                $retryCount++;
                $conn->rollback();
                if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                    $jitter = rand(0, $maxJitter);
                    $delay = $retryDelay * pow(2, $retryCount - 1) + $jitter;
                    sleep($delay);
                    logMessage("Retrying status $status - batch $batch due to deadlock. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
                } else {
                    logMessage("Exception in status $status - batch $batch: " . $e->getMessage(), 'error');
                    break;
                }
            }
        }
    }
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
} else {
    addCompletedTask("Successfully connected to the database.");
}

$conn->autocommit(false);

foreach ($config as $status => $settings) {
    // If the status is 'sleeping', we have to handle multiple levels
    if ($status === 'sleeping') {
        foreach ($settings as $level => $level_settings) {
            // Handling each sleeping level
            handleStatusUpdate($conn, $status, $level_settings, $level);
        }
    } else {
        // Handling statuses without levels
        handleStatusUpdate($conn, $status, $settings);
    }
}

$conn->close();

$total_players_processed = 0;

// Count the total players processed from the completed tasks
foreach ($completed_tasks as $task) {
    if (strpos($task, 'Successfully updated ages for') !== false) {
        preg_match('/Successfully updated ages for (\d+) players/', $task, $matches);
        $total_players_processed += intval($matches[1]);
    }
}

$report = "\n+-----------------------------------------+\n";
$report .= "|          Age Update Report             |\n";
$report .= "+-----------------------------------------+\n";
$report .= "| Total Players Processed: " . str_pad($total_players_processed, 12, ' ', STR_PAD_LEFT) . " |\n";
$report .= "+-----------------------------------------+\n\n";
$report .= "Completed Tasks:\n";
$report .= "================\n\n";
foreach ($completed_tasks as $task) {
    $report .= "[" . substr($task, 0, 19) . "] " . substr($task, 20) . "\n\n";
}

logMessage("Age update script completed.");
logMessage($report);

echo $report;
