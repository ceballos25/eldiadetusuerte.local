<?php
declare(strict_types=1);

/**
 * Database migration runner.
 * Usage: php database/migrate.php [--status]
 */

require_once __DIR__ . '/../config/config.php';

use App\Infrastructure\Database\PdoFactory;

$options = getopt('', ['status', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/migrate.php [--status]\n";
    exit(0);
}

$pdo = PdoFactory::get();
$migrationsDir = __DIR__ . '/migrations';

function ensureMigrationsTable(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/migrations/001_create_migrations_table.sql');
    if ($sql === false) {
        throw new RuntimeException('Cannot read 001_create_migrations_table.sql');
    }
    executeMigrationSql($pdo, $sql);
}

function getAppliedMigrations(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT migration FROM migrations ORDER BY id');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable) {
        return [];
    }
}

function getMigrationFiles(string $dir): array
{
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);
    return $files;
}

function executeMigrationSql(PDO $pdo, string $sql): void
{
    if (str_contains($sql, 'PREPARE stmt')) {
        $pdo->exec($sql);
        return;
    }

    $statements = splitSqlStatements($sql);
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

/**
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if (!$inString && $char === '-' && $next === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inString && ($char === '"' || $char === "'")) {
            $inString = true;
            $stringChar = $char;
            $buffer .= $char;
            continue;
        }

        if ($inString) {
            $buffer .= $char;
            if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = false;
            }
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function runMigration(PDO $pdo, string $file, int $batch): void
{
    $name = basename($file);
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration: {$name}");
    }

    echo "  → {$name}... ";

    try {
        executeMigrationSql($pdo, $sql);

        $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (:m, :b)');
        $stmt->execute([':m' => $name, ':b' => $batch]);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        throw new RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
    }
}

try {
    ensureMigrationsTable($pdo);

    if (isset($options['status'])) {
        $applied = getAppliedMigrations($pdo);
        $all = array_map('basename', getMigrationFiles($migrationsDir));
        echo "Migration status:\n";
        foreach ($all as $file) {
            $status = in_array($file, $applied, true) ? '[✓]' : '[ ]';
            echo "  {$status} {$file}\n";
        }
        exit(0);
    }

    $applied = getAppliedMigrations($pdo);
    $files = getMigrationFiles($migrationsDir);
    $pending = array_filter($files, static fn (string $f) => !in_array(basename($f), $applied, true));

    if ($pending === []) {
        echo "Nothing to migrate.\n";
        exit(0);
    }

    $batch = (int)$pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
    echo "Running batch {$batch} (" . count($pending) . " migrations)...\n";

    foreach ($pending as $file) {
        runMigration($pdo, $file, $batch);
    }

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
