<?php

namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

///class
class HouseController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function fetchHouseDetailsForPlayer($pdo, $playerUUID)
    {
        $houseDetails = [];

        // Fetch the house_id from the players table
        $houseId = TableController::getPlayerFieldValue($pdo, $playerUUID, "house_id");

        // Ensure house_id key is always set in houseDetails
        $houseDetails['house_id'] = $houseId ? $houseId : null;

        if ($houseId)
        {
            // Join with the house_types table to fetch house type name
            $houseDetailsQuery = $pdo->prepare("SELECT h.*, ht.house_type_name FROM houses h
                                                LEFT JOIN house_types ht ON h.house_type_id = ht.house_type_id
                                                WHERE h.house_id = ?");
            $houseDetailsQuery->execute([$houseId]);
            $houseDetails = $houseDetailsQuery->fetch(PDO::FETCH_ASSOC);

            if ($houseDetails && !isset($houseDetails['error']))
            {

                $playerCount = self::getMemberCount($pdo, $houseId);

                $houseDetails['member_count'] = $playerCount;

                // Add house type name to $houseDetails
                if (isset($houseDetails['house_type_name']))
                {
                    $houseDetails['house_type'] = $houseDetails['house_type_name'];
                }

                if (isset($houseDetails["clan_id"]))
                {
                    // Use the getClanNameById function to get the clan name
                    $clanName = ClanHelperController::getClanNameById($pdo, $houseDetails["clan_id"]);

                    if ($clanName !== null)
                    {
                        // Add the clan name to the houseDetails
                        $houseDetails['clan_name'] = $clanName;
                    }
                } else
                {
                    // Handle the case where the clan name couldn't be found
                    $houseDetails['clan_name'] = "None";
                }

                // Fetch the house species_id
                $houseSpeciesId = TableController::getFieldValue($pdo, "houses", "house_id", $houseId, "species_id");

                // Fetch house roles for the specific species
                $houseRolesQuery = $pdo->prepare("SELECT house_role_name, player_role_house_keyword FROM player_role_house WHERE species_id = ?");
                $houseRolesQuery->execute([$houseSpeciesId]);
                $houseRoles = $houseRolesQuery->fetchAll(PDO::FETCH_ASSOC);

                // Organize roles into a structured format
                $roles = [];
                foreach ($houseRoles as $role)
                {
                    $roles[$role['player_role_house_keyword']] = $role['house_role_name'];
                }
                $houseDetails['house_roles'] = $roles;
            }
        }

        // Fetch the house role details
        $houseRoleId = TableController::getPlayerFieldValue($pdo, $playerUUID, "house_role_id");
        if ($houseRoleId)
        {
            $houseRoleDetailsJson = TableController::getForeignKeyData($pdo, "players", "player_uuid", $playerUUID, "house_role_id", "player_role_house");
            $houseRoleDetails = json_decode($houseRoleDetailsJson, true);

            if (!isset($houseRoleDetails['error']))
            {
                $houseDetails = array_merge($houseDetails, $houseRoleDetails);
            }
        }

        // Fetch player status details
        $playerStatusId = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_status_id");
        if ($playerStatusId)
        {
            $playerStatusKeyword = TableController::getFieldValue($pdo, "player_status", "player_status_id", $playerStatusId, "player_status_keyword");
            $playerCurrentStatus = TableController::getFieldValue($pdo, "player_status", "player_status_id", $playerStatusId, "player_current_status");

            if ($playerStatusKeyword)
            {
                $houseDetails['player_status_keyword'] = $playerStatusKeyword;
            }

            if ($playerCurrentStatus)
            {
                $houseDetails['player_current_status'] = $playerCurrentStatus;
            }
        }

        //fetch key of person they are following
        $leadersID = PlayerDataController::getPlayerFollowingId($pdo, $playerUUID);
        $uuidOfLeader = TableController::getFieldValue($pdo, 'players', 'player_id', $leadersID, 'player_uuid');
        $houseDetails['uuid_of_leader'] = $uuidOfLeader;

        // Fetch the version number for "house books"
        $versionQuery = "SELECT version_number FROM version WHERE version_name = 'house books'";
        $versionStmt = $pdo->prepare($versionQuery);
        $versionStmt->execute();
        $versionResult = $versionStmt->fetch(PDO::FETCH_ASSOC);

        if ($versionResult)
        {
            $houseDetails['house_books_version'] = $versionResult['version_number'];
        } else
        {
            $houseDetails['house_books_version'] = "Version not found";
        }

        $houseDetails = self::replaceNullsWithEmptyString($houseDetails);
        return json_encode($houseDetails);
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

    public static function getPlayerCountInHouse($pdo, $houseId)
    {

        try
        {
            $query = "SELECT COUNT(*) as member_count FROM players WHERE house_id = :houseId";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) $result['member_count'];
        }
        catch (PDOException $e)
        {
            die('Database error: ' . $e->getMessage()); // or log it and return a default value or error code
        }
    }

    public static function renameHouse($pdo, $houseId, $newName)
    {
        // Check for empty or only whitespace name
        if (trim($newName) === "")
        {
            return new JsonResponse(400, "House name cannot be empty.");
        }

        try
        {
            // Check if the house with the given ID exists
            $checkQuery = "SELECT 1 FROM houses WHERE house_id = :houseId";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() == 0)
            {
                return new JsonResponse(404, "House with the given ID doesn't exist.");
            }

            // Prepare the SQL statement to rename the house
            $query = "UPDATE houses SET house_name = :newName WHERE house_id = :houseId";
            $stmt = $pdo->prepare($query);

            // Bind the parameters
            $stmt->bindParam(':newName', $newName, PDO::PARAM_STR);
            $stmt->bindParam(':houseId', $houseId, PDO::PARAM_INT);

            // Execute the query
            $stmt->execute();

            // Check if the name was updated
            if ($stmt->rowCount() > 0)
            {
                return new JsonResponse(200, "House renamed successfully.");
            } else
            {
                return new JsonResponse(400, "The new name is the same as the old one.");
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    public static function doesHouseExist($pdo, $houseID)
    {
        try
        {
            // Query to check if a house with the specified ID exists in the houses table
            $query = "SELECT 1 FROM houses WHERE house_id = :houseID LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $stmt->execute();

            // Return true if the house exists (i.e., the query result has one or more rows)
            // Otherwise, return false
            return $stmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            // If there's a database error, log the error for debugging purposes and return false
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function setOfficerForHouse($pdo, $playerUUID, $houseID, $officerPosition)
    {
        try
        {

            // Fetch player's legacy name and species ID from players table
            $playerDetailsQuery = "SELECT legacy_name, species_id FROM players WHERE player_uuid = :playerUUID";
            $detailsStmt = $pdo->prepare($playerDetailsQuery);
            $detailsStmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $detailsStmt->execute();

            $result = $detailsStmt->fetch(PDO::FETCH_ASSOC);
            if (!$result)
            {
                return ['status' => 404, 'message' => 'Player not found.'];
            }

            // Check if they are in the house.
            $checkHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUUID);

            if ($houseID != $checkHouseId)
            {
                return ['status' => 400, 'message' => 'Player not in house.'];
            }

            // Check if house owner.
            $currentRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);
            if ($currentRole == "house_owner")
            {
                return ['status' => 400, 'message' => "The house owner cannot hold an officer position."];
            }

            if ($currentRole == "house_officer_1" || $currentRole == "house_officer_2")
            {
                return ['status' => 400, 'message' => "The player is already set as an officer."];
            }

            $playerName = $result['legacy_name'];
            $playerSpeciesId = $result['species_id'];

            // Validate if the specified houseID exists and fetch its species ID.
            $houseQuery = "SELECT species_id FROM houses WHERE house_id = :houseID";
            $houseStmt = $pdo->prepare($houseQuery);
            $houseStmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $houseStmt->execute();

            $houseResult = $houseStmt->fetch(PDO::FETCH_ASSOC);
            if (!$houseResult)
            {
                return ['status' => 400, 'message' => "House with the given ID doesn't exist."];
            }

            $houseSpeciesId = $houseResult['species_id'];
            if ($playerSpeciesId != $houseSpeciesId)
            {
                return ['status' => 400, 'message' => "Player's species does not match the house's species."];
            }

            // Rest of the checks for player's current role in the house...

            // Fetch the role ID for the officer position based on species
            $officerRoleId = self::getRoleIdForKeyword($pdo, $playerUUID, $officerPosition);
            if (!$officerRoleId)
            {
                return ['status' => 400, 'message' => 'Officer role ID for the given species and position not found.'];
            }

            // Update house officer details
            $updateHouseOfficerQuery = "UPDATE houses SET {$officerPosition}_uuid = :playerUUID, {$officerPosition}_name = :playerName WHERE house_id = :houseID";
            $houseOfficerStmt = $pdo->prepare($updateHouseOfficerQuery);
            $houseOfficerStmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $houseOfficerStmt->bindParam(':playerName', $playerName, PDO::PARAM_STR);
            $houseOfficerStmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $houseOfficerStmt->execute();

            // Update player's house role ID
            $updatePlayerRoleQuery = "UPDATE players SET house_role_id = :roleId WHERE player_uuid = :playerUUID";
            $playerRoleStmt = $pdo->prepare($updatePlayerRoleQuery);
            $playerRoleStmt->bindParam(':roleId', $officerRoleId, PDO::PARAM_INT);
            $playerRoleStmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $playerRoleStmt->execute();

            return ['status' => 200, 'message' => "Officer set successfully."];

        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return ['status' => 500, 'message' => "Database error."];
        }
    }

    public static function demoteOfficer($pdo, $playerUUID, $houseID)
    {
        try
        {
            // Start a transaction
            $pdo->beginTransaction();

            // Fetch the player's current role in the house
            $currentRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);

            // Check if the player is an officer
            if (!in_array($currentRole, ["house_officer_1", "house_officer_2"]))
            {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "The player is not an officer of the specified house."];
            }

            // Demote the officer in the houses table using the helper function
            $demotionResult = self::removeOfficerNameAndUUID($pdo, $playerUUID);
            if (!$demotionResult)
            {
                $pdo->rollBack();
                return ['status' => 500, 'message' => "Error demoting officer."];
            }

            // Get the role ID for a regular member using the helper function
            $memberRoleId = self::getRoleIdForKeyword($pdo, $playerUUID, 'house_member');

            // Update the house_role_id in players table to represent a regular member
            $updateRoleQuery = "UPDATE players SET house_role_id = :memberRoleId WHERE player_uuid = :playerUUID";
            $roleStmt = $pdo->prepare($updateRoleQuery);
            $roleStmt->bindParam(':memberRoleId', $memberRoleId, PDO::PARAM_INT);
            $roleStmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $roleStmt->execute();

            // Commit the changes
            $pdo->commit();

            return ['status' => 200, 'message' => "Officer demoted successfully."];
        }
        catch (PDOException $e)
        {
            // Roll back the transaction if an exception occurs
            $pdo->rollBack();
            error_log("Database Error: " . $e->getMessage());
            return [
                'status' => 500,
                'message' => "Database error.",
                'extra' => [
                    'errorInfo' => $e->errorInfo,
                    'trace' => $e->getTraceAsString(),
                ],
            ];
        }
    }

    public static function printHouseMembersList($pdo, $houseID, $url, $batchSize = 10)
    {
        try
        {
            // Use a CTE to fetch all members and their followers recursively
            $query = "
        WITH RECURSIVE follower_tree AS (
            SELECT
                p.player_id,
                p.player_uuid,
                ps.player_current_status,
                p.player_current_health,
                p.player_age,
                p.player_following_id,
                pf.player_uuid AS player_following_uuid
            FROM players p
            LEFT JOIN player_status ps ON p.player_status_id = ps.player_status_id
            LEFT JOIN players pf ON p.player_following_id = pf.player_id
            WHERE p.house_id = :houseID AND p.player_following_id IS NULL

            UNION ALL

            SELECT
                p.player_id,
                p.player_uuid,
                ps.player_current_status,
                p.player_current_health,
                p.player_age,
                p.player_following_id,
                pf.player_uuid AS player_following_uuid
            FROM players p
            INNER JOIN follower_tree ft ON p.player_following_id = ft.player_id
            LEFT JOIN player_status ps ON p.player_status_id = ps.player_status_id
            LEFT JOIN players pf ON p.player_following_id = pf.player_id
        )
        SELECT
            player_uuid,
            player_current_status,
            player_current_health,
            player_age,
            player_following_uuid
        FROM follower_tree;
        ";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $stmt->execute();

            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$members)
            {
                return ['status' => 400, 'message' => 'No members found for the specified house.', 'extra' => null];
            }

            // Split members array into batches and send each batch to the specified URL
            $batches = array_chunk($members, $batchSize);
            $responses = [];
            foreach ($batches as $batch)
            {
                $batchWithMessage = [
                    "message" => "House members batch",
                    "data" => $batch,
                ];
                // Assuming self::sendDataToURL() is a method that sends data to a specified URL
                $responses[] = CommunicationController::sendDataToURL($url, $batchWithMessage);
            }

            // Handle responses after all batches have been sent
            foreach ($responses as $response)
            {
                if (isset($response['status']) && $response['status'] !== 200)
                {
                    error_log("Error sending batch to URL: " . $response['message']);
                    return ['status' => $response['status'], 'message' => 'Error sending batch to URL: ' . $response['message'], 'extra' => null];
                }
            }

            return ['status' => 200, 'message' => 'Members list processed successfully.', 'extra' => null];

        }
        catch (PDOException $e)
        {
            error_log("PDOException on printHouseMembersList: " . $e->getMessage());
            return ['status' => 500, 'message' => 'Database error: ' . $e->getMessage(), 'extra' => null];
        }
        catch (Exception $e)
        {
            error_log("Exception on printHouseMembersList: " . $e->getMessage());
            return ['status' => 500, 'message' => 'Internal server error: ' . $e->getMessage(), 'extra' => null];
        }
    }
    // Old Code to remove member from house and followers before bloodlines added
    // public static function removeMemberFromHouse($pdo, $initiatorUUID, $playerUUID, $houseID)
    // {
    //     try {
    //         $pdo->beginTransaction();

    //         // // Validate input data
    //         // if (empty($initiatorUUID) || empty($playerUUID) || empty($houseID)) {
    //         //     $pdo->rollBack();
    //         //     return new JsonResponse(400, "Invalid request data.");
    //         // }

    //         // Fetch roles of the initiator and target
    //         $initiatorRole = HouseHelperController::getCurrentHouseRole($pdo, $initiatorUUID);
    //         $playerRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);

    //         // House membership check
    //         $initiatorHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUUID);
    //         $playerHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUUID);

    //         if ($initiatorHouseId !== $playerHouseId) {
    //             $pdo->rollBack();
    //             return new JsonResponse(400, "The player is not currently a member of the specified house.");
    //         }

    //         // Check and handle the roles
    //         if ($initiatorRole == "house_member") {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, "Members can't remove anyone.");
    //         } elseif ($initiatorRole == "house_owner" && $playerRole == "house_owner") {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, "Owner can't remove themselves.");
    //         } elseif (in_array($initiatorRole, ["house_officer_1", "house_officer_2"]) && in_array($playerRole, ["house_owner", "house_officer_1", "house_officer_2"])) {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, "Officers can only remove members.");
    //         }

    //         // Clan role checks and actions for the target player
    //         $playerClanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUUID);
    //         if ($playerClanRoleId) {
    //             $playerClanRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $playerClanRoleId);
    //             // Prevent removal if the player is a clan owner or co-owner
    //             if (in_array($playerClanRoleKeyword, ['clan_owner', 'clan_co_owner'])) {
    //                 $pdo->rollBack();
    //                 return new JsonResponse(403, "Cannot remove a clan owner or co-owner from the house.");
    //             }
    //             // Demote if the player is a clan officer
    //             if (in_array($playerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2'])) {
    //                 $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);
    //                 ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $playerClanRoleKeyword);
    //                 ClanHelperController::updateClanRoleName($pdo, $clanId, null, $playerClanRoleKeyword);
    //                 ClanHelperController::updatePlayerClanRole($pdo, $playerUUID, null);
    //             }
    //         }

    //         // Remove the player from the house
    //         $removeQuery = $pdo->prepare("UPDATE players SET house_id = NULL, house_role_id = NULL, player_following_id = NULL, player_title = NULL WHERE player_uuid = :playerUUID");
    //         $removeQuery->execute([':playerUUID' => $playerUUID]);

    //         // Recursive function to handle followers
    //         try {
    //             self::removeFollowersFromHouse($pdo, $playerUUID, $houseID);
    //         } catch (Exception $e) {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, $e->getMessage());
    //         }

    //         $pdo->commit();
    //         return new JsonResponse(200, "Player and their followers removed successfully from the house.");
    //     } catch (PDOException $e) {
    //         $pdo->rollBack();
    //         return new JsonResponse(500, "Database error: " . $e->getMessage());
    //     } catch (Exception $e) {
    //         $pdo->rollBack();
    //         return new JsonResponse(500, "Internal server error: " . $e->getMessage());
    //     }
    // }

    // // Recursive function to remove followers from the house
    // public static function removeFollowersFromHouse($pdo, $playerUUID, $houseID)
    // {
    //     try {
    //         $query = $pdo->prepare("SELECT player_uuid FROM players WHERE player_following_id = (SELECT player_id FROM players WHERE player_uuid = :playerUUID)");
    //         $query->execute([':playerUUID' => $playerUUID]);
    //         $followers = $query->fetchAll(PDO::FETCH_COLUMN);

    //         foreach ($followers as $followerUUID) {
    //             // Check for clan role
    //             $followerClanRoleId = ClanHelperController::getClanRoleId($pdo, $followerUUID);
    //             if ($followerClanRoleId) {
    //                 $followerClanRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $followerClanRoleId);
    //                 // Initiate rollback if the follower is a clan owner or co-owner
    //                 if (in_array($followerClanRoleKeyword, ['clan_owner', 'clan_co_owner'])) {
    //                     throw new Exception("Cannot remove a clan owner or co-owner from the house.");
    //                 }

    //                 // Demote if the follower is a clan officer
    //                 if (in_array($followerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2'])) {
    //                     $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $followerUUID);
    //                     ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $followerClanRoleKeyword);
    //                     ClanHelperController::updateClanRoleName($pdo, $clanId, null, $followerClanRoleKeyword);
    //                     ClanHelperController::updatePlayerClanRole($pdo, $followerUUID, null);
    //                 }
    //             }

    //             // Check for house officer role and demote if necessary
    //             $followerHouseRole = HouseHelperController::getCurrentHouseRole($pdo, $followerUUID);
    //             if (in_array($followerHouseRole, ["house_officer_1", "house_officer_2"])) {
    //                 Self::removeOfficerNameAndUUID($pdo, $followerUUID);
    //             }

    //             // Remove the follower from the house
    //             $updateQuery = $pdo->prepare("UPDATE players SET house_id = NULL, house_role_id = NULL, player_title = NULL WHERE player_uuid = :followerUUID");
    //             $updateQuery->execute([':followerUUID' => $followerUUID]);

    //             // Recursively remove followers of the follower
    //             self::removeFollowersFromHouse($pdo, $followerUUID, $houseID);
    //         }
    //     } catch (PDOException $e) {
    //         // Propagate the exception to the caller
    //         throw $e;
    //     }
    // }



    public static function removeMemberFromHouse($pdo, $initiatorUUID, $playerUUID, $houseID)
    {
        try
        {
            $pdo->beginTransaction();

            // 1. Fetch house IDs of both initiator and target
            $initiatorHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $initiatorUUID);
            $playerHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUUID);

            if ($initiatorHouseId !== $playerHouseId)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "The player is not currently a member of the specified house.");
            }

            // 2. Check house roles of initiator and target
            $initiatorRole = HouseHelperController::getCurrentHouseRole($pdo, $initiatorUUID);
            $playerRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);

            if ($initiatorRole == "house_member")
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Members can't remove anyone.");
            } elseif ($initiatorRole == "house_owner" && $playerRole == "house_owner")
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Owner can't remove themselves.");
            } elseif (in_array($initiatorRole, ["house_officer_1", "house_officer_2"]) && in_array($playerRole, ["house_owner", "house_officer_1", "house_officer_2"]))
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Officers can only remove members.");
            }

            // 3. Clan role checks for the target player
            $playerClanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUUID);
            if ($playerClanRoleId)
            {
                $playerClanRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $playerClanRoleId);
                if (in_array($playerClanRoleKeyword, ['clan_owner', 'clan_co_owner']))
                {
                    $pdo->rollBack();
                    return new JsonResponse(403, "Cannot remove a clan owner or co-owner from the house.");
                }
                if (in_array($playerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2']))
                {
                    $pdo->rollBack();
                    return new JsonResponse(403, "Cannot remove a clan officer from the house.");
                }
            }

            // 4. Bloodline role checks for the target player
            $playerBloodlineRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $playerUUID);
            if ($playerBloodlineRoleId)
            {
                $playerBloodlineRoleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, (int) $playerBloodlineRoleId);
                if (in_array($playerBloodlineRoleKeyword, ['bloodline_owner', 'bloodline_officer_1', 'bloodline_officer_2']))
                {
                    $pdo->rollBack();
                    return new JsonResponse(403, "Cannot remove a player with a significant bloodline role from the house.");
                }
            }

            // 5. If the target player is an officer, remove their officer details (demote them)
            if (in_array($playerRole, ['house_officer_1', 'house_officer_2']))
            {
                HouseController::removeOfficerNameAndUUID($pdo, $playerUUID);
            }

            // 6. Remove the player from the house
            $removeQuery = $pdo->prepare("
            UPDATE players 
            SET house_id = NULL, house_role_id = NULL, player_following_id = NULL, player_title = NULL 
            WHERE player_uuid = :playerUUID
        ");
            $removeQuery->execute([':playerUUID' => $playerUUID]);

            // 7. Recursively remove followers from the house (includes role checks and officer demotion)
            try
            {
                self::removeFollowersFromHouse($pdo, $playerUUID, $houseID);
            }
            catch (Exception $e)
            {
                $pdo->rollBack();
                return new JsonResponse(403, $e->getMessage());
            }

            $pdo->commit();
            return new JsonResponse(200, "Player and their followers removed successfully from the house.");
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    public static function removeFollowersFromHouse($pdo, $playerUUID, $houseID)
    {
        try
        {
            $query = $pdo->prepare("
            SELECT player_uuid 
            FROM players 
            WHERE player_following_id = (SELECT player_id FROM players WHERE player_uuid = :playerUUID)
        ");
            $query->execute([':playerUUID' => $playerUUID]);
            $followers = $query->fetchAll(PDO::FETCH_COLUMN);

            foreach ($followers as $followerUUID)
            {
                // Check for clan role
                $followerClanRoleId = ClanHelperController::getClanRoleId($pdo, $followerUUID);
                if ($followerClanRoleId)
                {
                    $followerClanRoleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $followerClanRoleId);
                    if (in_array($followerClanRoleKeyword, ['clan_owner', 'clan_co_owner']))
                    {
                        throw new Exception("Cannot remove a clan owner or co-owner from the house.");
                    }
                    if (in_array($followerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2']))
                    {
                        throw new Exception("Cannot remove a clan officer from the house.");
                    }
                }

                // Check for bloodline role
                $followerBloodlineRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $followerUUID);
                if ($followerBloodlineRoleId)
                {
                    $followerBloodlineRoleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, $followerBloodlineRoleId);
                    if (in_array($followerBloodlineRoleKeyword, ['bloodline_owner', 'bloodline_officer_1', 'bloodline_officer_2']))
                    {
                        throw new Exception("Cannot remove a follower with a significant bloodline role from the house.");
                    }
                }

                // Check for house officer role and, if present, remove the officer details
                $followerHouseRole = HouseHelperController::getCurrentHouseRole($pdo, $followerUUID);
                if (in_array($followerHouseRole, ["house_officer_1", "house_officer_2"]))
                {
                    self::removeOfficerNameAndUUID($pdo, $followerUUID);
                }

                // Remove the follower from the house
                $updateQuery = $pdo->prepare("
                UPDATE players 
                SET house_id = NULL, house_role_id = NULL, player_title = NULL 
                WHERE player_uuid = :followerUUID
            ");
                $updateQuery->execute([':followerUUID' => $followerUUID]);

                // Recursively remove followers of this follower
                self::removeFollowersFromHouse($pdo, $followerUUID, $houseID);
            }
        }
        catch (PDOException $e)
        {
            throw $e;
        }
    }





    public static function addMemberToHouse($pdo, $inviterUUID, $invitedPlayerUUID, $houseID)
    {
        try
        {
            $pdo->beginTransaction();

            // Check if the invited player exists and is not already in a house
            $invitedPlayerExists = self::playerExists($pdo, $invitedPlayerUUID);
            if (!$invitedPlayerExists)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Invited player does not exist.");
            }

            $currentHouseID = self::getCurrentHouseID($pdo, $invitedPlayerUUID);
            if ($currentHouseID)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Invited player is already in a house.");
            }

            // Retrieve the role ID for "member"
            $memberRoleID = self::getRoleIdForKeyword($pdo, $invitedPlayerUUID, 'house_member');
            if (!$memberRoleID)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Member role ID not found.");
            }

            // Check if the inviter exists and retrieve their player ID
            $inviterExists = self::playerExists($pdo, $inviterUUID);
            if (!$inviterExists)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Inviter does not exist.");
            }

            $inviterId = self::getPlayerIdByUUID($pdo, $inviterUUID);
            if (!$inviterId)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Inviter's player ID not found.");
            }

            // Fetch species IDs for player and house
            $playerSpeciesId = self::getPlayerSpeciesId($pdo, $invitedPlayerUUID);
            $houseSpeciesId = self::getHouseSpeciesId($pdo, $houseID);

            // Check if the species match
            if ($playerSpeciesId != $houseSpeciesId)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Player's species does not match the house's species.");
            }

            // Add the invited player to the house, assign the member role, and set them as a follower of the inviter
            $addPlayerToHouseQuery = $pdo->prepare("UPDATE players SET house_id = :houseID, house_role_id = :memberRoleID, player_following_id = :inviterId WHERE player_uuid = :invitedPlayerUUID");
            $addPlayerToHouseQuery->execute([
                ':houseID' => $houseID,
                ':memberRoleID' => $memberRoleID,
                ':inviterId' => $inviterId,
                ':invitedPlayerUUID' => $invitedPlayerUUID,
            ]);

            // Recursively update the house_id for all followers
            self::updateFollowersHouseId($pdo, $invitedPlayerUUID, $houseID);

            $pdo->commit();
            return new JsonResponse(200, "Player successfully added to the house and set as a follower of the inviter.");
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    private static function updateFollowersHouseId($pdo, $playerUUID, $houseID)
    {
        try
        {
            $query = $pdo->prepare("SELECT player_uuid FROM players WHERE player_following_id = (SELECT player_id FROM players WHERE player_uuid = :playerUUID)");
            $query->execute([':playerUUID' => $playerUUID]);
            $followers = $query->fetchAll(PDO::FETCH_COLUMN);

            foreach ($followers as $followerUUID)
            {
                $updateQuery = $pdo->prepare("UPDATE players SET house_id = :houseID WHERE player_uuid = :followerUUID");
                $updateQuery->execute([
                    ':houseID' => $houseID,
                    ':followerUUID' => $followerUUID,
                ]);

                // Recursively update followers of the follower
                self::updateFollowersHouseId($pdo, $followerUUID, $houseID);
            }
        }
        catch (PDOException $e)
        {
            // Propagate the exception to the caller
            throw $e;
        }
    }

    // Helper function to check if player exists
    public static function playerExists($pdo, $playerUUID)
    {
        $query = $pdo->prepare("SELECT COUNT(*) FROM players WHERE player_uuid = :playerUUID");
        $query->execute([':playerUUID' => $playerUUID]);
        return $query->fetchColumn() > 0;
    }

    // Helper function to get player_id by UUID
    public static function getPlayerIdByUUID($pdo, $uuid)
    {
        $query = $pdo->prepare("SELECT player_id FROM players WHERE player_uuid = :uuid");
        $query->execute([':uuid' => $uuid]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['player_id'] : null;
    }

    public static function getRoleIdForKeyword($pdo, $playerUUID, $keyword)
    {
        // First, fetch the species ID of the player
        $playerSpeciesId = self::getPlayerSpeciesId($pdo, $playerUUID);

        // Now, fetch the role ID based on the keyword and species ID
        $query = $pdo->prepare("SELECT house_role_id FROM player_role_house WHERE player_role_house_keyword = :keyword AND species_id = :speciesId LIMIT 1");
        $query->execute([':keyword' => $keyword, ':speciesId' => $playerSpeciesId]);
        return $query->fetchColumn();
    }

    public static function removeOfficerNameAndUUID($pdo, $playerUUID)
    {
        try
        {
            // Check if the player is an officer in any house
            $officerCheckQuery1 = "SELECT house_officer_1_uuid FROM houses WHERE house_officer_1_uuid = ?";
            $officerCheckQuery2 = "SELECT house_officer_2_uuid FROM houses WHERE house_officer_2_uuid = ?";

            $officerCheckStmt1 = $pdo->prepare($officerCheckQuery1);
            $officerCheckStmt1->execute(array($playerUUID));
            $existingOfficer1 = $officerCheckStmt1->fetch(PDO::FETCH_ASSOC);

            $officerCheckStmt2 = $pdo->prepare($officerCheckQuery2);
            $officerCheckStmt2->execute(array($playerUUID));
            $existingOfficer2 = $officerCheckStmt2->fetch(PDO::FETCH_ASSOC);

            if (!$existingOfficer1 && !$existingOfficer2)
            {
                return false;
            }
            // Player is not an officer

            // Determine which officer position they hold and demote
            if ($existingOfficer1)
            {
                $demoteQuery = "UPDATE houses SET house_officer_1_uuid = NULL, house_officer_1_name = NULL WHERE house_officer_1_uuid = ?";
            } else
            {
                $demoteQuery = "UPDATE houses SET house_officer_2_uuid = NULL, house_officer_2_name = NULL WHERE house_officer_2_uuid = ?";
            }

            $demoteStmt = $pdo->prepare($demoteQuery);
            $demoteStmt->execute(array($playerUUID));

            // Update the house_role_id in players table to represent a regular member (assuming 4 is the ID for regular members)
            $updateRoleQuery = "UPDATE players SET house_role_id = 4 WHERE player_uuid = ?";
            $roleStmt = $pdo->prepare($updateRoleQuery);
            $roleStmt->execute(array($playerUUID));

            return true;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function removePlayerFromCurrentHouse($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("UPDATE players SET house_id = NULL, house_role_id = NULL WHERE player_uuid = :playerUUID");
            $query->execute([':playerUUID' => $playerUUID]);
            return true;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getCurrentHouseID($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("SELECT house_id FROM players WHERE player_uuid = :playerUUID");
            $query->execute([':playerUUID' => $playerUUID]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['house_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // Remove house from clan by clan owner or co-owner of the house
    public static function removeHouseFromClan($pdo, $playerUUID, $houseID)
    {
        try
        {
            // Start the transaction
            $pdo->beginTransaction();

            // Check if the player is the owner of the specified house
            $currentRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);
            if ($currentRole !== "house_owner")
            {
                $pdo->rollBack();
                return new JsonResponse(400, "The player is not the owner of the specified house.");
            }

            // Check if the house is part of any clan
            $houseClanID = HouseHelperController::getCurrentHouseClanId($pdo, $houseID);
            if (!$houseClanID || $houseClanID === null)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "The house is not part of any clan.");
            }

            // Get all members of the house
            $memberUUIDs = HouseHelperController::getAllHouseMembersUuids($pdo, $houseID);

            // Check each member for clan roles
            foreach ($memberUUIDs as $memberUUID)
            {
                $memberClanRoleId = ClanHelperController::getClanRoleId($pdo, $memberUUID);
                if ($memberClanRoleId)
                {
                    $roleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $memberClanRoleId);

                    // If a member is a clan owner or co-owner, the house cannot leave
                    if (in_array($roleKeyword, ['clan_owner', 'clan_co_owner']))
                    {
                        $pdo->rollBack();
                        return new JsonResponse(403, "A clan owner or co-owner is a member of the house. The house cannot leave the clan.");
                    }

                    // Reset the name and UUID in the clans table for officer roles
                    if (in_array($roleKeyword, ['clan_officer_1', 'clan_officer_2']))
                    {
                        ClanHelperController::updateClanRoleUUID($pdo, $houseClanID, null, $roleKeyword);
                        ClanHelperController::updateClanRoleName($pdo, $houseClanID, null, $roleKeyword);
                        ClanHelperController::updatePlayerClanRole($pdo, $memberUUID, null);
                    }
                }
            }

            // Remove the house from the clan
            $removeHouseFromClanQuery = $pdo->prepare("UPDATE houses SET clan_id = NULL WHERE house_id = :houseID");
            $removeHouseFromClanQuery->execute([':houseID' => $houseID]);

            // Commit the transaction
            $pdo->commit();

            return new JsonResponse(200, "House removed from the clan successfully, and officer roles reset.");
        }
        catch (PDOException $e)
        {
            // Rollback the transaction on error
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    public static function promoteToOwner($pdo, $currentOwnerUUID, $newOwnerUUID, $houseID)
    {
        try
        {
            $pdo->beginTransaction(); // Start transaction

            // Fetch the new owner's data from the players table
            $newOwnerQuery = $pdo->prepare("SELECT legacy_name, player_id FROM players WHERE player_uuid = ?");
            $newOwnerQuery->execute([$newOwnerUUID]);
            $newOwnerData = $newOwnerQuery->fetch(PDO::FETCH_ASSOC);

            if (!$newOwnerData)
            {
                $pdo->rollBack();
                return ['status' => 400, 'message' => "New owner not found in the database."];
            }

            // Check if they are in the house.
            $checkHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $newOwnerUUID);

            if ($houseID != $checkHouseId)
            {
                return ['status' => 400, 'message' => 'Player not in house.'];
            }

            $newOwnerName = $newOwnerData['legacy_name'];
            $newOwnerId = $newOwnerData['player_id'];

            // Check if the new owner is currently an officer and remove them
            $currentRole = HouseHelperController::getCurrentHouseRole($pdo, $newOwnerUUID);
            if (in_array($currentRole, ["house_officer_1", "house_officer_2"]))
            {
                self::removeOfficerNameAndUUID($pdo, $newOwnerUUID);
            }

            // Update the house's owner in the houses table
            $updateHouseOwnerQuery = $pdo->prepare("UPDATE houses SET house_owner_uuid = ?, house_owner_name = ? WHERE house_id = ?");
            $updateHouseOwnerQuery->execute([$newOwnerUUID, $newOwnerName, $houseID]);

            // Update the new owner's role in the players table to owner and clear following_id
            $ownerRoleId = self::getRoleIdForKeyword($pdo, $newOwnerUUID, 'house_owner');
            $updateNewOwnerRoleQuery = $pdo->prepare("UPDATE players SET house_role_id = ?, player_following_id = NULL WHERE player_uuid = ?");
            $updateNewOwnerRoleQuery->execute([$ownerRoleId, $newOwnerUUID]);

            // Update the old owner's role to member and set them as a follower of the new owner
            $memberRoleId = self::getRoleIdForKeyword($pdo, $currentOwnerUUID, 'house_member');
            $updateOldOwnerRoleQuery = $pdo->prepare("UPDATE players SET house_role_id = ?, player_following_id = ? WHERE player_uuid = ?");
            $updateOldOwnerRoleQuery->execute([$memberRoleId, $newOwnerId, $currentOwnerUUID]);

            $pdo->commit(); // Commit transaction
            return ['status' => 200, 'message' => "Successfully promoted the player to owner and updated the old owner to follower and member."];
        }
        catch (PDOException $e)
        {
            $pdo->rollBack(); // Roll back transaction
            return ['status' => 500, 'message' => "Database error.", 'extra' => $e->getMessage()];
        }
        catch (Exception $e)
        {
            // Catch any other exceptions that might occur
            $pdo->rollBack(); // Roll back transaction in case of any other exception
            return ['status' => 500, 'message' => "Error processing transaction: " . $e->getMessage()];
        }
    }

    public static function leaveHouse($pdo, $playerUUID)
    {
        try
        {
            // Start a transaction
            $pdo->beginTransaction();

            // 1. Fetch the player's current house and role
            $currentHouseID = self::getCurrentHouseID($pdo, $playerUUID);
            $currentRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUUID);
            $currentClanRole = ClanHelperController::getClanRoleKeyword($pdo, ClanHelperController::getClanRoleId($pdo, $playerUUID));

            // 2. Validate if the player is in a house
            if (!$currentHouseID)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "You are not currently in a house.");
            }

            // 3. Handle special cases based on roles
            if ($currentRole === "house_owner")
            {
                // Owners can't leave without transferring ownership
                $pdo->rollBack();
                return new JsonResponse(400, "As an owner, you can't leave the house without transferring ownership first.");
            }

            if (in_array($currentClanRole, ['clan_owner', 'clan_co_owner']))
            {
                // Clan owners or co-owners can't leave
                $pdo->rollBack();
                return new JsonResponse(403, "A clan owner or co-owner cannot leave the house.");
            }

            // 4. Demote if the player is a house or clan officer
            if (in_array($currentRole, ["house_officer_1", "house_officer_2"]) || in_array($currentClanRole, ['clan_officer_1', 'clan_officer_2']))
            {
                // Demote from house officer role
                self::removeOfficerNameAndUUID($pdo, $playerUUID);

                // Demote from clan officer role
                $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUUID);
                ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $currentClanRole);
                ClanHelperController::updateClanRoleName($pdo, $clanId, null, $currentClanRole);
                ClanHelperController::updatePlayerClanRole($pdo, $playerUUID, null);
            }

            // 5. Remove the player from the house
            $removeQuery = $pdo->prepare("UPDATE players SET house_id = NULL, house_role_id = NULL, player_following_id = NULL, player_title = NULL WHERE player_uuid = :playerUUID");
            $removeQuery->execute([':playerUUID' => $playerUUID]);

            try
            {
                // 6. Use the helper to recursively remove followers from the house
                self::removeFollowersFromHouse($pdo, $playerUUID, $currentHouseID);
            }
            catch (Exception $e)
            {
                // Roll back if removing followers fails
                $pdo->rollBack();
                return new JsonResponse(500, $e->getMessage());
            }

            // 7. Commit the changes
            $pdo->commit();

            return new JsonResponse(200, "You have left the house along with your followers.");
        }
        catch (PDOException $e)
        {
            // Roll back the transaction if an exception occurs
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    public static function deleteHouse($pdo, $houseID, $playerUUID)
    {
        try
        {
            // Begin the transaction
            $pdo->beginTransaction();

            // Check if the requesting player is the owner of the house
            $ownerCheckQuery = "SELECT house_owner_uuid FROM houses WHERE house_id = :houseID";
            $ownerCheckStmt = $pdo->prepare($ownerCheckQuery);
            $ownerCheckStmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $ownerCheckStmt->execute();

            $ownerData = $ownerCheckStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ownerData || $ownerData["house_owner_uuid"] !== $playerUUID)
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Only the house owner can delete the house.");
            }

            // Get all members of the house
            $memberUUIDs = HouseHelperController::getAllHouseMembersUuids($pdo, $houseID);

            // Check each member for clan roles
            foreach ($memberUUIDs as $memberUUID)
            {
                $memberClanRoleId = ClanHelperController::getClanRoleId($pdo, $memberUUID);
                if ($memberClanRoleId)
                {
                    $roleKeyword = ClanHelperController::getClanRoleKeyword($pdo, $memberClanRoleId);

                    // Prevent deletion if a member is a clan owner or co-owner
                    if (in_array($roleKeyword, ['clan_owner', 'clan_co_owner']))
                    {
                        $pdo->rollBack();
                        return new JsonResponse(403, "Cannot delete a house containing a clan owner or co-owner.");
                    }

                    // Demote if the player is a clan officer
                    if (in_array($roleKeyword, ['clan_officer_1', 'clan_officer_2']))
                    {
                        $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $memberUUID);
                        ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $roleKeyword);
                        ClanHelperController::updateClanRoleName($pdo, $clanId, null, $roleKeyword);
                        ClanHelperController::updatePlayerClanRole($pdo, $memberUUID, null);
                    }
                }
            }

            // Set all members of the house to no house and no role
            $membersUpdateQuery = "UPDATE players SET house_id = NULL, house_role_id = NULL WHERE house_id = :houseID";
            $membersUpdateStmt = $pdo->prepare($membersUpdateQuery);
            $membersUpdateStmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $membersUpdateStmt->execute();

            // Delete the house from the houses table
            $houseDeleteQuery = "DELETE FROM houses WHERE house_id = :houseID";
            $houseDeleteStmt = $pdo->prepare($houseDeleteQuery);
            $houseDeleteStmt->bindParam(':houseID', $houseID, PDO::PARAM_INT);
            $houseDeleteStmt->execute();

            // Commit the transaction
            $pdo->commit();

            return new JsonResponse(200, "House deleted successfully.");
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
    }

    public static function getFollowerCount($pdo, $playerUUID)
    {
        try
        {
            // Initialize an array to keep track of all the followers' UUIDs
            $allFollowers = [];
            $processedUUIDs = []; // To avoid reprocessing

            // Initialize the stack with the initial player's UUID
            $stack = [$playerUUID];

            while (!empty($stack))
            {
                $currentUUID = array_pop($stack);

                if (in_array($currentUUID, $processedUUIDs))
                {
                    continue; // Skip if already processed
                }

                // Prepare the SQL to find the followers of the current UUID
                $followersQuery = $pdo->prepare(
                    "SELECT player_uuid FROM players WHERE player_following_id = (
                        SELECT player_id FROM players WHERE player_uuid = ?
                    )"
                );
                $followersQuery->execute([$currentUUID]);
                $followers = $followersQuery->fetchAll(PDO::FETCH_COLUMN, 0);

                // Add the newly found followers to the stack if they are not already in the allFollowers array
                foreach ($followers as $followerUUID)
                {
                    if (!in_array($followerUUID, $allFollowers))
                    {
                        $allFollowers[] = $followerUUID; // Add to the all followers list
                        $stack[] = $followerUUID; // Add to the stack to search their followers
                    }
                }

                $processedUUIDs[] = $currentUUID; // Mark as processed
            }

            // Return the count of all unique followers found
            return count($allFollowers);
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }
    public static function getMemberCount($pdo, $houseId)
    {
        try
        {
            // Prepare the SQL to count the players with the given house_id
            $query = $pdo->prepare(
                "SELECT COUNT(*) FROM players WHERE house_id = ?"
            );
            $query->execute([$houseId]);

            // Fetch the count result
            $count = $query->fetchColumn();

            return $count;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getAliveFollowerCount($pdo, $playerUUID)
    {
        try
        {
            // Initialize an array to keep track of all processed UUIDs and alive followers' UUIDs
            $processedUUIDs = [];
            $aliveFollowers = [];

            // Get the initial set of followers for the player
            $initialFollowers = self::getFollowersUUIDs($pdo, $playerUUID);
            $stack = $initialFollowers;

            while (!empty($stack))
            {
                $currentUUID = array_pop($stack);

                if (in_array($currentUUID, $processedUUIDs))
                {
                    continue; // Skip if already processed
                }

                // Check if the current UUID is 'alive'
                $followerStatusId = self::getPlayerStatusId($pdo, $currentUUID);
                $statusInfo = self::getPlayerStatusById($pdo, $followerStatusId);
                if ($statusInfo['status'] == 200 && $statusInfo['player_status_keyword'] == 'alive')
                {
                    $aliveFollowers[] = $currentUUID; // Add to the alive followers list
                }

                // Add the followers of the current UUID to the stack
                $followers = self::getFollowersUUIDs($pdo, $currentUUID);
                foreach ($followers as $followerUUID)
                {
                    $stack[] = $followerUUID; // Add to the stack to search their followers
                }

                $processedUUIDs[] = $currentUUID; // Mark as processed
            }

            // Return the count of all unique alive followers found
            return count($aliveFollowers);
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    private static function getFollowersUUIDs($pdo, $playerUUID)
    {
        $query = $pdo->prepare(
            "SELECT player_uuid FROM players WHERE player_following_id = (
                SELECT player_id FROM players WHERE player_uuid = ?
            )"
        );
        $query->execute([$playerUUID]);
        return $query->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    // Function to check if a player is in a house
    public static function isPlayerInHouse($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("SELECT house_id FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->execute([':playerUUID' => $playerUUID]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return !empty($result['house_id']);
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // The action code to handle the 'checkPlayerAndFollowers' case
    public static function checkPlayerAndFollowers($pdo, $playerUUID)
    {
        try
        {
            $isInHouse = self::isPlayerInHouse($pdo, $playerUUID);
            $followerCount = self::getAliveFollowerCount($pdo, $playerUUID);
            $playerSpeciesId = self::getPlayerSpeciesId($pdo, $playerUUID); // Assuming this method exists
            $houseTypeName = self::getHouseTypeNameForSpecies($pdo, $playerSpeciesId);
            $playerStatusId = self::getPlayerStatusId($pdo, $playerUUID);
            $playerStatus = self::getPlayerStatusById($pdo, $playerStatusId);

            $isInHouseStr = $isInHouse ? 'Yes' : 'No';

            if ($isInHouse === null || $followerCount === false || $playerStatus['status'] !== 200 || $houseTypeName === null)
            {
                return ['status' => 500, 'message' => "Internal server error."];
            }

            return [
                'status' => 200,
                'in_house' => $isInHouseStr,
                'follower_count' => $followerCount,
                'species_id' => $playerSpeciesId,
                'house_type_name' => $houseTypeName,
                'player_status' => [
                    'status_keyword' => $playerStatus['player_status_keyword'],
                    'current_status' => $playerStatus['player_current_status'],
                ],
            ];
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return ['status' => 500, 'message' => "Internal server error."];
        }
    }

    // This function retrieves the status ID for a player using their UUID
    public static function getPlayerStatusId($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("SELECT player_status_id FROM players WHERE player_uuid = :playerUUID LIMIT 1");
            $query->execute([':playerUUID' => $playerUUID]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['player_status_id'] : false;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // This function retrieves the player status keyword and current status using the status ID
    public static function getPlayerStatusById($pdo, $statusId)
    {
        try
        {
            $query = $pdo->prepare("SELECT player_status_keyword, player_current_status FROM player_status WHERE player_status_id = :statusId LIMIT 1");
            $query->execute([':statusId' => $statusId]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            if ($result)
            {
                return [
                    'status' => 200,
                    'player_status_keyword' => $result['player_status_keyword'],
                    'player_current_status' => $result['player_current_status'],
                ];
            } else
            {
                return ['status' => 404, 'message' => "Status not found."];
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return ['status' => 500, 'message' => "Internal server error."];
        }
    }

    public static function createHouse($pdo, $playerUUID, $legacyName, $houseName)
    {
        try
        {
            // Check if the player is already in a house
            $currentHouseId = self::getCurrentHouseID($pdo, $playerUUID);
            if ($currentHouseId !== null)
            {
                return ['status' => 400, 'message' => "Player is already in a house."];
            }

            // Check if a house with the same name already exists
            $checkHouseQuery = $pdo->prepare("SELECT COUNT(*) FROM houses WHERE house_name = :houseName");
            $checkHouseQuery->execute([':houseName' => $houseName]);
            $houseExists = $checkHouseQuery->fetchColumn();
            if ($houseExists)
            {
                return ['status' => 400, 'message' => "A house with this name already exists."];
            }

            // Fetch the species_id of the house founder
            $playerSpeciesId = self::getPlayerSpeciesId($pdo, $playerUUID);
            if ($playerSpeciesId === false)
            {
                return ['status' => 400, 'message' => "Player species not found."];
            }

            // Determine house_type_id based on species_id
            $houseTypeId = self::getHouseTypeIdForSpecies($pdo, $playerSpeciesId);
            if ($houseTypeId === false)
            {
                return ['status' => 400, 'message' => "House type for species not found."];
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Insert the new house with species_id and house_type_id
            $insertHouseQuery = $pdo->prepare(
                "INSERT INTO houses (house_founder_uuid, house_name, house_founder, house_owner_name, house_owner_uuid, species_id, house_type_id)
             VALUES (:founderUUID, :houseName, :founderName, :ownerName, :ownerUUID, :speciesId, :houseTypeId)"
            );
            $insertHouseResult = $insertHouseQuery->execute([
                ':founderUUID' => $playerUUID,
                ':houseName' => $houseName,
                ':founderName' => $legacyName,
                ':ownerName' => $legacyName,
                ':ownerUUID' => $playerUUID,
                ':speciesId' => $playerSpeciesId,
                ':houseTypeId' => $houseTypeId,
            ]);
            if (!$insertHouseResult)
            {
                $pdo->rollBack();
                throw new Exception("Failed to insert new house.");
            }

            // Get the ID of the newly created house
            $houseId = $pdo->lastInsertId();

            // Get the role ID for the house owner and member
            $ownerRoleId = self::getRoleIdForKeyword($pdo, $playerUUID, 'house_owner');
            $memberRoleId = self::getRoleIdForKeyword($pdo, $playerUUID, 'house_member');
            if ($ownerRoleId === false || $memberRoleId === false)
            {
                $pdo->rollBack();
                throw new Exception("Failed to retrieve role IDs.");
            }

            // Update the house creator's house_id and house_role_id
            $updatePlayerQuery = $pdo->prepare(
                "UPDATE players SET house_id = :houseId, house_role_id = :ownerRoleId WHERE player_uuid = :playerUUID"
            );
            $updatePlayerResult = $updatePlayerQuery->execute([
                ':houseId' => $houseId,
                ':ownerRoleId' => $ownerRoleId,
                ':playerUUID' => $playerUUID,
            ]);
            if (!$updatePlayerResult)
            {
                $pdo->rollBack();
                throw new Exception("Failed to update player with new house information.");
            }

            // Initialize batch processing for followers
            $allFollowerIds = []; // Array to store the player_ids of all followers
            $currentBatch = [self::getPlayerIdFromUUID($pdo, $playerUUID)]; // Start with the player who created the house

            while (!empty($currentBatch))
            {
                $placeholders = implode(',', array_fill(0, count($currentBatch), '?'));
                $followersQuery = $pdo->prepare("SELECT player_id FROM players WHERE player_following_id IN ($placeholders)");
                $followersQuery->execute($currentBatch);
                $currentBatch = $followersQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                $allFollowerIds = array_merge($allFollowerIds, $currentBatch);
            }

            // Update all followers in one batch
            foreach ($allFollowerIds as $followerId)
            {
                $updateFollowerQuery = $pdo->prepare(
                    "UPDATE players SET house_id = :houseId, house_role_id = :memberRoleId WHERE player_id = :followerId"
                );
                $updateFollowerResult = $updateFollowerQuery->execute([
                    ':houseId' => $houseId,
                    ':memberRoleId' => $memberRoleId,
                    ':followerId' => $followerId,
                ]);
                if (!$updateFollowerResult)
                {
                    $pdo->rollBack();
                    throw new Exception("Failed to update follower with new house information.");
                }
            }

            $pdo->commit();
            return ['status' => 200, 'message' => "House created successfully, owner and all followers updated."];
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return ['status' => 500, 'message' => "Database error: " . $e->getMessage()];
        }
        catch (Exception $e)
        {
            $pdo->rollBack();
            return ['status' => 500, 'message' => "Operation failed: " . $e->getMessage()];
        }
    }

    private static function getPlayerIdFromUUID($pdo, $playerUUID)
    {
        $query = $pdo->prepare("SELECT player_id FROM players WHERE player_uuid = :playerUUID");
        $query->execute([':playerUUID' => $playerUUID]);
        return $query->fetchColumn();
    }

    public static function getAllFollowerUUIDs($pdo, $playerUUID)
    {
        try
        {
            // Initialize an array to keep track of all the followers' UUIDs
            $allFollowers = [];
            $processedUUIDs = []; // To avoid reprocessing the same UUIDs

            // Initialize the stack with the initial player's UUID
            $stack = [$playerUUID];

            while (!empty($stack))
            {
                $currentUUID = array_pop($stack);

                if (in_array($currentUUID, $processedUUIDs))
                {
                    continue; // Skip if this UUID has already been processed
                }

                // Prepare the SQL to find the followers of the current UUID
                $followersQuery = $pdo->prepare(
                    "SELECT player_uuid FROM players WHERE player_following_id = (
                    SELECT player_id FROM players WHERE player_uuid = :currentUUID
                )"
                );
                $followersQuery->execute([':currentUUID' => $currentUUID]);
                $followers = $followersQuery->fetchAll(PDO::FETCH_COLUMN, 0);

                // Add the newly found followers to the stack if they are not already in the allFollowers array
                foreach ($followers as $followerUUID)
                {
                    if (!in_array($followerUUID, $allFollowers))
                    {
                        $allFollowers[] = $followerUUID; // Add to the all followers list
                        $stack[] = $followerUUID; // Add to the stack to search their followers in the next iterations
                    }
                }

                $processedUUIDs[] = $currentUUID; // Mark this UUID as processed
            }

            return $allFollowers; // Return the complete list of followers' UUIDs
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false; // Return false to indicate failure
        }
    }

    public static function getPlayerHouseInfo($pdo, $playerUUID)
    {
        try
        {
            // Prepare the SQL to find the player's house, if any
            $query = "
            SELECT
                h.house_name,
                ho.player_uuid AS house_owner_uuid,
                ho.player_name AS house_owner_name,
                o1.player_uuid AS officer_1_uuid,
                o1.player_name AS officer_1_name,
                o2.player_uuid AS officer_2_uuid,
                o2.player_name AS officer_2_name
            FROM players p
            LEFT JOIN houses h ON p.house_id = h.house_id
            LEFT JOIN players ho ON h.house_owner_uuid = ho.player_uuid
            LEFT JOIN players o1 ON h.house_officer_1_uuid = o1.player_uuid
            LEFT JOIN players o2 ON h.house_officer_2_uuid = o2.player_uuid
            WHERE p.player_uuid = :playerUUID
        ";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if the player is in a house
            if ($result && $result['house_name'] !== null)
            {
                // Player is in a house, return house information
                return [
                    'status' => 200,
                    'inHouse' => true,
                    'houseInfo' => [
                        'houseName' => $result['house_name'],
                        'houseOwner' => [
                            'uuid' => $result['house_owner_uuid'],
                            'name' => $result['house_owner_name'],
                        ],
                        'officers' => [
                            'officer1' => [
                                'uuid' => $result['officer_1_uuid'],
                                'name' => $result['officer_1_name'],
                            ],
                            'officer2' => [
                                'uuid' => $result['officer_2_uuid'],
                                'name' => $result['officer_2_name'],
                            ],
                        ],
                    ],
                ];
            } else
            {
                // Player is not in a house, return message stating so
                return [
                    'status' => 200,
                    'inHouse' => false,
                    'message' => 'Player is not in any house.',
                ];
            }
        }
        catch (PDOException $e)
        {
            // Handle database error
            return [
                'status' => 500,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }
    public static function getPlayerInfoForNewFollowers($pdo, $playerUUID)
    {
        try
        {
            // First, check if the player exists in the system
            $playerExistsQuery = $pdo->prepare("SELECT COUNT(*) FROM players WHERE player_uuid = :playerUUID");
            $playerExistsQuery->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $playerExistsQuery->execute();

            // If player count is 0, then the player does not exist in the system
            if ($playerExistsQuery->fetchColumn() == 0)
            {
                return [
                    'status' => 404,
                    'message' => 'Player not found in the system.',
                ];
            }

            // Prepare the SQL to find the player's house, if any
            $query = "
            SELECT
                h.house_id,
                h.house_name,
                h.house_owner_uuid,
                h.house_owner_name,
                h.house_officer_1_uuid,
                h.house_officer_1_name,
                h.house_officer_2_uuid,
                h.house_officer_2_name
            FROM players p
            LEFT JOIN houses h ON p.house_id = h.house_id
            WHERE p.player_uuid = :playerUUID
        ";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if the player is in a house
            if ($result && $result['house_name'] !== null)
            {
                // Player is in a house, return house information
                return [
                    'status' => 200,
                    'message' => 'Player is in a house.',
                    'extra' => [
                        'in_house' => '1',
                        'house_id' => $result['house_id'],
                        'house_name' => $result['house_name'],
                        'house_owner_uuid' => $result['house_owner_uuid'],
                        'house_owner_name' => $result['house_owner_name'],
                        'officer_1_uuid' => $result['house_officer_1_uuid'],
                        'officer_1_name' => $result['house_officer_1_name'],
                        'officer_2_uuid' => $result['house_officer_2_uuid'],
                        'officer_2_name' => $result['house_officer_2_name'],
                    ],
                ];
            } else
            {
                // Player is not in a house, return message stating so
                return [
                    'status' => 200,
                    'message' => 'Player is not in any house.',
                    'extra' => [
                        'in_house' => '0',
                    ],
                ];
            }
        }
        catch (PDOException $e)
        {
            // Handle database error
            return [
                'status' => 500,
                'message' => 'Database error.',
                'extra' => ['error_info' => $e->getMessage()],
            ];
        }
    }

    public static function addFollower($pdo, $followerUUID, $leaderUUID)
    {
        try
        {
            // Begin a new transaction
            $pdo->beginTransaction();

            // Extract leader's details
            $leaderId = PlayerDataController::getPlayerIdByUUID($pdo, $leaderUUID);
            $leaderHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $leaderUUID);
            $leaderSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $leaderUUID);

            // Check if the leader exists
            if (!$leaderId)
            {
                $pdo->rollBack();
                return ['status' => 404, 'message' => 'Leader not found.'];
            }

            // Fetch the follower's clan role
            $playerClanRoleId = ClanHelperController::getClanRoleId($pdo, $followerUUID);
            $playerClanRoleKeyword = $playerClanRoleId ? ClanHelperController::getClanRoleKeyword($pdo, $playerClanRoleId) : null;

            // Fetch the follower's current house role
            $playerHouseRole = HouseHelperController::getCurrentHouseRole($pdo, $followerUUID);

            // Prevent if the follower is a clan owner or co-owner
            if (in_array($playerClanRoleKeyword, ['clan_owner', 'clan_co_owner']))
            {
                $pdo->rollBack();
                return ['status' => 400, 'message' => 'Follower is a clan owner or co-owner. Cannot add follower.'];
            }

            // Prevent if the follower is a house owner
            // if (in_array($playerHouseRole, ['house_owner'])) {
            //     $pdo->rollBack();
            //     return ['status' => 400, 'message' => 'Follower is a house owner. Cannot add follower.'];
            // }

            // Extract follower's details
            $followerId = HouseController::getPlayerIdByUUID($pdo, $followerUUID);
            $followerHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $followerUUID);
            $followerSpeciesId = PlayerDataController::getPlayerSpeciesId($pdo, $followerUUID);

            if (!$followerId)
            {
                $pdo->rollBack();
                return ['status' => 404, 'message' => 'Follower not found.'];
            }

            // Check if follower and leader are of the same species
            if ($followerSpeciesId != $leaderSpeciesId)
            {
                error_log("Follower and leader must be of the same species. Follower: " . $followerSpeciesId . " Leader: " . $leaderSpeciesId);
                $pdo->rollBack();
                return ['status' => 400, 'message' => 'Follower and leader must be of the same species.'];
            }

            // Recursive function to handle followers
            if ($followerHouseId !== null)
            {
                try
                {
                    self::removeFollowersFromHouse($pdo, $followerUUID, $followerHouseId);
                }
                catch (Exception $e)
                {
                    $pdo->rollBack();
                    return new JsonResponse(403, $e->getMessage());
                }
            }

            // Determine member role ID based on leader's house status
            $memberRoleId = null;
            if ($leaderHouseId)
            {
                // If the leader is in a house, get the member role ID
                $memberRoleId = self::getRoleIdForKeyword($pdo, $followerUUID, 'house_member');
                if ($memberRoleId === false)
                {
                    $pdo->rollBack();
                    return ['status' => 500, 'message' => 'Failed to retrieve member role ID.'];
                }
            }

            // Update the follower with the leader's house and role or nullify them
            $updateFollowerQuery = $pdo->prepare("UPDATE players SET player_following_id = :leaderId, house_id = :houseId, house_role_id = :memberRoleId, player_title = NULL WHERE player_uuid = :followerUUID");
            $updateFollowerQuery->bindParam(':leaderId', $leaderId, PDO::PARAM_INT);
            $updateFollowerQuery->bindParam(':houseId', $leaderHouseId, PDO::PARAM_INT);
            $updateFollowerQuery->bindValue(':memberRoleId', $memberRoleId, $memberRoleId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $updateFollowerQuery->bindParam(':followerUUID', $followerUUID, PDO::PARAM_STR);
            $updateResult = $updateFollowerQuery->execute();

            if (!$updateResult)
            {
                $pdo->rollBack();
                return ['status' => 500, 'message' => 'Failed to update follower.'];
            }

            // Batch update for all followers and their downline
            $allFollowerIds = [$followerId]; // Start with the current follower
            $currentBatch = [$followerId];
            while (!empty($currentBatch))
            {
                $placeholders = implode(',', array_fill(0, count($currentBatch), '?'));
                $followersQuery = $pdo->prepare("SELECT player_id FROM players WHERE player_following_id IN ($placeholders)");
                $followersQuery->execute($currentBatch);
                $currentBatch = $followersQuery->fetchAll(PDO::FETCH_COLUMN, 0);
                $allFollowerIds = array_merge($allFollowerIds, $currentBatch);
            }

            // Perform the update if there are followers to update
            if (!empty($allFollowerIds))
            {
                $placeholders = implode(',', array_fill(0, count($allFollowerIds), '?'));
                $updateFollowersQuery = $pdo->prepare("UPDATE players SET house_id = ?, house_role_id = ?, player_title = NULL WHERE player_id IN ($placeholders)");
                $updateFollowersQuery->execute(array_merge([$leaderHouseId, $memberRoleId], $allFollowerIds));
            }

            // Commit the transaction
            $pdo->commit();
            return ['status' => 200, 'message' => 'Follower added successfully.'];

        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return ['status' => 500, 'message' => 'Database error: ' . $e->getMessage()];
        }
        catch (Exception $e)
        {
            $pdo->rollBack();
            return ['status' => 500, 'message' => 'Operation failed: ' . $e->getMessage()];
        }
    }

    public static function setPlayerTitle($pdo, $playerUuid, $targetPlayerUuid, $title)
    {
        try
        {
            // Get the house ID of the player setting the title
            $houseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUuid);

            // Get the house ID of the player whose title is being set
            $targetHouseId = HouseHelperController::getHouseIdByPlayerUUID($pdo, $targetPlayerUuid);

            // Check if the player whose title is being set belongs to the same house
            if ($houseId !== $targetHouseId)
            {
                return new JsonResponse(403, "The player whose title is being set must belong to the same house.");
            }

            // Get the house role of the player setting the title
            $houseRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUuid);

            // Check if the player setting the title is the house owner
            if ($houseRole !== 'house_owner')
            {
                return new JsonResponse(403, "Only the house owner can set player titles.");
            }

            // Check if the title length exceeds 25 characters
            if (strlen($title) > 25)
            {
                return new JsonResponse(400, "Player title length should not exceed 25 characters.");
            }

            $query = "UPDATE players SET player_title = :title WHERE player_uuid = :targetPlayerUuid";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':targetPlayerUuid', $targetPlayerUuid);
            $stmt->execute();
            return new JsonResponse(200, "Player title updated successfully.");
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Failed to update player title: " . $e->getMessage());
        }
    }
























    // Utility function to fetch all downline UUIDs recursively
    public static function fetchAllDownlineUUIDs($pdo, $initialFollowerUUID)
    {
        $allFollowerUUIDs = [];
        $currentBatch = [$initialFollowerUUID];

        while (!empty($currentBatch))
        {
            $placeholders = implode(',', array_fill(0, count($currentBatch), '?'));
            $query = $pdo->prepare("SELECT player_uuid FROM players WHERE player_following_id IN (" . implode(',', array_fill(0, count($currentBatch), '?')) . ")");
            $query->execute($currentBatch);
            $currentBatch = []; // Reset current batch for next iteration
            while ($row = $query->fetch(PDO::FETCH_ASSOC))
            {
                $currentBatch[] = $row['player_uuid']; // Prepare for next level
                $allFollowerUUIDs[] = $row['player_uuid']; // Add to all followers
            }
        }

        return $allFollowerUUIDs;
    }

    public static function getPlayerSpeciesId($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("SELECT species_id FROM players WHERE player_uuid = ?");
            $query->execute([$playerUUID]);
            return $query->fetchColumn();
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getHouseSpeciesId($pdo, $houseID)
    {
        try
        {
            $query = $pdo->prepare("SELECT species_id FROM houses WHERE house_id = ?");
            $query->execute([$houseID]);
            return $query->fetchColumn();
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getHouseTypeIdForSpecies($pdo, $speciesId)
    {
        try
        {
            $query = $pdo->prepare("SELECT house_type_id FROM house_types WHERE species_id = :speciesId");
            $query->execute([':speciesId' => $speciesId]);
            $houseTypeId = $query->fetchColumn();

            if (!$houseTypeId)
            {
                throw new Exception("House type for species not found.");
            }

            return $houseTypeId;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getHouseTypeNameForSpecies($pdo, $speciesId)
    {
        try
        {
            $query = $pdo->prepare("SELECT house_type_name FROM house_types WHERE species_id = :speciesId LIMIT 1");
            $query->execute([':speciesId' => $speciesId]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['house_type_name'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }



}
