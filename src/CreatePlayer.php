<?php

// Get the raw JSON data from the request body
$jsonData = file_get_contents("php://input");

// Check if JSON data was received
if ($jsonData === false) {
    // Handle the error, e.g., by returning an error response
    echo "Error reading JSON data from request.";
    exit;
}

// Decode the JSON data
$requestData = json_decode($jsonData, true);
// Check if decoding was successful
if ($requestData === null) {
    // Handle the error, e.g., by returning an error response
    echo "Error decoding JSON data.";
    exit;
}

if (isset($requestData['api_key'])){
    
    // Extract data from the JSON array
    $legacy_name = $requestData["legacy_name"];
    $player_uuid = $requestData["player_uuid"];
    $species_id = $requestData["species_id"];
    $player_status_id = $requestData["player_status_id"];
    
    // Database connection information
    $servername = "localhost";
    $username = "system_live";
    $password = "tCbDGabdZ8w58H7e";
    $database = "system_live";
    
    // Create a database connection
    $conn = new mysqli($servername, $username, $password, $database);
    
    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // SQL query to check if the player_id exists in the players table
    $checkQuery = "SELECT COUNT(*) AS count FROM players WHERE player_uuid = ?";
    
    // Prepare the query
    $stmt = $conn->prepare($checkQuery);
    
    // Bind the player_id value to the query
    $stmt->bind_param("s", $player_uuid);
    
    // Execute the query
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    
    // Fetch the count
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    // Check if the player_id exists
    if ($count > 0) {
        echo "Player exist.";
        $conn->close();
        exit;
    }
    else {
        // API key is valid, proceed with inserting data into the "player" table
        $insertQuery = "INSERT INTO players (legacy_name, player_uuid, species_id, player_status_id)
                        VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        if (!$stmt) {
            // Handle the case where statement preparation failed
            echo "Error preparing the SQL statement: " . $conn->error;
            $conn->close();
            exit;
        }
        $stmt->bind_param("ssii", $legacy_name, $player_uuid, $species_id, $player_status_id);
        
        if ($stmt->execute()) {
            // Data was successfully inserted
            echo "Data inserted successfully.";
        } else {
            // Handle the case where data insertion failed
            echo "Error inserting data: " . $stmt->error;
        }
    }
    
    // Close the database connection
    $conn->close();
}
?>
