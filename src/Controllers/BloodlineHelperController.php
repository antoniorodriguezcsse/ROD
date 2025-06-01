<?php

namespace Fallen\SecondLife\Controllers;

use PDO;
use PDOException;
use Fallen\SecondLife\Classes\JsonResponse;

class BloodlineHelperController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getPlayerBloodlineRole($pdo, string $playerUUID)
    {
        try
        {
            // Get the player's bloodline role ID from the players table.
            $roleId = self::getBloodlineRoleId($pdo, $playerUUID);
            if (!$roleId)
            {
                return null; // or return an error message as needed
            }

            // Using the role ID, fetch the role keyword (e.g., 'bloodline_owner', 'bloodline_officer_1')
            $roleKeyword = self::getBloodlineRoleKeyword($pdo, (int) $roleId);
            return $roleKeyword;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getPlayerBloodlineRole: " . $e->getMessage());
            return null;
        }
    }
    public static function getPlayerBloodlineRoleName(PDO $pdo, string $playerUUID)
    {
        try
        {
            // 1) Get the player's bloodline role ID from the players table.
            $roleId = self::getBloodlineRoleId($pdo, $playerUUID);
            if (!$roleId)
            {
                return null; // Or handle the error as needed.
            }

            // 2) Query the player_role_bloodline table to get the full role name.
            $stmt = $pdo->prepare("
            SELECT player_bloodline_role_name
            FROM player_role_bloodline
            WHERE bloodline_role_id = :roleId
        ");
            $stmt->execute([':roleId' => $roleId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['player_bloodline_role_name'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getPlayerBloodlineRoleName: " . $e->getMessage());
            return null;
        }
    }
    public static function getPlayerBloodlineRoleNameByPlayerId(PDO $pdo, int $playerId)
    {
        try
        {
            // 1) Get the player's bloodline role ID from the players table using player_id.
            $stmt = $pdo->prepare("
                SELECT bloodline_role_id
                FROM players
                WHERE player_id = :playerId
                LIMIT 1
            ");
            $stmt->execute([':playerId' => $playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !$row['bloodline_role_id'])
            {
                return null; // Or handle error as needed.
            }
            $roleId = $row['bloodline_role_id'];

            // 2) Query the player_role_bloodline table to get the full role name.
            $stmt = $pdo->prepare("
                SELECT player_bloodline_role_name
                FROM player_role_bloodline
                WHERE bloodline_role_id = :roleId
            ");
            $stmt->execute([':roleId' => $roleId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['player_bloodline_role_name'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getPlayerBloodlineRoleNameByPlayerId: " . $e->getMessage());
            return null;
        }
    }


    // -------------------------------------------------------------------------
    // 1) Get the bloodline_id that a player is in, via their player_uuid
    // -------------------------------------------------------------------------
    public static function getBloodlineIdByPlayerUUID(PDO $pdo, string $playerUUID)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT bloodline_id
                FROM players
                WHERE player_uuid = :playerUUID
                LIMIT 1
            ");
            $stmt->execute([':playerUUID' => $playerUUID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    public static function getLoyalBloodlineIdByPlayerUUID(PDO $pdo, string $playerUUID)
    {
        $sql = "
            SELECT c.bloodline_id
              FROM players p
        INNER JOIN houses  h ON p.house_id  = h.house_id
        INNER JOIN clans   c ON h.clan_id   = c.clan_id
             WHERE p.player_uuid = :playerUUID
             LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['playerUUID' => $playerUUID]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['bloodline_id'] : null;
    }

    public static function isPrivilegedInBloodline(PDO $pdo, int $bloodlineId, string $playerUUID): bool
    {
        $stmt = $pdo->prepare("
        SELECT
            bloodline_owner_uuid,
            bloodline_co_owner_uuid,
            bloodline_officer_1_uuid,
            bloodline_officer_2_uuid
        FROM bloodlines
        WHERE bloodline_id = :bloodlineId
        LIMIT 1
    ");
        $stmt->execute(['bloodlineId' => $bloodlineId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row)
        {
            // no such bloodline
            return false;
        }

        // check if playerUUID matches any of the privileged columns
        return in_array($playerUUID, array_values($row), true);
    }



    // -------------------------------------------------------------------------
    // 2) Get the player's bloodline_role_id from players table
    // -------------------------------------------------------------------------
    public static function getBloodlineRoleId(PDO $pdo, string $playerUUID)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT bloodline_role_id
                FROM players
                WHERE player_uuid = :playerUUID
                LIMIT 1
            ");
            $stmt->execute([':playerUUID' => $playerUUID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_role_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // 3) Get the keyword for a specific bloodline role (owner/officer, etc.)
    //    based on bloodline_role_id
    // -------------------------------------------------------------------------
    public static function getBloodlineRoleKeyword(PDO $pdo, int $bloodlineRoleId)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT player_role_bloodline_keyword
                FROM player_role_bloodline
                WHERE bloodline_role_id = :roleId
            ");
            $stmt->execute([':roleId' => $bloodlineRoleId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['player_role_bloodline_keyword'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // 4) Update a player's bloodline_role_id in players table
    // -------------------------------------------------------------------------
    public static function updatePlayerBloodlineRole(PDO $pdo, string $playerUUID, $newRoleId)
    {
        try
        {
            $stmt = $pdo->prepare("
                UPDATE players
                SET bloodline_role_id = :newRoleId
                WHERE player_uuid = :playerUUID
            ");
            $stmt->bindParam(':newRoleId', $newRoleId);
            $stmt->bindParam(':playerUUID', $playerUUID);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // 5) Get a bloodline_role_id by the “keyword” (bloodline_owner, bloodline_officer_1, etc.)
    //    for the matching species, if that matters
    // -------------------------------------------------------------------------
    public static function getBloodlineRoleIdByKeyword(PDO $pdo, string $roleKeyword, int $speciesId)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT bloodline_role_id
                FROM player_role_bloodline
                WHERE player_role_bloodline_keyword = :roleKeyword
                  AND species_id = :speciesId
                LIMIT 1
            ");
            $stmt->execute([
                ':roleKeyword' => $roleKeyword,
                ':speciesId' => $speciesId
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_role_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // 6) Update a specific bloodline role's UUID in the bloodlines table
    //    E.g. set `bloodline_officer_1_uuid = :playerUUID`
    // -------------------------------------------------------------------------
    public static function updateBloodlineRoleUUID(PDO $pdo, int $bloodlineId, $playerUUID, string $roleKeyword)
    {
        try
        {
            // roleKeyword might be “bloodline_owner” or “bloodline_officer_1” etc.
            // We build the column name accordingly: e.g. “bloodline_officer_1_uuid”
            // but you might store them differently in your table.
            // If you store them in a single column, adapt as needed.
            $columnName = self::convertKeywordToUuidColumn($roleKeyword);

            $sql = "UPDATE bloodlines SET `$columnName` = :playerUUID WHERE bloodline_id = :bloodlineId";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':playerUUID' => $playerUUID,
                ':bloodlineId' => $bloodlineId
            ]);

            return $stmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // 7) Update a specific bloodline role’s “name” in the bloodlines table
    //    e.g. bloodline_officer_1_name
    // -------------------------------------------------------------------------
    public static function updateBloodlineRoleName(PDO $pdo, int $bloodlineId, $playerName, string $roleKeyword)
    {
        try
        {
            $columnName = self::convertKeywordToNameColumn($roleKeyword);

            $sql = "UPDATE bloodlines SET `$columnName` = :playerName WHERE bloodline_id = :bloodlineId";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':playerName' => $playerName,
                ':bloodlineId' => $bloodlineId
            ]);

            return $stmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }
    /**
     * (Optional) If you want role labels from 'player_role_bloodline' (like 'Arch Vampire').
     * Removes or modifies as needed if you don't use species.
     */
    public static function fetchBloodlineRolesForSpecies(PDO $pdo, int $speciesId): array
    {
        try
        {
            $stmt = $pdo->prepare("
            SELECT player_role_bloodline_keyword, player_bloodline_role_name
            FROM player_role_bloodline
            WHERE species_id = :speciesId
        ");
            $stmt->execute([':speciesId' => $speciesId]);

            $roleMap = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
            {
                $keyword = $row['player_role_bloodline_keyword'];  // e.g. 'bloodline_owner'
                $label = $row['player_bloodline_role_name'];     // e.g. 'Arch Vampire'
                $roleMap[$keyword] = $label;
            }
            return $roleMap;
        }
        catch (PDOException $e)
        {
            // if error, just return empty
            return [];
        }
    }

    // Example name columns:
    // bloodline_owner_name, bloodline_officer_1_name, bloodline_officer_2_name
    private static function convertKeywordToNameColumn(string $keyword)
    {
        // e.g. “bloodline_owner” => “bloodline_owner_name”
        // or “bloodline_officer_1” => “bloodline_officer_1_name”
        return $keyword . "_name";
    }
    // Example UUID columns:
    private static function convertKeywordToUuidColumn(string $keyword)
    {
        // e.g. “bloodline_officer_1” => “bloodline_officer_1_uuid”
        return $keyword . "_uuid";
    }

    // -------------------------------------------------------------------------
    // 8) Get a bloodline’s name by ID
    // -------------------------------------------------------------------------
    public static function getBloodlineNameById(PDO $pdo, int $bloodlineId)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT bloodline_name
                FROM bloodlines
                WHERE bloodline_id = :bloodlineId
            ");
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_name'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // 9) Count how many clans are in a given bloodline
    //    (clans has foreign key: clan.bloodline_id -> bloodlines.bloodline_id)
    // -------------------------------------------------------------------------
    public static function countClansInBloodline(PDO $pdo, int $bloodlineId)
    {
        try
        {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total_clans
                FROM clans
                WHERE bloodline_id = :bloodlineId
            ");
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            return (int) $stmt->fetchColumn();
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // 10) Count how many total players are in a bloodline
    //     i.e. all players in all houses of all clans for this bloodline.
    //     Because your chain is players -> houses -> clans -> bloodline.
    // -------------------------------------------------------------------------
    public static function countMembersInBloodline(PDO $pdo, int $bloodlineId)
    {
        try
        {
            $sql = "
                SELECT COUNT(p.player_id)
                FROM players p
                JOIN houses h ON p.house_id = h.house_id
                JOIN clans c  ON h.clan_id  = c.clan_id
                WHERE c.bloodline_id = :bloodlineId
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            return (int) $stmt->fetchColumn();
        }
        catch (PDOException $e)
        {
            error_log("Database Error in countMembersInBloodline: " . $e->getMessage());
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // 11) Possibly a check if any clan in the bloodline has certain roles
    //     (like checkHouseMembersForClanRoles). If you need to ensure no clan 
    //     members hold “bloodline_owner” in another bloodline, replicate logic here.
    // -------------------------------------------------------------------------
    public static function checkClanMembersForBloodlineRoles(PDO $pdo, int $clanId)
    {
        try
        {
            // Example check: do we have any players in this clan that are
            // owners/officers of another bloodline? Adapt if needed.
            $sql = "
                SELECT COUNT(*) 
                FROM players p
                WHERE p.house_id IN (
                    SELECT house_id FROM houses WHERE clan_id = :clanId
                )
                AND p.bloodline_role_id IN (
                    SELECT bloodline_role_id
                    FROM player_role_bloodline
                    WHERE player_role_bloodline_keyword IN (
                        'bloodline_owner',
                        'bloodline_officer_1',
                        'bloodline_officer_2'
                    )
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':clanId' => $clanId]);
            $count = $stmt->fetchColumn();
            // Return TRUE if no conflicts (count == 0), or FALSE otherwise
            return ($count == 0);
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return false;
        }
    }
    public static function getBloodlineLeaderId(PDO $pdo, int $bloodlineId)
    {
        try
        {
            $stmt = $pdo->prepare("SELECT bloodline_leader_id FROM bloodlines WHERE bloodline_id = :bloodlineId");
            $stmt->execute([':bloodlineId' => $bloodlineId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['bloodline_leader_id'] : null;
        }
        catch (PDOException $e)
        {
            error_log("Database Error in getBloodlineLeaderId: " . $e->getMessage());
            return null;
        }
    }

}
