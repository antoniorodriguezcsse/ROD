<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$maxRetries = 3;
$retryDelay = 5;
$maxJitter = 2;
$batchSize = 100;
$error_log_file = "resire_requests_processing_errors.txt";
$completed_tasks = [];

// Configurable option for the number of days
$daysToProcess = 15;

function logMessage($message, $level = 'info') {
    global $error_log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_message = "$current_date [$level]: $message\n";
    file_put_contents($error_log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

function addCompletedTask($message) {
    global $completed_tasks;
    $completed_tasks[] = $message;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}

$conn->autocommit(FALSE);

$offset = 0;
$total_requests_processed = 0;
$script_start_time = microtime(true);

$hasMoreResults = true;
while ($hasMoreResults) {
    $retryCount = 0;
    $success = false;
    $batch_start_time = microtime(true);

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            $query = "
                SELECT r.id, r.player_id, r.current_sire_id, r.root_sire_old_sire_id, 
                       p.player_uuid, s.player_uuid AS sire_uuid
                FROM resire_requests r
                JOIN players p ON r.player_id = p.player_id
                JOIN players s ON r.current_sire_id = s.player_id
                WHERE r.status = 'pending' 
                AND r.request_date <= DATE_SUB(NOW(), INTERVAL ? DAY)
                LIMIT ? OFFSET ?
                FOR UPDATE
            ";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("iii", $daysToProcess, $batchSize, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            $rowCount = $result->num_rows;
            logMessage("Query executed. Found $rowCount pending resire requests to process.");

            while ($row = $result->fetch_assoc()) {
                $resireRequestId = $row['id'];
                $playerUUID = $row['player_uuid'];
                $sireUUID = $row['sire_uuid'];
                $rootSireOldSireId = $row['root_sire_old_sire_id'];

                // Get the root sire's old sire details
                $oldSireQuery = "SELECT player_uuid, bloodline_id, player_generation FROM players WHERE player_id = ? FOR UPDATE";
                $oldSireStmt = $conn->prepare($oldSireQuery);
                $oldSireStmt->bind_param("i", $rootSireOldSireId);
                $oldSireStmt->execute();
                $oldSireResult = $oldSireStmt->get_result();
                $oldSireRow = $oldSireResult->fetch_assoc();

                $rootSireOldSireUUID = $oldSireRow['player_uuid'];
                $rootSireOldSireBloodlineId = $oldSireRow['bloodline_id'];
                $rootSireOldSireGeneration = $oldSireRow['player_generation'];

                // Update the player's sire to the root sire's old sire
                $updatePlayerQuery = "UPDATE players SET sire_id = ?, bloodline_id = ?, player_generation = ? WHERE player_uuid = ?";
                $updatePlayerStmt = $conn->prepare($updatePlayerQuery);
                $newGeneration = $rootSireOldSireGeneration + 1;
                $updatePlayerStmt->bind_param("iiis", $rootSireOldSireId, $rootSireOldSireBloodlineId, $newGeneration, $playerUUID);
                $updatePlayerStmt->execute();

                if ($updatePlayerStmt->affected_rows > 0) {
                    // Move the request to history table
                    $moveToHistoryQuery = "INSERT INTO resire_requests_history
                    (original_request_id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, status, completed_date)
                    SELECT id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, 'rejected', NOW()
                    FROM resire_requests WHERE id = ?";
                    $moveToHistoryStmt = $conn->prepare($moveToHistoryQuery);
                    $moveToHistoryStmt->bind_param("i", $resireRequestId);
                    $moveToHistoryStmt->execute();

                    // Delete the original request
                    $deleteQuery = "DELETE FROM resire_requests WHERE id = ?";
                    $deleteStmt = $conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("i", $resireRequestId);
                    $deleteStmt->execute();

                    logMessage("Processed resire request ID: $resireRequestId for player UUID: $playerUUID");
                    $total_requests_processed++;
                } else {
                    logMessage("Failed to update player's sire for request ID: $resireRequestId", 'error');
                    throw new Exception("Failed to update player's sire for request ID: $resireRequestId");
                }
            }

            $conn->commit();
            $success = true;
            $offset += $batchSize;

            $batch_end_time = microtime(true);
            $batch_duration = $batch_end_time - $batch_start_time;
            logMessage("Batch processed in " . number_format($batch_duration, 2) . " seconds.");

            addCompletedTask("Batch processed: $rowCount requests.");

            $hasMoreResults = ($rowCount == $batchSize);

        } catch (Exception $e) {
            $conn->rollback();
            $retryCount++;
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                $jitter = rand(0, $maxJitter);
                $delay = $retryDelay * pow(2, $retryCount - 1) + $jitter;
                sleep($delay);
                logMessage("Retrying batch due to deadlock. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
            } else {
                logMessage("Exception in batch: " . $e->getMessage(), 'error');
                break;
            }
        }
    }
}

$conn->close();

$script_end_time = microtime(true);
$total_duration = $script_end_time - $script_start_time;

$report = "----------------------\nREPORT\n----------------------\n";
$report .= "Total Resire Requests Processed: $total_requests_processed\n";
$report .= "Processed requests older than $daysToProcess days\n";
$report .= "Total Execution Time: " . number_format($total_duration, 2) . " seconds\n";
$report .= implode("\n", $completed_tasks);

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;