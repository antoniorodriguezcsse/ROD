<?php
// router.php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\ResireController;

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

    case "initiateResire":
        if (isset($requestData["playerUUID"], $requestData["newSireUUID"], $requestData["allowChainMove"])) {
            $response = ResireController::initiateResire($pdo, $requestData["playerUUID"], $requestData["newSireUUID"], $requestData["allowChainMove"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for initiateResire.");
        }
        break;

    case "joinResire":
        if (isset($requestData["playerUUID"])) {
            $response = ResireController::joinResire($pdo, $requestData["playerUUID"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for joinResire.");
        }
        break;

    case "declineResire":
        if (isset($requestData["playerUUID"])) {
            $response = ResireController::declineResire($pdo, $requestData["playerUUID"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for declineResire.");
        }
        break;

    case "calculateResirePrice":
        if (isset($requestData["playerUUID"], $requestData["newSireUUID"])) {
            $response = ResireController::calculateResirePrice($pdo, $requestData["playerUUID"], $requestData["newSireUUID"]);
            echo $response;
        } else {
            echo new JsonResponse(400, "Invalid request data for calculateResirePrice.");
        }
        break;

    default:
        echo new JsonResponse(400, "Invalid action.");
}
