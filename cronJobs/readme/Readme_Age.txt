# Age Update Script

This script is designed to update the age of players based on their status and sleeping levels. It processes players in batches and increments their age according to the configured rules.

## Configuration

The script uses a configuration array (`$config`) to define the age update rules for different player statuses and sleeping levels. Each status has an associated increment value and the number of days since the last update.

Example configuration:

$config = [
    'alive' => ['increment' => 1, 'days_since_last_update' => 1],
    'sleeping' => [
        1 => ['increment' => 1, 'days_since_last_update' => 2],
        2 => ['increment' => 0, 'days_since_last_update' => 1],
    ],
];

## Functionality

The script performs the following steps:

1. Connects to the database using the provided credentials.
2. Iterates over each status in the configuration array.
3. For each status, it retrieves the total number of players to be processed based on the status and sleeping level conditions.
4. Processes the players in batches of 1000 to avoid overloading the database.
5. Updates the age of the players based on the configured increment value and the number of days since the last update.
6. Handles any exceptions or deadlocks during the update process and retries with exponential backoff and jitter.
7. Generates a report containing the total players processed and the completed tasks.
8. Logs the script's execution details and any errors encountered.

## Usage

To use the age update script:

1. Configure the database connection credentials (`$servername`, `$username`, `$password`, `$dbname`).
2. Modify the `$config` array to define the age update rules for each status and sleeping level.
3. Set the `$test_mode` variable to `true` for testing purposes or `false` for production.
4. Run the script using a PHP interpreter.

## Logging

The script logs its execution details and any errors encountered to a log file specified by `$log_file`. The log messages include timestamps and log levels (info, warning, error) for better tracking and debugging.

## Error Handling

The script implements error handling and logging for various scenarios, such as database connection errors, query execution errors, and deadlocks. It uses a retry mechanism with exponential backoff and jitter to handle deadlocks and ensure the script's resilience.

## Report Generation

After the age update process is completed, the script generates a formatted report that includes the total players processed and a list of completed tasks. The report is logged and displayed in the console.

Feel free to customize and enhance the script based on your specific requirements and use cases.