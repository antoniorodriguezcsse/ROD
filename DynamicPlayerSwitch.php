<?php
// dynamic_switch_router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Controllers\SleepController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup

$secret = "iliketoeattacosandburritosat3819willowpassroad"; // Use your actual secret key

$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
    die(new JsonResponse(400, "INVALID_REQUEST"));
}

$requestData = json_decode($raw, true);

if (!isset($requestData['player_uuid']) || !isset($requestData['actions']) || !is_array($requestData['actions'])) {
    die(new JsonResponse(400, "Invalid request format"));
}

$playerUUID = $requestData['player_uuid'];
$responseData = [];

foreach ($requestData['actions'] as $action => $value) {
    switch ($action) {

        case "getPlayerStatus":
            $responseData[$action] = PlayerDataController::getPlayerStatus($pdo, $playerUUID);
            break;

        case "doesPlayerExist":
            $responseData['is_player'] = (string) PlayerDataController::doesPlayerExist($pdo, $playerUUID);
            break;

        case "getPlayerEssence":
            $responseData['player_essence'] = PlayerDataController::getPlayerEssence($pdo, $playerUUID);
            break;

        case "getPlayerSpeciesType":
            // First, get the species ID for the player
            $speciesId = PlayerDataController::getPlayerSpeciesId($pdo, $playerUUID);
            if ($speciesId) {
                // Then, use the species ID to get the species type
                $responseData['species_type'] = PlayerDataController::getSpeciesTypeById($pdo, $speciesId);
            } else {
                $responseData['species_type'] = "Species ID not found for player.";
            }
            break;

        case "getPlayerIdByUUID":
            $responseData['player_id'] = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);
            break;

        case "getPlayerStatusDetails":
            $statusId = PlayerDataController::getPlayerStatusId($pdo, $playerUUID);
            if ($statusId) {
                $responseData['player_status'] = PlayerDataController::getPlayerStatusById($pdo, $statusId);
            } else {
                $responseData['player_status'] = "Status ID not found";
            }
            break;

        case "getPlayerLegacyName":
            $responseData['player_legacy_name'] = PlayerDataController::getPlayerLegacyName($pdo, $playerUUID);
            break;

        case "getPlayerBloodlineId":
            $bloodlineId = PlayerDataController::getPlayerBloodlineIdByUuid($pdo, $playerUUID);
            if ($bloodlineId !== null) {
                $responseData['bloodline_id'] = $bloodlineId;
            } else {
                $responseData['bloodline_id'] = "Bloodline ID not found for player.";
            }
            break;

        case "getPlayerSleepDetails":
            $sleepDetails = SleepController::getPlayerSleepDetails($pdo, $playerUUID);
            if ($sleepDetails !== null) {
                $responseData['sleep_end_date'] = $sleepDetails['sleep_end_date'];
                $responseData['sleep_level'] = $sleepDetails['sleep_level'];
            } else {
                $responseData['sleep_end_date'] = "Sleep details not found for player.";
                $responseData['sleep_level'] = "Sleep details not found for player.";
            }
            break;
            
        case "getPlayerBloodlineName":
            $bloodlineName = PlayerDataController::getPlayerBloodlineName($pdo, $playerUUID);
            if ($bloodlineName !== null) {
                $responseData['bloodline_name'] = $bloodlineName;
            } else {
                $responseData['bloodline_name'] = "Bloodline name not found for player.";
            }
            break;

        // Add more cases for other methods as needed

        default:
            $responseData[$action] = "Unknown action";
            break;
    }
}

$response = new JsonResponse(200, "Requested Data", $responseData);
echo $response; // This will invoke __toString() method of JsonResponse
