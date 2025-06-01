<?php
// Uncomment for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\ClanController;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"];
$raw = file_get_contents("php://input");
$requestData = json_decode($raw, true); // Decode JSON request data

switch ($action) {

    case "retrieveClanDetailsForPlayer":
        if (isset($requestData["player_uuid"])) {
            $playerUUID = $requestData["player_uuid"];
            $response = ClanController::fetchClanDetailsForPlayer($pdo, $playerUUID);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. 'player_uuid' is required.");
        }
        break;

    case "renameClan":
        if (isset($requestData["player_uuid"]) && isset($requestData["new_name"])) {
            $playerUUID = $requestData["player_uuid"];
            $newName = $requestData["new_name"];
            $response = ClanController::renameClan($pdo, $playerUUID, $newName);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. 'player_uuid' and 'new_name' are required.");
        }
        break;

    case "addHouseToClan":
        if (isset($requestData["requesting_player_uuid"]) && isset($requestData["house_owner_uuid"])) {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $houseOwnerUUID = $requestData["house_owner_uuid"];
            // Optionally, you can also include checks to ensure these UUIDs are valid or formatted correctly.

            $response = ClanController::addHouseToClan($pdo, $requestingPlayerUUID, $houseOwnerUUID);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. Required: requesting_player_uuid and house_owner_uuid.");
        }
        break;

    case "removeHouseFromClan":
        if (isset($requestData["requesting_player_uuid"]) && isset($requestData["house_owner_uuid"])) {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $houseOwnerUUID = $requestData["house_owner_uuid"];
            // Optionally, you can also include checks to ensure these UUIDs are valid or formatted correctly.

            $response = ClanController::removeHouseFromClan($pdo, $requestingPlayerUUID, $houseOwnerUUID);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. Required: requesting_player_uuid and house_owner_uuid.");
        }
        break;

    case "promoteClanMember":
        if (isset($requestData["requesting_player_uuid"]) && isset($requestData["target_player_uuid"]) && isset($requestData["new_role_keyword"])) {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $targetPlayerUUID = $requestData["target_player_uuid"];
            $newRoleKeyword = $requestData["new_role_keyword"];

            $response = ClanController::promoteClanMember($pdo, $requestingPlayerUUID, $targetPlayerUUID, $newRoleKeyword);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. Required: requesting_player_uuid, target_player_uuid, and new_role_keyword.");
        }
        break;

    case "demoteClanMember":
        if (isset($requestData["requesting_player_uuid"]) && isset($requestData["target_player_uuid"])) {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $targetPlayerUUID = $requestData["target_player_uuid"];

            $response = ClanController::demoteClanMember($pdo, $requestingPlayerUUID, $targetPlayerUUID);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. Required: requesting_player_uuid and target_player_uuid.");
        }
        break;

    case "retrieveHousesInClan":
        
        if (isset($requestData["player_uuid"]) && isset($requestData["url"])) {
            $houseOwnerUUID = $requestData["player_uuid"];
            $url = $requestData["url"];
            $batchSize = isset($requestData["batchSize"]) ? $requestData["batchSize"] : 10; // Default batch size

            $response = ClanController::getHousesInClanWithDetails($pdo, $houseOwnerUUID, $url, $batchSize);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data. Required: houseOwnerUUID and url.");
        }
        break;

    // ... Other cases ...

    default:
        echo new JsonResponse(400, "Invalid action.");
}

// ... Rest of the script ...
