<?php

// Display help information
function displayHelp() {
    echo "Usage: php user_upload.php [options]\n";
    echo "Options:\n";
    echo "  --file [filename]          : Path to CSV file to process\n";
    echo "  --create_table             : Create/rebuild the 'users' table and exit\n";
    echo "  -u [username]              : MySQL username\n";
    echo "  -p [password]              : MySQL password\n";
    echo "  -h [hostname]              : MySQL host (default: localhost)\n";
    echo "  --help                    : Display this help message\n";
    exit(0);
}

// Parse command line arguments
$options = getopt("", ["file:", "create_table", "help", "dry_run", "u:", "p:", "h:"]);

if (isset($options['help'])) {
    displayHelp();
}
//var_dump($options);

// Validate required DB credentials
$host = $options['h'] ?? 'localhost';
$user = $options['u'] ?? 'root';
$pass = $options['p'] ?? '';

if (!$user) {
    fwrite(STDERR, "Missing MySQL username (-u)\n");
    exit(1);
}
/*if (!array_key_exists('p', $options)) { // Allow empty password
    fwrite(STDERR, "Missing MySQL password (-p)\n");
    exit(1);
}
    */

if (!$host) {
    $host = 'localhost';
}

$createTableOnly = isset($options['create_table']);
$csvFile = $options['file'] ?? null;
$dryRun = isset($options['dry_run']);
echo $createTableOnly; 
// Connect to MySQL
$mysqli = new mysqli($host, $user, $pass);

if ($mysqli->connect_errno) {
    fwrite(STDERR, "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . "\n");
    exit(1);
}

// Select database or create it if not exists
$dbName = "users_db";

if (!$mysqli->select_db($dbName)) {
    // Try to create database if it does not exist
    if (!$mysqli->query("CREATE DATABASE `$dbName`")) {
        fwrite(STDERR, "Database '$dbName' does not exist and failed to create: " . $mysqli->error . "\n");
        exit(1);
    }
    $mysqli->select_db($dbName);
}

// Create/rebuild users table
function createUsersTable($mysqli) {
    $dropSQL = "DROP TABLE IF EXISTS users";
    $createSQL = <<<SQL
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL;

    if (!$mysqli->query($dropSQL)) {
        fwrite(STDERR, "Failed to drop existing users table: " . $mysqli->error . "\n");
        exit(1);
    }
    if (!$mysqli->query($createSQL)) {
        fwrite(STDERR, "Failed to create users table: " . $mysqli->error . "\n");
        exit(1);
    }
    echo "Users table created/rebuilt successfully.\n";
}

if ($createTableOnly) {
    createUsersTable($mysqli);
    exit(0);
}

if (!$csvFile) {
    fwrite(STDERR, "Missing CSV file. Use --file [filename]\n");
    exit(1);
}

if (!file_exists($csvFile)) {
    fwrite(STDERR, "CSV file '$csvFile' does not exist.\n");
    exit(1);
}

// Prepare insert statement
$stmt = $mysqli->prepare("INSERT INTO users (name, surname, email) VALUES (?, ?, ?)");
if (!$stmt) {
    fwrite(STDERR, "Prepare statement failed: " . $mysqli->error . "\n");
    exit(1);
}

// Open CSV file for reading
if (($handle = fopen($csvFile, "r")) === false) {
    fwrite(STDERR, "Failed to open CSV file '$csvFile'\n");
    exit(1);
}

// Read CSV header
$header = fgetcsv($handle);
if ($header === false) {
    fwrite(STDERR, "CSV file appears to be empty.\n");
    exit(1);
}

// Expecting header to contain: name, surname, email (case insensitive)
$header = array_map('strtolower', $header);
$expectedHeaders = ['name', 'surname', 'email'];
foreach ($expectedHeaders as $col) {
    if (!in_array($col, $header)) {
        fwrite(STDERR, "CSV missing expected column '$col'\n");
        exit(1);
    }
}

// Map column indices
$colIndexes = [];
foreach ($expectedHeaders as $col) {
    $colIndexes[$col] = array_search($col, $header);
}

// Process each row
$rowNum = 1;
$insertCount = 0;
$errorCount = 0;

while (($data = fgetcsv($handle)) !== false) {
    $rowNum++;

    // Extract and sanitize fields
    $nameRaw = $data[$colIndexes['name']] ?? '';
    $surnameRaw = $data[$colIndexes['surname']] ?? '';
    $emailRaw = $data[$colIndexes['email']] ?? '';

    $name = ucfirst(strtolower(trim($nameRaw)));
    $surname = ucfirst(strtolower(trim($surnameRaw)));
    $email = strtolower(trim($emailRaw));

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDOUT, "Row $rowNum: Invalid email '$email'. Skipping insert.\n");
        $errorCount++;
        continue;
    }

    if ($dryRun) {
        echo "Row $rowNum: Validated - Name: $name, Surname: $surname, Email: $email\n";
        $insertCount++;
        continue;
    }

    // Bind parameters and execute insert
    $stmt->bind_param("sss", $name, $surname, $email);
    if (!$stmt->execute()) {
        fwrite(STDOUT, "Row $rowNum: Failed to insert: " . $stmt->error . "\n");
        $errorCount++;
    } else {
        $insertCount++;
    }
}

fclose($handle);
$stmt->close();
$mysqli->close();

echo "\nProcessing complete. Inserted: $insertCount, Errors: $errorCount\n";

exit(0);