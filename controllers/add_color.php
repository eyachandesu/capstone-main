<?php
require_once __DIR__ . '/../config/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['color'])) {
    $color = trim($_POST['color']);

    // Prevent duplicates
    $stmt = $conn->prepare("SELECT color_id FROM colors WHERE color = ?");
    $stmt->bind_param("s", $color);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Color already exists."]);
        exit;
    }

    $stmt->close();

    // Insert new color
    $insert = $conn->prepare("INSERT INTO colors (color) VALUES (?)");
    $insert->bind_param("s", $color);
    if ($insert->execute()) {
        echo json_encode([
            "success" => true,
            "color_id" => $insert->insert_id,
            "color" => $color
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to add color."]);
    }
    $insert->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
