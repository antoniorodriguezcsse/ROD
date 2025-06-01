<?php
// ResireController.php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;

class ResireController
{
    // public static function initiateResire(PDO $pdo, $playerUUID, $newSireUUID, $allowChainMove)
    // {
    //     try {
    //         // Start a transaction
    //         $pdo->beginTransaction();

    //         // Get the player ID and new sire ID using the player data helper
    //         $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
    //         if (!$playerId) {
    //             $pdo->rollBack();
    //             return new JsonResponse(400, "Invalid player UUID: Player not found.");
    //         }

    //         $newSireId = PlayerDataController::getPlayerIdByUUID($pdo, $newSireUUID);
    //         if (!$newSireId) {
    //             $pdo->rollBack();
    //             return new JsonResponse(400, "Invalid new sire UUID: Sire not found.");
    //         }

    //         // Check if the player is a bloodline leader
    //         if (self::isBloodlineLeader($pdo, $playerId)) {
    //             $pdo->rollBack();
    //             return new JsonResponse(400, "Bloodline leaders cannot initiate a Re-Sire.");
    //         }

    //         // Get the root sire's old sire ID and generation and old sire's bloodline ID
    //         $rootSireOldSireId = self::getRootSireOldSireId($pdo, $playerUUID);
    //         if (!$rootSireOldSireId) {
    //             $pdo->rollBack();
    //             return new JsonResponse(400, "Root sire's old sire not found.");
    //         }
    //         // Get the root sire's old sire's bloodline ID and generation
    //         $sireOldSireUUID = PlayerDataController::getPlayerUuidById($pdo, $rootSireOldSireId);
    //         $sireOldSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $sireOldSireUUID);
    //         $oldSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $sireOldSireUUID);

    //         // Check if the player and new sire are of the same species
    //         $playerSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $playerUUID);
    //         $newSireSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $newSireUUID);

    //         // Check if the player and new sire are of different species
    //         $isSpeciesChange = ($playerSpeciesId !== $newSireSpeciesId);

    //         if ($isSpeciesChange) {
    //             // Check if the player has any followers
    //             $directFollowerCount = SpeciesChangeController::getDirectFollowerCount($pdo, $playerId);
    //             if ($directFollowerCount) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(400, "Player must have no followers to initiate a Re-Sire with a species change.");
    //             }

    //             // Check if the player is following anyone
    //             $followingId = SpeciesChangeController::getPlayerFollowingId($pdo, $playerUUID);
    //             if ($followingId) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(400, "Player must not be following anyone to initiate a Re-Sire with a species change.");
    //             }

    //         }

    //         // Get the new sire's bloodline ID and generation
    //         $newSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $newSireUUID);
    //         $newSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $newSireUUID);

    //         // Move the root Re-Sire immediately
    //         if (!self::updatePlayerSire($pdo, $playerId, $newSireId, $newSireBloodlineId, $newSireGeneration + 1)) {
    //             $pdo->rollBack();
    //             return new JsonResponse(500, "Failed to update root sire.");
    //         }

    //         if ($isSpeciesChange) {

    //             // Get the new age group ID for the new species
    //             $newAgeGroupId = SpeciesChangeController::getNewAgeGroupIdForSpecies($pdo, $playerUUID, $newSireSpeciesId);
    //             if (!$newAgeGroupId) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(500, 'Failed to determine new age group for species.');
    //             }

    //             // Update the player's species_id, player_age_group_id
    //             $updateSpeciesQuery = $pdo->prepare(
    //                 "UPDATE players
    //              SET species_id = :newSpeciesId,
    //                  player_age_group_id = :newAgeGroupId
    //              WHERE player_uuid = :playerUUID"
    //             );
    //             $updateSpeciesQuery->execute([
    //                 ':newSpeciesId' => $newSireSpeciesId,
    //                 ':newAgeGroupId' => $newAgeGroupId,
    //                 ':playerUUID' => $playerUUID,
    //             ]);

    //             // Check if the update was successful
    //             if ($updateSpeciesQuery->rowCount() == 0) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(400, 'Failed to update player details for species change.');
    //             }

    //             // Update the player's status to 'alive' of the new species
    //             $statusUpdated = SpeciesChangeController::updatePlayerStatus($pdo, $playerUUID, "alive", false);
    //             if (!$statusUpdated) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(500, 'Failed to update player status.');
    //             }

    //             // Check if the player is a house owner
    //             if (self::isHouseOwner($pdo, $playerUUID)) {

    //                 // Change the house type and species
    //                 $changeHouseTypeResponse = self::changeHouseTypeAndSpecies($pdo, $playerUUID, $newSireSpeciesId);
    //                 if (!$changeHouseTypeResponse) {
    //                     $pdo->rollBack();
    //                     return new JsonResponse(500, 'Failed to change house type and species.');
    //                 }

    //                 // Get the new species role ID for the owner
    //                 $ownerRoleId = HouseController::getRoleIdForKeyword($pdo, $playerUUID, 'house_owner');

    //                 // Update the player's role ID
    //                 $updateRoleQuery = $pdo->prepare(
    //                     "UPDATE players
    //                  SET house_role_id = :newRoleId
    //                  WHERE player_uuid = :playerUUID"
    //                 );

    //                 $updateRoleQuery->execute([
    //                     ':newRoleId' => $ownerRoleId,
    //                     ':playerUUID' => $playerUUID,
    //                 ]);

    //                 // Check if the update was successful
    //                 if ($updateRoleQuery->rowCount() == 0) {
    //                     $pdo->rollBack();
    //                     return new JsonResponse(400, 'Failed to update player role.');
    //                 }
    //             }

    //             // Descendants will be set to the player's old sire
    //             $directDescendants = self::getDirectDescendants($pdo, $playerId);
    //             foreach ($directDescendants as $descendantId) {
    //                 // Update the direct descendant's sire to the player's old sire
    //                 if (!self::updatePlayerSire($pdo, $descendantId, $rootSireOldSireId, $oldSireBloodlineId, $sireOldSireGeneration + 1)) {
    //                     $pdo->rollBack();
    //                     return new JsonResponse(500, "Failed to set descendant's sire to root sire's old sire.");
    //                 }
    //             }

    //         } else {

    //             // Set the player's direct descendants' sire
    //             $directDescendants = self::getDirectDescendants($pdo, $playerId);
    //             foreach ($directDescendants as $descendantId) {
    //                 if ($allowChainMove) {
    //                     // Create a new entry in the resire_requests table for each direct descendant
    //                     if (!self::createResireRequest($pdo, $descendantId, $playerId, $rootSireOldSireId)) {
    //                         $pdo->rollBack();
    //                         return new JsonResponse(500, "Failed to create Re-Sire request for descendant.");
    //                     }

    //                     // Set the direct descendant's sire_id to NULL
    //                     if (!self::setPlayerSireNull($pdo, $descendantId)) {
    //                         $pdo->rollBack();
    //                         return new JsonResponse(500, "Failed to set descendant's sire to NULL.");
    //                     }
    //                 } else {
    //                     // Update the direct descendant's sire to the player's old sire
    //                     if (!self::updatePlayerSire($pdo, $descendantId, $rootSireOldSireId, $oldSireBloodlineId, $sireOldSireGeneration + 1)) {
    //                         $pdo->rollBack();
    //                         return new JsonResponse(500, "Failed to set descendant's sire to root sire.");
    //                     }
    //                 }
    //             }
    //         }

    //         // Commit the transaction
    //         $pdo->commit();
    //         return new JsonResponse(200, "Re-Sire initiated successfully.");
    //     } catch (Exception $e) {
    //         // Roll back the transaction in case of an exception
    //         $pdo->rollBack();
    //         error_log("Error initiating Re-Sire: " . $e->getMessage());
    //         return new JsonResponse(500, "An error occurred while initiating the Re-Sire: " . $e->getMessage());
    //     }
    // }

    public static function initiateResire(PDO $pdo, $playerUUID, $newSireUUID, $allowChainMove)
    {
        try {
            $pdo->beginTransaction();

            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            $newSireId = PlayerDataController::getPlayerIdByUUID($pdo, $newSireUUID);
            if (!$playerId || !$newSireId) {
                $pdo->rollBack();
                return new JsonResponse(400, "Invalid player or sire UUID.");
            }

            if (self::isBloodlineLeader($pdo, $playerId)) {
                $pdo->rollBack();
                return new JsonResponse(400, "Bloodline leaders cannot initiate a Re-Sire.");
            }

            $rootSireOldSireId = self::getRootSireOldSireId($pdo, $playerUUID);
            if (!$rootSireOldSireId) {
                $pdo->rollBack();
                return new JsonResponse(400, "Root sire's old sire not found.");
            }
            $sireOldSireUUID = PlayerDataController::getPlayerUuidById($pdo, $rootSireOldSireId);
            $sireOldSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $sireOldSireUUID);
            $oldSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $sireOldSireUUID);

            $playerSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $playerUUID);
            $newSireSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $newSireUUID);
            $isSpeciesChange = ($playerSpeciesId !== $newSireSpeciesId);

            $newSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $newSireUUID);
            $newSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $newSireUUID);

            $playerNewGeneration = $newSireGeneration + 1;

            if ($isSpeciesChange) {
                if (SpeciesChangeController::getDirectFollowerCount($pdo, $playerId) > 0 ||
                    SpeciesChangeController::getPlayerFollowingId($pdo, $playerUUID)) {
                    $pdo->rollBack();
                    return new JsonResponse(400, "Player must have no followers and not be following anyone for a species change Re-Sire.");
                }

                if (!self::handleSpeciesChange($pdo, $playerUUID, $newSireSpeciesId)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to handle species change.");
                }

                if (!self::moveDescendantsToOldSire($pdo, $playerId, $rootSireOldSireId, $oldSireBloodlineId, $sireOldSireGeneration)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to move descendants to old sire.");
                }
            } elseif ($allowChainMove) {
                // This covers both same bloodline and different bloodline with chain move
                if (!self::createResireRequestsForDescendants($pdo, $playerId, $rootSireOldSireId)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to create Re-Sire requests for descendants.");
                }
            } else {
                // Different bloodline without chain move
                if (!self::moveDescendantsToOldSire($pdo, $playerId, $rootSireOldSireId, $oldSireBloodlineId, $sireOldSireGeneration)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to move descendants to old sire.");
                }
            }

            if (!self::updatePlayerSire($pdo, $playerId, $newSireId, $newSireBloodlineId, $playerNewGeneration)) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to update player's sire.");
            }

            $pdo->commit();

            if($isSpeciesChange)
            {
                $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "reset_hud"]);
                // Check the status code of the response
                $statusCode = $sendToHudResponse->getStatus();
                if ($statusCode != 200) {
                    error_log("Resire for player: $playerUUID, failed to reset hud.");
                }
            }

            return new JsonResponse(200, "Re-Sire initiated successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error initiating Re-Sire: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while initiating the Re-Sire: " . $e->getMessage());
        }
    }

    private static function moveDescendantsToOldSire(PDO $pdo, $playerId, $oldSireId, $oldSireBloodlineId, $oldSireGeneration)
    {
        $directDescendants = self::getDirectDescendants($pdo, $playerId);
        foreach ($directDescendants as $descendantId) {
            $newGeneration = $oldSireGeneration + 1;
            if (!self::updatePlayerSire($pdo, $descendantId, $oldSireId, $oldSireBloodlineId, $newGeneration)) {
                return false;
            }
            self::notifyDescendantOfChange($pdo, $descendantId, $newGeneration, $oldSireBloodlineId);

            if (!self::updateDescendantChainForSpeciesChange($pdo, $descendantId, $newGeneration, $oldSireBloodlineId)) {
                return false;
            }
        }
        return true;
    }

    private static function updateDescendantChainForSpeciesChange(PDO $pdo, $parentId, $parentGeneration, $bloodlineId)
    {
        $descendants = self::getDirectDescendants($pdo, $parentId);
        foreach ($descendants as $descendantId) {
            $newGeneration = $parentGeneration + 1;
            if (!self::updatePlayerGeneration($pdo, $descendantId, $newGeneration, $bloodlineId)) {
                return false;
            }
            self::notifyDescendantOfChange($pdo, $descendantId, $newGeneration, $bloodlineId);

            if (!self::updateDescendantChainForSpeciesChange($pdo, $descendantId, $newGeneration, $bloodlineId)) {
                return false;
            }
        }
        return true;
    }

    private static function updateDescendantChain(PDO $pdo, $playerId, $parentGeneration, $bloodlineId)
    {
        $descendants = self::getDirectDescendants($pdo, $playerId);
        foreach ($descendants as $descendantId) {
            $descendantGeneration = $parentGeneration + 1;
            if (!self::updatePlayerSire($pdo, $descendantId, $playerId, $bloodlineId, $descendantGeneration)) {
                return false;
            }
            self::notifyDescendantOfChange($pdo, $descendantId, $descendantGeneration, $bloodlineId);

            if (!self::updateDescendantChain($pdo, $descendantId, $descendantGeneration, $bloodlineId)) {
                return false;
            }
        }
        return true;
    }

    private static function createResireRequestsForDescendants(PDO $pdo, $playerId, $rootSireOldSireId)
    {
        $descendants = self::getDirectDescendants($pdo, $playerId);
        foreach ($descendants as $descendantId) {
            if (!self::createResireRequest($pdo, $descendantId, $playerId, $rootSireOldSireId)) {
                return false;
            }
            if (!self::setPlayerSireNull($pdo, $descendantId)) {
                return false;
            }
        }
        return true;
    }

    private static function handleSpeciesChange(PDO $pdo, $playerUUID, $newSpeciesId)
    {
        // Update player's species
        $updateSpeciesQuery = $pdo->prepare("UPDATE players SET species_id = :newSpeciesId WHERE player_uuid = :playerUUID");
        if (!$updateSpeciesQuery->execute([':newSpeciesId' => $newSpeciesId, ':playerUUID' => $playerUUID])) {
            return false;
        }

        // Get the new age group ID for the new species
        $newAgeGroupId = SpeciesChangeController::getNewAgeGroupIdForSpecies($pdo, $playerUUID, $newSpeciesId);
        if (!$newAgeGroupId) {
            return false;
        }

        // Update the player's age group
        $updateAgeGroupQuery = $pdo->prepare("UPDATE players SET player_age_group_id = :newAgeGroupId WHERE player_uuid = :playerUUID");
        if (!$updateAgeGroupQuery->execute([':newAgeGroupId' => $newAgeGroupId, ':playerUUID' => $playerUUID])) {
            return false;
        }

        // Update the player's status to 'alive' of the new species
        if (!SpeciesChangeController::updatePlayerStatus($pdo, $playerUUID, "alive", false)) {
            return false;
        }

        // Check if the player is a house owner
        if (self::isHouseOwner($pdo, $playerUUID)) {
            // Change the house type and species
            if (!self::changeHouseTypeAndSpecies($pdo, $playerUUID, $newSpeciesId)) {
                return false;
            }

            // Get the new species role ID for the owner
            $ownerRoleId = HouseController::getRoleIdForKeyword($pdo, $playerUUID, 'house_owner');

            // Update the player's role ID
            $updateRoleQuery = $pdo->prepare("UPDATE players SET house_role_id = :newRoleId WHERE player_uuid = :playerUUID");
            if (!$updateRoleQuery->execute([':newRoleId' => $ownerRoleId, ':playerUUID' => $playerUUID])) {
                return false;
            }
        }

        return true;
    }

    private static function updatePlayerGeneration(PDO $pdo, $playerId, $newGeneration, $bloodlineId)
    {
        $query = "UPDATE players SET player_generation = :newGeneration, bloodline_id = :bloodlineId WHERE player_id = :playerId";
        $stmt = $pdo->prepare($query);
        return $stmt->execute([
            ':newGeneration' => $newGeneration,
            ':bloodlineId' => $bloodlineId,
            ':playerId' => $playerId,
        ]);
    }

    private static function notifyDescendantOfChange(PDO $pdo, $descendantId, $newGeneration, $newBloodlineId)
    {
        // Implementation depends on how you want to notify players
        // This could involve creating a notification in a separate table, sending an in-game message, etc.
        // For now, we'll just log the change
        error_log("Notifying player $descendantId of generation change to $newGeneration and bloodline change to $newBloodlineId");
    }

    private static function getRootSireOldSireId(PDO $pdo, $playerUUID)
    {
        $query = "SELECT sire_id FROM players WHERE player_uuid = :playerUUID";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':playerUUID' => $playerUUID]);
        return $stmt->fetchColumn();
    }

    public static function createResireRequest(PDO $pdo, $playerId, $currentSireId, $rootSireOldSireId)
    {
        $query = "INSERT INTO resire_requests (player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, status)
              VALUES (:playerId, :currentSireId, :rootSireOldSireId, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), 'pending')";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':currentSireId', $currentSireId, PDO::PARAM_INT);
        $stmt->bindParam(':rootSireOldSireId', $rootSireOldSireId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0 ? $pdo->lastInsertId() : false;
    }

    public static function updatePlayerSire(PDO $pdo, $playerId, $newSireId, $newSireBloodlineId, $playerGeneration)
    {
        $query = "UPDATE players SET sire_id = :newSireId, bloodline_id = :newSireBloodlineId, player_generation = :playerGeneration WHERE player_id = :playerId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':newSireId', $newSireId, PDO::PARAM_INT);
        $stmt->bindParam(':newSireBloodlineId', $newSireBloodlineId, PDO::PARAM_INT);
        $stmt->bindParam(':playerGeneration', $playerGeneration, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function setPlayerSireNull(PDO $pdo, $playerId)
    {
        $query = "UPDATE players SET sire_id = NULL WHERE player_id = :playerId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function notifyDirectDescendant(PDO $pdo, $playerId, $resireRequestId)
    {
        // Implementation to notify a direct descendant about the pending Re-Sire
        // This can be done through in-game messages, emails, or push notifications
        // Return true if the notification is successful, false otherwise

        // Placeholder implementation
        return true;
    }

    public static function joinResire(PDO $pdo, $playerUUID)
    {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // Get the player ID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);

            // Check if the player exists
            if (!$playerId) {
                $pdo->rollBack();
                return new JsonResponse(400, "Invalid player UUID.");
            }

            // Get the pending Re-Sire request for the player
            $query = "SELECT id, current_sire_id, root_sire_old_sire_id FROM resire_requests WHERE player_id = :playerId AND status = 'pending' LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $resireRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if a pending Re-Sire request exists for the player
            if (!$resireRequest) {
                $pdo->rollBack();
                return new JsonResponse(400, "No pending Re-Sire request found for the player.");
            }

            // Get the Re-Sire request ID
            $resireRequestId = $resireRequest['id'];

            // Get the new sire's bloodline ID and generation
            $newSireId = $resireRequest['current_sire_id'];
            $newSireUUID = PlayerDataController::getPlayerUuidById($pdo, $newSireId);
            $newSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $newSireUUID);
            $newSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $newSireUUID);

            // Update the player's sire, bloodline, and generation
            if (!self::updatePlayerSire($pdo, $playerId, $newSireId, $newSireBloodlineId, $newSireGeneration + 1)) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to update player's sire, bloodline, and generation.");
            }

            // Set the player's direct descendants' sire
            $directDescendants = self::getDirectDescendants($pdo, $playerId);
            foreach ($directDescendants as $descendantId) {
                // Create a new entry in the resire_requests table for each direct descendant
                if (!self::createResireRequest($pdo, $descendantId, $playerId, $resireRequest['root_sire_old_sire_id'])) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to create Re-Sire request for descendant.");
                }

                // Set the direct descendant's sire_id to NULL
                if (!self::setPlayerSireNull($pdo, $descendantId)) {
                    $pdo->rollBack();
                    return new JsonResponse(500, "Failed to set descendant's sire to NULL.");
                }
            }

            // Move the request to history table
            $moveToHistoryQuery = "INSERT INTO resire_requests_history
            (original_request_id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, status, completed_date)
            SELECT id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, 'accepted', NOW()
            FROM resire_requests WHERE id = :resireRequestId";
            $stmt = $pdo->prepare($moveToHistoryQuery);
            $stmt->execute([':resireRequestId' => $resireRequestId]);

            // Check if the move was successful
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to move Re-Sire request to history.");
            }

            // Delete the original request
            $deleteQuery = "DELETE FROM resire_requests WHERE id = :resireRequestId";
            $stmt = $pdo->prepare($deleteQuery);
            $stmt->execute([':resireRequestId' => $resireRequestId]);

            // Check if the delete was successful
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to delete original Re-Sire request.");
            }

            // Commit the transaction
            $pdo->commit();

            return new JsonResponse(200, "Re-Sire joined successfully.");
        } catch (Exception $e) {
            // Roll back the transaction in case of an exception
            $pdo->rollBack();
            error_log("Error joining Re-Sire: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while joining the Re-Sire: " . $e->getMessage());
        }
    }

    public static function declineResire(PDO $pdo, $playerUUID)
    {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // Get the player ID
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);

            // Check if the player exists
            if (!$playerId) {
                $pdo->rollBack();
                return new JsonResponse(400, "Invalid player UUID.");
            }

            // Get the pending Re-Sire request for the player
            $query = "SELECT id, current_sire_id, root_sire_old_sire_id FROM resire_requests WHERE player_id = :playerId AND status = 'pending' LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $resireRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if a pending Re-Sire request exists for the player
            if (!$resireRequest) {
                $pdo->rollBack();
                return new JsonResponse(400, "No pending Re-Sire request found for the player.");
            }

            $resireRequestId = $resireRequest['id'];
            $rootSireOldSireId = $resireRequest['root_sire_old_sire_id'];

            // Get the root sire's old sire bloodline ID and generation
            $rootSireOldSireUUID = PlayerDataController::getPlayerUuidById($pdo, $rootSireOldSireId);
            $rootSireOldSireBloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $rootSireOldSireUUID);
            $rootSireOldSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $rootSireOldSireUUID);

            // Update the player's sire to the root sire's old sire
            if (!self::updatePlayerSire($pdo, $playerId, $rootSireOldSireId, $rootSireOldSireBloodlineId, $rootSireOldSireGeneration + 1)) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to update player's sire to the root sire's old sire.");
            }

            // Move the request to history table
            $moveToHistoryQuery = "INSERT INTO resire_requests_history
            (original_request_id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, status, completed_date)
            SELECT id, player_id, current_sire_id, root_sire_old_sire_id, request_date, deadline_date, 'rejected', NOW()
            FROM resire_requests WHERE id = :resireRequestId";
            $stmt = $pdo->prepare($moveToHistoryQuery);
            $stmt->execute([':resireRequestId' => $resireRequestId]);

            // Check if the move was successful
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to move Re-Sire request to history.");
            }

            // Delete the original request
            $deleteQuery = "DELETE FROM resire_requests WHERE id = :resireRequestId";
            $stmt = $pdo->prepare($deleteQuery);
            $stmt->execute([':resireRequestId' => $resireRequestId]);

            // Check if the delete was successful
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to delete original Re-Sire request.");
            }

            // Commit the transaction
            $pdo->commit();

            return new JsonResponse(200, "Re-Sire declined successfully.");
        } catch (Exception $e) {
            // Roll back the transaction in case of an exception
            $pdo->rollBack();
            error_log("Error declining Re-Sire: " . $e->getMessage());
            return new JsonResponse(500, "An error occurred while declining the Re-Sire: " . $e->getMessage());
        }
    }

    // Methods for changing house type and species in a house using the player's UUID
    public static function changeHouseTypeAndSpecies(PDO $pdo, $houseOwnerUuid, $newSpeciesId)
    {

        try {
            // Verify the species_id exists
            $speciesQuery = "SELECT COUNT(*) FROM species WHERE species_id = :speciesId";
            $stmt = $pdo->prepare($speciesQuery);
            $stmt->execute([':speciesId' => $newSpeciesId]);
            if ($stmt->fetchColumn() == 0) {
                // Invalid species id
                error_log("Invalid species id: $newSpeciesId");

                return false;
            }

            // Get the corresponding house_type_id
            $houseTypeQuery = "SELECT house_type_id FROM house_types WHERE species_id = :speciesId";
            $stmt = $pdo->prepare($houseTypeQuery);
            $stmt->execute([':speciesId' => $newSpeciesId]);
            if ($stmt->rowCount() === 0) {
                // No house type found for this species
                error_log("No house type found for species: $newSpeciesId");
                return false;
            }
            $newHouseTypeId = $stmt->fetchColumn();

            // Update the house record
            $updateHouseQuery = "UPDATE houses
                             SET species_id = :speciesId, house_type_id = :houseTypeId
                             WHERE house_owner_uuid = :ownerUuid";
            $stmt = $pdo->prepare($updateHouseQuery);
            $stmt->execute([
                ':speciesId' => $newSpeciesId,
                ':houseTypeId' => $newHouseTypeId,
                ':ownerUuid' => $houseOwnerUuid,
            ]);

            // Check if the update was successful
            if ($stmt->rowCount() == 0) {
                // No house found with this owner UUID
                error_log("No house found with owner UUID: $houseOwnerUuid");
                return false;
            }

            return true;
        } catch (Exception $e) {
            // An error occurred
            error_log("Error changing house type and species: " . $e->getMessage());
            return false;
        }
    }

    public static function isHouseOwner(PDO $pdo, $playerUuid)
    {
        try {
            // Prepare the query to check if the player is a house owner
            $query = "SELECT COUNT(*) FROM houses WHERE house_owner_uuid = :playerUuid";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':playerUuid' => $playerUuid]);

            // Fetch the result
            $count = $stmt->fetchColumn();

            // If count is greater than 0, the player is a house owner
            return $count > 0;
        } catch (Exception $e) {
            // If an error occurs, return false
            return false;
        }
    }

    public static function calculateResirePrice($pdo, $playerUUID, $newSireUUID)
    {
        try {
            // Get player's current species and generation
            $playerSpecies = PlayerDataController::getPlayerSpecies($pdo, $playerUUID);
            $playerGeneration = PlayerDataController::getPlayerGeneration($pdo, $playerUUID);

            // Get new sire's species and generation
            $newSireSpecies = PlayerDataController::getPlayerSpecies($pdo, $newSireUUID);
            $newSireGeneration = PlayerDataController::getPlayerGeneration($pdo, $newSireUUID);

            // Calculate player's new generation after resire
            $playerNewGeneration = $newSireGeneration + 1;

            // Get player's number of descendants
            $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            $descendantCount = self::getDescendantCount($pdo, $playerId);

            // Set base price
            $basePrice = 1000;

            // Calculate species change price
            $speciesChangePrice = ($playerSpecies !== $newSireSpecies) ? 1000 : 0;

            // Calculate generation change
            $genChange = $playerNewGeneration - $playerGeneration;
            $genPrice = 0;
            if ($genChange != 0) {
                $genPrice = abs($genChange) * 100;
            }

            // Calculate total price
            $totalPrice = $basePrice + $speciesChangePrice + $genPrice;

            $resireInfo = [
                'basePrice' => $basePrice,
                'speciesChangePrice' => $speciesChangePrice,
                'genChange' => $genChange,
                'genPrice' => $genPrice,
                'descendantCount' => $descendantCount,
                'totalPrice' => $totalPrice,
                'playerSpecies' => $playerSpecies,
                'newSireSpecies' => $newSireSpecies,
                'playerCurrentGeneration' => $playerGeneration,
                'newSireGeneration' => $newSireGeneration,
                'playerNewGeneration' => $playerNewGeneration,
            ];

            return new JsonResponse(200, "Resire price calculated successfully.", $resireInfo);
        } catch (Exception $e) {
            error_log("Error calculating resire price: " . $e->getMessage());
            return new JsonResponse(500, "Error calculating resire price: " . $e->getMessage());
        }
    }

    // Helper function to get the number of descendants
    private static function getDescendantCount($pdo, $playerId)
    {
        $query = "WITH RECURSIVE descendants AS (
            SELECT player_id
            FROM players
            WHERE sire_id = :playerId
            UNION ALL
            SELECT p.player_id
            FROM players p
            INNER JOIN descendants d ON p.sire_id = d.player_id
        )
        SELECT COUNT(*) FROM descendants";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public static function isBloodlineLeader(PDO $pdo, $playerId)
    {
        $query = "SELECT COUNT(*) FROM bloodlines WHERE bloodline_leader_id = :playerId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }
    public static function getDirectDescendants(PDO $pdo, $playerId)
    {
        $query = "SELECT player_id FROM players WHERE sire_id = :playerId";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function getDaysRemainingForResireRequest($pdo, $playerUUID) {
        // Query to get the deadline_date for the given player's pending resire request
        $query = "SELECT rr.deadline_date 
                  FROM resire_requests rr
                  JOIN players p ON rr.player_id = p.player_id
                  WHERE p.player_uuid = :playerUUID 
                  AND rr.status = 'pending'
                  ORDER BY rr.request_date DESC
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':playerUUID' => $playerUUID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null; // No pending request found for this player
        }

        $deadlineDate = new \DateTime($result['deadline_date']);
        $currentDate = new \DateTime();

        // Calculate the difference in days
        $interval = $currentDate->diff($deadlineDate);
        $daysRemaining = $interval->days;

        // If the deadline has passed, return 0
        if ($currentDate > $deadlineDate) {
            return 0;
        }

        // Ensure the maximum is 15 days
        return min($daysRemaining, 15);
    }
}
