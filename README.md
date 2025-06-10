# Catalyst IT - PHP Programming (June 2025)

## Name- Jaspreet kaur

This project contains the solution to the Catalyst IT PHP Programming Tasks. It includes:

- A command-line PHP script to parse and import CSV data into MySQL (`user_upload.php`)
- A logic test that prints numbers 1 to 100 with substitutions (`foobar.php`)

---

Files Included

| File            | Description                                                  |

| `user_upload.php` | Main script to process user data from CSV into MySQL        |
| `foobar.php`     | Script that prints numbers 1–100 with "foo", "bar", "foobar" |
| `users.csv`      | Sample input CSV file with user data                         |
| `README.md`      | This file            

CREATE DATABASE users_db;

Run Commands

php user_upload.php --help
Create Table Only
php user_upload.php --create_table -u [user] -p [password] -h [host]
Dry Run (no DB insert)
php user_upload.php --file users.csv --dry_run -u [user] -p [password] -h [host]
Actual Import
php user_upload.php --file users.csv -u [user] -p [password] -h [host]
