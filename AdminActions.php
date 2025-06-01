<?php
// Comment out when not needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Classes\SecondLifeHeadersStatic;
use Fallen\SecondLife\Controllers\AdminController;
use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\DeathLogController;
use Fallen\SecondLife\Controllers\PlayerDataController;
use Fallen\SecondLife\Controllers\RevivalController;
use Fallen\SecondLife\Controllers\TableController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php';

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$secret = "iliketoeattacosandburritosat3819willowpassroad";

 if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
     die(new JsonResponse(400, "INVALID_REQUEST"));
}

///we can add the admin check here.
$uuid = SecondLifeHeadersStatic::getOwnerKey();
$response = AdminController::checkAdmin($pdo, $uuid);
$check = json_decode((string) $response, true);

if ($action !== "checkAdmin") {
    if (!isset($check["extra"]["is_admin"]) || $check["extra"]["is_admin"] !== "true") {
        echo new JsonResponse(403, "Access denied: You are not an admin.");
        return;
    }

    $adminLevel = $check["extra"]["admin_level"];
}


switch ($action) {

    case "checkAdmin":
        //  echo "test";

        try {
            $uuid = SecondLifeHeadersStatic::getOwnerKey();
            $response = AdminController::checkAdmin($pdo, $uuid);
            echo $response;
        } catch (Exception $e) {
            echo new JsonResponse(500, "Server error: " . $e->getMessage());
        }
        break;

    case "kill":

        $data = json_decode($raw, true);

        if ($data !== null) {

            $myUUID = SecondLifeHeadersStatic::getOwnerKey();

            // Access the individual values
            $playerUUID = $data["player_uuid"];

            // $player = PlayerController::create($playerUUID, $legacyName, $speciesID);
            $dataToSend = AdminController::killPlayer($pdo, $playerUUID);

            $regionName = SecondLifeHeadersStatic::getRegionName();
            $myID = PlayerDataController::getPlayerIdByUUID($pdo, $myUUID);
            $theirID = PlayerDataController::getPlayerIdByUUID($pdo, $playerUUID);

            // Log the death
            $deathLogResult = DeathLogController::addDeathLogEntry($pdo, $theirID, $myID, date('Y-m-d H:i:s'), $regionName, 'killed_by_admin', 'Player was killed by an admin.');
            $decodedDeathLogResult = json_decode((string) $deathLogResult, true);
            if ($decodedDeathLogResult['status'] != 200) {
                error_log("Error updating death log: " . $decodedDeathLogResult['message']);
            }

            echo CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, json_decode($dataToSend));
        } else {
            echo "Error decoding JSON data.";
        }
        break;

    case "giveEssence":
        $requestData = json_decode($raw, true);

        $essenceToGive = $requestData["amount_of_essence"];
        $playerUUID = $requestData["player_uuid"];

        if (isset($essenceToGive) && isset($playerUUID)) { // Use $playerUUID for consistency
            $message = PlayerDataController::updatePlayerEssence($pdo,
                $playerUUID,
                PlayerDataController::getPlayerEssenceCount($pdo, $playerUUID) + $essenceToGive);
            if ($message == "Player essence updated successfully.") {
                echo new JsonResponse(200, $message);

            } else {
                echo new JsonResponse(400, $message);
            }

        } else {
            echo new JsonResponse(400, "Incomplete data received.");
        }

        break;

    case "revivePlayer":

        $data = json_decode($raw, true);

        if ($data !== null) {
            $playerUUID = $data["player_uuid"] ?? null;

            if ($playerUUID) {
                // Revive the player
                $reviveResponse = RevivalController::revivePlayer($pdo, $playerUUID);

                echo $reviveResponse;
                CommunicationController::sendDataToPlayersHUD($pdo, $playerUUID, json_decode($reviveResponse));
            } else {
                echo new JsonResponse(400, "Incomplete data received.");
            }
        } else {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "fillPlayerHealth":

        $data = json_decode($raw, true);

        if ($data !== null) {
            $playerUUID = $data["player_uuid"] ?? null;
            $maxHealth = PlayerDataController::getPlayerMaxHealth($pdo, $playerUUID);

            if ($maxHealth == "404 error") {
                echo new JsonResponse(404, "Error getting foreign key data.");
            } else {

                $result = TableController::updateData($pdo, "players", $playerUUID, ["player_current_health" => $maxHealth]);
                $data = json_decode($result, true);
                $statusCode = $data['status'];

                if ($statusCode != 200) {
                    echo $result;
                } else {
                    echo CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "update_health"]);
                }
            }

        } else {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "banPlayer":
        $data = json_decode($raw, true);

        if ($data !== null) {
            $adminUUID = SecondLifeHeadersStatic::getOwnerKey();
            $playerUUID = $data["player_uuid"];
            $banReason = $data["ban_reason"] ?? "No reason provided";
            $banDuration = isset($data["ban_duration"]) ? intval($data["ban_duration"]) : 0; // 0 means permanent

            echo AdminController::banPlayer($pdo, $playerUUID, $banReason, $banDuration, $adminUUID);
        } else {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "unbanPlayer":
        $data = json_decode($raw, true);

        if ($data !== null) {
            $adminUUID = SecondLifeHeadersStatic::getOwnerKey();
            $playerUUID = $data["player_uuid"];

            echo AdminController::unbanPlayer($pdo, $playerUUID, $adminUUID);
        } else {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

        case "checkBanStatus":
            $data = json_decode($raw, true);
            
            if ($data !== null) {
                $playerUUID = $data["player_uuid"];
                echo AdminController::checkBanStatus($pdo, $playerUUID);
            } else {
                echo new JsonResponse(400, "Error decoding JSON data.");
            }
            break;
}
