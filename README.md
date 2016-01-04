# PHPBackupScript
Backups MySQL databases and directories to Amazon S3.

## Installation
1. Run composer install
2. Rename config.php.example to config.php
3. Edit config.php
4. Setup CRON (crontab -e) "0 3 * * * php /path/to/script/backup-s3.php > /dev/null 2>&1"
