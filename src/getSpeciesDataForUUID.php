<?php
// Replace with your database connection details
$servername = "localhost";
$username = "system_live";
$password = "tCbDGabdZ8w58H7e";
$dbname = "system_live";

// Your player UUID
$playerUUID = 'ea2be1e7-07d4-446e-b94e-a7ddd5f6d1bd';

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to retrieve data for the "vampire" species associated with the player UUID
$sql = "SELECT s.*
        FROM species s
        INNER JOIN players p ON s.species_type COLLATE utf8_unicode_ci = p.species_type COLLATE utf8_unicode_ci
        WHERE p.player_uuid = '$playerUUID' AND p.species_type = 'vampire'
        LIMIT 0, 25";

$result = $conn->query($sql);

$response = array();

if ($result->num_rows > 0) {
    // Fetch the data and add it to the response array
    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
} else {
    $response['message'] = "No data found for the 'vampire' species or player UUID.";
}

// Close the database connection
$conn->close();

// Set the response content type to JSON
header('Content-Type: application/json');

// Output the response as JSON
echo json_encode($response);
?>
