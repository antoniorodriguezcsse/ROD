<?php
// Comment out when not needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\HouseController;
use Fallen\SecondLife\Controllers\OutcastController;
use Fallen\SecondLife\Controllers\TableController;
use Fallen\SecondLife\Controllers\PlayerDataController;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$secret = "iliketoeattacosandburritosat3819willowpassroad";

switch ($action) {
    case "checkIfPlayer":
        $requestData = json_decode($raw, true);
        if ($requestData !== null && isset($requestData["player_uuid"])) {
            $playerUUID = $requestData["player_uuid"];

            $result = OutcastController::doesPlayerExist($pdo, $playerUUID);
            if($result){
                echo new JsonResponse(200, "uuid found in database.", $decodedDetails);
            }
            else{
                echo new JsonResponse(404, "uuid not found in database", $decodedDetails);
            }
            // $houseAndClanDetails = OutcastController::fetchHouseDetailsForPlayer($pdo, $playerUUID);
            // $decodedDetails = json_decode($houseAndClanDetails, true);

            // if (isset($decodedDetails["error"])) {
            //     echo new JsonResponse(404, $decodedDetails["error"]);
            // } else {
            //     echo new JsonResponse(200, "Success", $decodedDetails);
            // }
        } 
        else {
            echo new JsonResponse(400, "Error processing request data.");
        }
    break;

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

    case "leaveLeader":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary field: playerUUID
        if ($requestData !== null && isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];
           // echo $playerUUID;
      
            $result = OutcastController::updatePlayerFollowingIdToNull($pdo, $playerUUID);
            if($result) {
                echo new JsonResponse(200, "Success leaving leader", "");
            } 
            else {
                echo new JsonResponse(400, "Error leaving leader", "");
            }
            // Send the response (either success or error) back to the client
           // echo new JsonResponse($response['status'], $response['message']);
        } else {
            // The request data is missing required fields, send an error response
            echo new JsonResponse(400, "Invalid request data.");
        }
    break;

    case "outcastFollower":
        $requestData = json_decode($raw, true);
        
        // Check if the request data has the necessary field: playerUUID
        if ($requestData !== null && isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];
            $theirUUID = $requestData["theirUUID"];

            $myPlayerID = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            $theirFollowerID = PlayerDataController::getPlayerFollowingId($pdo, $theirUUID);
            

            if($myPlayerID === $theirFollowerID){
                $leftLeader = OutcastController::updatePlayerFollowingIdToNull($pdo, $theirUUID);
                if($leftLeader) {
                    $updateData = ["house_id" => null, "house_role_id" => null];
                    echo OutcastController::updateData($pdo, "players", $theirUUID, $updateData);
                }
                else{

                }
               //echo new JsonResponse(200, "Successfully outcasted player.", "");
            }
            else{
                echo new JsonResponse(403, "This player is not following you.", "");
            }
   
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;

        case "outcastPlayerAndFollowers":
            $requestData = json_decode($raw, true);
    
            if (isset($requestData["playerUUID"], $requestData["theirUUID"])) {
                $playerUUID = $requestData["playerUUID"];
                $theirUUID = $requestData["theirUUID"];
    
                // Verify if the targeted player is a follower of the initiating player
                $myPlayerID = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
                $theirFollowerID = PlayerDataController::getPlayerFollowingId($pdo, $theirUUID);
    
                if ($myPlayerID === $theirFollowerID) {
                    // Call the outcastPlayerAndFollowers function
                    $response = OutcastController::outcastPlayerAndFollowers($pdo, $theirUUID);
    
                    // Echo the response from the outcastPlayerAndFollowers function
                    echo $response;
                } else {
                    echo new JsonResponse(403, "This player is not following you.");
                }
            } else {
                echo new JsonResponse(400, "Invalid request data. 'playerUUID' and 'theirUUID' are required.");
            }
            break;
    
}
