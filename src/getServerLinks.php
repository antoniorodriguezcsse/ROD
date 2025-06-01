<?php
// Define global variables for database connection
$host = "localhost";
$database = "system_live";
$username = "system_live";
$password = "tCbDGabdZ8w58H7e";

try {
    // Establish the database connection using global variables
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);

    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL query to select all data from the "links_to_server" table
    $sql = "SELECT * FROM links_to_server";

    // Prepare the SQL statement
    $stmt = $pdo->prepare($sql);

    // Execute the query
    $stmt->execute();

    // Fetch all the rows as an associative array
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert the data to JSON
    $jsonResponse = json_encode($result);

    // Set the content type to JSON
    header('Content-Type: application/json');

    // Output the JSON response
    echo $jsonResponse;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

