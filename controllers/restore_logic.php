<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "dbms";

if (isset($_POST['restore_db'])) {
    $file = $_FILES['backup_file']['tmp_name'];
    if ($file) {
        $command = "mysql --user=$user --password=$pass --host=$host $dbname < $file";
        exec($command, $output, $result);
        
        if ($result === 0) {
            echo "<script>alert('Database Restored Successfully!'); window.location='settings.php';</script>";
        } else {
            echo "<script>alert('Restore Failed.'); window.location='settings.php';</script>";
        }
    }
}
?>