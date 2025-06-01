<?php

namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

///class
class OutcastController extends HouseController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }



    public static function doesPlayerExist($pdo, $uuid)
    {
        try
        {
            // Prepare the SQL statement to check if the UUID exists
            $checkQuery = "SELECT 1 FROM players WHERE player_uuid = :uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $checkStmt->execute();

            // Check if the UUID exists
            return $checkStmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return false; // Assume UUID doesn't exist in case of an error
        }
    }


    private static function getPlayerUuid($pdo, $player_id)
    {
        // Prepare and execute the SQL query
        $stmt = $pdo->prepare("SELECT player_uuid FROM players WHERE player_id = :player_id");
        $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return the player_uuid or null if not found
        return $result['player_uuid'] ?? null;
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

    // Old function to outcast a player and their followers from a house before bloodline was implemented
    // public static function outcastPlayerAndFollowers($pdo, $playerUuid) {
    //     try {
    //         $pdo->beginTransaction();

    //         $houseID = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUuid);

    //         // Fetch the player's clan role
    //         $playerClanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUuid);
    //         $playerClanRoleKeyword = $playerClanRoleId ? ClanHelperController::getClanRoleKeyword($pdo, $playerClanRoleId) : null;

    //         // Prevent outcasting if the player is a clan owner or co-owner
    //         if (in_array($playerClanRoleKeyword, ['clan_owner', 'clan_co_owner'])) {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, "Cannot outcast a clan owner or co-owner.");
    //         }

    //         // Demote if the player is a clan or house officer
    //         if (in_array($playerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2'])) {
    //             $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUuid);
    //             ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $playerClanRoleKeyword);
    //             ClanHelperController::updateClanRoleName($pdo, $clanId, null, $playerClanRoleKeyword);
    //             ClanHelperController::updatePlayerClanRole($pdo, $playerUuid, null);
    //         }

    //         // Handle house officer demotion
    //         $playerHouseRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUuid);
    //         if (in_array($playerHouseRole, ['house_officer_1', 'house_officer_2'])) {
    //             HouseController::removeOfficerNameAndUUID($pdo, $playerUuid);
    //         }

    //         // Recursive function to handle followers
    //         try {
    //             self::removeFollowersFromHouse($pdo, $playerUuid, $houseID);
    //         } catch (Exception $e) {
    //             $pdo->rollBack();
    //             return new JsonResponse(403, $e->getMessage());
    //         }


    //         // Remove the player from the house
    //         $removeQuery = $pdo->prepare("UPDATE players SET house_id = NULL, house_role_id = NULL, player_following_id = NULL, player_title = NULL WHERE player_uuid = :playerUUID");
    //         $removeQuery->execute([':playerUUID' => $playerUuid]);

    //         $pdo->commit();
    //         return new JsonResponse(200, "Player and their followers successfully outcasted.");
    //     } catch (PDOException $e) {
    //         $pdo->rollBack();
    //         return new JsonResponse(500, "Database error: " . $e->getMessage());
    //     } catch (Exception $e) {
    //         $pdo->rollBack();
    //         return new JsonResponse(500, "Internal server error: " . $e->getMessage());
    //     }
    // }


    public static function outcastPlayerAndFollowers($pdo, $playerUuid)
    {
        try
        {
            $pdo->beginTransaction();

            // Get the house ID the player belongs to.
            $houseID = HouseHelperController::getHouseIdByPlayerUUID($pdo, $playerUuid);

            // --- Clan Role Checks ---
            // Fetch the player's clan role
            $playerClanRoleId = ClanHelperController::getClanRoleId($pdo, $playerUuid);
            $playerClanRoleKeyword = $playerClanRoleId ? ClanHelperController::getClanRoleKeyword($pdo, $playerClanRoleId) : null;

            // Prevent outcasting if the player is a clan owner or co-owner
            if (in_array($playerClanRoleKeyword, ['clan_owner', 'clan_co_owner']))
            {
                $pdo->rollBack();
                return new JsonResponse(403, "Cannot outcast a clan owner or co-owner.");
            }

            // If the player is a clan officer, attempt to demote them
            if (in_array($playerClanRoleKeyword, ['clan_officer_1', 'clan_officer_2']))
            {
                $clanId = ClanHelperController::getClanIdByPlayerUUID($pdo, $playerUuid);
                ClanHelperController::updateClanRoleUUID($pdo, $clanId, null, $playerClanRoleKeyword);
                ClanHelperController::updateClanRoleName($pdo, $clanId, null, $playerClanRoleKeyword);
                ClanHelperController::updatePlayerClanRole($pdo, $playerUuid, null);
            }

            // --- Bloodline Role Check ---
            // Fetch the player's bloodline role (if any)
            $playerBloodlineRoleId = BloodlineHelperController::getBloodlineRoleId($pdo, $playerUuid);
            if ($playerBloodlineRoleId)
            {
                $playerBloodlineRoleKeyword = BloodlineHelperController::getBloodlineRoleKeyword($pdo, (int) $playerBloodlineRoleId);
                // Block outcasting if the player holds any significant bloodline role
                if (in_array($playerBloodlineRoleKeyword, ['bloodline_owner', 'bloodline_officer_1', 'bloodline_officer_2']))
                {
                    $pdo->rollBack();
                    return new JsonResponse(403, "Cannot outcast a player with a significant bloodline role.");
                }
            }

            // --- House Role Check ---
            // If the player is a house officer, remove the officer details
            $playerHouseRole = HouseHelperController::getCurrentHouseRole($pdo, $playerUuid);
            if (in_array($playerHouseRole, ['house_officer_1', 'house_officer_2']))
            {
                HouseController::removeOfficerNameAndUUID($pdo, $playerUuid);
            }

            // --- Remove Followers Recursively ---
            try
            {
                self::removeFollowersFromHouse($pdo, $playerUuid, $houseID);
            }
            catch (Exception $e)
            {
                $pdo->rollBack();
                return new JsonResponse(403, $e->getMessage());
            }

            // --- Remove the Player from the House ---
            $removeQuery = $pdo->prepare("
                UPDATE players 
                SET house_id = NULL, house_role_id = NULL, player_following_id = NULL, player_title = NULL 
                WHERE player_uuid = :playerUUID
            ");
            $removeQuery->execute([':playerUUID' => $playerUuid]);

            $pdo->commit();
            return new JsonResponse(200, "Player and their followers successfully outcasted.");
        }
        catch (PDOException $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        }
        catch (Exception $e)
        {
            $pdo->rollBack();
            return new JsonResponse(500, "Internal server error: " . $e->getMessage());
        }
    }

    public static function updatePlayerFollowingIdToNull($pdo, $playerUuid)
    {
        try
        {
            $tableName = 'players';

            // Prepare the SQL statement
            $stmt = $pdo->prepare("UPDATE $tableName SET player_following_id = NULL WHERE player_uuid = :playerUuid");

            // Bind the parameter
            $stmt->bindParam(':playerUuid', $playerUuid);

            // Execute the update
            $stmt->execute();

            // Check if any rows were affected
            $rowCount = $stmt->rowCount();

            if ($rowCount > 0)
            {
                // Rows were affected, update successful
                return true;
            } else
            {
                // No rows were affected, player UUID not found
                return false;
            }
        }
        catch (PDOException $e)
        {
            // Handle any database errors here
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
    public static function updateData($pdo, $tableName, $playerUuid, $dataToUpdate)
    {
        try
        {
            // ==============================
            // STEP 1: INPUT VALIDATION
            // Ensure the table name, column names, and UUID are valid to protect 
            // against SQL injection. Implement your validation logic here.
            // (Note: Actual implementation of validation is not provided here)
            // ==============================

            // ==============================
            // STEP 2: DATABASE CONNECTION
            // Utilize connection details stored in class static variables to 
            // establish a PDO connection with the MySQL database.
            // ==============================


            // ==============================
            // STEP 3: CHECK UUID EXISTENCE
            // Before attempting an update, ensure that a record with the 
            // specified UUID exists in the database.
            // ==============================
            $checkQuery = "SELECT 1 FROM $tableName WHERE player_uuid = :player_uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            $checkStmt->execute();
            if ($checkStmt->rowCount() == 0)
            {
                return new JsonResponse(400, "Player UUID does not exist.");
            }

            // ==============================
            // STEP 4: CONSTRUCT SET CLAUSE
            // Generate the SET clause for the SQL query by iterating over
            // the $dataToUpdate array and creating "column = :placeholder" pairs.
            // ==============================
            $setClause = [];
            foreach ($dataToUpdate as $column => $value)
            {
                $setClause[] = "$column = :$column";
            }
            $setClauseStr = implode(", ", $setClause);

            // ==============================
            // STEP 5: PREPARE UPDATE QUERY
            // Create the SQL UPDATE query with placeholders to prevent 
            // SQL injection.
            // ==============================
            $updateQuery = "UPDATE $tableName SET $setClauseStr WHERE player_uuid = :player_uuid";
            $updateStmt = $pdo->prepare($updateQuery);

            // ==============================
            // STEP 6: BIND PARAMETER VALUES
            // Replace placeholders with actual data in a safe manner.
            // ==============================
            $updateStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            foreach ($dataToUpdate as $column => $value)
            {
                $updateStmt->bindParam(":$column", $dataToUpdate[$column]);
            }

            // ==============================
            // STEP 7: EXECUTE THE QUERY
            // Run the SQL query on the database.
            // ==============================
            $updateStmt->execute();

            // ==============================
            // STEP 8: VERIFY UPDATE SUCCESS
            // Check whether the query made any changes and return a corresponding message.
            // ==============================

            // Check if there were any changes (updates)
            if ($updateStmt->rowCount() > 0)
            {
                return new JsonResponse(200, "Data updated successfully.");
            } else
            {
                // Check if the update query actually modified any data
                //  $updateStmt->debugDumpParams(); // Remove this line in production
                $meta = $updateStmt->errorInfo();

                // Check for the specific MySQL error code indicating no changes were made
                if ($meta[1] == 0)
                {
                    return new JsonResponse(200, "No data updated. The provided data is the same as existing data.");
                } else
                {
                    // Handle other errors
                    error_log("Database Error: " . $meta[2]);
                    return new JsonResponse(500, "Database Error: " . $meta[2]);
                }
            }
        }
        catch (PDOException $e)
        {
            // ==============================
            // STEP 9: ERROR HANDLING
            // Log any errors that occur and return a user-friendly message.
            // ==============================
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }
}
