<?php
// Ensure this script is not executed via web browser for security reasons
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Include database connection
require_once __DIR__ . '/includes/db.php';

// --- Helper Functions for Existence Checks ---

/**
 * Checks if a table exists in the current database.
 * @param mysqli $conn The database connection.
 * @param string $tableName The name of the table to check.
 * @return bool True if the table exists, false otherwise.
 */
function _tableExists(mysqli $conn, string $tableName): bool
{
    $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    return $result && $result->num_rows > 0;
}

/**
 * Checks if a column exists in a given table.
 * @param mysqli $conn The database connection.
 * @param string $tableName The name of the table.
 * @param string $columnName The name of the column to check.
 * @return bool True if the column exists, false otherwise.
 */
function _columnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    // Need to escape table name for SHOW COLUMNS
    $tableNameEscaped = $conn->real_escape_string($tableName);
    $columnNameEscaped = $conn->real_escape_string($columnName);

    $result = $conn->query("SHOW COLUMNS FROM `{$tableNameEscaped}` LIKE '{$columnNameEscaped}'");
    return $result && $result->num_rows > 0;
}

// --- Main Migration Logic ---

$migration_files = [
    /*
    __DIR__ . '/update_database_monetization.sql',
    __DIR__ . '/update_database_stats_and_admins.sql',
    __DIR__ . '/add_auth_columns.sql', // New migration file
    __DIR__ . '/update_database_remember_selector.sql',
    __DIR__ . '/update_database_rate_limits.sql',
    __DIR__ . '/update_database_videos.sql',
    __DIR__ . '/update_database_crusades_salvation_clicks.sql',
    __DIR__ . '/update_database_shared_streams.sql',
    __DIR__ . '/update_database_comments.sql',
    __DIR__ . '/update_database_gift_subscriptions.sql',
    __DIR__ . '/update_database_salvation_gifts.sql',
    __DIR__ . '/add_thumbnail_columns.sql',
    */
    __DIR__ . '/add_daily_room_column.sql', // Daily.co migration
    __DIR__ . '/add_kingschat_column.sql', // KingsChat migration
    __DIR__ . '/migrations/001_crusade_enhancements.sql',
    __DIR__ . '/migrations/002_squad_payments_and_subscriptions.sql',
    __DIR__ . '/migrations/003_kingschat_oauth.sql',
];

echo "Starting database migration...\n";

foreach ($migration_files as $sql_file) {
    echo "\nProcessing migration file: " . basename($sql_file) . "\n";

    if (!file_exists($sql_file)) {
        echo "Error: SQL migration file not found at " . $sql_file . "\n";
        continue; // Skip to next file
    }

    $sql_content = file_get_contents($sql_file);

    if ($sql_content === false) {
        echo "Error: Could not read SQL migration file: " . basename($sql_file) . "\n";
        continue; // Skip to next file
    }

    // Split SQL statements by semicolon, being careful with comments
    $sql_statements = array_filter(array_map('trim', explode(';', $sql_content)));

    foreach ($sql_statements as $sql) {
        // Remove comments and blank lines from the current SQL statement
        $sql = preg_replace('/^--.*$/m', '', $sql); // Remove single-line comments
        $sql = trim($sql);
        if (empty($sql)) {
            continue;
        }

        echo "  Processing: " . substr($sql, 0, 80) . "...\n";

        // --- Handle ALTER TABLE with multiple ADD columns ---
        if (preg_match('/^ALTER TABLE `?([a-zA-Z0-9_]+)`?\s+(.*)$/is', $sql, $tableMatches)) {
            $tableName = $tableMatches[1];
            $alterBody = trim($tableMatches[2]);

            if (preg_match_all('/\bADD\b/i', $alterBody) > 1 && !preg_match('/\bADD\s+(CONSTRAINT|INDEX|KEY|UNIQUE|PRIMARY)\b/i', $alterBody)) {
                $parts = preg_split('/\s*,\s*ADD\s+/i', $alterBody);
                $handledAny = false;

                foreach ($parts as $index => $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }

                    $addClause = ($index === 0 && preg_match('/^ADD\b/i', $part)) ? $part : 'ADD ' . $part;

                    if (!preg_match('/^ADD\s+(?:COLUMN\s+)?`?([a-zA-Z0-9_]+)`?/i', $addClause, $addMatches)) {
                        $handledAny = false;
                        break;
                    }

                    $columnName = $addMatches[1];
                    if (_columnExists($conn, $tableName, $columnName)) {
                        echo "    [SKIPPED]: Column '{$columnName}' in table '{$tableName}' already exists.\n";
                        $handledAny = true;
                        continue;
                    }

                    $singleSql = "ALTER TABLE `{$tableName}` {$addClause}";
                    if ($conn->query($singleSql) === TRUE) {
                        echo "    [SUCCESS]: Added column '{$columnName}'.\n";
                    } else {
                        echo "    [ERROR]: " . $conn->error . "\n";
                        $conn->close();
                        die("Migration failed for " . basename($sql_file) . " due to SQL error. Please fix and rerun.\n");
                    }

                    $handledAny = true;
                }

                if ($handledAny) {
                    continue;
                }
            }
        }

        $executed = false;

        // --- Check for CREATE TABLE ---
        if (preg_match('/^CREATE TABLE `?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            if (_tableExists($conn, $tableName)) {
                echo "    [SKIPPED]: Table '{$tableName}' already exists.\n";
                $executed = true;
            }
        }
        // --- Check for ALTER TABLE ADD COLUMN ---
        // Handles ADD `column` and ADD COLUMN `column`
        if (!$executed && preg_match('/^ALTER TABLE `?([a-zA-Z0-9_]+)`?\s+ADD (?:COLUMN\s+)?`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
            $tableName = $matches[1];
            $columnName = $matches[2];
            if (_columnExists($conn, $tableName, $columnName)) {
                echo "    [SKIPPED]: Column '{$columnName}' in table '{$tableName}' already exists.\n";
                $executed = true;
            }
        }
        // --- Check for ALTER TABLE ADD CONSTRAINT ---
        // This is more complex. For now, if the FK is part of a CREATE TABLE and that's skipped, it's covered.
        // If it's a standalone ALTER TABLE ADD CONSTRAINT, it would need more specific parsing.
        // Given the current SQL, this is less critical as FKs are inside CREATE TABLEs.
        // If a future migration needs this, this part would need to be expanded.

        if (!$executed) { // Only execute if not skipped by an existence check
            if ($conn->query($sql) === TRUE) {
                echo "    [SUCCESS]\n";
            } else {
                echo "    [ERROR]: " . $conn->error . "\n";
                $conn->close();
                die("Migration failed for " . basename($sql_file) . " due to SQL error. Please fix and rerun.\n");
            }
        }
    }
}


echo "\nDatabase migration completed successfully!\n";

$conn->close();
?>
