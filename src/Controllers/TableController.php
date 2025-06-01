<?php
namespace Fallen\SecondLife\Controllers;
use Fallen\SecondLife\Classes\Db; 
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;
use PDOException;
use Exception;


class TableController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // $result = TableController::getForeignKeyData($pdo, 'players', 'player_uuid', $playerUUID, 'player_age_group_id', 'player_age_group');
    // $resultArray = json_decode($result, true);
    // echo  $resultArray['max_health'];
    //the commented code will get the player max_health 
    public static function getForeignKeyData($pdo, $tableName, $conditionColumn, $conditionValue, $foreignKeyColumn, $referenceTable) {
        try {
            // Set the connection character set to UTF-8
            $pdo->exec("SET NAMES utf8mb4");
    
            $query = "SELECT $referenceTable.* 
                      FROM $tableName
                      INNER JOIN $referenceTable ON $tableName.$foreignKeyColumn = $referenceTable.$foreignKeyColumn
                      WHERE $tableName.$conditionColumn = :conditionValue 
                      LIMIT 1";
    
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':conditionValue', $conditionValue, PDO::PARAM_STR);
            $stmt->execute();
    
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($result) {
                // Ensure your web server and client handle Unicode
                // For example, set the appropriate content type header:
                // header('Content-Type: application/json; charset=utf-8');
                return json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                return json_encode(['error' => 'No data found'], JSON_UNESCAPED_UNICODE);
            }
        } catch (PDOException $e) {
            return json_encode(['error' => 'Database Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public static function getPlayerFieldValue($pdo, $playerUuid, $fieldName){
        try {
            $query = "SELECT $fieldName
                      FROM players
                      WHERE player_uuid = :playerUuid
                      LIMIT 1";

            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':playerUuid', $playerUuid, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result[$fieldName];
        } catch (PDOException $e) {
            return ['error' => 'Database Error: ' . $e->getMessage()];
        }
    }
    
    public static function getFieldValue($pdo, $tableName, $conditionField, $conditionValue, $targetField)
    {
        try {
            // Prepare the SQL statement
            $query = "SELECT {$targetField} FROM {$tableName} WHERE {$conditionField} = :conditionValue LIMIT 1";
            $stmt = $pdo->prepare($query);
            
            // Bind the condition value parameter
            $stmt->bindParam(':conditionValue', $conditionValue);
            
            // Execute the query
            $stmt->execute();
            
            // Fetch the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Return the target field value if found
            if ($result && isset($result[$targetField])) {
                return $result[$targetField];
            }
            
            // If not found, return null
            return null;
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            return null;
        }
    }
 
    public static function updateData($pdo, $tableName, $playerUuid, $dataToUpdate) {
        try {
            // ==============================
            // STEP 1: INPUT VALIDATION
            // Ensure the table name, column names, and UUID are valid to protect 
            // against SQL injection. Implement your validation logic here.
            // (Note: Actual implementation of validation is not provided here)
            // ==============================
    
            // ==============================
            // STEP 2: DATABASE CONNECTION
            // Utilize connection details stored in class static variables to 
            // establish a PDO connection with the MySQL database.
            // ==============================
            
    
            // ==============================
            // STEP 3: CHECK UUID EXISTENCE
            // Before attempting an update, ensure that a record with the 
            // specified UUID exists in the database.
            // ==============================
            $checkQuery = "SELECT 1 FROM $tableName WHERE player_uuid = :player_uuid";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            $checkStmt->execute();
            if ($checkStmt->rowCount() == 0) {
                return new JsonResponse(400, "Player UUID does not exist.");
            }
    
            // ==============================
            // STEP 4: CONSTRUCT SET CLAUSE
            // Generate the SET clause for the SQL query by iterating over
            // the $dataToUpdate array and creating "column = :placeholder" pairs.
            // ==============================
            $setClause = [];
            foreach ($dataToUpdate as $column => $value) {
                $setClause[] = "$column = :$column";
            }
            $setClauseStr = implode(", ", $setClause);
    
            // ==============================
            // STEP 5: PREPARE UPDATE QUERY
            // Create the SQL UPDATE query with placeholders to prevent 
            // SQL injection.
            // ==============================
            $updateQuery = "UPDATE $tableName SET $setClauseStr WHERE player_uuid = :player_uuid";
            $updateStmt = $pdo->prepare($updateQuery);
    
            // ==============================
            // STEP 6: BIND PARAMETER VALUES
            // Replace placeholders with actual data in a safe manner.
            // ==============================
            $updateStmt->bindParam(':player_uuid', $playerUuid, PDO::PARAM_STR);
            foreach ($dataToUpdate as $column => $value) {
                $updateStmt->bindParam(":$column", $dataToUpdate[$column]);
            }
    
            // ==============================
            // STEP 7: EXECUTE THE QUERY
            // Run the SQL query on the database.
            // ==============================
            $updateStmt->execute();
    
            // ==============================
            // STEP 8: VERIFY UPDATE SUCCESS
            // Check whether the query affected any rows and return a 
            // corresponding message.
            // ==============================
            if ($updateStmt->rowCount() > 0) {
                return new JsonResponse(200, "Data updated successfully.");
            } else {
                // Check whether the UUID exists in the database.
                $checkStmt->execute();
                if ($checkStmt->rowCount() == 0) {
                    // UUID does not exist, provide a specific error message.
                    return new JsonResponse(404, "No data updated. The player UUID does not exist.");
                } else {
                    // UUID exists, so the data was identical to existing data.
                    return new JsonResponse(409, "No data updated. The provided data is the same as existing data.");
                }
            }
        } catch (PDOException $e) {
            // ==============================
            // STEP 9: ERROR HANDLING
            // Log any errors that occur and return a user-friendly message.
            // ==============================
            error_log("Database Error: " . $e->getMessage());
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    } 
    
    public static function insertRowIntoTableWithData($pdo, $tableName, $dataToInsert) {
        try {
            // Establish a database connection
            
            // Construct the SQL INSERT statement
            $columns = implode(", ", array_keys($dataToInsert));
            $placeholders = ":" . implode(", :", array_keys($dataToInsert));
            
            $sql = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
    
            // Bind values
            foreach ($dataToInsert as $column => $value) {
                $stmt->bindValue(":$column", $value);
            }
    
            // Execute the statement
            if (!$stmt->execute()) {
                $error = $stmt->errorInfo();
                error_log("Insertion Error: " . $error[2]); // Log the error
                return new JsonResponse(500, "Insertion Error: " . $error[2]);
            }
    
            // Check last insert ID to verify insertion
            $lastId = $pdo->lastInsertId();
            if ($lastId) {
                return new JsonResponse(201, "Data inserted successfully. Last inserted ID: $lastId");
            } else {
                return new JsonResponse(500, "Insertion was not successful.");
            }
    
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage()); // Log the error
            return new JsonResponse(500, "Database Error: " . $e->getMessage());
        }
    }


// $tableName = "tracked_essence_container";
// $primaryKeyField = "essence_container_uuid";
// $primaryKeyValue = "your_uuid_here";
// $fieldToUpdate = "total_essence";
// $newValue = 100; // Replace with your desired value

    public static function updateTableData($pdo, $tableName, $primaryKeyField, $primaryKeyValue, $fieldToUpdate, $newValue) {
        try {
            // Prepare the SQL statement
            $sql = "UPDATE $tableName SET $fieldToUpdate = :newValue WHERE $primaryKeyField = :primaryKeyValue";
    
            // Prepare the PDO statement
            $stmt = $pdo->prepare($sql);
    
            // Bind parameters
            $stmt->bindParam(':newValue', $newValue, PDO::PARAM_STR);
            $stmt->bindParam(':primaryKeyValue', $primaryKeyValue, PDO::PARAM_STR);
    
            // Execute the update
            $stmt->execute();
    
            // Check for success
            $rowCount = $stmt->rowCount();
            if ($rowCount > 0) {
                return "Successfully updated.";
            } else {
                return "No records updated.";
            }
        } catch (PDOException $e) {
            return "Error updating $fieldToUpdate: " . $e->getMessage();
        }
    }

}   

