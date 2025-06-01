<?php
// Enable output buffering
ob_start();

// Enable error reporting for better debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '/www/wwwroot/api.systemsl.xyz/Mystical_Convergence/vendor/autoload.php';

use Fallen\SecondLife\Controllers\CommunicationController;

$error_log_file = "master_script_log.txt";
$script_directory = __DIR__; // Directory where this script and included scripts are located

// Function to log messages
function logMessage($message) {
    global $error_log_file;
    $current_date = date("Y-m-d H:i:s");
    $log_entry = $current_date . ": " . $message . "\n";
    
    // Write to STDOUT for terminal output
    fwrite(STDOUT, $log_entry);
    
    // Write to log file
    file_put_contents($error_log_file, $log_entry, FILE_APPEND);
}

// Function to run individual scripts in separate PHP processes and capture their output
function runScript($scriptPath) {
    global $script_directory;
    $fullPath = $script_directory . '/' . $scriptPath;
    
    logMessage("Attempting to execute: $fullPath");
    
    // Check if the script file exists
    if (!file_exists($fullPath)) {
        return "Error: Script file not found: $fullPath";
    }
    
    // Execute the command and capture both stdout and stderr
    $output = [];
    $return_var = 0;
    $last_line = exec("php " . escapeshellarg($fullPath) . " 2>&1", $output, $return_var);
    
    // Join the output array into a single string
    $output_string = implode("\n", $output);
    
    // If there's no output, return a message indicating that
    if (empty($output_string)) {
        return "Script executed, but produced no output. Return value: $return_var, Last line: $last_line";
    }
    
    return "Return value: $return_var\nOutput:\n$output_string";
}

// Function to split long messages for Discord
function splitDiscordMessage($message, $maxLength = 1950) { // Adjusted to 1950 to account for added formatting
    $messages = [];
    $lines = explode("\n", $message);
    $currentMessage = "";
    
    foreach ($lines as $line) {
        if (strlen($currentMessage) + strlen($line) + 1 > $maxLength) {
            $messages[] = $currentMessage;
            $currentMessage = $line;
        } else {
            $currentMessage .= ($currentMessage ? "\n" : "") . $line;
        }
    }
    
    if ($currentMessage) {
        $messages[] = $currentMessage;
    }
    
    return $messages;
}

logMessage("Starting master script execution...");

// Run your scripts in order, capture their output, and send to Discord
$scripts = [
    "subsistence_health_check.php",
    "player_age_update.php",
    "update_player_age_groups.php",
    "essence_update.php",
    "process_sleep_end_players.php",
    "check_overseers_online_status.php",
    "overseer_death_check.php",
    "ban_expiry_update.php"
];

foreach ($scripts as $index => $script) {
    logMessage("Executing script $index: $script");
    $output = runScript($script);
    
    // Log the output locally
    logMessage("Output from $script:");
    logMessage($output);
    
    // Prepare the output for Discord
    $discordMessage = "**Output from $script:**\n$output";
    $messageParts = splitDiscordMessage($discordMessage);
    
    foreach ($messageParts as $index => $part) {
        $partNumber = $index + 1;
        $totalParts = count($messageParts);
        $messageWithPart = ($totalParts > 1 ? "(Part $partNumber/$totalParts)\n" : "") . "```\n$part\n```";
        
        try {
            $result = CommunicationController::postToDiscordCronLog($messageWithPart);
            logMessage("DISCORD: " . (string)$result);
            logMessage("Successfully posted part $partNumber/$totalParts to Discord for $script");
            
            // Flush the output buffer after each Discord post
            ob_flush();
            flush();
        } catch (Exception $e) {
            logMessage("Error posting part $partNumber/$totalParts to Discord for $script: " . $e->getMessage());
            
            // Flush the output buffer after logging the error
            ob_flush();
            flush();
        }
    }
    
    // Flush the output buffer after processing each script
    ob_flush();
    flush();
}

logMessage("Master script execution completed.");

// End output buffering and flush final output
ob_end_flush();