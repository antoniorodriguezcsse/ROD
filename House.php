<?php
// Comment out when not needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\HouseController;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$secret = "iliketoeattacosandburritosat3819willowpassroad";

switch ($action) {

    case "retrieveHouseAndClanDetailsForPlayer":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"])) {
            $playerUUID = $requestData["player_uuid"];
            $houseAndClanDetails = HouseController::fetchHouseDetailsForPlayer($pdo, $playerUUID);
            $decodedDetails = json_decode($houseAndClanDetails, true);

            if (isset($decodedDetails["error"])) {
                echo new JsonResponse(404, $decodedDetails["error"]);
            } else {
                echo new JsonResponse(200, "Success", $decodedDetails);
            }
        } else {
            echo new JsonResponse(400, "Error processing request data.");
        }
        break;

    case "renameHouse":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["house_id"]) && isset($requestData["new_name"])) {
            $houseId = $requestData["house_id"];
            $newName = $requestData["new_name"];
            $response = HouseController::renameHouse($pdo, $houseId, $newName);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "addMemberToHouse":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: playerUUID, houseID, and invitingPlayerUUID
        if ($requestData !== null && isset($requestData["playerUUID"]) && isset($requestData["houseID"]) && isset($requestData["invitingPlayerUUID"])) {
            $playerUUID = $requestData["playerUUID"]; // This is the invited player UUID
            $houseID = $requestData["houseID"];
            $invitingPlayerUUID = $requestData["invitingPlayerUUID"]; // This is the inviter's player UUID

            // Execute the addMemberToHouse function from the HouseController
            $response = HouseController::addMemberToHouse($pdo, $invitingPlayerUUID, $playerUUID, $houseID);

            // Since $response is already a JsonResponse, just echo it
            echo $response;
        } else {
            // The request data is missing required fields, send an error JsonResponse
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case 'removeMemberFromHouse':
        $raw = file_get_contents("php://input");
        $requestData = json_decode($raw, true);

        if (!isset($requestData['initiatorUUID'], $requestData['playerUUID'], $requestData['houseID'])) {
            echo new JsonResponse(400, "Invalid request data.");
            break;
        }

        $initiatorUUID = $requestData['initiatorUUID'];
        $playerUUID = $requestData['playerUUID'];
        $houseID = $requestData['houseID'];

        // Call the remove function, passing the $pdo instance
        $response = HouseController::removeMemberFromHouse($pdo, $initiatorUUID, $playerUUID, $houseID);

        // Echo the JsonResponse directly
        echo $response;
        break;

    case "setOfficerForHouse":
        $requestData = json_decode($raw, true);
        if (
            $requestData !== null &&
            isset($requestData["playerUUID"]) &&
            isset($requestData["houseID"]) &&
            isset($requestData["officerPosition"])
        ) {

            $playerUUID = $requestData["playerUUID"];
            $houseID = $requestData["houseID"];
            $officerPosition = $requestData["officerPosition"];

            // Set the officer for the house
            $response = HouseController::setOfficerForHouse($pdo, $playerUUID, $houseID, $officerPosition);

            // Convert the response array to JsonResponse and echo it
            echo new JsonResponse($response['status'], $response['message']);
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "demoteOfficer":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["playerUUID"]) && isset($requestData["houseID"])) {
            $playerUUID = $requestData["playerUUID"];
            $houseID = $requestData["houseID"];

            // Demote the officer
            $response = HouseController::demoteOfficer($pdo, $playerUUID, $houseID);

            // Check if there was a database error and include error details if present
            $status = $response['status'];
            $message = $response['message'];
            $extra = isset($response['extra']) ? $response['extra'] : null;

            // Convert the response array to JsonResponse and echo it
            echo new JsonResponse($status, $message, $extra);
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "printHouseMembersList":
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: houseID and url
        if ($requestData !== null && isset($requestData["houseID"]) && isset($requestData["url"])) {
            $houseID = $requestData["houseID"];
            $url = $requestData["url"];
            $batchSize = isset($requestData["batchSize"]) ? $requestData["batchSize"] : 50; // Use default batch size if not provided

            $response = HouseController::printHouseMembersList($pdo, $houseID, $url, $batchSize);

            // Send the response (either success or error) back to the client using JsonResponse
            echo new JsonResponse($response['status'], $response['message']);
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "removeHouseFromClan":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: playerUUID and houseID
        if ($requestData !== null && isset($requestData["playerUUID"]) && isset($requestData["houseID"])) {
            $playerUUID = $requestData["playerUUID"];
            $houseID = $requestData["houseID"];

            // Execute the removeHouseFromClan function from the HouseController
            $response = HouseController::removeHouseFromClan($pdo, $playerUUID, $houseID);

            // Echo the JsonResponse directly
            echo $response;
        } else {
            // The request data is missing required fields
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "promoteToOwner":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: currentOwnerUUID, newOwnerUUID, and houseID
        if ($requestData !== null && isset($requestData["currentOwnerUUID"]) && isset($requestData["newOwnerUUID"]) && isset($requestData["houseID"])) {
            $currentOwnerUUID = $requestData["currentOwnerUUID"];
            $newOwnerUUID = $requestData["newOwnerUUID"];
            $houseID = $requestData["houseID"];

            // Execute the promoteToOwner function from the HouseController
            $response = HouseController::promoteToOwner($pdo, $currentOwnerUUID, $newOwnerUUID, $houseID);

            // Send the response (either success or error) back to the client
            echo new JsonResponse($response['status'], $response['message']);
        } else {
            // The request data is missing required fields, send an error response
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "leaveHouse":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary field: playerUUID
        if ($requestData !== null && isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];

            // Execute the leaveHouse function from the HouseController
            $response = HouseController::leaveHouse($pdo, $playerUUID);

            // Since $response is already a JsonResponse, simply echo it
            echo $response;
        } else {
            // The request data is missing required fields, send an error response
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "deleteHouse":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: houseID and playerUUID
        if ($requestData !== null && isset($requestData["houseID"]) && isset($requestData["playerUUID"])) {
            $houseID = $requestData["houseID"];
            $playerUUID = $requestData["playerUUID"];

            // Execute the deleteHouse function from the HouseController
            $response = HouseController::deleteHouse($pdo, $houseID, $playerUUID);

            // Directly echo the response
            echo $response;
        } else {
            // The request data is missing required fields, send an error response
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

    case "checkPlayerAndFollowers":
        $requestData = json_decode($raw, true);

        if ($requestData !== null && isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];
            $response = HouseController::checkPlayerAndFollowers($pdo, $playerUUID);
            echo json_encode($response);
        } else {
            echo json_encode(['status' => 400, 'message' => "Invalid request data."]);
        }
        break;

    case "createHouse":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: playerUUID, legacyName, and houseName
        if ($requestData !== null && isset($requestData["playerUUID"]) && isset($requestData["legacyName"]) && isset($requestData["houseName"])) {
            $playerUUID = $requestData["playerUUID"];
            $legacyName = $requestData["legacyName"];
            $houseName = $requestData["houseName"];

            // Execute the createHouse function from the HouseController
            $response = HouseController::createHouse($pdo, $playerUUID, $legacyName, $houseName);

            // Send the response (either success or error) back to the client
            echo json_encode($response);
        } else {
            // The request data is missing required fields, send an error response
            echo json_encode(['status' => 400, 'message' => "Invalid request data. Required: playerUUID, legacyName, and houseName"]);
        }
        break;

    case "getPlayerInfoForSettingNewFollowers":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary field: playerUUID
        if ($requestData !== null && isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];

            // Execute the getPlayerInfoForSettingNewFollowers function to get house information
            $response = HouseController::getPlayerInfoForNewFollowers($pdo, $playerUUID);

            // Send the response (either the house information or an error) back to the client
            echo json_encode($response);
        } else {
            // The request data is missing the required field, send an error response
            echo json_encode(['status' => 400, 'message' => "Invalid request data. Required: playerUUID"]);
        }
        break;

    case "addFollower":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary fields: followerUUID and leaderUUID
        if ($requestData !== null && isset($requestData["followerUUID"]) && isset($requestData["leaderUUID"])) {
            $followerUUID = $requestData["followerUUID"];
            $leaderUUID = $requestData["leaderUUID"];

            // Execute the addFollower function from the appropriate controller
            $response = HouseController::addFollower($pdo, $followerUUID, $leaderUUID);

            // Send the response (either success or error) back to the client
            echo json_encode($response);
        } else {
            // The request data is missing required fields, send an error response
            echo json_encode(['status' => 400, 'message' => "Invalid request data. Required: followerUUID, leaderUUID"]);
        }
        break;

        case "setPlayerTitle":
            $requestData = json_decode($raw, true);
            if ($requestData !== null && isset($requestData["player_uuid"]) && isset($requestData["target_player_uuid"]) && isset($requestData["title"])) {
                $playerUuid = $requestData["player_uuid"];
                $targetPlayerUuid = $requestData["target_player_uuid"];
                $title = $requestData["title"];
                $response = HouseController::setPlayerTitle($pdo, $playerUuid, $targetPlayerUuid, $title);
                echo $response;
            } else {
                echo new JsonResponse(400, "Invalid request data.");
            }
            break;

}
