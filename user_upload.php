<?php

// Display help instructions
function displayHelp()
{
    echo "Usage: php user_upload.php [options]\n";
    echo "Options:\n";
    echo "--file [csv file name]       : Name of the CSV file to process\n";
    echo "--create_table               : Create the 'users' table and exit\n";
    echo "--dry_run                    : Run the script without inserting into DB\n";
    echo "-u [MySQL username]          : MySQL username\n";
    echo "-p [MySQL password]          : MySQL password\n";
    echo "-h [MySQL host]              : MySQL host\n";
    echo "--help                       : Display this help message\n";
    exit();
}

// Parse command-line arguments
$options = getopt("", ["file:", "create_table", "dry_run", "help"], $restIndex);
$shortOpts = getopt("u:p:h:");

if (isset($options['help'])) {
    displayHelp();
}

// Merge short and long options for easier access
$args = array_merge($options, $shortOpts);

// Store flags
$filename = $args['file'] ?? null;
$createTable = isset($args['create_table']);
$dryRun = isset($args['dry_run']);
$dbUser = $args['u'] ?? null;
$dbPass = $args['p'] ?? null;
$dbHost = $args['h'] ?? null;

// db connection
function connectToDB($host, $user, $pass)
{
    $conn = new mysqli($host, $user, $pass);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error . PHP_EOL);
    }

    // Create and use database
    $conn->query("CREATE DATABASE IF NOT EXISTS user_data");
    $conn->select_db("user_data");

    return $conn;
}
// create table
function createUserTable($conn)
{
    $sql = "DROP TABLE IF EXISTS users";
    $conn->query($sql);

    $sql = "CREATE TABLE users (
        name VARCHAR(100),
        surname VARCHAR(100),
        email VARCHAR(255) UNIQUE
    )";

    if ($conn->query($sql) === TRUE) {
        echo "Table 'users' created successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
}
if ($createTable) {
    if (!$dbHost || !$dbUser) {
        die("Missing DB credentials. Use -u -p -h\n");
    }
    $conn = connectToDB($dbHost, $dbUser, $dbPass);
    createUserTable($conn);
    $conn->close();
    exit();
}
// insert records
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function processCSV($filename, $conn = null, $dryRun = false)
{
    if (!file_exists($filename)) {
        die("CSV file not found: $filename\n");
    }

    $handle = fopen($filename, "r");
    if (!$handle) {
        die("Failed to open file: $filename\n");
    }

    $rowCount = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowCount++;

        // Skip header row
        if ($rowCount === 1 && strtolower($data[0]) === 'name') {
            continue;
        }

        if (count($data) !== 3) {
            echo "Skipping malformed row at line $rowCount\n";
            continue;
        }

        [$name, $surname, $email] = $data;

        // Transform data
        $name = ucfirst(strtolower($name));
        $surname = ucfirst(strtolower($surname));
        $email = strtolower($email);

        // Validate email
        if (!isValidEmail($email)) {
            echo "Invalid email format at line $rowCount: $email\n";
            continue;
        }

        echo "Valid: $name $surname <$email>\n";

        // Insert into DB if not dry run
        if (!$dryRun && $conn) {
            $stmt = $conn->prepare("INSERT IGNORE INTO users (name, surname, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $surname, $email);
            $stmt->execute();
            $stmt->close();
        }
    }

    fclose($handle);
}

if (!$filename) {
    echo "No CSV file specified. Use --file option.\n";
    exit();
}

// Connect if not dry run
$conn = null;
if (!$dryRun) {
    if (!$dbHost || !$dbUser) {
        die("Missing DB credentials. Use -u -p -h\n");
    }
    $conn = connectToDB($dbHost, $dbUser, $dbPass);
}

processCSV($filename, $conn, $dryRun);

if ($conn) {
    $conn->close();
}