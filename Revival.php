<?php
// Comment out when not needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Controllers\RevivalController;
use Fallen\SecondLife\Controllers\TableController;
use Fallen\SecondLife\Helpers\IntegrityChecker;
use Fallen\SecondLife\Controllers\InventoryController;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$secret = "iliketoeattacosandburritosat3819willowpassroad";

switch ($action)
{

    case "revivePlayerPotion":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        $data = json_decode($raw, true);

        if ($data !== null)
        {
            $playerUUID = $data["player_uuid"] ?? null;
            $consumableUuid = $data["consumable_uuid"] ?? null; // Assuming this is passed in the request

            if ($playerUUID && $consumableUuid)
            {
                // Revive the player
                $reviveResponse = RevivalController::revivePlayer($pdo, $playerUUID, $revivalMethod = "potion");


                // Decode the JSON response
                $reviveResponseData = json_decode($reviveResponse->__toString(), true);

                // Check if the player was revived successfully
                if ($reviveResponseData['status'] == 200 && $reviveResponseData['message'] == "Player revived successfully.")
                {
                    // Mark the consumable item as used
                    $consumableResponse = InventoryController::setConsumableItemAsUsed($pdo, $consumableUuid);
                }

                echo $reviveResponse;
                CommunicationController::sendDataToPlayersHUD($pdo, $playerUUID, json_decode($reviveResponse));
            } else
            {
                echo new JsonResponse(400, "Incomplete data received.");
            }
        } else
        {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;







    case "getReviverPlayerInfo":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];

            RevivalController::getReviverPlayerInfo($pdo, $playerUUID);
        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "getDeadPlayerInfo":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];

            RevivalController::getDeadPlayerInfo($pdo, $playerUUID);
        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    // Revive Player using Altar.
    case "revivePlayerAltar":
        // Uncomment the following lines in production to ensure data integrity
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }
        $data = json_decode($raw, true);
        if ($data !== null)
        {
            $deadPlayerUUID = $data["dead_player_uuid"] ?? null;
            $reviverUUID = $data["reviver_uuid"] ?? null;

            if ($deadPlayerUUID && $reviverUUID)
            {
                // Use the updated revivePlayer function
                $result = RevivalController::revivePlayerAltar($pdo, $deadPlayerUUID, $reviverUUID);
                //Update the blood bar
                CommunicationController::sendDataToPlayersHUD($pdo, $deadPlayerUUID, json_decode($result));
                echo $result;
                // Additional logic...
            } else
            {
                echo new JsonResponse(400, "Incomplete data received.");
            }
        } else
        {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "checkIfPlayer":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];

            $result = PlayerDataController::doesPlayerExist($pdo, $playerUUID);
            if ($result)
            {
                $status = PlayerDataController::getPlayerStatus($pdo, $playerUUID);
                if ($status === "alive")
                {
                    echo new JsonResponse(404, "player is alive", $decodedDetails);
                }
            } else
            {
                echo new JsonResponse(404, "uuid not found in database", $decodedDetails);
            }
        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "checkIfPlayerHasRequiredEssence":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];

            // $result = PlayerDataController::doesPlayerExist($pdo, $playerUUID);
            // if ($result) {
            $status = PlayerDataController::getPlayerStatus($pdo, $playerUUID);

            if ($status === "dead")
            {
                echo new JsonResponse(404, "player is dead", $decodedDetails);
            } else if ($status === "alive")
            {
                $totalEssence = PlayerDataController::getPlayerEssenceCount($pdo, $playerUUID);
                if ($totalEssence >= 2.0)
                {
                    echo new JsonResponse(200, "has enough essence", ["total_essence" => $totalEssence]);
                } else
                {
                    echo new JsonResponse(404, "player does not have enough essence", ["total_essence" => $totalEssence]);
                }
            }
            // } else {
            //     echo new JsonResponse(404, "uuid not found in database", $decodedDetails);
            // }
        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "takeEssence":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];
            $remainingEssence = $requestData["remaining_essence"];
            $result = TableController::updateData($pdo, "players", $playerUUID, ["player_essence" => $remainingEssence]);
            echo $result;

        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "checkIfHumanAndDead":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["human_uuid"]))
        {
            $humanUUID = $requestData["human_uuid"];

            // Use the checkIfHumanExistsAndDead function
            $result = RevivalController::checkIfHumanExistsAndDead($pdo, $humanUUID);
            echo $result;
        } else
        {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "reviveHumanPotion":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["human_uuid"], $requestData["consumable_uuid"]))
        {
            $humanUUID = $requestData["human_uuid"];
            $consumableUuid = $requestData["consumable_uuid"];

            // Use the reviveHuman function
            $result = RevivalController::reviveHuman($pdo, $humanUUID, $consumableUuid);
            echo $result;
        } else
        {
            echo new JsonResponse(400, "Error processing request data: human_uuid and consumable_uuid are required.");
        }
        break;

    case "setConsumableItemAsUsed":
        if (isset($requestData["consumable_uuid"]))
        {
            $consumableUuid = $requestData["consumable_uuid"];

            $response = InventoryController::setConsumableItemAsUsed($pdo, $consumableUuid);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Invalid request data for setting consumable item as used.");
        }
        break;

}
