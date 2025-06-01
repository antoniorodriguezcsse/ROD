<?php
// router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
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

    case "EssenceBought":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        $data = json_decode($raw, true);

        if ($data !== null) {
            $playerUUID = $data["playerUUID"];
            $amountOfEssenceBought = $data["amountOfEssenceBought"];
            $amountOfPlayerEssence = PlayerDataController::getPlayerEssenceCount($pdo, $playerUUID);
            $newTotalEssence = $amountOfPlayerEssence + $amountOfEssenceBought;
            $result = PlayerDataController::updatePlayerEssence($pdo, $playerUUID, $newTotalEssence);

            if($result == "Player essence updated successfully."){
                echo new JsonResponse(200, "Data updated successfully.", ['player_uuid' => $playerUUID, 
                                                                          'essence_had' => $amountOfPlayerEssence, 
                                                                          'amount_bought' => $amountOfEssenceBought,
                                                                          'total' => $newTotalEssence]);
            }
            else{
                echo new JsonResponse(400,"Failed to upadate essence.",$result);
            }
        } else {
            echo new JsonResponse(400,"Error decoding JSON data.");
        }
        break;
}
