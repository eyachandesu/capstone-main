<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// ✅ Only logged-in admins
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit;
}

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "
        SELECT 
            CONCAT(first_name, ' ', last_name) AS full_name, 
            r.role_name 
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $adminStmt = $conn->prepare($query);
    $adminStmt->bind_param("i", $admin_id);
    $adminStmt->execute();
    $result = $adminStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $adminStmt->close();
}

// 🔹 Fetch refunds from refunds table
$query = "
  SELECT 
    r.refund_id,
    r.order_id,
    r.product_id,
    p.product_name,
    r.stock_id,
    s.current_qty AS stock_after,
    sz.size,
    c.color,
    r.refund_amount,
    r.refunded_at,
    a.username AS refunded_by
  FROM refunds r
  LEFT JOIN products p ON r.product_id = p.product_id
  LEFT JOIN stock s ON r.stock_id = s.stock_id
  LEFT JOIN sizes sz ON r.size_id = sz.size_id
  LEFT JOIN colors c ON r.color_id = c.color_id
  LEFT JOIN adminusers a ON r.refunded_by = a.admin_id
  ORDER BY r.refunded_at DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Refund History - Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-pink-50 font-sans">

<div class="flex h-screen">

  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-md min-h-screen" x-data="{ userMenu: false, productMenu: false }">
    <div class="p-4">
      <!-- Logo & Brand -->
      <div class="flex items-center space-x-4">
        <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
        <h2 class="text-lg font-semibold">SevenDwarfs</h2>
      </div>

      <!-- Admin Info -->
      <div class="mt-4 flex items-center space-x-4">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6">
      <ul>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="dashboard.php" class="flex items-center">
            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
          </a>
        </li>

        <!-- User Management -->
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center">
              <i class="fas fa-users-cog mr-2"></i>User Management
            </span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
          </div>
        </li>
        <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 hover:text-pink-600"><a href="users.php" class="flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
          <li class="py-1 hover:text-pink-600"><a href="customers.php" class="flex items-center"><i class="fas fa-users mr-2"></i>Customer</a></li>
        </ul>


<!-- Product Management -->
<li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
  <div class="flex items-center justify-between">
    <span class="flex items-center">
      <i class="fas fa-box-open mr-2"></i>Product Management
    </span>
    <i class="fas fa-chevron-down transition-transform duration-200" 
       :class="{ 'rotate-180': productMenu }"></i>
  </div>
</li>
<ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1" x-cloak>
  <li class="py-1 hover:text-pink-600">
    <a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a>
  </li>
  <li class="py-1 hover:text-pink-600">
    <a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a>
  </li>
  <li class="py-1 hover:text-pink-600">
    <a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a>
  </li>
  <li class="py-1 hover:text-pink-600">
    <a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a>
  </li>
</ul>


<!-- Other Pages -->
<li class="px-4 py-2 hover:bg-gray-200">
  <a href="orders.php" class="flex items-center">
    <i class="fas fa-shopping-cart mr-2"></i>Orders
  </a>
</li>

<li class="px-4 py-2 hover:bg-gray-200">
  <a href="refund_history.php" class="flex items-center">
    <i class="fas fa-undo-alt mr-2"></i>Refund History
  </a>
</li>

<li class="px-4 py-2 hover:bg-gray-200">
  <a href="suppliers.php" class="flex items-center">
    <i class="fas fa-industry mr-2"></i>Suppliers
  </a>
</li>


<li class="px-4 py-2 hover:bg-gray-200">
  <a href="logout.php" class="flex items-center">
    <i class="fas fa-sign-out-alt mr-2"></i>Log out
  </a>
</li>


      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <h1 class="text-2xl font-bold text-pink-700 mb-6">Refund History</h1>

    <div class="bg-white shadow rounded-lg overflow-hidden">
      <table class="min-w-full table-auto border-collapse">
        <thead class="bg-pink-200 text-pink-900">
          <tr>
            <th class="px-4 py-2 text-left">Refund ID</th>
            <th class="px-4 py-2 text-left">Order ID</th>
            <th class="px-4 py-2 text-left">Product</th>
            <th class="px-4 py-2 text-left">Size</th>
            <th class="px-4 py-2 text-left">Color</th>
            <th class="px-4 py-2 text-left">Refund Amount</th>
            <th class="px-4 py-2 text-left">Refunded By</th>
            <th class="px-4 py-2 text-left">Refund Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-pink-100">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-pink-50">
                <td class="px-4 py-2"><?= htmlspecialchars($row['refund_id']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['order_id']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['product_name']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['size'] ?? '-') ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['color'] ?? '-') ?></td>
                <td class="px-4 py-2">₱<?= number_format($row['refund_amount'], 2) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['refunded_by']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($row['refunded_at']) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="px-4 py-4 text-center text-gray-500">No refunds recorded yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

   
  </div>

</div>
</body>
</html>
```
