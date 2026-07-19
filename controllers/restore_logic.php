<?php
require_once __DIR__ . '/../config/config.php';

if (isset($_POST['restore_db'])) {

    if (
        !isset($_FILES['backup_file']) ||
        $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK
    ) {
        die("No backup file uploaded.");
    }

    $file = $_FILES['backup_file']['tmp_name'];

    // Database credentials
    $host = $_ENV['DB_HOST'];
    $db   = $_ENV['DB_DATABASE'];
    $user = $_ENV['DB_USERNAME'];
    $pass = $_ENV['DB_PASSWORD'];

    // macOS XAMPP mysql client
    $mysql = "/Applications/XAMPP/xamppfiles/bin/mysql";

    if (!file_exists($mysql)) {
        die("MySQL client not found:<br>$mysql");
    }

    $command = sprintf(
        '%s --host=%s --user=%s --password=%s %s < %s 2>&1',
        escapeshellcmd($mysql),
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($db),
        escapeshellarg($file)
    );

    exec($command, $output, $result);

    if ($result === 0) {

        header("Location: /settings.php?restore=success");
        exit;

    } else {

        echo "<h2>Restore Failed</h2>";

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