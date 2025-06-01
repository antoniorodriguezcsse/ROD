<?php

namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use PDO;
use PDOException;

//use PDOException;
class EssenceContainerController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // // This function is used to register a consumable item to a player's inventory
    public static function registerEssenceContainer($pdo, $playerUUID, $essenceContainerUUID, $consumableType, $capacity, $ownerName)
    {
        try {
            // Get the region name from the header
            $regionName = SecondLifeHeadersStatic::getRegionName();

            // Get player_id using UUID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            if (!$playerId) {
                return new JsonResponse(404, "Player UUID not found.");
            }

            // Check if the essence_container_uuid already exists
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_essence_containers WHERE essence_container_uuid = :essence_container_uuid");
            $checkQuery->execute([':essence_container_uuid' => $essenceContainerUUID]);
            $count = $checkQuery->fetchColumn();

            if ($count > 0) {
                // The essence_container_uuid already exists, indicating a duplicate
                return new JsonResponse(409, "Item already exists. Potential duplicate detected.");
            }

            $insertQuery = $pdo->prepare("INSERT INTO tracked_essence_containers
                                         (essence_container_uuid, region_name, current_owner_uuid, last_owner_uuid, current_owner_name,
                                         total_essence, capacity)
                                          VALUES (:essence_container_uuid, :region_name, :current_owner_uuid, :last_owner_uuid, :current_owner_name, :total_essence, :capacity)");

            $insertQuery->execute([
                ':essence_container_uuid' => $essenceContainerUUID,
                ':region_name' => $regionName,
                ':current_owner_uuid' => $playerUUID,
                ':last_owner_uuid' => $playerUUID,
                ':current_owner_name' => $ownerName, // Add this line
                ':total_essence' => "0.00",
                ':capacity' => $capacity,
            ]);

            // Check if the insert operation was successful
            if ($insertQuery->rowCount() === 0) {
                // Log the error
                error_log("Failed to insert new consumable item for essence_container_uuid: $essenceContainerUUID");
                return new JsonResponse(400, "Failed to insert new container item.");
            }

            return new JsonResponse(200, "Item registered successfully.");
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while registering the item.");
        }
    }

    public static function depositEssenceInContainer($pdo, $playerUUID, $amountOfEssenceToDeposit, $containerUUID)
    {
        try {
            // Get the region name from the header
            $regionName = SecondLifeHeadersStatic::getRegionName();

            // Get player_id using UUID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            if (!$playerId) {
                return new JsonResponse(404, "Player UUID not found.");
            }

            // Check if the essence_container_uuid already exists
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_essence_containers WHERE essence_container_uuid = :essence_container_uuid");
            $checkQuery->execute([':essence_container_uuid' => $containerUUID]);
            $count = $checkQuery->fetchColumn();

            if ($count > 0) {
                $totalEssenceInContainer = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence");
                $newTotalEssenceForContainer = (floatval($totalEssenceInContainer) + floatval($amountOfEssenceToDeposit));
                $result = TableController::updateTableData($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence", $newTotalEssenceForContainer);

                if ($result == "Successfully updated.") {
                    $amountOfPlayerEssence = PlayerDataController::getPlayerEssenceCount($pdo, $playerUUID);
                    $newPlayerTotal = (floatval($amountOfPlayerEssence) - floatval($amountOfEssenceToDeposit));
                    $result = PlayerDataController::updatePlayerEssence($pdo, $playerUUID, $newPlayerTotal);

                    if ($result == "Player essence updated successfully.") {
                        $totalEssenceInContainer = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence");
                        return new JsonResponse(200, "Deposit has been successful.", ["total_essence_deposited" => $amountOfEssenceToDeposit, "container_essence" => $newTotalEssenceForContainer]);
                    } else {
                        return new JsonResponse(422, "could not update player's essence.");
                    }
                } else {
                    return new JsonResponse(422, $result);
                }
            } else {
                return new JsonResponse(200, "Essence container could not be found in the database.");
            }

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while registering the item.");
        }
    }

    public static function withdrawEssenceFromContainer($pdo, $playerUUID, $amountOfEssenceToWithdraw, $containerUUID)
    {
        try {
            // Get the region name from the header
            $regionName = SecondLifeHeadersStatic::getRegionName();

            // Get player_id using UUID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            if (!$playerId) {
                return new JsonResponse(404, "Player UUID not found.");
            }

            // Check if the essence_container_uuid already exists
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_essence_containers WHERE essence_container_uuid = :essence_container_uuid");
            $checkQuery->execute([':essence_container_uuid' => $containerUUID]);
            $count = $checkQuery->fetchColumn();

            if ($count > 0) {
                $totalEssenceInContainer = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence");
                if (floatval($totalEssenceInContainer) >= floatval($amountOfEssenceToWithdraw)) {
                    $newTotalEssenceForContainer = floatval($totalEssenceInContainer) - floatval($amountOfEssenceToWithdraw);

                    $result = TableController::updateTableData($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence", $newTotalEssenceForContainer);
                    if ($result == "Successfully updated.") {
                        $amountOfPlayerEssence = PlayerDataController::getPlayerEssenceCount($pdo, $playerUUID);
                        $newPlayerTotal = (floatval($amountOfPlayerEssence) + floatval($amountOfEssenceToWithdraw));
                        $result = PlayerDataController::updatePlayerEssence($pdo, $playerUUID, $newPlayerTotal);

                        $totalEssenceInContainer = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "total_essence");

                        if ($result == "Player essence updated successfully.") {
                            return new JsonResponse(200, "Withdrawal has been successful.", ["container_essence" => $totalEssenceInContainer, "withdrew_amount" => $amountOfEssenceToWithdraw]);
                        } else {
                            return new JsonResponse(422, "Could not update player's essence.");
                        }
                    } else {
                        return new JsonResponse(422, $result);
                    }
                } else {
                    return new JsonResponse(422, "Insufficient essence in the container.");
                }
            } else {
                return new JsonResponse(200, "Essence container could not be found in the database.");
            }

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while withdrawing essence.");
        }
    }

    public static function checkAndAssignOwner($pdo, $containerUUID, $newOwnerUuid, $newOwnerName)
    {
        try {
            $pdo->beginTransaction();

            $regionName = SecondLifeHeadersStatic::getRegionName();

            // Check if the essence_container_uuid already exists
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_essence_containers WHERE essence_container_uuid = :essence_container_uuid");
            $checkQuery->execute([':essence_container_uuid' => $containerUUID]);
            $result = $checkQuery->fetchColumn();

            if ($result === false) {
                $pdo->rollBack();
                return new JsonResponse(404, "Essence container not found.");
            }

            //TableController::updateTableData($pdo, "tracked_essence_container", "essence_container_uuid", $containerUUID,"region_name",  SecondLifeHeadersStatic::getRegionName());

            // Update the current owner of the consumable item if there's a change
            $currentOwnerUUID = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "current_owner_uuid");
            $currentOwnerName = TableController::getFieldValue($pdo, "tracked_essence_containers", "essence_container_uuid", $containerUUID, "current_owner_name");
            // echo "current owner: " . $currentOwnerUUID;
            // echo "\nnew owner: " . $currentOwnerName;
            
            if ($currentOwnerUUID !== $newOwnerUuid) {
                $updateQuery = $pdo->prepare("UPDATE tracked_essence_containers
                SET current_owner_uuid = :new_owner_uuid, last_owner_uuid = :current_owner_uuid, region_name = :region_name, current_owner_name = :current_owner_name, last_owner_name = :last_owner_name
                WHERE essence_container_uuid = :essence_container_uuid");

                $updateQuery->execute([
                    ':new_owner_uuid' => $newOwnerUuid,
                    ':current_owner_uuid' => $currentOwnerUUID,
                    ':essence_container_uuid' => $containerUUID,
                    ':region_name' => $regionName,
                    ':current_owner_name' => $newOwnerName,
                    ':last_owner_name' => $currentOwnerName, 
                ]);

                $currentOwner = $newOwnerUuid;
                if ($updateQuery->rowCount() === 0) {
                    // Log the error but do not change the response
                    error_log("Failed to update owner for essence_container_uuid: $containerUUID");
                }
            } else {

                $updateQuery = $pdo->prepare("UPDATE tracked_essence_containers
                SET region_name = :region_name
                WHERE essence_container_uuid = :essence_container_uuid");

                $updateQuery->execute([
                    ':region_name' => $regionName,
                    ':essence_container_uuid' => $containerUUID,
                ]);

                if ($updateQuery->rowCount() === 0) {
                    // Log the error but do not change the response
                    error_log("Failed to update region name for essence_container_uuid: $containerUUID");
                }

            }

            $pdo->commit();
            return new JsonResponse(200, "Ownership of the Essence Container has been successfully updated.", ["owner_key" => $currentOwner]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    public static function deleteFromDatabase($pdo, $essenceContainerUUID)
    {
        try {
            $pdo->beginTransaction();

            // Prepare the delete query
            $deleteQuery = $pdo->prepare("DELETE FROM tracked_essence_containers WHERE essence_container_uuid = :essence_container_uuid");
            $deleteQuery->execute([':essence_container_uuid' => $essenceContainerUUID]);

            if ($deleteQuery->rowCount() == 0) {
                $pdo->rollBack();
                return new JsonResponse(404, "Essence Container not found or already deleted.");
            }

            $pdo->commit();
            return new JsonResponse(200, "Essence Container deleted successfully.");

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

}
