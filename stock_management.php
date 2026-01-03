<?php
session_start();
require 'admin_only.php';
require 'conn.php';


function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch Admin Details
if ($admin_id) {
    $sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
            FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id
            WHERE a.admin_id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $admin_name = $row['full_name'] ?: $admin_name;
            $admin_role = $row['role_name'] ?: $admin_role;
        }
        $stmt->close();
    }
}

// 🔹 Fetch Lists for Dropdowns
$categories = [];
$res = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($c = $res->fetch_assoc()) $categories[] = $c;

$supplier_list = [];
$res = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
while ($s = $res->fetch_assoc()) $supplier_list[] = $s;

// 🟢 NEW: Fetch All Products for the Stock In Dropdown
$product_list = [];
$res = $conn->query("SELECT product_id, product_name, supplier_price, price_id, supplier_id FROM products ORDER BY product_name ASC");
while ($p = $res->fetch_assoc()) $product_list[] = $p;

$color_list = [];
$res = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
while ($c = $res->fetch_assoc()) $color_list[] = $c;

$size_list = [];
$res = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
while ($s = $res->fetch_assoc()) $size_list[] = $s;

// 🔹 Filters & Logic
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$selected_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : 'all';

$stock_rows = [];
$outStock = false;

// 🔹 Build Dynamic WHERE Clause
$whereClauses = [];
if ($selected_category) {
    $whereClauses[] = "p.category_id = ?";
}
if ($selected_status === 'in') {
    $whereClauses[] = "s.current_qty > 0";
} elseif ($selected_status === 'out') {
    $whereClauses[] = "s.current_qty <= 0";
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses) . " ";
}

// 🔹 Stock Query
// Note: MAX(si.date_added) retrieves the most recent stock-in date
$stockQuery = "
    SELECT 
        s.stock_id,
        p.product_id,
        p.product_name,
        p.supplier_price,
        p.price_id AS seller_price,
        s.color_id,
        s.size_id,
        col.color,
        sz.size,
        COALESCE(s.current_qty, 0) AS current_qty,
        p.supplier_id,
        sup.supplier_name,
        MAX(si.date_added) AS date_added
    FROM products p
    INNER JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN colors col ON s.color_id = col.color_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN stock_in si ON si.stock_id = s.stock_id
    LEFT JOIN suppliers sup ON p.supplier_id = sup.supplier_id
    " . $whereSQL . "
    GROUP BY s.stock_id, p.product_id, p.product_name, s.color_id, s.size_id, col.color, sz.size, s.current_qty
    ORDER BY 
        date_added DESC,
        s.stock_id DESC
";

if ($stmt = $conn->prepare($stockQuery)) {
    if ($selected_category) {
        $stmt->bind_param('i', $selected_category);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if ((int)$r['current_qty'] === 0) $outStock = true;
        $stock_rows[] = $r;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Management | Seven Dwarfs</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
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

<!-- 🟢 START GLOBAL WRAPPER -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: true,
        stockInOpen: false,
        editStockOpen: false,
        editData: { 
            id: null, 
            qty: 0,
            supplier_id: '',
            product_id: '',
            color_id: '',
            size_id: '',
            supplier_price: '',
            price: ''
        }
     }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

    <!-- 🌸 SIDEBAR -->
  <aside 
    class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto"
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

      <!-- User Management Dropdown -->
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li>
            <a href="manage_users.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Users">
              <i class="fas fa-user w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Users</span>
            </a>
          </li>
          <li>
            <a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Roles">
              <i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Roles</span>
            </a>
          </li>
        </ul>
      </div>
    <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
      </a>
      <!-- Product Management Dropdown (Active) -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg text-[var(--rose)]"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li>
            <a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Category">
              <i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Category</span>
            </a>
          </li>
          <li>
            <a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Product">
              <i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Product</span>
            </a>
          </li>
          <li>
            <a href="stock_management.php" class="block py-2 active-nav flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Stock">
              <i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Stock In</span>
            </a>
          </li>
          <li>
            <a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Inventory">
              <i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Inventory</span>
            </a>
          </li>
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

      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
      </a>
    </nav>
  </aside>


  <!-- Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition"><i class="fas fa-bars text-xl"></i></button>
          <h1 class="text-xl font-semibold">Stock Management</h1>
      </div>
    </header>

    <section class="p-6 space-y-6">
        <?php if ($outStock && $selected_status != 'in'): ?>
          <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl shadow-sm flex items-center animate-pulse">
            <i class="fas fa-exclamation-triangle mr-2"></i><span><strong>Alert:</strong> Some items are out of stock!</span>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                <!-- 🟢 FILTERS -->
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2">
                        <label class="text-gray-700 font-bold text-xs uppercase tracking-wide">Category:</label>
                        <select name="category_id" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-1 focus:ring-[var(--rose)]">
                          <option value="0">All Categories</option>
                          <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id']==$selected_category)?'selected':'' ?> ><?= e($cat['category_name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-gray-700 font-bold text-xs uppercase tracking-wide">Status:</label>
                        <select name="stock_status" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-1 focus:ring-[var(--rose)]">
                          <option value="all" <?= $selected_status == 'all' ? 'selected' : '' ?>>All Status</option>
                          <option value="in" <?= $selected_status == 'in' ? 'selected' : '' ?>>In Stock</option>
                          <option value="out" <?= $selected_status == 'out' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                </form>

                <button type="button" @click="stockInOpen = true"
                  class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white text-sm font-medium rounded-lg px-4 py-2 shadow-sm transition flex items-center gap-2">
                  <i class="fas fa-plus"></i> Stock In
                </button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Product</th>
                            <th class="px-6 py-3">Color</th>
                            <th class="px-6 py-3">Size</th>
                            <th class="px-6 py-3">Qty</th>
                            <th class="px-6 py-3">Supplier</th>
                            <!-- 🟢 ADDED COLUMN HEADER -->
                            <th class="px-6 py-3">Last Added</th>
                            <th class="px-6 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if (count($stock_rows) > 0): foreach ($stock_rows as $row): ?>
                          <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 font-bold text-gray-800">
                                <?= e($row['product_name']) ?>
                            </td>
                            <td class="px-6 py-4"><?= $row['color'] ? e($row['color']) : '<span class="text-gray-400">—</span>' ?></td>
                            <td class="px-6 py-4"><?= $row['size'] ? e($row['size']) : '<span class="text-gray-400">—</span>' ?></td>
                            <td class="px-6 py-4 font-bold <?= ((int)$row['current_qty'] == 0) ? 'text-red-600' : 'text-green-600' ?>"><?= (int)$row['current_qty'] ?></td>
                            <td class="px-6 py-4 text-xs text-gray-500"><?= e($row['supplier_name'] ?? 'N/A') ?></td>
                            
                            <!-- 🟢 ADDED DATE DATA -->
                            <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">
                                <?php echo $row['date_added'] ? date('M d, Y', strtotime($row['date_added'])) : '<span class="text-gray-300">-</span>'; ?>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <button 
                                    @click="
                                        editData.id = '<?= $row['stock_id'] ?>';
                                        editData.qty = '<?= (int)$row['current_qty'] ?>';
                                        editData.supplier_id = '<?= $row['supplier_id'] ?? '' ?>';
                                        editData.product_id = '<?= $row['product_id'] ?? '' ?>';
                                        editData.color_id = '<?= $row['color_id'] ?? '' ?>';
                                        editData.size_id = '<?= $row['size_id'] ?? '' ?>';
                                        
                                        editData.supplier_price = '<?= $row['supplier_price'] ?? '' ?>';
                                        editData.price = '<?= $row['seller_price'] ?? '' ?>';
                                        
                                        editStockOpen = true; 
                                        fetchProducts(editData.supplier_id, 'editProductSelect', editData.product_id);
                                    "
                                    class="text-[var(--rose)] hover:text-white hover:bg-[var(--rose)] p-2 rounded-lg transition" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </td>
                          </tr>
                        <?php endforeach; else: ?>
                          <!-- Updated colspan from 6 to 7 to match new column count -->
                          <tr><td colspan="7" class="text-center py-8 text-gray-500">No stock records found matching filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div> 
    </section>
  </main>

   <!-- Stock In Modal -->
  <div x-show="stockInOpen" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm animate-fade-in">
    <div @click.away="stockInOpen = false" class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all">
      <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Stock In (New)</h3>
      <form action="process_stock_in.php" method="POST" class="space-y-4">
        
        <!-- 🟢 Product Select -->
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Product</label>
          <select id="productSelect" name="product_id" 
                  onchange="onProductChange(this)" 
                  class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" required>
            <option value="">Select Product</option>
            <?php foreach ($product_list as $prod): ?>
                <option value="<?= $prod['product_id'] ?>" 
                        data-supplier-price="<?= $prod['supplier_price'] ?>"
                        data-seller-price="<?= $prod['price_id'] ?>"
                        data-default-supplier="<?= $prod['supplier_id'] ?>">
                    <?= htmlspecialchars($prod['product_name']) ?>
                </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 🟢 Supplier Select -->
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Supplier</label>
          <select id="supplierSelect" name="supplier_id" 
                  class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" required>
            <option value="">Select Supplier</option>
            <?php foreach ($supplier_list as $sup): ?>
              <option value="<?= $sup['supplier_id'] ?>"><?= htmlspecialchars($sup['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- Price Fields -->
        <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Supplier Price</label>
              <input type="number" step="0.01" id="stockInSupplierPrice" name="supplier_price" placeholder="0.00" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Seller Price</label>
              <input type="number" step="0.01" id="stockInSellerPrice" name="price" placeholder="0.00" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
            </div>
        </div>

        <!-- 🟢 UPDATED: Color & Size are now optional -->
        <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Color (Optional)</label>
              <!-- Removed required attribute -->
              <select name="color_id" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">None (Optional)</option>
                <?php foreach ($color_list as $c): ?>
                    <option value="<?= $c['color_id'] ?>"><?= htmlspecialchars($c['color']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Size (Optional)</label>
              <!-- Removed required attribute -->
              <select name="size_id" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">None (Optional)</option>
                <?php foreach ($size_list as $s): ?>
                    <option value="<?= $s['size_id'] ?>"><?= htmlspecialchars($s['size']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Quantity</label>
          <input type="number" name="quantity" min="1" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" required>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="stockInOpen=false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancel</button>
          <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg text-sm font-medium transition">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Stock Modal -->
  <div x-show="editStockOpen" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm animate-fade-in">
    <div @click.away="editStockOpen = false" class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all">
      <div class="flex justify-between items-center border-b pb-2 mb-4">
          <h3 class="text-xl font-bold text-gray-800">Edit Stock Details</h3>
          <button @click="editStockOpen = false" class="text-gray-400 hover:text-gray-600 transition"><i class="fas fa-times"></i></button>
      </div>

      <form action="process_edit_stock.php" method="POST" class="space-y-4">
        <input type="hidden" name="stock_id" x-model="editData.id">
        
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Supplier</label>
          <select id="editSupplierSelect" name="supplier_id" x-model="editData.supplier_id" 
                  @change="fetchProducts($event.target.value, 'editProductSelect')"
                  class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
              <option value="">Select Supplier</option>
              <?php foreach ($supplier_list as $sup): ?>
                  <option value="<?= $sup['supplier_id'] ?>"><?= e($sup['supplier_name']) ?></option>
              <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Product</label>
          <select id="editProductSelect" name="product_id" x-model="editData.product_id" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
              <option value="">Select Supplier First</option>
          </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Supplier Price</label>
              <input type="number" step="0.01" name="supplier_price" x-model="editData.supplier_price" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Seller Price</label>
              <input type="number" step="0.01" name="price" x-model="editData.price" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Color</label>
              <select name="color_id" x-model="editData.color_id" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                  <option value="">None (Optional)</option>
                  <?php foreach ($color_list as $c): ?>
                      <option value="<?= $c['color_id'] ?>"><?= e($c['color']) ?></option>
                  <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-bold text-gray-700 mb-1">Size</label>
              <select name="size_id" x-model="editData.size_id" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                  <option value="">None (Optional)</option>
                  <?php foreach ($size_list as $s): ?>
                      <option value="<?= $s['size_id'] ?>"><?= e($s['size']) ?></option>
                  <?php endforeach; ?>
              </select>
            </div>
        </div>

        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1">Quantity</label>
          <input type="number" name="new_quantity" x-model="editData.qty" min="0" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] font-semibold text-gray-800" required>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="editStockOpen=false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancel</button>
          <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg text-sm font-medium transition">Update</button>
        </div>
      </form>
    </div>
  </div>

</div> 
<!-- 🔴 END GLOBAL WRAPPER -->

<script>
// NProgress Logic
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
            if(link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript') || link.target === '_blank') return;
            NProgress.start();
        });
    });
    window.addEventListener('load', () => NProgress.done());
    window.addEventListener('pageshow', () => NProgress.done());
});

function onProductChange(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    // 1. Fill Prices
    const supplierPrice = selectedOption.getAttribute('data-supplier-price');
    const sellerPrice = selectedOption.getAttribute('data-seller-price');

    const supInput = document.getElementById('stockInSupplierPrice');
    const sellInput = document.getElementById('stockInSellerPrice');
    
    if(supInput) supInput.value = supplierPrice || '';
    if(sellInput) sellInput.value = sellerPrice || '';

    // 2. Auto-select Default Supplier if available (But allow user to change it)
    const defaultSupplierId = selectedOption.getAttribute('data-default-supplier');
    const supplierSelect = document.getElementById('supplierSelect');
    
    if (supplierSelect && defaultSupplierId) {
        supplierSelect.value = defaultSupplierId;
    }
}

function fetchProducts(supplierId, targetSelectId, preSelectedId = null) {
    const productSel = document.getElementById(targetSelectId);
    
    if (!supplierId) {
        productSel.innerHTML = '<option value="">Select Supplier First</option>';
        return;
    }
    productSel.innerHTML = '<option>Loading...</option>';

    fetch('fetch_products_by_supplier.php?supplier_id=' + encodeURIComponent(supplierId))
      .then(response => response.json())
      .then(data => {
        productSel.innerHTML = '<option value="">Select Product</option>';
        
        if (!Array.isArray(data) || data.length === 0) {
            productSel.innerHTML = '<option value="">No products found</option>';
            return;
        }

        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = p.product_name;
            opt.setAttribute('data-supplier-price', p.supplier_price);
            opt.setAttribute('data-seller-price', p.price_id);
            productSel.appendChild(opt);
        });
        
        if (preSelectedId) {
            productSel.value = preSelectedId;
            const root = document.querySelector('[x-data]');
            if(root && root.__x) {
                root.__x.$data.editData.product_id = preSelectedId;
            }
        }
      })
      .catch(error => { 
          console.error('Error:', error);
          productSel.innerHTML = '<option value="">Error loading products</option>'; 
      });
}
</script>
</body>
</html>