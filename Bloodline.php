<?php
require_once "./vendor/autoload.php"; // or whatever your autoloader is
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\BloodlineController;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"] ?? null;
$raw = file_get_contents("php://input");
$requestData = json_decode($raw, true);

switch ($action)
{

    case "retrieveBloodlineDetailsForPlayer":
        if (isset($requestData["player_uuid"]))
        {
            $playerUUID = $requestData["player_uuid"];
            $response = BloodlineController::fetchBloodlineDetailsForPlayer($pdo, $playerUUID);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Invalid request data. 'player_uuid' is required.");
        }
        break;

    case "renameBloodline":
        if (isset($requestData["player_uuid"], $requestData["new_name"]))
        {
            $playerUUID = $requestData["player_uuid"];
            $newName = $requestData["new_name"];
            $response = BloodlineController::renameBloodline($pdo, $playerUUID, $newName);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Invalid request data. 'player_uuid' and 'new_name' required.");
        }
        break;

    case "addClanToBloodline":
        if (isset($requestData["requesting_player_uuid"], $requestData["clan_owner_uuid"]))
        {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $clanOwnerUUID = $requestData["clan_owner_uuid"];
            $response = BloodlineController::addClanToBloodline($pdo, $requestingPlayerUUID, $clanOwnerUUID);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Invalid request data. 'requesting_player_uuid' and 'clan_owner_uuid' required.");
        }
        break;

    case "removeClanFromBloodline":
        if (isset($requestData["requesting_player_uuid"], $requestData["clan_owner_uuid"]))
        {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $clanOwnerUUID = $requestData["clan_owner_uuid"];
            $response = BloodlineController::removeClanFromBloodline($pdo, $requestingPlayerUUID, $clanOwnerUUID);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Invalid request data. 'requesting_player_uuid' and 'clan_owner_uuid' required.");
        }
        break;


    case "promoteBloodlineMember":
        if (isset($requestData["requesting_player_uuid"], $requestData["target_player_uuid"], $requestData["new_role_keyword"]))
        {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $targetPlayerUUID = $requestData["target_player_uuid"];
            $newRoleKeyword = $requestData["new_role_keyword"];
            $response = BloodlineController::promoteBloodlineMember($pdo, $requestingPlayerUUID, $targetPlayerUUID, $newRoleKeyword);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Missing requesting_player_uuid, target_player_uuid, new_role_keyword.");
        }
        break;

    case "demoteBloodlineMember":
        if (isset($requestData["requesting_player_uuid"], $requestData["target_player_uuid"]))
        {
            $requestingPlayerUUID = $requestData["requesting_player_uuid"];
            $targetPlayerUUID = $requestData["target_player_uuid"];
            $response = BloodlineController::demoteBloodlineMember($pdo, $requestingPlayerUUID, $targetPlayerUUID);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "Missing requesting_player_uuid, target_player_uuid.");
        }
        break;

    case "retrieveClansInBloodline":
        if (isset($requestData["player_uuid"], $requestData["url"]))
        {
            $playerUUID = $requestData["player_uuid"];
            $url = $requestData["url"];
            $batchSize = isset($requestData["batchSize"]) ? (int) $requestData["batchSize"] : 10;
            $response = BloodlineController::getClansInBloodlineWithDetails($pdo, $playerUUID, $url, $batchSize);
            echo $response;
        } else
        {
            echo new JsonResponse(400, "player_uuid and url required.");
        }
        break;


    // ...
    default:
        echo new JsonResponse(400, "Invalid or missing action for bloodline requests.");
        break;
}
