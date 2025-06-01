<?php

namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

///class
class ClanHelperController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // get the version number by name
    public static function getVersionNumberByName($pdo, $versionName)
    {
        try
        {
            // Prepare the SQL query to select version_number from the version table
            $query = $pdo->prepare("SELECT version_number FROM `version` WHERE version_name = :versionName LIMIT 1");
            $query->bindParam(':versionName', $versionName, PDO::PARAM_STR);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result)
            {
                return $result['version_number'];
            } else
            {
                return null; // Version name not found or version number not set
            }
        }
        catch (PDOException $e)
        {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }
    // get the clan role keyword useing clan_role_id
    public static function getClanRoleKeyword($pdo, $clanRoleId)
    {
        try
        {
            $query = $pdo->prepare("SELECT clan_role_keyword FROM player_role_clan WHERE clan_role_id = :clanRoleId");
            $query->bindParam(':clanRoleId', $clanRoleId, PDO::PARAM_INT);
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['clan_role_keyword'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // get the clan role id 
    public static function getClanRoleId($pdo, $playerUUID)
    {
        try
        {
            $query = $pdo->prepare("SELECT clan_role_id FROM players WHERE player_uuid = :playerUUID");
            $query->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['clan_role_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }


    // Helper function to count the total members in a clan using JOIN
    public static function countMembersInClan($pdo, $clanId)
    {
        try
        {
            // Query to count all players in houses that belong to the specified clan
            $query = $pdo->prepare("
                SELECT COUNT(p.player_id) 
                FROM players p
                JOIN houses h ON p.house_id = h.house_id
                WHERE h.clan_id = :clanId
            ");
            $query->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $query->execute();

            $result = $query->fetchColumn();
            return $result !== false ? $result : 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in countMembersInClan: " . $e->getMessage());
            return 0; // Return 0 in case of an error
        }
    }

    // Helper function to count the total houses in a clan
    public static function countHousesInClan($pdo, $clanId)
    {
        try
        {
            $query = $pdo->prepare("SELECT COUNT(*) FROM houses WHERE clan_id = :clanId");
            $query->execute([':clanId' => $clanId]);
            $result = $query->fetchColumn();
            return $result !== false ? $result : 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in countHousesInClan: " . $e->getMessage());
            return 0; // Return 0 in case of an error
        }
    }

    // get the clan id by player uuid
    public static function getClanIdByPlayerUUID($pdo, $playerUUID)
    {
        try
        {
            // First, get the house_id associated with the player
            $houseQuery = $pdo->prepare("SELECT house_id FROM players WHERE player_uuid = :playerUUID");
            $houseQuery->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $houseQuery->execute();

            $houseResult = $houseQuery->fetch(PDO::FETCH_ASSOC);
            if ($houseResult && $houseResult['house_id'])
            {
                // Now, get the clan_id associated with the house
                $clanQuery = $pdo->prepare("SELECT clan_id FROM houses WHERE house_id = :houseId");
                $clanQuery->bindParam(':houseId', $houseResult['house_id'], PDO::PARAM_INT);
                $clanQuery->execute();

                $clanResult = $clanQuery->fetch(PDO::FETCH_ASSOC);
                return $clanResult ? $clanResult['clan_id'] : null;
            } else
            {
                return null; // Player not found or not associated with a house
            }
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // Helper function to check if any member of a house holds a significant clan role
    public static function checkHouseMembersForClanRoles($pdo, $houseId)
    {
        try
        {
            $query = $pdo->prepare("
                SELECT COUNT(*) 
                FROM players 
                WHERE house_id = :houseId AND clan_role_id IN 
                    (SELECT clan_role_id 
                     FROM player_role_clan 
                     WHERE clan_role_keyword IN ('clan_owner', 'clan_co_owner', 'clan_officer_1', 'clan_officer_2'))
            ");
            $query->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $query->execute();

            // Return true if no house members hold significant roles, false otherwise
            return $query->fetchColumn() == 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false; // In case of error, conservatively return false
        }
    }

    // Helper function to check if any member of a house holds a significant bloodline role
    public static function checkHouseMembersForBloodlineRoles($pdo, $houseId)
    {
        try
        {
            $query = $pdo->prepare("
                SELECT COUNT(*) 
                FROM players 
                WHERE house_id = :houseId 
                  AND bloodline_role_id IN (
                      SELECT bloodline_role_id
                      FROM player_role_bloodline
                      WHERE player_role_bloodline_keyword IN ('bloodline_owner', 'bloodline_officer_1', 'bloodline_officer_2')
                  )
            ");
            $query->bindParam(':houseId', $houseId, PDO::PARAM_INT);
            $query->execute();
            // Return true if no house members hold any of the significant bloodline roles
            return $query->fetchColumn() == 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in checkHouseMembersForBloodlineRoles: " . $e->getMessage());
            return false;
        }
    }

    // Update the clan role for a player
    public static function updatePlayerClanRole($pdo, $playerUUID, $newRoleId)
    {
        try
        {
            $updateQuery = $pdo->prepare("UPDATE players SET clan_role_id = :newRoleId WHERE player_uuid = :playerUUID");
            $updateQuery->bindParam(':newRoleId', $newRoleId, PDO::PARAM_INT);
            $updateQuery->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $updateQuery->execute();

            return $updateQuery->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // get the clan role id by keyword
    public static function getClanRoleIdByKeyword($pdo, $roleKeyword)
    {
        try
        {
            $query = $pdo->prepare("SELECT clan_role_id FROM player_role_clan WHERE clan_role_keyword = :roleKeyword");
            $query->bindParam(':roleKeyword', $roleKeyword, PDO::PARAM_STR);
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['clan_role_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // Helper function to update the UUID for a specific clan role
    public static function updateClanRoleUUID($pdo, $clanId, $playerUUID, $roleKeyword)
    {
        try
        {
            $columnNameUuid = $roleKeyword . "_uuid";
            $updateQuery = $pdo->prepare("
                UPDATE clans 
                SET `$columnNameUuid` = :playerUUID 
                WHERE clan_id = :clanId
            ");
            $updateQuery->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $updateQuery->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $updateQuery->execute();

            return $updateQuery->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // Helper function to update the name for a specific clan role
    public static function updateClanRoleName($pdo, $clanId, $playerName, $roleKeyword)
    {
        try
        {
            $columnNameName = $roleKeyword . "_name";
            $updateQuery = $pdo->prepare("
                UPDATE clans 
                SET `$columnNameName` = :playerName 
                WHERE clan_id = :clanId
            ");
            $updateQuery->bindParam(':playerName', $playerName, PDO::PARAM_STR);
            $updateQuery->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $updateQuery->execute();

            return $updateQuery->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // Helper function to get the clan name by clan ID
    public static function getClanNameById($pdo, $clanId)
    {
        try
        {
            // Prepare the SQL query to select the clan name from the clans table
            $query = $pdo->prepare("SELECT clan_name FROM clans WHERE clan_id = :clanId");
            $query->bindParam(':clanId', $clanId, PDO::PARAM_INT);
            $query->execute();

            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result)
            {
                return $result['clan_name'];
            } else
            {
                return null; // Clan not found
            }
        }
        catch (PDOException $e)
        {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }

    public static function getClanBloodline(PDO $pdo, int $clanId)
    {
        try
        {
            $stmt = $pdo->prepare("SELECT bloodline_id FROM clans WHERE clan_id = :clanId");
            $stmt->execute([':clanId' => $clanId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getClanBloodline: " . $e->getMessage());
            return null;
        }
    }
    public static function getPlayerClanBloodlineName(PDO $pdo, string $playerUUID)
    {
        try
        {
            // Get the clan ID associated with the player
            $clanId = self::getClanIdByPlayerUUID($pdo, $playerUUID);
            if (!$clanId)
            {
                return null; // Player is not associated with a clan
            }

            // Retrieve the bloodline ID from the clans table using the clan ID
            $stmt = $pdo->prepare("SELECT bloodline_id FROM clans WHERE clan_id = :clanId");
            $stmt->execute([':clanId' => $clanId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$row['bloodline_id'])
            {
                return null; // Clan not found or not associated with a bloodline
            }

            $bloodlineId = $row['bloodline_id'];
            // Get the bloodline name using the existing helper in BloodlineHelperController
            return BloodlineHelperController::getBloodlineNameById($pdo, $bloodlineId);
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getPlayerClanBloodlineName: " . $e->getMessage());
            return null;
        }
    }




}
