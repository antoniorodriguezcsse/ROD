<?php

namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

///class
class HouseHelperController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getCurrentHouseRole($pdo, $playerUUID)
    {
        try {
            $query = $pdo->prepare("SELECT player_role_house_keyword FROM players JOIN player_role_house ON players.house_role_id = player_role_house.house_role_id WHERE player_uuid = :playerUUID");
            $query->execute([':playerUUID' => $playerUUID]);

            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['player_role_house_keyword'] : null;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    public static function getHouseIdByPlayerUUID(PDO $pdo, $playerUUID)
    {
        try {
            // Prepare the SQL statement
            $stmt = $pdo->prepare("SELECT house_id FROM players WHERE player_uuid = :playerUUID");

            // Bind the player UUID to the prepared statement
            $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);

            // Execute the statement
            if (!$stmt->execute()) {
                // Handle execution error
                throw new Exception("Execution failed: " . implode(", ", $stmt->errorInfo()));
            }

            // Fetch the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if result is found
            if ($result) {
                return $result['house_id'];
            } else {
                return null; // No result found for the given UUID
            }
        } catch (PDOException $e) {
            // Handle PDO specific error
            die("PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            // Handle general errors
            die("Error: " . $e->getMessage());
        }
    }

    // Update the clan ID for a house
    public static function updateHouseClanId($pdo, $houseId, $clanId) {
        try {
            $updateQuery = $pdo->prepare("UPDATE houses SET clan_id = :clanId WHERE house_id = :houseId");
            $updateQuery->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $updateQuery->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $updateQuery->execute();

            return $updateQuery->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // Remove the clan association for a house
    public static function removeClanAssociation($pdo, $houseId) {
        try {
            $updateQuery = $pdo->prepare("UPDATE houses SET clan_id = NULL WHERE house_id = :houseId");
            $updateQuery->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $updateQuery->execute();

            return $updateQuery->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }
    // Get the clan ID for a house
    public static function getCurrentHouseClanId($pdo, $houseId) {
        try {
            $query = $pdo->prepare("SELECT clan_id FROM houses WHERE house_id = :houseId");
            $query->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['clan_id'] : null;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // Helper function to get the UUIDs of all members of a given house
    public static function getAllHouseMembersUuids($pdo, $houseID) {
        try {
            // Prepare the SQL query to select player_uuid from players where house_id matches
            $query = $pdo->prepare("SELECT player_uuid FROM players WHERE house_id = :houseID");
            $query->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $query->execute();

            // Fetch all the results
            $results = $query->fetchAll(PDO::FETCH_ASSOC);

            // Extract the player_uuids from the results
            $memberUUIDs = array_map(function($item) {
                return $item['player_uuid'];
            }, $results);
            
            // Log the member UUIDs to the error log
            error_log("Member UUIDs for house " . $houseID . ": " . implode(", ", $memberUUIDs));

            return $memberUUIDs;
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error in getHouseMembers: " . $e->getMessage());
            return []; // Return an empty array in case of an error
        }
    }




}