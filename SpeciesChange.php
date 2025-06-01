<?php
// router.php
require_once "./vendor/autoload.php";


use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Helpers\IntegrityChecker;
use Fallen\SecondLife\Controllers\SpeciesChangeController;

$pdo = require __DIR__ . '/src/Classes/database.php'; // Your PDO connection setup

$secret = "iliketoeattacosandburritosat3819willowpassroad";

$action = $_GET["action"];
$checksum = @$_SERVER["HTTP_X_INTEGRITY_CHECKSUM"];
$raw = file_get_contents("php://input");

// if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
//      die(new JsonResponse(400, "INVALID_REQUEST"));
// }

$requestData = json_decode($raw, true);

switch ($action) {

    case "getSpeciesList":
        if (isset($requestData["playerUUID"])) {
            $playerUUID = $requestData["playerUUID"];
            $response = SpeciesChangeController::getSpeciesList($pdo, $playerUUID);
            echo $response; // Assuming the getSpeciesList function returns a JsonResponse object
        } else {
            echo new JsonResponse(400, "Player UUID is required.");
        }
        break;
    

        case "changeSpecies":
            // Decode the JSON request data
            $requestData = json_decode($raw, true);
    
            // Check if the necessary data is available
            if ($requestData !== null && isset($requestData["playerUUID"]) && isset($requestData["newSpeciesId"])) {
                $playerUUID = $requestData["playerUUID"];
                $newSpeciesId = $requestData["newSpeciesId"];
    
                // Call the function to change the player's species
                $response = SpeciesChangeController::changePlayerSpecies($pdo, $playerUUID, $newSpeciesId);
    
                // Send the response back to the client
                echo json_encode($response);
            } else {
                // The request data is missing required fields
                echo json_encode(['status' => 400, 'message' => "Invalid request data. Required: playerUUID, newSpeciesId"]);
            }
            break;
    
            
            

    default:
        echo new JsonResponse(400, "Invalid action.");
}
?>
