<?php

namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;
use Exception;
///class
class ClanController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    public static function fetchClanDetailsForPlayer($pdo, $playerUUID)
    {
        try
        {
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);
            $clanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUUID);

            if (!$clanId)
            {
                return new JsonResponse(404, "Player not found or not part of any clan.");
            }

            // Fetch the clan details
            $clanDetailsQuery = $pdo->prepare("SELECT * FROM clans WHERE clan_id = :clanId");
            $clanDetailsQuery->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $clanDetailsQuery->execute();
            $clanDetails = $clanDetailsQuery->fetch(PDO::FETCH_ASSOC);

            if (!$clanDetails)
            {
                return new JsonResponse(404, "Clan details not found.");
            }

            // Fetch the player's status details and add them to the clan details array
            $playerStatusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
            if ($playerStatusId !== false)
            {
                $statusDetails = PlayerDataController::getPlayerStatusById($pdo, $playerStatusId);
                if ($statusDetails['status'] === 200)
                {
                    $clanDetails['player_status_keyword'] = $statusDetails['player_status_keyword'];
                    $clanDetails['player_current_status'] = $statusDetails['player_current_status'];
                }
            }

            // Add total counts and clan role keyword to the details
            $clanDetails['total_members'] = ClanHelperController::countMembersInClan($pdo, $clanId);
            $clanDetails['total_houses'] = ClanHelperController::countHousesInClan($pdo, $clanId);
            $clanDetails['clan_role_keyword'] = $clanRoleId ? ClanHelperController::getClanRoleKeyword($pdo, $clanRoleId) : null;

            // Fetch the version number for 'clan books'
            $clanBooksVersion = VersionsController::getVersionNumberByName($pdo, 'clan book');
            $clanDetails['clan_books_version'] = $clanBooksVersion;

            // *** New Addition: Retrieve and add the bloodline name ***
            if (isset($clanDetails['bloodline_id']) && !empty($clanDetails['bloodline_id']))
            {
                $clanDetails['bloodline_name'] = BloodlineHelperController::getBloodlineNameById($pdo, $clanDetails['bloodline_id']);
            } else
            {
                $clanDetails['bloodline_name'] = "Renegade";
            }

            // Replace any null values with empty strings
            $clanDetails = self::replaceNullsWithEmptyString($clanDetails);

            return new JsonResponse(200, "Clan details retrieved successfully", $clanDetails);
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }


    private static function replaceNullsWithEmptyString($data)
    {
        if (is_array($data))
        {
            foreach ($data as $key => $value)
            {
                if (is_null($value))
                {
                    $data[$key] = "";
                } else
                {
                    $data[$key] = self::replaceNullsWithEmptyString($value);
                }
            }
        } elseif (is_object($data))
        {
            foreach ($data as $key => $value)
            {
                if (is_null($value))
                {
                    $data->$key = "";
                } else
                {
                    $data->$key = self::replaceNullsWithEmptyString($value);
                }
            }
        }
        return $data;
    }

    // Rename clan name to new name in clans table
    public static function renameClan($pdo, $playerUUID, $newName)
    {
        try
        {
            // Check if the new name is not too long
            if (mb_strlen($newName) > 35)
            {
                return new JsonResponse(400, "Clan name must not exceed 35 characters.");
            }

            // Get the clan_id from the player's data
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);
            if (!$clanId)
            {
                return new JsonResponse(404, "Clan not found for the player.");
            }

            // Get the player's clan role ID
            $clanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUUID);
            if (!$clanRoleId)
            {
                return new JsonResponse(403, "Player role not found or player not part of any clan.");
            }

            // Get the clan role keyword
            $clanRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $clanRoleId);
            if (!in_array($clanRoleKeyword, ['clan_owner', 'clan_co_owner']))
            {
                return new JsonResponse(403, "Only the clan owner or co-owner can rename the clan.");
            }

            // Check if the new name is already in use
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM clans WHERE clan_name = :newName");
            $checkQuery->bindParam(':newName', $newName, PDO::PARAM_STR);
            $checkQuery->execute();

            if ($checkQuery->fetchColumn() > 0)
            {
                return new JsonResponse(409, "Clan name '$newName' is already in use.");
            }

            // Rename the clan
            $updateQuery = $pdo->prepare("UPDATE clans SET clan_name = :newName WHERE clan_id = :clanId");
            $updateQuery->bindParam(':newName', $newName, PDO::PARAM_STR);
            $updateQuery->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $updateQuery->execute();

            if ($updateQuery->rowCount() > 0)
            {
                return new JsonResponse(200, "Clan name updated successfully.");
            } else
            {
                return new JsonResponse(404, "No update made. Clan not found or name unchanged.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Error updating clan name: " . $e->getMessage());
        }
    }

    public static function addHouseToClan($pdo, $requestingPlayerUUID, $houseOwnerUUID)
    {
        try
        {

            // Check if the requesting player is the clan owner or co-owner 
            $requestingPlayerClanRoleId = ClanHelperController::getClanRoleId($pdo, $requestingPlayerUUID);
            $requestingPlayerRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $requestingPlayerClanRoleId);
            if (!in_array($requestingPlayerRoleKeyword, ['clan_owner', 'clan_co_owner', 'clan_officer_1']))
            {
                return new JsonResponse(403, "Only the clan owner or co-owner can add a house to the clan.");
            }

            // Check if the house owner is actually the owner of the house
            $houseOwnerRole = HouseHelperController::getCurrentHouseRole($pdo, $houseOwnerUUID);
            if ($houseOwnerRole !== 'house_owner')
            {
                return new JsonResponse(403, "Only the house owner can add the house to a clan.");
            }

            // Get the clan ID of the requesting player
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $requestingPlayerUUID);
            if ($clanId === null)
            {
                return new JsonResponse(404, "Requesting player is not part of any clan.");
            }

            // Get the house ID owned by the house owner
            $houseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $houseOwnerUUID);
            if ($houseId === null)
            {
                return new JsonResponse(404, "House not found for the house owner.");
            }

            // Ensure no house members hold significant roles in any clan
            if (!ClanHelperController::checkHouseMembersForClanRoles($pdo, $houseId))
            {
                return new JsonResponse(403, "A member of the house holds a significant role in another clan.");
            }

            // Update the clan_id of the house
            $updateSuccessful = HouseHelperController::updateHouseClanId($pdo, $houseId, $clanId);
            if ($updateSuccessful)
            {
                return new JsonResponse(200, "House successfully added to the clan.");
            } else
            {
                return new JsonResponse(404, "Failed to add the house to the clan.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Error adding house to clan: " . $e->getMessage());
        }
    }

    // Remove house from clan by clan owner or co-owner of the house
    public static function removeHouseFromClan($pdo, $requestingPlayerUUID, $houseOwnerUUID)
    {
        try
        {

            // Check if the requesting player is the clan owner or co-owner
            $requestingPlayerClanRoleId = ClanHelperController::getClanRoleId($pdo, $requestingPlayerUUID);
            $requestingPlayerRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $requestingPlayerClanRoleId);
            if (!in_array($requestingPlayerRoleKeyword, ['clan_owner', 'clan_co_owner']))
            {
                return new JsonResponse(403, "Only the clan owner or co-owner can remove houses from the clan.");
            }

            // Get the clan ID of the requesting player
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $requestingPlayerUUID);

            // Get the house ID owned by the house owner
            $houseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $houseOwnerUUID);
            if ($houseId === null)
            {
                return new JsonResponse(404, "House not found for the house owner.");
            }

            // Check if the house is in the clan
            $currentClanId = HouseHelperController::getCurrentHouseClanId($pdo, $houseId);
            if ($currentClanId !== $clanId)
            {
                return new JsonResponse(400, "House is not part of the specified clan.");
            }

            // Ensure no house members hold significant roles in the clan
            if (!ClanHelperController::checkHouseMembersForClanRoles($pdo, $houseId))
            {
                return new JsonResponse(403, "A member of the house holds a significant clan role.");
            }

            // Ensure no house members hold significant bloodline roles
            if (!ClanHelperController::checkHouseMembersForBloodlineRoles($pdo, $houseId))
            {
                return new JsonResponse(403, "A member of the house holds a significant bloodline role.");
            }

            // Remove the clan association from the house
            $updateSuccessful = HouseHelperController::removeClanAssociation($pdo, $houseId);
            if ($updateSuccessful)
            {
                return new JsonResponse(200, "House successfully removed from the clan.");
            } else
            {
                return new JsonResponse(404, "Failed to remove the house from the clan.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Error removing house from clan: " . $e->getMessage());
        }
    }


    public static function promoteClanMember($pdo, $requestingPlayerUUID, $targetPlayerUUID, $newRoleKeyword)
    {
        try
        {
            $pdo->beginTransaction(); // Start the transaction

            // Get the clan role ID and keyword of the requesting player
            $requestingPlayerClanRoleId = ClanHelperController::getClanRoleId($pdo, $requestingPlayerUUID);
            $requestingPlayerRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $requestingPlayerClanRoleId);

            // Define role hierarchy and permissions
            $rolePermissions = [
                'clan_owner' => ['clan_co_owner', 'clan_officer_1', 'clan_officer_2'],
                'clan_co_owner' => ['clan_officer_1', 'clan_officer_2']
            ];

            // Check if the requesting player has permission to assign the new role
            if (!isset($rolePermissions[$requestingPlayerRoleKeyword]) ||
                !in_array($newRoleKeyword, $rolePermissions[$requestingPlayerRoleKeyword]))
            {
                $pdo->rollBack();
                return new JsonResponse(403, "You do not have permission to assign this role.");
            }

            // Ensure the target player is in the same clan as the requesting player
            $requestingPlayerClanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $requestingPlayerUUID);
            $targetPlayerClanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $targetPlayerUUID);

            if ($requestingPlayerClanId !== $targetPlayerClanId)
            {
                $pdo->rollBack();
                return new JsonResponse(403, "player must be in the same clan.");
            }

            // Get the clan_role_id for the target player
            $targetPlayerRoleId = ClanHelperController::getClanRoleId($pdo, $targetPlayerUUID);

            // Check if the target player already holds a significant role
            if ($targetPlayerRoleId !== null)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Target player already holds a clan role.");
            }

            // Get the clan_role_id for the new role
            $newRoleId = ClanHelperController::getClanRoleIdByKeyword($pdo, $newRoleKeyword);
            if ($newRoleId === null)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "The specified role does not exist.");
            }

            // Update the target player's clan role
            $updateSuccessful = ClanHelperController::updatePlayerClanRole($pdo, $targetPlayerUUID, $newRoleId);
            if (!$updateSuccessful)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Target player not found or operation failed.");
            }

            // Get player name for the target player
            $playerName = PlayerDataController::getPlayerLegacyName($pdo, $targetPlayerUUID);

            // Update the clans table with the new role information
            $updateUuidSuccess = ClanHelperController::updateClanRoleUUID($pdo, $requestingPlayerClanId, $targetPlayerUUID, $newRoleKeyword);
            $updateNameSuccess = ClanHelperController::updateClanRoleName($pdo, $requestingPlayerClanId, $playerName, $newRoleKeyword);

            if ($updateUuidSuccess && $updateNameSuccess)
            {
                $pdo->commit();
                return new JsonResponse(200, "Player successfully promoted.");
            } else
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to update clan officer details.");
            }
        }
        catch (PDOException $e)
        {
            $pdo->rollBack(); // Roll back the transaction on error
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Error promoting clan member: " . $e->getMessage());
        }
    }
    public static function demoteClanMember($pdo, $requestingPlayerUUID, $targetPlayerUUID)
    {
        try
        {
            $pdo->beginTransaction(); // Start the transaction

            // Define role hierarchy and permissions for demotion
            $rolePermissions = [
                'clan_owner' => ['clan_co_owner', 'clan_officer_1', 'clan_officer_2'],
                'clan_co_owner' => ['clan_co_owner', 'clan_officer_1', 'clan_officer_2'],
                'clan_officer_1' => ['clan_officer_1'],
                'clan_officer_2' => ['clan_officer_2']
            ];

            // Get the clan role ID and keyword of the requesting player
            $requestingPlayerClanRoleId = ClanHelperController::getClanRoleId($pdo, $requestingPlayerUUID);
            $requestingPlayerRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $requestingPlayerClanRoleId);

            // Check if the requesting player has permission to demote
            if (!isset($rolePermissions[$requestingPlayerRoleKeyword]))
            {
                $pdo->rollBack();
                return new JsonResponse(403, "You do not have permission to demote.");
            }

            // Ensure the target player is in the same clan as the requesting player
            $requestingPlayerClanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $requestingPlayerUUID);
            $targetPlayerClanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $targetPlayerUUID);

            if ($requestingPlayerClanId !== $targetPlayerClanId)
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Players must be in the same clan.");
            }

            // Get the clan_role_id for the target player
            $targetPlayerRoleId = ClanHelperController::getClanRoleId($pdo, $targetPlayerUUID);
            $targetPlayerRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $targetPlayerRoleId);

            // Check if the target player can be demoted based on the role permissions
            if (!in_array($targetPlayerRoleKeyword, $rolePermissions[$requestingPlayerRoleKeyword]))
            {
                $pdo->rollBack();
                return new JsonResponse(403, "You do not have permission to demote this player.");
            }

            // Demote the target player by setting their clan_role_id to null
            $demoteSuccessful = ClanHelperController::updatePlayerClanRole($pdo, $targetPlayerUUID, null);
            if (!$demoteSuccessful)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Target player not found or operation failed.");
            }

            // Reset the name and UUID in the clans table for the demoted role
            $resetUuidSuccess = ClanHelperController::updateClanRoleUUID($pdo, $requestingPlayerClanId, null, $targetPlayerRoleKeyword);
            $resetNameSuccess = ClanHelperController::updateClanRoleName($pdo, $requestingPlayerClanId, null, $targetPlayerRoleKeyword);

            if ($resetUuidSuccess && $resetNameSuccess)
            {
                $pdo->commit();
                return new JsonResponse(200, "Player successfully demoted.");
            } else
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to reset clan officer details.");
            }
        }
        catch (PDOException $e)
        {
            $pdo->rollBack(); // Roll back the transaction on error
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Error demoting clan member: " . $e->getMessage());
        }
    }

    public static function getHousesInClanWithDetails($pdo, $playerUUID, $url, $batchSize = 1)
    {
        try
        {
            // First, get the clan ID for the given player
            $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);

            // If no clan ID is found, return an error
            if (!$clanId)
            {
                return new JsonResponse(400, 'No clan found for the specified player.');
            }

            // Use a CTE for fetching house details in the clan
            $query = "
                WITH house_details AS (
                    SELECT
                        h.house_id,
                        h.house_name,
                        ht.house_type_name,
                        h.house_owner_uuid,
                        h.house_officer_1_uuid,
                        h.house_officer_2_uuid
                    FROM houses h
                    LEFT JOIN house_types ht ON h.house_type_id = ht.house_type_id
                    WHERE h.clan_id = :clanId
                )
                SELECT * FROM house_details;
            ";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $stmt->execute();

            $houses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$houses)
            {
                return new JsonResponse(400, 'No houses found for the specified clan.');
            }
            // Log the fetched data for inspection
            //error_log("Fetched data: " . print_r($houses, true));

            // Split houses array into batches
            $batches = array_chunk($houses, $batchSize);
            foreach ($batches as $batch)
            {
                $batchWithMessage = [
                    "message" => "House details batch",
                    "data" => $batch,
                ];

                // Replace null values with empty strings
                $batchWithMessage = self::replaceNullsWithEmptyString($batchWithMessage);
                // Send each batch to the specified URL
                $response = CommunicationController::sendDataToURL($url, $batchWithMessage);
                //error_log("Batch response: " . json_encode($response)); // Logging the response for debugging
            }

            return new JsonResponse(200, 'Houses in clan processed successfully.');

        }
        catch (PDOException $e)
        {
            error_log("PDOException in getHousesInClanWithDetails: " . $e->getMessage());
            return new JsonResponse(500, 'Database error: ' . $e->getMessage());
        }
        catch (Exception $e)
        {
            error_log("Exception in getHousesInClanWithDetails: " . $e->getMessage());
            return new JsonResponse(500, 'Internal server error: ' . $e->getMessage());
        }
    }



}
