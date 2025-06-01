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
$batchSize = 50;
$urlBatchSize = 6;
$error_log_file = "overseer_notifier_errors.txt";
$completed_tasks = [];

$cache = ['player_uuid' => []];

$maxAttacksPerNotification = 2;

function logMessage($message, $level = 'info') {
    global $error_log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_message = "$current_date [$level]: $message\n";
    file_put_contents($error_log_file, $log_message, FILE_APPEND);
    echo $log_message;
    //CommunicationController::postToDiscordCronLog($log_message);
}

function addCompletedTask($message) {
    global $completed_tasks;
    $completed_tasks[] = $message;
}

function formatDateTime($dateTimeString) {
    $dateTime = new DateTime($dateTimeString);
    $dateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
    return $dateTime->format('g:i:s A');  // Returns time in 12-hour format with seconds
}

function sendNotificationsBatch($urlBatch) {
    $mh = curl_multi_init();
    $curlHandles = [];
    $successCount = 0;
    
    foreach ($urlBatch as $item) {
        $ch = curl_init($item['url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($item['data']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        
        curl_multi_add_handle($mh, $ch);
        $curlHandles[] = $ch;
    }
    
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status == CURLM_OK);
    
    foreach ($curlHandles as $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 200 && $httpCode < 300) {
            $successCount++;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    
    return $successCount;
}

function getPlayerUUID($conn, $playerId) {
    global $cache;
    if (isset($cache['player_uuid'][$playerId])) {
        return $cache['player_uuid'][$playerId];
    }
    
    $stmt = $conn->prepare("SELECT player_uuid FROM players WHERE player_id = ?");
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        $cache['player_uuid'][$playerId] = $row['player_uuid'];
        return $row['player_uuid'];
    }
    
    return null;
}

function getOverseerUrls($conn, $victimId) {
    logMessage("Querying database for Overseer URLs for victim ID: $victimId");
    $stmt = $conn->prepare("SELECT overseer_url, notify_attack FROM overseer WHERE tracked_player_id = ? AND notify_attack = 1 AND is_online = 1");
    $stmt->bind_param("i", $victimId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $urls = [];
    while ($row = $result->fetch_assoc()) {
        logMessage("Online Overseer entry found for victim ID $victimId. URL: {$row['overseer_url']}");
        $urls[] = $row['overseer_url'];
    }
    
    if (empty($urls)) {
        logMessage("No active online Overseer entries found for victim ID $victimId");
    }
    
    return $urls;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    logMessage("Connection failed: " . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}
logMessage("Successfully connected to the database.");

$conn->autocommit(FALSE);

$fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));
$offset = 0;
$total_notifications_sent = 0;
$total_failed_notifications = 0;
$urlBatch = [];

logMessage("Checking for attacks since: $fifteenMinutesAgo");

do {
    $retryCount = 0;
    $success = false;

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                SELECT 
                    a.victim_id, a.attacker_id, a.attack_date, a.victim_blood_after_attack, a.attack_location
                FROM attack_log a
                WHERE a.attack_date >= ?
                ORDER BY a.victim_id, a.attack_date
                LIMIT ? OFFSET ?
            ");
            
            $stmt->bind_param("sii", $fifteenMinutesAgo, $batchSize, $offset);
            $stmt->execute();
            $result = $stmt->get_result();

            logMessage("Query executed. Found " . $result->num_rows . " rows to process.");

            $currentVictimId = null;
            $currentAttacks = [];
            
            while ($row = $result->fetch_assoc()) {
                if ($currentVictimId !== $row['victim_id'] || count($currentAttacks) >= $maxAttacksPerNotification) {
                    if ($currentVictimId !== null && !empty($currentAttacks)) {
                        $overseerUrls = getOverseerUrls($conn, $currentVictimId);
                        foreach ($overseerUrls as $url) {
                            $urlBatch[] = [
                                'url' => $url,
                                'data' => [
                                    'status' => 0,
                                    'message' => 'Attack notification',
                                    'extra' => ['attacks' => array_values($currentAttacks)]
                                ]
                            ];
                            
                            if (count($urlBatch) >= $urlBatchSize) {
                                $sent = sendNotificationsBatch($urlBatch);
                                $total_notifications_sent += $sent;
                                $total_failed_notifications += (count($urlBatch) - $sent);
                                logMessage("Sent batch of $sent notifications. Failed: " . (count($urlBatch) - $sent));
                                addCompletedTask("Sent batch of $sent notifications. Failed: " . (count($urlBatch) - $sent));
                                $urlBatch = [];
                            }
                        }
                    }
                    $currentVictimId = $row['victim_id'];
                    $currentAttacks = [];
                }
                
                // Update or add the latest attack for this attacker
                $currentAttacks[$row['attacker_id']] = [
                    'attacker_uuid' => getPlayerUUID($conn, $row['attacker_id']),
                    'attack_date' => formatDateTime($row['attack_date']),
                    'victim_blood_after_attack' => $row['victim_blood_after_attack'],
                    'attack_location' => $row['attack_location']
                ];
            }
            
            // Process the last victim's attacks
            if ($currentVictimId !== null && !empty($currentAttacks)) {
                $overseerUrls = getOverseerUrls($conn, $currentVictimId);
                foreach ($overseerUrls as $url) {
                    $urlBatch[] = [
                        'url' => $url,
                        'data' => [
                            'status' => 0,
                            'message' => 'Attack notification',
                            'extra' => ['attacks' => array_values($currentAttacks)]
                        ]
                    ];
                    
                    if (count($urlBatch) >= $urlBatchSize) {
                        $sent = sendNotificationsBatch($urlBatch);
                        $total_notifications_sent += $sent;
                        $total_failed_notifications += (count($urlBatch) - $sent);
                        logMessage("Sent batch of $sent notifications. Failed: " . (count($urlBatch) - $sent));
                        addCompletedTask("Sent batch of $sent notifications. Failed: " . (count($urlBatch) - $sent));
                        $urlBatch = [];
                    }
                }
            }

            $conn->commit();
            $success = true;
            logMessage("Batch processed successfully.");

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

    if ($success) {
        $offset += $batchSize;
    } else {
        logMessage("Moving to next batch after failed retries.", 'warning');
        $offset += $batchSize;
    }

} while ($result->num_rows == $batchSize);

// Send any remaining notifications
if (!empty($urlBatch)) {
    $sent = sendNotificationsBatch($urlBatch);
    $total_notifications_sent += $sent;
    $total_failed_notifications += (count($urlBatch) - $sent);
    logMessage("Sent final batch of $sent notifications. \nFailed: " . (count($urlBatch) - $sent ) . "\n");
    addCompletedTask("Sent final batch of $sent notifications. \nFailed: " . (count($urlBatch) - $sent ) . "\n");
}

$conn->close();

$report = "-----------------------------\nOVERSEER ATTACK NOTIFY REPORT: " . date("Y-m-d h:i:sa"). "\n-----------------------------\n";
$report .= "Total Notifications Sent to Online Overseers: $total_notifications_sent\n";
$report .= "Total Failed Notifications: $total_failed_notifications\n\n";
$report .= implode("\n", $completed_tasks);

file_put_contents($error_log_file, $report, FILE_APPEND);


echo $report;

logMessage("Script execution completed.");
