<?php

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Helpers\IntegrityChecker;
use Fallen\SecondLife\Controllers\SleepController;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup
$secret = "iliketoeattacosandburritosat3819willowpassroad";



$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];

// if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
//     die(new JsonResponse(400, "INVALID_REQUEST"));
// }

$raw = file_get_contents("php://input");
$requestData = json_decode($raw, true);

// Attempt to get the action from the requestData array
$action = isset($requestData['action']) ? $requestData['action'] : null;

switch ($action) {

    case "setSleeping":
        if ($requestData !== null && isset($requestData["player_uuid"]) && isset($requestData["sleep_level"])) {
            $playerUuid = $requestData["player_uuid"];
            $sleepLevel = $requestData["sleep_level"];
            $response = SleepController::setSleeping($pdo, $playerUuid, $sleepLevel);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data.");
        }
        break;
    
        case "leaveSleeping":
            if ($requestData !== null && isset($requestData["player_uuid"])) {
                $playerUuid = $requestData["player_uuid"];
                $response = SleepController::leaveSleeping($pdo, $playerUuid);
                echo $response;
            } else {
                echo new JsonResponse(400, "Invalid request data.");
            }
            break;
    
    default:
        echo new JsonResponse(400, "Invalid action.");
}