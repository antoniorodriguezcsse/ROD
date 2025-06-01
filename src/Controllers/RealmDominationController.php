<?php

namespace Fallen\SecondLife\Controllers;

use PDO;
use PDOException;
use Fallen\SecondLife\Classes\JsonResponse;

class RealmDominationController
{
    // Function to handle planting a flag in a region for "Realm Domination" game mode
    
    public static function plantFlag(PDO $pdo, $regionName, $playerUUID) {
        try {
            // Get the player's integer ID based on UUID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);

            if ($playerId === null) {
                return new JsonResponse(404, "Player not found.");
            }

            // Get the clan ID and house ID associated with the player
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);
            $houseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUUID);

            // Check if the region is already being captured
            $regionStatus = self::checkRegionStatus($pdo, $regionName);
            if ($regionStatus && $regionStatus['is_flag_planted']) {
                return new JsonResponse(409, "Region is already being captured.");
            }

            // Plant the flag in the region
            $success = self::initiateRegionCapture($pdo, $regionStatus['region_id'], $houseId, $clanId, $playerId);
            if ($success) {
                return new JsonResponse(200, "Flag planted successfully, capture process initiated.");
            } else {
                return new JsonResponse(500, "Failed to plant the flag in the region.");
            }

        } catch (PDOException $e) {
            error_log("Database Error in plantFlag: " . $e->getMessage());
            return new JsonResponse(500, "Server error occurred: " . $e->getMessage());
        }
    }

    // Method to check the current status of a region
    public static function checkRegionStatus(PDO $pdo, $regionName) {
        try {
            $query = $pdo->prepare("
                SELECT rp.region_id, rp.is_flag_planted 
                FROM game_mode_realm_dom_regions r
                LEFT JOIN game_mode_realm_dom_region_points rp ON r.region_id = rp.region_id
                WHERE r.region_name = :regionName
                LIMIT 1
            ");
            $query->bindParam(':regionName', $regionName, PDO::PARAM_STR);
            $query->execute();

            return $query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database Error in checkRegionStatus: " . $e->getMessage());
            return null;
        }
    }

    private static function initiateRegionCapture(PDO $pdo, $regionId, $houseId, $clanId, $playerId) {
        try {
            $query = $pdo->prepare("
                INSERT INTO game_mode_realm_dom_region_points 
                (region_id, house_id, clan_id, points, last_updated, is_flag_planted, flag_planter_id) 
                VALUES (:regionId, :houseId, :clanId, 1, NOW(), TRUE, :playerId)
                ON DUPLICATE KEY UPDATE 
                points = points + 1, last_updated = NOW(), is_flag_planted = TRUE, flag_planter_id = :playerId
            ");
            $query->bindParam(':regionId', $regionId, PDO::PARAM_INT);
            $query->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $query->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $query->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $query->execute();
    
            return $query->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Database Error in initiateRegionCapture: " . $e->getMessage());
            return false;
        }
    }

    // Method to update points for a house or clan in a region
    public static function updatePoints(PDO $pdo, $regionId, $houseId, $clanId, $points)
    {
        try {
            // Implement the logic to update points
        } catch (PDOException $e) {
            return self::handleException($e);
        }
    }

    

    // Method to reset points for a region (e.g., at the end of a cycle or after a region is captured)
    public static function resetRegionPoints(PDO $pdo, $regionId)
    {
        try {
            // Reset points logic
        } catch (PDOException $e) {
            return self::handleException($e);
        }
    }

    // Additional methods to support other functionalities of the game mode...

    // Method to handle exceptions and return JSON responses
    private static function handleException(PDOException $e)
    {
        // Log the exception, and return an appropriate JSON response
        error_log("Database Error: " . $e->getMessage());
        return new JsonResponse(500, "Server error occurred: " . $e->getMessage());
    }
}
