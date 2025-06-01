<?php
// router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\EssenceContainerController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup

$secret = "iliketoeattacosandburritosat3819willowpassroad";

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

// if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
//     die(new JsonResponse(400, "INVALID_REQUEST"));
// }

$requestData = json_decode($raw, true);

switch ($action) {

    case "registerContainer":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        if (isset($requestData["player_uuid"], $requestData["essence_container_uuid"], $requestData["item_type"], $requestData["capacity"], $requestData["owner_name"])) {
            $playerUUID = $requestData["player_uuid"];
            $containerUuid = $requestData["essence_container_uuid"];
            $item = $requestData["item_type"];
            $capacity = $requestData["capacity"];
            $ownerName = $requestData["owner_name"];

            $response = EssenceContainerController::registerEssenceContainer($pdo, $playerUUID, $containerUuid, $item, $capacity, $ownerName);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for registering consumable item.");
        }

        break;

    case "depositEssence":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        if (isset($requestData["player_uuid"], $requestData["essence_to_deposit"], $requestData["essence_container_uuid"])) {
            $playerUUID = $requestData["player_uuid"];
            $amountOfEssenceToDeposit = $requestData["essence_to_deposit"];
            $containerUUID = $requestData["essence_container_uuid"];
            $result = PlayerDataController::getPlayerStatus($pdo, $playerUUID);
            //  withdrawHttpRequest
            if ($result == "alive") {
                $totalEssence = PlayerDataController::getPlayerEssence($pdo, $playerUUID);
                if ($totalEssence > 0) {
                    if ($amountOfEssenceToDeposit > $totalEssence) {
                        echo new JsonResponse(422, "Does not have that many essence.");
                    } else {
                        $result = EssenceContainerController::depositEssenceInContainer($pdo, $playerUUID, $amountOfEssenceToDeposit, $containerUUID);
                        echo $result;
                    }
                } else {
                    echo new JsonResponse(422, "Does not have any essence.");
                }
            } else {
                echo new JsonResponse(200, "Player is not alive.");
            }
        }
        break;

    case "withdrawEssence":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        if (isset($requestData["player_uuid"], $requestData["essence_to_withdraw"], $requestData["essence_container_uuid"])) {
            $playerUUID = $requestData["player_uuid"];
            $amountOfEssenceToWithdraw = $requestData["essence_to_withdraw"];
            $containerUUID = $requestData["essence_container_uuid"];
            $result = PlayerDataController::getPlayerStatus($pdo, $playerUUID);

            if ($result == "alive") {
                echo EssenceContainerController::withdrawEssenceFromContainer($pdo, $playerUUID, $amountOfEssenceToWithdraw, $containerUUID);
            } else {
                echo new JsonResponse(200, "Player is not alive.");
            }
        }
        break;

    case "checkAndAssignOwner":
        if (isset($requestData["essence_container_uuid"], $requestData["player_uuid"], $requestData["owner_name"])) {
            $consumableUuid = $requestData["essence_container_uuid"];
            $playerUuid = $requestData["player_uuid"];
            $ownerName = $requestData["owner_name"]; 
            $response = EssenceContainerController::checkAndAssignOwner($pdo, $consumableUuid, $playerUuid, $ownerName);
            echo $response; // Assuming $response already handles JSON formatting
        } else {
            echo new JsonResponse(400, "Invalid request data for updating consumable owner.");
        }
        break;

    case "deleteContainer":
        if (isset($requestData["essence_container_uuid"])) {
            $response = EssenceContainerController::deleteFromDatabase($pdo, $requestData["essence_container_uuid"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for delete container.");
        }
        break;

    default:
        echo new JsonResponse(400, "Invalid action.");
}
