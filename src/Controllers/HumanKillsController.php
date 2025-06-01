<?php
namespace Fallen\SecondLife\Controllers;

use Exception;
use Fallen\SecondLife\Classes\JsonResponse;
use PDO;

class HumanKillsController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    private static function validateUUID($uuid) {
        if (empty($uuid) || !is_string($uuid)) {
            return false;
        }
        $cleaned = preg_replace('/[^a-f0-9-]/i', '', $uuid);
        if (strlen($cleaned) !== strlen($uuid)) {
            return false;
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            return false;
        }
        return true;
    }

    public static function getLeadersByTimePeriod(PDO $pdo, $timePeriod, $page = 0, $limit = 10, $searchUUID = null)
    {
        try {
            // Sanitize and validate inputs
            $page = max(0, intval($page));
            $limit = max(1, min(50, intval($limit)));
            $offset = $page * $limit;

            if ($searchUUID !== null) {
                $searchUUID = filter_var($searchUUID, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
                if (!self::validateUUID($searchUUID)) {
                    return new JsonResponse(400, "Invalid UUID format");
                }
                $searchUUID = trim(strtolower($searchUUID));
            }

            if (!in_array($timePeriod, ['day', 'week', 'month', 'all'], true)) {
                return new JsonResponse(400, "Invalid time period specified");
            }

            // For 'all' time period, use player_statistics table
            if ($timePeriod === 'all') {
                if ($searchUUID) {
                    // Check if player exists first
                    $checkQuery = "
                        SELECT 1 FROM players WHERE player_uuid = :search_uuid
                    ";
                    $checkStmt = $pdo->prepare($checkQuery);
                    $checkStmt->bindValue(':search_uuid', $searchUUID, PDO::PARAM_STR);
                    $checkStmt->execute();
                    
                    if (!$checkStmt->fetch()) {
                        return new JsonResponse(404, "Player not found");
                    }

                    // Get rank for searched player
                    $rankQuery = "
                        SELECT 
                            (SELECT COUNT(*) FROM player_statistics ps2 
                            WHERE ps2.human_kills > ps1.human_kills) + 1 as player_rank
                        FROM player_statistics ps1
                        JOIN players p ON p.player_id = ps1.player_id
                        WHERE p.player_uuid = :search_uuid
                    ";
                    
                    $rankStmt = $pdo->prepare($rankQuery);
                    $rankStmt->bindValue(':search_uuid', $searchUUID, PDO::PARAM_STR);
                    $rankStmt->execute();
                    $rankResult = $rankStmt->fetch(PDO::FETCH_ASSOC);

                    if ($rankResult) {
                        $rank = $rankResult['player_rank'];
                        $page = floor(($rank - 1) / $limit);
                        $offset = $page * $limit;
                    }
                }

                // Main query using player_statistics
                $query = "
                    SELECT 
                        p.legacy_name,
                        ps.human_kills as total_kills
                    FROM player_statistics ps
                    JOIN players p ON p.player_id = ps.player_id
                    WHERE ps.human_kills > 0
                    ORDER BY ps.human_kills DESC, p.legacy_name ASC
                    LIMIT :limit OFFSET :offset
                ";

                $stmt = $pdo->prepare($query);
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            } else {
                // Construct date conditions based on time period
                $dateCondition = "";
                switch ($timePeriod) {
                    case 'day':
                        $dateCondition = "DATE(h.last_feed_on) = CURDATE()";
                        break;
                    case 'week':
                        $dateCondition = "YEARWEEK(h.last_feed_on) = YEARWEEK(CURDATE())";
                        break;
                    case 'month':
                        $dateCondition = "YEAR(h.last_feed_on) = YEAR(CURDATE()) AND MONTH(h.last_feed_on) = MONTH(CURDATE())";
                        break;
                }

                if ($searchUUID) {
                    // Get rank for searched player
                    $rankQuery = "
                        WITH PlayerKills AS (
                            SELECT 
                                p.player_id, 
                                COUNT(DISTINCT h.human_id) as kills
                            FROM players p
                            INNER JOIN humans h ON p.player_id = h.killer_player_id
                            WHERE {$dateCondition}
                            GROUP BY p.player_id
                        )
                        SELECT 
                            (SELECT COUNT(*) FROM PlayerKills pk2 
                            WHERE pk2.kills > pk1.kills) + 1 as player_rank
                        FROM PlayerKills pk1
                        JOIN players p ON p.player_id = pk1.player_id
                        WHERE p.player_uuid = :search_uuid
                    ";

                    $rankStmt = $pdo->prepare($rankQuery);
                    $rankStmt->bindValue(':search_uuid', $searchUUID, PDO::PARAM_STR);
                    $rankStmt->execute();
                    $rankResult = $rankStmt->fetch(PDO::FETCH_ASSOC);

                    if ($rankResult) {
                        $rank = $rankResult['player_rank'];
                        $page = floor(($rank - 1) / $limit);
                        $offset = $page * $limit;
                    }
                }

                // Time-based query using humans table
                $query = "
                    SELECT 
                        p.legacy_name,
                        COUNT(DISTINCT h.human_id) as total_kills
                    FROM players p
                    INNER JOIN humans h ON p.player_id = h.killer_player_id
                    WHERE {$dateCondition}
                    GROUP BY p.player_id, p.legacy_name
                    ORDER BY total_kills DESC, legacy_name ASC
                    LIMIT :limit OFFSET :offset
                ";

                $stmt = $pdo->prepare($query);
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            }

            $stmt->execute();
            $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countQuery = $timePeriod === 'all' 
                ? "SELECT COUNT(*) FROM player_statistics WHERE human_kills > 0"
                : "SELECT COUNT(DISTINCT p.player_id) 
                FROM players p 
                INNER JOIN humans h ON p.player_id = h.killer_player_id 
                WHERE {$dateCondition}";

            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute();

            $totalRows = $countStmt->fetchColumn();
            $totalPages = ceil($totalRows / $limit);

            return new JsonResponse(200, "Human kills leaderboard fetched successfully", [
                'leaders' => $leaders,
                'total_rows' => $totalRows,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'limit' => $limit,
                'found_rank' => $searchUUID && isset($rank) ? $rank : null
            ]);

        } catch (Exception $e) {
            error_log("Error in getLeadersByTimePeriod: " . $e->getMessage());
            return new JsonResponse(500, "Error: " . $e->getMessage());
        }
    }
}