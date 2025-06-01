<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$maxRetries = 3;
$retryDelay = 5;
$maxJitter = 2;
$batchSize = 100;
$error_log_file = "overseer_status_check_errors.txt";
$completed_tasks = [];

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

function pingOverseer($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => 'ping']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode == 200 && $response == 'pong';
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}

$conn->autocommit(FALSE);

$offset = 0;
$total_overseers_checked = 0;
$total_overseers_offline = 0;
$script_start_time = microtime(true);

logMessage("Starting overseer status check script.");

do {
    $retryCount = 0;
    $success = false;
    $batch_start_time = microtime(true);

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            $query = "
                SELECT overseer_id, overseer_url, offline_counter
                FROM overseer
                WHERE is_online = 1
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $conn->prepare($query);
            
            if ($stmt === false) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }

            $stmt->bind_param("ii", $batchSize, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            $rowCount = $result->num_rows;
            logMessage("Query executed. Found $rowCount overseers to process.");

            while ($row = $result->fetch_assoc()) {
                $overseerId = $row['overseer_id'];
                $overseerUrl = $row['overseer_url'];
                $offlineCounter = $row['offline_counter'];

                $isOnline = pingOverseer($overseerUrl);

                if ($isOnline) {
                    $updateSql = "UPDATE overseer SET offline_counter = 0 WHERE overseer_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("i", $overseerId);
                    $updateStmt->execute();
                    logMessage("Overseer $overseerId is online. Counter reset.");
                } else {
                    $offlineCounter++;
                    if ($offlineCounter >= 3) {
                        $updateSql = "UPDATE overseer SET is_online = 0, offline_counter = ? WHERE overseer_id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("ii", $offlineCounter, $overseerId);
                        $updateStmt->execute();
                        logMessage("Overseer $overseerId marked as offline. Offline for $offlineCounter days.");
                        $total_overseers_offline++;
                    } else {
                        $updateSql = "UPDATE overseer SET offline_counter = ? WHERE overseer_id = ?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("ii", $offlineCounter, $overseerId);
                        $updateStmt->execute();
                        logMessage("Overseer $overseerId is offline. Counter increased to $offlineCounter.");
                    }
                }
                $total_overseers_checked++;
            }

            $conn->commit();
            $success = true;
            $offset += $batchSize;

            $batch_end_time = microtime(true);
            $batch_duration = $batch_end_time - $batch_start_time;
            logMessage("Batch processed in " . number_format($batch_duration, 2) . " seconds.");

            addCompletedTask("Batch processed: $rowCount overseers checked.");

            // Sleep for 1 second between batches
            sleep(1);

        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            logMessage("Exception in batch: " . $e->getMessage(), 'error');
            
            if ($retryCount >= $maxRetries) {
                logMessage("Max retries reached. Exiting script.", 'error');
                die("Script terminated due to repeated errors.");
            }
            
            $delay = $retryDelay * pow(2, $retryCount - 1) + rand(0, $maxJitter);
            sleep($delay);
            logMessage("Retrying batch. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
        }
    }
} while ($rowCount == $batchSize);

$conn->close();

$script_end_time = microtime(true);
$total_duration = $script_end_time - $script_start_time;

$report = "----------------------\nREPORT\n----------------------\n";
$report .= "Total Overseers Checked: $total_overseers_checked\n";
$report .= "Total Overseers Marked Offline: $total_overseers_offline\n";
$report .= "Total Execution Time: " . number_format($total_duration, 2) . " seconds\n";
$report .= implode("\n", $completed_tasks);

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;