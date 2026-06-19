<?php
session_start();
require_once __DIR__ . '/../config/config.php';
ini_set('display_errors', 0); 
error_reporting(E_ALL);

// 1. ✅ CONFIG: Fix SQL Modes
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
$conn->query("SET SESSION group_concat_max_len = 100000");

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
$admin_id = $_SESSION['admin_id'];

// Fetch cashier info
$cashierRes = $conn->prepare("SELECT first_name, last_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id); $cashierRes->execute();
$cashierRow = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashierRow ? $cashierRow['first_name'] . ' ' . $cashierRow['last_name'] : 'Unknown';
$cashierRes->close();

/* =============================================================
   📅 DATE FILTER LOGIC
============================================================= */
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');

// Safety checks
$startDate = $conn->real_escape_string($startDate);
$endDate   = $conn->real_escape_string($endDate);
$dateCondition = "DATE(o.created_at) BETWEEN '$startDate' AND '$endDate'";

// Label for Display
if ($startDate === $endDate) {
    $label = date("F j, Y", strtotime($startDate));
    $printLabel = date("F j, Y", strtotime($startDate));
    $isSingleDay = true;
} else {
    $label = date("M j", strtotime($startDate)) . " - " . date("M j", strtotime($endDate));
    $printLabel = date("F j, Y", strtotime($startDate)) . " to " . date("F j, Y", strtotime($endDate));
    $isSingleDay = false;
}

/* =============================================================
   1. MAIN QUERY
============================================================= */
$query = "
    SELECT
        o.order_id,
        COALESCE(o.transaction_id, CONCAT('ORD-', o.order_id)) AS transaction_display_id,
        o.total_amount, o.cash_given, o.changes, o.created_at,
        pm.payment_method_name, os.order_status_name AS status,
        COUNT(oi.id) AS total_items,
        GROUP_CONCAT(CONCAT(oi.qty, '***', COALESCE(p.product_name, 'Unknown'), '***', COALESCE(sz.size, oi.size, '-'), '***', COALESCE(cl.color, oi.color, '-'), '***', COALESCE(p.price_id, 0)) SEPARATOR '///') as item_details,
        (SELECT GROUP_CONCAT(CONCAT(r.refund_id, '***', r.order_item_id, '***', r.refund_amount, '***', COALESCE(pr.product_name, 'Unknown'), '***', COALESCE(sz2.size, '-'), '***', COALESCE(cl2.color, '-'), '***', r.refunded_at) SEPARATOR '///') FROM refunds r LEFT JOIN order_items oi2 ON r.order_item_id = oi2.id LEFT JOIN products pr ON r.product_id = pr.product_id LEFT JOIN sizes sz2 ON r.size_id = sz2.size_id LEFT JOIN colors cl2 ON r.color_id = cl2.color_id WHERE r.order_id = o.order_id) as refund_details
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
    LEFT JOIN stock st ON oi.stock_id = st.stock_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
    LEFT JOIN colors cl ON st.color_id = cl.color_id
    WHERE $dateCondition AND o.admin_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id); $stmt->execute();
$result = $stmt->get_result();
$transactionCount = $result->num_rows;

// Total Sales
$totalStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM orders o WHERE $dateCondition AND o.admin_id = ?");
$totalStmt->bind_param("i", $admin_id); $totalStmt->execute();
$totalSales = $totalStmt->get_result()->fetch_assoc()['total_sales'] ?? 0;
$totalStmt->close();

// Trending Products
$trendingStmt = $conn->prepare("SELECT p.product_name, p.image_url, p.price_id as price, SUM(oi.qty) as total_sold FROM order_items oi JOIN orders o ON oi.order_id = o.order_id JOIN products p ON oi.product_id = p.product_id WHERE $dateCondition AND o.admin_id = ? GROUP BY oi.product_id ORDER BY total_sold DESC LIMIT 5");
$trendingStmt->bind_param("i", $admin_id); $trendingStmt->execute();
$trendingResult = $trendingStmt->get_result();
$trendingItems = [];
while($row = $trendingResult->fetch_assoc()) {
    $img = 'uploads/default.png';
    if (!empty($row['image_url'])) {
        if (str_starts_with(trim($row['image_url']), '[')) { $decoded = json_decode($row['image_url'], true); $img = !empty($decoded) ? $decoded[0] : 'uploads/default.png'; } 
        elseif (str_contains($row['image_url'], ',')) { $parts = explode(',', $row['image_url']); $img = trim($parts[0]); } 
        else { $img = trim($row['image_url']); }
    }
    $row['final_image'] = $img;
    $trendingItems[] = $row;
}
$trendingStmt->close();

/* =============================================================
   📊 CHART DATA LOGIC
============================================================= */
if ($isSingleDay) {
    $groupBy = 'HOUR(o.created_at)'; $orderBy = 'HOUR(o.created_at)'; $selectLabel = 'HOUR(o.created_at)';
} else {
    $groupBy = 'DATE(o.created_at)'; $orderBy = 'DATE(o.created_at)'; $selectLabel = 'DATE(o.created_at)';
}
$chartStmt = $conn->prepare("SELECT $selectLabel as label, SUM(o.total_amount) as total FROM orders o WHERE $dateCondition AND o.admin_id = ? GROUP BY $groupBy ORDER BY $orderBy");
$chartStmt->bind_param("i", $admin_id);
$chartStmt->execute();
$chartDataRes = $chartStmt->get_result();
$chartLabels = []; $chartTotals = [];
while ($row = $chartDataRes->fetch_assoc()) {
    if ($isSingleDay) { $chartLabels[] = date("g A", strtotime($startDate . " " . $row['label'] . ":00:00")); } 
    else { $chartLabels[] = date("M d", strtotime($row['label'])); }
    $chartTotals[] = $row['total'];
}
$chartStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Report | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
<style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .sidebar-text { transition: opacity 0.2s ease-in-out, transform 0.2s ease; white-space: nowrap; }
    .w-20 .sidebar-text { opacity: 0; transform: translateX(-10px); pointer-events: none; }
    .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.6); }
    input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; filter: invert(0.4); }
    input[type="date"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }

    /* 🖨️ PRINT STYLES - Transforms Dashboard into Report */
    @media print {
        @page { size: A4; margin: 10mm; }
        body { background-color: white; color: black; overflow: visible !important; height: auto !important; }
        #sidebar, #dateFormContainer, #printBtn, .no-print-element { display: none !important; }
        main { margin: 0 !important; padding: 0 !important; overflow: visible !important; height: auto !important; }
        .glass-header { position: static; border: none; background: none; padding: 0 !important; height: auto !important; margin-bottom: 20px; }
        .custom-scroll { overflow: visible !important; height: auto !important; }
        
        /* Show the Report Header */
        #reportHeader { display: block !important; }
        
        /* Simplify Cards for Print */
        .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; margin-bottom: 20px !important; }
        .stat-card { border: 1px solid #ddd !important; box-shadow: none !important; background: white !important; color: black !important; }
        .stat-card-icon { display: none !important; }
        .stat-card h3 { color: black !important; font-size: 1.5rem !important; }
        
        /* Hide Charts & Extra Visuals to save ink */
        #chartSection, #bestSellerSection { display: none !important; }
        
        /* Table Styling for Print */
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f3f4f6 !important; font-weight: bold; -webkit-print-color-adjust: exact; }
        .print-full-width { width: 100% !important; }
        
        /* Hide Receipt Links in Table */
        .action-col { display: none !important; }
    }
</style>
</head>

<body class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

<!-- SIDEBAR (Hidden on Print) -->
<aside id="sidebar" :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-white border-r border-slate-100 flex flex-col h-full z-30 shadow-sm relative shrink-0">
    <div class="flex items-center justify-between h-20 px-0 pl-6 border-b border-slate-100">
        <div class="flex items-center gap-2 overflow-hidden">
            <div class="shrink-0 text-xl font-bold text-rose-600 tracking-tight">SD</div>
            <div class="sidebar-text" :class="!sidebarOpen && 'opacity-0'">
                <h1 class="text-xl font-bold text-rose-600 tracking-tight">Seven Dwarfs</h1>
                <p class="text-[10px] font-semibold text-slate-300 uppercase tracking-widest">Boutique POS</p>
            </div>
        </div>
    </div>
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3 top-24 bg-white border border-slate-200 rounded-full p-1 shadow-md text-slate-400 hover:text-rose-600 transition-colors z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
    </button>
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto no-scrollbar">
        <a href="cashier_pos.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden"><i class="fas fa-cash-register w-6 text-center text-lg group-hover:scale-110 transition-transform"></i><span class="sidebar-text">POS Terminal</span></a>
        <a href="cashier_transactions.php" class="flex items-center gap-3 px-3 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-all group overflow-hidden relative"><i class="fas fa-receipt w-6 text-center text-lg"></i><span class="sidebar-text">Transactions</span></a>
        <a href="cashier_inventory.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden"><i class="fas fa-boxes w-6 text-center text-lg group-hover:scale-110 transition-transform"></i><span class="sidebar-text">Inventory</span></a>
    </nav>
    <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3 overflow-hidden">
        <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm shrink-0"><?= strtoupper(substr($cashier_name,0,1)) ?></div>
        <div class="sidebar-text overflow-hidden">
            <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p>
            <form action="logout.php" method="POST"><button class="text-xs text-rose-500 font-medium hover:underline">Sign Out</button></form>
        </div>
    </div>
</aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden h-full bg-[#f9fafb]">
        
        <!-- 🖨️ REPORT HEADER (Only Visible on Print) -->
        <div id="reportHeader" class="hidden pb-4 border-b-2 border-slate-800 mb-6">
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-2xl font-bold uppercase tracking-wider text-slate-900">Seven Dwarfs Boutique</h1>
                    <p class="text-sm text-slate-600">Sales Report</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-bold text-slate-500 uppercase">Date Range</p>
                    <p class="text-sm font-bold text-slate-900"><?= $printLabel ?></p>
                </div>
            </div>
            <div class="mt-4 flex justify-between text-xs text-slate-500">
                <p>Generated by: <span class="font-bold text-slate-700"><?= htmlspecialchars($cashier_name) ?></span></p>
                <p>Generated on: <?= date("F j, Y g:i A") ?></p>
            </div>
        </div>

        <!-- UI Header (Hidden on Print) -->
        <header class="h-20 glass-header flex items-center justify-between px-8 z-20 shrink-0">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Transactions</h2>
                <p class="text-xs text-slate-500 font-medium">Data for: <span class="text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded ml-1"><?= $label ?></span></p>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- 📅 AUTO-UPDATE DATE PICKER -->
                <form id="dateFormContainer" method="GET" class="flex items-center gap-3 bg-white rounded-xl border border-slate-200 p-2 shadow-sm">
                    <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">From</span>
                        <!-- Added onchange=submit() -->
                        <input type="date" name="start_date" value="<?= $startDate ?>" onchange="this.form.submit()" required class="bg-transparent text-sm font-bold text-slate-700 outline-none w-32 cursor-pointer">
                    </div>
                    <div class="w-px h-6 bg-slate-200"></div>
                    <div class="flex items-center gap-2 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">To</span>
                        <!-- Added onchange=submit() -->
                        <input type="date" name="end_date" value="<?= $endDate ?>" onchange="this.form.submit()" required class="bg-transparent text-sm font-bold text-slate-700 outline-none w-32 cursor-pointer">
                    </div>
                </form>

                <!-- 🖨️ PRINT BUTTON -->
                <button id="printBtn" onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white p-3 rounded-xl shadow-lg hover:shadow-xl transition active:scale-95" title="Print Report">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8 custom-scroll">
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 stats-grid">
                <!-- Revenue -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between fade-in-up stat-card" style="animation-delay: 0s;">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Revenue</p>
                        <h3 class="text-3xl font-bold text-rose-600 mt-1">₱<?= number_format($totalSales, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 shadow-sm stat-card-icon"><i class="fas fa-coins text-lg"></i></div>
                </div>
                <!-- Count -->
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between fade-in-up stat-card" style="animation-delay: 0.1s;">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Transactions</p>
                        <h3 class="text-3xl font-bold text-slate-800 mt-1"><?= number_format($transactionCount) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 shadow-sm stat-card-icon"><i class="fas fa-receipt text-lg"></i></div>
                </div>
                <!-- Top Item (Hidden on Print usually, or simplified) -->
                <div id="bestSellerSection" class="bg-gradient-to-br from-rose-500 to-pink-600 p-6 rounded-2xl shadow-lg shadow-rose-200 text-white flex items-center justify-between relative overflow-hidden fade-in-up" style="animation-delay: 0.2s;">
                    <div class="absolute -right-6 -bottom-6 text-white/10 text-9xl"><i class="fas fa-crown"></i></div>
                    <div class="relative z-10">
                        <p class="text-xs font-bold text-rose-100 uppercase tracking-wider">Overall Best Seller</p>
                        <h3 class="text-xl font-bold mt-1 truncate w-40"><?= !empty($trendingItems) ? htmlspecialchars($trendingItems[0]['product_name']) : 'N/A' ?></h3>
                        <p class="text-sm mt-1 text-rose-100 font-medium"><?= !empty($trendingItems) ? $trendingItems[0]['total_sold'] . ' units sold' : '-' ?></p>
                    </div>
                    <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm relative z-10"><i class="fas fa-star text-xl"></i></div>
                </div>
            </div>

            <!-- Charts (Hidden on Print) -->
            <div id="chartSection" class="flex flex-col lg:flex-row gap-6 mb-8 min-h-[380px] fade-in-up" style="animation-delay: 0.3s;">
                <div class="flex-1 bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg text-slate-800">Sales Analytics</h3>
                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full"><i class="far fa-calendar-alt mr-1"></i> <?= $label ?></span>
                    </div>
                    <div class="flex-1 relative w-full h-64 lg:h-auto"><canvas id="salesChart"></canvas></div>
                </div>
                <!-- Best Sellers List -->
                <div class="w-full lg:w-[380px] bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                    <div class="flex justify-between items-end mb-4"><h3 class="font-bold text-lg text-slate-800">Top 5 Products</h3></div>
                    <?php if (count($trendingItems) > 0): $top = $trendingItems[0]; $rest = array_slice($trendingItems, 1); ?>
                        <div class="bg-gradient-to-r from-rose-50 to-white rounded-xl p-4 flex gap-4 items-center mb-4 border border-rose-100 relative overflow-hidden group transition-all hover:shadow-md cursor-default">
                            <div class="absolute top-0 left-0 bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-br-lg z-10 shadow-sm">#1 Top Seller</div>
                            <img src="<?= htmlspecialchars($top['final_image']) ?>" class="w-16 h-16 rounded-lg object-cover bg-white shadow-sm group-hover:scale-105 transition-transform duration-300">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-bold text-slate-800 text-sm truncate"><?= htmlspecialchars($top['product_name']) ?></h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs font-bold text-rose-600 bg-white px-2 py-0.5 rounded shadow-sm border border-rose-100"><?= $top['total_sold'] ?> sold</span>
                                    <span class="text-[10px] text-slate-400">In this period</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto space-y-2 pr-1 custom-scroll">
                            <?php foreach ($rest as $index => $item): ?>
                            <div class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg transition-colors group">
                                <div class="font-bold text-slate-300 text-sm w-5 text-center group-hover:text-rose-400 transition-colors"><?= $index + 2 ?></div>
                                <img src="<?= htmlspecialchars($item['final_image']) ?>" class="w-10 h-10 rounded-md object-cover bg-slate-100 border border-slate-100">
                                <div class="flex-1 min-w-0"><h5 class="text-xs font-bold text-slate-700 truncate"><?= htmlspecialchars($item['product_name']) ?></h5><p class="text-[10px] text-slate-500"><?= $item['total_sold'] ?> sold</p></div>
                                <div class="text-xs font-semibold text-slate-500">₱<?= number_format($item['price'], 0) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-slate-400"><i class="fas fa-box-open text-3xl mb-2 opacity-30"></i><p class="text-sm">No sales recorded yet</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLE -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden fade-in-up print-full-width" style="animation-delay: 0.4s;">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center no-print-element">
                    <h3 class="font-bold text-slate-800">Transaction History</h3>
                </div>
                <div class="overflow-x-auto custom-scroll">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Transaction ID</th>
                                <th class="px-6 py-4 w-[35%]">Items</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4 text-right action-col">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $statusName = "Completed";
                                    $itemStrings = !empty($row['item_details']) ? explode('///', $row['item_details']) : [];
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <td class="px-6 py-4 font-bold text-slate-600 align-top font-mono text-xs tracking-tight">
                                        <?= htmlspecialchars($row['transaction_display_id']); ?>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <?php if(empty($itemStrings)): ?>
                                            <span class="text-xs text-slate-400 italic">No items data</span>
                                        <?php else:
                                            $allItems = [];
                                            foreach($itemStrings as $str) {
                                                $parts = explode('***', $str);
                                                $qty = isset($parts[0]) ? abs($parts[0]) : 0;
                                                $name = isset($parts[1]) ? $parts[1] : 'Unknown';
                                                $size = (isset($parts[2]) && $parts[2] !== '-') ? $parts[2] : null;
                                                $color = (isset($parts[3]) && $parts[3] !== '-') ? $parts[3] : null;
                                                $price = (isset($parts[4])) ? $parts[4] : 0;
                                                $specs = []; if ($size) $specs[] = $size; if ($color) $specs[] = ucfirst($color);
                                                $specString = !empty($specs) ? ' <span class="text-slate-400 text-[10px] font-normal">(' . implode(', ', $specs) . ')</span>' : '';
                                                $allItems[] = ['qty' => $qty, 'name' => $name, 'specString' => $specString, 'price' => $price];
                                            }
                                            $refundDetails = [];
                                            if (!empty($row['refund_details'])) {
                                                $refundStrings = explode('///', $row['refund_details']);
                                                foreach ($refundStrings as $rstr) {
                                                    $rparts = explode('***', $rstr);
                                                    if (count($rparts) >= 7) { $refundDetails[] = [ 'amount' => $rparts[2], 'name' => $rparts[3] ]; }
                                                }
                                            }
                                        ?>
                                            <div class="flex flex-col gap-3">
                                                <div class="space-y-1">
                                                    <?php foreach($allItems as $item): ?><div class="text-sm text-slate-700 font-medium leading-tight"><span class="text-slate-400 text-xs mr-1"><?= $item['qty'] ?>x</span><?= htmlspecialchars($item['name']) ?><?= $item['specString'] ?></div><?php endforeach; ?>
                                                </div>
                                                <?php if(!empty($refundDetails)): ?>
                                                    <div class="border-t border-dashed border-rose-200 pt-2 mt-1">
                                                        <div class="flex items-center gap-1 mb-1"><i class="fas fa-undo text-[10px] text-rose-500"></i><span class="text-[10px] font-bold text-rose-600 uppercase tracking-wide">Returned Items</span></div>
                                                        <div class="space-y-1"><?php foreach($refundDetails as $r): ?><div class="text-xs text-rose-500 bg-rose-50 px-2 py-1 rounded w-fit flex gap-2 items-center"><span><?= htmlspecialchars($r['name']) ?></span><span class="font-bold">-₱<?= number_format($r['amount'], 2) ?></span></div><?php endforeach; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 align-top"><div class="font-bold text-slate-800">₱<?= number_format($row['total_amount'], 2); ?></div><?php if($row['cash_given'] > 0): ?><div class="text-[10px] text-slate-400 mt-0.5">Cash: ₱<?= number_format($row['cash_given'], 2) ?></div><div class="text-[10px] text-slate-400">Change: ₱<?= number_format($row['changes'], 2) ?></div><?php endif; ?></td>
                                    <td class="px-6 py-4 align-top"><span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wide <?= strtolower($row['payment_method_name']) == 'gcash' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-green-50 text-green-600 border border-green-100' ?>"><?= htmlspecialchars($row['payment_method_name']); ?></span></td>
                                    <td class="px-6 py-4 align-top"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200 flex w-fit items-center gap-1"><div class="w-1.5 h-1.5 rounded-full bg-slate-400"></div><?= htmlspecialchars($statusName); ?></span></td>
                                    <td class="px-6 py-4 text-slate-500 align-top text-xs"><div class="font-medium text-slate-700"><?= date('M d, Y', strtotime($row['created_at'])); ?></div><div class="text-[10px] text-slate-400"><?= date('h:i A', strtotime($row['created_at'])); ?></div></td>
                                    <td class="px-6 py-4 text-right align-top action-col"><a href="receipt.php?order_id=<?= $row['order_id']; ?>" target="_blank" class="inline-flex items-center gap-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 px-2 py-1 rounded transition text-xs font-bold border border-transparent hover:border-rose-100"><i class="fas fa-print"></i> Receipt</a></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="px-6 py-20 text-center text-slate-400"><i class="fas fa-inbox text-4xl mb-3 text-slate-200"></i><p class="text-sm font-medium">No transactions found for this period.</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Chart Config (Hidden on Print) -->
    <div class="no-print-element">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(225, 29, 72, 0.15)');
        gradient.addColorStop(1, 'rgba(225, 29, 72, 0)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels); ?>,
                datasets: [{ 
                    label: 'Sales', 
                    data: <?= json_encode($chartTotals); ?>, 
                    backgroundColor: gradient, 
                    borderColor: '#e11d48', 
                    borderWidth: 2, 
                    pointBackgroundColor: '#fff', 
                    pointBorderColor: '#e11d48', 
                    pointBorderWidth: 2, 
                    pointRadius: 4, 
                    pointHoverRadius: 6, 
                    fill: true, 
                    tension: 0.35 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        backgroundColor: '#1e293b', 
                        titleFont: { family: 'Poppins', size: 13 },
                        bodyFont: { family: 'Poppins', size: 13, weight: 'bold' },
                        padding: 12, 
                        cornerRadius: 8, 
                        displayColors: false, 
                        callbacks: { label: function(context) { return '₱' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2}); } } 
                    } 
                }, 
                scales: { 
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Poppins', size: 11 } } }, 
                    y: { grid: { color: '#f1f5f9', borderDash: [5, 5] }, ticks: { color: '#94a3b8', font: { family: 'Poppins', size: 11 }, callback: function(value) { return '₱' + value; } }, beginAtZero: true } 
                } 
            }
        });
        </script>
    </div>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>