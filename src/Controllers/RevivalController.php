<?php

namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;

///class
class RevivalController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getReviverPlayerInfo($pdo, $playerUUID)
    {
        try
        {
            // Check if player exists
            if (PlayerDataController::doesPlayerExist($pdo, $playerUUID))
            {
                // Get player status ID
                $statusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
                if ($statusId !== false)
                {
                    // Get player status using the status ID
                    $statusInfo = PlayerDataController::getPlayerStatusById($pdo, $statusId);
                    if ($statusInfo['status'] === 200)
                    {
                        // Get player essence
                        $playerEssence = PlayerDataController::getPlayerEssence($pdo, $playerUUID);
                        if ($playerEssence !== null)
                        {
                            echo new JsonResponse(200, "Player information retrieved successfully", [
                                "player_exists" => 1,
                                "player_status_keyword" => $statusInfo['player_status_keyword'],
                                "player_current_status" => $statusInfo['player_current_status'],
                                "player_essence" => $playerEssence,
                            ]);
                        } else
                        {
                            echo new JsonResponse(404, "Player essence not found.");
                        }
                    } else
                    {
                        echo new JsonResponse($statusInfo['status'], $statusInfo['message']);
                    }
                } else
                {
                    echo new JsonResponse(404, "Player status ID not found.");
                }
            } else
            {
                echo new JsonResponse(404, "Player not found.");
            }
        }
        catch (PDOException $e)
        {
            // Handle database error in checking player existence
            echo new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    public static function getDeadPlayerInfo($pdo, $playerUUID)
    {
        try
        {
            // Check if player exists
            if (PlayerDataController::doesPlayerExist($pdo, $playerUUID))
            {
                // Get player status ID
                $statusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
                if ($statusId !== false)
                {
                    // Get player status using the status ID
                    $statusInfo = PlayerDataController::getPlayerStatusById($pdo, $statusId);
                    if ($statusInfo['status'] === 200)
                    {
                        echo new JsonResponse(200, "Player information retrieved successfully", [
                            "player_exists" => 1,
                            "player_status_keyword" => $statusInfo['player_status_keyword'],
                            "player_current_status" => $statusInfo['player_current_status'],
                        ]);
                    } else
                    {
                        echo new JsonResponse($statusInfo['status'], $statusInfo['message']);
                    }
                } else
                {
                    echo new JsonResponse(404, "Player status ID not found.");
                }
            } else
            {
                echo new JsonResponse(404, "Player not found.");
            }
        }
        catch (PDOException $e)
        {
            // Handle database error in checking player existence
            echo new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    // ==============================

    public static function revivePlayer($pdo, $playerUuid, $revivalMethod = null)
    {
        try
        {

            // ==============================
            // STEP 2: VALIDATE PLAYER EXISTENCE
            // Ensure that the player with the provided UUID exists in the database.
            // ==============================
            $existsQuery = "SELECT * FROM players WHERE player_uuid = :player_uuid";
            $existsStmt = $pdo->prepare($existsQuery);
            $existsStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            $existsStmt->execute();

            if ($existsStmt->rowCount() == 0)
            {
                return new JsonResponse(404, "Player not found.");
            }

            $playerData = $existsStmt->fetch(PDO::FETCH_ASSOC);

            // ==============================
            // STEP 3: CHECK PLAYER STATUS
            // Ensure that the player is in a 'dead' status before attempting revival.
            // ==============================
            $currentStatusId = $playerData['player_status_id'];
            $speciesId = $playerData['species_id'];

            $isDeadQuery = "SELECT 1 FROM player_status WHERE player_status_id = :current_status_id AND species_id = :species_id
            AND (player_status_keyword = 'dead' OR player_status_keyword = 'dead from hunger')";
            $isDeadStmt = $pdo->prepare($isDeadQuery);
            $isDeadStmt->bindParam(':current_status_id', $currentStatusId, PDO::PARAM_INT);
            $isDeadStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
            $isDeadStmt->execute();

            if ($isDeadStmt->rowCount() == 0)
            {
                return new JsonResponse(409, "Player is not dead and cannot be revived.");
            }

            // ==============================
            // STEP 4: RETRIEVE MAX HEALTH
            // Determine the maximum health for the player based on their age group.
            // ==============================
            $ageGroupId = $playerData['player_age_group_id'];

            // Debug: Check if ageGroupId is null or empty
            if (empty($ageGroupId))
            {
                return new JsonResponse(500, "Age group ID is empty or null.");
            }

            $maxHealthQuery = "SELECT max_health FROM player_age_group WHERE player_age_group_id = :age_group_id";
            $maxHealthStmt = $pdo->prepare($maxHealthQuery);
            $maxHealthStmt->bindParam(':age_group_id', $ageGroupId, PDO::PARAM_INT);
            $executionStatus = $maxHealthStmt->execute();

            // Debug: Check if PDO statement execution is successful
            if (!$executionStatus)
            {
                error_log("Error in maxHealthQuery: " . implode(", ", $maxHealthStmt->errorInfo()));
                return new JsonResponse(500, "Database query error: failed to fetch max health.");
            }

            if ($maxHealthStmt->rowCount() == 0)
            {
                return new JsonResponse(404, "Age group not found.");
            }

            $maxHealthData = $maxHealthStmt->fetch(PDO::FETCH_ASSOC);

            // Debug: Check if max_health is fetched correctly
            if (!isset($maxHealthData['max_health']))
            {
                return new JsonResponse(500, "max_health is not set in fetched data.");
            }

            $maxHealth = $maxHealthData['max_health'];

            // ==============================
            // STEP 5: UPDATE PLAYER STATUS
            // Modify the player’s status to 'alive' and update their health to the max for their age group.
            // ==============================
            $aliveStatusIdQuery = "SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'alive'";
            $aliveStatusIdStmt = $pdo->prepare($aliveStatusIdQuery);
            $aliveStatusIdStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
            $aliveStatusIdStmt->execute();

            if ($aliveStatusIdStmt->rowCount() == 0)
            {
                return new JsonResponse(500, "Alive status ID not found for species.");
            }

            $aliveStatusData = $aliveStatusIdStmt->fetch(PDO::FETCH_ASSOC);
            $aliveStatusId = $aliveStatusData['player_status_id'];

            $reviveQuery = "UPDATE players SET player_status_id = :alive_status_id, player_current_health = :max_health WHERE player_uuid = :player_uuid";
            $reviveStmt = $pdo->prepare($reviveQuery);
            $reviveStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            $reviveStmt->bindParam(':max_health', $maxHealth, PDO::PARAM_STR);
            $reviveStmt->bindParam(':alive_status_id', $aliveStatusId, PDO::PARAM_INT);
            $reviveStmt->execute();


            // For revival notice in discord
            if ($revivalMethod == "potion")
            {
                self::sendRevivalNotification($pdo, $playerUuid, null, 'potion');
            }


            return new JsonResponse(200, "Player revived successfully.");

            // ==============================
            // STEP 6: ERROR HANDLING
            // Catch any potential exceptions and handle database errors.
            // ==============================
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    // ==============================

    public static function revivePlayerAltar($pdo, $deadPlayerUuid, $reviverUuid)
    {
        try
        {
            $pdo->beginTransaction();

            // ==============================
            // STEP 1: VALIDATE REVIVING PLAYER
            // Ensure that the reviving player exists and has enough essence.
            // ==============================
            $reviverQuery = "SELECT player_essence FROM players WHERE player_uuid = :reviver_uuid";
            $reviverStmt = $pdo->prepare($reviverQuery);
            $reviverStmt->bindParam(':reviver_uuid', $reviverUuid, PDO::PARAM_STR);
            $reviverStmt->execute();

            if ($reviverStmt->rowCount() == 0)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Reviving player not found.");
            }

            $reviverData = $reviverStmt->fetch(PDO::FETCH_ASSOC);
            if ($reviverData['player_essence'] < 2)
            {
                $pdo->rollBack();
                return new JsonResponse(409, "Insufficient player essence to perform revival.");
            }

            // Deduct essence from reviving player
            $updateEssenceQuery = "UPDATE players SET player_essence = player_essence - 2 WHERE player_uuid = :reviver_uuid";
            $updateEssenceStmt = $pdo->prepare($updateEssenceQuery);
            $updateEssenceStmt->bindParam(':reviver_uuid', $reviverUuid, PDO::PARAM_STR);
            $updateEssenceStmt->execute();

            // ==============================
            // STEP 2: VALIDATE DEAD PLAYER
            // Ensure that the dead player exists.
            // ==============================
            $existsQuery = "SELECT * FROM players WHERE player_uuid = :player_uuid";
            $existsStmt = $pdo->prepare($existsQuery);
            $existsStmt->bindParam(':player_uuid', $deadPlayerUuid, PDO::PARAM_STR);
            $existsStmt->execute();

            if ($existsStmt->rowCount() == 0)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Dead player not found.");
            }

            $playerData = $existsStmt->fetch(PDO::FETCH_ASSOC);

            // ==============================
            // STEP 3: CHECK DEAD PLAYER STATUS
            // Ensure the player is marked as 'dead'.
            // ==============================
            $currentStatusId = $playerData['player_status_id'];
            $speciesId = $playerData['species_id'];

            $isDeadQuery = "SELECT 1 FROM player_status WHERE player_status_id = :current_status_id AND species_id = :species_id
            AND (player_status_keyword = 'dead' OR player_status_keyword = 'dead from hunger')";
            $isDeadStmt = $pdo->prepare($isDeadQuery);
            $isDeadStmt->bindParam(':current_status_id', $currentStatusId, PDO::PARAM_INT);
            $isDeadStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
            $isDeadStmt->execute();

            if ($isDeadStmt->rowCount() == 0)
            {
                $pdo->rollBack();
                return new JsonResponse(409, "Player is not dead and cannot be revived.");
            }

            // ==============================
            // STEP 4: RETRIEVE MAX HEALTH
            // Determine the maximum health for the player based on their age group.
            // ==============================
            $ageGroupId = $playerData['player_age_group_id'];
            if (empty($ageGroupId))
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Age group ID is empty or null.");
            }

            $maxHealthQuery = "SELECT max_health FROM player_age_group WHERE player_age_group_id = :age_group_id";
            $maxHealthStmt = $pdo->prepare($maxHealthQuery);
            $maxHealthStmt->bindParam(':age_group_id', $ageGroupId, PDO::PARAM_INT);
            if (!$maxHealthStmt->execute())
            {
                error_log("Error in maxHealthQuery: " . implode(", ", $maxHealthStmt->errorInfo()));
                $pdo->rollBack();
                return new JsonResponse(500, "Database query error: failed to fetch max health.");
            }

            if ($maxHealthStmt->rowCount() == 0)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Age group not found.");
            }

            $maxHealthData = $maxHealthStmt->fetch(PDO::FETCH_ASSOC);
            if (!isset($maxHealthData['max_health']))
            {
                $pdo->rollBack();
                return new JsonResponse(500, "max_health is not set in fetched data.");
            }

            $maxHealth = $maxHealthData['max_health'] * 0.20; // 20% of max health is used

            // ==============================
            // STEP 5: UPDATE DEAD PLAYER STATUS
            // Modify the dead player’s status to 'alive' and update their health.
            // ==============================
            $aliveStatusIdQuery = "SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'alive'";
            $aliveStatusIdStmt = $pdo->prepare($aliveStatusIdQuery);
            $aliveStatusIdStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
            $aliveStatusIdStmt->execute();

            if ($aliveStatusIdStmt->rowCount() == 0)
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Alive status ID not found for species.");
            }

            $aliveStatusData = $aliveStatusIdStmt->fetch(PDO::FETCH_ASSOC);
            $aliveStatusId = $aliveStatusData['player_status_id'];

            $reviveQuery = "UPDATE players SET player_status_id = :alive_status_id, player_current_health = :max_health WHERE player_uuid = :player_uuid";
            $reviveStmt = $pdo->prepare($reviveQuery);
            $reviveStmt->bindParam(':player_uuid', $deadPlayerUuid, PDO::PARAM_STR);
            $reviveStmt->bindParam(':max_health', $maxHealth, PDO::PARAM_INT); // Assuming max_health is an integer
            $reviveStmt->bindParam(':alive_status_id', $aliveStatusId, PDO::PARAM_INT);
            $reviveStmt->execute();

            // Log the activity without waiting for a response
            $activityName = "revived_player"; // Replace with the actual activity name
            ActivityController::logActivity($pdo, $reviverUuid, $activityName);

            $pdo->commit();

            // For altar revival
            self::sendRevivalNotification($pdo, $deadPlayerUuid, $reviverUuid, 'altar');
            return new JsonResponse(200, "Player revived successfully.");

        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    // check if human exists and dead (blood level is 0)
    public static function checkIfHumanExistsAndDead($pdo, $humanUUID)
    {
        // Check if the UUID belongs to a player
        if (PlayerDataController::doesPlayerExist($pdo, $humanUUID))
        {
            return new JsonResponse(400, "The UUID belongs to a player, not a human.");
        }

        // Ensure the human exists in the database, and handle if adding the human fails
        if (!PlayerDataController::addHumanIfNotExists($pdo, $humanUUID))
        {
            return new JsonResponse(500, "Failed to ensure human existence in database.");
        }

        // Check if the human is dead (blood level is 0)
        try
        {
            $checkQuery = "SELECT blood_level FROM humans WHERE human_uuid = :uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
            $checkStmt->execute();

            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['blood_level'] == 0)
            {
                return new JsonResponse(200, "Human exists and is dead.");
            } else
            {
                return new JsonResponse(200, "Human exists but is not dead.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Internal Server Error.");
        }
    }

    public static function reviveHuman($pdo, $humanUUID, $consumableUuid)
    {
        try
        {
            // Check if the human exists
            $existQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
            $existStmt = $pdo->prepare($existQuery);
            $existStmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
            $existStmt->execute();

            if ($existStmt->fetchColumn() == 0)
            {
                // No human found with the provided UUID
                return new JsonResponse(404, "No human found with the provided UUID.");
            }

            // Update the human's blood level to 5.0 if they are dead
            $updateQuery = "UPDATE humans SET blood_level = 5.0 WHERE human_uuid = :uuid AND blood_level = 0";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
            $updateStmt->execute();

            if ($updateStmt->rowCount() > 0)
            {
                // Human was successfully revived

                // Attempt to mark the consumable as used
                $consumableResponse = InventoryController::setConsumableItemAsUsed($pdo, $consumableUuid);

                if ($consumableResponse->getStatus() != 200)
                {
                    // Consumable could not be marked as used
                    error_log("Failed to mark consumable as used: " . $consumableUuid);
                    return new JsonResponse(200, "Human revived, but failed to update consumable status.");
                }

                return new JsonResponse(200, "Human successfully revived.");
            } else
            {
                // Human was found but is not dead
                return new JsonResponse(200, "Human found but is not dead.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Internal Server Error.");
        }
    }

    private static $REVIVAL_COLORS = [
        'altar' => [
            'color' => 7844437, // Purple
            'description' => "A soul has been called back through ancient magic.",
        ],
        'potion' => [
            'color' => 3066993, // Green
            'description' => "A soul has been restored through alchemical means.",
        ],
        'default' => [
            'color' => 3447003, // Blue
            'description' => "A soul has returned to the realm.",
        ],
    ];

    private static function sendRevivalNotification($pdo, $revivedPlayerUuid, $reviverUuid = null, $revivalMethod): void
    {
        try
        {
            // Get revived player's details
            $revivedPlayerName = PlayerDataController::getPlayerLegacyName($pdo, $revivedPlayerUuid);
            $revivedPlayerBloodline = PlayerDataController::getPlayerBloodlineName($pdo, $revivedPlayerUuid);

            // Get reviver's name if available
            $reviverName = null;
            if ($reviverUuid)
            {
                $reviverName = PlayerDataController::getPlayerLegacyName($pdo, $reviverUuid);
            }

            // Get color and description from revival types array
            $revivalInfo = self::$REVIVAL_COLORS[$revivalMethod] ?? self::$REVIVAL_COLORS['default'];

            $fields = [
                [
                    "name" => "Revived Soul",
                    "value" => $revivedPlayerName . ($revivedPlayerBloodline ? "\nBloodline: *{$revivedPlayerBloodline}*" : ""),
                    "inline" => true,
                ],
            ];

            // Add reviver if exists
            if ($reviverName)
            {
                $fields[] = [
                    "name" => "Revived By",
                    "value" => $reviverName,
                    "inline" => true,
                ];
            }

            // Format method display based on revival method
            $methodMap = [
                'altar' => 'Reawakening Shrine',
                'potion' => 'Revival Elixir',
            ];
            $methodDisplay = $methodMap[$revivalMethod] ?? 'Unknown Method';

            $fields[] = [
                "name" => "Method",
                "value" => $methodDisplay,
                "inline" => true,
            ];

            $embed = [
                "title" => "Revival Log Entry",
                "type" => "rich",
                "description" => $revivalInfo['description'],
                "color" => $revivalInfo['color'],
                "fields" => $fields,
                "footer" => ["text" => "Welcome back to the realm"],
                "timestamp" => date("c"),
            ];

            // Prepare the message
            $message = json_encode([
                "username" => "Dark Oracle",
                "avatar_url" => "https://i.imgur.com/wUI5y5B.png",
                "embeds" => [$embed],
            ]);

            try
            {
                $response = CommunicationController::postToDiscordDeathLog($message);
                if ($response->getStatus() !== 204)
                {
                    error_log("Discord webhook revival log error: " . $response->getMessage());
                }
            }
            catch (Exception $e)
            {
                error_log("Error sending Discord webhook: " . $e->getMessage());
            }

        }
        catch (PDOException $e)
        {
            error_log("Database Error in revival notification: " . $e->getMessage());
        }
    }
}
