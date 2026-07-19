<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';


// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
              FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id WHERE a.admin_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $stmt->close();
}

// 🔹 Fetch order statuses
$status_query = "SELECT order_status_id, order_status_name FROM order_status ORDER BY order_status_id ASC";
$status_result = $conn->query($status_query);
$status_options = '';
while ($status_row = $status_result->fetch_assoc()) {
    $selected = (($_GET['status'] ?? 'all') == $status_row['order_status_id']) ? "selected" : "";
    $status_options .= "<option value='{$status_row['order_status_id']}' $selected>{$status_row['order_status_name']}</option>";
}

// 🔹 Build filters
$where  = ["1=1"];
$params = [];
$types  = "";
$report_title = "All Orders Report";

// Status filter
if (isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'all') {
    $status_id = (int) $_GET['status'];
    $where[] = "o.order_status_id = ?";
    $params[] = $status_id;
    $types .= "i";
}

// 🔹 Date range filter
$date_filter = $_GET['date_range'] ?? 'all';
switch ($date_filter) {
    case 'today':
        $where[] = "DATE(o.created_at) = CURDATE()";
        $report_title = "Daily Sales Report (" . date('M d, Y') . ")";
        break;
    case 'week':
        $where[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $report_title = "Weekly Sales Report (Week " . date('W, Y') . ")";
        break;
    case 'month':
        $where[] = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
        $report_title = "Monthly Sales Report (" . date('F Y') . ")";
        break;
    case 'year':
        $where[] = "YEAR(o.created_at) = YEAR(CURDATE())";
        $report_title = "Annual Sales Report (" . date('Y') . ")";
        break;
}

// 🔹 MAIN QUERY
$sql = "
SELECT
    o.order_id,

    CONCAT('ORD-', o.order_id) AS transaction_display_id,

    o.total_amount,
    os.order_status_name AS order_status,
    pm.payment_method_name AS payment_method,
    o.created_at,

    GROUP_CONCAT(
        DISTINCT CONCAT(
            '<div class=\"mb-1\">• <b>',
            p.product_name,
            '</b> <span class=\"text-gray-500 text-[10px]\">(',
            COALESCE(sz.size,'Free'),
            ', ',
            COALESCE(c2.color,'Std'),
            ')</span> <span class=\"bg-blue-50 text-blue-700 px-1 rounded font-bold ml-1\">x',
            oi.qty,
            '</span></div>'
        )
        ORDER BY oi.id
        SEPARATOR ''
    ) AS purchased_items,

    (
        SELECT GROUP_CONCAT(
            CONCAT(
                '<div class=\"text-xs mt-1 border-t pt-1 ',
                IF(sa.type='return_restock',
                    'text-green-600 border-green-100',
                    'text-red-600 border-red-100'
                ),
                '\">',
                IF(sa.type='return_restock',
                    '<i class=\"fas fa-sync\"></i> <b>Restocked (Wrong Item):</b> ',
                    '<i class=\"fas fa-trash\"></i> <b>Refunded (Damaged):</b> '
                ),
                p2.product_name,
                ' (',
                COALESCE(sz2.size,'-'),
                ', ',
                COALESCE(c3.color,'-'),
                ') ',
                '<span class=\"px-1 rounded font-bold ',
                IF(sa.type='return_restock',
                    'bg-green-100 text-green-800',
                    'bg-red-100 text-red-800'
                ),
                '\">x',
                sa.quantity,
                '</span>',
                IF(
                    sa.type='damaged',
                    CONCAT(
                        ' — ₱',
                        FORMAT(
                            sa.quantity *
                            (
                                SELECT price
                                FROM order_items
                                WHERE order_id=o.order_id
                                AND stock_id=sa.stock_id
                                LIMIT 1
                            ),
                            2
                        )
                    ),
                    ''
                ),
                '</div>'
            )
            SEPARATOR ''
        )
        FROM stock_adjustments sa
        LEFT JOIN stock s2
            ON sa.stock_id=s2.stock_id
        LEFT JOIN products p2
            ON s2.product_id=p2.product_id
        LEFT JOIN sizes sz2
            ON s2.size_id=sz2.size_id
        LEFT JOIN colors c3
            ON s2.color_id=c3.color_id
        WHERE sa.reason LIKE CONCAT('Order #',o.order_id,'%')
    ) AS return_history

FROM orders o

LEFT JOIN order_status os
    ON o.order_status_id=os.order_status_id

LEFT JOIN payment_methods pm
    ON o.payment_method_id=pm.payment_method_id

LEFT JOIN order_items oi
    ON o.order_id=oi.order_id

LEFT JOIN stock s
    ON oi.stock_id=s.stock_id

LEFT JOIN products p
    ON s.product_id=p.product_id

LEFT JOIN sizes sz
    ON s.size_id=sz.size_id

LEFT JOIN colors c2
    ON s.color_id=c2.color_id

WHERE " . implode(" AND ", $where) . "

GROUP BY o.order_id

ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// 🔹 Calculate summaries
$total_sales = 0;
$total_orders = count($orders);
$payment_counts = [];

foreach ($orders as $order) {
    $total_sales += $order['total_amount'];
    $pm = $order['payment_method'] ?? 'N/A';
    if (!isset($payment_counts[$pm])) $payment_counts[$pm] = 0;
    $payment_counts[$pm] += $order['total_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Orders Report | Seven Dwarfs</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Alpine JS -->
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    
    /* Sidebar specific */
    .active-nav { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Animations */
    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }

    /* 🖨 PRINT STYLES */
    @media print {
      @page { margin: 10mm; size: landscape; }
      body, main { background: white !important; color: black !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
      /* Reset Margins created by sidebar logic */
      main { margin-left: 0 !important; padding-top: 0 !important; }
      /* Hide UI */
      aside, header, .no-print, .print-btn { display: none !important; }
      /* Formatting */
      .report-header { display: flex !important; justify-content: space-between; border-bottom: 2px solid var(--rose); padding-bottom: 10px; margin-bottom: 20px; }
      table { width: 100%; border-collapse: collapse; font-size: 11px; }
      th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
      th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; font-weight: bold; }
    }
  </style>
</head>

<body class="text-sm animate-fade-in">

<!-- Global State Wrapper -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: false
     }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- 🌸 Sidebar (Dynamic Width) -->
  <aside 
    class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden"
    :class="sidebarOpen ? 'w-64' : 'w-20'"
  >
    <!-- Logo -->
    <div class="p-5 border-b flex items-center h-20 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300" 
            x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
    </div>

    <!-- Admin Profile -->
    <div class="p-5 border-b flex items-center h-24 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
      <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
      </a>

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="manage_users.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Users</span></a></li>
          <li><a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Roles</span></a></li>
        </ul>
      </div>
      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
      </a>
      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Category</span></a></li>
          <li><a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Product</span></a></li>
          <li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Stock In</span></a></li>
          <li><a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Inventory</span></a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-3 active-nav flex items-center transition-all duration-300" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-shopping-cart w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Orders</span>
      </a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-chart-line w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Cashier Sales</span>
      </a>
      <a href="system_logs.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-file-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">System Logs</span>
      </a>
      <a href="settings.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-cogs w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Settings</span></a>

      <a href="/controllers/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- 🌸 Main Content (Dynamic Margin) -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <!-- Header (Fixed Top) -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      
      <div class="flex items-center gap-4">
          <!-- Toggle Button -->
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
             <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">Orders Report</h1>
      </div>

      <div class="flex items-center gap-4">
          <button onclick="window.print()" class="bg-white text-[var(--rose)] px-3 py-1.5 rounded-lg shadow hover:bg-gray-100 font-bold transition text-xs flex items-center">
            <i class="fas fa-print mr-2"></i> Print Report
          </button>
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6 space-y-6">

        <!-- Filters (No Print) -->
        <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-center gap-4 no-print">
          <div class="flex items-center gap-2">
            <label class="font-bold text-gray-700 text-xs uppercase">Filter Status:</label>
            <select name="status" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
              <option value="all" <?= ($_GET['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Statuses</option>
              <?= $status_options ?>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <label class="font-bold text-gray-700 text-xs uppercase">Time Period:</label>
            <select name="date_range" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
              <option value="all" <?= ($date_filter === 'all') ? 'selected' : '' ?>>All Time</option>
              <option value="today" <?= ($date_filter === 'today') ? 'selected' : '' ?>>Today</option>
              <option value="week" <?= ($date_filter === 'week') ? 'selected' : '' ?>>This Week</option>
              <option value="month" <?= ($date_filter === 'month') ? 'selected' : '' ?>>This Month</option>
              <option value="year" <?= ($date_filter === 'year') ? 'selected' : '' ?>>This Year</option>
            </select>
          </div>
        </form>

        <!-- Orders Report Card -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">

          <!-- Report Header (Print Only) -->
          <div class="report-header hidden">
             <div>
                <h1 class="text-xl font-bold">Seven Dwarfs Boutique</h1>
                <p class="text-sm text-gray-500"><?= $report_title ?></p>
             </div>
             <div class="text-right">
                <p class="text-xs text-gray-400">Date Generated</p>
                <p class="font-bold"><?= date('M d, Y') ?></p>
             </div>
          </div>

          <!-- Summary Dashboard -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-pink-50 rounded-lg border border-pink-100 summary-box">
            <div class="text-center md:text-left summary-item">
                <p class="text-gray-500 text-xs uppercase">Total Orders</p>
                <p class="text-2xl font-bold"><?= number_format($total_orders) ?></p>
            </div>
            <div class="text-center summary-item">
                <p class="text-gray-500 text-xs uppercase">Total Sales</p>
                <p class="text-2xl font-bold text-[var(--rose)]">₱<?= number_format($total_sales, 2) ?></p>
            </div>
            <div class="text-center md:text-right summary-item">
                <p class="text-gray-500 text-xs uppercase">Payment Breakdown</p>
                <div class="text-xs mt-1">
                    <?php foreach($payment_counts as $method => $amount): ?>
                        <div><?= htmlspecialchars($method) ?>: <span class="font-semibold">₱<?= number_format($amount, 2) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
          </div>

          <!-- Orders Table -->
          <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="min-w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                <tr>
                  <th class="px-6 py-3 text-left">Transaction ID</th> <!-- 🟢 CHANGED Header -->
                  <th class="px-6 py-3 text-left">Date</th>
                  <th class="px-6 py-3 text-left w-1/3">Items & Details</th>
                  <th class="px-6 py-3 text-left">Status</th>
                  <th class="px-6 py-3 text-right">Total</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-gray-100 text-gray-700">
              <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): 
                    $status_class = "bg-green-100 text-green-600 border-green-200";
                    if ($order['order_status'] === 'Refunded') $status_class = "bg-red-100 text-red-600 border-red-200";
                    if ($order['order_status'] === 'Pending') $status_class = "bg-yellow-100 text-yellow-600 border-yellow-200";
                ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 font-mono font-semibold text-gray-600 align-top tracking-tight text-xs">
                    <?= htmlspecialchars($order['transaction_display_id']); ?> <!-- 🟢 CHANGED to Display ID -->
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap align-top">
                    <?= date('M d, Y', strtotime($order['created_at'])); ?>
                    <div class="text-xs text-gray-400 mt-1"><?= date('h:i A', strtotime($order['created_at'])); ?></div>
                  </td>
                  <td class="px-6 py-4 text-xs leading-5 align-top">
                    <?= $order['purchased_items'] ?: '<em>No items recorded</em>' ?>
                    
                    <?php if (!empty($order['return_history'])): ?>
                        <!-- Spacer -->
                        <div class="h-2"></div>
                        <!-- Return/Refund Section -->
                        <div class="p-2 bg-gray-50 rounded border border-gray-100">
                            <?= $order['return_history'] ?>
                        </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-6 py-4 align-top">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold border <?= $status_class ?>">
                      <?= htmlspecialchars($order['order_status'] ?? 'Unknown'); ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 text-right font-bold text-gray-800 align-top">
                    ₱<?= number_format($order['total_amount'], 2); ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500">
                        No orders found for this period.
                    </td>
                </tr>
              <?php endif; ?>
              </tbody>
              
              <!-- Table Footer for Print -->
              <tfoot class="hidden print:table-row-group bg-gray-50 font-bold">
                <tr>
                    <td colspan="4" class="px-6 py-3 text-right">GRAND TOTAL:</td>
                    <td class="px-6 py-3 text-right">₱<?= number_format($total_sales, 2); ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          <!-- Print Footer -->
          <div class="hidden print:block mt-8 pt-8 border-t border-gray-300 flex justify-between text-xs text-gray-500">
            <p>Printed from Seven Dwarfs Admin Panel</p>
            <p>Page 1 of 1</p>
          </div>

        </div> 
    </section>

  </main>
</div>

</body>
</html>