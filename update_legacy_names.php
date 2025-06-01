<?php
/**
 * Simple PHP script to update legacy names from YOUR LSL server (instead of W-Hat).
 */

// ---------------------------------------------------------------------------
// 1. Configuration
// ---------------------------------------------------------------------------
$dbHost       = 'localhost';       // e.g. '127.0.0.1'
$dbName       = 'system_live';     // e.g. 'my_database'
$dbUser       = 'system_live';     // e.g. 'root'
$dbPass       = 'eymLXdmbxibzf87P';
$lslApiURL    = 'http://simhost-0c535c3dbb5d3e9d1.agni.secondlife.io:12046/cap/f3d3a8df-8b22-f9bd-baa0-28cbf61151fe'; // The URL granted by llRequestURL() in LSL
$logFile      = __DIR__ . '/update_legacy_names.log';
$delaySeconds = 1.0; // delay between requests (avoid flooding LSL)

// ---------------------------------------------------------------------------
// 2. Logging Function
// ---------------------------------------------------------------------------
function logMessage($message, $logFile = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $line      = "[{$timestamp}] {$message}\n";
    echo $line;
    if ($logFile) {
        file_put_contents($logFile, $line, FILE_APPEND);
    }
}

// ---------------------------------------------------------------------------
// 3. Connect to DB (autocommit mode)
// ---------------------------------------------------------------------------
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    logMessage("Connected to the database successfully.", $logFile);
} catch (PDOException $e) {
    logMessage("Database connection failed: " . $e->getMessage(), $logFile);
    exit(1);
}

// ---------------------------------------------------------------------------
// 4. Fetch Player UUIDs
// ---------------------------------------------------------------------------
try {
    $stmt = $pdo->query("SELECT player_uuid FROM players");
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $totalPlayers = count($players);
    logMessage("Fetched {$totalPlayers} player UUID(s) from DB.", $logFile);
} catch (PDOException $e) {
    logMessage("Failed to fetch UUIDs: " . $e->getMessage(), $logFile);
    exit(1);
}

// ---------------------------------------------------------------------------
// 5. Prepare Update Statement
// ---------------------------------------------------------------------------
try {
    $updateStmt = $pdo->prepare("UPDATE players SET legacy_name = :legacy_name WHERE player_uuid = :uuid");
} catch (PDOException $e) {
    logMessage("Failed to prepare update statement: " . $e->getMessage(), $logFile);
    exit(1);
}

// ---------------------------------------------------------------------------
// 6. Function to Fetch Legacy Name from Your LSL Server (POST)
// ---------------------------------------------------------------------------
function fetchLegacyNameFromLSL($uuid, $lslApiURL)
{
    // We'll POST form data: "uuid=<KEY>"
    $postData = http_build_query(['uuid' => $uuid]);

    $ch = curl_init($lslApiURL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTFIELDS     => $postData,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error'   => "cURL error: {$error}",
        ];
    }

    // We expect LSL to return 200 if found, 404 if not, etc.
    if ($httpCode === 200) {
        // 'response' is the username returned by the LSL script
        return [
            'success' => true,
            'data'    => trim($response),
        ];
    } elseif ($httpCode === 404) {
        // Avatar not found or invalid
        return [
            'success' => false,
            'error'   => "Avatar UUID not found: {$uuid}",
        ];
    } elseif ($httpCode === 400) {
        // Missing 'uuid' param or some other user error
        return [
            'success' => false,
            'error'   => "Bad request to LSL (check 'uuid' param?).",
        ];
    } else {
        // Some other status
        return [
            'success' => false,
            'error'   => "HTTP status code: {$httpCode}. Body: {$response}",
        ];
    }
}

// ---------------------------------------------------------------------------
// 7. Update Loop - Skip on Failure, Continue with Next
// ---------------------------------------------------------------------------
$processed = 0;
foreach ($players as $uuid) {
    $processed++;

    // 7.1 Fetch legacy name from your LSL server
    $fetchResult = fetchLegacyNameFromLSL($uuid, $lslApiURL);
    if (!$fetchResult['success']) {
        // Skip this UUID, log the error
        logMessage("Failed to fetch legacy name for [{$uuid}]: {$fetchResult['error']}", $logFile);
        continue;
    }

    $legacyName = $fetchResult['data'];
    if (empty($legacyName)) {
        logMessage("LSL returned an empty name for [{$uuid}]. Skipping.", $logFile);
        continue;
    }

    // 7.2 Optional: Replace dots with spaces if desired
    $legacyName = str_replace('.', ' ', $legacyName);

    // 7.3 Update DB
    try {
        $updateStmt->execute([
            ':legacy_name' => $legacyName,
            ':uuid'        => $uuid
        ]);
        logMessage("Updated [{$uuid}] => '{$legacyName}'", $logFile);
    } catch (PDOException $e) {
        logMessage("DB update failed for [{$uuid}]: " . $e->getMessage(), $logFile);
        // Skip this player, continue with next
        continue;
    }

    // 7.4 Throttle to avoid overloading LSL server
    if ($processed < $totalPlayers) {
        usleep((int)($delaySeconds * 1000000)); // convert seconds to microseconds
    }
}

// ---------------------------------------------------------------------------
// 8. Done
// ---------------------------------------------------------------------------
logMessage("Done updating legacy names. Processed {$processed} player(s).", $logFile);
echo "All done. Check the log file for details.\n";
