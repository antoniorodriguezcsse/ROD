<?php
// BloodBankController.php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;

class SpeciesChangeController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    public static function getSpeciesList(PDO $pdo, $playerUUID)
    {
        try {

            $PlayerStatus = PlayerDataController::getPlayerStatus($pdo, $playerUUID);
            if($PlayerStatus == "dead")
            {
                return new JsonResponse(400, "Player is dead");
            }

            // Retrieve player ID using the provided UUID
            $playerId = self::getPlayerIdByUUID($pdo, $playerUUID);

            // Check if the player has any followers
            $directFollowerCount = self::getDirectFollowerCount($pdo, $playerId);
            if ($directFollowerCount > 0) {
                return new JsonResponse(400, "Player must have no followers to change species.");
            }

            $followingId = SpeciesChangeController::getPlayerFollowingId($pdo, $playerUUID);
            if ($followingId) {
               
                return new JsonResponse(400, "Player must not be following anyone.");
            }

            // Check if the player is in a house
            $houseId = self::getCurrentHouseID($pdo, $playerUUID);
            if ($houseId) {
                return new JsonResponse(400, "Player must not be in a house to change species.");
            }

            // Check if the player is in a house
            $houseId = self::getCurrentHouseID($pdo, $playerUUID);
            if ($houseId) {
                return new JsonResponse(400, "Player must not be in a house to change species.");
            }

            // Fetch the list of species from the database
            $stmt = $pdo->prepare("SELECT species_id, species_type FROM species");
            $stmt->execute();
            $speciesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($speciesList)) {
                return new JsonResponse(200, "Species list fetched successfully", $speciesList);
            } else {
                return new JsonResponse(404, "No species found");
            }
        } catch (Exception $e) {
            error_log("Error fetching species list: " . $e->getMessage());
            return new JsonResponse(500, "Error fetching species list: " . $e->getMessage());
        }
    }

    public static function changePlayerSpecies(PDO $pdo, $playerUUID, $newSpeciesId)
    {
        try {
            // Start a database transaction
            $pdo->beginTransaction();

            // Fetch the current species ID of the player
            $playerSpeciesId = self::getPlayerSpeciesId($pdo, $playerUUID);

            // Check if the species are the same
            if ($playerSpeciesId == $newSpeciesId) {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "Player is already of this species."];
            }

            // Fetch the player's ID
            $playerId = self::getPlayerIdByUUID($pdo, $playerUUID);

            // Check if the player has any followers
            $directFollowerCount = self::getDirectFollowerCount($pdo, $playerId);
            if ($directFollowerCount) {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "Player must have no followers to change species."];
            }

            $followingId = SpeciesChangeController::getPlayerFollowingId($pdo, $playerUUID);
            if ($followingId) {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "Player must not be following anyone."];
            }

            // Check if the player is in a house
            $houseId = self::getCurrentHouseID($pdo, $playerUUID);
            if ($houseId) {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "Player must not be in a house to change species."];
            }

            // Get the new age group ID for the new species
            $newAgeGroupId = self::getNewAgeGroupIdForSpecies($pdo, $playerUUID, $newSpeciesId);
            if (!$newAgeGroupId) {
                $pdo->rollBack();
                return ['status' => 500, 'message' => 'Failed to determine new age group for species.'];
            }

            // Get the bloodline leader's ID and bloodline ID for the new species
            $lineLeaderId = self::getBloodlineLeaderIdForSpecies($pdo, $newSpeciesId);
            $bloodlineId = self::getBloodlineIdBySpecies($pdo, $newSpeciesId);
            if (!$lineLeaderId || !$bloodlineId) {
                $pdo->rollBack();
                return ['status' => 500, 'message' => 'Failed to determine line leader or bloodline for new species.'];
            }

            // Get the line leader's generation and increment it for the player
            $lineLeaderGeneration = self::getPlayerGenerationById($pdo, $lineLeaderId);
            if ($lineLeaderGeneration === null) {
                $pdo->rollBack();
                return ['status' => 500, 'message' => 'Failed to retrieve line leader generation.'];
            }
            $newPlayerGeneration = $lineLeaderGeneration + 1;

            // Update the player's species_id, player_age_group_id, sire_id, bloodline_id, and player_generation
            $updateSpeciesQuery = $pdo->prepare(
                "UPDATE players
             SET species_id = :newSpeciesId,
                 player_age_group_id = :newAgeGroupId,
                 sire_id = :lineLeaderId,
                 bloodline_id = :bloodlineId,
                 player_generation = :newPlayerGeneration
             WHERE player_uuid = :playerUUID"
            );
            $updateSpeciesQuery->execute([
                ':newSpeciesId' => $newSpeciesId,
                ':newAgeGroupId' => $newAgeGroupId,
                ':lineLeaderId' => $lineLeaderId,
                ':bloodlineId' => $bloodlineId,
                ':newPlayerGeneration' => $newPlayerGeneration,
                ':playerUUID' => $playerUUID,
            ]);

            // Check if the update was successful
            if ($updateSpeciesQuery->rowCount() == 0) {
                $pdo->rollBack();
                return ['status' => 400, 'message' => 'Failed to update player details for species change.'];
            }

            // Update the player's status to 'alive' (or another appropriate status)
            $statusUpdated = self::updatePlayerStatus($pdo, $playerUUID, "alive", false);

            if (!$statusUpdated) {
                $pdo->rollBack();
                return ['status' => 500, 'message' => 'Failed to update player status.'];
            }

            // Commit the transaction
            $pdo->commit();
            
           $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "reset_hud"]);
           // Check the response from the HUD update
            if ($sendToHudResponse->getStatus() == 200) {
                // HUD update was successful
                return ['status' => 200, 'message' => 'Player species, sire, bloodline, generation, and status changed successfully.'];
            } else {
                // HUD update failed, handle accordingly
                // Depending on your application's needs, you might log this or take different actions
                error_log("Failed to update player's HUD: " . $sendToHudResponse->getMessage());
                return ['status' => 500, 'message' => 'Player species changed successfully, but failed to update HUD.'];
            }

            //return ['status' => 200, 'message' => 'Player species, sire, bloodline, generation, and status changed successfully.'];

        } catch (PDOException $e) {
            $pdo->rollBack();
            return ['status' => 500, 'message' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            $pdo->rollBack();
            return ['status' => 500, 'message' => 'Operation failed: ' . $e->getMessage()];
        }
    }

    public static function getNewAgeGroupIdForSpecies(PDO $pdo, $playerUUID, $newSpeciesId)
    {
        try {
            // Fetch the player's age
            $ageQuery = $pdo->prepare("SELECT player_age FROM players WHERE player_uuid = :playerUUID");
            $ageQuery->execute([':playerUUID' => $playerUUID]);
            $ageResult = $ageQuery->fetch(PDO::FETCH_ASSOC);

            if (!$ageResult) {
                error_log("No age found for player UUID: $playerUUID");
                return null;
            }

            $playerAge = $ageResult['player_age'];

            // Query to select the most suitable age group ID for the new species based on player's age
            $query = $pdo->prepare("
                SELECT player_age_group_id
                FROM player_age_group
                WHERE species_id = :newSpeciesId AND age_group_required_age <= :playerAge
                ORDER BY age_group_required_age DESC
                LIMIT 1
            ");

            $query->execute([':newSpeciesId' => $newSpeciesId, ':playerAge' => $playerAge]);
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // If a result is found, return the age group ID
            if ($result) {
                return $result['player_age_group_id'];
            } else {
                // If no result is found, it could mean there's no suitable age group for the player's age in the new species
                error_log("No suitable age group found for species ID: $newSpeciesId with player age: $playerAge");
                return null;
            }
        } catch (PDOException $e) {
            // Log any error for debugging purposes
            error_log("Database Error in getNewAgeGroupIdForSpecies: " . $e->getMessage());
            return null;
        }
    }

    // Helper function to update the player status
    public static function updatePlayerStatus($pdo, $uuid, $statusKeyword, $useTransaction = true)
    {
        try {
            if ($useTransaction) {
                // Begin transaction only if $useTransaction is true
                $pdo->beginTransaction();
            }

            // Retrieve the species of the player
            $speciesQuery = "SELECT species_id FROM players WHERE player_uuid = :uuid";
            $stmtSpecies = $pdo->prepare($speciesQuery);
            $stmtSpecies->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtSpecies->execute();

            $speciesResult = $stmtSpecies->fetch(PDO::FETCH_ASSOC);
            if (!$speciesResult) {
                error_log("Player with UUID $uuid not found");
                if ($useTransaction) {
                    $pdo->rollBack();
                }
                return false;
            }
            $speciesId = $speciesResult['species_id'];

            // Debugging
            error_log("Updating status for UUID: $uuid, Status Keyword: $statusKeyword, Species ID: $speciesId");

            // SQL query to update player status
            $updateStatusQuery = "UPDATE players
                                  SET player_status_id = (
                                      SELECT player_status_id
                                      FROM player_status
                                      WHERE player_status_keyword = :statusKeyword AND species_id = :speciesId
                                  )
                                  WHERE player_uuid = :uuid";

            $stmtUpdateStatus = $pdo->prepare($updateStatusQuery);
            $stmtUpdateStatus->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtUpdateStatus->bindParam(':statusKeyword', $statusKeyword, PDO::PARAM_STR);
            $stmtUpdateStatus->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $stmtUpdateStatus->execute();

            $affectedRows = $stmtUpdateStatus->rowCount();
            if ($affectedRows > 0) {
                error_log("Updated $affectedRows row(s) successfully");
            } else {
                error_log("No rows updated for UUID $uuid. This might be expected if the status is already set.");
            }

            if ($useTransaction) {
                $pdo->commit();
            }

            return $affectedRows > 0;
        } catch (PDOException $e) {
            error_log("PDOException in updatePlayerStatus: " . $e->getMessage());
            if ($useTransaction) {
                $pdo->rollBack();
            }
            return false;
        }
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

    // Helper function to get player_id by UUID
    public static function getPlayerIdByUUID($pdo, $uuid)
    {
        $query = $pdo->prepare("SELECT player_id FROM players WHERE player_uuid = :uuid");
        $query->execute([':uuid' => $uuid]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['player_id'] : null;

    }
    // Helper function to get the player's follower count
    public static function getDirectFollowerCount($pdo, $playerId)
    {
        try {
            // Prepare the SQL to count the direct followers of the player
            $followersQuery = $pdo->prepare(
                "SELECT COUNT(*) FROM players WHERE player_following_id = :playerId"
            );
            $followersQuery->execute([':playerId' => $playerId]);

            // Fetch the count result
            $followerCount = $followersQuery->fetchColumn();

            return $followerCount; // Return the count of direct followers
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false; // Return false to indicate a failure
        }
    }

    // Helper function to get the player's house ID
    public static function getCurrentHouseID($pdo, $playerUUID)
    {
        try {
            $query = $pdo->prepare("SELECT house_id FROM players WHERE player_uuid = :playerUUID");
            $query->execute([':playerUUID' => $playerUUID]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['house_id'] : null;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the bloodline leader's ID for a specific species.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param int $speciesId The ID of the species for which to find the bloodline leader.
     * @return mixed The bloodline leader's ID if found, null if not found, or false on error.
     */
    public static function getBloodlineLeaderIdForSpecies(PDO $pdo, $speciesId)
    {
        try {
            $stmt = $pdo->prepare("SELECT bloodline_leader_id FROM bloodlines WHERE species_id = :speciesId LIMIT 1");
            $stmt->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['bloodline_leader_id'] : null;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false; // Indicate an error occurred.
        }
    }

    /**
     * Sets the sire_id for a given player.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param string $playerUUID The UUID of the player.
     * @param int $sireId The ID of the new sire.
     * @return bool True on success, false on failure.
     */
    public static function setPlayerSireId(PDO $pdo, $playerUUID, $sireId)
    {
        try {
            $stmt = $pdo->prepare("UPDATE players SET sire_id = :sireId WHERE player_uuid = :playerUUID");
            $stmt->bindParam(':sireId', $sireId, PDO::PARAM_INT);
            $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return false; // Indicate an error occurred.
        }
    }

    /**
     * Fetches the bloodline ID associated with a given species ID.
     *
     * @param PDO $pdo The PDO connection object.
     * @param int $speciesId The ID of the species for which the bloodline ID is required.
     *
     * @return int|null Returns the bloodline ID if found, otherwise returns null.
     *                  Returns null also in case of any database error.
     */
    public static function getBloodlineIdBySpecies(PDO $pdo, $speciesId)
    {
        try {
            $stmt = $pdo->prepare("SELECT bloodline_id FROM bloodlines WHERE species_id = :speciesId");
            $stmt->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['bloodline_id'])) {
                return $result['bloodline_id'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            error_log("Database Error in getBloodlineIdBySpecies: " . $e->getMessage());
            return null;
        }
    }
/**
 * Retrieves the player generation for a given player ID.
 *
 * @param PDO $pdo The PDO connection object.
 * @param int $playerId The ID of the player whose generation is to be retrieved.
 *
 * @return int|null Returns the player generation if found, otherwise returns null.
 *                  Returns null also in case of any database error.
 */
    public static function getPlayerGenerationById(PDO $pdo, $playerId)
    {
        try {
            $stmt = $pdo->prepare("SELECT player_generation FROM players WHERE player_id = :playerId");
            $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['player_generation'])) {
                return $result['player_generation'];
            } else {
                return null;
            }
        } catch (PDOException $e) {
            error_log("Database Error in getPlayerGenerationById: " . $e->getMessage());
            return null;
        }
    }
    public static function getPlayerFollowingId(PDO $pdo, $playerUUID)
    {
        try {
            $query = $pdo->prepare("SELECT player_following_id FROM players WHERE player_uuid = :playerUUID");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['player_following_id'])) {
                return $result['player_following_id'];
            } else {
                // Return null if no following ID found or player does not exist
                return null;
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            // Return null in case of an error
            return null;
        }
    }

}
