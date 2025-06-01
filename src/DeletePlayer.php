<?php
$playerIdToDelete=$_POST['peeweeHerman'];

// Database connection parameters
$servername = "localhost";
$username = "system_live";
$password = "tCbDGabdZ8w58H7e";
$database = "system_live";

// Create a MySQLi connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL query to delete the player
$sql = "DELETE FROM players WHERE player_uuid = '$playerIdToDelete'";

if ($conn->query($sql) === TRUE) {
    echo "Player with ID $playerIdToDelete deleted successfully.";
} else {
    echo "Error deleting player: " . $conn->error;
}

// Close the database connection
$conn->close();
?>
