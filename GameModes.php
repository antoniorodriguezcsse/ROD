<?php

// GameModes.php
require_once "./vendor/autoload.php";
//require_once __DIR__ . "/../vendor/autoload.php";

use Fallen\SecondLife\Controllers\ChallengeController;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\InventoryController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup

$secret = "iliketoeattacosandburritosat3819willowpassroad";

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

 if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
     die(new JsonResponse(400, "INVALID_REQUEST"));
}

$requestData = json_decode($raw, true);


switch ($action) {
    case "start_and_monitor_fight":
        if (isset($requestData["challenger_uuid"]) && isset($requestData["challenged_uuid"]) && isset($requestData["fight_duration"]) && isset($requestData["consumable_uuid"])) {
            $challengerUUID = $requestData["challenger_uuid"];
            $challengedUUID = $requestData["challenged_uuid"];
            $fightDurationMinutes = $requestData["fight_duration"];
            $consumableUUID = $requestData["consumable_uuid"];

            $challengerIsPlayer = PlayerDataController::doesPlayerExist($pdo, $challengerUUID);
            $challengedIsPlayer = PlayerDataController::doesPlayerExist($pdo, $challengedUUID);

            if (!$challengerIsPlayer) {
                echo new JsonResponse(400, "Challenger player does not exist.");
                break;
            }

            if (!$challengedIsPlayer) {
                echo new JsonResponse(400, "Challenged player does not exist.");
                break;
            }

            $ChallengerStatus = PlayerDataController::getPlayerStatus($pdo, $challengerUUID);
            $ChallengedStatus = PlayerDataController::getPlayerStatus($pdo, $challengedUUID);

            if ($ChallengerStatus == "alive" && $ChallengedStatus == "alive") {
                // Start output buffering
                ob_start();

                // Call the startAndMonitorFight method
                ChallengeController::startAndMonitorFight($pdo, $challengerUUID, $challengedUUID, $fightDurationMinutes, $consumableUUID);

                // Get the captured output
                $output = ob_get_clean();

                // Send the captured output back to the LSL script
                echo $output;
            } else {
                echo new JsonResponse(400, "One of the players is not alive.");
            }
        } else {
            echo new JsonResponse(400, "Invalid request data for start_and_monitor_fight.");
        }
        break;
    
    // Add more cases for other actions as needed
    
    default:
        echo new JsonResponse(400, "Invalid action.");
        break;
}