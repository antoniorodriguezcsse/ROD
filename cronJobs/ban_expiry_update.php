<?php

// Disable error display and reporting for production
ini_set('display_errors', 0);
error_reporting(0);

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";

$log_file = "ban_expiry_update.log";
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

// Get total number of active banned players
$total_banned_players_sql = "
    SELECT COUNT(*) as total 
    FROM banned_players 
    WHERE DATE(ban_end_date) > CURDATE() 
    AND is_permanent = 0";

$total_banned_players_result = $conn->query($total_banned_players_sql);
$total_banned_players = $total_banned_players_result->fetch_assoc()["total"];

// Get players whose ban has expired - with detailed info
$expired_bans_sql = "
    SELECT 
        bp.player_id, 
        bp.ban_id, 
        bp.ban_reason,
        bp.ban_start_date,
        bp.ban_end_date,
        bp.banned_by,
        p.species_id, 
        p.player_uuid,
        p.legacy_name,
        b.legacy_name as banned_by_name
    FROM banned_players bp
    JOIN players p ON bp.player_id = p.player_id
    LEFT JOIN players b ON bp.banned_by = b.player_id
    WHERE DATE(bp.ban_end_date) <= CURDATE()
    AND bp.is_permanent = 0";

$expired_bans_result = $conn->query($expired_bans_sql);
if (!$expired_bans_result) {
    logMessage("Error executing expired bans query: " . $conn->error, 'error');
    die("Error executing expired bans query: " . $conn->error);
}

$total_expired_bans = $expired_bans_result->num_rows;
$completed_tasks[] = "Total players to be processed: $total_expired_bans";

$processed_players = []; // Array to store details of processed players
$total_players_updated = 0;

while ($row = $expired_bans_result->fetch_assoc()) {
    $player_id = $row['player_id'];
    $ban_id = $row['ban_id'];
    $species_id = $row['species_id'];
    $player_uuid = $row['player_uuid'];
    $retryCount = 0;
    $success = false;

    while (!$success && $retryCount < $maxRetries) {
        try {
            $conn->begin_transaction();

            // Get the 'dead' status ID for this species
            $status_sql = "
                SELECT player_status_id 
                FROM player_status 
                WHERE species_id = ? 
                AND player_status_keyword = 'dead'
                LIMIT 1";
            
            $stmt = $conn->prepare($status_sql);
            $stmt->bind_param("i", $species_id);
            $stmt->execute();
            $status_result = $stmt->get_result();
            
            if ($status_row = $status_result->fetch_assoc()) {
                $dead_status_id = $status_row['player_status_id'];
                
                // Update player status to dead and set health to 0
                $update_status_sql = "
                    UPDATE players 
                    SET player_status_id = ?,
                        player_current_health = 0.000
                    WHERE player_id = ?";
                
                $stmt = $conn->prepare($update_status_sql);
                $stmt->bind_param("ii", $dead_status_id, $player_id);
                
                if ($stmt->execute()) {
                    // Remove the ban record
                    $delete_ban_sql = "
                        DELETE FROM banned_players
                        WHERE ban_id = ?";
                    
                    $stmt = $conn->prepare($delete_ban_sql);
                    $stmt->bind_param("i", $ban_id);
                    
                    if ($stmt->execute()) {
                        $total_players_updated++;
                        $success = true;
                        $conn->commit();
                        
                        // Store processed player details
                        $processed_players[] = [
                            'legacy_name' => $row['legacy_name'],
                            'player_uuid' => $row['player_uuid'],
                            'ban_reason' => $row['ban_reason'],
                            'ban_start_date' => $row['ban_start_date'],
                            'ban_end_date' => $row['ban_end_date'],
                            'banned_by' => $row['banned_by_name']
                        ];
                        
                        addCompletedTask("Player {$row['legacy_name']} ($player_uuid): Successfully updated to dead status and removed ban record #$ban_id.");
                        
                        // Send reset_hud message
                        $curl = curl_init();
                        curl_setopt_array($curl, [
                            CURLOPT_URL => "http://api.systemsl.xyz/Mystical_Convergence/AdminActions.php?action=sendToHud",
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode([
                                "player_uuid" => $player_uuid,
                                "message" => [
                                    "status" => "200",
                                    "message" => "reset_hud"
                                ]
                            ])
                        ]);
                        curl_exec($curl);
                        curl_close($curl);
                    } else {
                        throw new Exception("Error deleting ban record for player $player_uuid: " . $conn->error);
                    }
                } else {
                    throw new Exception("Error updating player status for player $player_uuid: " . $conn->error);
                }
            } else {
                throw new Exception("Could not find dead status for species ID $species_id");
            }
        } catch (Exception $e) {
            $retryCount++;
            $conn->rollback();
            if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries) {
                $jitter = rand(0, $maxJitter);
                $delay = $retryDelay * pow(2, $retryCount - 1) + $jitter;
                sleep($delay);
                logMessage("Retrying player {$row['legacy_name']} ($player_uuid) due to deadlock. Retry count: $retryCount, Delay: $delay seconds.", 'warning');
            } else {
                logMessage("Exception for player {$row['legacy_name']} ($player_uuid): " . $e->getMessage(), 'error');
                break;
            }
        }
    }
}

$conn->close();

// Generate detailed report
$report = "\n+===========================================+\n";
$report .= "|        Ban Expiry Update Report          |\n";
$report .= "+===========================================+\n";
$report .= "Debug Information:\n";
$report .= "=================\n";
$report .= "Current Date (CURDATE): " . date('Y-m-d') . "\n";
$report .= "Current Time (NOW): " . date('Y-m-d H:i:s') . "\n\n";
$report .= "| Total Active Bans: " . str_pad($total_banned_players, 20, ' ', STR_PAD_LEFT) . " |\n";
$report .= "| Total Expired Bans Processed: " . str_pad($total_expired_bans, 10, ' ', STR_PAD_LEFT) . " |\n";
$report .= "| Total Players Updated: " . str_pad($total_players_updated, 15, ' ', STR_PAD_LEFT) . " |\n";
$report .= "+===========================================+\n\n";

if (!empty($processed_players)) {
    $report .= "Processed Players:\n";
    $report .= "=================\n\n";
    foreach ($processed_players as $player) {
        $report .= "Player Name: " . $player['legacy_name'] . "\n";
        $report .= "UUID: " . $player['player_uuid'] . "\n";
        $report .= "Ban Reason: " . $player['ban_reason'] . "\n";
        $report .= "Ban Start: " . $player['ban_start_date'] . "\n";
        $report .= "Ban End: " . $player['ban_end_date'] . "\n";
        $report .= "Banned By: " . $player['banned_by'] . "\n";
        $report .= "-------------------------------------------\n\n";
    }
}

$report .= "\nCompleted Tasks:\n";
$report .= "================\n\n";
foreach ($completed_tasks as $task) {
    $report .= "[" . substr($task, 0, 19) . "] " . substr($task, 20) . "\n\n";
}

logMessage("Ban expiry update script completed.");
logMessage($report);

echo $report;