<?php
namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;
use Exception;

class ActivityController
{
    public static function logActivity($pdo, $playerUUID, $activityName) {
        try {
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            if (!$playerId) {
                return new JsonResponse(404, "Player not found.");
            }

            $activityId = self::getActivityIdByName($pdo, $activityName);
            if (!$activityId) {
                return new JsonResponse(404, "Activity not found.");
            }

            if (self::isActivityLoggedToday($pdo, $playerId, $activityId)) {
                return new JsonResponse(409, "Activity already logged for today.");
            }

            self::insertActivityLog($pdo, $playerId, $activityId);

            return new JsonResponse(200, "Activity logged successfully.");
        } catch (PDOException $e) {
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        } catch (Exception $e) {
            return new JsonResponse(500, "Server Error: " . $e->getMessage());
        }
    }

    private static function getActivityIdByName($pdo, $activityName) {
        try {
            $query = $pdo->prepare("SELECT activity_id FROM player_activities WHERE name = :activityName");
            $query->bindParam(':activityName', $activityName, PDO::PARAM_STR);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['activity_id'] : null;
        } catch (PDOException $e) {
            throw new Exception("Error retrieving activity ID.");
        }
    }

    private static function isActivityLoggedToday($pdo, $playerId, $activityId) {
        try {
            $query = $pdo->prepare("SELECT COUNT(*) FROM player_activity_logs WHERE player_id = :playerId AND activity_id = :activityId AND DATE(date) = CURDATE()");
            $query->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $query->bindParam(':activityId', $activityId, PDO::PARAM_INT);
            $query->execute();
            $count = $query->fetchColumn();

            return $count > 0;
        } catch (PDOException $e) {
            throw new Exception("Error checking activity log.");
        }
    }

    private static function insertActivityLog($pdo, $playerId, $activityId) {
        try {
            $query = $pdo->prepare("INSERT INTO player_activity_logs (player_id, activity_id, date) VALUES (:playerId, :activityId, NOW())");
            $query->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $query->bindParam(':activityId', $activityId, PDO::PARAM_INT);
            $query->execute();
        } catch (PDOException $e) {
            throw new Exception("Error inserting activity log.");
        }
    }

    // Additional static methods as needed
}