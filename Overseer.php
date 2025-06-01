<?php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\OverseerController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup

$secret = "iliketoeattacosandburritosat3819willowpassroad";

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$requestData = json_decode($raw, true);

if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
    die(new JsonResponse(400, "INVALID_REQUEST"));
}

switch ($action) {

    case "registerContainer":
        if (!isset($requestData["owner_uuid"], $requestData["overseer_uuid"], $requestData["item_type"], $requestData["version_number"])) {
            echo new JsonResponse(400, "Missing required data for registerContainer.");
            break;
        }

        $versionNumber = $requestData["version_number"];
        $ownerUUID = $requestData["owner_uuid"];
        $overseerUUID = $requestData["overseer_uuid"];

        try {

            // Register the overseer
            $registerResult = OverseerController::registerOverseer($pdo, $overseerUUID, $ownerUUID, $versionNumber);
            echo $registerResult;

        } catch (Exception $e) {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID,
                $e->getMessage(), basename(__FILE__));

            error_log("Error: " . $e->getMessage());
            echo new JsonResponse(500, "Database error: Contact an admin.");
            // // Handle any exceptions that occur during the process
            // echo json_encode([
            //     "status" => "error",
            //     "message" => $e->getMessage(),
            // ]);
        }
        break;

    case "checkAndAssignOwner":
        // if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
        //     die(new JsonResponse(400, "INVALID_REQUEST"));
        // }

        if (isset($requestData["owner_uuid"], $requestData["overseer_uuid"])) {
            $overseerUUID = $requestData["overseer_uuid"];
            $ownerUUID = $requestData["owner_uuid"];

            try {
                // Get the player ID by UUID
                $ownerId = PlayerDataController::getPlayerIdByUUID($pdo, $ownerUUID);

                // Call the checkAndAssignOwner function
                $response = OverseerController::checkAndAssignOwner($pdo, $overseerUUID, $ownerId);

                // Output the response
                echo $response;

            } catch (PDOException $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, $e->getMessage(), basename(__FILE__));
                error_log("Error: " . $e->getMessage());

                echo new JsonResponse(500, "Database error: Contact an admin.");
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, $e->getMessage(), basename(__FILE__));

                error_log("Error: " . $e->getMessage());
                echo new JsonResponse(500, "Database error: Contact an admin.");
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, "Invalid request data for checking and assigning owner.", basename(__FILE__));
            echo new JsonResponse(400, "Invalid request data for checking and assigning owner.");
        }
        break;

    case "deleteContainer":
        if (isset($requestData["overseer_uuid"], $requestData["owner_uuid"])) {
            $response = OverseerController::deleteFromDatabase($pdo, $requestData["overseer_uuid"], $requestData["owner_uuid"]);
            echo $response;
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, "Invalid request data for registering consumable item.", basename(__FILE__));
            echo new JsonResponse(400, "Invalid request data for delete container.");
        }
        break;

    case "updateOverseerUrl":
        if (isset($requestData["overseer_uuid"]) && isset($requestData["overseer_url"]) && isset($requestData["owner_uuid"])) {
            $overseerUUID = $requestData["overseer_uuid"];
            $overseerURL = $requestData["overseer_url"];
            $ownerUUID = $requestData["owner_uuid"];
            $counter = 0;
            $online = 1;

            try {
                // Prepare the update query to set overseer_url, is_online, and offline_counter
                $updateQuery = $pdo->prepare("UPDATE overseer
                        SET overseer_url = :overseer_url, is_online = :is_online, offline_counter = :offline_counter
                        WHERE overseer_uuid = :overseer_uuid");

                // Execute the update query with bound values
                $updateQuery->execute([
                    ':overseer_url' => $overseerURL,
                    ':is_online' => $online,
                    ':offline_counter' => $counter,
                    ':overseer_uuid' => $overseerUUID,
                ]);

                echo new JsonResponse(200, "Overseer updated successfully.");

            } catch (PDOException $e) {
                // Handle database errors
                echo new JsonResponse(500, "Database error: " . $e->getMessage());
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID,"An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                // Handle unexpected exceptions
                echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, "Missing required data for updateOverseerUrl.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data for updateOverseerUrl.");
        }

        break;

    case "getRecipientBloodInfo":
        if (isset($requestData["overseer_uuid"]) && isset($requestData["donors_uuid"]) && isset($requestData["version_number"])) {
            $ownerUUID = $requestData["donors_uuid"];
            $overseerUUID = $requestData["overseer_uuid"];

            // Fetch version number from the database
            $versionQuery = $pdo->prepare("SELECT version_number FROM version WHERE version_name = 'overseer'");
            $versionQuery->execute();
            $versionNumberFromDb = $versionQuery->fetchColumn();

            if (!$versionNumberFromDb) {
                CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID,"Error fetching version number from the database in getRecipientBloodInfo.", basename(__FILE__));
                // Handle case where the version number was not found
                die(new JsonResponse(500, "Error fetching version number from the database."));
            }

            // Check if the version number from the request matches the one in the database
            if ($requestData["version_number"] !== $versionNumberFromDb) {
                // Version numbers don't match, so abort the operation
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["donors_uuid"],"Version number mismatch in getRecipientBloodInfo.", basename(__FILE__));
                die(new JsonResponse(400, "Version number mismatch."));
            }

            // Process the blood transfer if the version is valid
            OverseerController::processBloodTransfer($requestData, $pdo);
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, "Missing required data in getRecipientBloodInfo.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data.");
        }

        break;

    case "addPlayerToTrack":
        $theirUUID = $requestData["their_UUID"];
        $OverseerUUID = $requestData["overseer_uuid"];
        $myUUID = $requestData["my_uuid"];
        if (isset($theirUUID, $OverseerUUID, $myUUID)) {
            if (!PlayerDataController::doesPlayerExist($pdo, $theirUUID)) {
                echo new JsonResponse(403, "Not a player.");
                return;
            }

            if (!PlayerDataController::validatePlayerForGame($pdo, $myUUID)) {
                $playersStatus = PlayerDataController::getPlayersCurrentStatus($pdo, $myUUID);
                echo new JsonResponse(403, "Owner is dead.", ["player_status" => $playersStatus]);
                return;
            }

            $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $theirUUID);
            echo OverseerController::addPlayerToTrack($pdo, $myUUID, $OverseerUUID, $theirID);
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $myUUID, "Missing required data in addPlayerToTrack.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data for addPlayerToTrack."); // Handle case where required data is missing
        }
        break;

    case "updateBloodInformation":
        OverseerController::handleBloodTransaction($pdo, $requestData);
        break;

    case "printAttackLog":
        OverseerController::printAttackLogs($pdo, $requestData);
        break;

    case "printKillLogs":
        OverseerController::printKillLogs($pdo, $requestData);
        break;

    case "printDeathLogs":
        OverseerController::printDeathLogs($pdo, $requestData);
        break;

    case "attackAlert":

        if (isset($requestData["attack_alert"]) && isset($requestData["owner_uuid"]) && isset($requestData["overseer_uuid"])) {
            $attackAlertStatus = $requestData["attack_alert"];
            $overseerUUID = $requestData["overseer_uuid"];

            try {
                // Set the notify_attack value based on the status
                $notifyAttackValue = ($attackAlertStatus == "turn off") ? 0 : 1;

                // Prepare the SQL query
                $query = "UPDATE overseer SET notify_attack = :notify_attack WHERE overseer_uuid = :overseer_uuid";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':notify_attack', $notifyAttackValue, PDO::PARAM_INT);
                $stmt->bindParam(':overseer_uuid', $overseerUUID, PDO::PARAM_STR);

                // Execute the query
                if ($stmt->execute()) {
                    echo new JsonResponse(200, "Notification status updated successfully.");
                } else {
                    CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Failed to update notification status for attack alert.", basename(__FILE__));
                    echo new JsonResponse(500, "Failed to update notification status for attack alert.");
                }
            } catch (PDOException $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"Database error: " . $e->getMessage(), basename(__FILE__));
                // Handle database errors
                echo new JsonResponse(500, "Database error: " . $e->getMessage());
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                // Handle unexpected exceptions
                echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Missing required data for attackAlert.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data.");
        }

        break;

    case "updateWebhook":
        if (isset($requestData["webhook"]) && isset($requestData["overseer_uuid"]) && isset($requestData["owner_uuid"])) {
            $webhook = $requestData["webhook"];
            $overseerUUID = $requestData["overseer_uuid"];
            $ownerUUID = $requestData["owner_uuid"];

            // Verify if the webhook URL is valid
            if (filter_var($webhook, FILTER_VALIDATE_URL) && preg_match('/^https:\/\/discord(?:app)?\.com\/api\/webhooks\/\d+\/[\w-]+$/', $webhook)) {
                try {
                    // Update the webhook field in the overseer table
                    $query = "UPDATE overseer SET webhook = :webhook WHERE overseer_uuid = :overseer_uuid";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':webhook', $webhook, PDO::PARAM_STR);
                    $stmt->bindParam(':overseer_uuid', $overseerUUID, PDO::PARAM_STR);

                    if ($stmt->execute()) {
                        echo new JsonResponse(200, "Webhook URL updated successfully.");
                    } else {
                        CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID,"Failed to update webhook URL.", basename(__FILE__));
                        echo new JsonResponse(500, "Failed to update webhook URL.");
                    }
                } catch (PDOException $e) {
                    CommunicationController::logErrorToDiscordByPlayerUUID($pdo,$ownerUUID,"Database error: " . $e->getMessage(),basename(__FILE__));
                    echo new JsonResponse(500, "Database error: " . $e->getMessage());
                } catch (Exception $e) {
                    CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID, "An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                    echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
                }
            } else {
                CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $ownerUUID,"Invalid webhook URL provided.", basename(__FILE__));
                echo new JsonResponse(400, "Invalid webhook URL.");
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo,$requestData["owner_uuid"], "Missing required data for updating webhook.", basename(__FILE__) );
            echo new JsonResponse(400, "Missing required data.");
        }
        break;

    case "lowBloodAlert":

        if (isset($requestData["low_blood_alert"]) && isset($requestData["owner_uuid"]) && isset($requestData["overseer_uuid"])) {
            $lowBloodAlertStatus = $requestData["low_blood_alert"];
            $overseerUUID = $requestData["overseer_uuid"];

            try {
                // Set the notify_near_death value based on the status
                $notifyNearDeathValue = ($lowBloodAlertStatus == "turn off") ? 0 : 1;

                // Prepare the SQL query
                $query = "UPDATE overseer SET notify_near_death = :notify_near_death WHERE overseer_uuid = :overseer_uuid";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':notify_near_death', $notifyNearDeathValue, PDO::PARAM_INT);
                $stmt->bindParam(':overseer_uuid', $overseerUUID, PDO::PARAM_STR);

                // Execute the query
                if ($stmt->execute()) {
                    echo new JsonResponse(200, "Notification status updated successfully.");
                } else {
                    CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Failed to update notification status for lowBloodAlert.", basename(__FILE__));
                    echo new JsonResponse(500, "Failed to update notification status.");
                }
            } catch (PDOException $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"Database error: " . $e->getMessage(), basename(__FILE__));
                // Handle database errors
                echo new JsonResponse(500, "Database error: " . $e->getMessage());
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                // Handle unexpected exceptions
                echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Failed to update notification status for lowBloodAlert. Missing required data.", basename(__FILE__));
            echo new JsonResponse(400, "Failed to update notification status. Missing required data.");
        }

        break;

    case "setDaysTillDeathAlert":
        if (isset($requestData["overseer_uuid"]) && isset($requestData["value"]) && isset($requestData["owner_uuid"])) {
            $overseerUUID = $requestData["overseer_uuid"];
            $value = $requestData["value"];
            try {
                // Prepare the update query to set the notification_limit
                $updateQuery = $pdo->prepare("UPDATE overseer
                                                  SET days_till_death_notification = :value
                                                  WHERE overseer_uuid = :overseer_uuid");

                // Execute the update query with bound values
                $updateQuery->execute([
                    ':value' => $value,
                    ':overseer_uuid' => $overseerUUID,
                ]);

                echo new JsonResponse(200, "Notification limit set successfully.");

            } catch (PDOException $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"Database error: " . $e->getMessage(), basename(__FILE__));
                // Handle database errors
                echo new JsonResponse(500, "Database error: " . $e->getMessage());
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                // Handle unexpected exceptions
                echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Missing required data for setDaysTillDeathAlert.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data.");
        }

        break;

    case "clearPlayerData":

        if (isset($requestData["overseer_uuid"]) && isset($requestData["owner_uuid"])) {
            $overseerUUID = $requestData["overseer_uuid"];

            try {
                // Prepare the update query to clear tracked_player_id
                $updateQuery = $pdo->prepare("UPDATE overseer
                SET tracked_player_id = NULL
                WHERE overseer_uuid = :overseer_uuid");

                // Execute the update query with bound value
                $updateQuery->execute([
                    ':overseer_uuid' => $overseerUUID,
                ]);

                echo new JsonResponse(200, "Player data cleared successfully.");

            } catch (PDOException $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"Database error: " . $e->getMessage(), basename(__FILE__));
                // Handle database errors
                echo new JsonResponse(500, "Database error: " . $e->getMessage());
            } catch (Exception $e) {
                CommunicationController::logErrorToDiscordByPlayerUUID( $pdo, $requestData["owner_uuid"],"An unexpected error occurred: " . $e->getMessage(), basename(__FILE__));
                // Handle unexpected exceptions
                echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
            }
        } else {
            CommunicationController::logErrorToDiscordByPlayerUUID($pdo, $requestData["owner_uuid"], "Missing required data for clear player data clearPlayerData.", basename(__FILE__));
            echo new JsonResponse(400, "Missing required data.");
        }

        break;

    case "getOverseerOwners":
        OverseerController::getOwnersOfOverseers($pdo, $requestData);
        break;

    case "removeTrackedPlayers":

        if (isset($requestData["owner_uuid"], $requestData["their_uuid"])) {
            $ownerUUID = $requestData["owner_uuid"];
            $theirUUID = $requestData["their_uuid"];

            // Convert UUIDs to player IDs
            $ownerID = PlayerDataController::getPlayerIdByUUID($pdo, $ownerUUID);
            $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $theirUUID);

            if ($ownerID && $theirID) {
                try {
                    // Prepare the update query
                    $updateQuery = $pdo->prepare("
                            UPDATE overseer
                            SET tracked_player_id = NULL
                            WHERE owner_id = :owner_id AND tracked_player_id = :tracked_player_id
                        ");

                    // Execute the update query with bound values
                    $updateQuery->execute([
                        ':owner_id' => $ownerID,
                        ':tracked_player_id' => $theirID,
                    ]);

                    if ($updateQuery->rowCount() > 0) {
                        echo new JsonResponse(200, "Tracked players successfully removed.", ["owner_uuid" => $ownerUUID]);
                    } else {
                        echo new JsonResponse(404, "No tracked players found for the specified owner and player.");
                    }
                } catch (PDOException $e) {
                    // Handle database errors
                    echo new JsonResponse(500, "Database error: " . $e->getMessage());
                } catch (Exception $e) {
                    // Handle unexpected exceptions
                    echo new JsonResponse(500, "An unexpected error occurred: " . $e->getMessage());
                }
            } else {
                echo new JsonResponse(400, "Invalid owner or player UUID.");
            }
        } else {
            echo new JsonResponse(400, "Missing required data.");
        }

        break;

    default:
        echo new JsonResponse(400, "Invalid action.");
        break;
}
