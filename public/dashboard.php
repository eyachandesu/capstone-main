<?php
require_once __DIR__ . '/../init.php';

$user = checkAuth();


// 1. Fetch Admin Details
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
}

// === 2. FILTER HANDLING ===
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

// 🟢 UX IMPROVEMENT: If 'From' is selected but 'To' is empty, default 'To' to Today.
// This ensures the automatic filter works immediately after selecting just the first date.
if ($from && empty($to)) {
  $to = date('Y-m-d');
}

$chartGroupBy = "";
$filter = '';

if ($from && $to) {
  // Custom date range
  $dateCondition = "DATE(created_at) BETWEEN '" . $conn->real_escape_string($from) . "' AND '" . $conn->real_escape_string($to) . "'";
  $chartGroupBy = "DATE(created_at)";
  $filter = 'custom';
} else {
  // Default: today
  $dateCondition = "DATE(created_at) = CURDATE()";
  $chartGroupBy = "HOUR(created_at)";
  $filter = 'today';
}

$dateConditionWithAlias = str_replace("created_at", "o.created_at", $dateCondition);

// 3. Basic Metrics
$newOrders = 0;
$totalSales = 0;
$totalRevenue = 0;

$ordersQuery = $conn->query("SELECT COUNT(*) AS new_orders FROM orders WHERE $dateCondition");
if ($row = $ordersQuery->fetch_assoc())
  $newOrders = $row['new_orders'];

$salesQuery = $conn->query("SELECT COUNT(*) AS total_sales FROM orders WHERE $dateCondition");
if ($row = $salesQuery->fetch_assoc())
  $totalSales = $row['total_sales'];

$revenueQuery = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE $dateCondition");
if ($row = $revenueQuery->fetch_assoc())
  $totalRevenue = $row['revenue'] ?? 0;

// 4. Top 1 Product
$topProductName = "No Sales Yet";
$topProductQty = 0;
$topProductQuery = "SELECT p.product_name, SUM(oi.qty) as total_qty FROM order_items oi JOIN orders o ON oi.order_id = o.order_id JOIN products p ON oi.product_id = p.product_id WHERE $dateConditionWithAlias GROUP BY oi.product_id ORDER BY total_qty DESC LIMIT 1";
$topRes = $conn->query($topProductQuery);
if ($topRes && $topRow = $topRes->fetch_assoc()) {
  $topProductName = $topRow['product_name'];
  $topProductQty = $topRow['total_qty'];
}

// 5. Peak Sales Time
$mostSalesDate = "N/A";
$mostSalesCount = 0;
$mostSalesQuery = "SELECT $chartGroupBy as time_period, COUNT(*) as count FROM orders WHERE $dateCondition GROUP BY $chartGroupBy ORDER BY count DESC LIMIT 1";
$mostRes = $conn->query($mostSalesQuery);
if ($mostRes && $mostRow = $mostRes->fetch_assoc()) {
  $rawTime = $mostRow['time_period'];
  $mostSalesCount = $mostRow['count'];
  if ($filter == 'today') {
    $mostSalesDate = date("g A", strtotime("$rawTime:00:00"));
  } elseif ($filter == 'all') {
    $mostSalesDate = date("M Y", strtotime($rawTime . "-01"));
  } else {
    $mostSalesDate = date("M j", strtotime($rawTime));
  }
}

// 6. Chart 1 Data
$chartDataQuery = $conn->query("SELECT $chartGroupBy AS period, COUNT(*) AS count FROM orders WHERE $dateCondition GROUP BY period ORDER BY period ASC");
$rawChartData = [];
while ($row = $chartDataQuery->fetch_assoc()) {
  $rawChartData[$row['period']] = $row['count'];
}
$chartLabels = [];
$chartValues = [];

if ($filter == 'today') {
  for ($i = 0; $i <= 23; $i++) {
    $chartLabels[] = date("g A", strtotime("$i:00"));
    $chartValues[] = $rawChartData[$i] ?? 0;
  }
} elseif ($filter == 'week') {
  $start = strtotime('monday this week');
  for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("+$i days", $start));
    $chartLabels[] = date('M j', strtotime($d));
    $chartValues[] = $rawChartData[$d] ?? 0;
  }
} elseif ($filter == 'month') {
  $daysInMonth = date('t');
  $ym = date('Y-m');
  for ($i = 1; $i <= $daysInMonth; $i++) {
    $d = sprintf("%s-%02d", $ym, $i);
    $chartLabels[] = $i;
    $chartValues[] = $rawChartData[$d] ?? 0;
  }
} elseif ($filter == 'all') {
  if (empty($rawChartData)) {
    $chartLabels[] = "No Data";
    $chartValues[] = 0;
  } else {
    foreach ($rawChartData as $ym => $count) {
      $chartLabels[] = date("M Y", strtotime($ym . "-01"));
      $chartValues[] = $count;
    }
  }
} else {
  // Custom Range Chart Handling
  foreach ($rawChartData as $date => $count) {
    $chartLabels[] = date("M j", strtotime($date));
    $chartValues[] = $count;
  }
}

// 7. Chart 2 Data
$top5Labels = [];
$top5Data = [];
$top5Query = "SELECT p.product_name, SUM(oi.qty) as total_qty FROM order_items oi JOIN orders o ON oi.order_id = o.order_id JOIN products p ON oi.product_id = p.product_id WHERE $dateConditionWithAlias GROUP BY oi.product_id ORDER BY total_qty DESC LIMIT 5";
$top5Res = $conn->query($top5Query);
while ($row = $top5Res->fetch_assoc()) {
  $top5Labels[] = $row['product_name'];
  $top5Data[] = $row['total_qty'];
}

// 8. Recent Orders
$recentOrdersQuery = "SELECT o.order_id, o.total_amount, o.order_status_id, o.created_at, CONCAT(c.first_name, ' ', c.last_name) as customer FROM orders o LEFT JOIN customers c ON o.customer_id = c.customer_id ORDER BY o.created_at DESC LIMIT 5";
$recentOrdersRes = $conn->query($recentOrdersQuery);

// 9. Notifications
$newOrdersNotif = 0;
$lowStockNotif = 0;
$notifRes = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
if ($row = $notifRes->fetch_assoc())
  $newOrdersNotif = $row['count'];
$stockRes = $conn->query("SELECT COUNT(*) as count FROM stock WHERE current_qty < 10");
if ($row = $stockRes->fetch_assoc())
  $lowStockNotif = $row['count'];
$totalNotif = $newOrdersNotif + $lowStockNotif;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard | Seven Dwarfs Boutique</title>

  <!-- Libraries -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- NProgress (Loading Bar) -->
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <style>
    :root {
      --rose: #e59ca8;
      --rose-hover: #d27b8c;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }

    .active {
      background-color: #fce8eb;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }

    /* Hide scrollbar for sidebar */
    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    @view-transition {
      navigation: auto;
    }

    @keyframes fadeInSlide {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fade-in {
      animation: fadeInSlide 0.4s ease-out;
    }

    #nprogress .bar {
      background: var(--rose) !important;
      height: 3px !important;
    }

    #nprogress .peg {
      box-shadow: 0 0 10px var(--rose), 0 0 5px var(--rose) !important;
    }

    #nprogress .spinner-icon {
      border-top-color: var(--rose) !important;
      border-left-color: var(--rose) !important;
    }
  </style>
</head>

<body class="text-sm animate-fade-in">

  <div class="flex min-h-screen" x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: false 
     }" x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

    <!-- Sidebar -->
    <aside
      class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden"
      :class="sidebarOpen ? 'w-64' : 'w-20'">
      <!-- Logo -->
      <div class="p-5 border-b flex items-center h-20 transition-all duration-300"
        :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="img/logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300"
          x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
      </div>

      <!-- Admin Profile -->
      <div class="p-5 border-b flex items-center h-24 transition-all duration-300"
        :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="img/newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
        <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-4 space-y-1">
        <a href="dashboard.php" class="block px-4 py-3 active flex items-center transition-all duration-300"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
        </a>

        <!-- User Management -->
        <div>
          <button @click="userMenu = !userMenu"
            class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300"
            :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
            <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
              <i class="fas fa-users-cog w-5 text-center text-lg"></i>
              <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
            </div>
            <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': userMenu }"></i>
          </button>
          <ul x-show="userMenu"
            class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all"
            :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
            <li>
              <a href="manage_users.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Users">
                <i class="fas fa-user w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Users</span>
              </a>
            </li>
            <li>
              <a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Roles">
                <i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Roles</span>
              </a>
            </li>
          </ul>
        </div>
        <a href="suppliers.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-industry w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
        </a>
        <!-- Product Management -->
        <div>
          <button @click="productMenu = !productMenu"
            class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300"
            :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
            <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
              <i class="fas fa-box-open w-5 text-center text-lg"></i>
              <span x-show="sidebarOpen" class="whitespace-nowrap">Product Management</span>
            </div>
            <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': productMenu }"></i>
          </button>
          <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden"
            :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
            <li>
              <a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Category">
                <i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Category</span>
              </a>
            </li>
            <li>
              <a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Product">
                <i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Product</span>
              </a>
            </li>
            <li>
              <a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Stock">
                <i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Stock In</span>
              </a>
            </li>
            <li>
              <a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Inventory">
                <i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Inventory</span>
              </a>
            </li>
          </ul>
        </div>

        <a href="orders.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-shopping-cart w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Orders</span>
        </a>
        <a href="cashier_sales_report.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-chart-line w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Cashier Sales</span>
        </a>

        <a href="system_logs.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-file-alt w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">System Logs</span>
        </a>
        <a href="settings.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i
            class="fas fa-cogs w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Settings</span></a>

        <a href="logout.php"
          class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
        </a>
      </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out"
      :class="sidebarOpen ? 'ml-64' : 'ml-20'">

      <!-- Header -->
      <header
        class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
        :class="sidebarOpen ? 'left-64' : 'left-20'">

        <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen"
            class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">Dashboard</h1>
        </div>

        <div class="flex items-center gap-4">
          <!-- Notification Bell -->
          <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button class="relative text-white text-lg hover:scale-110 transition" @click="open = !open">
              <i class="fas fa-bell"></i>
              <?php if ($totalNotif > 0): ?>
                <span
                  class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5 shadow"><?= $totalNotif ?></span>
              <?php endif; ?>
            </button>
            <div x-show="open"
              class="absolute right-0 mt-3 w-72 bg-white rounded-xl shadow-lg ring-1 ring-gray-100 overflow-hidden z-50 text-gray-700"
              style="display: none;">
              <div class="p-3 border-b border-gray-100 font-semibold text-sm">Notifications</div>
              <ul class="max-h-64 overflow-y-auto">
                <?php if ($newOrdersNotif > 0): ?>
                  <li class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50 border-b"><span
                      class="text-[var(--rose)]">🛒</span> <span><?= $newOrdersNotif ?> new order(s)</span></li>
                <?php endif; ?>
                <?php if ($lowStockNotif > 0): ?>
                  <li class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50 border-b"><span
                      class="text-yellow-500">📦</span> <span><?= $lowStockNotif ?> low stock item(s)</span></li>
                <?php endif; ?>
                <?php if ($totalNotif === 0): ?>
                  <li class="px-4 py-3 text-center text-gray-500">No new notifications</li><?php endif; ?>
              </ul>
            </div>
          </div>
        </div>
      </header>

      <!-- 🟢 AUTOMATIC FILTER SECTION -->
      <section class="px-6 mt-6 flex flex-col sm:flex-row justify-between items-end gap-4">
        <form method="GET" id="filterForm" class="flex flex-wrap items-end gap-2">
          <div class="flex flex-col gap-1">
            <label for="fromDate" class="text-xs font-bold text-gray-500 uppercase tracking-wider">From</label>
            <!-- Added onchange -->
            <input type="date" name="from" id="fromDate" value="<?= htmlspecialchars($from ?? '') ?>"
              onchange="this.form.submit()"
              class="border border-gray-300 bg-white px-3 py-2 rounded-lg shadow-sm focus:ring-2 focus:ring-[var(--rose)] focus:outline-none text-sm cursor-pointer hover:bg-gray-50 transition">
          </div>

          <div class="flex flex-col gap-1">
            <label for="toDate" class="text-xs font-bold text-gray-500 uppercase tracking-wider">To</label>
            <!-- Added onchange -->
            <input type="date" name="to" id="toDate" value="<?= htmlspecialchars($to ?? '') ?>"
              onchange="this.form.submit()"
              class="border border-gray-300 bg-white px-3 py-2 rounded-lg shadow-sm focus:ring-2 focus:ring-[var(--rose)] focus:outline-none text-sm cursor-pointer hover:bg-gray-50 transition">
          </div>

          <!-- Added Reset Button when filter is active -->
          <?php if ($filter === 'custom'): ?>
            <div class="h-9 flex items-end">
              <a href="dashboard.php"
                class="px-4 py-2 bg-gray-200 text-gray-600 rounded-lg shadow hover:bg-gray-300 transition text-xs font-bold flex items-center gap-1">
                <i class="fas fa-times"></i> Reset
              </a>
            </div>
          <?php endif; ?>
        </form>

        <div class="text-right">
          <span class="text-xs text-gray-500 block">Date: <?= date('M j, Y') ?></span>
          <?php if ($mostSalesCount > 0): ?>
            <span class="text-xs text-[var(--rose)] font-bold">Peak: <?= $mostSalesDate ?></span>
          <?php endif; ?>
        </div>
      </section>

      <!-- 1. KEY METRICS CARDS -->
      <section class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Card 1: Revenue -->
        <div
          class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-green-500 flex items-center justify-between hover:shadow-md transition">
          <div>
            <p class="text-xs text-gray-500 uppercase font-bold">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">₱<?= number_format($totalRevenue, 2) ?></p>
          </div>
          <div class="bg-green-100 p-3 rounded-full text-green-600"><i class="fas fa-money-bill-wave"></i></div>
        </div>

        <!-- Card 2: Orders -->
        <div
          class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-purple-500 flex items-center justify-between hover:shadow-md transition">
          <div>
            <p class="text-xs text-gray-500 uppercase font-bold">New Orders</p>
            <p class="text-2xl font-bold text-gray-800 mt-1"><?= $newOrders ?></p>
          </div>
          <div class="bg-purple-100 p-3 rounded-full text-purple-600"><i class="fas fa-shopping-bag"></i></div>
        </div>

        <!-- Card 3: Sales Count -->
        <div
          class="bg-white p-5 rounded-xl shadow-sm border-l-4 border-blue-500 flex items-center justify-between hover:shadow-md transition">
          <div>
            <p class="text-xs text-gray-500 uppercase font-bold">Total Sales</p>
            <p class="text-2xl font-bold text-gray-800 mt-1"><?= $totalSales ?></p>
          </div>
          <div class="bg-blue-100 p-3 rounded-full text-blue-600"><i class="fas fa-receipt"></i></div>
        </div>

        <!-- Card 4: Top Product -->
        <a href="#top-products-modal" id="topProductCard"
          class="bg-gradient-to-r from-amber-500 to-orange-500 p-5 rounded-xl shadow-sm text-white flex flex-col justify-between hover:scale-[1.02] transition cursor-pointer relative overflow-hidden group">
          <div class="absolute right-0 top-0 p-4 opacity-20 transform group-hover:rotate-12 transition">
            <i class="fas fa-crown text-5xl"></i>
          </div>
          <p class="text-xs uppercase font-bold text-amber-100 opacity-90">Top Item (<?= ucfirst($filter) ?>)</p>
          <div class="mt-2 relative z-10">
            <?php if ($topProductQty > 0): ?>
              <p class="text-lg font-bold leading-tight truncate"><?= htmlspecialchars($topProductName) ?></p>
              <p class="text-sm mt-1 font-medium opacity-90"><?= $topProductQty ?> Sold</p>
            <?php else: ?>
              <p class="text-lg font-bold">No Sales</p>
            <?php endif; ?>
          </div>
        </a>
      </section>

      <!-- 2. CHARTS SECTION -->
      <section class="px-6 grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Chart 1: Sales Trend -->
        <div class="bg-white p-5 rounded-xl shadow-sm lg:col-span-2">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-gray-700">Sales Trends</h3>
            <select id="trendChartType" class="text-xs border rounded p-1 text-gray-600 focus:outline-none">
              <option value="line">Line</option>
              <option value="bar">Bar</option>
            </select>
          </div>
          <div class="h-64 w-full">
            <canvas id="ordersChart"></canvas>
          </div>
        </div>
        <!-- Chart 2: Top 5 Products -->
        <div class="bg-white p-5 rounded-xl shadow-sm">
          <h3 class="font-bold text-gray-700 mb-4">Top 5 Products</h3>
          <div class="h-64 w-full">
            <canvas id="topProductsChart"></canvas>
          </div>
        </div>
      </section>

      <!-- 3. RECENT ORDERS TABLE -->
      <section class="px-6 mb-10">
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
          <div class="p-5 border-b flex justify-between items-center">
            <h3 class="font-bold text-gray-700">Recent Orders</h3>
            <a href="orders.php" class="text-xs text-[var(--rose)] hover:underline">View All</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50 text-gray-500 uppercase font-medium text-xs">
                <tr>
                  <th class="px-5 py-3">Order ID</th>
                  <th class="px-5 py-3">Customer</th>
                  <th class="px-5 py-3">Date</th>
                  <th class="px-5 py-3">Amount</th>
                  <th class="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if ($recentOrdersRes->num_rows > 0):
                  while ($order = $recentOrdersRes->fetch_assoc()):
                    $status_id = $order['order_status_id'];
                    $status_name = 'Unknown';
                    $bgClass = 'bg-gray-100 text-gray-600';
                    switch ($status_id) {
                      case 0:
                        $status_name = 'Completed';
                        $bgClass = 'bg-green-100 text-green-700';
                        break;
                      case 2:
                        $status_name = 'Cancelled';
                        $bgClass = 'bg-red-100 text-red-700';
                        break;
                      case 4:
                        $status_name = 'Refunded';
                        $bgClass = 'bg-orange-100 text-orange-700';
                        break;
                      default:
                        $status_name = 'Status #' . $status_id;
                    }
                    ?>
                    <tr class="hover:bg-gray-50 transition">
                      <td class="px-5 py-3 font-medium">#<?= $order['order_id'] ?></td>
                      <td class="px-5 py-3"><?= htmlspecialchars($order['customer'] ?? 'Walk-in') ?></td>
                      <td class="px-5 py-3 text-xs text-gray-500"><?= date('M j, H:i', strtotime($order['created_at'])) ?>
                      </td>
                      <td class="px-5 py-3 font-semibold text-gray-700">₱<?= number_format($order['total_amount'], 2) ?>
                      </td>
                      <td class="px-5 py-3">
                        <span
                          class="px-3 py-1 rounded-full text-xs font-bold <?= $bgClass ?>"><?= htmlspecialchars($status_name) ?></span>
                      </td>
                    </tr>
                  <?php endwhile; else: ?>
                  <tr>
                    <td colspan="5" class="px-5 py-3 text-center text-gray-500">No recent orders.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </main>

    <!-- Modal for Top Products -->
    <div id="top-products-modal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg relative animate-[fadeIn_0.3s_ease-out]">
        <button id="closeTopProducts"
          class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        <h3 class="text-lg font-bold mb-4 text-gray-800 border-b pb-2">Top Selling Products (<?= ucfirst($filter) ?>)
        </h3>
        <div class="overflow-y-auto max-h-[400px]">
          <table class="w-full table-auto border-collapse text-sm">
            <thead class="bg-yellow-50 text-yellow-700 sticky top-0">
              <tr>
                <th class="px-3 py-3 text-left rounded-tl-lg">Product</th>
                <th class="px-3 py-3 text-right rounded-tr-lg">Qty Sold</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $topList = $conn->query("SELECT p.product_name, SUM(oi.qty) as total_qty FROM order_items oi JOIN orders o ON oi.order_id = o.order_id JOIN products p ON oi.product_id = p.product_id WHERE $dateConditionWithAlias GROUP BY oi.product_id ORDER BY total_qty DESC LIMIT 10");
              if ($topList && $topList->num_rows > 0):
                $rank = 1;
                while ($row = $topList->fetch_assoc()):
                  ?>
                  <tr class="border-b hover:bg-gray-50 transition">
                    <td class="px-3 py-3 font-medium text-gray-700 flex items-center gap-2">
                      <span
                        class="text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full <?= $rank <= 3 ? 'bg-yellow-100 text-yellow-600' : 'bg-gray-100 text-gray-500' ?>"><?= $rank++ ?></span>
                      <?= htmlspecialchars($row['product_name']) ?>
                    </td>
                    <td class="px-3 py-3 text-right font-bold text-[var(--rose)]"><?= $row['total_qty'] ?></td>
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="2" class="p-4 text-center text-gray-500">No sales found for this period.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <script>
    // --- DATA FROM PHP ---
    const labels = <?= json_encode($chartLabels) ?>;
    const dataOrders = <?= json_encode($chartValues) ?>;
    const topProdLabels = <?= json_encode($top5Labels) ?>;
    const topProdData = <?= json_encode($top5Data) ?>;

    // --- CHART 1: SALES TREND ---
    const ctx1 = document.getElementById('ordersChart').getContext('2d');
    let trendGradient = ctx1.createLinearGradient(0, 0, 0, 400);
    trendGradient.addColorStop(0, 'rgba(229, 156, 168, 0.5)');
    trendGradient.addColorStop(1, 'rgba(255, 255, 255, 0)');

    let trendChart = new Chart(ctx1, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Orders',
          data: dataOrders,
          borderColor: '#e59ca8',
          backgroundColor: trendGradient,
          fill: true,
          tension: 0.4,
          pointRadius: 3,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { stepSize: 1 } }
        }
      }
    });

    document.getElementById('trendChartType').addEventListener('change', function () {
      trendChart.config.type = this.value;
      trendChart.update();
    });

    // --- CHART 2: TOP PRODUCTS ---
    const ctx2 = document.getElementById('topProductsChart').getContext('2d');
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: topProdLabels,
        datasets: [{
          label: 'Sold',
          data: topProdData,
          backgroundColor: ['#e7a753ff', '#C0C0C0', '#599ed6ff', '#e59ca8', '#e59ca8'],
          borderRadius: 4,
          barThickness: 20
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, grid: { display: false }, ticks: { stepSize: 1 } },
          y: { grid: { display: false } }
        }
      }
    });

    // --- MODAL LOGIC ---
    const topProductCard = document.getElementById('topProductCard');
    const topProductsModal = document.getElementById('top-products-modal');
    const closeTopProducts = document.getElementById('closeTopProducts');

    if (topProductCard && topProductsModal) {
      topProductCard.addEventListener('click', (e) => { e.preventDefault(); topProductsModal.classList.remove('hidden'); });
      closeTopProducts.addEventListener('click', () => topProductsModal.classList.add('hidden'));
      topProductsModal.addEventListener('click', (e) => { if (e.target === topProductsModal) topProductsModal.classList.add('hidden'); });
    }

    // --- NPROGRESS LOGIC (LOADING BAR) ---
    document.addEventListener('DOMContentLoaded', () => {
      // Start bar on link clicks
      document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
          // Avoid triggers on hash links (#) or new tabs
          if (link.getAttribute('href').startsWith('#') || link.target === '_blank') return;
          NProgress.start();
        });
      });

      // Finish bar when page is fully loaded
      window.addEventListener('load', () => NProgress.done());
      // Failsafe if back button is used
      window.addEventListener('pageshow', () => NProgress.done());
    });
  </script>
</body>

</html>