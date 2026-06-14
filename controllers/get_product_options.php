<?php
require_once __DIR__ . '/../config/conn.php';

$product_id = intval($_GET['product_id'] ?? 0);

$sizes = [];
$colors = [];

if ($product_id > 0) {
    $size_res = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
    while ($row = $size_res->fetch_assoc()) {
        $sizes[] = $row;
    }

    $color_res = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
    while ($row = $color_res->fetch_assoc()) {
        $colors[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode(['sizes' => $sizes, 'colors' => $colors]);
