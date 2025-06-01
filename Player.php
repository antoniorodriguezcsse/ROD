<?php
use Fallen\SecondLife\Classes\JsonResponse;
// Comment out when not needed
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once "./vendor/autoload.php";

//$slHeaders = new SecondLifeHeaders();

use Fallen\SecondLife\Controllers\CommunicationController;
use Fallen\SecondLife\Controllers\PlayerController;
use Fallen\SecondLife\Controllers\TableController;
use Fallen\SecondLife\Controllers\VersionsController;
use Fallen\SecondLife\Helpers\IntegrityChecker;

$pdo = require __DIR__ . '/src/Classes/database.php';
$isLocalRequest = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

$secret = "iliketoeattacosandburritosat3819willowpassroad";

//Example usage of the $slHeaders instance
//echo "Owner Key: " . $slHeaders->getOwnerKey() . "\n";
// echo "Owner Name: " . $slHeaders->getOwnerName() . "\n";
// echo "Region Name: " . $slHeaders->getRegionName() . "\n";
// echo "Region Position: " . $slHeaders->getRegionPosition() . "\n";

switch ($action)
{
    case "test":

        break;

    case "create":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        $data = json_decode($raw, true);

        if ($data !== null)
        {
            // Access the individual values
            $playerUUID = $data["player_uuid"];
            $legacyName = $data["legacy_name"];
            $speciesID = $data["species_id"];

            $player = PlayerController::create($pdo, $playerUUID, $legacyName, $speciesID);
            echo $player;
        }
        else
        {
            echo "Error decoding JSON data.";
        }
        break;

    case "getPlayerData":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }
        $playersUUID = $raw;
        $player = PlayerController::getPlayerData($pdo, $playersUUID);
        echo $player;
        break;

    case "sireNewHuman":
        $data = json_decode($raw, true);

        if ($data !== null)
        {
            // Access the individual values
            $humanUUID = $data["human_uuid"];
            $legacyName = $data["legacy_name"];
            $sirePlayerUUID = $data["sire_player_uuid"];

            $result = PlayerController::sireNewHuman($pdo, $humanUUID, $legacyName, $sirePlayerUUID);

            echo $result;
        }
        else
        {
            echo "Error decoding JSON data.";
        }
        break;

    case "checkHumanBeforeSire":
        $response = PlayerController::handleCheckHumanBeforeSire($raw, $pdo);
        echo $response;
        break;

    case "doAttack":
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        $data = json_decode($raw, true);
        if ($data !== null)
        {

            $attackedPlayerUUID = $data["attacked_player_key"];

            $attackedPlayerDamage = $data["attacked_player_damage"];
            $myUUID = $data["my_uuid"];
            $myAttackWeapon = $data["my_attack_weapon"];
            $mySpecies = $data["my_species"];

            $player = PlayerController::attack($pdo, $attackedPlayerUUID, $attackedPlayerDamage, $myUUID);
            echo $player;

            $player = json_decode($player, true);

            $player["extra"]["my_key"] = $myUUID;
            $player["extra"]["my_attack_weapon"] = $myAttackWeapon;
            $player["extra"]["my_species"] = $mySpecies;

            $response = CommunicationController::sendDataToPlayersHUD($pdo, $attackedPlayerUUID, $player);
            //error_log($response);

        }
        else
        {
            echo "Error decoding JSON data.";
        }
        break;

    case "getScan":
        $playersUUID = $raw;
        print_r($playerUUID);
        $player = PlayerController::getPublicScan($pdo, $playersUUID); //getPublicScan($pdo, $playersUUID);
        echo $player;
        break;

    case "getPrivateScan":

        if (!$isLocalRequest)
        {
            $userNameFormat = "hud";
            // Only check integrity for non-local requests
            if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
            {
                die(new JsonResponse(400, "INVALID_REQUEST"));
            }
        }
        else
        {
            // $userNameFormat = "discord";
        }

        $playersUUID = $raw;
        $player = PlayerController::getSpeciesScan($pdo, $playersUUID, "private", $userNameFormat);
        echo $player;
        break;

    case "updatePlayerData":
        // Update example
        // {
        //     "player_uuid": "ce896715-ff46-49f0-a3e3-986de9e54bb5",
        //     "table_name": "players",
        //     "update_data": {
        //         "player_status_id": "2"
        // }

        //Uncomment the following lines in production to ensure data integrity
        if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw))
        {
            die(new JsonResponse(400, "INVALID_REQUEST"));
        }

        $data = json_decode($raw, true);

        // Check if the data was successfully decoded
        if ($data !== null)
        {
            $playerUUID = $data["player_uuid"] ?? null;
            $tableName = $data["table_name"] ?? null;
            $updateData = $data["update_data"] ?? null;

            // Check if the necessary data is available
            if ($playerUUID && $tableName && $updateData)
            {
                //   echo $data;
                // Use the updateData function and retrieve the JsonResponse object
                $result = TableController::updateData($pdo, $tableName, $playerUUID, $updateData);

                // Get the keys of the array
                $keys = array_keys($updateData);

                $data = json_decode($result);
                $statusCode = $data->status;

                //Find the key "name"
                if ($statusCode === 200 && in_array("player_current_health", $keys))
                {
                    CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "update_health"]);
                    echo new JsonResponse(200, "Health successfully updated.", "");
                }
                else
                {
                    echo "Could not update health. Status code: " . $statusCode;
                }

            }
            else
            {
                echo new JsonResponse(400, "Incomplete data received.");
            }
        }
        else
        {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "sendDataToPlayersHud":
        // Send data to player's HUD example
        // {
        //     "player_uuid": "ce896715-ff46-49f0-a3e3-986de9e54bb5",
        //     "data": {
        //         "attackerUUID": "some-uuid",
        //         "damageTaken": 50,
        //         "currentHealth": 250
        //     }
        // }

        // Uncomment the following lines in production to ensure data integrity
        // if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)){
        //     die(new JsonResponse(400, "INVALID_REQUEST"));
        // }

        $requestData = json_decode($raw, true);

        // Check if the data was successfully decoded
        if ($requestData !== null)
        {
            $playerUUID = $requestData["player_uuid"] ?? null;
            $dataToSend = $requestData["data"] ?? null;

            // Check if the necessary data is available
            if ($playerUUID && $dataToSend)
            {
                // Use the sendDataToPlayersHud function and retrieve the JsonResponse object
                $result = CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, $dataToSend);

                // Directly echo the JsonResponse object.
                echo $result;
            }
            else
            {
                echo new JsonResponse(400, "Incomplete data received.");
            }
        }
        else
        {
            echo new JsonResponse(400, "Error decoding JSON data.");
        }
        break;

    case "checkUUIDExists":
        // Decode the JSON request data
        $requestData = json_decode($raw, true);

        // Check if the request data has the necessary field: uuid
        if ($requestData !== null && isset($requestData["uuid"]))
        {
            $uuid = $requestData["uuid"];

            // Call the function to check if the UUID exists
            $exists = PlayerController::checkPlayerUUIDExists($pdo, $uuid);

            // Prepare the response
            $response = [
                'status' => $exists ? 200 : 404,
                'message' => $exists ? 'UUID exists.' : 'UUID not found.',
            ];

            // Send the response back to the client
            echo json_encode($response);
        }
        else
        {
            // The request data is missing the required field, send an error response
            echo json_encode(['status' => 400, 'message' => "Invalid request data. Required: uuid"]);
        }
        break;

    case "updatePlayerAttachedHudDetails":
        $response = PlayerController::updatePlayerAttachedHudDetails($pdo, $raw);
        echo $response;
        break;

    case "getCurrentVersion":
        // Decode the JSON request payload
        $requestData = json_decode($raw, true);

        // Check if the request data is valid
        if ($requestData !== null)
        {
            // Extract the version name from the request
            $versionName = $requestData["version_name"];

            // Attempt to retrieve the version number by the version name
            $response = VersionsController::getVersionNumberByName($pdo, $versionName);


            // Check if a version number was found
            if ($response != null)
            {
                // Version found: return a 200 JSON response
                echo new JsonResponse(200, "Version found.", ["version_number" => "$response"]);
            }
            else
            {
                // Version not found: return a 404 JSON response
                echo new JsonResponse(404, "Version not found.");
            }
        }
        else
        {
            // Invalid request data: return a 400 JSON response
            echo new JsonResponse(400, "Invalid request data.");
        }

}
