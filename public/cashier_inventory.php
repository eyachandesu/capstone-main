<?php
session_start();
require_once __DIR__ . '/../config/config.php';
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure cashier logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get cashier info
$cashierRes = $conn->prepare("SELECT first_name, role_id FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashier = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashier['first_name'] ?? 'Cashier';
$role_id = $cashier['role_id'] ?? 0;
$cashierRes->close();

// --- FILTERS ---

// 1. Category Filter (Array Handling)
$selected_categories = isset($_GET['category']) ? $_GET['category'] : [];
if (!is_array($selected_categories)) {
    $selected_categories = [];
}

// 2. Other Filters
$selected_size = $_GET['size'] ?? 'all';
$selected_color = $_GET['color'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all';

// Fetch Options
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$sizes = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id ORDER BY s.size ASC");
$colors = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id ORDER BY c.color ASC");

// --- QUERY BUILDER ---
$query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.price_id AS price,
        p.supplier_price,
        c.category_name,
        p.image_url,
        COALESCE(sz.size, 'Free Size') as size,
        COALESCE(cl.color, 'Standard') as color,
        s.current_qty AS stock_qty
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN colors cl ON s.color_id = cl.color_id
    WHERE 1=1
";

$params = [];
$types = "";

// Category Logic
if (!empty($selected_categories)) {
    $placeholders = implode(',', array_fill(0, count($selected_categories), '?'));
    $query .= " AND p.category_id IN ($placeholders) ";
    foreach ($selected_categories as $cat_id) {
        $params[] = $cat_id;
        $types .= "i";
    }
}

// Standard Filters
if ($selected_size !== 'all') { $query .= " AND sz.size = ? "; $params[] = $selected_size; $types .= "s"; }
if ($selected_color !== 'all') { $query .= " AND cl.color = ? "; $params[] = $selected_color; $types .= "s"; }
if ($selected_status === 'in_stock') { $query .= " AND s.current_qty > 0 "; } 
elseif ($selected_status === 'out_of_stock') { $query .= " AND s.current_qty <= 0 "; }

$query .= " ORDER BY p.product_name ASC, s.current_qty ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory | Seven Dwarfs Boutique</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>

    <style>
        :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.4s ease-out forwards; opacity: 0; }
        #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-text { transition: opacity 0.2s ease-in-out, transform 0.2s ease; white-space: nowrap; }
        .w-20 .sidebar-text { opacity: 0; transform: translateX(-10px); pointer-events: none; }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.6); }
        .custom-checkbox:checked { background-color: #e11d48; border-color: #e11d48; }
        .custom-checkbox:checked + div { font-weight: 700; color: #e11d48; }
    </style>

    <script>
        // Alpine Logic to keep dropdown open after page reload
        document.addEventListener('alpine:init', () => {
            Alpine.data('categoryFilter', () => ({
                open: localStorage.getItem('cat_menu_open') === 'true',
                
                toggle() {
                    this.open = !this.open;
                    if(!this.open) localStorage.removeItem('cat_menu_open');
                },
                
                submit(event) {
                    // Save state so it re-opens after reload
                    localStorage.setItem('cat_menu_open', 'true');
                    // Submit the form
                    document.getElementById('filterForm').submit();
                }
            }))
        })
    </script>
</head>
<body class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

    <!-- SIDEBAR -->
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
            <a href="cashier_transactions.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden"><i class="fas fa-receipt w-6 text-center text-lg group-hover:scale-110 transition-transform"></i><span class="sidebar-text">Transactions</span></a>
            <a href="cashier_inventory.php" class="flex items-center gap-3 px-3 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-all group overflow-hidden relative"><i class="fas fa-boxes w-6 text-center text-lg"></i><span class="sidebar-text">Inventory</span></a>
        </nav>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3 overflow-hidden">
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm shrink-0"><?= strtoupper(substr($cashier_name,0,1)) ?></div>
            <div class="sidebar-text overflow-hidden"><p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p><form action="/controllers/logout.php" method="POST"><button class="text-xs text-rose-500 font-medium hover:underline">Sign Out</button></form></div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden h-full bg-[#f9fafb]">
        <header class="h-20 glass-header flex items-center justify-between px-8 z-20 shrink-0">
            <div><h2 class="text-2xl font-bold text-slate-800 tracking-tight">Inventory</h2><p class="text-xs text-slate-500 font-medium">Track stock levels by variant</p></div>
            <div class="relative"><input id="searchInput" type="search" placeholder="Search product variant..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-200 w-72 shadow-sm transition-all hover:border-rose-300"><svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 custom-scroll bg-[#f9fafb]">
            
            <!-- Filters -->
            <form id="filterForm" method="GET" class="relative z-30 flex flex-wrap gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 items-end fade-in-up" style="animation-delay: 0s;">
                
                <!-- Stock Status -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Stock Status</label>
                    <div class="relative">
                        <select name="status" class="w-40 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Status</option>
                            <option value="in_stock" <?= $selected_status == 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="out_of_stock" <?= $selected_status == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>

                <!-- CATEGORY DROPDOWN (Alpine Logic: categoryFilter) -->
                <div class="relative" x-data="categoryFilter">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Category</label>
                    
                    <!-- Toggle Button -->
                    <button type="button" @click="toggle()" @click.outside="if(open) toggle()" class="w-48 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 flex justify-between items-center font-medium hover:bg-slate-100 transition">
                        <span><?php if(empty($selected_categories)) echo "All Categories"; else echo count($selected_categories) . " Selected"; ?></span>
                        <i class="fas fa-chevron-down text-xs text-slate-500"></i>
                    </button>
                    
                    <!-- Dropdown Content -->
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-100" 
                         x-transition:enter-start="transform opacity-0 scale-95" 
                         x-transition:enter-end="transform opacity-100 scale-100" 
                         class="absolute z-50 mt-2 w-64 bg-white rounded-xl shadow-xl border border-slate-100 p-3"
                         style="display: none;">
                        
                        <div class="max-h-60 overflow-y-auto custom-scroll space-y-2">
                            <?php 
                            // Reset pointer for re-use
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer transition">
                                    <input type="checkbox" name="category[]" 
                                           value="<?= $cat['category_id'] ?>" 
                                           class="custom-checkbox w-4 h-4 text-rose-600 rounded border-slate-300 focus:ring-rose-500"
                                           <?= in_array($cat['category_id'], $selected_categories) ? 'checked' : '' ?>
                                           @change="submit($event)"> <!-- Auto-submit on change -->
                                    <div class="text-sm text-slate-600"><?= htmlspecialchars($cat['category_name']) ?></div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <!-- Size -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Size</label>
                    <div class="relative">
                        <select name="size" class="w-32 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Sizes</option>
                            <?php while ($s = $sizes->fetch_assoc()): ?><option value="<?= $s['size'] ?>" <?= $selected_size == $s['size'] ? 'selected' : '' ?>><?= htmlspecialchars($s['size']) ?></option><?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>

                <!-- Color -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Color</label>
                    <div class="relative">
                        <select name="color" class="w-32 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Colors</option>
                            <?php while ($c = $colors->fetch_assoc()): ?><option value="<?= $c['color'] ?>" <?= $selected_color == $c['color'] ? 'selected' : '' ?>><?= htmlspecialchars($c['color']) ?></option><?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>

                <div class="ml-auto pb-1"><a href="cashier_inventory.php" onclick="localStorage.removeItem('cat_menu_open')" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition flex items-center gap-1"><i class="fas fa-times"></i> Clear Filters</a></div>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto fade-in-up relative z-10" style="animation-delay: 0.1s; max-width: 100vw;">
                <table class="min-w-[1200px] w-full text-sm text-left align-middle">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-semibold border-b border-slate-100 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4">Product</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4 text-center">Size</th>
                            <th class="px-6 py-4 text-center">Color</th>
                            <th class="px-6 py-4">Price</th>
                            <th class="px-6 py-4 text-center bg-blue-50 text-blue-700">Stock In</th>
                            <th class="px-6 py-4 text-center bg-orange-50 text-orange-700">Sold</th>
                            <th class="px-6 py-4 text-center bg-green-50 text-green-700">Returned</th>
                            <th class="px-6 py-4 text-center bg-red-50 text-red-700">Damaged</th>
                            <th class="px-6 py-4 text-center bg-gray-200 text-gray-800">Remaining</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-center">Return History</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="divide-y divide-slate-50">
                        <?php if ($result->num_rows > 0): ?>
                            <?php $delay = 0; while ($row = $result->fetch_assoc()): 
                                $imagePath = $row['image_url']; $img = 'uploads/default.png';
                                if (!empty($imagePath)) { if (str_starts_with(trim($imagePath), '[')) { $decoded = json_decode($imagePath, true); $img = is_array($decoded) && count($decoded) > 0 ? $decoded[0] : 'uploads/default.png'; } elseif (str_contains($imagePath, ',')) { $parts = explode(',', $imagePath); $img = trim($parts[0]); } else { $img = trim($imagePath); } }
                                $stock = $row['stock_qty'] ?? 0; $delay += 0.05;
                                
                                $stock_id = null; $stock_id_query = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? AND size_id = (SELECT size_id FROM sizes WHERE size = ?) AND color_id = (SELECT color_id FROM colors WHERE color = ?)");
                                $stock_id_query->bind_param("iss", $row['product_id'], $row['size'], $row['color']); $stock_id_query->execute(); $stock_id_res = $stock_id_query->get_result(); if ($stock_id_row = $stock_id_res->fetch_assoc()) { $stock_id = $stock_id_row['stock_id']; } $stock_id_query->close();
                                
                                $q_in = $conn->query("SELECT COALESCE(SUM(quantity),0) AS total_in FROM stock_in WHERE stock_id = $stock_id")->fetch_assoc(); $total_in = intval($q_in['total_in']);
                                $q_sold = $conn->query("SELECT COALESCE(SUM(qty),0) AS total_sold FROM order_items WHERE stock_id = $stock_id")->fetch_assoc(); $total_sold = intval($q_sold['total_sold']);
                                $q_returned = $conn->query("SELECT COALESCE(SUM(quantity),0) AS returned_restock FROM stock_adjustments WHERE stock_id = $stock_id AND type = 'return_restock'")->fetch_assoc(); $returned_restock = intval($q_returned['returned_restock']);
                                $q_damaged = $conn->query("SELECT COALESCE(SUM(quantity),0) AS total_damaged FROM stock_adjustments WHERE stock_id = $stock_id AND type IN ('damaged','return_discard','lost')")->fetch_assoc(); $total_damaged = intval($q_damaged['total_damaged']);
                                $remaining = $total_in - $total_sold + $returned_restock - $total_damaged;
                            ?>
                            <tr class="hover:bg-rose-50/30 transition-colors group search-row fade-in-up" style="animation-delay: <?= $delay ?>s; animation-fill-mode: forwards;">
                                <td class="px-6 py-3"><div class="flex items-center gap-4 min-w-[220px]"><div class="relative flex items-center justify-center w-20 h-20 bg-slate-50 border border-slate-100 rounded-lg overflow-hidden"><img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-contain rounded-lg transition-transform duration-200 group-hover:scale-105" style="max-width:70px; max-height:70px;"><?php if($stock <= 0): ?><div class="absolute inset-0 bg-white/60 backdrop-blur-[1px] rounded-lg"></div><?php endif; ?></div><div><div class="font-bold text-slate-800 text-search"><?= htmlspecialchars($row['product_name']) ?></div><div class="text-[10px] text-slate-400 font-mono">ID: #<?= $row['product_id'] ?></div></div></div></td>
                                <td class="px-6 py-3"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide border border-slate-200"><?= htmlspecialchars($row['category_name']) ?></span></td>
                                <td class="px-6 py-3 text-center"><span class="text-slate-700 font-bold text-xs border border-slate-200 px-2.5 py-1 rounded-md bg-white shadow-sm"><?= htmlspecialchars($row['size'] ?? 'N/A') ?></span></td>
                                <td class="px-6 py-3 text-center"><?php if(!empty($row['color'])): ?><div class="flex items-center justify-center gap-2"><div class="w-3 h-3 rounded-full border border-slate-300 shadow-sm" style="background-color: <?= htmlspecialchars($row['color']) ?>;"></div><span class="text-xs font-medium text-slate-600"><?= htmlspecialchars($row['color']) ?></span></div><?php else: ?><span class="text-slate-400 text-xs">-</span><?php endif; ?></td>
                                <td class="px-6 py-3 font-bold text-rose-600 text-sm">₱<?= number_format($row['price'], 2) ?></td>
                                <td class="px-6 py-3 text-center bg-blue-50 text-blue-700 font-bold"><?= $total_in ?></td>
                                <td class="px-6 py-3 text-center bg-orange-50 text-orange-700 font-bold">-<?= $total_sold ?></td>
                                <td class="px-6 py-3 text-center bg-green-50 text-green-700 font-bold"><?= $returned_restock > 0 ? "+$returned_restock" : "0" ?></td>
                                <td class="px-6 py-3 text-center bg-red-50 text-red-700 font-bold"><?= $total_damaged > 0 ? "-$total_damaged" : "0" ?></td>
                                <td class="px-6 py-3 text-center bg-gray-200 text-gray-800 font-bold"><?= $remaining ?></td>
                                <td class="px-6 py-3 text-center"><?php if($remaining <= 0): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 uppercase tracking-wide"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Out of Stock</span><?php elseif($remaining < 0): ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-wide"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Low Stock</span><?php else: ?><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wide"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In Stock</span><?php endif; ?></td>
                                <td class="px-6 py-3 text-center"><button onclick="openReturnHistoryModal('rh<?= $stock_id ?>')" class="px-3 py-1 rounded bg-slate-100 text-slate-700 font-bold text-xs hover:bg-rose-100 transition">View</button></td>
                                <?php echo "<div id='rh{$stock_id}' class='fixed inset-0 z-50 hidden flex items-center justify-center bg-black/30'><div class='bg-white border border-slate-200 rounded-xl shadow-2xl p-6 w-full max-w-xs relative'><button onclick=\"closeReturnHistoryModal('rh{$stock_id}')\" class='absolute top-2 right-2 text-slate-400 hover:text-rose-600 text-xl font-bold'>&times;</button><div class='font-bold text-base mb-3 text-slate-700'>Return History</div>";
                                $returns = $conn->query("SELECT quantity, type, reason, created_at FROM stock_adjustments WHERE stock_id = $stock_id AND type IN ('return_restock','damaged','return_discard') ORDER BY created_at DESC LIMIT 5");
                                if ($returns->num_rows > 0) { while ($ret = $returns->fetch_assoc()) { $typeLabel = $ret['type'] === 'return_restock' ? '<span class="text-green-600 font-bold">Good</span>' : '<span class="text-red-600 font-bold">Damaged</span>'; echo '<div class="mb-3 text-xs flex flex-col gap-1"><div><b>Date:</b> ' . date('Y-m-d H:i', strtotime($ret['created_at'])) . '</div><div><b>Qty:</b> ' . intval($ret['quantity']) . ' | <b>Type:</b> ' . $typeLabel . '</div><div><b>Remarks:</b> ' . htmlspecialchars($ret['reason']) . '</div></div><hr class="my-2">'; } } else { echo '<div class="text-xs text-slate-400">No returns recorded.</div>'; }
                                echo "</div><script>function openReturnHistoryModal(id){document.getElementById(id).classList.remove('hidden');document.body.style.overflow='hidden';}function closeReturnHistoryModal(id){document.getElementById(id).classList.add('hidden');document.body.style.overflow='';}</script></div>"; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="12" class="px-6 py-12 text-center text-slate-400 text-sm bg-white"><div class="flex flex-col items-center"><i class="fas fa-search text-2xl mb-2 opacity-20"></i><p>No products found matching these filters.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-50 flex justify-between items-center bg-white" id="paginationContainer">
                <div class="text-xs text-slate-400 font-medium" id="pageInfo">Showing results</div>
                <div class="flex gap-2" id="pagination"></div>
            </div>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById("searchInput");
        const rows = Array.from(document.querySelectorAll(".search-row"));
        const pagination = document.getElementById("pagination");
        const pageInfo = document.getElementById("pageInfo");
        let currentPage = 1; const rowsPerPage = 10;
        function updateTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filtered = rows.filter(row => { const text = row.querySelector('.text-search').textContent.toLowerCase(); return text.includes(query); });
            const totalPages = Math.ceil(filtered.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = 1;
            const start = (currentPage - 1) * rowsPerPage; const end = start + rowsPerPage;
            rows.forEach(r => { r.classList.add('hidden'); r.style.animation = 'none'; });
            const slice = filtered.slice(start, end);
            slice.forEach((r, index) => { r.classList.remove('hidden'); void r.offsetWidth; r.style.animation = `fadeInUp 0.3s ease-out ${index * 0.05}s forwards`; });
            pageInfo.textContent = `Showing ${slice.length > 0 ? start + 1 : 0}-${Math.min(end, filtered.length)} of ${filtered.length} variants`;
            pagination.innerHTML = "";
            if (totalPages > 1) {
                const prev = document.createElement("button"); prev.innerHTML = '<i class="fas fa-chevron-left"></i>'; prev.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition text-xs"; prev.disabled = currentPage === 1; prev.onclick = () => { currentPage--; updateTable(); }; pagination.appendChild(prev);
                let startPage = Math.max(1, currentPage - 2); let endPage = Math.min(totalPages, currentPage + 2);
                for (let i = startPage; i <= endPage; i++) { const btn = document.createElement("button"); btn.textContent = i; btn.className = `w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition ${i === currentPage ? 'bg-rose-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-50 border border-slate-200'}`; btn.onclick = () => { currentPage = i; updateTable(); }; pagination.appendChild(btn); }
                const next = document.createElement("button"); next.innerHTML = '<i class="fas fa-chevron-right"></i>'; next.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition text-xs"; next.disabled = currentPage === totalPages; next.onclick = () => { currentPage++; updateTable(); }; pagination.appendChild(next);
            }
        }
        searchInput.addEventListener("input", () => { currentPage = 1; updateTable(); });
        updateTable();
    </script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>