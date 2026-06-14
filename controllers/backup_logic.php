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
?>