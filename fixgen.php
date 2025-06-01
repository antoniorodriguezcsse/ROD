<?php
/*
 * fix_generation.php  —  mysqli version (cycle‑safe, self‑loop cleaner)
 * -----------------------------------------------------------
 * • Re‑homes all players whose `sire_id` equals OLD_SIRE_ID under NEW_SIRE_ID.
 * • Sets any self‑looping sire relationships (player_id == sire_id) to NULL
 *   so true roots have no sire.
 * • Recalculates `player_generation` so every bloodline leader and every ID
 *   in EXTRA_ROOTS is generation 0.
 * • Cycle‑proof: the recursive CTE uses UNION (distinct) and excludes loops.
 *
 * MySQL ≥ 8.0.34 required.
 * Run:  php fix_generation.php
 */

// ──────────────────────────────────────────────────
// ❶ Database credentials
// ──────────────────────────────────────────────────
$servername = "localhost";
$username = "system_live";
$password = "eymLXdmbxibzf87P";
$dbname = "system_live";

// ──────────────────────────────────────────────────
// ❷ Business rules
// ──────────────────────────────────────────────────
const OLD_SIRE_ID = 116;
const NEW_SIRE_ID = 248;
const EXTRA_ROOTS = [1, 40, 116];

// ──────────────────────────────────────────────────
// Helper: run query and die on error
// ──────────────────────────────────────────────────
function run(mysqli $db, string $sql): int
{
    if (!$db->query($sql))
    {
        die("SQL error: {$db->error}\nSQL: $sql\n");
    }
    return $db->affected_rows;
}

// ──────────────────────────────────────────────────
// ❸ Connect via mysqli
// ──────────────────────────────────────────────────
$db = new mysqli($servername, $username, $password, $dbname);
if ($db->connect_error)
{
    die("Connection failed: {$db->connect_error}\n");
}

// Bump recursion limit to 20 000 for this session
run($db, 'SET SESSION cte_max_recursion_depth = 20000');

// ──────────────────────────────────────────────────
// ❹ Clean self‑loops BEFORE any other work
// ──────────────────────────────────────────────────
$selfLoops = run($db, 'UPDATE players SET sire_id = NULL WHERE player_id = sire_id');
echo "Fixed $selfLoops self‑looping sire relationship(s).\n";

// ──────────────────────────────────────────────────
// ❺ Move children of OLD_SIRE_ID → NEW_SIRE_ID
// ──────────────────────────────────────────────────
$preChildren = $db->query('SELECT COUNT(*) AS c FROM players WHERE sire_id = ' . OLD_SIRE_ID)
    ->fetch_assoc()['c'];

echo "Children of " . OLD_SIRE_ID . " before move: $preChildren\n";

$moved = $db->query('UPDATE players SET sire_id = ' . NEW_SIRE_ID . ' WHERE sire_id = ' . OLD_SIRE_ID)
    ? $db->affected_rows : 0;

echo "Moved $moved player(s) from sire " . OLD_SIRE_ID . " to " . NEW_SIRE_ID . "\n";

$afterChildren = $db->query('SELECT COUNT(*) AS c FROM players WHERE sire_id = ' . OLD_SIRE_ID)
    ->fetch_assoc()['c'];

echo "Children of " . OLD_SIRE_ID . " after  move: $afterChildren\n\n";

// ──────────────────────────────────────────────────
// ❻ Rebuild player_generation (cycle‑safe CTE)
// ──────────────────────────────────────────────────
$cteSql = "WITH RECURSIVE\n" .
    "extra_roots AS (\n    SELECT " . EXTRA_ROOTS[0] . " AS player_id UNION ALL\n    SELECT " . EXTRA_ROOTS[1] . " UNION ALL\n    SELECT " . EXTRA_ROOTS[2] . "\n),\n" .
    "roots AS (\n    SELECT DISTINCT bloodline_leader_id AS player_id\n    FROM   bloodlines\n    WHERE  bloodline_leader_id IS NOT NULL\n    UNION ALL\n    SELECT player_id FROM extra_roots\n),\n" .
    "ancestry AS (\n    SELECT p.player_id, p.sire_id, 0 AS gen\n    FROM   players p\n    JOIN   roots   r ON p.player_id = r.player_id\n\n    UNION\n\n    SELECT c.player_id, c.sire_id, a.gen + 1\n    FROM   players   c\n    JOIN   ancestry  a ON c.sire_id = a.player_id\n    WHERE  c.player_id <> a.player_id\n)\nUPDATE players\nJOIN ancestry USING (player_id)\nSET  player_generation = ancestry.gen;";

$updated = run($db, $cteSql);
echo "Updated generation for $updated player(s).\n\n";

// ──────────────────────────────────────────────────
// ❼ Sanity check
// ──────────────────────────────────────────────────
$ids = array_merge(EXTRA_ROOTS, [NEW_SIRE_ID]);
$idsList = implode(', ', $ids);

$result = $db->query("SELECT player_id, sire_id, player_generation\n                      FROM   players\n                      WHERE  player_id IN ($idsList)\n                      ORDER BY player_id");

echo "Root & new‑sire generations:\n";
while ($row = $result->fetch_assoc())
{
    printf("ID %d | Gen %d | Sire %s\n",
        $row['player_id'],
        $row['player_generation'],
        $row['sire_id'] ?? 'NULL');
}

$db->close();
?>