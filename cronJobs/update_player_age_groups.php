<?php

// Enable error reporting for better debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection credentials
$servername = "localhost";
$username = "system_live";
$password = "ZbsaSt6za76Es4e7";
$dbname = "system_live";


$error_log_file = "age_group_update_report.txt";

// Function to log errors to a file and echo them for debugging purposes
function logError($error_message) {
    global $error_log_file;
    $current_date = date("Y-m-d H:i:s");
    file_put_contents($error_log_file, $current_date . ": " . $error_message . "\n", FILE_APPEND);
    echo $current_date . ': ' . $error_message . "\n";
}

// Establishing the database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    logError("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Function to get count of players in each age group
function getAgeGroupCounts($conn) {
    $counts = [];
    $result = $conn->query("SELECT player_age_group_id, COUNT(*) as count FROM players GROUP BY player_age_group_id");
    while($row = $result->fetch_assoc()) {
        $counts[$row['player_age_group_id']] = $row['count'];
    }
    return $counts;
}

// Function to update age groups in batches
function updateAgeGroupsInBatches($conn, $batchSize) {
    $offset = 0;
    $totalUpdated = 0;

    while (true) {
        $update_age_group_sql = "
            UPDATE players p
            JOIN (
                SELECT p1.player_id, 
                       MAX(pag.player_age_group_id) AS new_age_group_id
                FROM players p1
                JOIN player_age_group pag ON p1.species_id = pag.species_id 
                                         AND p1.player_age >= pag.age_group_required_age
                JOIN player_status ps ON p1.player_status_id = ps.player_status_id 
                                         AND ps.player_status_keyword = 'alive'
                GROUP BY p1.player_id
                LIMIT $batchSize OFFSET $offset
            ) AS age_group_updates ON p.player_id = age_group_updates.player_id
            SET p.player_age_group_id = age_group_updates.new_age_group_id
        ";

        if ($conn->query($update_age_group_sql)) {
            $affectedRows = $conn->affected_rows;
            if ($affectedRows == 0) {
                break; // Exit the loop if no more rows to update
            }

            $totalUpdated += $affectedRows;
            $offset += $batchSize;
        } else {
            logError("Error updating age groups in batch: " . $conn->error);
            break;
        }
    }

    return $totalUpdated;
}

// Get initial counts
$initialCounts = getAgeGroupCounts($conn);

// Define batch size and perform batch update
$batchSize = 1000; // Adjust as needed
$totalUpdated = updateAgeGroupsInBatches($conn, $batchSize);

// Get updated counts
$updatedCounts = getAgeGroupCounts($conn);

// Build the report
$report = "----------------------\nREPORT\n----------------------\n";
$report .= "Initial Age Group Counts:\n";
foreach($initialCounts as $age_group_id => $count) {
    $report .= "Age Group $age_group_id: $count players\n";
}
$report .= "\nUpdated Age Group Counts:\n";
foreach($updatedCounts as $age_group_id => $count) {
    $report .= "Age Group $age_group_id: $count players\n";
}
$report .= "\nTotal players updated: $totalUpdated\n";

file_put_contents($error_log_file, $report, FILE_APPEND);
echo $report;

$conn->close();

?>
