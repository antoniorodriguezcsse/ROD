<?php
require_once "./vendor/autoload.php";

use Fallen\SecondLife\Controllers\HumanKillsController;
use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Helpers\IntegrityChecker;

// Initialize PDO for database connection
$pdo = require __DIR__ . '/src/Classes/database.php';

// Define secret for checksum verification
$secret = getenv('INTEGRITY_CHECKSUM_SECRET') ?: "iliketoeattacosandburritosat3819willowpassroad";


$isLocalRequest = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']);
//error_log("\n\n\n\nLocal is" . $isLocalRequest);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(new JsonResponse(405, "Method Not Allowed"));
    exit;
}

// Validate 'action' parameter
 $action = isset($_GET["action"]) ? trim($_GET["action"]) : '';
if (empty($action)) {
    http_response_code(400); // Bad Request
    echo json_encode(new JsonResponse(400, "Action not specified"));
    exit;
}

// For external requests, validate integrity checksum
if (!$isLocalRequest) {
    // Retrieve checksum from headers
    $checksum = isset($_SERVER["HTTP_X_INTEGRITY_CHECKSUM"]) ? $_SERVER["HTTP_X_INTEGRITY_CHECKSUM"] : '';
    
    if (empty($checksum)) {
        http_response_code(400); // Bad Request
        echo json_encode(new JsonResponse(400, "Missing integrity checksum"));
        exit;
    }
}

// Retrieve and validate request body
$raw = file_get_contents("php://input");
if (empty($raw)) {
    http_response_code(400); // Bad Request
    echo json_encode(new JsonResponse(400, "Empty request body"));
    exit;
}

// Verify integrity for external requests
if (!$isLocalRequest) {
    if (!IntegrityChecker::verify_checksum($secret, $checksum, $raw)) {
        http_response_code(400); // Bad Request
        echo json_encode(new JsonResponse(400, "INVALID_REQUEST"));
        exit;
    }
}

// Decode JSON data
$requestData = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(new JsonResponse(400, "Invalid JSON data"));
    exit;
}

try {
    switch ($action) {
        case "getLeadersByTimePeriod":
            if (!isset($requestData["timePeriod"])) {
                echo new JsonResponse(400, "Time period not specified for leaderboard.");
                break;
            }

            $timePeriod = trim($requestData["timePeriod"]);
            $page = isset($requestData["page"]) ? (int)$requestData["page"] : 0;
            $limit = isset($requestData["limit"]) ? (int)$requestData["limit"] : 10;
            $searchUUID = isset($requestData["searchUUID"]) ? trim($requestData["searchUUID"]) : null;

            $response = HumanKillsController::getLeadersByTimePeriod($pdo, $timePeriod, $page, $limit, $searchUUID);
            echo $response;
            break;

        default:
            echo new JsonResponse(400, "Invalid action.");
            break;
    }
} catch (Exception $e) {
    error_log("Error in HumanKills.php: " . $e->getMessage());
    echo new JsonResponse(500, "An error occurred while processing the request.");
}