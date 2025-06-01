<?php
// Subsistence Health Check Script
// Purpose: Deduct subsistence from players based on age group, status, and last subsistence deduction date

// Disable error display and reporting for production
ini_set('display_errors', 0);
error_reporting(0);

require_once '/www/wwwroot/api.systemsl.xyz/Mystical_Convergence/vendor/autoload.php';

use Fallen\SecondLife\Controllers\DeathLogController;

$pdo = require __DIR__ . '/../src/Classes/database.php';

// Log file path
$log_file = "subsistence_health_check.log";

// Arrays and variables for tracking script progress and statistics
$completed_tasks = []; // Array to store completed tasks
$total_died = 0; // Total players who died in this batch
$total_processed = 0; // Total players processed in this batch
$test_mode = false; // Set to true for testing purposes

// Subsistence Modifiers Configuration
// Defines the subsistence modifiers and update frequencies for different player statuses and sleep levels
$subsistenceModifiers = [
    'alive' => [
        'modifier' => 1, // 1.0 = 100% subsistence
        'days_since_last_update' => 1, // Update daily
    ],
    'sleeping' => [
        1 => [
            'modifier' => 0.0, // Update every 1 day for level 1 with 0% subsistence
            'days_since_last_update' => 1,
        ],
        2 => [
            'modifier' => 0.0, // Update every 1 days for level 2 with 0% subsistence
            'days_since_last_update' => 1,
        ],
        3 => [
            'modifier' => 0.0, // Update every 1 days for level 3 with 0% subsistence
            'days_since_last_update' => 1,
        ],
        4 => [
            'modifier' => 0.0, // Update every 1 days for level 4 with 0% subsistence
            'days_since_last_update' => 1,
        ],
        // Add more levels as needed
    ],
    //'away' => ['modifier' => 0.75, 'days_since_last_update' => 3],
    //'anotherStatus' => ['modifier' => 1.5, 'days_since_last_update' => 2],
];

// Skip Conditions Configuration
// Defines the player statuses to skip during subsistence deduction
$skipStatuses = [
    'banned',
    'dead',
    'dead from hunger',
];

// Function to log messages to the log file
function logMessage($message, $level = 'info')
{
    global $log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_entry = "$current_date [$level]: $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Function to add completed tasks to the completed tasks array
function addCompletedTask($message)
{
    global $completed_tasks;
    $current_date = date("Y-m-d H:i:s");
    $completed_tasks[] = "$current_date: $message";
}

// Configuration for retry mechanism
$maxRetries = 3;
$retryDelay = 10;
$maxJitter = 5;

logMessage("Starting subsistence health check script.");

// Array to track processed player IDs
$processed_player_ids = array();

// SQL query to retrieve age groups and player counts
$age_groups_sql = "
    SELECT pag.player_age_group_id, pag.age_group_name, COUNT(p.player_id) as total_players
    FROM player_age_group pag
    LEFT JOIN players p ON pag.player_age_group_id = p.player_age_group_id
    WHERE p.player_current_health > 0
    AND p.player_status_id NOT IN (
        SELECT player_status_id
        FROM player_status
        WHERE player_status_keyword IN ('" . implode("','", $skipStatuses) . "')
    )
    GROUP BY pag.player_age_group_id
    ORDER BY pag.player_age_group_id
";
$age_groups_result = $pdo->query($age_groups_sql);

// Iterate over each age group
while ($age_group = $age_groups_result->fetch(PDO::FETCH_ASSOC)) {
    $age_group_id = $age_group['player_age_group_id'];
    $age_group_name = $age_group['age_group_name'];
    $total_players = $age_group['total_players'];

    addCompletedTask("Identified $total_players players in age group '$age_group_name' (ID: $age_group_id) to process.");
    $batches = ceil($total_players / 1000);

    // Process players in batches of 1000
    for ($batch = 0; $batch < $batches; $batch++) {
        $retryCount = 0;
        $success = false;
        $players_died_this_batch = 0;

        // Retry mechanism with exponential backoff and jitter
        while (!$success && $retryCount < $maxRetries) {
            try {
                $pdo->beginTransaction();

                // Subsistence deduction SQL with adjustments based on player status and sleep level
                $subsistence_sql = "
                    UPDATE players p
                    JOIN player_age_group pag ON p.player_age_group_id = pag.player_age_group_id
                    JOIN player_status ps ON p.player_status_id = ps.player_status_id
                    SET
                        p.player_current_health = CASE";

                foreach ($subsistenceModifiers as $status => $config) {
                    if ($status === 'sleeping') {
                        foreach ($config as $level => $levelConfig) {
                            $days_since_last_update = $levelConfig['days_since_last_update'];
                            $date_check = $test_mode ? "" : "AND (p.last_subsistence_deducted IS NULL OR DATEDIFF(CURDATE(), p.last_subsistence_deducted) >= $days_since_last_update)";
                            $subsistence_sql .= "
                            WHEN ps.player_status_keyword = '$status' AND ps.sleep_level = $level $date_check THEN
                                GREATEST(p.player_current_health - (pag.subsistence * {$levelConfig['modifier']}), 0)";
                        }
                    } else {
                        $days_since_last_update = $config['days_since_last_update'];
                        $date_check = $test_mode ? "" : "AND (p.last_subsistence_deducted IS NULL OR DATEDIFF(CURDATE(), p.last_subsistence_deducted) >= $days_since_last_update)";
                        $subsistence_sql .= "
                            WHEN ps.player_status_keyword = '$status' $date_check THEN
                                GREATEST(p.player_current_health - (pag.subsistence * {$config['modifier']}), 0)";
                    }
                }

                $subsistence_sql .= "
                            ELSE p.player_current_health
                        END,
                        p.last_subsistence_deducted = CASE";

                foreach ($subsistenceModifiers as $status => $config) {
                    if ($status === 'sleeping') {
                        foreach ($config as $level => $levelConfig) {
                            $days_since_last_update = $levelConfig['days_since_last_update'];
                            $date_check = $test_mode ? "" : "AND (p.last_subsistence_deducted IS NULL OR DATEDIFF(CURDATE(), p.last_subsistence_deducted) >= $days_since_last_update)";
                            $subsistence_sql .= "
                            WHEN ps.player_status_keyword = '$status' AND ps.sleep_level = $level $date_check THEN CURDATE()";
                        }
                    } else {
                        $days_since_last_update = $config['days_since_last_update'];
                        $date_check = $test_mode ? "" : "AND (p.last_subsistence_deducted IS NULL OR DATEDIFF(CURDATE(), p.last_subsistence_deducted) >= $days_since_last_update)";
                        $subsistence_sql .= "
                            WHEN ps.player_status_keyword = '$status' $date_check THEN CURDATE()";
                    }
                }

                $subsistence_sql .= "
                            ELSE p.last_subsistence_deducted
                        END
                    WHERE p.player_age_group_id = :age_group_id
                    AND p.player_current_health > 0
                    AND ps.player_status_keyword NOT IN ('" . implode("','", $skipStatuses) . "')
                ";

                // Execute the subsistence deduction SQL
                $stmt = $pdo->prepare($subsistence_sql);
                $stmt->bindParam(':age_group_id', $age_group_id, PDO::PARAM_INT);
                $stmt->execute();
                $players_processed_this_batch = $stmt->rowCount();
                $total_processed += $players_processed_this_batch;

                // Fetch deceased players
                $deceased_players_sql = "
                    SELECT player_id
                    FROM players
                    WHERE player_current_health <= 0
                    AND player_age_group_id = :age_group_id
                    AND player_status_id NOT IN (
                        SELECT player_status_id
                        FROM player_status
                        WHERE player_status_keyword IN ('dead', 'dead from hunger')
                    )
                    LIMIT 1000
                ";
                $deceased_stmt = $pdo->prepare($deceased_players_sql);
                $deceased_stmt->bindParam(':age_group_id', $age_group_id, PDO::PARAM_INT);
                $deceased_stmt->execute();
                $deceased_players = $deceased_stmt->fetchAll(PDO::FETCH_COLUMN);

                $newly_deceased_players = array_diff($deceased_players, $processed_player_ids);
                $processed_player_ids = array_merge($processed_player_ids, $newly_deceased_players);

                // Update status to 'dead from hunger' and set essence to 0.0 for each newly deceased player in this batch
                if (!empty($newly_deceased_players)) {
                    $update_status_sql = "
                        UPDATE players p
                        JOIN player_status ps ON p.species_id = ps.species_id AND ps.player_status_keyword = 'dead from hunger'
                        SET p.player_status_id = ps.player_status_id,
                            p.player_essence = 0.0
                        WHERE p.player_id IN (" . implode(",", $newly_deceased_players) . ")
                    ";

                    $update_stmt = $pdo->prepare($update_status_sql);
                    $update_stmt->execute();
                    $players_died_this_batch = count($newly_deceased_players);
                    $total_died += $players_died_this_batch;

                    // Log each death
                    foreach ($newly_deceased_players as $deceasedPlayerId) {
                        $deceasedPlayerId = (int) $deceasedPlayerId;
                        $killerPlayerId = null;
                        $deathDate = date('Y-m-d H:i:s');
                        $deathLocation = null;
                        $causeOfDeath = 'hunger';
                        $comments = null;

                        try {
                            $response = DeathLogController::addDeathLogEntry($pdo, $deceasedPlayerId, $killerPlayerId, $deathDate, $deathLocation, $causeOfDeath, $comments);

                            $decodedResponse = json_decode($response, true);

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
                            }

                            if ($decodedResponse['status'] === 200) {
                                $deathId = $decodedResponse['extra']['deathId'] ?? 'Unknown';

                                addCompletedTask("Added death log entry for player ID: $deceasedPlayerId. Death ID: $deathId");
                                logMessage("Death log entry added for player ID: $deceasedPlayerId. Death ID: $deathId", 'info');
                            } else {
                                logMessage("Error adding death log entry for player ID $deceasedPlayerId: " . ($decodedResponse['message'] ?? 'Unknown error'), 'error');
                            }
                        } catch (Exception $e) {
                            logMessage("Exception while adding death log entry for player ID $deceasedPlayerId: " . $e->getMessage(), 'error');
                        }
                        sleep(1); // Sleep for 1 second between requests this is also to help with discord rate limit
                    }
                }

                $pdo->commit();
                $success = true;
                addCompletedTask("Batch $batch: Processed $players_processed_this_batch players, $players_died_this_batch died in age group '$age_group_name' (ID: $age_group_id).");
            } catch (PDOException $e) {
                $retryCount++;
                $pdo->rollBack();

                if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                    $jitter = rand(0, $maxJitter);
                    $delay = $retryDelay * pow(2, $retryCount - 1) + $jitter;
                    sleep($delay);
                    logMessage("Retrying batch $batch for age group '$age_group_name' (ID: $age_group_id) due to deadlock. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
                } else {
                    $errorMessage = "Exception in batch $batch for age group '$age_group_name' (ID: $age_group_id): " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString();
                    logMessage($errorMessage, 'error');
                    break;
                }
            }
        }
    }
}

// Generate the report
$report = "\n+-----------------------------------------+\n";
$report .= "|   Subsistence Health Check Report       |\n";
$report .= "+-----------------------------------------+\n";
$report .= "| Total Players Processed:           $total_processed    |\n";
$report .= "| Total Players Who Died:            $total_died    |\n";
$report .= "+-----------------------------------------+\n\n";
$report .= "Completed Tasks:\n";
$report .= "================\n\n";
foreach ($completed_tasks as $task) {
    $report .= "[" . substr($task, 0, 19) . "] " . substr($task, 20) . "\n\n";
}

logMessage("Subsistence health check script completed.");
logMessage($report);

echo $report;