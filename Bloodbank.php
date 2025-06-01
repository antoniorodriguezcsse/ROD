<?php
// router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Controllers\BloodBankController;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Helpers\IntegrityChecker;
use Fallen\SecondLife\Controllers\CommunicationController;

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

    case "getPlayerTotalBlood":
        if (isset($requestData["player_uuid"])) {
            $response = BloodBankController::getPlayerTotalBlood($pdo, $requestData["player_uuid"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for getPlayerTotalBlood.");
        }
        break;
    
        case "depositBlood":
            if (isset($requestData["player_uuid"], $requestData["amount"])) {
                $response = BloodBankController::depositBlood($pdo, $requestData["player_uuid"], $requestData["amount"]);
                echo $response;
            } else {
                echo new JsonResponse(400, "Invalid request data for deposit.");
            }
            break;
        

    case "withdrawBlood":
        if (isset($requestData["player_uuid"], $requestData["amount"])) {
            $response = BloodBankController::withdrawBlood($pdo, $requestData["player_uuid"], $requestData["amount"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for withdrawal.");
        }
        break;

        case "transferBlood":
            if (isset($requestData["donor_uuid"], $requestData["recipient_uuid"], $requestData["amount"])) {
        
                // Ensure the amount is a float and format it without trailing zeros
                $amount = (float) $requestData["amount"];
                $formatted_amount = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
        
               
        
                // Call the transferBlood method with the provided data
                $response = BloodBankController::transferBlood($pdo, $requestData["donor_uuid"], $requestData["recipient_uuid"], $formatted_amount);
                echo $response;
            } else {
                // Return an error response if any required data is missing
                echo new JsonResponse(400, "Invalid request data for transfer.");
            }
            break;
        
        
        

            case "getLeadersByTimePeriod":
                if (isset($requestData["timePeriod"])) {
                    $timePeriod = $requestData["timePeriod"];
                    $limit = isset($requestData["limit"]) ? (int)$requestData["limit"] : 10; // Default limit is 10
            
                    if (in_array($timePeriod, ['day', 'week', 'month', 'all'])) {
                        $response = BloodBankController::getLeadersByTimePeriod($pdo, $timePeriod, $limit);
                        echo $response;
                    } else {
                        echo new JsonResponse(400, "Invalid time period specified.");
                    }
                } else {
                    echo new JsonResponse(400, "Time period not specified for leaderboard.");
                }
                break;
            
            

    default:
        echo new JsonResponse(400, "Invalid action.");
}

