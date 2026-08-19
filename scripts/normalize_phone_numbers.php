<?php

/* ----------------------------------------------------------------------------
 * BookingS (Easy!Appointments) - One-time phone number normalization script.
 *
 * Normalizes ea_users.phone_number for CUSTOMER rows only, to "+40..." format.
 *
 * STRICT rules (no guessing):
 *   - "+XX..." (already has an international prefix)  => NOT touched, left as-is.
 *   - exactly 10 digits starting with "07"            => "+40" + digits without the leading 0.
 *   - exactly 9 digits starting with "7"              => "+40" + digits.
 *   - exactly 12 digits starting with "0040"          => "+40" + digits after "0040".
 *   - anything else                                   => NOT touched, listed as "needs manual review".
 *
 * Usage:
 *   php scripts/normalize_phone_numbers.php                # DRY-RUN (default, writes nothing)
 *   php scripts/normalize_phone_numbers.php --apply        # performs the UPDATEs, one by one
 *
 * DB connection defaults are read from config.php (class Config constants) and can be overridden:
 *   php scripts/normalize_phone_numbers.php --host=... --port=3306 --dbname=... --user=... --pass=...
 *
 * Every --apply run writes an audit CSV log to scripts/phone_normalization_YYYYmmdd_His.csv
 * ---------------------------------------------------------------------------- */

$options = getopt('', ['apply', 'host::', 'port::', 'dbname::', 'user::', 'pass::', 'table-prefix::']);

$apply = array_key_exists('apply', $options);

// ---------------------------------------------------------------------------
// DB credentials: config.php defaults + CLI overrides.
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../config.php';

$host = $options['host'] ?? (getenv('PHONE_DB_HOST') ?: Config::DB_HOST);
$port = (int) ($options['port'] ?? (getenv('PHONE_DB_PORT') ?: 3306));
$dbname = $options['dbname'] ?? (getenv('PHONE_DB_NAME') ?: Config::DB_NAME);
$user = $options['user'] ?? (getenv('PHONE_DB_USER') ?: Config::DB_USERNAME);
$pass = $options['pass'] ?? (getenv('PHONE_DB_PASS') ?: Config::DB_PASSWORD);
$prefix = $options['table-prefix'] ?? 'ea_';

// ---------------------------------------------------------------------------
// Normalization (STRICT - see header).
// ---------------------------------------------------------------------------

/**
 * @return array{action: string, new_value: ?string} action: leave|update|review
 */
function normalize_phone(string $raw): array
{
    $value = trim($raw);

    if ($value === '') {
        return ['action' => 'leave', 'new_value' => null];
    }

    // Already international: "+XX..." => do not touch.
    if (str_starts_with($value, '+')) {
        return ['action' => 'leave', 'new_value' => null];
    }

    // Only digits and common separators are allowed, anything else needs a human.
    if (!preg_match('/^[0-9\s.\-()]+$/', $value)) {
        return ['action' => 'review', 'new_value' => null];
    }

    $digits = preg_replace('/\D/', '', $value);

    if (strlen($digits) === 12 && str_starts_with($digits, '0040')) {
        return ['action' => 'update', 'new_value' => '+40' . substr($digits, 4)];
    }

    if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
        return ['action' => 'update', 'new_value' => '+40' . substr($digits, 1)];
    }

    if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
        return ['action' => 'update', 'new_value' => '+40' . $digits];
    }

    return ['action' => 'review', 'new_value' => null];
}

// ---------------------------------------------------------------------------
// Connect (single-statement queries only - Railway MySQL 8 compatible).
// ---------------------------------------------------------------------------

$mysqli = new mysqli($host, $user, $pass, $dbname, $port);

if ($mysqli->connect_errno) {
    fwrite(STDERR, "DB connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

$mysqli->set_charset('utf8mb4');

$users_table = $prefix . 'users';
$roles_table = $prefix . 'roles';

$result = $mysqli->query(
    "SELECT u.id, u.first_name, u.last_name, u.phone_number
     FROM `{$users_table}` u
     INNER JOIN `{$roles_table}` r ON r.id = u.id_roles
     WHERE r.slug = 'customer'
     ORDER BY u.id"
);

if (!$result) {
    fwrite(STDERR, "Query failed: {$mysqli->error}\n");
    exit(1);
}

$updates = [];
$review = [];
$leaveCount = 0;

while ($row = $result->fetch_assoc()) {
    $phone = (string) ($row['phone_number'] ?? '');

    $outcome = normalize_phone($phone);

    if ($outcome['action'] === 'update' && $outcome['new_value'] !== $phone) {
        $updates[] = ['id' => (int) $row['id'], 'old' => $phone, 'new' => $outcome['new_value']];
    } elseif ($outcome['action'] === 'review') {
        $review[] = ['id' => (int) $row['id'], 'name' => trim($row['first_name'] . ' ' . $row['last_name']), 'value' => $phone];
    } else {
        $leaveCount++;
    }
}

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------

$mode = $apply ? 'APPLY' : 'DRY-RUN';

echo "=== Phone normalization ({$mode}) ===\n";
echo "Host: {$host}:{$port} / DB: {$dbname} / Table: {$users_table}\n\n";

echo "--- Rows to change: " . count($updates) . " ---\n";
printf("%-8s | %-25s | %-25s\n", 'id', 'old_value', 'new_value');
echo str_repeat('-', 66) . "\n";

foreach ($updates as $u) {
    printf("%-8d | %-25s | %-25s\n", $u['id'], $u['old'], $u['new']);
}

echo "\n--- Needs manual review (NOT touched): " . count($review) . " ---\n";
printf("%-8s | %-30s | %s\n", 'id', 'name', 'current_value');
echo str_repeat('-', 66) . "\n";

foreach ($review as $r) {
    printf("%-8d | %-30s | %s\n", $r['id'], mb_strimwidth($r['name'], 0, 30), $r['value']);
}

echo "\nLeft as-is (already OK or empty): {$leaveCount}\n\n";

if (!$apply) {
    echo "DRY-RUN only - nothing was written. Re-run with --apply to perform the UPDATEs.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Apply: one UPDATE per row (no multi-statements).
// ---------------------------------------------------------------------------

$statement = $mysqli->prepare("UPDATE `{$users_table}` SET phone_number = ? WHERE id = ?");

if (!$statement) {
    fwrite(STDERR, "Prepare failed: {$mysqli->error}\n");
    exit(1);
}

$logFile = __DIR__ . '/phone_normalization_' . date('Ymd_His') . '.csv';
$log = fopen($logFile, 'w');
fputcsv($log, ['id', 'old_value', 'new_value', 'status']);

$updatedCount = 0;
$failedCount = 0;

foreach ($updates as $u) {
    $statement->bind_param('si', $u['new'], $u['id']);

    if ($statement->execute()) {
        $updatedCount++;
        fputcsv($log, [$u['id'], $u['old'], $u['new'], 'updated']);
        echo "UPDATED id={$u['id']}: {$u['old']} -> {$u['new']}\n";
    } else {
        $failedCount++;
        fputcsv($log, [$u['id'], $u['old'], $u['new'], 'FAILED: ' . $statement->error]);
        echo "FAILED  id={$u['id']}: {$statement->error}\n";
    }
}

fclose($log);

echo "\n=== Done ===\n";
echo "Updated: {$updatedCount}\n";
echo "Failed: {$failedCount}\n";
echo "Needs manual review (untouched): " . count($review) . "\n";
echo "Audit log: {$logFile}\n";
