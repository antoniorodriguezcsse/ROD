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

// Check if the "api_key" parameter is present in the JSON data
if (isset($requestData['api_key'])) {
    $providedApiKey = $requestData['api_key'];

    $servername = "localhost";
    $database = "system_live";
    $username = "system_live";
    $password = "tCbDGabdZ8w58H7e";
    
    // Create a database connection
    $conn = new mysqli($servername, $username, $password, $database);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Validate the API key
    $apiKeyQuery = "SELECT * FROM api_key WHERE api_key = ?";
    $stmt = $conn->prepare($apiKeyQuery);
    $stmt->bind_param("s", $providedApiKey);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // API key is invalid
        echo "Invalid API key. Access denied.";
        $conn->close();
        exit;
    }

    // API key is valid, proceed with retrieving species data
    $speciesQuery = "SELECT species_id, species_type FROM species";
    $speciesResult = $conn->query($speciesQuery);

    if ($speciesResult->num_rows > 0) {
        $speciesData = array();

        // Fetch and store species data
        while ($row = $speciesResult->fetch_assoc()) {
            $speciesData[] = $row;
        }

        // Return species data as JSON response
        header('Content-Type: application/json');
        echo json_encode($speciesData);
    } else {
        // Handle the case where no species data is found
        echo "No species data found.";
    }

    // Close the database connection
    $conn->close();
} else {
    // Handle the case where the "api_key" parameter is missing in the JSON data
    echo "API key not found in JSON data.";
}
?>
