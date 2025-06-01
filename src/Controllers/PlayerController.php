<?php
namespace Fallen\SecondLife\Controllers;

//use Fallen\SecondLife\Classes\Db;
use Exception;
//use Fallen\SecondLife\Controllers\VampireController;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Classes\SecondLifeHeaders;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Controllers\TableController;
use PDO;
use PDOException;

//get player dSata with a class
///class
class PlayerController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function create($pdo, string $playerUUID, string $legacy_name, string $species_id)
    {
        try
        {
            // Check if the player_uuid already exists in the players table
            $checkQuery = "SELECT * FROM players WHERE player_uuid = :player_uuid LIMIT 1";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
            $checkStmt->execute();

            if ($checkStmt->fetch())
            {
                return new JsonResponse(409, "Player exists.");
            }

            // Check if the player_uuid exists in the humans table
            $checkHumanQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :player_uuid";
            $checkHumanStmt = $pdo->prepare($checkHumanQuery);
            $checkHumanStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
            $checkHumanStmt->execute();
            $rowCount = $checkHumanStmt->fetchColumn();

            if ($rowCount > 0)
            {
                // Player is in the humans table, remove them from there
                $deleteHumanQuery = "DELETE FROM humans WHERE human_uuid = :player_uuid";
                $deleteHumanStmt = $pdo->prepare($deleteHumanQuery);
                $deleteHumanStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
                $deleteHumanStmt->execute();
            }

            // Insert the new player into the players table
            $insertQuery = "INSERT INTO players (player_uuid, legacy_name, species_id)
                            VALUES (?, ?, ?)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(1, $playerUUID, PDO::PARAM_STR);
            $insertStmt->bindParam(2, $legacy_name, PDO::PARAM_STR);
            $insertStmt->bindParam(3, $species_id, PDO::PARAM_INT);
            $success = $insertStmt->execute();

            if ($success)
            {
                // Player created successfully
                return new JsonResponse(201, "Player created.");
            } else
            {
                // Handle the failure case
                return new JsonResponse(500, "Failed to insert player data");
            }
        }
        catch (PDOException $e)
        {
            // Handle database connection errors
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }

    public static function getSire($pdo, $player_id)
    {
        try
        {
            // SQL query to retrieve legacy_name for the given player_id
            $sql = "SELECT legacy_name FROM players WHERE player_id = :player_id";

            // Prepare the statement
            $stmt = $pdo->prepare($sql);

            // Bind the player_id parameter
            $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);

            // Execute the query
            $stmt->execute();

            // Fetch the result
            $result = $stmt->fetch();

            if ($result)
            {
                // Close the database connection
                $pdo = null;

                return $result['legacy_name'];
            } else
            {
                // Close the database connection
                $pdo = null;

                return "Player not found or legacy name is empty.";
            }
        }
        catch (PDOException $e)
        {
            return "Error: " . $e->getMessage();
        }
    }

    public static function getHumanRow($pdo, $playerUUID)
    {
        $sql = "SELECT * FROM humans WHERE human_uuid = :uuid LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['uuid' => $playerUUID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    private static function getGhoulStatus($bloodLevel)
    {
        // For example, let's assume Ghouls can have up to 10 liters (or more).
        // Adjust ranges and names to your liking.
        if ($bloodLevel >= 10.0)
        {
            return "Overflowing with Unholy Vitae"; // Extremely high blood level
        } else if ($bloodLevel >= 8.0)
        {
            return "Blood-Flooded Monstrosity"; // Very high blood
        } else if ($bloodLevel >= 6.0)
        {
            return "Engorged Abomination"; // Above normal human max
        } else if ($bloodLevel >= 4.0)
        {
            return "Twisted Veins"; // Still above average
        } else if ($bloodLevel >= 2.0)
        {
            return "Damned Vessel"; // Mid-range
        } else if ($bloodLevel > 0.0)
        {
            return "Withering Husk"; // Low blood
        } else
        {
            return "Empty Husk"; // 0.0 or below
        }
    }
    private static function getHumanStatus($bloodLevel)
    {
        if ($bloodLevel === -1 || $bloodLevel === 5.0)
        {
            return "Novus Sanguis"; // New Blood
        } else if ($bloodLevel === 0.0)
        {
            return "Sanguis Nullus"; // No Blood
        } else if ($bloodLevel >= 4.0)
        {
            return "Sanguis Plenus"; // Full of Blood
        } else if ($bloodLevel >= 2.0)
        {
            return "Sanguis Medius"; // Moderate
        } else if ($bloodLevel > 0.0)
        {
            return "Sanguis Exiguus"; // Low
        }
        return "Ignotus"; // Catch-all
    }
    private static function addMissingHuman($pdo, $playerUUID, $defaultBlood = 5.0, $defaultGhoulFlag = 0)
    {
        // Insert a new record for this UUID with default values
        $stmt = $pdo->prepare("
        INSERT INTO humans (human_uuid, blood_level, last_feed_on, is_ghoul)
        VALUES (:uuid, :blood, NOW(), :is_ghoul)
    ");
        $stmt->execute([
            'uuid' => $playerUUID,
            'blood' => $defaultBlood,
            'is_ghoul' => $defaultGhoulFlag,
        ]);

        // Re-fetch the newly created record and return it
        return self::getHumanRow($pdo, $playerUUID);
    }

    public static function getPublicScan($pdo, $playerUUID)
    {
        // 1. Check if the entity is an active player (vampire, lycan, witch, etc.).
        if (self::checkPlayerExists($pdo, $playerUUID))
        {
            return self::getSpeciesScan($pdo, $playerUUID, "public");
        }
        // 2. If not an active player, fetch from `humans` table.
        else
        {
            $humanRow = self::getHumanRow($pdo, $playerUUID);

            // If there's no entry for this UUID, create one using the helper.
            if (!$humanRow)
            {
                // Insert a new record with default values (5 liters, not a ghoul).
                $humanRow = self::addMissingHuman($pdo, $playerUUID, 5.0, 0);
            }

            // Now you can safely continue, as $humanRow is guaranteed to exist.
            $isGhoul = !empty($humanRow['is_ghoul']);
            $label = $isGhoul ? "Blighted Ghoul" : "Human";

            $bioRaw = ($isGhoul && !empty($humanRow['ghoul_bio']))
                ? $humanRow['ghoul_bio']
                : "No backstory available.";

            // STEP A: Convert literal "\n" into an actual newline character.
            $bioWithRealNewlines = str_replace('\\n', "\n", $bioRaw);
            // STEP B: Indent each newline so it lines up under the pipe (║).
            $formattedBio = str_replace("\n", "\n║           ", $bioWithRealNewlines);

            // 3. Update blood level (healing or other logic).
            $newBloodLevel = self::updateHumanBloodLevel($pdo, $playerUUID);

            // 4. Show the appropriate scan result based on blood level and ghoul flag.
            if ($newBloodLevel <= 0.0)
            {
                $status = $isGhoul
                    ? self::getGhoulStatus($newBloodLevel)
                    : self::getHumanStatus($newBloodLevel);

                $killerID = $humanRow['killer_player_id'] ?? null;
                if ($killerID)
                {
                    $killerUUID = PlayerDataController::getPlayerUuidById($pdo, $killerID);
                    $killerInfo = "secondlife:///app/agent/{$killerUUID}/about";
                } else
                {
                    $killerInfo = "The Darkness";
                }

                $scanResult =
                    "\n╔═★⋅•⋅∙∘☽ {$label} Scan Result ☾∘∙⋅•⋅★\n"
                    . "║ {$label}: secondlife:///app/agent/{$playerUUID}/about\n"
                    . "║ Status: {$status}\n"
                    . "║ Killed by: {$killerInfo}\n"
                    . ($isGhoul ? "║ Echo: {$formattedBio}\n" : "")
                    . "╚═•⋅⋅•⋅⋅•⋅⊰⋅⋅•⋅⋅•∙⋅∘☽★☾∘⋅∙•⋅⋅•⋅⋅⊰⋅•⋅⋅•";
            } else
            {
                $status = $isGhoul
                    ? self::getGhoulStatus($newBloodLevel)
                    : self::getHumanStatus($newBloodLevel);
                $formattedBlood = number_format($newBloodLevel, 3);

                $scanResult =
                    "\n╔═★⋅•⋅∙∘☽ {$label} Scan Result ☾∘∙⋅•⋅★\n"
                    . "║ {$label}: secondlife:///app/agent/{$playerUUID}/about\n"
                    . "║ Status: {$status}\n"
                    . "║ Blood: {$formattedBlood} liters\n"
                    . ($isGhoul ? "║ Reward: " . self::getGhoulReward($pdo, $playerUUID) . " Essence\n" : "")
                    . ($isGhoul ? "║ Echo: {$formattedBio}\n" : "")
                    . "╚═•⋅⋅•⋅⋅•⋅⊰⋅⋅•⋅⋅•∙⋅∘☽★☾∘⋅∙•⋅⋅•⋅⋅⊰⋅•⋅⋅•";
            }

            return new JsonResponse(200, "human_scan", ["human_scan_result" => $scanResult]);
        }
    }

    public static function getFollowerCounts($pdo, $playerUUID)
    {
        try
        {
            // Prepare and execute the query
            $query = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*)
                     FROM players
                     WHERE player_following_id = (SELECT player_id FROM players WHERE player_uuid = ?)) AS total_followers,

                    (SELECT COUNT(*)
                     FROM players
                     JOIN player_status ON players.player_status_id = player_status.player_status_id
                     WHERE player_following_id = (SELECT player_id FROM players WHERE player_uuid = ?)
                       AND player_status_keyword = 'alive') AS alive_followers
            ");
            $query->execute([$playerUUID, $playerUUID]);
            // Fetch and return the counts
            $result = $query->fetch(PDO::FETCH_ASSOC);

            return [
                'alive_followers' => $result['alive_followers'],
                'total_followers' => $result['total_followers'],
            ];
        }
        catch (PDOException $e)
        {
            error_log("Database Error: " . $e->getMessage());
            return ['alive_followers' => 0, 'total_followers' => 0];
        }
    }

    public static function getSpeciesScan($pdo, string $playerUUID, string $scanType, $userNameFormat = "hud")
    {
        $response = self::getPlayerData($pdo, $playerUUID); // Assuming this is your JSON response

        // Decode the JSON string into an associative array
        $data = json_decode($response, true);
        $extraData = $data['extra'];

        $currentHealth = $extraData["player_current_health"];
        $maxHealth = $extraData["max_health"];

        $speciesType = $extraData['species_type'];
        $legacyName = $extraData['legacy_name'];
        $status = $extraData["player_current_status"];
        $ageGroup = $extraData["age_group_name"];



        $bloodlineId = BloodlineHelperController::getLoyalBloodlineIdByPlayerUUID($pdo, $playerUUID);

        if ($bloodlineId)
        {
            $bloodlineRole = BloodlineHelperController::getPlayerBloodlineRoleName($pdo, $playerUUID);
            $clanBloodlineName = ClanHelperController::getPlayerClanBloodlineName($pdo, $playerUUID);
            $bloodlineLeaderId = BloodlineHelperController::getBloodlineLeaderId($pdo, $bloodlineId);

            $BloodlineLeaderRoleName = BloodlineHelperController::getPlayerBloodlineRoleNameByPlayerId($pdo, $bloodlineLeaderId);
            $BloodlineLeaderName = PlayerDataController::getPlayerLegacyNameByPlayerID($pdo, $bloodlineLeaderId);
        }




        // Scan is for hud or for discord scan, correct name format.
        if ($userNameFormat == "hud")
        {
            $userNameLine = "║ Username: " . "secondlife:///app/agent/" . $playerUUID . "/about" . "\n";
        } else
        {
            $userNameLine = "║ Username: " . $legacyName . "\n";
        }

        $activityScore = TableController::getPlayerFieldValue($pdo, $playerUUID, "activity_score");

        $age = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_age");
        $generation = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_generation");

        $bloodline = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'bloodline_id', 'bloodlines');
        $data = json_decode($bloodline, true);
        $bloodline = $data['bloodline_name'];

        $house = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'house_id', 'houses');
        $data = json_decode($house, true);
        $house = $data['house_name'];
        $clanId = $data['clan_id'];

        $clanName = tableController::getFieldValue($pdo, "clans", "clan_id", $clanId, "clan_name");
        $clanRoleID = TableController::getPlayerFieldValue($pdo, $playerUUID, "clan_role_id");
        $clanRole = tableController::getFieldValue($pdo, "player_role_clan", "clan_role_id", $clanRoleID, "clan_role_name");

        $houseRole = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'house_role_id', 'player_role_house');
        $jsonData = json_decode($houseRole, true);

        $title = PlayerDataController::getPlayerTitle($pdo, $playerUUID);
        if ($speciesType == "witch")
        {
            $speciesBloodString = "║ Energy: " . $currentHealth . " of " . $maxHealth . "\n";
        } else
        {
            $speciesBloodString = "║ Blood: " . $currentHealth . " of " . $maxHealth . "\n";
        }

        if ($jsonData !== null)
        {
            if (isset($jsonData['house_role_name']))
            {
                $houseRole = $jsonData['house_role_name'];
            } else
            {
                $houseRole = "";
            }
        } else
        {
            echo "Error decoding JSON data.";
        }

        if (empty($clanBloodlineName))
        {
            $bloodlinePublicLine = "";
        } else
        {
            $bloodlinePublicLine = "║ Bloodline: " . $clanBloodlineName . "\n";
        }

        if (empty($bloodline))
        {
            $bloodLineLine = "";
        } else
        {
            $bloodLineLine = "║ Bloodline DNA: " . $bloodline . "\n";
        }

        if (empty($clanBloodlineName))
        {
            if ($clanId)
            {
                $clanBloodlineNameLine = "║ Clan Allegiance: Renegade (No Arch) \n";
            } else
            {
                $clanBloodlineNameLine = "";
            }

        } else
        {
            $clanBloodlineNameLine = "║ Clan Allegiance: " . $clanBloodlineName . "\n║ Loyal to " . $BloodlineLeaderRoleName . ": " . $BloodlineLeaderName . "\n";
        }

        if (empty($bloodlineRole))
        {
            $bloodLineRoleLine = "";
        } else
        {
            $bloodLineRoleLine = "║ Bloodline Role: " . $bloodlineRole . "\n";
        }


        if (empty($clanName))
        {
            $clanLine = "";
        } else
        {
            $clanLine = "║ Clan: " . $clanName . "\n";
        }

        if (empty($clanRole))
        {
            $clanRoleLine = "";
        } else
        {
            $clanRoleLine = "║ Clan Role: " . $clanRole . "\n";
        }

        if (empty($house))
        {
            $houseLine = "║ No Affiliation: Outlaw" . "\n";
        } elseif ($speciesType == "witch")
        {
            $houseLine = "║ Coven: " . $house . "\n";
        } elseif ($speciesType == "lycan")
        {
            $houseLine = "║ Pack: " . $house . "\n";
        } else
        {
            $houseLine = "║ House: " . $house . "\n";
        }

        if (empty($houseRole))
        {
            $houseRoleLine = "";
        } else
        {
            if ($speciesType == "witch")
            {
                //$houseLine = "║ Circle: " . $house . "\n";
                $houseRoleLine = "║ Coven Role: " . $houseRole . "\n";
            } else if ($speciesType == "lycan")
            {
                $houseRoleLine = "║ Pack Role: " . $houseRole . "\n";
            } else
            {
                $houseRoleLine = "║ House Role: " . $houseRole . "\n";
            }
        }

        if ($title)
        {
            $titleLine = "║ Title: " . $title . "\n";
        } else
        {
            $titleLine = "";
        }

        if (empty($bloodLineLine . $clanLine . $clanRoleLine . $houseLine . $houseRoleLine))
        {
            $bloodLineLine = "║ non-existent.\n";
        }

        if ($currentHealth <= 0)
        {
            // Player is dead, retrieve the last death details
            $playerId = PlayerDataController::getPlayerIdByUuid($pdo, $playerUUID);
            $deathDetails = PlayerDataController::getLastDeathDetails($pdo, $playerId);

            if ($deathDetails)
            {
                $causeOfDeath = $deathDetails['cause_of_death'];
                $deathMessage = "";
                $dateOfDeath = $deathDetails['death_date'];
                switch ($causeOfDeath)
                {
                    case 'hunger':
                        $deathMessage = "Hunger";
                        break;
                    case 'killed_by_player':
                        $killerId = $deathDetails['killer_player_id'];
                        $killerUuid = PlayerDataController::getPlayerUuidById($pdo, $killerId);
                        $deathMessage = "Killed by " . "secondlife:///app/agent/" . $killerUuid . "/about";
                        break;
                    case 'killed_by_darkness':
                        $deathMessage = "The Darkness";
                        break;
                    case 'killed_by_admin':
                        $statusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
                        $statusDetails = PlayerDataController::getPlayerStatusById($pdo, $statusId);
                        if ($statusDetails['status'] === 200)
                        {
                            $playerstatuskeyword = $statusDetails['player_status_keyword'];
                        }
                        if ($playerstatuskeyword === "banned")
                        {
                            $bannedMessageLine = "Status: Banned";
                        }

                        $deathMessage = "Killed by admin";
                        break;
                }

            }
        }

        if ($scanType == "private")
        {
            $bornDate = TableController::getPlayerFieldValue($pdo, $playerUUID, "join_date");
            $bornDate = self::convertDateTimeFormat($bornDate);

            $sire = TableController::getPlayerFieldValue($pdo, $playerUUID, "sire_id");

            if (empty($sire))
            {
                $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
                $sireLegacyName = self::getCurrentSireLegacyNameFromResireRequest($pdo, $playerId);
                if ($sireLegacyName !== false)
                {
                    $daysRemaining = ResireController::getDaysRemainingForResireRequest($pdo, $playerUUID);
                    $sire = $sireLegacyName . " (Pending Re-Sire Request, Days Remaining: $daysRemaining)";
                } else
                {
                    $sire = "None";
                }
            } else
            {
                $sire = self::getSire($pdo, $sire);
            }

            $subsistence = $extraData["subsistence"];
            $essence = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_essence");
            $playerId = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_following_id");
            $personIAmFollowing = self::getLegacyNameFromID($pdo, $playerId);
            if (empty($personIAmFollowing))
            {
                $followerLine = "";
            } else
            {
                $followerLine = "║ Follower of: " . $personIAmFollowing . "\n";
            }

            $followerCounts = self::getFollowerCounts($pdo, $playerUUID);
            $aliveFollowerCount = $followerCounts['alive_followers'];
            $totalFollowerCount = $followerCounts['total_followers'];

            $deathLine = "";
            if ($currentHealth <= 0)
            {
                $status = $status . " (" . $dateOfDeath . ")";
                $deathLine = "║ Cause of Death: " . $deathMessage . "\n";
            }

            $selfScan = "\n╔═★⋅•⋅∙∘☽ The Realm of Darkness ☾∘∙⋅•⋅★\n" .
                $userNameLine .
                "║ Born: " . $bornDate . "\n" .
                "║ Sire: " . $sire . "\n" .
                "║ Generation: " . $generation . "\n" .
                "║ Species: " . ucfirst($speciesType) . "\n" .
                "║ Status: " . $status . "\n" .
                $deathLine .
                "╠═ ★────────✧˖°˖☆ Age ☆˖°˖✧────────★\n" .
                "║ Age: " . $age . "\n" .
                "║ Age Group: " . $ageGroup . "\n" .
                "║ Subsistence: " . $subsistence . "\n" .
                $speciesBloodString .
                // "║ Blood: " . $currentHealth . " of " . $maxHealth . "\n" .
                "║ Essence: " . $essence . "\n" .
                "║ Dark Rating: " . $activityScore . "%\n" .
                "╠═ ★─────°✧˖°˖☆ Bloodline ☆˖°˖✧°─────★\n" .
                $followerLine .
                $bloodLineLine .
                $clanBloodlineNameLine .
                $bloodLineRoleLine .
                $clanLine .
                $clanRoleLine .
                $houseLine .
                $houseRoleLine .
                $titleLine .
                "║ Followers: " . $aliveFollowerCount . " of " . $totalFollowerCount . "\n" .
                "╚═•⋅⋅•⋅⋅•⋅⊰⋅⋅•⋅⋅•∙⋅∘☽★☾∘⋅∙•⋅⋅•⋅⋅⊰⋅•⋅⋅•⋅⋅•⋅⋅•";
        } elseif ($scanType == "public")
        {

            if ($currentHealth <= 0)
            {
                $selfScan =
                    "\n╔═★⋅•⋅∙∘☽ The Realm of Darkness ☾∘∙⋅•⋅★\n" .
                    "║ Username: secondlife:///app/agent/" . $playerUUID . "/about\n" .
                    "║ Date of Death: " . $dateOfDeath . "\n" .
                    "║ Cause of Death: " . $deathMessage . "\n" .
                    (!empty($bannedMessageLine) ? "║ " . $bannedMessageLine . "\n" : "") .
                    "╚═•⋅⋅•⋅⋅•⋅⊰⋅⋅•⋅⋅•∙⋅∘☽★☾∘⋅∙•⋅⋅•⋅⋅⊰⋅•⋅⋅•⋅⋅•⋅⋅•";
            } else
            {
                $selfScan =
                    "\n╔═★⋅•⋅∙∘☽ The Realm of Darkness ☾∘∙⋅•⋅★\n" .
                    $userNameLine .
                    "║ Generation: " . $generation . "\n" .
                    "║ Species: " . ucfirst($speciesType) . "\n" .
                    "║ Status: " . $status . "\n" .
                    "║ Dark Rating: " . $activityScore . "%\n" .
                    "╠═ ★────────✧˖°˖☆ Age ☆˖°˖✧────────★\n" .
                    "║ Age Group: " . $ageGroup . "\n" .
                    "║ Blood: " . $currentHealth . " of " . $maxHealth . "\n" .
                    "╠═ ★─────°✧˖°˖☆ Bloodline ☆˖°˖✧°─────★\n" .
                    $bloodlinePublicLine .
                    $bloodLineRoleLine .
                    $clanLine .
                    $clanRoleLine .
                    $titleLine .
                    $houseLine .
                    $houseRoleLine .
                    "╚═•⋅⋅•⋅⋅•⋅⊰⋅⋅•⋅⋅•∙⋅∘☽★☾∘⋅∙•⋅⋅•⋅⋅⊰⋅•⋅⋅•⋅⋅•⋅⋅•";
            }
        }

        return new JsonResponse(200, "self_scan_result", ["self_scan" => $selfScan]);
    }

    public static function getCurrentSireLegacyNameFromResireRequest($pdo, $player_id)
    {
        try
        {
            // SQL query to retrieve the legacy_name of the current_sire from the resire_request
            $sql = "SELECT p.legacy_name
                FROM resire_requests rr
                JOIN players p ON rr.current_sire_id = p.player_id
                WHERE rr.player_id = :player_id";

            // Prepare the statement
            $stmt = $pdo->prepare($sql);

            // Bind the player_id parameter
            $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);

            // Execute the query
            $stmt->execute();

            // Fetch the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && !empty($result['legacy_name']))
            {
                return $result['legacy_name'];
            } else
            {
                return false;
            }
        }
        catch (PDOException $e)
        {
            //error_log("Error in getCurrentSireLegacyNameFromResireRequest: " . $e->getMessage());
            return false;
        }
    }

    private static function getLegacyNameFromID($pdo, $playerId)
    {

        try
        {
            $stmt = $pdo->prepare("SELECT legacy_name FROM Players WHERE player_id = :playerId");
            $stmt->bindParam(':playerId', $playerId, PDO::PARAM_INT);
            $stmt->execute();

            // Fetch the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Close the connection
            $conn = null;

            // Return the legacy_name or null if player not found
            return $result ? $result['legacy_name'] : null;
        }
        catch (PDOException $e)
        {
            // Handle connection errors
            return null;
        }
    }

    private static function convertDateTimeFormat($dateTimeString)
    {
        // Convert the input date-time string to a Unix timestamp
        $timestamp = strtotime($dateTimeString);

        // Check if the conversion was successful
        if ($timestamp === false)
        {
            return "Invalid date-time format";
        }

        // Convert the timestamp to the desired format
        $formattedDateTime = date("F j, Y - g:i A", $timestamp);

        return $formattedDateTime;
    }

    private static function updateLastLogin($pdo, $playerUUID)
    {
        // Get the current date
        $currentDate = date('Y-m-d');

        // Check if the last_login is already set for today
        $stmt = $pdo->prepare("SELECT last_login FROM players WHERE player_uuid = :playerUUID");
        $stmt->bindParam(':playerUUID', $playerUUID);
        $stmt->execute();
        $lastLoginDate = $stmt->fetchColumn();

        if ($lastLoginDate !== false && $lastLoginDate !== null)
        {
            $lastLoginDate = date('Y-m-d', strtotime($lastLoginDate));

            // echo "PlayerController: blah";
            // If the last_login is already set for today, no need to update
            if ($lastLoginDate === $currentDate)
            {
                return true;
            }
        }

        // Update the last_login to the current date and time
        $stmt = $pdo->prepare("UPDATE players SET last_login = NOW() WHERE player_uuid = :playerUUID");
        $stmt->bindParam(':playerUUID', $playerUUID);
        $result = $stmt->execute();

        return $result;
    }

    public static function getPlayerData($pdo, string $playerUUID)
    {

        $playerExists = self::checkPlayerExists($pdo, $playerUUID);

        // Check if the specified parcel in the region is a safe zone
        $slHeaders = new SecondLifeHeaders();

        if (!$playerExists)
        {
            // Player doesn't exist, return an appropriate response
            return new JsonResponse(404, "Player not found");
        }

        $requestingUUID = $slHeaders->getOwnerKey();
        if ($requestingUUID == $playerUUID)
        {
            // Log the activity without waiting for a response
            $activityName = "log_on"; // Replace with the actual activity name
            ActivityController::logActivity($pdo, $playerUUID, $activityName);
        }

        // Call the updateLastLogin function
        $success = self::updateLastLogin($pdo, $playerUUID);
        if (!$success)
        {
            error_log("Failed to update last login for player: " . $playerUUID);
        }

        $versionNumber = self::getVersionNumber($pdo);
        $statusData = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'player_status_id', 'player_status');
        $speciesData = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'species_id', 'species');

        $ageGroup = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'player_age_group_id', 'player_age_group');
        $health = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_current_health");
        $health = number_format((float) $health, 3, '.', '');

        $legacyName = TableController::getPlayerFieldValue($pdo, $playerUUID, "legacy_name");
        // Update legacy name if the player has changed it.
        $currentLegacyName = $slHeaders->getOwnerName();
        if ($legacyName != $currentLegacyName && $requestingUUID == $playerUUID)
        {
            TableController::updateData($pdo, "players", $playerUUID, ["legacy_name" => $currentLegacyName]);
            // Name was uppdated
            $legacyName = $currentLegacyName;
        }

        $playerHudSaveLocation = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_hud_save_location");
        if ($playerHudSaveLocation == "")
        {
            $playerHudSaveLocation = "<0.00000, 0.96994, -0.41683>";
        }

        $playerBloodlineId = TableController::getPlayerFieldValue($pdo, $playerUUID, "bloodline_id");

        $playerEssence = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_essence");

        //echo $slHeaders->getRegionName();
        $SafeZoneData = self::checkRegionSafeZone($pdo, $slHeaders->getRegionName(), $slHeaders->getParcelID());
        // Now SafeZoneData contains all the safe zone info (safe_zone, parcel_id, min_height, max_height)
        //var_dump($isSafeZone);

        $statusData = json_decode($statusData, true);
        $speciesData = json_decode($speciesData, true);
        $ageGroup = json_decode($ageGroup, true);

        $mergeArrays = is_array($statusData)
            && is_array($speciesData)
            && is_array($ageGroup)
            && is_array($health)
            && is_array($legacyName)
            && is_array($versionNumber)
            ? [] : array_merge($speciesData,
                $statusData,
                $ageGroup,
                ["player_current_health" => $health],
                ["legacy_name" => $legacyName],
                $versionNumber,
                ["player_hud_save_location" => $playerHudSaveLocation],
                ["bloodline_id" => $playerBloodlineId],
                ["player_essence" => $playerEssence],
                $SafeZoneData);
        return new JsonResponse(200, "player_data", $mergeArrays);
    }

    // public static function attack($pdo, string $theirUUID, float $theirDamage, string $myUUID)
    // {
    //     try
    //     {
    //         // Fetch species type and ensure existence in the database
    //         $theirSpecies = self::getTheirSpeciesType($pdo, $theirUUID);

    //         if ($theirSpecies === false)
    //         {
    //             self::ensureHumanExists($pdo, $theirUUID);
    //             // Update human blood level using UUID
    //             $theirCurrentHealth = self::updateHumanBloodLevel($pdo, $theirUUID);
    //         }

    //         $isPlayer = !empty($theirSpecies);
    //         $theirCurrentHealth = $isPlayer ? self::getPlayerCurrentHealth($pdo, $theirUUID) : self::getHumanBloodLevel($pdo, $theirUUID);
    //         if ($theirCurrentHealth === 0.0)
    //         {
    //             return new JsonResponse(422, "Dead", ["their_key" => $theirUUID, 'their_species' => $isPlayer ? $theirSpecies : "human"]);
    //         }

    //         // Capture the victim's blood level before the attack
    //         $victimBloodBefore = (float) $theirCurrentHealth;

    //         // Apply damage and update health
    //         $theirCurrentHealth = $isPlayer ? self::updateSpeciesHealth($pdo, $theirUUID, -$theirDamage) : self::updateTheirHumanBlood($pdo, $theirUUID, $theirDamage);
    //         $myNewHealth = self::updateSpeciesHealth($pdo, $myUUID, $theirDamage * 0.90); // Assuming positive damage heals the attacker
    //         $myMaxBlood = self::getPlayerMaxBlood($pdo, $myUUID);
    //         $theirMaxBlood = self::getPlayerMaxBlood($pdo, $theirUUID);

    //         // Cap the attacker's health at their maximum blood level
    //         if ($myNewHealth > $myMaxBlood)
    //         {
    //             $myNewHealth = $myMaxBlood;
    //         }

    //         $regionName = SecondLifeHeadersStatic::getRegionName();
    //         $myID = PlayerDataController::getPlayerIdByUUID($pdo, $myUUID);
    //         $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $theirUUID);

    //         // Log the attack details // logs player vs players attacks
    //         if ($theirSpecies != "")
    //         {

    //             // Log the activity without waiting for a response
    //             $activityName = "feed_on_player"; // Replace with the actual activity name
    //             ActivityController::logActivity($pdo, $myUUID, $activityName);

    //             $attackOutcome = 'damage';
    //             $attackLogResult = DeathLogController::addAttackLogEntry(
    //                 $pdo,
    //                 $myID,
    //                 $theirID,
    //                 $theirDamage,
    //                 $victimBloodBefore,
    //                 (float) $theirCurrentHealth,
    //                 $attackOutcome,
    //                 $regionName,
    //                 "Additional attack details"
    //             );

    //             // Check if the attack log was not successful
    //             if ($attackLogResult->getStatus() != 200)
    //             {
    //                 // Log the failure message
    //                 error_log("Failed to log attack: " . $attackLogResult->getMessage());
    //             }
    //         }

    //         if ($theirSpecies == "")
    //         {
    //             $theirSpecies = "human";

    //             // Log the activity without waiting for a response
    //             $activityName = "feed_on_human"; // Replace with the actual activity name
    //             ActivityController::logActivity($pdo, $myUUID, $activityName);
    //         }

    //         // Update player status to 'dead' if necessary and log death
    //         if ($theirCurrentHealth <= 0.0)
    //         {
    //             if ($theirSpecies != "human")
    //             {

    //                 $statusResponse = self::updatePlayerStatus($pdo, $theirUUID, "dead");
    //                 if (!$statusResponse)
    //                 {
    //                     error_log("Failed to update player status for $theirUUID to 'dead'");
    //                     //return new JsonResponse(500, "Failed to update player status");
    //                 }

    //                 $updatePlayerEssence = PlayerDataController::updatePlayerEssence($pdo, $theirUUID, 0.0);
    //                 if ($updatePlayerEssence == "No update made. Player not found or essence unchanged.")
    //                 {
    //                     error_log("Failed to update player essence on death of $theirUUID");
    //                 }

    //                 // Log the death
    //                 $deathLogResult = DeathLogController::addDeathLogEntry($pdo, $theirID, $myID, date('Y-m-d H:i:s'), $regionName, 'killed_by_player', 'Player was killed in an attack.');
    //                 $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
    //                 if ($decodedDeathLogResult['status'] != 200)
    //                 {
    //                     error_log("Error updating death log: " . $decodedDeathLogResult['message']);
    //                 }
    //             } else
    //             {
    //                 // log the death of human here as well.
    //                 self::recordHumanKiller($pdo, $theirUUID, $myID);

    //             }

    //             return new JsonResponse(200, "killed", [
    //                 'their_species' => $isPlayer ? $theirSpecies : "human",
    //                 'their_current_health' => $theirCurrentHealth,
    //                 'my_current_health' => $myNewHealth,
    //                 'their_max_health' => $theirMaxBlood,
    //             ]);
    //         }

    //         // Return response for a successful attack
    //         return new JsonResponse(200, "damage_done", [
    //             'their_species' => $isPlayer ? $theirSpecies : "human",
    //             'their_current_health' => $theirCurrentHealth,
    //             'my_current_health' => $myNewHealth,
    //             'their_max_health' => $theirMaxBlood,
    //         ]);

    //     }
    //     catch (PDOException $e)
    //     {
    //         return new JsonResponse(500, "Error: " . $e->getMessage());
    //     }
    // }
    /**
     * Checks if a given UUID (non-player) is flagged as a ghoul in the `humans` table.
     */
    private static function isGhoul(PDO $pdo, string $uuid): bool
    {
        $stmt = $pdo->prepare("
        SELECT is_ghoul
        FROM humans
        WHERE human_uuid = :uuid
        LIMIT 1
    ");
        $stmt->execute(['uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no row found or `is_ghoul` is 0, return false.
        return ($row && $row['is_ghoul'] == 1);
    }

    /**
     * Fetches the ghoul's reward from the `humans` table (column `ghoul_reward`).
     * Returns 0 if no row found or the column isn't set.
     */
    private static function getGhoulReward(PDO $pdo, string $uuid): float
    {
        $stmt = $pdo->prepare("
        SELECT ghoul_reward
        FROM humans
        WHERE human_uuid = :uuid
        LIMIT 1
    ");
        $stmt->execute(['uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row)
        {
            return 0.0; // No record found, or no reward
        }
        return (float) $row['ghoul_reward'];
    }

    // public static function attack($pdo, string $theirUUID, float $theirDamage, string $myUUID)
    // {
    //     try
    //     {
    //         // 1. Determine if target is a player (vampire, lycan, witch, etc.)
    //         //    or false if not found (meaning they might be a human or ghoul).
    //         $theirSpecies = self::getTheirSpeciesType($pdo, $theirUUID);
    //         $isPlayer = !empty($theirSpecies);

    //         // 2. If not a player, ensure they're in the `humans` table (could be normal human or ghoul)
    //         if ($theirSpecies === false)
    //         {
    //             self::ensureHumanExists($pdo, $theirUUID);
    //             self::updateHumanBloodLevel($pdo, $theirUUID);
    //         }

    //         // 3. Get victim's current health or blood
    //         $theirCurrentHealth = $isPlayer
    //             ? self::getPlayerCurrentHealth($pdo, $theirUUID)
    //             : self::getHumanBloodLevel($pdo, $theirUUID);

    //         // If already 0, no need to attack
    //         if ($theirCurrentHealth === 0.0)
    //         {
    //             return new JsonResponse(422, "Dead", [
    //                 "their_key" => $theirUUID,
    //                 'their_species' => $isPlayer ? $theirSpecies : "human"
    //             ]);
    //         }

    //         // 4. Remember health before the attack (for logging, etc.)
    //         $victimBloodBefore = (float) $theirCurrentHealth;

    //         // 5. Apply damage
    //         if ($isPlayer)
    //         {
    //             // If it's a player (vamp, lycan, etc.), update their species health
    //             $theirCurrentHealth = self::updateSpeciesHealth($pdo, $theirUUID, -$theirDamage);
    //         } else
    //         {
    //             // If it's a human/ghoul, reduce their blood level
    //             $theirCurrentHealth = self::updateTheirHumanBlood($pdo, $theirUUID, $theirDamage);
    //         }

    //         // 6. Attacker gains some health from the attack (unchanged from your code)
    //         $myNewHealth = self::updateSpeciesHealth($pdo, $myUUID, $theirDamage * 0.90);
    //         $myMaxBlood = self::getPlayerMaxBlood($pdo, $myUUID);
    //         if ($myNewHealth > $myMaxBlood)
    //         {
    //             $myNewHealth = $myMaxBlood;
    //         }

    //         // 7. Attack logging
    //         $regionName = SecondLifeHeadersStatic::getRegionName();
    //         $myID = PlayerDataController::getPlayerIdByUUID($pdo, $myUUID);
    //         $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $theirUUID);

    //         if ($isPlayer)
    //         {
    //             // Player vs. Player
    //             ActivityController::logActivity($pdo, $myUUID, "feed_on_player");
    //             $attackLogResult = DeathLogController::addAttackLogEntry(
    //                 $pdo,
    //                 $myID,
    //                 $theirID,
    //                 $theirDamage,
    //                 $victimBloodBefore,
    //                 (float) $theirCurrentHealth,
    //                 "damage",
    //                 $regionName,
    //                 "Attacker dealt damage"
    //             );
    //             if ($attackLogResult->getStatus() != 200)
    //             {
    //                 error_log("Failed to log attack: " . $attackLogResult->getMessage());
    //             }
    //         } else
    //         {
    //             // It's a "human" (including potential ghoul)
    //             $theirSpecies = "human";
    //             ActivityController::logActivity($pdo, $myUUID, "feed_on_human");
    //         }

    //         // 8. Check if the victim died
    //         if ($theirCurrentHealth <= 0.0)
    //         {
    //             // 8a. If a player victim (non-human species), mark them as dead
    //             if ($theirSpecies != "human")
    //             {
    //                 $statusResponse = self::updatePlayerStatus($pdo, $theirUUID, "dead");
    //                 if (!$statusResponse)
    //                 {
    //                     error_log("Failed to update player status for $theirUUID to 'dead'");
    //                 }

    //                 // Optionally reset their essence to zero
    //                 $essenceResponse = PlayerDataController::updatePlayerEssence($pdo, $theirUUID, 0.0);
    //                 if ($essenceResponse === "No update made. Player not found or essence unchanged.")
    //                 {
    //                     error_log("Failed to update player essence on death of $theirUUID");
    //                 }

    //                 // Log the death
    //                 $deathLogResult = DeathLogController::addDeathLogEntry(
    //                     $pdo,
    //                     $theirID,
    //                     $myID,
    //                     date('Y-m-d H:i:s'),
    //                     $regionName,
    //                     'killed_by_player',
    //                     'Player was killed in an attack.'
    //                 );
    //                 $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
    //                 if ($decodedDeathLogResult['status'] != 200)
    //                 {
    //                     error_log("Error updating death log: " . $decodedDeathLogResult['message']);
    //                 }
    //             }
    //             // 8b. If it's "human", see if they're flagged as ghoul
    //             else
    //             {
    //                 // Record who killed the "human"
    //                 self::recordHumanKiller($pdo, $theirUUID, $myID);

    //                 // If they're actually a ghoul, award essence
    //                 if (self::isGhoul($pdo, $theirUUID))
    //                 {
    //                     $ghoulReward = self::getGhoulReward($pdo, $theirUUID);
    //                     $myCurrentEssence = PlayerDataController::getPlayerEssence($pdo, $myUUID);
    //                     $newEssence = $myCurrentEssence + $ghoulReward;

    //                     // Use your existing helper
    //                     $updateResult = PlayerDataController::updatePlayerEssence($pdo, $myUUID, $newEssence);

    //                     if ($updateResult !== "Player essence updated successfully.")
    //                     {
    //                         error_log("Ghoul kill reward not updated: $updateResult");
    //                     } else
    //                     {
    //                         // Log or confirm it was successful
    //                         ActivityController::logActivity($pdo, $myUUID, "killed_ghoul");
    //                         error_log("Attacker {$myUUID} gained {$ghoulReward} essence for killing a Ghoul: {$theirUUID}");

    //                         // **NEW**: Notify Discord via your helper
    //                         $slHeaders = new SecondLifeHeaders();
    //                         $regionName = $slHeaders->getRegionName();  // however you get region name
    //                         $webhookUrl = "https://discord.com/api/webhooks/1273656407291465808/gAj11MkrKXf2FNzadrgAZnTDvL5NXZSVvLk2BiejXblOJGHxU7kpgQS-p9Qy3Zws6Obf";

    //                         self::notifyGhoulKillToDiscord($pdo, $myUUID, $theirUUID, $ghoulReward, $regionName, $webhookUrl);
    //                     }
    //                 }
    //             }

    //             // Return the "killed" response
    //             return new JsonResponse(200, "killed", [
    //                 'their_species' => $theirSpecies,
    //                 'their_current_health' => $theirCurrentHealth,
    //                 'my_current_health' => $myNewHealth,
    //                 'their_max_health' => self::getPlayerMaxBlood($pdo, $theirUUID)
    //             ]);
    //         }

    //         // 9. If victim didn't die, just return "damage_done"
    //         return new JsonResponse(200, "damage_done", [
    //             'their_species' => $theirSpecies,
    //             'their_current_health' => $theirCurrentHealth,
    //             'my_current_health' => $myNewHealth,
    //             'their_max_health' => self::getPlayerMaxBlood($pdo, $theirUUID),
    //         ]);
    //     }
    //     catch (PDOException $e)
    //     {
    //         return new JsonResponse(500, "Error: " . $e->getMessage());
    //     }
    // }


    public static function attack($pdo, string $theirUUID, float $theirDamage, string $myUUID)
    {
        try
        {
            // 1. Determine if target is a player or false (human/ghoul)
            $theirSpecies = self::getTheirSpeciesType($pdo, $theirUUID);
            $isPlayer = !empty($theirSpecies);

            // 2. If not a player, ensure humans table row exists
            if ($theirSpecies === false)
            {
                self::ensureHumanExists($pdo, $theirUUID);
                self::updateHumanBloodLevel($pdo, $theirUUID);
            }

            // 3. Get victim's current health/blood
            $theirCurrentHealth = $isPlayer
                ? self::getPlayerCurrentHealth($pdo, $theirUUID)
                : self::getHumanBloodLevel($pdo, $theirUUID);

            // If already 0, no need to attack
            if ($theirCurrentHealth === 0.0)
            {
                return new JsonResponse(422, "Dead", [
                    "their_key" => $theirUUID,
                    'their_species' => $isPlayer ? $theirSpecies : "human"
                ]);
            }

            // 4. Remember health before the attack (used in logging)
            $victimBloodBefore = (float) $theirCurrentHealth;

            // 5. Apply damage (atomic update)
            if ($isPlayer)
            {
                $theirCurrentHealth = self::updateSpeciesHealth($pdo, $theirUUID, -$theirDamage);
            } else
            {
                $theirCurrentHealth = self::updateTheirHumanBlood($pdo, $theirUUID, $theirDamage);
            }

            // 6. Attacker heals from damage
            $myNewHealth = self::updateSpeciesHealth($pdo, $myUUID, $theirDamage * 0.90);
            $myMaxBlood = self::getPlayerMaxBlood($pdo, $myUUID);
            if ($myNewHealth > $myMaxBlood)
            {
                $myNewHealth = $myMaxBlood;
            }

            // 7. Logging
            $regionName = SecondLifeHeadersStatic::getRegionName();
            $myID = PlayerDataController::getPlayerIdByUUID($pdo, $myUUID);
            $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $theirUUID);

            if ($isPlayer)
            {
                ActivityController::logActivity($pdo, $myUUID, "feed_on_player");
                $attackLogResult = DeathLogController::addAttackLogEntry(
                    $pdo,
                    $myID,
                    $theirID,
                    $theirDamage,
                    $victimBloodBefore,
                    (float) $theirCurrentHealth,
                    "damage",
                    $regionName,
                    "Attacker dealt damage"
                );
                if ($attackLogResult->getStatus() != 200)
                {
                    error_log("Failed to log attack: " . $attackLogResult->getMessage());
                }
            } else
            {
                ActivityController::logActivity($pdo, $myUUID, "feed_on_human");
                $theirSpecies = "human";
            }

            // 8. If victim died
            if ($theirCurrentHealth <= 0.0)
            {
                if ($theirSpecies != "human")
                {
                    // Mark non-human as dead, log death, reset essence
                    if (!self::updatePlayerStatus($pdo, $theirUUID, "dead"))
                    {
                        error_log("Failed to update player status for $theirUUID to 'dead'");
                    }
                    $essenceResponse = PlayerDataController::updatePlayerEssence($pdo, $theirUUID, 0.0);
                    if ($essenceResponse === "No update made. Player not found or essence unchanged.")
                    {
                        error_log("Failed to update player essence on death of $theirUUID");
                    }
                    $deathLogResult = DeathLogController::addDeathLogEntry(
                        $pdo,
                        $theirID,
                        $myID,
                        date('Y-m-d H:i:s'),
                        $regionName,
                        'killed_by_player',
                        'Player was killed in an attack.'
                    );
                    $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
                    if ($decodedDeathLogResult['status'] != 200)
                    {
                        error_log("Error updating death log: " . $decodedDeathLogResult['message']);
                    }
                } else
                {
                    // Record who killed the "human"
                    self::recordHumanKiller($pdo, $theirUUID, $myID);

                    // If ghoul, award essence, log, notify Discord
                    if (self::isGhoul($pdo, $theirUUID))
                    {
                        $ghoulReward = self::getGhoulReward($pdo, $theirUUID);
                        $myCurrentEssence = PlayerDataController::getPlayerEssence($pdo, $myUUID);
                        $newEssence = $myCurrentEssence + $ghoulReward;

                        $updateResult = PlayerDataController::updatePlayerEssence($pdo, $myUUID, $newEssence);
                        if ($updateResult !== "Player essence updated successfully.")
                        {
                            error_log("Ghoul kill reward not updated: $updateResult");
                        } else
                        {
                            ActivityController::logActivity($pdo, $myUUID, "killed_ghoul");
                            error_log("Attacker {$myUUID} gained {$ghoulReward} essence for killing Ghoul: {$theirUUID}");

                            $slHeaders = new SecondLifeHeaders();
                            $regionName = $slHeaders->getRegionName();
                            $webhookUrl = "https://discord.com/api/webhooks/1273656407291465808/gAj11MkrKXf2FNzadrgAZnTDvL5NXZSVvLk2BiejXblOJGHxU7kpgQS-p9Qy3Zws6Obf";

                            self::notifyGhoulKillToDiscord($pdo, $myUUID, $theirUUID, $ghoulReward, $regionName, $webhookUrl);
                        }
                    }
                }

                return new JsonResponse(200, "killed", [
                    'their_species' => $theirSpecies,
                    'their_current_health' => $theirCurrentHealth,
                    'my_current_health' => $myNewHealth,
                    'their_max_health' => self::getPlayerMaxBlood($pdo, $theirUUID)
                ]);
            }

            // 9. Victim not dead
            return new JsonResponse(200, "damage_done", [
                'their_species' => $theirSpecies,
                'their_current_health' => $theirCurrentHealth,
                'my_current_health' => $myNewHealth,
                'their_max_health' => self::getPlayerMaxBlood($pdo, $theirUUID),
            ]);
        }
        catch (PDOException $e)
        {
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }


    /**
     * Atomic update for a player's health: 
     * - Damaging by passing negative $damage 
     * - Healing by passing positive $damage
     * 
     * Returns the updated health after the operation.
     */
    private static function updateSpeciesHealth($pdo, $uuid, $damage)
    {
        $query = "
        UPDATE players p
        JOIN player_age_group ag ON p.player_age_group_id = ag.player_age_group_id
        SET p.player_current_health = LEAST(
            GREATEST(p.player_current_health + :damage, 0),
            ag.max_health
        )
        WHERE p.player_uuid = :uuid
    ";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':damage', $damage, PDO::PARAM_STR);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->execute();

        return self::getPlayerCurrentHealth($pdo, $uuid);
    }

    /**
     * Atomic update for a human/ghoul's blood level:
     * - $bloodLost should be positive to reduce blood
     * - If you want to 'heal' a human, pass negative
     * 
     * Returns the updated blood level.
     */
    private static function updateTheirHumanBlood($pdo, $uuid, $bloodLost)
    {
        $query = "
        UPDATE humans
        SET blood_level = GREATEST(blood_level - :bloodLost, 0)
        WHERE human_uuid = :uuid
    ";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':bloodLost', $bloodLost, PDO::PARAM_STR);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->execute();

        return self::getHumanBloodLevel($pdo, $uuid);
    }

    /**
     * Return the player's current health from the `players` table.
     */
    private static function getPlayerCurrentHealth($pdo, $uuid)
    {
        $selectQuery = "SELECT player_current_health FROM players WHERE player_uuid = :uuid";
        $stmtSelect = $pdo->prepare($selectQuery);
        $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmtSelect->execute();

        return (float) $stmtSelect->fetchColumn();
    }

    /**
     * Return the human's blood level from the `humans` table.
     */
    public static function getHumanBloodLevel($pdo, $uuid, $default = -1)
    {
        $selectQuery = "SELECT blood_level FROM humans WHERE human_uuid = :uuid";
        $stmtSelect = $pdo->prepare($selectQuery);
        $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmtSelect->execute();

        $bloodLevel = $stmtSelect->fetchColumn();
        return ($bloodLevel !== false) ? (float) $bloodLevel : (float) $default;
    }

    /**
     * Return the player's maximum blood (max health).
     */
    private static function getPlayerMaxBlood($pdo, $uuid)
    {
        try
        {
            $query = "
            SELECT age_group.max_health
            FROM players
            INNER JOIN player_age_group AS age_group 
                ON players.player_age_group_id = age_group.player_age_group_id
            WHERE players.player_uuid = :uuid
            LIMIT 1
        ";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();

            $maxBloodLevel = $stmt->fetchColumn();
            return (float) $maxBloodLevel; // fallback
        }
        catch (PDOException $e)
        {
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    /**
     * Simple helper if you need to forcibly set player health 
     * (e.g., if healing goes above max).
     */
    private static function setPlayerHealth($pdo, $uuid, $newHealth)
    {
        $query = "
        UPDATE players
        SET player_current_health = :newHealth
        WHERE player_uuid = :uuid
    ";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
        $stmt->bindParam(':newHealth', $newHealth, PDO::PARAM_STR);
        $stmt->execute();
    }

    /////////////////////////////////////////////////
/////////////////////////////////////////////////













































    public static function notifyGhoulKillToDiscord(
        PDO $pdo,
        string $myUUID,        // The attacker's UUID
        string $ghoulUUID,     // The ghoul's UUID
        float $ghoulReward,   // Essence rewarded
        string $regionName,    // Current region
        string $webhookUrl     // Discord webhook URL
    ): void {
        // 1) Get the attacker's legacy name
        $attackerLegacyName = PlayerDataController::getPlayerLegacyName($pdo, $myUUID);
        if (empty($attackerLegacyName))
        {
            $attackerLegacyName = "Unknown Attacker";
        }

        // 2) Build your embed parameters
        $embedParams = [
            'title' => "Ghoul Slain!",
            'description' => "**{$attackerLegacyName}** has slain ghoul **{$ghoulUUID}** " .
                "in region **{$regionName}**.\n" .
                "Reward: **{$ghoulReward} essence**",
            'color' => 0xAD0B0B,       // For example, a dark red
            'timestamp' => date('c'),      // ISO8601 timestamp
            'footer' => [
                'text' => "Realm of Darkness",
            ],
            'author' => [
                'name' => "Dark Oracle",     // Customize as you like
            ],
        ];

        // 3) Create the embed using your CommunicationController
        $embed = CommunicationController::buildDiscordEmbed($embedParams);

        // 4) Build the final payload
        $payload = [
            'username' => "Dark Oracle", // Name that appears in Discord
            'embeds' => [$embed],
        ];

        // 5) Send the webhook
        $response = CommunicationController::sendDiscordWebhook($webhookUrl, $payload);

        // 6) Check the result and log if it fails
        if ($response->getStatus() !== 200)
        {
            error_log("Failed to send ghoul kill embed to Discord: " . $response->getMessage());
        }
    }








    // update killer_player_id in the humans table
    public static function recordHumanKiller($pdo, $humanUUID, $killerPlayerId)
    {
        try
        {
            // Prepare the SQL query to update killer_player_id in the humans table
            $query = $pdo->prepare("UPDATE humans SET killer_player_id = :killerPlayerId WHERE human_uuid = :humanUUID");
            $query->bindParam(':humanUUID', $humanUUID, PDO::PARAM_STR);
            $query->bindParam(':killerPlayerId', $killerPlayerId, PDO::PARAM_INT);
            $query->execute();

            // Return true if any rows were affected, false otherwise
            return $query->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return false; // Return false in case of an error
        }
    }

    // used to update human blood level
    public static function updateHumanBloodLevel($pdo, $humanUUID)
    {
        // Only relevant for this function:
        $HUMAN_MAX_BLOOD_LEVEL = 5.0;
        $GHOUL_MAX_BLOOD_LEVEL = 10.0;
        $FULL_RECOVERY_DAYS = 21; // 3 weeks

        // 1. Fetch row from `humans`
        $stmt = $pdo->prepare("SELECT last_feed_on, blood_level, is_ghoul FROM humans WHERE human_uuid = :humanUUID");
        $stmt->bindParam(':humanUUID', $humanUUID, PDO::PARAM_STR);
        $stmt->execute();
        $human = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$human)
        {
            return null; // Row not found
        }

        $lastFeedOn = $human['last_feed_on'];
        $currentBloodLevel = (float) $human['blood_level'];
        $isGhoul = !empty($human['is_ghoul']) && $human['is_ghoul'] == 1;

        // 2. Determine max based on ghoul or human
        $maxBloodLevel = $isGhoul ? $GHOUL_MAX_BLOOD_LEVEL : $HUMAN_MAX_BLOOD_LEVEL;

        // 3. Daily recovery rate based on max
        $dailyRecoveryRate = $maxBloodLevel / $FULL_RECOVERY_DAYS;

        // 4. If alive (blood > 0) and has last_feed_on, update
        if ($currentBloodLevel > 0 && $lastFeedOn)
        {
            $timeElapsed = time() - strtotime($lastFeedOn);
            $daysElapsed = $timeElapsed / (24 * 60 * 60); // Convert to days
            $bloodRecovered = $dailyRecoveryRate * $daysElapsed;

            $newBloodLevel = min($currentBloodLevel + $bloodRecovered, $maxBloodLevel);

            // Update DB
            $updateStmt = $pdo->prepare("UPDATE humans
                                            SET blood_level = :newBloodLevel, last_feed_on = NOW()
                                            WHERE human_uuid = :humanUUID");
            $updateStmt->bindParam(':newBloodLevel', $newBloodLevel, PDO::PARAM_STR);
            $updateStmt->bindParam(':humanUUID', $humanUUID, PDO::PARAM_STR);
            $updateStmt->execute();

            return $newBloodLevel;
        }

        // If no update, return current level or null
        return $currentBloodLevel;
    }

    public static function updatePlayerStatus($pdo, $uuid, $statusKeyword, $useTransaction = true)
    {
        try
        {
            if ($useTransaction)
            {
                // Begin transaction only if $useTransaction is true
                $pdo->beginTransaction();
            }

            // Retrieve the species of the player
            $speciesQuery = "SELECT species_id FROM players WHERE player_uuid = :uuid";
            $stmtSpecies = $pdo->prepare($speciesQuery);
            $stmtSpecies->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtSpecies->execute();

            $speciesResult = $stmtSpecies->fetch(PDO::FETCH_ASSOC);
            if (!$speciesResult)
            {
                error_log("Player with UUID $uuid not found");
                if ($useTransaction)
                {
                    $pdo->rollBack();
                }
                return false;
            }
            $speciesId = $speciesResult['species_id'];

            // Debugging
            //error_log("Updating status for UUID: $uuid, Status Keyword: $statusKeyword, Species ID: $speciesId");

            // SQL query to update player status
            $updateStatusQuery = "UPDATE players
                                  SET player_status_id = (
                                      SELECT player_status_id
                                      FROM player_status
                                      WHERE player_status_keyword = :statusKeyword AND species_id = :speciesId
                                  )
                                  WHERE player_uuid = :uuid";

            $stmtUpdateStatus = $pdo->prepare($updateStatusQuery);
            $stmtUpdateStatus->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtUpdateStatus->bindParam(':statusKeyword', $statusKeyword, PDO::PARAM_STR);
            $stmtUpdateStatus->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $stmtUpdateStatus->execute();

            $affectedRows = $stmtUpdateStatus->rowCount();
            // if ($affectedRows > 0) {
            //     error_log("Updated $affectedRows row(s) successfully");
            // } else {
            //     error_log("No rows updated for UUID $uuid. This might be expected if the status is already set.");
            // }

            if ($useTransaction)
            {
                $pdo->commit();
            }

            return $affectedRows > 0;
        }
        catch (PDOException $e)
        {
            error_log("PDOException in updatePlayerStatus: " . $e->getMessage());
            if ($useTransaction)
            {
                $pdo->rollBack();
            }
            return false;
        }
    }

    // private static function updateSpeciesHealth($pdo, $uuid, $damage)
    // {
    //     $currentHealth = self::getPlayerCurrentHealth($pdo, $uuid);
    //     $maxHealth = self::getPlayerMaxBlood($pdo, $uuid);

    //     // Calculate the new health value, ensuring it doesn't go below 0 or above maxHealth
    //     $newHealth = min(max($currentHealth + $damage, 0), $maxHealth);
    //     $updateQuery = "UPDATE players SET player_current_health = :newHealth WHERE player_uuid = :uuid";
    //     $stmtUpdate = $pdo->prepare($updateQuery);
    //     $stmtUpdate->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtUpdate->bindParam(':newHealth', $newHealth, PDO::PARAM_STR);
    //     $stmtUpdate->execute();

    //     return $newHealth;
    // }

    // private static function updateTheirHumanBlood($pdo, $uuid, $bloodLost)
    // {
    //     $currentBlood = self::getHumanBloodLevel($pdo, $uuid);

    //     // Ensure blood doesn't go below 0
    //     $newBlood = max($currentBlood - $bloodLost, 0);

    //     // Update human's blood level
    //     $updateQuery = "UPDATE humans SET blood_level = :newBlood WHERE human_uuid = :uuid";
    //     $stmtUpdate = $pdo->prepare($updateQuery);
    //     $stmtUpdate->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtUpdate->bindParam(':newBlood', $newBlood, PDO::PARAM_STR);
    //     $stmtUpdate->execute();

    //     return $newBlood;
    // }

    public static function updatePlayerHudSaveLocation($pdo, $uuid, $newHudSaveLocation)
    {

        try
        {
            // Start a transaction
            $pdo->beginTransaction();

            // SQL query to update player HUD save location
            $updateHudSaveLocationQuery = "UPDATE players
                                           SET player_hud_save_location = :newHudSaveLocation
                                           WHERE player_uuid = :uuid";

            $stmtUpdateHudSaveLocation = $pdo->prepare($updateHudSaveLocationQuery);
            $stmtUpdateHudSaveLocation->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmtUpdateHudSaveLocation->bindParam(':newHudSaveLocation', $newHudSaveLocation, PDO::PARAM_STR);
            $stmtUpdateHudSaveLocation->execute();

            // If we got this far without an exception, commit the transaction
            $pdo->commit();

            return new JsonResponse(200, "player_hud_save_location_updated", [
                'player_uuid' => $uuid,
                'new_hud_save_location' => $newHudSaveLocation,
            ]);
        }
        catch (Exception $e)
        {
            // An error occurred, rollback the transaction
            $pdo->rollBack();

            // Log or handle the error as appropriate
            // ...

            return new JsonResponse(500, "player_hud_save_location_update_failed", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function getSpeciesCurrentHealth($pdo, $uuid)
    {
        try
        {
            $query = "SELECT player_current_health FROM players WHERE player_uuid = :uuid";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();

            $currentHealth = $stmt->fetchColumn();

            if ($currentHealth !== false)
            {
                return (float) $currentHealth;
            } else
            {
                // Handle the case where the player is not found or current health is NULL
                return 0.0; // Default to 0 health
            }
        }
        catch (PDOException $e)
        {
            // Handle the error as needed
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    private static function ensureHumanExists($pdo, $uuid)
    {
        try
        {
            // Check if the player is in the human table
            $checkQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
            $stmt = $pdo->prepare($checkQuery);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();

            $rowCount = $stmt->fetchColumn();

            if ($rowCount == 0)
            {
                // Player is not in the human table, add them
                $insertQuery = "INSERT INTO humans (human_uuid) VALUES (:uuid)";
                $stmtInsert = $pdo->prepare($insertQuery);
                $stmtInsert->bindParam(':uuid', $uuid, PDO::PARAM_STR);
                $stmtInsert->execute();
            }
        }
        catch (PDOException $e)
        {
            // Handle any errors here
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    // private static function getPlayerCurrentHealth($pdo, $uuid)
    // {
    //     $selectQuery = "SELECT player_current_health FROM players WHERE player_uuid = :uuid";
    //     $stmtSelect = $pdo->prepare($selectQuery);
    //     $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtSelect->execute();

    //     return $stmtSelect->fetchColumn();
    // }

    // public static function getHumanBloodLevel($pdo, $uuid, $default = -1)
    // {

    //     $selectQuery = "SELECT blood_level FROM humans WHERE human_uuid = :uuid";
    //     $stmtSelect = $pdo->prepare($selectQuery);
    //     $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtSelect->execute();

    //     $bloodLevel = $stmtSelect->fetchColumn();

    //     return ($bloodLevel !== false) ? $bloodLevel : $default;
    // }

    // private static function getPlayerMaxBlood($pdo, $uuid)
    // {
    //     try
    //     {
    //         // Query to retrieve the max blood level based on age group
    //         $query = "SELECT age_group.max_health
    //                   FROM players
    //                   INNER JOIN player_age_group AS age_group ON players.player_age_group_id = age_group.player_age_group_id
    //                   WHERE players.player_uuid = :uuid
    //                   LIMIT 1";

    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmt->execute();

    //         $maxBloodLevel = $stmt->fetchColumn();

    //         return $maxBloodLevel;
    //     }
    //     catch (PDOException $e)
    //     {
    //         // Handle the error as needed
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }

    public static function getTheirSpeciesType($pdo, $uuid)
    {
        try
        {
            // Query to retrieve the player's species type
            $query = "SELECT species.species_type
                      FROM players
                      INNER JOIN species ON players.species_id = species.species_id
                      WHERE players.player_uuid = :uuid";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
            $stmt->execute();

            $speciesType = $stmt->fetchColumn();

            return $speciesType;
        }
        catch (PDOException $e)
        {
            // Handle the error as needed
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    private static function getVersionNumber($pdo)
    {
        try
        {
            $versionName = 'Mystical Convergence';

            $query = "SELECT version_number FROM version WHERE version_name = :versionName";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':versionName', $versionName, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result)
            {
                return $result;
            } else
            {
                return null; // Version not found
            }
        }
        catch (PDOException $e)
        {
            // Handle database connection errors or other exceptions
            return null;
        }
    }

    public static function checkPlayerExists($pdo, $playerUUID)
    {
        try
        {
            $query = "SELECT COUNT(*) FROM players WHERE player_uuid = :uuid";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $playerUUID, PDO::PARAM_STR);
            $stmt->execute();

            $count = $stmt->fetchColumn();
            return ($count > 0);
        }
        catch (PDOException $e)
        {
            // Handle the error as needed
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    public static function checkHumanExists($pdo, $humanUUID)
    {
        try
        {

            $query = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
            $stmt->execute();

            $count = $stmt->fetchColumn();
            return ($count > 0);
        }
        catch (PDOException $e)
        {
            throw new Exception("Error: " . $e->getMessage());
        }
    }

    public static function deleteHumanByUUID($pdo, $humanUUID)
    {
        try
        {
            // Create the DELETE SQL query
            $query = "DELETE FROM humans WHERE human_uuid = :uuid";

            // Prepare and bind parameters
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);

            // Execute the deletion
            $stmt->execute();

            // Check if any row was deleted
            return $stmt->rowCount() > 0;
        }
        catch (PDOException $e)
        {
            // Log the error or handle it as required
            error_log("Database Error in deleteHumanByUUID: " . $e->getMessage());
            return false;
        }
    }

    // The sireNewHuman function handles the process of creating a new human
    // by inheriting attributes from a siring player and then inserting this new human into the 'players' table.
    public static function sireNewHuman($pdo, string $humanUUID, string $legacy_name, string $sirePlayerUUID)
    {
        try
        {
            // Start the transaction
            $pdo->beginTransaction();

            // Step 1: Fetch attributes of the siring player.
            $bloodline_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "bloodline_id");
            $species_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "species_id");
            $sire_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "player_id");
            $player_essence = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "player_essence");
            $sire_generation = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "player_generation");

            // Step 2: Validation of fetched attributes.
            if (!$bloodline_id || !$species_id || !$sire_id || $player_essence === null)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Siring player id, bloodline, species, or essence not found.");
            }

            if ($player_essence < 2)
            {
                $pdo->rollBack();
                return new JsonResponse(400, "Insufficient essence to sire a player.");
            }

            if ($sire_generation === null)
            {
                $pdo->rollBack();
                return new JsonResponse(404, "Siring player's generation not found.");
            }

            // Step 3: Prepare the data for the new human.
            $insertData = [
                "player_uuid" => $humanUUID,
                "legacy_name" => $legacy_name,
                "bloodline_id" => $bloodline_id,
                "species_id" => $species_id,
                "sire_id" => $sire_id,
                "player_generation" => $sire_generation + 1,
            ];

            // Step 4: Insert the new human's data into the 'players' table.
            $tableName = "players";
            $response = TableController::insertRowIntoTableWithData($pdo, $tableName, $insertData);
            $responseData = json_decode($response);

            if (!$responseData || !isset($responseData->status) || $responseData->status !== 201)
            {
                $pdo->rollBack();
                return $response; // Or a custom error message
            }

            // Step 5: Assign the correct age group based on the species.
            $ageGroupResult = self::assignAgeGroup($pdo, $humanUUID);
            if (isset($ageGroupResult['error']))
            {
                $pdo->rollBack();
                return new JsonResponse(500, "Failed to assign age group: " . $ageGroupResult['error']);
            }

            // Step 6: Update the player status to 'alive'.
            $status = self::updatePlayerStatus($pdo, $humanUUID, "alive", false);
            if ($status == 0)
            {
                return new JsonResponse(500, "Failed to update player status to alive", $status);
            }

            // Step 7: Deduct 2 essence points from the siring player.
            $updatedEssence = $player_essence - 2;
            $updateResponse = TableController::updateData($pdo, "players", $sirePlayerUUID, ["player_essence" => $updatedEssence]);
            $updateResponseData = json_decode($updateResponse);

            if (!$updateResponseData || !isset($updateResponseData->status) || $updateResponseData->status !== 200)
            {
                $pdo->rollBack();
                return $updateResponse; // Or a custom error message
            }

            // Step 8: Delete the corresponding human record from the 'humans' table.
            self::deleteHumanByUUID($pdo, $humanUUID);

            // Step 9: Additional operations like API calls
            // $postUrl = 'https://api.systemsl.xyz/VendorSystem/deliver_items.php';
            // $postData = ['avatar_uuid' => $humanUUID, 'item_name' => 'Realm of Darkness v1.0.41'];
            // CommunicationController::sendPostRequest($postUrl, $postData, []);

            // If all operations are successful, commit the transaction

            $pdo->commit();
            // Send discord webhook of new player.
            $webhookUrl = "https://discordapp.com/api/webhooks/1174106679768535090/gt58ZyzqAOzgrElOQQ1egnVVlKQseg57E3eOU5X_iAUf-0F9dM5myVqDfczugEVEB2Iq";
            $sireLegacyName = PlayerDataController::getPlayerLegacyName($pdo, $sirePlayerUUID);
            $regionName = SecondLifeHeadersStatic::getRegionName();
            $message = "- New player information - \n$legacy_name has been sired by $sireLegacyName \nTheir UUID is $humanUUID \nRegion: $regionName";
            CommunicationController::sendDiscordWebhook($webhookUrl, $message);

            // Log the activity without waiting for a response
            $activityName = "turn_new_player"; // Replace with the actual activity name
            ActivityController::logActivity($pdo, $sirePlayerUUID, $activityName);

            return new JsonResponse(201, "Human sired successfully and all updates applied.");
        }
        catch (Exception $e)
        {
            // In case of any exception, roll back the transaction
            $pdo->rollBack();
            // Capture the full error message, including the stack trace
            $fullErrorMessage = "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();

            // Create a response with the full error message as a string
            $errorResponse = [
                'status' => 'error',
                'message' => $fullErrorMessage,
            ];

            return new JsonResponse(500, json_encode($errorResponse));
        }
    }

    //  The handleCheckHumanBeforeSire function checks the existence and attributes of a human using its UUID.
    //  It provides appropriate responses based on whether the human exists in the 'players' or 'humans' table.

    public static function handleCheckHumanBeforeSire($raw, $pdo)
    {
        $data = json_decode($raw, true);

        if ($data !== null)
        {
            // Step 1: Extract the human's UUID from the provided data.
            $uuid = $data["human_uuid"];

            // Step 2: Check if this UUID is already associated with a player in the 'players' table.
            $isPlayer = self::checkPlayerExists($pdo, $uuid);

            if ($isPlayer)
            {
                return new JsonResponse(200, "UUID exists in the players database.", ["human_uuid" => $uuid]);
            } else
            {
                // Step 3: If the UUID is not associated with a player, check if it exists as a human in the 'humans' table.
                $isHuman = self::checkHumanExists($pdo, $uuid);

                if ($isHuman)
                {
                    // Step 4: If the UUID exists in the 'humans' table, fetch and return the blood level of the human.
                    $bloodLevel = self::getHumanBloodLevel($pdo, $uuid);

                    if ($bloodLevel == -1)
                    {
                        return new JsonResponse(404, "UUID exists as a human, but blood level not found.", ["human_uuid" => $uuid]);
                    } else
                    {
                        // Return the blood level and human UUID as separate key-value pairs.
                        return new JsonResponse(200, "UUID exists as a human in the database.", ["human_blood_level" => $bloodLevel, "human_uuid" => $uuid]);
                    }
                } else
                {
                    // Step 5: If the UUID is neither in 'players' nor 'humans', add it to the 'humans' table.
                    $dataToInsert = ['human_uuid' => $uuid];
                    $response = TableController::insertRowIntoTableWithData($pdo, 'humans', $dataToInsert);

                    $responseData = json_decode($response);

                    // Step 6: After adding to the 'humans' table, fetch and return the human's blood level.
                    if ($responseData && isset($responseData->status) && $responseData->status === 201)
                    {
                        $bloodLevel = self::getHumanBloodLevel($pdo, $uuid);
                        // Return the blood level and human UUID as separate key-value pairs.
                        return new JsonResponse(201, "UUID added successfully as a human.", ["human_blood_level" => $bloodLevel, "human_uuid" => $uuid]);
                    } else
                    {
                        return $response;
                    }
                }
            }
        } else
        {
            return new JsonResponse(400, "Error decoding JSON data.");
        }
    }

    public static function checkPlayerUUIDExists($pdo, $uuid)
    {
        try
        {
            // Prepare the SQL statement
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE player_uuid = :uuid");

            // Bind the UUID parameter
            $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);

            // Execute the query
            $stmt->execute();

            // Fetch the result
            $exists = $stmt->fetchColumn() > 0;

            // Return true if the UUID exists, false otherwise
            return $exists;

        }
        catch (PDOException $e)
        {
            // Handle the exception
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }

    //This method is for updating the hud url and checking if the sim is a safe zone
    public static function updatePlayerAttachedHudDetails($pdo, $rawJson)
    {
        $responseExtra = [
            'update_status' => 'pending',
            'safe_zone' => 'pending',
        ];

        // Decode the JSON input
        $data = json_decode($rawJson, true);
        if ($data === null)
        {
            return new JsonResponse(400, "Error decoding JSON data", ['error' => 'json_decoding_error']);
        }

        $playerUUID = $data["player_uuid"] ?? null;
        $playerHudUrl = $data["player_hud_url"] ?? null;
        $regionName = $data["region_name"] ?? null;
        $parcelId = $data["parcel_id"] ?? null;

        if (!$playerUUID || !$playerHudUrl || !$regionName || !$parcelId)
        {
            return new JsonResponse(400, "Incomplete data received", ['error' => 'missing_data']);
        }

        // Check if the specified parcel in the region is a safe zone
        $safeZoneData = self::checkRegionSafeZone($pdo, $regionName, $parcelId);
        $responseExtra['safe_zone'] = $safeZoneData['safe_zone'];
        $responseExtra['parcel_id'] = $safeZoneData['parcel_id'];
        $responseExtra['min_height'] = $safeZoneData['min_height'];
        $responseExtra['max_height'] = $safeZoneData['max_height'];

        // Update the HUD URL for the player
        $updateResponse = TableController::updateData($pdo, "players", $playerUUID, ['player_hud_url' => $playerHudUrl, 'current_sim' => $regionName]);

        if ($updateResponse->getStatus() == 200)
        {
            // Update successful
            $responseExtra['update_status'] = 'success';
            $responseMessage = "HUD URL updated successfully";
        } else
        {
            // Update failed
            $responseExtra['update_status'] = 'failed';
            $responseMessage = $updateResponse->getMessage();
            $responseStatus = $updateResponse->getStatus();
            return new JsonResponse($responseStatus, $responseMessage, $responseExtra);
        }

        return new JsonResponse(200, $responseMessage, $responseExtra);
    }

    // private static function checkRegionSafeZone($pdo, $regionName, $parcelId)
    // {
    //     // First, check if the specific parcel in this region is a safe zone
    //     $stmt = $pdo->prepare("SELECT COUNT(*) FROM safe_zone WHERE region_name = :region_name AND parcel_id = :parcel_id");
    //     $stmt->execute(['region_name' => $regionName, 'parcel_id' => $parcelId]);

    //     if ($stmt->fetchColumn() > 0) {
    //         // The specific parcel is a safe zone
    //         return true;
    //     }

    //     // Next, check if the entire region is a safe zone (parcel_id is NULL)
    //     $stmt = $pdo->prepare("SELECT COUNT(*) FROM safe_zone WHERE region_name = :region_name AND parcel_id IS NULL");
    //     $stmt->execute(['region_name' => $regionName]);

    //     return $stmt->fetchColumn() > 0; // Return true if the entire region is a safe zone
    // }

    private static function checkRegionSafeZone($pdo, $regionName, $parcelId)
    {
        $stmt = $pdo->prepare("
        SELECT parcel_id, min_height, max_height
        FROM safe_zone
        WHERE region_name = :region_name
    ");

        $stmt->execute([
            'region_name' => $regionName,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'safe_zone' => $result ? 'true' : 'false',
            'parcel_id' => $result ? ($result['parcel_id'] === null ? '' : $result['parcel_id']) : '',
            'min_height' => $result ? ($result['min_height'] === null ? '' : $result['min_height']) : '',
            'max_height' => $result ? ($result['max_height'] === null ? '' : $result['max_height']) : '',
        ];
    }

    public static function assignAgeGroup($pdo, $playerUUID)
    {
        try
        {
            // Retrieve the player's age and species_id
            $playerQuery = "SELECT player_age, species_id FROM players WHERE player_uuid = :playerUUID";
            $stmtPlayer = $pdo->prepare($playerQuery);
            $stmtPlayer->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $stmtPlayer->execute();

            $playerResult = $stmtPlayer->fetch(PDO::FETCH_ASSOC);
            if (!$playerResult)
            {
                // No player found or missing age/species_id
                return ['error' => 'Player data not found or incomplete'];
            }
            $playerAge = $playerResult['player_age'];
            $speciesId = $playerResult['species_id'];

            // Find the appropriate age group ID based on the player's age and species from the player_age_group table
            $ageGroupQuery = "SELECT player_age_group_id FROM player_age_group
                            WHERE (species_id = :speciesId OR species_id IS NULL)
                            AND age_group_required_age <= :playerAge
                            ORDER BY age_group_required_age DESC LIMIT 1";
            $stmtAgeGroup = $pdo->prepare($ageGroupQuery);
            $stmtAgeGroup->bindParam(':speciesId', $speciesId, PDO::PARAM_INT);
            $stmtAgeGroup->bindParam(':playerAge', $playerAge, PDO::PARAM_INT);
            $stmtAgeGroup->execute();

            $ageGroupResult = $stmtAgeGroup->fetch(PDO::FETCH_ASSOC);
            if (!$ageGroupResult)
            {
                // No age group found for this player's age and species
                return ['error' => 'Appropriate age group not found'];
            }

            // Update the player's age group in the players table
            $updateAgeGroupQuery = "UPDATE players SET player_age_group_id = :ageGroupID WHERE player_uuid = :playerUUID";
            $stmtUpdateAgeGroup = $pdo->prepare($updateAgeGroupQuery);
            $stmtUpdateAgeGroup->bindParam(':ageGroupID', $ageGroupResult['player_age_group_id'], PDO::PARAM_INT);
            $stmtUpdateAgeGroup->bindParam(':playerUUID', $playerUUID, PDO::PARAM_STR);
            $stmtUpdateAgeGroup->execute();

            return ['success' => 'Age group assigned successfully'];
        }
        catch (PDOException $e)
        {
            // Handle any database errors
            return ['error' => $e->getMessage()];
        }
    }
}
