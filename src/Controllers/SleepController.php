<?php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;

class SleepController {


    public static function setSleeping(PDO $pdo, $playerUuid, $sleepLevel) {
        try {
            // Get the player's species ID and player ID
            $stmt = $pdo->prepare("SELECT player_id, species_id FROM players WHERE player_uuid = :player_uuid");
            $stmt->bindParam(":player_uuid", $playerUuid);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$result) {
                return new JsonResponse(404, "Player not found.");
            }
    
            $playerId = $result['player_id'];
            $speciesId = $result['species_id'];
    
            // Get the sleeping status ID and sleep days based on species and sleep level
            $stmt = $pdo->prepare("SELECT player_status_id, sleep_days FROM player_status WHERE species_id = :species_id AND sleep_level = :sleep_level");
            $stmt->bindParam(":species_id", $speciesId);
            $stmt->bindParam(":sleep_level", $sleepLevel);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$result) {
                return new JsonResponse(400, "Invalid sleep level for the player's species.");
            }
    
            $sleepingStatusId = $result['player_status_id'];
            $sleepDays = $result['sleep_days'];
    
            // Calculate the sleep end date based on the current date and sleep days
            $sleepEndDate = date('Y-m-d', strtotime("+$sleepDays days"));
    
            // Update the player's status to sleeping
            $stmt = $pdo->prepare("UPDATE players SET player_status_id = :sleeping_status_id WHERE player_uuid = :player_uuid");
            $stmt->bindParam(":sleeping_status_id", $sleepingStatusId);
            $stmt->bindParam(":player_uuid", $playerUuid);
            $stmt->execute();
    
            // Insert a new record into the players_sleeping_log table
            $stmt = $pdo->prepare("INSERT INTO players_sleeping_log (player_id, sleep_end_date, sleep_level) VALUES (:player_id, :sleep_end_date, :sleep_level)");
            $stmt->bindParam(":player_id", $playerId);
            $stmt->bindParam(":sleep_end_date", $sleepEndDate);
            $stmt->bindParam(":sleep_level", $sleepLevel);
            $stmt->execute();
    
            $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUuid, ["status" => "200", "message" => "reset_hud"]);
            if ($sendToHudResponse->getStatus() == 200) {
                return new JsonResponse(200, "Player set to sleeping successfully.");
            }
    
            return new JsonResponse(200, "Player set to sleeping successfully.");
        } catch (PDOException $e) {
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        } catch (Exception $e) {
            return new JsonResponse(500, "Internal server error: " . $e->getMessage());
        }
    }
    
    public static function leaveSleeping(PDO $pdo, $playerUuid) {
        try {
            // Get the player's species ID
            $stmt = $pdo->prepare("SELECT species_id FROM players WHERE player_uuid = :player_uuid");
            $stmt->bindParam(":player_uuid", $playerUuid);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                return new JsonResponse(404, "Player not found.");
            }
            $speciesId = $result['species_id'];
    
            // Get the "alive" status ID for the player's species
            $stmt = $pdo->prepare("SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'alive'");
            $stmt->bindParam(":species_id", $speciesId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                return new JsonResponse(400, "Alive status not found for the player's species.");
            }
            $aliveStatusId = $result['player_status_id'];
    
            // Update the player's status to "alive"
            $stmt = $pdo->prepare("UPDATE players SET player_status_id = :alive_status_id WHERE player_uuid = :player_uuid");
            $stmt->bindParam(":alive_status_id", $aliveStatusId);
            $stmt->bindParam(":player_uuid", $playerUuid);
            $stmt->execute();
    
            // Remove the row from the players_sleeping_log table
            $stmt = $pdo->prepare("DELETE FROM players_sleeping_log WHERE player_id = (SELECT player_id FROM players WHERE player_uuid = :player_uuid)");
            $stmt->bindParam(":player_uuid", $playerUuid);
            $stmt->execute();
    
            $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUuid, ["status" => "200", "message" => "reset_hud"]);
            // Check the status code of the response
            $statusCode = $sendToHudResponse->getStatus();
            if ($statusCode == 200) {
                return new JsonResponse(200, "Player set to alive successfully.");
            }
    
            return new JsonResponse(200, "Player set to alive successfully.");
        } catch (PDOException $e) {
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        } catch (Exception $e) {
            return new JsonResponse(500, "Internal server error: " . $e->getMessage());
        }
    }


    public static function getPlayerSleepDetails(PDO $pdo, $playerUUID) {
        try {
            $stmt = $pdo->prepare("SELECT sleep_end_date, sleep_level FROM players_sleeping_log WHERE player_id = (SELECT player_id FROM players WHERE player_uuid = :player_uuid) ORDER BY id DESC LIMIT 1");
            $stmt->bindParam(":player_uuid", $playerUUID);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'sleep_end_date' => $result['sleep_end_date'],
                    'sleep_level' => $result['sleep_level']
                ];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            return null;
        }
    }
}