<?php
// BloodBankController.php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;

//use PDOException;
class BloodBankController
{

    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public static function getPlayerTotalBlood(PDO $pdo, $playerUUID)
    {
        try {
            $playerID = self::getPlayerIdFromUuid($pdo, $playerUUID);

            // Fetch total blood
            $stmt = $pdo->prepare("SELECT total_blood FROM blood_bank_totals WHERE player_id = ?");
            $stmt->execute([$playerID]);
            $bloodResult = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch player status keyword and current status
            $statusStmt = $pdo->prepare("
                SELECT ps.player_status_keyword, ps.player_current_status
                FROM players p
                INNER JOIN player_status ps ON p.player_status_id = ps.player_status_id
                WHERE p.player_id = ?
            ");
            $statusStmt->execute([$playerID]);
            $statusResult = $statusStmt->fetch(PDO::FETCH_ASSOC);

            // Check and respond with total blood and status
            if ($bloodResult && $statusResult) {
                return new JsonResponse(200, "Player's total blood and status fetched successfully", [
                    'total_blood' => $bloodResult['total_blood'],
                    'player_status_keyword' => $statusResult['player_status_keyword'],
                    'player_current_status' => $statusResult['player_current_status'],
                ]);
            } elseif (!$bloodResult && $statusResult) {
                // Initialize total blood to 0 if not present
                $stmt = $pdo->prepare("INSERT INTO blood_bank_totals (player_id, total_blood) VALUES (?, 0)");
                $stmt->execute([$playerID]);
                return new JsonResponse(200, "Player's total blood initialized to 0", [
                    'total_blood' => 0,
                    'player_status_keyword' => $statusResult['player_status_keyword'],
                    'player_current_status' => $statusResult['player_current_status'],
                ]);
            } else {
                return new JsonResponse(404, "Player not found or status not available");
            }
        } catch (Exception $e) {
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    public static function depositBlood(PDO $pdo, $playerUUID, $amount, $manageTransaction = true)
    {
        if ($manageTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $playerID = self::getPlayerIdFromUuid($pdo, $playerUUID);

            // Validate the amount for being a positive number
            $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);
            if ($amount === false || $amount <= 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new Exception("Invalid or negative amount format.");
            }

            $currentHealth = self::getPlayerCurrentHealth($pdo, $playerID);
            if ($currentHealth - $amount <= 0.0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return new JsonResponse(400, "Deposit amount is too much, player would die");
            }

            // Decrease player's current health
            $stmt = $pdo->prepare("UPDATE players SET player_current_health = player_current_health - ? WHERE player_id = ?");
            $stmt->execute([$amount, $playerID]);

            // Record the transaction
            $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, transaction_type) VALUES (?, ?, 'deposit')");
            $stmt->execute([$playerID, $amount]);

            // Update the player's total blood in the bank
            $stmt = $pdo->prepare("UPDATE blood_bank_totals SET total_blood = total_blood + ? WHERE player_id = ?");
            $stmt->execute([$amount, $playerID]);

            if ($manageTransaction) {
                $pdo->commit();
            }

            // Log the activity without waiting for a response
            $activityName = "use_blood_bank"; // Replace with the actual activity name
            ActivityController::logActivity($pdo, $playerUUID, $activityName);

            CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "update_health"]);
            return new JsonResponse(200, "Deposit successful", ["deposited_amount" => $amount]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    // public static function withdrawBlood(PDO $pdo, $playerUUID, $amount, $manageTransaction = true)
    // {
    //     try {
    //         $playerID = self::getPlayerIdFromUuid($pdo, $playerUUID);
    //         $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);

    //         // Fetch the player's total blood in the bank
    //         $totalBloodInBankResponse = self::getPlayerTotalBlood($pdo, $playerUUID);
    //         $totalBloodInBankData = json_decode($totalBloodInBankResponse, true);
    //         $totalBloodInBank = $totalBloodInBankData['extra']['total_blood'] ?? 0;

    //         if ($amount > $totalBloodInBank) {
    //             return new JsonResponse(400, "Insufficient blood in bank for withdrawal");
    //         }

    //         // Fetch current health and max health
    //         $currentHealth = self::getPlayerCurrentHealth($pdo, $playerID);
    //         $maxHealth = self::getMaxHealthForAgeGroup($pdo, $playerID);

    //         if ($currentHealth >= $maxHealth) {
    //             return new JsonResponse(400, "Player is already at maximum health");
    //         }

    //         $permissibleWithdrawal = min($amount, $maxHealth - $currentHealth);

    //         $value = round((float)$permissibleWithdrawal, 2);
    //         if (abs($value) < 0.01) {
    //             $permissibleWithdrawal = 0.00;
    //             echo "1.$permissibleWithdrawal\n";
    //         } else {
    //             $permissibleWithdrawal = number_format($value, 2, '.', '');
    //             echo "2.$value\n";
    //         }

    //         if ($amount === false || $amount <= 0) {
    //             throw new Exception("Invalid or negative amount format.");
    //         }
    //         if ($permissibleWithdrawal <= 0) {
    //             return new JsonResponse(400, "Withdrawal amount is too high");
    //         }

    //         if ($manageTransaction) {
    //             $pdo->beginTransaction();
    //         }

    //         // Update player's current health
    //         $stmt = $pdo->prepare("UPDATE players SET player_current_health = player_current_health + ? WHERE player_id = ?");
    //         $stmt->execute([$permissibleWithdrawal, $playerID]);

    //         // Record the withdrawal transaction
    //         $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, transaction_type) VALUES (?, ?, 'withdrawal')");
    //         $stmt->execute([$playerID, -$permissibleWithdrawal]);

    //         // Update the player's total blood in the bank
    //         $stmt = $pdo->prepare("UPDATE blood_bank_totals SET total_blood = total_blood - ? WHERE player_id = ?");
    //         $stmt->execute([$permissibleWithdrawal, $playerID]);

    //         if ($manageTransaction) {
    //             $pdo->commit();
    //         }

    //         // Notify player HUD
    //         CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "update_health"]);

    //         return new JsonResponse(200, "Withdrawal successful", ["withdrawn_amount" => $permissibleWithdrawal]);
    //     } catch (Exception $e) {
    //         if ($pdo->inTransaction()) {
    //             $pdo->rollBack();
    //         }
    //         return new JsonResponse(500, "Error: " . $e->getMessage());
    //     }
    // }

    public static function withdrawBlood(PDO $pdo, $playerUUID, $amount, $manageTransaction = true)
    {
        try {
            $playerID = self::getPlayerIdFromUuid($pdo, $playerUUID);
            $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);

            // Fetch the player's total blood in the bank
            $totalBloodInBankResponse = self::getPlayerTotalBlood($pdo, $playerUUID);
            $totalBloodInBankData = json_decode($totalBloodInBankResponse, true);
            $totalBloodInBank = $totalBloodInBankData['extra']['total_blood'] ?? 0;

            if ($amount > $totalBloodInBank) {
                return new JsonResponse(400, "Insufficient blood in bank for withdrawal");
            }

            // Fetch current health and max health
            $currentHealth = self::getPlayerCurrentHealth($pdo, $playerID);
            $maxHealth = self::getMaxHealthForAgeGroup($pdo, $playerID);

            if ($currentHealth >= $maxHealth) {
                return new JsonResponse(400, "Player is already at maximum health");
            }

            $permissibleWithdrawal = min($amount, $maxHealth - $currentHealth);

            // Round and format the permissible withdrawal
            $value = round((float) $permissibleWithdrawal, 2);
            if (abs($value) < 0.01) {
                $permissibleWithdrawal = 0.00;
            } else {
                $permissibleWithdrawal = number_format($value, 2, '.', '');
            }

            if ($amount === false || $amount <= 0) {
                throw new Exception("Invalid or negative amount format.");
            }
            if ($permissibleWithdrawal <= 0) {
                return new JsonResponse(400, "Withdrawal amount is too high");
            }

            if ($manageTransaction) {
                $pdo->beginTransaction();
            }

            // Update player's current health
            $stmt = $pdo->prepare("UPDATE players SET player_current_health = player_current_health + ? WHERE player_id = ?");
            $stmt->execute([$permissibleWithdrawal, $playerID]);

            // Record the withdrawal transaction
            $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, transaction_type) VALUES (?, ?, 'withdrawal')");
            $stmt->execute([$playerID, -$permissibleWithdrawal]);

            // Update the player's total blood in the bank
            $stmt = $pdo->prepare("UPDATE blood_bank_totals SET total_blood = total_blood - ? WHERE player_id = ?");
            $stmt->execute([$permissibleWithdrawal, $playerID]);

            if ($manageTransaction) {
                $pdo->commit();
            }

            // Notify player HUD
            CommunicationController::sendDataToPlayersHud($pdo, $playerUUID, ["status" => "200", "message" => "update_health"]);

            return new JsonResponse(200, "Withdrawal successful", ["withdrawn_amount" => $permissibleWithdrawal]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    public static function transferBlood(PDO $pdo, $donorUUID, $recipientUUID, $amount)
    {
        try {
            $pdo->beginTransaction();

            // Validate the transfer amount
            $amount = filter_var($amount, FILTER_VALIDATE_FLOAT);
            if ($amount === false || $amount <= 0) {
                $pdo->rollBack();
                throw new Exception("Invalid or negative amount format.");
            }
            //CommunicationController::postToDiscordErrorLog("blood bank transfer: $amount");
            // Withdraw blood from the donor's bank and record the transaction
            // Note: Here, the donor is the sender, and the recipient is the receiver
            $withdrawResponse = self::adjustPlayerBankBalance($pdo, $donorUUID, -$amount, $recipientUUID, 'transfer', false);
            if ($withdrawResponse->getStatus() !== 200) {
                $pdo->rollBack();
                return $withdrawResponse; // Withdrawal failed, return error
            }

            // Deposit blood into the recipient's bank and record the transaction
            // Note: For the deposit, the recipient is the main party, and the donor is the sender
            $depositResponse = self::adjustPlayerBankBalance($pdo, $recipientUUID, $amount, $donorUUID, 'transfer', false);
            if ($depositResponse->getStatus() !== 200) {
                $pdo->rollBack();
                return $depositResponse; // Deposit failed, return error
            }

            $pdo->commit();
            return new JsonResponse(200, "Transfer successful", ["transferred_amount" => $amount]);
        } catch (Exception $e) {
            $pdo->rollBack();
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    private static function adjustPlayerBankBalance(PDO $pdo, $playerUUID, $amount, $otherPartyUUID = null, $transactionType = 'deposit', $manageTransaction = true)
    {
        if ($manageTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $playerID = self::getPlayerIdFromUuid($pdo, $playerUUID);
            $otherPartyID = null;
            if ($otherPartyUUID !== null) {
                $otherPartyID = self::getPlayerIdFromUuid($pdo, $otherPartyUUID);
            }

            // Fetch current bank balance
            $currentBankBalanceResponse = self::getPlayerTotalBlood($pdo, $playerUUID);
            $currentBankBalanceData = json_decode($currentBankBalanceResponse, true);

            if ($currentBankBalanceData && isset($currentBankBalanceData['extra']['total_blood'])) {
                $currentBankBalance = $currentBankBalanceData['extra']['total_blood'];
            } else {
                if ($manageTransaction) {
                    $pdo->rollBack();
                }
                return new JsonResponse(400, "Unable to retrieve total blood balance.");
            }

            $newBankBalance = $currentBankBalance + $amount;
            //CommunicationController::postToDiscordErrorLog("blood bank balance: $currentBankBalance, amount: $amount, new balance: $newBankBalance");

            if ($newBankBalance < 0) {
                if ($manageTransaction) {
                    $pdo->rollBack();
                }
                return new JsonResponse(400, "Insufficient blood in bank for transaction");
            }

            // Update the player's total blood in the bank
            $stmt = $pdo->prepare("UPDATE blood_bank_totals SET total_blood = ? WHERE player_id = ?");
            $stmt->execute([$newBankBalance, $playerID]);

            // Record the transaction with the appropriate sender and recipient based on the transaction type and amount
            if ($transactionType == 'transfer') {
                if ($amount < 0) {
                    // Withdrawal transaction - only recipient_id is set
                    $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, recipient_id, transaction_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$playerID, $amount, $otherPartyID, $transactionType]);
                } else {
                    // Deposit transaction - only sender_id is set
                    $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, sender_id, transaction_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$playerID, $amount, $otherPartyID, $transactionType]);
                }
            } else {
                // Non-transfer transactions
                $stmt = $pdo->prepare("INSERT INTO blood_bank_transactions (player_id, amount, transaction_type) VALUES (?, ?, ?)");
                $stmt->execute([$playerID, $amount, $transactionType]);
            }

            if ($manageTransaction) {
                $pdo->commit();
            }

            return new JsonResponse(200, "Bank balance adjusted successfully");
        } catch (Exception $e) {
            if ($manageTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    public static function getLeadersByTimePeriod(PDO $pdo, $timePeriod, $limit = 10) // Default limit set to 10

    {
        try {
            $endDate = new \DateTime(); // Current date and time
            $startDate = new \DateTime(); // Default start date, to be adjusted

            switch ($timePeriod) {
                case 'day':
                    $startDate->setTime(0, 0, 0);
                    break;
                case 'week':
                    $startDate->modify('last monday')->setTime(0, 0, 0);
                    break;
                case 'month':
                    $startDate->modify('first day of this month')->setTime(0, 0, 0);
                    break;
                case 'all':
                    $startDate = new \DateTime("1970-01-01"); // Arbitrary early date to cover all records
                    break;
                default:
                    return new JsonResponse(400, "Invalid time period specified");
            }

            $stmt = $pdo->prepare("
            SELECT
                sub.legacy_name,
                sub.total_blood_banked
            FROM (
                SELECT
                    p.legacy_name,
                    ROUND(SUM(CASE
                        WHEN bbt.transaction_type = 'transfer' AND bbt.amount > 0 THEN 0
                        ELSE bbt.amount
                    END), 2) AS total_blood_banked
                FROM
                    blood_bank_transactions bbt
                INNER JOIN
                    players p ON bbt.player_id = p.player_id
                WHERE
                    bbt.transaction_date BETWEEN ? AND ?
                GROUP BY
                    p.legacy_name
            ) AS sub
            WHERE sub.total_blood_banked > 0
            ORDER BY
                sub.total_blood_banked DESC
            LIMIT ?
        ");
            $stmt->execute([$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'), $limit]);
            $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return new JsonResponse(200, "Leaderboard fetched successfully", $leaders);
        } catch (Exception $e) {
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }

    private static function getPlayerIdFromUuid(PDO $pdo, $playerUUID)
    {
        $stmt = $pdo->prepare("SELECT player_id FROM Players WHERE player_uuid = ?");
        $stmt->execute([$playerUUID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['player_id'];
        } else {
            throw new Exception("Player not found");
        }
    }

    private static function hasEnoughBlood(PDO $pdo, $playerID, $amount)
    {
        // Correct the table name to 'blood_bank_transactions'
        $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM blood_bank_transactions WHERE player_id = ?");
        $stmt->execute([$playerID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the total amount is greater or equal to the requested amount
        return $result && $result['total'] >= $amount;
    }

    private static function isFloat($value)
    {
        if (is_float($value)) {
            return true;
        }
        if (is_string($value)) {
            return floatval($value) && (floatval($value) != 0 || $value == "0" || $value == "0.0");
        }
        return false;
    }
    private static function getMaxHealthForAgeGroup(PDO $pdo, $playerID)
    {
        $stmt = $pdo->prepare("SELECT pag.max_health FROM players p JOIN player_age_group pag ON p.player_age_group_id = pag.player_age_group_id WHERE p.player_id = ?");
        $stmt->execute([$playerID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['max_health'] : null;
    }

    private static function getPlayerCurrentHealth(PDO $pdo, $playerID)
    {
        $stmt = $pdo->prepare("SELECT player_current_health FROM players WHERE player_id = ?");
        $stmt->execute([$playerID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['player_current_health'] : null;
    }

}
