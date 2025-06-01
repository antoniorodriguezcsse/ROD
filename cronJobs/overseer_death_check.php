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
$error_log_file = "overseer_death_check_errors.txt";
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

function sendNotificationToMultipleUrls($urls, $data) {
    $jsonData = json_encode($data);
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_exec($ch);
        curl_close($ch);
    }
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}

$conn->autocommit(FALSE);

// Fetch all 'alive' status IDs
$aliveStatusIds = [];
$aliveStatusQuery = "SELECT player_status_id FROM player_status WHERE player_status_keyword = 'alive'";
$aliveStatusResult = $conn->query($aliveStatusQuery);
if ($aliveStatusResult) {
    while ($row = $aliveStatusResult->fetch_assoc()) {
        $aliveStatusIds[] = $row['player_status_id'];
    }
    logMessage("Alive status IDs: " . implode(', ', $aliveStatusIds));
} else {
    logMessage("Failed to fetch 'alive' status IDs", 'error');
    die("Critical error: Unable to determine 'alive' status IDs");
}

if (empty($aliveStatusIds)) {
    logMessage("No 'alive' status IDs found", 'error');
    die("Critical error: No 'alive' status IDs found");
}

// Fetch and store subsistence data
$subsistenceData = [];
$subsistenceQuery = "SELECT player_age_group_id, subsistence FROM player_age_group";
$subsistenceResult = $conn->query($subsistenceQuery);
if ($subsistenceResult === false) {
    logMessage("Error fetching subsistence data: " . $conn->error, 'error');
    die("Subsistence data fetch failed");
}
while ($row = $subsistenceResult->fetch_assoc()) {
    $subsistenceData[$row['player_age_group_id']] = $row['subsistence'];
    //logMessage("subsistance: " . $row['subsistence']);
}

$offset = 0;
$total_notifications_sent = 0;
$script_start_time = microtime(true);

$hasMoreResults = true;
while ($hasMoreResults) {
    $retryCount = 0;
    $success = false;
    $batch_start_time = microtime(true);

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            $placeholders = str_repeat('?,', count($aliveStatusIds) - 1) . '?';
            $query = "
                SELECT o.overseer_url, o.days_till_death_notification, p.player_id, p.player_uuid, 
                       p.player_current_health, p.player_age_group_id
                FROM overseer o
                JOIN players p ON o.tracked_player_id = p.player_id
                WHERE o.notify_near_death = 1 AND p.player_status_id IN ($placeholders)
                      AND o.is_online = 1
                LIMIT ? OFFSET ?
            ";

            $stmt = $conn->prepare($query);
            $types = str_repeat('i', count($aliveStatusIds)) . 'ii';
            $params = array_merge($aliveStatusIds, [$batchSize, $offset]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $rowCount = $result->num_rows;
            logMessage("Query executed. Found $rowCount online overseers to process.");

            $notifications = [];

            while ($row = $result->fetch_assoc()) {
                $currentHealth = $row['player_current_health'];
                $ageGroupId = $row['player_age_group_id'];
                $subsistence = $subsistenceData[$ageGroupId] ?? 0;
                $notificationLimit = $row['days_till_death_notification'];
            
                //logMessage("Processing player: UUID = {$row['player_uuid']}, Current Health = $currentHealth, Age Group ID = $ageGroupId, Subsistence = $subsistence, Notification Limit = $notificationLimit");
            
                if ($subsistence > 0) {
                    // Calculate days until death, including today if health drops to 0 or below tonight
                    $daysUntilDeath = ceil($currentHealth / $subsistence);
            
                    //logMessage("Days until death: $daysUntilDeath");
            
                    if ($daysUntilDeath <= $notificationLimit) {

                        //logMessage("Notification condition met. Adding notification for player {$row['player_uuid']}");
                        $notifications[] = [
                            'overseer_url' => $row['overseer_url'],
                            'data' => [
                                'status' => 0,
                                'message' => 'Near death notification',
                                'extra' => [
                                    'player_uuid' => $row['player_uuid'],
                                    'days_until_death' => $daysUntilDeath,
                                    'current_health' => $currentHealth,
                                    'daily_subsistence' => $subsistence
                                ]
                            ]
                        ];
                    } else {
                        //logMessage("Notification condition not met for player {$row['player_uuid']}");
                    }
                } else {
                    logMessage("Warning: No or invalid subsistence data for age group ID $ageGroupId", 'warning');
                }
            }

            if (!empty($notifications)) {
                foreach ($notifications as $notification) {
                    sendNotificationToMultipleUrls([$notification['overseer_url']], $notification['data']);
                    logMessage("Notification sent to URL: {$notification['overseer_url']}");
                }
                $total_notifications_sent += count($notifications);
                addCompletedTask("Batch processed: " . count($notifications) . " notifications sent.");
            }

            $conn->commit();
            $success = true;
            $offset += $batchSize;

            $batch_end_time = microtime(true);
            $batch_duration = $batch_end_time - $batch_start_time;
            logMessage("Batch processed in " . number_format($batch_duration, 2) . " seconds.");

            $hasMoreResults = ($rowCount == $batchSize);

        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
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
$report .= "Total Notifications Sent: $total_notifications_sent\n";
$report .= "Total Execution Time: " . number_format($total_duration, 2) . " seconds\n";
$report .= implode("\n", $completed_tasks);

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;