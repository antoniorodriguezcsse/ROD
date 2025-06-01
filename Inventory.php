<?php
// router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\InventoryController;

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

    case "updatePlayerInventory":
        if (isset($requestData["player_uuid"], $requestData["items"]) && is_array($requestData["items"])) {
            $isVendor = isset($requestData["is_vendor"]) ? $requestData["is_vendor"] : false;
            $donorUUID = isset($requestData["donor_uuid"]) ? $requestData["donor_uuid"] : null;

            $response = InventoryController::updatePlayerInventory($pdo, $requestData["player_uuid"], $requestData["items"], $isVendor, $donorUUID);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for updatePlayerInventory.");
        }
        break;

    case "transferItemBetweenPlayers":
        if (isset($requestData["donor_uuid"], $requestData["recipient_uuid"], $requestData["item"], $requestData["quantity"])) {
            $response = InventoryController::transferItemBetweenPlayers($pdo, $requestData["donor_uuid"], $requestData["recipient_uuid"], $requestData["item"], $requestData["quantity"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for item transfer.");
        }
        break;

    case "registerConsumableItem":
        if (isset($requestData["player_uuid"], $requestData["consumable_uuid"], $requestData["consumable_type"])) {
            $playerUUID = $requestData["player_uuid"];
            $consumableUuid = $requestData["consumable_uuid"];
            $consumableType = $requestData["consumable_type"];

            $response = InventoryController::registerConsumableItem($pdo, $playerUUID, $consumableUuid, $consumableType);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for registering consumable item.");
        }
        break;

    case "checkAndUpdateConsumableItemOwner":
        if (isset($requestData["consumable_uuid"], $requestData["player_uuid"])) {
            $consumableUuid = $requestData["consumable_uuid"];
            $playerUuid = $requestData["player_uuid"];

            $response = InventoryController::checkIfConsumableUsedAndUpdateOwner($pdo, $consumableUuid, $playerUuid);
            echo $response; // Assuming $response already handles JSON formatting
        } else {
            echo new JsonResponse(400, "Invalid request data for updating consumable owner.");
        }
        break;
        
    case "deleteConsumable":
        if (isset($requestData["consumable_uuid"])) {
            $response = InventoryController::deleteConsumable($pdo, $requestData["consumable_uuid"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for deleteConsumable.");
        }
        break;

    default:
        echo new JsonResponse(400, "Invalid action.");
}
