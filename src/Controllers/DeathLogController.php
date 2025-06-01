<?php
// DeathLogController.php

namespace Fallen\SecondLife\Controllers;

ini_set('display_errors', 1);
error_reporting(E_ALL);

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use InvalidArgumentException;
use PDO;
use PDOException;

class DeathLogController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function addDeathLogEntry($pdo, $deceasedPlayerId, $killerPlayerId = null, $deathDate, $deathLocation = null, $causeOfDeath, $comments = null)
    {

        try {
            // Validate inputs
            if (!is_int($deceasedPlayerId) || $deceasedPlayerId <= 0) {
                throw new InvalidArgumentException("Invalid deceasedPlayerId: $deceasedPlayerId");
            }
            if ($killerPlayerId !== null && (!is_int($killerPlayerId) || $killerPlayerId <= 0)) {
                throw new InvalidArgumentException("Invalid killerPlayerId: $killerPlayerId");
            }
            if (!\DateTime::createFromFormat('Y-m-d H:i:s', $deathDate)) {
                throw new InvalidArgumentException("Invalid deathDate format: $deathDate");
            }
            if ($deathLocation !== null && strlen($deathLocation) > 255) {
                throw new InvalidArgumentException("deathLocation exceeds maximum length");
            }
            if ($comments !== null && strlen($comments) > 1000) {
                throw new InvalidArgumentException("comments exceed maximum length");
            }

            // Get player names
            $deceasedPlayerName = PlayerDataController::getPlayerLegacyNameByPlayerID($pdo, $deceasedPlayerId);
            $killerPlayerName = $killerPlayerId ? PlayerDataController::getPlayerLegacyNameByPlayerID($pdo, $killerPlayerId) : null;

            // Prepare and send Discord embed
            $embed = self::prepareDiscordEmbed($deceasedPlayerName, $killerPlayerName, $deathDate, $deathLocation, $causeOfDeath, $comments);
            $message = json_encode([
                "username" => "Dark Oracle",
                "avatar_url" => "https://i.imgur.com/wUI5y5B.png",
                "embeds" => [$embed],
            ]);
            self::sendDiscordWebhook($message);

            // Insert death log entry
            $sql = "INSERT INTO death_log (deceased_player_id, killer_player_id, death_date, death_location, cause_of_death, comments) VALUES (:deceasedPlayerId, :killerPlayerId, :deathDate, :deathLocation, :causeOfDeath, :comments)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':deceasedPlayerId' => $deceasedPlayerId,
                ':killerPlayerId' => $killerPlayerId,
                ':deathDate' => $deathDate,
                ':deathLocation' => $deathLocation,
                ':causeOfDeath' => $causeOfDeath,
                ':comments' => $comments,
            ]);

            $deathId = $pdo->lastInsertId();
            error_log("Death log entry added successfully. Death ID: " . $deathId);
            return new JsonResponse(200, "Death log entry added successfully", ['deathId' => $deathId]);

        } catch (InvalidArgumentException $e) {
            error_log("Validation error in addDeathLogEntry: " . $e->getMessage());
            return new JsonResponse(400, $e->getMessage());
        } catch (PDOException $e) {
            error_log("Database error in addDeathLogEntry: " . $e->getMessage());
            return new JsonResponse(500, "Database error: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Unexpected error in addDeathLogEntry: " . $e->getMessage());
            return new JsonResponse(500, "Unexpected error occurred");
        }
    }

    private static function prepareDiscordEmbed($deceasedPlayerName, $killerPlayerName, $deathDate, $deathLocation, $causeOfDeath, $comments) 
{
    // Set color based on killer status
    $color = $killerPlayerName ? 15158332 : 3447003; // Red if killer exists, Blue if unknown/null
    
    // Start with base fields
    $fields = [
        ["name" => "Deceased", "value" => $deceasedPlayerName, "inline" => true],
    ];

    // Add killer right after deceased if exists
    if ($killerPlayerName) {
        $fields[] = ["name" => "Killer", "value" => $killerPlayerName, "inline" => true];
    }

    // Add remaining fields
    $fields = array_merge($fields, [
        ["name" => "Date", "value" => $deathDate, "inline" => true],
        ["name" => "Location", "value" => $deathLocation ?? "Unknown", "inline" => true],
        ["name" => "Cause of Death", "value" => $causeOfDeath, "inline" => false],
    ]);

    $embed = [
        "title" => "Death Log Entry",
        "type" => "rich",
        "description" => "A new death has been recorded in the realm.",
        "color" => $color,
        "fields" => $fields,
        "footer" => ["text" => "May their soul find peace in the afterlife"],
        "timestamp" => date("c"),
    ];

    // Add comments if they exist
    if ($comments) {
        $embed["fields"][] = ["name" => "Additional Comments", "value" => $comments, "inline" => false];
    }

    return $embed;
}

    private static function sendDiscordWebhook($message)
    {
        try {
            $response = CommunicationController::postToDiscordDeathLog($message);
            if ($response->getStatus() !== 204) {
                error_log("Discord webhook kill log error: " . $response->getMessage());
            } else {
                error_log("Discord webhook sent successfully");
            }
        } catch (Exception $e) {
            error_log("Error sending Discord webhook: " . $e->getMessage());
        }
    }

    public static function addAttackLogEntry($pdo, $attackerId, $victimId, $damageDealt, $victimBloodBefore, $victimBloodAfter, $attackOutcome, $attackLocation = null, $additionalInfo = null)
    {
        // Validate attackerId
        if (!is_int($attackerId) || $attackerId <= 0) {
            return new JsonResponse(400, "Invalid input: attackerId must be a positive integer.");
        }

        // Validate victimId
        if (!is_int($victimId) || $victimId <= 0) {
            return new JsonResponse(400, "Invalid input: victimId must be a positive integer.");
        }

        // Validate damageDealt
        if (!is_float($damageDealt) || $damageDealt < 0) {
            return new JsonResponse(400, "Invalid input: damageDealt must be a positive float.");
        }

        // Validate victimBloodBefore
        if (!is_float($victimBloodBefore)) {
            return new JsonResponse(400, "Invalid input: victimBloodBefore must be a float.");
        }

        // Validate victimBloodAfter
        if (!is_float($victimBloodAfter)) {
            return new JsonResponse(400, "Invalid input: victimBloodAfter must be a float.");
        }

        // Validate attackOutcome
        $validOutcomes = ['damage', 'killed'];
        if (!in_array($attackOutcome, $validOutcomes)) {
            return new JsonResponse(400, "Invalid input: attackOutcome must be 'damage' or 'killed'.");
        }

        // Validate attackLocation length
        if ($attackLocation !== null && strlen($attackLocation) > 255) {
            return new JsonResponse(400, "Invalid input: attackLocation must be 255 characters or less.");
        }

        // Validate additionalInfo length (optional)
        // Assuming a maximum length of 1000 characters for additionalInfo
        if ($additionalInfo !== null && strlen($additionalInfo) > 1000) {
            return new JsonResponse(400, "Invalid input: additionalInfo must be 1000 characters or less.");
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO attack_log (attacker_id, victim_id, damage_dealt, victim_blood_before_attack, victim_blood_after_attack, attack_outcome, attack_location, additional_info) VALUES (:attackerId, :victimId, :damageDealt, :victimBloodBefore, :victimBloodAfter, :attackOutcome, :attackLocation, :additionalInfo)");

            $stmt->bindParam(':attackerId', $attackerId, PDO::PARAM_INT);
            $stmt->bindParam(':victimId', $victimId, PDO::PARAM_INT);
            $stmt->bindParam(':damageDealt', $damageDealt, PDO::PARAM_STR);
            $stmt->bindParam(':victimBloodBefore', $victimBloodBefore, PDO::PARAM_STR);
            $stmt->bindParam(':victimBloodAfter', $victimBloodAfter, PDO::PARAM_STR);
            $stmt->bindParam(':attackOutcome', $attackOutcome, PDO::PARAM_STR);
            $stmt->bindParam(':attackLocation', $attackLocation, PDO::PARAM_STR);
            $stmt->bindParam(':additionalInfo', $additionalInfo, PDO::PARAM_STR);

            $stmt->execute();
            $attackId = $pdo->lastInsertId();
            return new JsonResponse(200, "Attack log entry added successfully", ['attackId' => $attackId]);
        } catch (PDOException $e) {
            error_log("Database error in addAttackLogEntry: " . $e->getMessage());
            return new JsonResponse(500, "Internal Server Error");
        }
    }

}
