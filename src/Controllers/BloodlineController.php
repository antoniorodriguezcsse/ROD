<?php

namespace Fallen\SecondLife\Controllers;

use PDO;
use PDOException;
use Exception;
use Fallen\SecondLife\Classes\JsonResponse;

class BloodlineController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // 1) Return bloodline details for a given player (like fetchClanDetailsForPlayer)
    // -------------------------------------------------------------------------
    public static function fetchBloodlineDetailsForPlayer(PDO $pdo, string $playerUUID)
    {
        try
        {
            // 1) Loyalty lookup via clan → bloodline
            $bloodlineId = BloodlineHelperController::getLoyalBloodlineIdByPlayerUUID($pdo, $playerUUID);
            if (!$bloodlineId)
            {
                return new JsonResponse(404, "Player not found or their clan isn’t part of any bloodline.");
            }

            // 2) Privilege check
            if (!BloodlineHelperController::isPrivilegedInBloodline($pdo, $bloodlineId, $playerUUID))
            {
                return new JsonResponse(403, "You do not have permission to view this bloodline’s details.");
            }


            // 2) Grab the record from `bloodlines` table (which now has founder, owner, co-owner, officers, etc.)
            $stmt = $pdo->prepare("SELECT * FROM bloodlines WHERE bloodline_id = :bloodlineId LIMIT 1");
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            $bloodlineDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bloodlineDetails)
            {
                return new JsonResponse(404, "Bloodline details not found.");
            }

            // 3) Count total clans in this bloodline + total members
            $bloodlineDetails['total_clans'] = BloodlineHelperController::countClansInBloodline($pdo, $bloodlineId);
            $bloodlineDetails['total_members'] = BloodlineHelperController::countMembersInBloodline($pdo, $bloodlineId);

            // 4) Find player's bloodline role (e.g. 'bloodline_owner', 'bloodline_officer_1', etc.)
            $bloodlineRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $playerUUID);
            $roleKeyword = null;
            if ($bloodlineRoleId)
            {
                $roleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, $bloodlineRoleId);
            }
            $bloodlineDetails['bloodline_role_keyword'] = $roleKeyword ?: '';

            // 5) Version number for “bloodline books” (if you maintain a 'versions' table)
            $bloodlineDetails['bloodline_books_version'] = VersionsController::getVersionNumberByName($pdo, 'bloodline books') ?? '';

            // 6) Possibly fetch the player's status (alive, dead, etc.)
            $playerStatusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
            if ($playerStatusId !== false)
            {
                $statusInfo = PlayerDataController::getPlayerStatusById($pdo, $playerStatusId);
                if (isset($statusInfo['status']) && $statusInfo['status'] === 200)
                {
                    $bloodlineDetails['player_status_keyword'] = $statusInfo['player_status_keyword'];
                    $bloodlineDetails['player_current_status'] = $statusInfo['player_current_status'];
                }
            }

            // 7) If your bloodlines table has a 'species_id' column, fetch dynamic role labels (like 'Arch Vampire')
            if (!empty($bloodlineDetails['species_id']))
            {
                $speciesId = (int) $bloodlineDetails['species_id'];
                $bloodlineDetails['bloodline_roles'] = BloodlineHelperController::fetchBloodlineRolesForSpecies($pdo, $speciesId);
            } else
            {
                $bloodlineDetails['bloodline_roles'] = [];
            }

            // 8) Convert any null fields to empty string
            $bloodlineDetails = self::replaceNullsWithEmptyString($bloodlineDetails);

            // 9) Return final JSON
            return new JsonResponse(200, "Bloodline details retrieved successfully.", $bloodlineDetails);
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }



    // Simple helper to replace nulls with empty strings
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

    // -------------------------------------------------------------------------
    // 2) Rename a bloodline (like renameClan)
    // -------------------------------------------------------------------------
    public static function renameBloodline(PDO $pdo, string $playerUUID, string $newName)
    {
        try
        {
            // 1) Ensure length constraints
            if (mb_strlen($newName) > 35)
            {
                return new JsonResponse(400, "Bloodline name must not exceed 35 characters.");
            }

            // 2) Find the player’s bloodline
            $bloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $playerUUID);
            if (!$bloodlineId)
            {
                return new JsonResponse(404, "Player not found or not part of any bloodline.");
            }

            // 3) Check if the player is “bloodline_owner” or “bloodline_officer_1”, etc. 
            //    Only owners might rename. Adjust as needed.
            $roleId = BloodlineHelperController::getBloodlineRoleId($pdo, $playerUUID);
            if (!$roleId)
            {
                return new JsonResponse(403, "Player has no bloodline role.");
            }
            $roleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, $roleId);
            // Let’s say only “bloodline_owner” can rename:
            if ($roleKeyword !== 'bloodline_owner')
            {
                return new JsonResponse(403, "Only the bloodline owner can rename the bloodline.");
            }

            // 4) Check if the new name is already taken
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bloodlines WHERE bloodline_name = :newName");
            $checkStmt->execute([':newName' => $newName]);
            if ($checkStmt->fetchColumn() > 0)
            {
                return new JsonResponse(409, "Bloodline name '{$newName}' is already in use.");
            }

            // 5) Update
            $updateStmt = $pdo->prepare("
                UPDATE bloodlines
                SET bloodline_name = :newName
                WHERE bloodline_id = :bloodlineId
            ");
            $updateStmt->execute([
                ':newName' => $newName,
                ':bloodlineId' => $bloodlineId
            ]);

            if ($updateStmt->rowCount() > 0)
            {
                return new JsonResponse(200, "Bloodline name updated successfully.");
            } else
            {
                return new JsonResponse(404, "No update made; bloodline not found or name unchanged.");
            }
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Error updating bloodline name: " . $e->getMessage());
        }
    }

    public static function addClanToBloodline(PDO $pdo, string $requestingPlayerUUID, string $clanOwnerUUID)
    {
        try
        {
            // 1) Check if the requesting player is indeed a bloodline owner.
            $requestingRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $requestingPlayerUUID);
            $requestingRoleKeyword = $requestingRoleId
                ? BloodlineHelperController::getBloodlineRoleKeyword($pdo, $requestingRoleId)
                : null;
            if ($requestingRoleKeyword !== 'bloodline_owner')
            {
                return new JsonResponse(403, "Only the bloodline owner can add a clan to the bloodline.");
            }

            // 2) Get the bloodline of the requesting player.
            $bloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $requestingPlayerUUID);
            if (!$bloodlineId)
            {
                return new JsonResponse(404, "Requesting player is not part of any bloodline.");
            }

            // 3) Retrieve the clan record by clan owner UUID.
            $clanStmt = $pdo->prepare("SELECT clan_id, bloodline_id, clan_owner_uuid FROM clans WHERE clan_owner_uuid = :clanOwnerUUID");
            $clanStmt->execute([':clanOwnerUUID' => $clanOwnerUUID]);
            $clanRow = $clanStmt->fetch(PDO::FETCH_ASSOC);
            if (!$clanRow)
            {
                return new JsonResponse(404, "No clan found for the provided clan owner.");
            }
            $clanId = (int) $clanRow['clan_id'];

            // 4) Ensure the clan is not already part of a bloodline.
            if ($clanRow['bloodline_id'])
            {
                return new JsonResponse(400, "Clan is already part of a bloodline.");
            }

            // 5) Verify the owner UUID matches the provided clanOwnerUUID.
            if ($clanRow['clan_owner_uuid'] !== $clanOwnerUUID)
            {
                return new JsonResponse(403, "Provided clan owner UUID does not match the clan's owner.");
            }

            // 6) Update the clan to point to this bloodline.
            $updateStmt = $pdo->prepare("
            UPDATE clans
            SET bloodline_id = :bloodlineId
            WHERE clan_id = :clanId
        ");
            $updateStmt->execute([
                ':bloodlineId' => $bloodlineId,
                ':clanId' => $clanId,
            ]);

            if ($updateStmt->rowCount() > 0)
            {
                return new JsonResponse(200, "Clan successfully added to the bloodline.");
            } else
            {
                return new JsonResponse(404, "Failed to add clan to bloodline or no rows changed.");
            }
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Error adding clan to bloodline: " . $e->getMessage());
        }
    }




    // -------------------------------------------------------------------------
    // 4) Remove a clan from the bloodline (like removeHouseFromClan)
    // -------------------------------------------------------------------------
    public static function removeClanFromBloodline(PDO $pdo, string $requestingPlayerUUID, string $clanOwnerUUID)
    {
        try
        {
            // 1) Check if the requesting player is the bloodline owner.
            $roleId = BloodlineHelperController::getBloodlineRoleId($pdo, $requestingPlayerUUID);
            $roleKeyword = $roleId ? BloodlineHelperController::getBloodlineRoleKeyword($pdo, $roleId) : null;
            if ($roleKeyword !== 'bloodline_owner')
            {
                return new JsonResponse(403, "Only the bloodline owner can remove clans.");
            }

            // 2) Get the bloodline of the requesting player.
            $requestorBloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $requestingPlayerUUID);
            if (!$requestorBloodlineId)
            {
                return new JsonResponse(404, "Requesting player is not in any bloodline.");
            }

            // 3) Retrieve the clan record by clan owner UUID.
            $clanStmt = $pdo->prepare("SELECT clan_id, bloodline_id, clan_owner_uuid FROM clans WHERE clan_owner_uuid = :clanOwnerUUID");
            $clanStmt->execute([':clanOwnerUUID' => $clanOwnerUUID]);
            $clanRow = $clanStmt->fetch(PDO::FETCH_ASSOC);
            if (!$clanRow)
            {
                return new JsonResponse(404, "No clan found for the provided clan owner.");
            }
            $clanId = (int) $clanRow['clan_id'];

            // 4) Check if the clan belongs to the same bloodline as the requesting player.
            if ((int) $clanRow['bloodline_id'] !== (int) $requestorBloodlineId)
            {
                return new JsonResponse(400, "Clan is not part of your bloodline.");
            }

            // 5) Disassociate the clan from the bloodline by setting bloodline_id to NULL.
            $updateStmt = $pdo->prepare("
            UPDATE clans
            SET bloodline_id = NULL
            WHERE clan_id = :clanId
        ");
            $updateStmt->execute([':clanId' => $clanId]);

            if ($updateStmt->rowCount() > 0)
            {
                return new JsonResponse(200, "Clan successfully removed from your bloodline.");
            } else
            {
                return new JsonResponse(404, "Failed to remove clan or no rows changed.");
            }
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Error removing clan from bloodline: " . $e->getMessage());
        }
    }


    // -------------------------------------------------------------------------
    // 5) Promote a player to some bloodline role (like promoteClanMember)
    // -------------------------------------------------------------------------
    public static function promoteBloodlineMember(PDO $pdo, string $requestingPlayerUUID, string $targetPlayerUUID, string $newRoleKeyword)
    {
        try
        {
            $pdo->beginTransaction();

            // 1) Ensure requestor is “bloodline_owner,” or some top role
            $reqRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $requestingPlayerUUID);
            $reqRoleKeyword = $reqRoleId
                ? BloodlineHelperController::getBloodlineRoleKeyword($pdo, $reqRoleId)
                : null;

            // Let’s say only the “bloodline_owner” can promote
            if ($reqRoleKeyword !== 'bloodline_owner')
            {
                $pdo->rollBack();
                return new JsonResponse(403, "You do not have permission to promote.");
            }

            // 2) Make sure they’re in the same bloodline
            $reqBloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $requestingPlayerUUID);
            $tgtBloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $targetPlayerUUID);

            if (!$reqBloodlineId || !$tgtBloodlineId || $reqBloodlineId !== $tgtBloodlineId)
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Target player must be in the same bloodline.");
            }

            // 3) Possibly check if target player already has a major role
            $tgtRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $targetPlayerUUID);
            if ($tgtRoleId)
            {
                // They already hold a bloodline role
                // If your logic forbids multiple roles, handle that. E.g.:
                $pdo->rollBack();
                return new JsonResponse(400, "Target player already holds a bloodline role.");
            }

            // 4) We need the numeric role ID from player_role_bloodline
            //    But also need that player's species ID to match the role. 
            $playerSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $targetPlayerUUID);
            if (!$playerSpeciesId)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Target player species not found.");
            }

            $newRoleId = BloodlineHelperController::getBloodlineRoleIdByKeyword($pdo, $newRoleKeyword, $playerSpeciesId);
            if (!$newRoleId)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Role not found for the given keyword/species.");
            }

            // 5) Assign the new role to the target player
            $success = BloodlineHelperController::updatePlayerBloodlineRole($pdo, $targetPlayerUUID, $newRoleId);
            if (!$success)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Target player not found or operation failed.");
            }

            // 6) Also update the bloodlines table with the new officer/owner name/uuid
            $bloodlineId = $reqBloodlineId;
            $playerName = PlayerDataController::getPlayerLegacyName($pdo, $targetPlayerUUID);

            $uuidSuccess = BloodlineHelperController::updateBloodlineRoleUUID($pdo, $bloodlineId, $targetPlayerUUID, $newRoleKeyword);
            $nameSuccess = BloodlineHelperController::updateBloodlineRoleName($pdo, $bloodlineId, $playerName, $newRoleKeyword);

            if ($uuidSuccess && $nameSuccess)
            {
                $pdo->commit();
                return new JsonResponse(200, "Player promoted successfully.");
            } else
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to update the bloodline's role columns.");
            }
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Error promoting bloodline member: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 6) Demote a bloodline member (like demoteClanMember)
    // -------------------------------------------------------------------------
    public static function demoteBloodlineMember(PDO $pdo, string $requestingPlayerUUID, string $targetPlayerUUID)
    {
        try
        {
            $pdo->beginTransaction();

            // 1) Requesting player must be the bloodline_owner
            $reqRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $requestingPlayerUUID);
            $reqRoleKeyword = $reqRoleId
                ? BloodlineHelperController::getBloodlineRoleKeyword($pdo, $reqRoleId)
                : null;
            if ($reqRoleKeyword !== 'bloodline_owner')
            {
                $pdo->rollBack();
                return new JsonResponse(403, "You do not have permission to demote.");
            }

            // 2) Must be in the same bloodline
            $reqBloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $requestingPlayerUUID);
            $tgtBloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $targetPlayerUUID);

            if ($reqBloodlineId !== $tgtBloodlineId)
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Both players must be in the same bloodline.");
            }

            // 3) Get the target's current role
            $tgtRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $targetPlayerUUID);
            if (!$tgtRoleId)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Target player does not hold any bloodline role.");
            }
            $tgtRoleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, $tgtRoleId);

            // 4) Demote them by setting their bloodline_role_id = null
            $demoteOk = BloodlineHelperController::updatePlayerBloodlineRole($pdo, $targetPlayerUUID, null);
            if (!$demoteOk)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Target player not found or operation failed.");
            }

            // 5) Clear the name/uuid in the bloodlines table
            $uuidSuccess = BloodlineHelperController::updateBloodlineRoleUUID($pdo, $reqBloodlineId, null, $tgtRoleKeyword);
            $nameSuccess = BloodlineHelperController::updateBloodlineRoleName($pdo, $reqBloodlineId, null, $tgtRoleKeyword);

            if ($uuidSuccess && $nameSuccess)
            {
                $pdo->commit();
                return new JsonResponse(200, "Player demoted successfully.");
            } else
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to reset bloodline role columns.");
            }
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Error demoting bloodline member: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // 7) Retrieve all clans in a bloodline, optionally in batches, like 
    //    getHousesInClanWithDetails in your clan code.
    // -------------------------------------------------------------------------
    public static function getClansInBloodlineWithDetails(PDO $pdo, string $playerUUID, string $url, int $batchSize = 10)
    {
        try
        {
            // 1) Determine the bloodline of the requesting player.
            $bloodlineId = BloodlineHelperController::getBloodlineIdByPlayerUUID($pdo, $playerUUID);
            if (!$bloodlineId)
            {
                return new JsonResponse(400, "No bloodline found for that player.");
            }

            // 2) Retrieve all clans belonging to this bloodline.
            $sql = "
            SELECT
                clan_id,
                clan_name,
                clan_founder_uuid,
                clan_owner_uuid,
                clan_co_owner_uuid,
                clan_officer_1_uuid,
                clan_officer_2_uuid,
                clan_created_date
            FROM clans
            WHERE bloodline_id = :bloodlineId
        ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            $clans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$clans)
            {
                return new JsonResponse(400, "No clans found for this bloodline.");
            }

            // 3) For each clan, add the member count using the existing helper.
            foreach ($clans as &$clan)
            {
                $clan["member_count"] = ClanHelperController::countMembersInClan($pdo, $clan["clan_id"]);
            }
            unset($clan); // break reference

            // 4) Break the results into batches and send each batch to the specified URL.
            $batches = array_chunk($clans, $batchSize);
            foreach ($batches as $batch)
            {
                $batchData = [
                    "message" => "Clan details batch",
                    "data" => $batch,
                ];
                // Optionally, replace any null values with empty strings.
                $batchData = self::replaceNullsWithEmptyString($batchData);

                $response = CommunicationController::sendDataToURL($url, $batchData);
                // Optionally check $response status.
            }

            return new JsonResponse(200, "Clans in bloodline processed successfully.");
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
        catch (Exception $e)
        {
            return new JsonResponse(500, "Internal server error: " . $e->getMessage());
        }
    }


}
