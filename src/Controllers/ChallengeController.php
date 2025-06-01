<?php
// ChallengeController.php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\SecondLifeHeaders;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use PDO;
use PDOException;

class ChallengeController
{
    /**
     * Start and monitor a fight between two players.
     *
     * @param PDO $pdo PDO database connection object
     * @param string $challengerUUID UUID of the challenger player
     * @param string $challengedUUID UUID of the challenged player
     * @param int $fightDurationMinutes Duration of the fight in minutes
     */
    public static function startAndMonitorFight($pdo, $challengerUUID, $challengedUUID, $fightDurationMinutes, $consumableUUID)
    {
        $fightDuration = $fightDurationMinutes * 60; // Convert minutes to seconds for internal use

        // Set the timeout to the fight duration plus a buffer (e.g., 60 seconds)
        set_time_limit($fightDuration + 60);

        // Constants for warning interval and warning timeout
        define('WARNING_INTERVAL', 10); // 10 seconds each loop
        define('WARNING_TIMEOUT', 60); // Time to retry in seconds before death
        define('STARTING_INTERVAL', 60); // 60 seconds 

        try {
            //error_log("\n -- \n -- \n Starting fight between $challengerUUID and $challengedUUID with $fightDurationMinutes minutes. \n -- \n -- \n");

            // Get the player IDs and starting region
            $challengerId = PlayerDataController::getPlayerIdByUUID($pdo, $challengerUUID);
            $challengedId = PlayerDataController::getPlayerIdByUUID($pdo, $challengedUUID);
            $slHeaders = new SecondLifeHeaders();
            $startingRegion = $slHeaders->getRegionName();
            error_log("Starting region: $startingRegion");

            // Insert the challenge into the database
            $stmt = $pdo->prepare("INSERT INTO challenges (challenger_id, challenged_id, status, starting_region, fight_duration) VALUES (?, ?, 'pending', ?, ?)");
            $stmt->execute([$challengerId, $challengedId, $startingRegion, $fightDuration]);
            $challengeId = $pdo->lastInsertId();

            // Send fight notifications to both players' HUDs
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                "status" => "200",
                "message" => "fight_notification",
                "extra" => [
                    'opponent_uuid' => $challengedUUID,
                    'time_to_fight' => STARTING_INTERVAL,
                ],
            ]);
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                "status" => "200",
                "message" => "fight_notification",
                "extra" => [
                    'opponent_uuid' => $challengerUUID,
                    'time_to_fight' => STARTING_INTERVAL,
                ],
            ]);

            // Send the success response
            echo json_encode(["status" => 200, "message" => "Fight started successfully."]);

            InventoryController::setConsumableItemAsUsed($pdo, $consumableUUID);

            // Flush the output buffer and send the response immediately
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Ignore user abort and allow the script to continue running
            ignore_user_abort(true);

            // Start the fight after a delay
            sleep(STARTING_INTERVAL);

            // Update the challenge status to 'in_progress'
            $stmt = $pdo->prepare("UPDATE challenges SET status = 'in_progress' WHERE id = ?");
            $stmt->execute([$challengeId]);

            // Send fight start notifications to both players' HUDs
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                "status" => "200",
                "message" => "fight_start",
                "extra" => [
                    'fight_duration' => $fightDurationMinutes,
                ]
            ]);
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                "status" => "200",
                "message" => "fight_start",
                "extra" => [
                    'fight_duration' => $fightDurationMinutes,
                ]
            ]);

            // Set the start time
            $startTime = time();

            // Initialize variables to track warning timers and player statuses
            $challengerWarningTime = null;
            $challengerLastWarningTime = null;
            $challengedWarningTime = null;
            $challengedLastWarningTime = null;

            // Monitor the fight
            while (time() - $startTime < $fightDuration) {
                //error_log("Checking status for $challengerUUID and $challengedUUID" . $startTime . " - " . time());
                // Get the current status of both players
                $challengerStatus = PlayerDataController::getPlayerStatus($pdo, $challengerUUID);
                $challengedStatus = PlayerDataController::getPlayerStatus($pdo, $challengedUUID);

                // Check if both players are alive
                if ($challengerStatus === "alive" && $challengedStatus === "alive") {

                    $remainingTime = $fightDuration - (time() - $startTime);
                    $remainingMinutes = floor($remainingTime / 60);
                    $remainingSeconds = $remainingTime % 60;

                    // Get the current region of both players
                    $challengerResponse = CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                        "status" => "200",
                        "message" => "get_status",
                        "extra" => [
                            "timer" => sprintf("%02d:%02d", $remainingMinutes, $remainingSeconds), 
                        ],
                    ]);
                    $challengerData = json_decode($challengerResponse, true)['response_data'];

                    $challengedResponse = CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                        "status" => "200",
                        "message" => "get_status",
                        "extra" => [
                            "timer" => sprintf("%02d:%02d", $remainingMinutes, $remainingSeconds),
                        ],
                    ]);
                    $challengedData = json_decode($challengedResponse, true)['response_data'];

                    // Check if the challenger is in the starting region
                    if ($challengerData['region'] !== $startingRegion) {
                        // Handle challenger not in the starting region
                        if ($challengerWarningTime === null) {
                            $challengerWarningTime = time();
                            $challengerLastWarningTime = time();
                        }

                        if (time() - $challengerLastWarningTime >= WARNING_INTERVAL) {
                            $remainingTime = max(0, WARNING_TIMEOUT - (time() - $challengerWarningTime));
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                                "status" => "200",
                                "message" => "warning_return_to_starting_region",
                                "extra" => [
                                    "starting_region" => $startingRegion, 
                                    "timer" => $remainingTime
                                ],
                            ]);

                            $challengerLastWarningTime = time();
                        }

                        if ($challengerWarningTime !== null && time() - $challengerWarningTime >= WARNING_TIMEOUT) {
                            // Kill both players if the challenger is not in the starting region
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                                "status" => "200",
                                "message" => "kill_player",
                                "extra" => [
                                    "killed_uuid" => $challengerUUID
                                ],
                            ]);
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                                "status" => "200",
                                "message" => "kill_player",
                                "extra" => ["killed_uuid" => $challengerUUID],
                            ]);

                            Self::killPlayer($pdo, $challengerUUID);
                        }
                    } else {
                        // Reset the warning time and last warning time for the challenger
                        //The Fix now allows 60 seconds to return to the starting region max tiem and it wont reset
                        //$challengerWarningTime = null;
                        $challengerLastWarningTime = null;
                    }

                    // Check if the challenged player is in the starting region
                    if ($challengedData['region'] !== $startingRegion) {
                        // Handle challenged player not in the starting region
                        if ($challengedWarningTime === null) {
                            $challengedWarningTime = time();
                            $challengedLastWarningTime = time();
                        }

                        if (time() - $challengedLastWarningTime >= WARNING_INTERVAL) {
                            $remainingTime = max(0, WARNING_TIMEOUT - (time() - $challengedWarningTime));
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                                "status" => "200",
                                "message" => "warning_return_to_starting_region",
                                "extra" => ["starting_region" => $startingRegion, "timer" => $remainingTime],
                            ]);

                            $challengedLastWarningTime = time();
                        }

                        if ($challengedWarningTime !== null && time() - $challengedWarningTime >= WARNING_TIMEOUT) {
                            // Kill both players if the challenged player is not in the starting region
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                                "status" => "200",
                                "message" => "kill_player",
                                "extra" => ["killed_uuid" => $challengedUUID],
                            ]);
                            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                                "status" => "200",
                                "message" => "kill_player",
                                "extra" => ["killed_uuid" => $challengedUUID],
                            ]);

                            Self::killPlayer($pdo, $challengedUUID);
                        }
                    } else {
                        // Reset the warning time and last warning time for the challenged player
                        //$challengedWarningTime = null;
                        //The Fix now allows 60 seconds to return to the starting region max tiem and it wont reset
                        $challengedLastWarningTime = null;
                    }
                } else {
                    // One or both players are dead, end the fight
                    break;
                }

                // Sleep before the next monitoring iteration
                sleep(WARNING_INTERVAL);
            }

            // Determine the fight result
            $challengerStatus = PlayerDataController::getPlayerStatus($pdo, $challengerUUID);
            $challengedStatus = PlayerDataController::getPlayerStatus($pdo, $challengedUUID);

            if ($challengerStatus === "dead" && $challengedStatus === "dead") {
                // Both players are dead, fight ends in a tie
                $fightResult = "tie";
                $winnerId = null;
                $winnerUUID = null;
            } elseif ($challengerStatus === "alive" && $challengedStatus === "dead") {
                // Challenger wins
                $fightResult = "win";
                $winnerId = $challengerId;
                $winnerUUID = $challengerUUID;
            } elseif ($challengerStatus === "dead" && $challengedStatus === "alive") {
                // Challenged player wins
                $fightResult = "win";
                $winnerId = $challengedId;
                $winnerUUID = $challengedUUID;
            } else {
                // Fight ends in a draw
                $fightResult = "draw";
                $winnerId = null;
                $winnerUUID = null;
            }

            // Update the challenge status and winner in the database
            $stmt = $pdo->prepare("UPDATE challenges SET status = ?, winner_id = ? WHERE id = ?");
            $stmt->execute([$fightResult, $winnerId, $challengeId]);

            if ($winnerUUID === null) {
                $winnerUUID = ""; // this bs fixes the sl not dealing with null values
            }

            // Send fight end notifications to both players' HUDs
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengerUUID, [
                "status" => "200",
                "message" => "fight_end",
                "extra" => ["result" => $fightResult, "winner_uuid" => $winnerUUID],
            ]);
            CommunicationController::sendDataToHUDAndReceiveResponse($pdo, $challengedUUID, [
                "status" => "200",
                "message" => "fight_end",
                "extra" => ["result" => $fightResult, "winner_uuid" => $winnerUUID],
            ]);

        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
        }
    }

    /**
     * Kill a player by setting their status to "dead", updating their current health to 0,
     * and sending a "reset_hud" message to the player's HUD.
     *
     * @param PDO $pdo PDO database connection object
     * @param string $playerUUID UUID of the player
     * @return bool True if the player was killed successfully, false otherwise
     */
    public static function killPlayer($pdo, $playerUUID)
    {
        // Update the player's status to "dead"
        $statusUpdated = PlayerController::updatePlayerStatus($pdo, $playerUUID, "dead");

        //update the player's essence to 0
        $updatePlayerEssence = PlayerDataController::updatePlayerEssence($pdo, $playerUUID, 0.0);
        if ($updatePlayerEssence == "No update made. Player not found or essence unchanged.")
        {
            error_log(" kill player Failed to update player essence for player: " . $playerUUID);
        }

        // Update the player's current health to 0
        $healthUpdated = PlayerDataController::updatePlayerCurrentHealth($pdo, $playerUUID, 0.0);

        if ($statusUpdated && $healthUpdated) {


            $regionName = SecondLifeHeadersStatic::getRegionName();

            $playerID = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);

            // Log the death
            $deathLogResult = DeathLogController::addDeathLogEntry($pdo, $playerID, null, date('Y-m-d H:i:s'), $regionName, 'killed_by_darkness', '1v1 death');
            $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
            if ($decodedDeathLogResult['status'] != 200) {
                error_log("Error updating death log: " . $decodedDeathLogResult['message']);
            }

            // Send a "reset_hud" message to the player's HUD
            $sendToHudResponse = CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, [
                "status" => "200",
                "message" => "reset_hud",
            ]);

            if ($sendToHudResponse->getStatus() == 200) {
                return true;
            } else {
                // Handle the case when sending the HUD message fails
                error_log("Failed to send 'reset_hud' message to player: " . $playerUUID);
                return false;
            }

            
        } else {
            // Handle the case when updating the player's status or health fails
            error_log("Failed to update player status or health for player: " . $playerUUID);
            return false;
        }
    }
}