<?php
// Replace with your DB credentials
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "dbms"; 

if (isset($_POST['backup_db'])) {
    $filename = "backups/db_backup_" . date("Y-m-d_H-i-s") . ".sql";
    // Adjust path for 'mysqldump' if necessary (e.g. C:/xampp/mysql/bin/mysqldump)
    $command = "mysqldump --user=$user --password=$pass --host=$host $dbname > $filename";
    
    exec($command, $output, $result);
    
    if ($result === 0) {
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=".basename($filename));
        readfile($filename);
        exit;
    } else {
        echo "Backup failed. Check if mysqldump is in your PATH.";
    }
}
?><?php
require_once __DIR__ . '/../config/config.php';

if (isset($_POST['backup_db'])) {

    // Create backups folder if it doesn't exist
    $backupDir = __DIR__ . '/../backups/';

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    $filename = $backupDir . "db_backup_" . date("Y-m-d_H-i-s") . ".sql";

    // Database credentials
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $dbname = DB_NAME;

    // XAMPP mysqldump path for macOS
    $mysqldump = "/Applications/XAMPP/xamppfiles/bin/mysqldump";

    if (!file_exists($mysqldump)) {
        die("mysqldump not found at:<br>$mysqldump");
    }

    // Escape everything
    $command = sprintf(
        '%s --host=%s --user=%s --password=%s %s > %s 2>&1',
        escapeshellcmd($mysqldump),
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($dbname),
        escapeshellarg($filename)
    );

    exec($command, $output, $result);

    if ($result === 0 && file_exists($filename)) {

        header("Content-Description: File Transfer");
        header("Content-Type: application/sql");
        header("Content-Disposition: attachment; filename=\"" . basename($filename) . "\"");
        header("Content-Length: " . filesize($filename));

        readfile($filename);

        exit;

    } else {

        echo "<h3>Backup Failed</h3>";

        echo "<strong>Command:</strong><br>";
        echo "<pre>$command</pre>";

        echo "<strong>Output:</strong><br>";
        echo "<pre>";
        print_r($output);
        echo "</pre>";

        echo "<strong>Exit Code:</strong> $result";
    }
}
?>