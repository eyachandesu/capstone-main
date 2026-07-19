<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch Admin Details
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

// 🔹 Fetch dropdowns
$categories = []; $r = $conn->query("SELECT category_name FROM categories ORDER BY category_name"); while($row=$r->fetch_assoc()) $categories[]=$row;
$colors = []; $r = $conn->query("SELECT color FROM colors ORDER BY color"); while($row=$r->fetch_assoc()) $colors[]=$row;
$sizes = []; $r = $conn->query("SELECT size FROM sizes ORDER BY size"); while($row=$r->fetch_assoc()) $sizes[]=$row;

// 🔹 Filters
$catFilter = $_GET['category'] ?? ''; // Changed default from 'all' to empty string for logic handling
$colFilter = $_GET['color'] ?? 'all';
$sizFilter = $_GET['size'] ?? 'all';
$statusFilter = $_GET['stock_status'] ?? 'all';
$search = $_GET['search'] ?? '';

// 🔹 QUERY: Fetch Stock + Actual History of Returns/Damages
$sql = "
    SELECT 
        st.stock_id,
        p.product_name,
        c.category_name,
        co.color,
        sz.size,
        st.current_qty,
        p.created_at,
        
        -- 1. Total Stock In (Purchased from Supplier)
        COALESCE((SELECT SUM(quantity) FROM stock_in WHERE stock_id = st.stock_id), 0) AS total_in,
        
        -- 2. Total Sold (From Orders)
        COALESCE((SELECT SUM(qty) FROM order_items WHERE stock_id = st.stock_id), 0) AS total_sold,
        
        -- 3. Returns (Restocked/Sellable)
        COALESCE((SELECT SUM(quantity) FROM stock_adjustments WHERE stock_id = st.stock_id AND type = 'return_restock'), 0) AS returned_restock,
        
        -- 4. Damaged/Discarded (From Returns or Manual Adjustment)
        COALESCE((SELECT SUM(quantity) FROM stock_adjustments WHERE stock_id = st.stock_id AND type IN ('damaged', 'return_discard', 'lost')), 0) AS total_damaged

    FROM stock st
    JOIN products p ON st.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN colors co ON st.color_id = co.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
    WHERE 1=1
";

// Apply Filters
$types = ""; $params = [];

// 🟢 MODIFIED: Multi-Select Category Logic
if(!empty($catFilter) && $catFilter !== 'all') { 
    $catArray = explode(',', $catFilter); // Convert "Dress,Blouse" to array
    // Create placeholders (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($catArray), '?'));
    $sql .= " AND c.category_name IN ($placeholders)";
    
    // Add params
    foreach ($catArray as $cat) {
        $params[] = $cat;
        $types .= "s";
    }
}

if($colFilter !== 'all') { $sql .= " AND co.color = ?"; $params[] = $colFilter; $types .= "s"; }
if($sizFilter !== 'all') { $sql .= " AND sz.size = ?"; $params[] = $sizFilter; $types .= "s"; }
if($statusFilter === 'in_stock') { $sql .= " AND st.current_qty > 0"; }
elseif($statusFilter === 'out_of_stock') { $sql .= " AND st.current_qty <= 0"; }
if($search) { $sql .= " AND p.product_name LIKE ?"; $params[] = "%$search%"; $types .= "s"; }

$sql .= " ORDER BY p.product_name ASC";

$stmt = $conn->prepare($sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$data = $stmt->get_result();
$inventory = [];
while($row = $data->fetch_assoc()) $inventory[] = $row;

// 🔹 AJAX Output
if(isset($_GET['ajax'])) {
    if(empty($inventory)) { echo "<tr><td colspan='10' class='text-center py-8 text-gray-400'><i class='fas fa-box-open text-2xl mb-2 block'></i>No inventory items found.</td></tr>"; exit; }
    foreach($inventory as $i) {
        $qty = (int)$i['current_qty'];
        
        // Status Badge
        if($qty == 0) { $cls="bg-red-100 text-red-700 border-red-200"; $lbl="Out of Stock"; }
        elseif($qty < 0) { $cls="bg-yellow-100 text-yellow-700 border-yellow-200"; $lbl="Low Stock"; }
        else { $cls="bg-green-100 text-green-700 border-green-200"; $lbl="In Stock"; }

        // Formatting
        $prodName = htmlspecialchars($i['product_name']);
        $catName = htmlspecialchars($i['category_name']);
        $colBadge = $i['color'] ? "<span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-200 shadow-sm bg-white text-gray-800'><span class='w-2 h-2 mr-1.5 rounded-full bg-gray-400'></span>" . htmlspecialchars($i['color']) . "</span>" : "<span class='text-gray-400'>—</span>";
        $sizBadge = $i['size'] ? "<span class='inline-block w-8 h-8 leading-8 text-center rounded bg-white border border-gray-300 text-xs font-bold text-gray-700 shadow-sm'>" . htmlspecialchars($i['size']) . "</span>" : "<span class='text-gray-400'>—</span>";

        echo "<tr class='hover:bg-gray-50 transition duration-150'>
            <td class='px-6 py-4 font-medium text-gray-900'>{$prodName}</td>
            <td class='px-6 py-4'><span class='bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-xs font-medium'>{$catName}</span></td>
            <td class='px-6 py-4 text-center'>$colBadge</td>
            <td class='px-6 py-4 text-center'>$sizBadge</td>
            
            <!-- INVENTORY LOGIC COLUMNS -->
            <td class='px-6 py-4 text-center text-blue-600 font-bold'>{$i['total_in']}</td>
            
            <td class='px-6 py-4 text-center text-orange-600 font-bold'>
                - {$i['total_sold']}
            </td>

            <td class='px-6 py-4 text-center text-green-600 font-bold'>
                ".($i['returned_restock'] > 0 ? "+ {$i['returned_restock']}" : "<span class='text-gray-300'>0</span>")."
            </td>

            <td class='px-6 py-4 text-center text-red-500 font-bold'>
                ".($i['total_damaged'] > 0 ? "- {$i['total_damaged']}" : "<span class='text-gray-300'>0</span>")."
            </td>

            <td class='px-6 py-4 text-center'>
                <span class='text-lg font-bold text-gray-800 bg-gray-50 px-3 py-1 rounded border border-gray-200'>{$qty}</span>
            </td>

            <td class='px-6 py-4 text-center'><span class='px-2.5 py-1 rounded-full text-xs font-bold border $cls'>$lbl</span></td>
        </tr>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory | Seven Dwarfs Boutique</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />

  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    @keyframes fadeInSlide { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }
    #nprogress .bar { background: var(--rose) !important; height: 3px !important; }
    #nprogress .peg { box-shadow: 0 0 10px var(--rose), 0 0 5px var(--rose) !important; }
  </style>
</head>

<body class="text-sm animate-fade-in">

<!-- Global State Wrapper -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: true
     }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- 🌸 Sidebar -->
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
            <i class="fas fa-box-open w-5 text-center text-lg text-[var(--rose)]"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Category</span></a></li>
          <li><a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Product</span></a></li>
          <li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Stock In</span></a></li>
          <li><a href="inventory.php" class="block py-2 active flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Inventory</span></a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
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

  <!-- 🌸 Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
             <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">Inventory Management</h1>
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6">
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <!-- Filters -->
            <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-gray-50">
                <div class="flex flex-wrap gap-4 items-center z-10">
                    
                    <!-- 🟢 MODIFIED: Multi-Select Category Checkboxes using Alpine.js -->
                    <div class="relative" x-data="{
                        open: false,
                        selected: [],
                        toggle(value) {
                            if (this.selected.includes(value)) {
                                this.selected = this.selected.filter(i => i !== value);
                            } else {
                                this.selected.push(value);
                            }
                            // Update hidden input for load() function to read
                            document.getElementById('cat').value = this.selected.join(',');
                            load(); // Trigger AJAX
                        },
                        displayText() {
                            if (this.selected.length === 0) return 'All Categories';
                            if (this.selected.length === 1) return this.selected[0];
                            return this.selected.length + ' Selected';
                        }
                    }" @click.away="open = false">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Category</label>
                        
                        <!-- Hidden Input used by JS load() function -->
                        <input type="hidden" id="cat" value="">

                        <!-- Dropdown Button -->
                        <button @click="open = !open" type="button" class="w-full md:w-48 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm bg-white text-left flex justify-between items-center">
                            <span class="truncate" x-text="displayText()"></span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                        </button>

                        <!-- Dropdown Content -->
                        <div x-show="open" class="absolute mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto" style="display: none;">
                            <div class="p-2 space-y-1">
                                <?php foreach ($categories as $c): ?>
                                    <label class="flex items-center space-x-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" value="<?= htmlspecialchars($c['category_name']) ?>" 
                                            @change="toggle($el.value)" 
                                            class="rounded text-[var(--rose)] focus:ring-[var(--rose)] border-gray-300">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($c['category_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <!-- 🟢 END MODIFIED -->

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Color</label>
                        <select id="col" onchange="load()" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[120px] cursor-pointer bg-white">
                        <option value="all">All</option><?php foreach ($colors as $c) echo "<option value='{$c['color']}'>{$c['color']}</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Size</label>
                        <select id="siz" onchange="load()" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[100px] cursor-pointer bg-white">
                        <option value="all">All</option><?php foreach ($sizes as $c) echo "<option value='{$c['size']}'>{$c['size']}</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                        <select id="sts" onchange="load()" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[120px] cursor-pointer bg-white">
                            <option value="all">All</option><option value="in_stock">In Stock</option><option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                </div>

                <div class="w-full md:w-auto">
                     <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                     <div class="relative">
                         <input type="text" id="search" oninput="load()" placeholder="Search..." class="w-full md:w-64 pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm shadow-sm bg-white">
                         <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                     </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-100 text-gray-500 uppercase font-bold text-xs border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Product Name</th>
                            <th class="px-6 py-4">Category</th>
                            <th class="px-6 py-4 text-center">Color</th>
                            <th class="px-6 py-4 text-center">Size</th>
                            <!-- LOGIC COLUMNS -->
                            <th class="px-6 py-4 text-center bg-blue-50 text-blue-700">Total In</th>
                            <th class="px-6 py-4 text-center bg-orange-50 text-orange-700">Sold</th>
                            <th class="px-6 py-4 text-center bg-green-50 text-green-700">Returned (Good)</th>
                            <th class="px-6 py-4 text-center bg-red-50 text-red-700">Damaged</th>
                            <th class="px-6 py-4 text-center bg-gray-200 text-gray-800">Remaining</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="divide-y divide-gray-100 bg-white">
                        <!-- AJAX LOADED CONTENT -->
                    </tbody>
                </table>
            </div>

        </div> 
    </section>

  </main>
</div>

<!-- Scripts -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // NProgress
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
            if(link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript') || link.target === '_blank') return;
            NProgress.start();
        });
    });
    window.addEventListener('load', () => { NProgress.done(); load(); });
});

let timeout = null;
function load() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        const params = new URLSearchParams({
            ajax: 1,
            category: document.getElementById('cat').value, // This now reads the hidden input populated by Alpine.js
            color: document.getElementById('col').value,
            size: document.getElementById('siz').value,
            stock_status: document.getElementById('sts').value,
            search: document.getElementById('search').value
        });
        fetch('inventory.php?' + params)
            .then(r => r.text())
            .then(html => document.getElementById('inventoryTableBody').innerHTML = html)
            .catch(e => console.error(e));
    }, 300);
}
</script>

</body>
</html>