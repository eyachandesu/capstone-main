<?php
require_once __DIR__ . '/../config/conn.php';

$searchQuery = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$searchTerm = "%$searchQuery%";

$sql = "
    SELECT 
        p.product_id, p.product_name, p.description, p.price_id, p.image_url, c.category_name,
        GROUP_CONCAT(DISTINCT col.color ORDER BY col.color SEPARATOR ', ') AS colors,
        GROUP_CONCAT(DISTINCT sz.size ORDER BY sz.size SEPARATOR ', ') AS sizes
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock st ON p.product_id = st.product_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

$conditions = [];
$params = [];
$types = '';

if ($category !== 'all') {
    $conditions[] = "p.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if (!empty($searchQuery)) {
    $conditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

if (!empty($conditions)) $sql .= " WHERE ".implode(" AND ", $conditions);
$sql .= " GROUP BY p.product_id ORDER BY p.product_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $imageList = [];
    $raw = trim($row['image_url'] ?? '');
    if ($raw && json_decode($raw) !== null) $imageList = json_decode($raw, true);
    else $imageList = array_filter(array_map('trim', explode(',', $raw)));

    $displayImages = [];
    if (!empty($imageList)) {
        foreach ($imageList as $img) $displayImages[] = "uploads/products/".str_replace("uploads/products/","",$img);
    } else $displayImages[] = "uploads/products/default.png";

    echo "<tr class='hover:bg-gray-50 transition'>";
    echo "<td class='px-4 py-3'><div class='flex flex-wrap gap-2' x-data=\"{open:false,imageSrc:''}\">";
    foreach ($displayImages as $img) {
        echo "<img src='$img' class='w-14 h-14 rounded-lg border shadow-sm object-cover cursor-pointer hover:scale-105 transition-transform duration-200' @click=\"imageSrc='$img';open=true\" onerror=\"this.src='uploads/products/default.png';\">";
    }
    echo "<div x-show='open' x-transition @click='open=false' class='fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4'>
            <div class='relative bg-white rounded-xl shadow-lg p-4 max-w-md w-full'>
            <button @click='open=false' class='absolute top-2 right-2 text-gray-500 hover:text-gray-800 text-xl font-bold'>&times;</button>
            <img :src='imageSrc' alt='Preview' class='w-full h-auto rounded-lg object-contain'>
            </div></div></div></td>";
    echo "<td class='px-4 py-3 font-semibold text-gray-800'>".htmlspecialchars($row['product_name'])."</td>";
    echo "<td class='px-4 py-3'>".htmlspecialchars($row['description'])."<div class='text-xs text-gray-500 mt-1'><strong>Colors:</strong> ".($row['colors'] ?: '—')."<br><strong>Sizes:</strong> ".($row['sizes'] ?: '—')."</div></td>";
    echo "<td class='px-4 py-3 font-medium text-[var(--rose)]'>₱".number_format($row['price_id'],2)."</td>";
    echo "<td class='px-4 py-3'>".htmlspecialchars($row['category_name'])."</td>";
    echo "<td class='px-4 py-3'><div class='flex gap-2'>
          <a href='edit_product.php?id=".$row['product_id']."' class='bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg shadow text-xs font-medium transition'>Edit</a>
          <a href='delete_product.php?id=".$row['product_id']."' onclick=\"return confirm('Are you sure?')\" class='bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg shadow text-xs font-medium transition'>Delete</a>
          </div></td>";
    echo "</tr>";
}

if ($result->num_rows === 0) {
    echo "<tr><td colspan='6' class='text-center text-gray-500 py-8'>No products available.</td></tr>";
}

$stmt->close();
$conn->close();
?>
