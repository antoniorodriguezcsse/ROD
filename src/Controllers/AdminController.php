<?php

namespace Fallen\SecondLife\Controllers;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use Exception;
use PDO;
use PDOException;

//get player dSata with a class
///class
class AdminController {

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // check if they are an admin in the database and return their admin level if they are.
    public static function checkAdmin($pdo, $uuid) {
        $stmt = $pdo->prepare("SELECT admin_level FROM admin WHERE admin_uuid = :uuid");
        $stmt->bindParam(':uuid', $uuid);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $adminLevel = $stmt->fetchColumn();
            return new JsonResponse(200, "Admin verified", ["is_admin" => "true", "admin_level" => $adminLevel]);
        } else {
            return new JsonResponse(200, "Not an admin", ["is_admin" => "false"]);
        }
    }

    public static function killPlayer($pdo, $playerUUID) {
        try {
           
            PlayerController::updatePlayerStatus($pdo, $playerUUID, "dead");
            // // SQL query to update the player's status and health
            $sql = "UPDATE players SET player_current_health = 0.0 WHERE player_uuid = :uuid";
    
            // // Prepare the SQL statement
             $stmt = $pdo->prepare($sql);
    
            // // Bind parameters
             $stmt->bindParam(":uuid", $playerUUID, PDO::PARAM_STR);
    
            // // Execute the statement
             $stmt->execute();
    
            // // Close the database connection
             $pdo = null;
    
           // echo "Player's status and health updated successfully.";
            return new JsonResponse(200, "player_killed_successfully", [""]);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
    
    // public static function create($pdo, string $playerUUID, string $legacy_name, string $species_id) {
    //     try {
    
    //         // Check if the player_uuid already exists in the players table
    //         $checkQuery = "SELECT * FROM players WHERE player_uuid = :player_uuid LIMIT 1";
    //         $checkStmt = $pdo->prepare($checkQuery);
    //         $checkStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
    //         $checkStmt->execute();
    
    //         if ($checkStmt->fetch()) {
    //             return new JsonResponse(409, "Player exists.");
    //         }
    
    //         // Check if the player_uuid exists in the humans table
    //         $checkHumanQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :player_uuid";
    //         $checkHumanStmt = $pdo->prepare($checkHumanQuery);
    //         $checkHumanStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
    //         $checkHumanStmt->execute();
    //         $rowCount = $checkHumanStmt->fetchColumn();
    
    //         if ($rowCount > 0) {
    //             // Player is in the humans table, remove them from there
    //             $deleteHumanQuery = "DELETE FROM humans WHERE human_uuid = :player_uuid";
    //             $deleteHumanStmt = $pdo->prepare($deleteHumanQuery);
    //             $deleteHumanStmt->bindParam(':player_uuid', $playerUUID, PDO::PARAM_STR);
    //             $deleteHumanStmt->execute();
    //         }
    
    //         // Insert the new player into the players table
    //         $insertQuery = "INSERT INTO players (player_uuid, legacy_name, species_id)
    //                         VALUES (?, ?, ?)";
    //         $insertStmt = $pdo->prepare($insertQuery);
    //         $insertStmt->bindParam(1, $playerUUID, PDO::PARAM_STR);
    //         $insertStmt->bindParam(2, $legacy_name, PDO::PARAM_STR);
    //         $insertStmt->bindParam(3, $species_id, PDO::PARAM_INT);
    //         $success = $insertStmt->execute();
    
    //         if ($success) {
    //             // Player created successfully
    //             return new JsonResponse(201, "Player created.");
    //         } else {
    //             // Handle the failure case
    //             return new JsonResponse(500, "Failed to insert player data");
    //         }
    //     } catch (PDOException $e) {
    //         // Handle database connection errors
    //         return new JsonResponse(500, "Database Error: " . $e->getMessage());
    //     }
    // }

    // public static function getSire($pdo, $player_id) {
    //     try {
         

    //         // SQL query to retrieve legacy_name for the given player_id
    //         $sql = "SELECT legacy_name FROM players WHERE player_id = :player_id";
            
    //         // Prepare the statement
    //         $stmt = $pdo->prepare($sql);
            
    //         // Bind the player_id parameter
    //         $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
            
    //         // Execute the query
    //         $stmt->execute();

    //         // Fetch the result
    //         $result = $stmt->fetch();

    //         if ($result) {
    //             // Close the database connection
    //             $pdo = null;

    //             return $result['legacy_name'];
    //         } else {
    //             // Close the database connection
    //             $pdo = null;

    //             return "Player not found or legacy name is empty.";
    //         }
    //     } catch (PDOException $e) {
    //         return "Error: " . $e->getMessage();
    //     }
    // }
 
    // public static function getScan($pdo, $playerUUID){ 
        
  
    //     //check if they are a player
    //     // if they're a player show them the scan. 
    //     // if they're not a player show them blood levels.
    //     // when you bite them it automatically adds them to the humans table.
    //     if (self::checkPlayerExists($pdo, $playerUUID)) {
    //          return self::getSelfScan($pdo, $playerUUID);
    //     } else {
    //        // self::ensureHumanExists($pdo, $playerUUID);
    //        if(self::getHumanBloodLevel($pdo, $playerUUID) === -1){
    //             $scanResult = "secondlife:///app/agent/".$playerUUID."/about"." is a new fleshy human full of blood.";
    //        }
    //        else{
    //            $scanResult = "secondlife:///app/agent/".$playerUUID."/about"." is a human with ".self::getHumanBloodLevel($pdo, $playerUUID)." liters of blood.";
    //        }
    //        return new JsonResponse(200, "human_scan", ["human_scan_result" => $scanResult]);
    //     }
    // }

    // public static function getPlayerStatus($pdo, $playerUUID){
    //     $response = self::getPlayerData($pdo, $playerUUID); // Assuming this is your JSON response
    //     $data = json_decode($response, true);
    //     $extraData = $data['extra'];
    //     return $extraData["player_current_status"];
    // }

    // public static function getSelfScan($pdo, string $playerUUID){
    //     $response = self::getPlayerData($pdo, $playerUUID); // Assuming this is your JSON response
       
    //     // Decode the JSON string into an associative array
    //     $data = json_decode($response, true);

    //     // Access the "extra" data
    //     $extraData = $data['extra'];

    //     //Now, $extraData contains the "extra" data
    //     $bornDate = TableController::getPlayerFieldValue($pdo, $playerUUID, "join_date");
    //     $bornDate = self::convertDateTimeFormat($bornDate);
    //     $speciesType = $extraData['species_type'];
    //     $legacyName = $extraData['legacy_name'];
    //     $status = $extraData["player_current_status"];
    //     $ageGroup = $extraData["age_group_name"];
    //     $age = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_age"); 
    //     $generation = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_generation");
    //     $sire = TableController::getPlayerFieldValue($pdo, $playerUUID, "sire_id");
    //     if (empty($sire)) {
    //         $sire = "None";
    //     } else {
    //         $sire = self::getSire($pdo, $sire);  
    //     }

    //     $essence = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_essence");
    //     $subsistence =  $extraData["subsistence"];
    //     $currentHealth = $extraData["player_current_health"];
    //     $maxHealth = $extraData["max_health"];

    
       
    //     $bloodline = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'bloodline_id', 'bloodlines');
    //     $data = json_decode($bloodline, true);
    //     $bloodline = $data['bloodline_name'];
      
    //     $clan = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'clan_id', 'clans');
    //     $data = json_decode($clan, true);
    //     $clan = $data['clan_name'];

    //     $house = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'house_id', 'houses');
    //     $data = json_decode($house, true);
    //     $house = $data['house_name'];

    //     $selfScan = "\n\n==== The Realm of Darkness ====\n".
    //     "Username: ".$legacyName."\n".
    //     "Born: ".$bornDate."\n".
    //     "Sire: ".$sire."\n".
    //     "Generation: ".$generation."\n".
    //     "Species: ".ucfirst($speciesType)."\n".
    //     "Status: ".$status."\n".
    //     "\n========= Age =========\n".
    //     "Age: ".$age."\n".
    //     "Age Group: ".$ageGroup."\n".
    //     "Subsistence: ".$subsistence."\n".
    //     "Blood: ".$currentHealth." of ".$maxHealth."\n".
    //     "Essence: ".$essence."\n".
    //     "\n======= Bloodline =======\n".
    //     "Bloodline: ". $bloodline."\n".
    //     "Clan: ".$clan."\n".
    //     "House: ".$house."\n".
    //     "Role: Something here.";

    //     return new JsonResponse(200, "self_scan_result", ["self_scan" => $selfScan]);
    // }

    // private static function convertDateTimeFormat($dateTimeString) {
    //     // Convert the input date-time string to a Unix timestamp
    //     $timestamp = strtotime($dateTimeString);

    //     // Check if the conversion was successful
    //     if ($timestamp === false) {
    //         return "Invalid date-time format";
    //     }

    //     // Convert the timestamp to the desired format
    //     $formattedDateTime = date("F j, Y - g:i A", $timestamp);

    //     return $formattedDateTime;
    // }
    
    // public static function getPlayerData($pdo, string $playerUUID) {
  
        
    //     $playerExists = self::checkPlayerExists($pdo, $playerUUID);
    
    //     if (!$playerExists) {
    //         // Player doesn't exist, return an appropriate response
    //         return new JsonResponse(404, "Player not found");
    //     }

    //     $versionNumber = self::getVersionNumber($pdo);
    //     $statusData = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'player_status_id', 'player_status');
    //     $speciesData = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'species_id', 'species');

    //     $ageGroup = TableController::getForeignKeyData($pdo, "players", 'player_uuid', $playerUUID, 'player_age_group_id', 'player_age_group');
    //     $health = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_current_health");
    //     $legacyName = TableController::getPlayerFieldValue($pdo, $playerUUID, "legacy_name");

    //     $playerHudSaveLocation = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_hud_save_location");
    //     $playerBloodlineId = TableController::getPlayerFieldValue($pdo, $playerUUID, "bloodline_id");

    //     $playerEssence = TableController::getPlayerFieldValue($pdo, $playerUUID, "player_essence");

    //     $statusData = json_decode($statusData, true);
    //     $speciesData = json_decode($speciesData, true);
    //     $ageGroup = json_decode($ageGroup, true);
        
    //     $mergeArrays = is_array($statusData) && is_array($speciesData) 
    //                                          && is_array($ageGroup)
    //                                          && is_array($health)
    //                                          && is_array($legacyName) 
    //                                          && is_array($versionNumber)
    //                                           ? [] : array_merge($speciesData, 
    //                                                              $statusData, 
    //                                                              $ageGroup, 
    //                                                              ["player_current_health" => $health],
    //                                                              ["legacy_name" => $legacyName],
    //                                                              $versionNumber,
    //                                                              ["player_hud_save_location" => $playerHudSaveLocation],
    //                                                              ["bloodline_id" => $playerBloodlineId],
    //                                                              ["player_essence" => $playerEssence]);
    //     return new JsonResponse(200, "player_data", $mergeArrays);
    // }

    // public static function attack($pdo, string $theirUUID, float $theirDamage, string $myUUID) {
    //     try {
           
            
    //         //theirSpecies is false if they're human
    //         $theirSpecies = self::getTheirSpeciesType($pdo, $theirUUID);
            
    //         //if their species is false, check if they're in the database.
    //         // if not in the database add them to it.
    //         if ($theirSpecies === false) {
    //             self::ensureHumanExists($pdo, $theirUUID);
    //         }
          
    //         $isPlayer = !empty($theirSpecies);

    //         // if they are a species do the damage and return their new updated health value.
    //         // else if they're human, do the damage and return their new updated health value.
    //         $theirCurrentHealth = $isPlayer ? self::updateSpeciesHealth($pdo, $theirUUID, -$theirDamage) 
    //                                          :self::updateTheirHumanBlood($pdo, $theirUUID, $theirDamage);
            
    //         //10% spill I get 90% of their damage to heal me.
    //         //10% is also outputted to their display in the file "Attacking"
    //         $myNewHealth = self::updateSpeciesHealth($pdo, $myUUID, $theirDamage*0.90); // Assuming positive damage heals the attacker
    //         $myMaxBlood = self::getPlayerMaxBlood($pdo, $myUUID);
    //         $theirMaxBlood = self::getPlayerMaxBlood($pdo, $theirUUID);
           
    //         //if their health is 0, return they are dead
    //         // else if my new health > my max blood set my health to my max blood and return.
    //         // if not return my new updated health
    //         if($myNewHealth >  $myMaxBlood){
    //             $myNewHealth = $myMaxBlood;
    //         }

    //         if ($theirCurrentHealth <= 0) {
    //             try {
    //                 return self::updatePlayerStatus($pdo, $theirUUID, $isPlayer, $theirSpecies, $myNewHealth , "Slain");
    //             } catch (PDOException $e) {
    //                 // Handle any errors here
    //                 return new JsonResponse(500, "Error: " . $e->getMessage());
    //             }
    //         } 
    //         else{ 
    //             return new JsonResponse(200, "damage_done", [
    //                 'their_species' => $isPlayer ? $theirSpecies : "human",
    //                 'their_current_health' => $theirCurrentHealth,
    //                 'my_current_health' => $myNewHealth,
    //                 'their_max_health' => $theirMaxBlood,
    //             ]);
    //         }

    //     } catch (PDOException $e) {
    //         return new JsonResponse(500, "Error: " . $e->getMessage());
    //     }
    // }

    // public static function revivePlayer($pdo, $playerUuid) {
    //     try {
    //         // ==============================
    //         // STEP 1: DATABASE CONNECTION
    //         // Establish a connection to the MySQL database using PDO.
    //         // ==============================
         
    
    //         // ==============================
    //         // STEP 2: VALIDATE PLAYER EXISTENCE
    //         // Ensure that the player with the provided UUID exists in the database.
    //         // ==============================
    //         $existsQuery = "SELECT * FROM players WHERE player_uuid = :player_uuid";
    //         $existsStmt = $pdo->prepare($existsQuery);
    //         $existsStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
    //         $existsStmt->execute();
    
    //         if ($existsStmt->rowCount() == 0) {
    //             return new JsonResponse(404, "Player not found.");
    //         }
    
    //         $playerData = $existsStmt->fetch(PDO::FETCH_ASSOC);
    
    //         // ==============================
    //         // STEP 3: CHECK PLAYER STATUS
    //         // Ensure that the player is in a 'dead' status before attempting revival.
    //         // ==============================
    //         $currentStatusId = $playerData['player_status_id'];
    //         $speciesId = $playerData['species_id'];
    
    //         $isDeadQuery = "SELECT 1 FROM player_status WHERE player_status_id = :current_status_id AND species_id = :species_id AND player_status_keyword = 'dead'";
    //         $isDeadStmt = $pdo->prepare($isDeadQuery);
    //         $isDeadStmt->bindParam(':current_status_id', $currentStatusId, PDO::PARAM_INT);
    //         $isDeadStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
    //         $isDeadStmt->execute();
    
    //         if ($isDeadStmt->rowCount() == 0) {
    //             return new JsonResponse(409, "Player is not dead and cannot be revived.");
    //         }
    
    //         // ==============================
    //         // STEP 4: RETRIEVE MAX HEALTH
    //         // Determine the maximum health for the player based on their age group.
    //         // ==============================
    //         $ageGroupId = $playerData['player_age_group_id'];

    //         // Debug: Check if ageGroupId is null or empty
    //         if(empty($ageGroupId)) {
    //             return new JsonResponse(500, "Age group ID is empty or null.");
    //         }

    //         $maxHealthQuery = "SELECT max_health FROM player_age_group WHERE player_age_group_id = :age_group_id";
    //         $maxHealthStmt = $pdo->prepare($maxHealthQuery);
    //         $maxHealthStmt->bindParam(':age_group_id', $ageGroupId, PDO::PARAM_INT);
    //         $executionStatus = $maxHealthStmt->execute();

    //         // Debug: Check if PDO statement execution is successful
    //         if(!$executionStatus) {
    //             error_log("Error in maxHealthQuery: " . implode(", ", $maxHealthStmt->errorInfo()));
    //             return new JsonResponse(500, "Database query error: failed to fetch max health.");
    //         }

    //         if ($maxHealthStmt->rowCount() == 0) {
    //             return new JsonResponse(404, "Age group not found.");
    //         }

    //         $maxHealthData = $maxHealthStmt->fetch(PDO::FETCH_ASSOC);

    //         // Debug: Check if max_health is fetched correctly
    //         if(!isset($maxHealthData['max_health'])) {
    //             return new JsonResponse(500, "max_health is not set in fetched data.");
    //         }

    //         $maxHealth = $maxHealthData['max_health'];

    
    //         // ==============================
    //         // STEP 5: UPDATE PLAYER STATUS
    //         // Modify the player’s status to 'alive' and update their health to the max for their age group.
    //         // ==============================
    //         $aliveStatusIdQuery = "SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'alive'";
    //         $aliveStatusIdStmt = $pdo->prepare($aliveStatusIdQuery);
    //         $aliveStatusIdStmt->bindParam(':species_id', $speciesId, PDO::PARAM_INT);
    //         $aliveStatusIdStmt->execute();
    
    //         if ($aliveStatusIdStmt->rowCount() == 0) {
    //             return new JsonResponse(500, "Alive status ID not found for species.");
    //         }
    
    //         $aliveStatusData = $aliveStatusIdStmt->fetch(PDO::FETCH_ASSOC);
    //         $aliveStatusId = $aliveStatusData['player_status_id'];
    
    //         $reviveQuery = "UPDATE players SET player_status_id = :alive_status_id, player_current_health = :max_health WHERE player_uuid = :player_uuid";
    //         $reviveStmt = $pdo->prepare($reviveQuery);
    //         $reviveStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
    //         $reviveStmt->bindParam(':max_health', $maxHealth, PDO::PARAM_STR);
    //         $reviveStmt->bindParam(':alive_status_id', $aliveStatusId, PDO::PARAM_INT);
    //         $reviveStmt->execute();
    
    //         return new JsonResponse(200, "Player revived successfully.");
    
    //     // ==============================
    //     // STEP 6: ERROR HANDLING
    //     // Catch any potential exceptions and handle database errors.
    //     // ==============================
    //     } catch (PDOException $e) {
    //         error_log("Database Error: " . $e->getMessage());
    //         return new JsonResponse(500, "Database Error: " . $e->getMessage());
    //     }
    // }
    
    // public static function updatePlayerStatus($pdo, $uuid, $isPlayer, $theirSpecies, $myNewHealth, $newStatus) {  
    //     try {
    //         // SQL query to update player status
    //         $updateStatusQuery = "UPDATE players
    //                               SET player_status_id = (
    //                                   SELECT player_status_id
    //                                   FROM player_status
    //                                   WHERE player_current_status = :newStatus
    //                               )
    //                               WHERE player_uuid = :uuid";
         
    //         $stmtUpdateStatus = $pdo->prepare($updateStatusQuery);
    //         $stmtUpdateStatus->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmtUpdateStatus->bindParam(':newStatus', $newStatus, PDO::PARAM_STR);
    //         $stmtUpdateStatus->execute();
    
    //         return new JsonResponse(200, "player_is_slain",  [
    //             'their_species' => $isPlayer ? $theirSpecies : "human",
    //             'their_current_health' => 0.0,
    //             'my_current_health' => $myNewHealth,
    //         ]);
    //     }

    //     catch (PDOException $e) {
    //         // Handle database connection errors or other exceptions
    //         return new JsonResponse(500, "Database Error: " . $e->getMessage());
    //     }
    // }

    // private static function updateSpeciesHealth($pdo, $uuid, $damage) {
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

    // private static function updateTheirHumanBlood($pdo, $uuid, $bloodLost) {
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
    
    // public static function updatePlayerHudSaveLocation($pdo, $uuid, $newHudSaveLocation) {
       
    //     try {
    //         // Start a transaction
    //         $pdo->beginTransaction();
            
    //         // SQL query to update player HUD save location
    //         $updateHudSaveLocationQuery = "UPDATE players
    //                                        SET player_hud_save_location = :newHudSaveLocation
    //                                        WHERE player_uuid = :uuid";
         
    //         $stmtUpdateHudSaveLocation = $pdo->prepare($updateHudSaveLocationQuery);
    //         $stmtUpdateHudSaveLocation->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmtUpdateHudSaveLocation->bindParam(':newHudSaveLocation', $newHudSaveLocation, PDO::PARAM_STR);
    //         $stmtUpdateHudSaveLocation->execute();
            
    //         // If we got this far without an exception, commit the transaction
    //         $pdo->commit();
    
    //         return new JsonResponse(200, "player_hud_save_location_updated", [
    //             'player_uuid' => $uuid,
    //             'new_hud_save_location' => $newHudSaveLocation,
    //         ]);
    //     } catch (Exception $e) {
    //         // An error occurred, rollback the transaction
    //         $pdo->rollBack();
            
    //         // Log or handle the error as appropriate
    //         // ...
    
    //         return new JsonResponse(500, "player_hud_save_location_update_failed", [
    //             'error' => $e->getMessage(),
    //         ]);
    //     }
    // }

    // private static function getSpeciesCurrentHealth($pdo, $uuid) {
    //     try {
    //         $query = "SELECT player_current_health FROM players WHERE player_uuid = :uuid";
    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmt->execute();
    
    //         $currentHealth = $stmt->fetchColumn();
    
    //         if ($currentHealth !== false) {
    //             return (float)$currentHealth;
    //         } else {
    //             // Handle the case where the player is not found or current health is NULL
    //             return 0.0; // Default to 0 health
    //         }
    //     } catch (PDOException $e) {
    //         // Handle the error as needed
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }

    // private static function ensureHumanExists($pdo, $uuid) {
    //     try {
    //         // Check if the player is in the human table
    //         $checkQuery = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
    //         $stmt = $pdo->prepare($checkQuery);
    //         $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmt->execute();
    
    //         $rowCount = $stmt->fetchColumn();
    
    //         if ($rowCount == 0) {
    //             // Player is not in the human table, add them
    //             $insertQuery = "INSERT INTO humans (human_uuid) VALUES (:uuid)";
    //             $stmtInsert = $pdo->prepare($insertQuery);
    //             $stmtInsert->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //             $stmtInsert->execute();
    //         }
    //     } catch (PDOException $e) {
    //         // Handle any errors here
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }
    
    // private static function getPlayerCurrentHealth($pdo, $uuid) {
    //     $selectQuery = "SELECT player_current_health FROM players WHERE player_uuid = :uuid";
    //     $stmtSelect = $pdo->prepare($selectQuery);
    //     $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtSelect->execute();
    
    //     return $stmtSelect->fetchColumn();
    // }
    
    // public static function getHumanBloodLevel($pdo, $uuid, $default = -1) {
        
    //     $selectQuery = "SELECT blood_level FROM humans WHERE human_uuid = :uuid";
    //     $stmtSelect = $pdo->prepare($selectQuery);
    //     $stmtSelect->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //     $stmtSelect->execute();
    
    //     $bloodLevel = $stmtSelect->fetchColumn();
    
    //     return ($bloodLevel !== false) ? $bloodLevel : $default;
    // }
     
    // private static function getPlayerMaxBlood($pdo, $uuid) {
    //     try {
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
    //     } catch (PDOException $e) {
    //         // Handle the error as needed
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }   
    
    // private static function getTheirSpeciesType($pdo, $uuid) {
    //     try {
    //         // Query to retrieve the player's species type
    //         $query = "SELECT species.species_type
    //                   FROM players
    //                   INNER JOIN species ON players.species_id = species.species_id
    //                   WHERE players.player_uuid = :uuid";
    
    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    //         $stmt->execute();
    
    //         $speciesType = $stmt->fetchColumn();
    
    //         return $speciesType;
    //     } catch (PDOException $e) {
    //         // Handle the error as needed
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }

    // private static function getVersionNumber($pdo) {
    //     try {
         
    //         $versionName = 'Mystical Convergence';

    //         $query = "SELECT version_number FROM version WHERE version_name = :versionName";
    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':versionName', $versionName, PDO::PARAM_STR);
    //         $stmt->execute();

    //         $result = $stmt->fetch(PDO::FETCH_ASSOC);

    //         if ($result) {
    //             return $result;
    //         } else {
    //             return null; // Version not found
    //         }
    //     } catch (PDOException $e) {
    //         // Handle database connection errors or other exceptions
    //         return null;
    //     }
    // }

    // public static function checkPlayerExists($pdo, $playerUUID) {
    //     try {

            
    //         $query = "SELECT COUNT(*) FROM players WHERE player_uuid = :uuid";

    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $playerUUID, PDO::PARAM_STR);
    //         $stmt->execute();
    
    //         $count = $stmt->fetchColumn();
    //         return ($count > 0);
    //     } catch (PDOException $e) {
    //         // Handle the error as needed
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }

    // public static function checkHumanExists($pdo, $humanUUID) {
    //     try {
           
    //         $query = "SELECT COUNT(*) FROM humans WHERE human_uuid = :uuid";
    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
    //         $stmt->execute();
        
    //         $count = $stmt->fetchColumn();
    //         return ($count > 0);
    //     } catch (PDOException $e) {
    //         throw new Exception("Error: " . $e->getMessage());
    //     }
    // }

    // public static function deleteHumanByUUID($pdo, $humanUUID) {
    //     try {
           
           
            
    //         // Create the DELETE SQL query
    //         $query = "DELETE FROM humans WHERE human_uuid = :uuid";
            
    //         // Prepare and bind parameters
    //         $stmt = $pdo->prepare($query);
    //         $stmt->bindParam(':uuid', $humanUUID, PDO::PARAM_STR);
            
    //         // Execute the deletion
    //         if ($stmt->execute()) {
    //             // Check if any row was deleted
    //             if ($stmt->rowCount() > 0) {
    //                 return true;  // Successfully deleted
    //             } else {
    //                 throw new Exception("No human found with UUID: $humanUUID");
    //             }
    //         } else {
    //             throw new Exception("Failed to delete human with UUID: $humanUUID");
    //         }
    //     } catch (PDOException $e) {
    //         throw new Exception("Database Error: " . $e->getMessage());
    //     }
    // }


// The sireNewHuman function handles the process of creating a new human 
// by inheriting attributes from a siring player and then inserting this new human into the 'players' table.
 
// public static function sireNewHuman($pdo, string $humanUUID, string $legacy_name, string $sirePlayerUUID) {
//     try {
//         // Step 1: Fetch attributes of the siring player.
//         // This step involves querying the database to retrieve specific attributes (bloodline, species, sire ID, and essence) 
//         // of the player who is siring the new human.
//         $bloodline_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "bloodline_id");
//         $species_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "species_id");
//         $sire_id = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "player_id");
//         $player_essence = TableController::getPlayerFieldValue($pdo, $sirePlayerUUID, "player_essence");

//         // Step 2: Validation of fetched attributes.
//         // Before proceeding, it's crucial to ensure that all the required attributes are available.
//         // If any attribute is missing, the function returns an error response.
//         if (!$bloodline_id || !$species_id || !$sire_id || !$player_essence) {
//             return new JsonResponse(404, "Siring player id, bloodline, species, or essence not found.");
//         }

//         // Check if there's enough essence to perform the siring (requires at least 2 essence points).
//         if ($player_essence < 2) {
//             return new JsonResponse(400, "Insufficient essence to sire a player.");
//         }

//         // Step 3: Prepare the data for the new human.
//         // This involves creating an associative array that holds the new human's details, 
//         // including the inherited attributes from the siring player.
//         $insertData = [
//             "player_uuid" => $humanUUID,
//             "legacy_name" => $legacy_name,
//             "bloodline_id" => $bloodline_id,
//             "species_id" => $species_id,
//             "sire_id" => $sire_id
//         ];

//         // Step 4: Insert the new human's data into the 'players' table.
//         // This step makes use of the insertData function from the TableController to add the new human's record to the database.
//         $tableName = "players";
//         $response = TableController::insertRowIntoTableWithData($pdo, $tableName, $insertData);

//         // Step 5: Analyze the response from the insertion process.
//         $responseData = json_decode($response);

//         if ($responseData && isset($responseData->status) && $responseData->status === 201) {
//             // Step 6: Deduct 2 essence points from the player's essence in the database.
//             $updatedEssence = $player_essence - 2;
//             $updateData = [
//                 "player_essence" => $updatedEssence
//             ];
//             $updateResponse = TableController::updateData($pdo, "players", $sirePlayerUUID, $updateData);

//             // Check if the essence deduction was successful.
//             $updateResponseData = json_decode($updateResponse);
//             if ($updateResponseData && isset($updateResponseData->status) && $updateResponseData->status === 200) {
//                 // Step 7: Delete the corresponding human record from the 'humans' table.
//                 // After successfully siring (i.e., creating) a new human in the 'players' table,
//                 // the corresponding record in the 'humans' table is no longer needed and can be deleted.
//                 if (self::deleteHumanByUUID($pdo, $humanUUID)) {
//                     return new JsonResponse(201, "Human sired successfully and removed from the humans table.");
//                 } else {
//                     return new JsonResponse(500, "Human sired successfully, but failed to remove from the humans table.");
//                 }
//             } else {
//                 // Essence deduction was not successful, return the update response.
//                 return $updateResponse;
//             }
//         } else {
//             // If the insertion was not successful, the original response from the insertion process is returned.
//             return $response;
//         }
//     } catch (Exception $e) {
//         // Handle any exceptions that might arise during the process.
//         return new JsonResponse(500, "Error: " . $e->getMessage());
//     }
// }



//  The handleCheckHumanBeforeSire function checks the existence and attributes of a human using its UUID.
//  It provides appropriate responses based on whether the human exists in the 'players' or 'humans' table.
 
// public static function handleCheckHumanBeforeSire($raw, $pdo) {
//     $data = json_decode($raw, true);
    
//     if ($data !== null) {
//         // Step 1: Extract the human's UUID from the provided data.
//         $uuid = $data["human_uuid"];
        
//         // Step 2: Check if this UUID is already associated with a player in the 'players' table.
//         $isPlayer = self::checkPlayerExists($pdo, $uuid);
        
//         if ($isPlayer) {
//             return new JsonResponse(200, "UUID exists in the players database.", ["human_uuid" => $uuid]);
//         } else {
//             // Step 3: If the UUID is not associated with a player, check if it exists as a human in the 'humans' table.
//             $isHuman = self::checkHumanExists($pdo, $uuid);
            
//             if ($isHuman) {
//                 // Step 4: If the UUID exists in the 'humans' table, fetch and return the blood level of the human.
//                 $bloodLevel = self::getHumanBloodLevel($pdo, $uuid);
                
//                 if ($bloodLevel == -1) {
//                     return new JsonResponse(404, "UUID exists as a human, but blood level not found.", ["human_uuid" => $uuid]);
//                 } else {
//                     // Return the blood level and human UUID as separate key-value pairs.
//                     return new JsonResponse(200, "UUID exists as a human in the database.", ["human_blood_level" => $bloodLevel, "human_uuid" => $uuid]);
//                 }
//             } else {
//                 // Step 5: If the UUID is neither in 'players' nor 'humans', add it to the 'humans' table.
//                 $dataToInsert = ['human_uuid' => $uuid];
//                 $response = TableController::insertRowIntoTableWithData($pdo, 'humans', $dataToInsert);
    
//                 $responseData = json_decode($response);
    
//                 // Step 6: After adding to the 'humans' table, fetch and return the human's blood level.
//                 if ($responseData && isset($responseData->status) && $responseData->status === 201) {
//                     $bloodLevel = self::getHumanBloodLevel($pdo, $uuid);
//                     // Return the blood level and human UUID as separate key-value pairs.
//                     return new JsonResponse(201, "UUID added successfully as a human.", ["human_blood_level" => $bloodLevel, "human_uuid" => $uuid]);
//                 } else {
//                     return $response;
//                 }
//             }
//         }
//     } else {
//         return new JsonResponse(400, "Error decoding JSON data.");
//     }
// }

public static function banPlayer($pdo, $playerUUID, $banReason, $banDuration, $adminUUID) {
    try {
        $pdo->beginTransaction();

        // Get admin's player_id
        $adminPlayerId = PlayerDataController::getPlayerIdByUUID($pdo, $adminUUID);
        if (!$adminPlayerId) {
            throw new Exception("Admin ID not found");
        }

        // Get player info and check if exists
        $stmt = $pdo->prepare("SELECT player_id, species_id FROM players WHERE player_uuid = :uuid");
        $stmt->bindParam(':uuid', $playerUUID);
        $stmt->execute();
        $playerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playerInfo) {
            throw new Exception("Player not found");
        }

        // Check if player is already banned
        $checkBanQuery = "SELECT ban_id FROM banned_players WHERE player_id = :player_id";
        $stmt = $pdo->prepare($checkBanQuery);
        $stmt->bindParam(':player_id', $playerInfo['player_id']);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            throw new Exception("Player is already banned");
        }
        
        // Get the correct banned status ID for this species
        $statusStmt = $pdo->prepare("SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'banned'");
        $statusStmt->bindParam(':species_id', $playerInfo['species_id']);
        $statusStmt->execute();
        $statusResult = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$statusResult) {
            throw new Exception("Could not find banned status for species ID: " . $playerInfo['species_id']);
        }
        
        $bannedStatusId = $statusResult['player_status_id'];

        // Calculate ban end date
        $banEndDate = new \DateTime();
        if ($banDuration > 0) {
            $banEndDate->modify("+{$banDuration} days");
        }

        // Insert into banned_players table
        $insertBanQuery = "INSERT INTO banned_players (
            player_id, 
            ban_start_date, 
            ban_end_date, 
            ban_reason, 
            banned_by,
            is_permanent
        ) VALUES (
            :player_id,
            CURRENT_TIMESTAMP,
            :ban_end_date,
            :ban_reason,
            :banned_by,
            :is_permanent
        )";

        $stmt = $pdo->prepare($insertBanQuery);
        $isPermanent = ($banDuration === 0) ? 1 : 0;
        $banEndDateStr = $isPermanent ? null : $banEndDate->format('Y-m-d H:i:s');

        $stmt->bindParam(':player_id', $playerInfo['player_id']);
        $stmt->bindParam(':ban_end_date', $banEndDateStr);
        $stmt->bindParam(':ban_reason', $banReason);
        $stmt->bindParam(':banned_by', $adminPlayerId);
        $stmt->bindParam(':is_permanent', $isPermanent);
        
        $stmt->execute();

        // Update player status to banned and set health to 0
        $updateQuery = "UPDATE players 
                       SET player_status_id = :status_id, 
                           player_current_health = 0.000
                       WHERE player_uuid = :uuid";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':status_id', $bannedStatusId);
        $updateStmt->bindParam(':uuid', $playerUUID);
        $updateStmt->execute();

        $pdo->commit();

        // Add death log entry - do this after commit
        $regionName = SecondLifeHeadersStatic::getRegionName();

        
        $deathLogResult = DeathLogController::addDeathLogEntry(
            $pdo,
            PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID),
            PlayerDataController::getPlayerIdByUUID($pdo, $adminUUID),
            date('Y-m-d H:i:s'),
            $regionName,
            'killed_by_admin',
            'Player was banned by admin.'
        );
        $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
        if ($decodedDeathLogResult['status'] != 200) {
            error_log("Error updating death log: " . $decodedDeathLogResult['message']);
        }

        // Send reset HUD notification
        $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, [
            "status" => "200",
            "message" => "reset_hud"
        ]);

        return new JsonResponse(200, "Player banned successfully");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return new JsonResponse(500, "Error banning player: " . $e->getMessage());
    }
}

public static function unbanPlayer($pdo, $playerUUID, $adminUUID) {
    try {
        $pdo->beginTransaction();

        // Get player info
        $stmt = $pdo->prepare("SELECT player_id, species_id FROM players WHERE player_uuid = :uuid");
        $stmt->bindParam(':uuid', $playerUUID);
        $stmt->execute();
        $playerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$playerInfo) {
            throw new Exception("Player not found");
        }
        
        // Get the correct dead status ID for this species
        $statusStmt = $pdo->prepare("SELECT player_status_id FROM player_status WHERE species_id = :species_id AND player_status_keyword = 'dead'");
        $statusStmt->bindParam(':species_id', $playerInfo['species_id']);
        $statusStmt->execute();
        $statusResult = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$statusResult) {
            throw new Exception("Could not find dead status for species ID: " . $playerInfo['species_id']);
        }
        
        $deadStatusId = $statusResult['player_status_id'];

        // Check if player is actually banned
        $checkBanQuery = "SELECT ban_id FROM banned_players WHERE player_id = :player_id";
        $stmt = $pdo->prepare($checkBanQuery);
        $stmt->bindParam(':player_id', $playerInfo['player_id']);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new Exception("Player is not banned");
        }

        // Remove from banned_players table
        $deleteBanQuery = "DELETE FROM banned_players WHERE player_id = :player_id";
        $stmt = $pdo->prepare($deleteBanQuery);
        $stmt->bindParam(':player_id', $playerInfo['player_id']);
        $stmt->execute();

        // Update player status to dead and set health to 0
        $updateQuery = "UPDATE players 
                       SET player_status_id = :status_id, 
                           player_current_health = 0.000
                       WHERE player_uuid = :uuid";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':status_id', $deadStatusId);
        $updateStmt->bindParam(':uuid', $playerUUID);
        $updateStmt->execute();

        $pdo->commit();

        // Send reset HUD notification
        CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, [
            "status" => "200",
            "message" => "reset_hud"
        ]);

        return new JsonResponse(200, "Player unbanned successfully and set to dead status");

    } catch (Exception $e) {
        $pdo->rollBack();
        return new JsonResponse(500, "Error unbanning player: " . $e->getMessage());
    }
}
    
public static function checkBanStatus($pdo, $playerUUID) {
    try {
        $playerId = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
        if (!$playerId) {
            return new JsonResponse(404, "Player not found");
        }

        $checkQuery = "SELECT bp.ban_id, bp.is_permanent, bp.ban_end_date, bp.ban_reason
                     FROM banned_players bp 
                     WHERE bp.player_id = :player_id";
        
        $stmt = $pdo->prepare($checkQuery);
        $stmt->bindParam(':player_id', $playerId);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['is_permanent']) {
                return new JsonResponse(200, "Player is permanently banned", [
                    "is_banned" => "true",
                    "permanent" => "true",
                    "reason" => $row['ban_reason']
                ]);
            } else {
                $banEndDate = new \DateTime($row['ban_end_date']);
                $currentDate = new \DateTime();
                $isBanned = $currentDate < $banEndDate;
                
                return new JsonResponse(200, "Ban status retrieved", [
                    "is_banned" => $isBanned,
                    "permanent" => "false",
                    "end_date" => $row['ban_end_date'],
                    "reason" => $row['ban_reason']
                ]);
            }
        }
        
        return new JsonResponse(200, "Player is not banned", ["is_banned" => false]);

    } catch (Exception $e) {
        return new JsonResponse(500, "Error checking ban status: " . $e->getMessage());
    }
} 

 
   
}
?>