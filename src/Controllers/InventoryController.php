<?php

namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use PDO;
use PDOException;

//use PDOException;
class InventoryController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    // This function is used to update a player's inventory
    public static function updatePlayerInventory($pdo, $playerUUID, $items, $isVendor = false, $donorUUID = null)
    {
        try {
            // Begin a transaction
            $pdo->beginTransaction();

            // Get the region name from the header
            $regionName = SecondLifeHeadersStatic::getRegionName();

            // Get player_id using UUID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            if (!$playerId) {
                $pdo->rollBack();
                return new JsonResponse(404, "Player UUID not found.");
            }

            // Resolve donorId from donorUUID if provided and not a vendor transaction
            $donorId = null;
            if ($donorUUID && !$isVendor) {
                $donorId = PlayerDataController::getPlayerIdByUUID($pdo, $donorUUID);
                // Handle the case where donor UUID is invalid
                if (!$donorId) {
                    $pdo->rollBack();
                    return new JsonResponse(404, "Donor UUID not found.");
                }
            }

            // Check if the player has an existing inventory
            $query = $pdo->prepare("SELECT * FROM player_inventory WHERE player_id = :player_id");
            $query->execute([':player_id' => $playerId]);
            $inventory = $query->fetch(PDO::FETCH_ASSOC);

            if ($inventory) {
                // Update existing inventory
                foreach ($items as $item => $quantity) {
                    $updateQuery = $pdo->prepare("UPDATE player_inventory SET $item = $item + :quantity WHERE player_id = :player_id");
                    $updateQuery->execute([':quantity' => $quantity, ':player_id' => $playerId]);
                }
            } else {
                // Create new inventory entry
                $fields = implode(", ", array_keys($items));
                $values = ":" . implode(", :", array_keys($items));
                $sql = "INSERT INTO player_inventory (player_id, $fields) VALUES (:player_id, $values)";
                $insertQuery = $pdo->prepare($sql);
                $params = array_combine(array_map(function ($k) {return ':' . $k;}, array_keys($items)), array_values($items));
                $params[':player_id'] = $playerId;
                $insertQuery->execute($params);
            }

            // Log each item update/insertion
            $sourceType = $isVendor ? 'vendor' : 'player';
            foreach ($items as $item => $quantity) {
                if (!self::logInventoryTransaction($pdo, $donorId, $playerId, $item, $quantity, $regionName, $sourceType)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to log the inventory update, changes rolled back.");
                }
            }

            // Commit the transaction
            $pdo->commit();
            return new JsonResponse(200, "Inventory updated successfully.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            return new JsonResponse(500, "Failed to update player inventory: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    // This function is used to transfer an item from one player to another
    public static function transferItemBetweenPlayers($pdo, $donorUUID, $recipientUUID, $item, $quantity, $regionName = null)
    {
        try {
            // Begin a transaction
            $pdo->beginTransaction();

            // Get donor and recipient player_id
            $donorId = PlayerDataController::getPlayerIdByUUID($pdo, $donorUUID);
            $recipientId = PlayerDataController::getPlayerIdByUUID($pdo, $recipientUUID);

            if (!$donorId || !$recipientId) {
                $pdo->rollBack();
                return new JsonResponse(404, "One or both player UUIDs not found.");
            }

            // Check donor's inventory
            $donorQuery = $pdo->prepare("SELECT $item FROM player_inventory WHERE player_id = :player_id");
            $donorQuery->execute([':player_id' => $donorId]);
            $donorInventory = $donorQuery->fetch(PDO::FETCH_ASSOC);

            if ($donorInventory[$item] < $quantity) {
                $pdo->rollBack();
                return new JsonResponse(400, "Donor does not have enough of the item to transfer.");
            }

            // Deduct the item from the donor
            $updateDonor = $pdo->prepare("UPDATE player_inventory SET $item = $item - :quantity WHERE player_id = :player_id");
            $updateDonor->execute([':quantity' => $quantity, ':player_id' => $donorId]);

            // Add the item to the recipient
            $recipientQuery = $pdo->prepare("SELECT * FROM player_inventory WHERE player_id = :player_id");
            $recipientQuery->execute([':player_id' => $recipientId]);
            $recipientInventory = $recipientQuery->fetch(PDO::FETCH_ASSOC);

            if ($recipientInventory) {
                $updateRecipient = $pdo->prepare("UPDATE player_inventory SET $item = $item + :quantity WHERE player_id = :player_id");
                $updateRecipient->execute([':quantity' => $quantity, ':player_id' => $recipientId]);
            } else {
                $insertRecipient = $pdo->prepare("INSERT INTO player_inventory (player_id, $item) VALUES (:player_id, :quantity)");
                $insertRecipient->execute([':player_id' => $recipientId, ':quantity' => $quantity]);
            }

            // Log the transaction
            if (!self::logInventoryTransaction($pdo, $donorId, $recipientId, $item, $quantity, $regionName, 'player')) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to log the transaction, transfer rolled back.");
            }

            // Commit the transaction
            $pdo->commit();
            return new JsonResponse(200, "Item transferred successfully.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            return new JsonResponse(500, "Failed to transfer item: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    // This function is used to register a consumable item to a player's inventory
    public static function registerConsumableItem($pdo, $playerUUID, $consumableUuid, $consumableType)
    {
        try {


            // Get the region name from the header
            $regionName = SecondLifeHeadersStatic::getRegionName();


            // Check if the consumable_uuid already exists
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_consumables WHERE consumable_uuid = :consumable_uuid");
            $checkQuery->execute([':consumable_uuid' => $consumableUuid]);
            $count = $checkQuery->fetchColumn();

            if ($count > 0) {
                // The consumable_uuid already exists, indicating a duplicate
                return new JsonResponse(409, "Item already exists. Potential duplicate detected.");
            }

            // Insert the new consumable item
            $insertQuery = $pdo->prepare("INSERT INTO tracked_consumables (consumable_uuid, consumable_type, current_owner_uuid, region_name) VALUES (:consumable_uuid, :consumable_type, :current_owner_uuid, :region_name)");
            $insertQuery->execute([
                ':consumable_uuid' => $consumableUuid,
                ':consumable_type' => $consumableType,
                ':current_owner_uuid' => $playerUUID,
                ':region_name' => $regionName,
            ]);

            return new JsonResponse(200, "Item registered successfully.");
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while registering the item.");
        }
    }

    public static function checkIfConsumableUsedAndUpdateOwner($pdo, $consumableUuid, $newOwnerUuid) {
        try {
            $pdo->beginTransaction();
    
            // Check the current status and owner of the consumable item
            $checkQuery = $pdo->prepare("SELECT used, current_owner_uuid FROM tracked_consumables WHERE consumable_uuid = :consumable_uuid");
            $checkQuery->execute([':consumable_uuid' => $consumableUuid]);
            $result = $checkQuery->fetch(PDO::FETCH_ASSOC);
    
            if ($result === false) {
                $pdo->rollBack();
                return new JsonResponse(404, "Consumable item not found.");
            }
    
            if ($result['used']) {
                $pdo->rollBack();
                return new JsonResponse(400, "Consumable item has already been used.");
            }
    
            // Update the current owner of the consumable item if there's a change
            if ($result['current_owner_uuid'] !== $newOwnerUuid) {
                $updateQuery = $pdo->prepare("UPDATE tracked_consumables SET current_owner_uuid = :new_owner_uuid, last_owner_uuid = :current_owner_uuid WHERE consumable_uuid = :consumable_uuid");
                $updateQuery->execute([
                    ':new_owner_uuid' => $newOwnerUuid,
                    ':current_owner_uuid' => $result['current_owner_uuid'],
                    ':consumable_uuid' => $consumableUuid
                ]);
    
                if ($updateQuery->rowCount() == 0) {
                    // Log the error but do not change the response
                    error_log("Failed to update owner for consumable_uuid: " . $consumableUuid);
                }
            }
    
            $pdo->commit();
            return new JsonResponse(200, "Consumable item is valid and not used.");
    
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }
    

    // This function is used to mark a consumable item as used
    public static function setConsumableItemAsUsed($pdo, $consumableUuid)
    {
        try {
            // Prepare a query to update the consumable item as used
            $updateQuery = $pdo->prepare("UPDATE tracked_consumables SET used = TRUE WHERE consumable_uuid = :consumable_uuid AND used = FALSE");
            $updateQuery->execute([':consumable_uuid' => $consumableUuid]);

            if ($updateQuery->rowCount() > 0) {
                return new JsonResponse(200, "Consumable item marked as used successfully.");
            } else {
                // Check if the item exists
                $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM tracked_consumables WHERE consumable_uuid = :consumable_uuid");
                $checkQuery->execute([':consumable_uuid' => $consumableUuid]);
                if ($checkQuery->fetchColumn() > 0) {
                    return new JsonResponse(409, "Consumable item already used.");
                } else {
                    return new JsonResponse(404, "Consumable item not found.");
                }
            }
        } catch (PDOException $e) {
            // Log and return a generic error message
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "Database error while updating consumable item.");
        }
    }

    // This function is used to log inventory transactions
    public static function logInventoryTransaction($pdo, $donorId, $recipientId, $item, $quantity, $regionName = null, $sourceType = 'player')
    {
        try {
            $logQuery = $pdo->prepare("INSERT INTO inventory_transfer_logs (donor_id, recipient_id, item, quantity, region_name, source_type) VALUES (:donor_id, :recipient_id, :item, :quantity, :region_name, :source_type)");
            $logQuery->execute([
                ':donor_id' => $donorId,
                ':recipient_id' => $recipientId,
                ':item' => $item,
                ':quantity' => $quantity,
                ':region_name' => $regionName,
                ':source_type' => $sourceType,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log inventory transaction: " . $e->getMessage());
            return false;
        }
    }


    public static function deleteConsumable($pdo, $consumableUuid) {
        try {
            $pdo->beginTransaction();
    
            // Prepare the delete query
            $deleteQuery = $pdo->prepare("DELETE FROM tracked_consumables WHERE consumable_uuid = :consumable_uuid");
            $deleteQuery->execute([':consumable_uuid' => $consumableUuid]);
    
            if ($deleteQuery->rowCount() == 0) {
                $pdo->rollBack();
                return new JsonResponse(404, "Consumable item not found or already deleted.");
            }
    
            $pdo->commit();
            return new JsonResponse(200, "Consumable item deleted successfully.");
    
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }
    

}
