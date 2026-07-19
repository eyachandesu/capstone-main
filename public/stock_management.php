<?php
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';


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

      <a href="/controllers/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- ================= MAIN CONTENT ================= -->
<main
    class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300"
    :class="sidebarOpen ? 'ml-64' : 'ml-20'">

    <!-- Header -->
    <header
        class="fixed top-0 right-0 z-20 bg-[var(--rose)] text-white shadow-md rounded-bl-2xl transition-all duration-300"
        :class="sidebarOpen ? 'left-64' : 'left-20'">
        <div class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-4">
                <button
                    @click="sidebarOpen=!sidebarOpen"
                    class="hover:bg-white/20 rounded-lg p-2 transition">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <h1 class="text-xl font-semibold">
                    Stock Management
                </h1>
            </div>
        </div>
    </header>

    <section class="p-6 space-y-6">
        <?php if ($outStock && $selected_status != 'in'): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 shadow-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span>
                    <strong>Warning!</strong>
                    Some products are already out of stock.
                </span>
            </div>
        <?php endif; ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">

            <!-- Top Controls -->
            <div class="flex flex-wrap items-center justify-between gap-4 p-6 border-b">
                <form method="GET" class="flex flex-wrap gap-3">
                    <select
                        name="category_id"
                        onchange="this.form.submit()"
                        class="rounded-lg border px-4 py-2">
                        <option value="0">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option
                                value="<?= $cat['category_id'] ?>"
                                <?= $selected_category==$cat['category_id']?'selected':'';?>>
                                <?= e($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select
                        name="stock_status"
                        onchange="this.form.submit()"
                        class="rounded-lg border px-4 py-2">
                        <option value="all" <?= $selected_status=="all"?"selected":"";?>>
                            All Status
                        </option>
                        <option value="in" <?= $selected_status=="in"?"selected":"";?>>
                            In Stock
                        </option>
                        <option value="out" <?= $selected_status=="out"?"selected":"";?>>
                            Out of Stock
                        </option>
                    </select>
                </form>
                <button
                    type="button"
                    @click="stockInOpen=true"
                    class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-5 py-2 rounded-lg shadow">
                    <i class="fas fa-plus mr-2"></i>
                    Stock In
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-6 py-4 text-left">Product</th>
                            <th class="px-6 py-4 text-left">Color</th>
                            <th class="px-6 py-4 text-left">Size</th>
                            <th class="px-6 py-4 text-center">Quantity</th>
                            <th class="px-6 py-4 text-left">Supplier</th>
                            <th class="px-6 py-4 text-left">Last Added</th>
                            <th class="px-6 py-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                    <?php if(!empty($stock_rows)): ?>
                        <?php foreach($stock_rows as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-semibold">
                                <?= e($row['product_name']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= !empty($row['color']) ? e($row['color']) : '<span class="text-gray-400">N/A</span>' ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= !empty($row['size']) ? e($row['size']) : '<span class="text-gray-400">N/A</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if($row['current_qty']>0): ?>
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 font-bold">
                                        <?= (int)$row['current_qty'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 font-bold">
                                        0
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= e($row['supplier_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4">
                                <?= !empty($row['date_added'])
                                    ? date('M d, Y',strtotime($row['date_added']))
                                    : 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button
                                  type="button"
                                  @click="
                                      editData = {
                                          id: '<?= $row['stock_id'] ?>',
                                          product_id: '<?= $row['product_id'] ?>',
                                          supplier_id: '<?= $row['supplier_id'] ?>',
                                          qty: '<?= $row['current_qty'] ?>',
                                          supplier_price: '<?= $row['supplier_price'] ?>',
                                          price: '<?= $row['seller_price'] ?>',
                                          color_id: '<?= $row['color_id'] ?>',
                                          size_id: '<?= $row['size_id'] ?>'
                                      };

                                      editStockOpen = true;

                                      setTimeout(() => {
                                          fetchProducts(
                                              editData.supplier_id,
                                              'editProductSelect',
                                              editData.product_id
                                          );
                                      }, 100);
                                  "
                                  class="rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 p-2">
                                  <i class="fas fa-edit"></i>
                              </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7"
                                class="py-10 text-center text-gray-500">
                                No stock records found.
                            </td>
                        </tr>
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
      <form action="/controllers/process_stock_in.php" method="POST" class="space-y-4">
        
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
<div x-show="editStockOpen" x-cloak
     class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm">
    <div @click.away="editStockOpen = false"
         class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center border-b pb-2 mb-4">
            <h3 class="text-xl font-bold">Edit Stock Details</h3>
            <button type="button"
                    @click="editStockOpen=false"
                    class="text-gray-500 hover:text-black">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="/controllers/process_edit_stock.php" method="POST">
            <!-- IMPORTANT -->
            <input type="hidden" name="stock_id" x-model="editData.id">
            <div class="mb-3">
                <label class="font-semibold block mb-1">
                    Supplier
                </label>
                <select
                    id="editSupplierSelect"
                    name="supplier_id"
                    x-model="editData.supplier_id"
                    @change="fetchProducts($event.target.value,'editProductSelect')"
                    class="border rounded-lg w-full p-2">
                    <option value="">Select Supplier</option>
                    <?php foreach($supplier_list as $sup): ?>
                        <option value="<?= $sup['supplier_id'] ?>">
                            <?= htmlspecialchars($sup['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="font-semibold block mb-1">
                    Product
                </label>
                <select
                    id="editProductSelect"
                    name="product_id"
                    x-model="editData.product_id"
                    class="border rounded-lg w-full p-2">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="font-semibold block mb-1">
                        Supplier Price
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="supplier_price"
                        x-model="editData.supplier_price"
                        class="border rounded-lg w-full p-2">
                </div>
                <div>
                    <label class="font-semibold block mb-1">
                        Seller Price
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="price"
                        x-model="editData.price"
                        class="border rounded-lg w-full p-2">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
                <div>
                    <label class="font-semibold block mb-1">
                        Color
                    </label>
                    <select
                        name="color_id"
                        x-model="editData.color_id"
                        class="border rounded-lg w-full p-2">
                        <option value="">None</option>
                        <?php foreach($color_list as $c): ?>
                            <option value="<?= $c['color_id'] ?>">
                                <?= htmlspecialchars($c['color']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="font-semibold block mb-1">
                        Size
                    </label>
                    <select
                        name="size_id"
                        x-model="editData.size_id"
                        class="border rounded-lg w-full p-2">
                        <option value="">None</option>
                        <?php foreach($size_list as $s): ?>
                            <option value="<?= $s['size_id'] ?>">
                                <?= htmlspecialchars($s['size']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <label class="font-semibold block mb-1">
                    Quantity
                </label>
                <input
                    type="number"
                    name="new_quantity"
                    min="0"
                    x-model="editData.qty"
                    class="border rounded-lg w-full p-2">
            </div>
            <div class="flex justify-end gap-3 mt-5">
                <button
                    type="button"
                    @click="editStockOpen=false"
                    class="px-4 py-2 bg-gray-200 rounded-lg">
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-rose-500 text-white rounded-lg">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>
</div> 
<!-- 🔴 END GLOBAL WRAPPER -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (
                link.getAttribute('href').startsWith('#') ||
                link.getAttribute('href').startsWith('javascript') ||
                link.target === '_blank'
            ) {
                return;
            }
            NProgress.start();
        });
    });
    window.addEventListener('load', () => NProgress.done());
    window.addEventListener('pageshow', () => NProgress.done());
});


function onProductChange(select) {
    if (!select.value) return;
    const option = select.options[select.selectedIndex];
    const supplierPrice =
        option.dataset.supplierPrice || '';
    const sellerPrice =
        option.dataset.sellerPrice || '';
    const supplierId =
        option.dataset.defaultSupplier || '';
    document.getElementById('stockInSupplierPrice').value =
        supplierPrice;
    document.getElementById('stockInSellerPrice').value =
        sellerPrice;
    if (supplierId) {
        document.getElementById('supplierSelect').value =
            supplierId;
    }
}


function fetchProducts(supplierId, targetSelectId, preSelectedId = null) {
    console.log("Supplier:", supplierId);
    const select = document.getElementById(targetSelectId);
    if (!select) {
        console.error("Select not found:", targetSelectId);
        return;
    }
    select.innerHTML = "<option>Loading...</option>";
    fetch("/controllers/fetch_products_by_supplier.php?supplier_id=" + supplierId)
        .then(response => {
            console.log(response.status);
            if (!response.ok) {
                throw new Error("HTTP " + response.status);
            }
            return response.json();
        })
        .then(products => {
            console.log(products);
            select.innerHTML = "";
            if (!products.length) {
                select.innerHTML =
                    "<option value=''>No products found</option>";
                return;
            }
            products.forEach(product => {
                const option = document.createElement("option");
                option.value = product.product_id;
                option.textContent = product.product_name;
                option.dataset.supplierPrice = product.supplier_price;
                option.dataset.sellerPrice = product.price_id;
                select.appendChild(option);
            });
            if (preSelectedId) {
                select.value = preSelectedId;
            }
        })
        .catch(error => {
            console.error(error);
            select.innerHTML =
                "<option value=''>Unable to load products</option>";

        });

}

// =========================
// EDIT PRODUCT CHANGE
// =========================
document.addEventListener('DOMContentLoaded', () => {

    const editProduct =
        document.getElementById('editProductSelect');

    if (!editProduct) return;

    editProduct.addEventListener('change', function () {

        const option =
            this.options[this.selectedIndex];

        const root =
            document.querySelector('[x-data]');

        if (!root || !root.__x) return;

        root.__x.$data.editData.product_id =
            this.value;

        root.__x.$data.editData.supplier_price =
            option.dataset.supplierPrice || 0;

        root.__x.$data.editData.price =
            option.dataset.sellerPrice || 0;

    });

});
</script>
</body>
</html>