<?php

require_once __DIR__ . '/../config/conn.php';

$filter = $_GET['filter'] ?? 'today';
$dateCondition = "DATE(created_at) = CURDATE()"; // default

if ($filter === 'month') {
    $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
}

$data = [];
$labels = [];

$query = $conn->query("
    SELECT DATE(created_at) AS order_date, COUNT(*) AS count
    FROM orders
    WHERE $dateCondition
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
");

while ($row = $query->fetch_assoc()) {
    $labels[] = $row['order_date'];
    $data[] = $row['count'];
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
