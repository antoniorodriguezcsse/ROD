<?php

namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

///class
class PlayerDataController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Check player status including sleeping status, if they're sleeping, or alive this function
    // will return true
    //$result = BloodNexusController::validatePlayerForGame($pdo, $playerUUID, true);

    // Check player status excluding sleeping status, if they are alive, only, this function will return true.
    // if they're sleeping this will return false
    //$result = BloodNexusController::validatePlayerForGame($pdo, $playerUUID);

    public static function validatePlayerForGame($pdo, $playerUUID, $checkSleeping = false)
    {
        // Check if the player exists
        if (PlayerDataController::doesPlayerExist($pdo, $playerUUID)) {
            // Get the player's status
            $playersStatus = PlayerDataController::getPlayerStatus($pdo, $playerUUID);

            // Optionally check for sleeping status
            if ($checkSleeping && $playersStatus == "sleeping") {
                //  echo new JsonResponse(200, "Player is currently sleeping.", ["player_status" => $playersStatus]);
                return true; // Return true since player is valid (alive or sleeping)
            }

            // If the player is not alive, they can't participate
            if ($playersStatus != "alive") {
                // echo new JsonResponse(200, "Player is not alive.", ["player_status" => $playersStatus]);
                return false;
            }

            // If checks pass (player is alive)
            return true;
        } else {
            echo new JsonResponse(404, "Player does not exist.");
            return false;
        }
    }

    //brings back values like "undead" or "hibernation" not "sleeping"
    public static function getPlayersCurrentStatus($pdo, $playerUUID)
    {
        $response = PlayerController::getPlayerData($pdo, $playerUUID); // Assuming this is your JSON response
        $data = json_decode($response, true);
        $extraData = $data['extra'];
        return $extraData["player_current_status"];
    }

    public static function getPlayerStatus($pdo, $playerUUID)
    {
        //  TableController::
        $jsonString = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'player_status_id', 'player_status');

        // Decode the JSON string
        $data = json_decode($jsonString, true); // Set the second parameter to true for associative array

        // Access the "player_status_keyword" key to get the value "alive"
        $playerStatusKeyword = $data['player_status_keyword'];

        // Output the result
        return $playerStatusKeyword; // Output: alive
    }

    public static function doesPlayerExist($pdo, $uuid)
    {
        try {
            // Prepare the SQL statement to check if the UUID exists
            $checkQuery = "SELECT 1 FROM players WHERE player_uuid = :uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $checkStmt->execute();

            // Check if the UUID exists
            return $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return false; // Assume UUID doesn't exist in case of an error
        }
    }

    //  Helper function to check if a human exists
    public static function doesHumanExist($pdo, $uuid)
    {
        try {
            // Prepare the SQL statement to check if the UUID exists
            $checkQuery = "SELECT 1 FROM humans WHERE human_uuid = :uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $checkStmt->execute();

            // Check if the UUID exists
            return $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return false; // Assume UUID doesn't exist in case of an error
        }
    }

    public static function getPlayerEssenceCount($pdo, $uuid)
    {
        return TableController::getPlayerFieldValue($pdo, $uuid, "player_essence");
    }

    //The code above is the same as the code below, but the code below is more readable and easier to understand. and is not using getplayerfieldvalue.
    // Helper function to get the player's essence
    public static function getPlayerEssence($pdo, $playerUUID)
    {
        try {
            // Prepare the SQL query to select player_essence from the players table
            $query = $pdo->prepare("SELECT player_essence FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['player_essence'];
            } else {
                return null; // Player not found or essence not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }
    public static function updatePlayerEssence($pdo, $playerUUID, $newEssence)
    {
        try {
            // Prepare the SQL query to update player_essence in the players table
            $query = $pdo->prepare("UPDATE players SET player_essence = :newEssence WHERE player_uuid = :playerUUID");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->bindParam(':newEssence', $newEssence, PDO::PARAM_INT);
            $query->execute();

            // Check if any rows were affected
            if ($query->rowCount() > 0) {
                return "Player essence updated successfully.";
            } else {
                return "No update made. Player not found or essence unchanged.";
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return "Error updating player essence: " . $e->getMessage();
        }
    }

    public static function getPlayerSpecies($pdo, $playerUUID)
    {
        return self::getSpeciesTypeById($pdo, self::getPlayerSpeciesId($pdo, $playerUUID));
    }
    // Helper function to get the player's species ID
    public static function getPlayerSpeciesId($pdo, $playerUUID)
    {
        try {
            $query = $pdo->prepare("SELECT species_id FROM players WHERE player_uuid = ?");
            $query->execute([$playerUUID]);
            return $query->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // Helper function to get the species type by ID
    public static function getSpeciesTypeById($pdo, $speciesId)
    {
        try {
            // Prepare the SQL query to select species_type from the species table
            $query = $pdo->prepare("SELECT species_type FROM species WHERE species_id = :speciesId LIMIT 1");
            $query->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['species_type'];
            } else {
                return null; // Species not found
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

    // Helper function to get player_id by UUID
    public static function getPlayerIdByUUID($pdo, $uuid)
    {
        $query = $pdo->prepare("SELECT player_id FROM players WHERE player_uuid = :uuid");
        $query->execute([':uuid' => $uuid]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['player_id'] : null;

    }

    // This function retrieves the status ID for a player using their UUID
    public static function getPlayerStatusId($pdo, $playerUUID)
    {
        try {
            $query = $pdo->prepare("SELECT player_status_id FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->execute([':playerUUID' => $playerUUID]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['player_status_id'] : false;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

// This function retrieves the player status keyword and current status using the status ID
    public static function getPlayerStatusById($pdo, $statusId)
    {
        try {
            $query = $pdo->prepare("SELECT player_status_keyword, player_current_status FROM player_status WHERE player_status_id = :statusId LIMIT 1");
            $query->execute([':statusId' => $statusId]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return [
                    'status' => 200,
                    'player_status_keyword' => $result['player_status_keyword'],
                    'player_current_status' => $result['player_current_status'],
                ];
            } else {
                return ['status' => 404, 'message' => "Status not found."];
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return ['status' => 500, 'message' => "Internal server error."];
        }
    }
// This function retrieves the player's legacy name using their UUID
    public static function getPlayerLegacyName($pdo, $playerUUID)
    {
        try {
            // Prepare the SQL query to select legacy_name from the players table
            $query = $pdo->prepare("SELECT legacy_name FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['legacy_name'];
            } else {
                return null; // Player not found or legacy_name not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

    // This function retrieves the player's legacy name using their ID
    public static function getPlayerLegacyNameByPlayerID($pdo, $playerID)
    {
        try {
            // Prepare the SQL query to select legacy_name from the players table
            $query = $pdo->prepare("SELECT legacy_name FROM players WHERE player_id= :playerID LIMIT 1");
            $query->bindParam(':playerID', $playerID, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['legacy_name'];
            } else {
                return null; // Player not found or legacy_name not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

// Checks if a human with the given UUID exists in the database. If not, adds a new record for the human.
    public static function addHumanIfNotExists($pdo, $uuid)
    {
        try {
            // Prepare and execute a query to check if the human exists
            $checkQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();

            // If the human doesn't exist, insert a new record
            if ($stmt->fetchColumn() == 0) {
                $insertQuery = "INSERT INTO humans (human_uuid) VALUES (:uuid)";
                $stmtInsert = $pdo->prepare($insertQuery);
                $stmtInsert->bindParam(':uuid', $uuid, PDO::PARAM_STR);
                $stmtInsert->execute();
            }
            return true; // Human exists or has been successfully added
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false; // Return false in case of an error
        }
    }

    public static function getPlayerCurrentHealth($pdo, $playerUUID)
    {
        return TableController::getPlayerFieldValue($pdo, $playerUUID, "player_current_health");
    }

    public static function getPlayerMaxHealth($pdo, $playerUUID)
    {
        try {
            // Call the getForeignKeyData function
            $result = TableController::getForeignKeyData($pdo, 'players', 'player_uuid', $playerUUID, 'player_age_group_id', 'player_age_group');
            $resultArray = json_decode($result, true);

            if (isset($resultArray['error'])) {
                // Handle the error, for example, by echoing the error message
                return "404 error";
            } else {
                // Access the 'max_health' value from the result
                return $resultArray['max_health'];
            }
        } catch (PDOException $e) {
            // Handle database errors
            echo "Error: " . $e->getMessage();
        }
    }

    public static function setPlayerHealth($pdo, $uuid, $newHealth)
    {
        if (self::doesPlayerExist($pdo, $uuid)) {
            $maxHealth = self::getPlayerMaxHealth($pdo, $uuid);

            // Ensure the new health value doesn't go below 0 or above maxHealth
            $newHealth = min(max($newHealth, 0), $maxHealth);

            $updateQuery = "UPDATE players SET player_current_health = :newHealth WHERE player_uuid = :uuid";
            $stmtUpdate = $pdo->prepare($updateQuery);
            $stmtUpdate->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':newHealth', $newHealth, PDO::PARAM_STR);
            $stmtUpdate->execute();
        } else {

        }
    }

    public static function getPlayerFollowingId($pdo, $playerUUID)
    {
        try {
            // Prepare the SQL query to select player_following_id from the players table
            $query = $pdo->prepare("SELECT player_following_id FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['player_following_id'];
            } else {
                return null; // Player not found or following_id not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

    public static function getPlayerUuidById($pdo, $playerId)
    {
        try {
            // Prepare the SQL query to select player_uuid from the players table
            $query = $pdo->prepare("SELECT player_uuid FROM players WHERE player_id = :playerId LIMIT 1");
            $query->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['player_uuid'];
            } else {
                return null; // Player not found
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }
    public static function getHumanKillerIdByUuid($pdo, $humanUuid)
    {
        try {
            // Prepare the SQL query to select killer_player_id from the humans table
            $query = $pdo->prepare("SELECT killer_player_id FROM humans WHERE human_uuid = :humanUuid LIMIT 1");
            $query->bindParam(':humanUuid', $humanUuid, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['killer_player_id'];
            } else {
                return null; // Human not found or killer_player_id not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }
    public static function getLastDeathDetails($pdo, $playerId)
    {
        $sql = "SELECT
                    death_date,
                    killer_player_id,
                    cause_of_death
                FROM death_log
                WHERE deceased_player_id = :playerId
                ORDER BY death_date DESC
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result;
        } else {
            return null;
        }
    }

    public static function getPlayerBloodlineIdByUuid($pdo, $playerUuid)
    {
        try {
            // Prepare the SQL query to select bloodline_id from the players table
            $query = $pdo->prepare("SELECT bloodline_id FROM players WHERE player_uuid = :playerUuid LIMIT 1");
            $query->bindParam(':playerUuid', $playerUuid, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['bloodline_id'];
            } else {
                return null; // Player not found or bloodline_id not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

    // This function retrieves the player's bloodline name using their UUID
    public static function getPlayerBloodlineName(PDO $pdo, string $playerUUID)
    {
        try {
            // First, get the bloodline_id for the player
            $query = "SELECT b.bloodline_name
                  FROM players p
                  JOIN bloodlines b ON p.bloodline_id = b.bloodline_id
                  WHERE p.player_uuid = :playerUUID";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['bloodline_name'];
            } else {
                return null; // Player not found or no bloodline associated
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }
    public static function getPlayerTitle($pdo, $playerUuid)
    {
        try {
            $query = "SELECT player_title FROM players WHERE player_uuid = :playerUuid";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUuid', $playerUuid);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['player_title'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            // Handle the exception, e.g., log the error or return an error response
            return null;
        }
    }

    public static function hudAttachedDateAndTime($pdo, $playerUuid)
    {
        try {
            $query = "SELECT last_login FROM players WHERE player_uuid = :playerUuid";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUuid', $playerUuid);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['last_login'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            // Handle the exception, e.g., log the error or return an error response
            return null;
        }
    }

    /**
     * Update a player's current health in the database.
     *
     * @param PDO $pdo PDO database connection object
     * @param string $playerUUID UUID of the player
     * @param float $currentHealth Current health value to set
     * @return bool True if the player's current health was updated successfully, false otherwise
     */
    public static function updatePlayerCurrentHealth($pdo, $playerUUID, $currentHealth)
    {
        try {
            $stmt = $pdo->prepare("UPDATE players SET player_current_health = :health WHERE player_uuid = :uuid");
            $stmt->bindParam(":health", $currentHealth);
            $stmt->bindParam(":uuid", $playerUUID);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getPlayerGeneration(PDO $pdo, $playerUUID)
    {
        $query = "SELECT player_generation FROM players WHERE player_uuid = :playerUUID";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
        $stmt->execute();

        $generation = $stmt->fetchColumn();

        if ($generation === false) {
            // Handle the case when the player is not found
            throw new PDOException("Player not found.");
        }

        return (int) $generation;
    }
}
