<?php

namespace Fallen\SecondLife\Controllers;

use Fallen\SecondLife\Classes\JsonResponse;
use Fallen\SecondLife\Controllers\TableController;

use PDO;
use PDOException;

///class
class VersionsController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // get the version number by name
    public static function getVersionNumberByName($pdo, $versionName) {
        try {
            // Prepare the SQL query to select version_number from the version table
            $query = $pdo->prepare("SELECT version_number FROM `version` WHERE version_name = :versionName LIMIT 1");
            $query->bindParam(':versionName', $versionName, PDO::PARAM_STR);
            $query->execute();
    
            // Fetch the result
            $result = $query->fetch(PDO::FETCH_ASSOC);

            // Check if a result was found
            if ($result) {
                return $result['version_number'];
            } else {
                return null; // Version name not found or version number not set
            }
        } catch (PDOException $e) {
            // Handle any database errors here
            error_log("Database Error: " . $e->getMessage());
            return null; // Return null in case of an error
        }
    }


}