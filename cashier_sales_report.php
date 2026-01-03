<?php
session_start();
require 'admin_only.php';
require 'conn.php';

// Verify admin session
if (!isset($_SESSION["admin_id"])) {
  header("Location: login.php");
  exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch current logged-in admin info
$query = "
  SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, r.role_name 
  FROM adminusers a
  LEFT JOIN roles r ON a.role_id = r.role_id
  WHERE a.admin_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  $admin_name = $row['full_name'];
  $admin_role = $row['role_name'] ?? 'Admin';
}
$stmt->close();

// 🔹 Handle Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$selected_cashier = $_GET['cashier_id'] ?? '';

// Build Query Conditions
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Date Filter
if ($start_date && $end_date) {
    $where_clauses[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
    $date_label = "From: " . date('M d, Y', strtotime($start_date)) . " To: " . date('M d, Y', strtotime($end_date));
} else {
    $date_label = "All Time";
}

// Cashier Filter logic
$cashier_label = "All Cashiers";
if ($selected_cashier) {
    $where_clauses[] = "o.admin_id = ?";
    $params[] = $selected_cashier;
    $types .= "i";
    
    // Fetch name for label
    $c_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM adminusers WHERE admin_id = ?");
    $c_stmt->bind_param("i", $selected_cashier);
    $c_stmt->execute();
    $c_stmt->bind_result($fetched_cashier_name);
    if($c_stmt->fetch()) { $cashier_label = $fetched_cashier_name; }
    $c_stmt->close();
} else {
    $where_clauses[] = "o.admin_id IS NOT NULL";
}

$where_sql = implode(" AND ", $where_clauses);

// 🔹 1. Fetch List of Cashiers for Dropdown
$cashier_sql = "SELECT admin_id, CONCAT(first_name, ' ', last_name) AS cashier_name FROM adminusers WHERE role_id = 1 ORDER BY first_name ASC";
$cashier_list = $conn->query($cashier_sql)->fetch_all(MYSQLI_ASSOC);

// 🔹 2. Calculate Grand Total
$total_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as grand_total FROM orders o WHERE $where_sql";
$stmt_total = $conn->prepare($total_sales_query);
if (!empty($params)) { $stmt_total->bind_param($types, ...$params); }
$stmt_total->execute();
$grand_total_sales = $stmt_total->get_result()->fetch_assoc()['grand_total'];
$stmt_total->close();

// 🔹 3. Breakdown by Cashier
$breakdown_sql = "
    SELECT 
        CONCAT(a.first_name, ' ', a.last_name) AS cashier_name,
        COALESCE(SUM(o.total_amount), 0) AS total_sales,
        COUNT(o.order_id) AS transaction_count
    FROM orders o
    LEFT JOIN adminusers a ON o.admin_id = a.admin_id
    WHERE $where_sql
    GROUP BY o.admin_id
    ORDER BY total_sales DESC
";
$stmt_breakdown = $conn->prepare($breakdown_sql);
if (!empty($params)) { $stmt_breakdown->bind_param($types, ...$params); }
$stmt_breakdown->execute();
$cashier_performance = $stmt_breakdown->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_breakdown->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales Summary | Seven Dwarfs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    .active-nav { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    
    /* 🖨 PRINT STYLES */
    @media print {
        @page { size: portrait; margin: 15mm; }
        body { background: white; color: black; -webkit-print-color-adjust: exact; }
        aside, header, nav, form, .no-print { display: none !important; }
        main { margin: 0 !important; padding: 0 !important; }
        #printable-summary { display: block !important; width: 100%; }
        h1, h2, p, th, td { color: black !important; }
        .bg-gray-100 { background-color: #f3f4f6 !important; }
    }
  </style>
</head>

<body class="text-sm">

<div class="flex min-h-screen" 
     x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, userMenu: false, productMenu: false }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- 🌸 Sidebar -->
  <aside class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden no-print" :class="sidebarOpen ? 'w-64' : 'w-20'">
    <div class="p-5 border-b flex items-center h-20 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300" x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
    </div>
    <div class="p-5 border-b flex items-center h-24 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
      <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-tachometer-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">Dashboard</span></a>
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'"><div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''"><i class="fas fa-users-cog w-5 text-center text-lg"></i><span x-show="sidebarOpen">User Management</span></div><i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i></button>
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'"><li><a href="manage_users.php" class="block py-2 hover:text-[var(--rose)]">Users</a></li><li><a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)]">Roles</a></li></ul>
      </div>
      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-industry w-5 text-center text-lg"></i><span x-show="sidebarOpen">Suppliers</span></a>
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'"><div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''"><i class="fas fa-box-open w-5 text-center text-lg"></i><span x-show="sidebarOpen">Product Management</span></div><i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i></button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'"><li><a href="categories.php" class="block py-2 hover:text-[var(--rose)]">Category</a></li><li><a href="products.php" class="block py-2 hover:text-[var(--rose)]">Product</a></li><li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)]">Stock In</a></li><li><a href="inventory.php" class="block py-2 hover:text-[var(--rose)]">Inventory</a></li></ul>
      </div>
      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-shopping-cart w-5 text-center text-lg"></i><span x-show="sidebarOpen">Orders</span></a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 active-nav flex items-center transition-all duration-300" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-chart-line w-5 text-center text-lg"></i><span x-show="sidebarOpen">Cashier Sales</span></a>
      <a href="system_logs.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-file-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Logs</span></a>
    <a href="settings.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-cogs w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Settings</span></a>
      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-sign-out-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">Logout</span></a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <!-- Header -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out no-print" :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
          <div><h1 class="text-xl font-semibold">Cashier Sales Summary</h1><p class="text-xs text-white/80"><?= htmlspecialchars($date_label); ?></p></div>
      </div>
      <div>
         <button onclick="window.print()" class="bg-white text-[var(--rose)] px-4 py-2 rounded-lg font-bold shadow-md hover:bg-gray-100 transition flex items-center gap-2"><i class="fas fa-print"></i> Print Report</button>
      </div>
    </header>

    <section class="p-6 space-y-6">

        <!-- 🔹 PRINTABLE SUMMARY REPORT (Hidden on screen) -->
        <div id="printable-summary" class="hidden bg-white p-10 border border-gray-200">
            <div class="text-center mb-8 pb-4 border-b-2 border-gray-800">
                <h1 class="text-3xl font-bold uppercase tracking-widest mb-1">Seven Dwarfs Boutique</h1>
                <h2 class="text-xl font-semibold text-gray-600">Sales Summary Report</h2>
                <p class="text-sm mt-2">Date Generated: <?= date('M d, Y h:i A'); ?></p>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8 text-base">
                <div><p class="text-gray-500 uppercase text-xs font-bold mb-1">Report Period</p><p class="font-medium text-lg border-l-4 border-[var(--rose)] pl-3"><?= htmlspecialchars($date_label); ?></p></div>
                <div class="text-right"><p class="text-gray-500 uppercase text-xs font-bold mb-1">Filter Context</p><p class="font-medium text-lg"><?= htmlspecialchars($cashier_label); ?></p></div>
            </div>

            <table class="w-full text-left border-collapse mb-8">
                <thead>
                    <tr class="bg-gray-100 border-b-2 border-gray-300">
                        <th class="py-3 px-4 uppercase text-xs font-bold text-gray-600">Cashier Name</th>
                        <th class="py-3 px-4 uppercase text-xs font-bold text-gray-600 text-center">Transactions</th>
                        <th class="py-3 px-4 uppercase text-xs font-bold text-gray-600 text-right">Sales Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($cashier_performance) > 0): ?>
                        <?php foreach($cashier_performance as $cp): ?>
                        <tr class="border-b border-gray-200">
                            <td class="py-3 px-4 font-medium"><?= htmlspecialchars($cp['cashier_name']); ?></td>
                            <td class="py-3 px-4 text-center"><?= number_format($cp['transaction_count']); ?></td>
                            <td class="py-3 px-4 text-right font-bold">₱<?= number_format($cp['total_sales'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="py-4 text-center text-gray-500 italic">No sales found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold text-lg">
                        <td class="py-3 px-4 text-right" colspan="2">GRAND TOTAL:</td>
                        <td class="py-3 px-4 text-right text-[var(--rose)]">₱<?= number_format($grand_total_sales, 2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-16 flex justify-between pt-8 border-t border-dashed border-gray-400">
                <div class="text-center w-64"><p class="font-bold text-lg mb-10 border-b border-gray-800 pb-2"><?= htmlspecialchars($admin_name); ?></p><p class="text-xs uppercase text-gray-500">Prepared By</p></div>
                <div class="text-center w-64"><p class="font-bold text-lg mb-10 border-b border-gray-800 pb-2">&nbsp;</p><p class="text-xs uppercase text-gray-500">Received / Verified By</p></div>
            </div>
        </div>
        <!-- 🔹 END PRINTABLE AREA -->

        <!-- 🔹 FILTER BAR (Now at the top) -->
        <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-end gap-4 no-print">
            <div class="flex flex-col gap-1">
                <label for="cashier_id" class="font-bold text-gray-700 text-xs uppercase">Filter Cashier:</label>
                <div class="relative">
                    <select name="cashier_id" id="cashier_id" onchange="this.form.submit()" class="pl-3 pr-8 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-48 appearance-none">
                        <option value="">Show All</option>
                        <?php foreach ($cashier_list as $c): ?>
                            <option value="<?= $c['admin_id']; ?>" <?= $selected_cashier == $c['admin_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['cashier_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-gray-500"><i class="fas fa-chevron-down text-xs"></i></span>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label for="start_date" class="font-bold text-gray-700 text-xs uppercase">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date); ?>" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="end_date" class="font-bold text-gray-700 text-xs uppercase">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date); ?>" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" />
            </div>
            <div class="pb-0.5">
                <a href="cashier_sales_report.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition text-sm flex items-center gap-2"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
          
        <!-- 🔹 Screen Only: Cashier Sales Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 no-print">
            <!-- Grand Total Card -->
            <div class="col-span-full bg-gradient-to-r from-gray-800 to-gray-700 text-white p-6 rounded-2xl shadow-lg flex justify-between items-center">
                <div>
                    <p class="text-gray-300 text-xs font-bold uppercase tracking-wider">Grand Total Revenue</p>
                    <h2 class="text-4xl font-extrabold mt-1">₱<?= number_format($grand_total_sales, 2); ?></h2>
                    <p class="text-gray-400 text-xs mt-1"><?= htmlspecialchars($date_label); ?></p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-md">
                    <i class="fas fa-coins text-2xl"></i>
                </div>
            </div>

            <!-- Individual Cashier Cards -->
            <?php if (count($cashier_performance) > 0): ?>
                <?php foreach ($cashier_performance as $cp): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow relative overflow-hidden group">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-sm">
                                <?= strtoupper(substr($cp['cashier_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-lg leading-tight"><?= htmlspecialchars($cp['cashier_name']); ?></h3>
                                <span class="text-xs font-medium text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">Cashier</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 grid grid-cols-2 gap-4 border-t border-dashed border-gray-100 pt-4">
                        <div>
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Orders</p>
                            <p class="text-xl font-bold text-gray-700"><?= number_format($cp['transaction_count']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Total Sales</p>
                            <p class="text-xl font-extrabold text-[var(--rose)]">₱<?= number_format($cp['total_sales'], 2); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full py-12 text-center text-gray-400 bg-white rounded-2xl border border-gray-100 border-dashed">
                    <i class="fas fa-cash-register text-4xl mb-3 text-gray-200"></i>
                    <p>No sales data found for the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>

    </section>

  </main>
</div>

</body>
</html>