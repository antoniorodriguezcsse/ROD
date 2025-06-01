# Subsistence Health Check Script

This script is responsible for deducting subsistence (health) from players based on their age group, status, and the number of days since their last subsistence deduction. It processes players in batches and updates their health accordingly. If a player's health drops to zero or below, the script marks them as deceased, sets their essence to zero, and logs their death in the death log table.

## Configuration

The script uses a configuration array (`$subsistenceModifiers`) to define the subsistence modifiers and update frequencies for different player statuses and sleep levels. Each status has an associated modifier value and the number of days since the last update.

Example configuration:

$subsistenceModifiers = [
    'alive' => [
        'modifier' => 1,
        'days_since_last_update' => 1,
    ],
    'sleeping' => [
        1 => [
            'modifier' => 0.5,
            'days_since_last_update' => 1,
        ],
        2 => [
            'modifier' => 0.5,
            'days_since_last_update' => 4,
        ],
    ],
];

## Functionality

The script performs the following steps:

1. Connects to the database using the provided credentials.
2. Retrieves the count of players for each age group who have a positive current health and are not in any of the skip statuses.
3. Processes the players in batches of 1000 to avoid overloading the database.
4. Deducts subsistence from the players' current health based on their status and sleep level using the configured modifiers and update frequencies.
   - The script joins the `players`, `player_age_group`, and `player_status` tables to determine the correct subsistence value for each player.
   - It uses a `CASE` statement to apply the appropriate modifier based on the player's status and sleep level, as defined in the `$subsistenceModifiers` array.
   - The subsistence deduction is calculated by multiplying the base subsistence value from the `player_age_group` table with the corresponding modifier.
   - The script also checks the `last_subsistence_deducted` column to ensure that the deduction is performed only if the specified number of days since the last update has passed.
5. Updates the last subsistence deduction date for the processed players.
6. Checks for any deceased players (those with current health less than or equal to 0) and updates their status to 'dead', sets their essence to 0.0, and logs their death in the death log table.
   - The script identifies the deceased players by selecting the `player_id` from the `players` table where the `player_current_health` is less than or equal to 0 and the `player_status_id` is not already set to 'dead'.
   - It then updates the `player_status_id` of the deceased players to the corresponding 'dead' status for their species, which is obtained by joining the `player_status` table.
   - The `player_essence` column is also set to 0.0 for the deceased players.
   - Finally, the script logs the death of each deceased player in the `death_log` table, including the deceased player ID, death date, cause of death, and other relevant details.
7. Handles any exceptions or deadlocks during the update process and retries with exponential backoff and jitter.
8. Generates a report containing the total players processed, total players who died, and a list of completed tasks.
9. Logs the script's execution details and any errors encountered.

## Usage

To use the subsistence health check script:

1. Configure the database connection credentials (`$servername`, `$username`, `$password`, `$dbname`).
2. Modify the `$subsistenceModifiers` array to define the subsistence modifiers and update frequencies for each status and sleep level.
3. Adjust the `$skipStatuses` array to specify the player statuses to skip during subsistence deduction.
4. Set the `$test_mode` variable to `true` for testing purposes or `false` for production.
5. Run the script using a PHP interpreter.

## Logging

The script logs its execution details and any errors encountered to a log file specified by `$log_file`. The log messages include timestamps and log levels (info, warning, error) for better tracking and debugging.

## Error Handling

The script implements error handling and logging for various scenarios, such as database connection errors, query execution errors, and deadlocks. It uses a retry mechanism with exponential backoff and jitter to handle deadlocks and ensure the script's resilience.

## Report Generation

After the subsistence health check process is completed, the script generates a formatted report that includes the total players processed, total players who died, and a list of completed tasks. The report is logged and displayed in the console.

Feel free to customize and enhance the script based on your specific requirements and use cases.