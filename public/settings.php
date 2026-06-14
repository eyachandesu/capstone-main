<?php
session_start();
// require 'admin_only.php'; // Uncomment if you have this file
require_once __DIR__ . '/../config/conn.php';

// Verify if logged in
if (!isset($_SESSION["admin_id"])) {
  header("Location: login.php");
  exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = "Admin";
$admin_role = "Admin";

// Fetch logged-in admin details
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

// Fetch current store settings
$query = "SELECT * FROM store_settings WHERE id = 1";
$result = mysqli_query($conn, $query);
$settings = mysqli_fetch_assoc($result);

// Handle Settings Update
$update_success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $name = mysqli_real_escape_string($conn, $_POST['store_name']);
    $email = mysqli_real_escape_string($conn, $_POST['store_email']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $update = "UPDATE store_settings SET 
               store_name='$name', store_email='$email', 
               contact='$contact', address='$address' 
               WHERE id=1";
    
    if (mysqli_query($conn, $update)) {
        // Log the action
        $username = $_SESSION['username'] ?? $admin_name;
        mysqli_query($conn, "INSERT INTO system_logs (user_id, username, action, role_id) VALUES ($admin_id, '$username', 'Updated system settings', (SELECT role_id FROM adminusers WHERE admin_id=$admin_id))");
        $update_success = true;
        // Refresh data
        $result = mysqli_query($conn, $query);
        $settings = mysqli_fetch_assoc($result);
    }
}

// --- NEW CODE: Handle Database Reset ---
$reset_success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_database'])) {
    // List of tables to clear in order (Business data only)
    $tables_to_clear = [
        'transactions',
        'refunds',
        'order_items',
        'orders',
        'stock_adjustments',
        'stock_in',
        'stock',
        'product_colors',
        'product_sizes',
        'products',
        'categories',
        'cart_items',
        'carts',
        'customers',
        'system_logs' // We clear logs too for a fresh start
    ];

    // Disable foreign key checks to allow TRUNCATE
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables_to_clear as $table) {
        mysqli_query($conn, "TRUNCATE TABLE $table");
    }

    // Re-enable foreign key checks
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

    // Add a single log entry back so the log isn't empty
    $username = $_SESSION['username'] ?? $admin_name;
    $action_note = "PERFORMED FULL SYSTEM RESET (Business data cleared)";
    mysqli_query($conn, "INSERT INTO system_logs (user_id, username, action, role_id) VALUES ($admin_id, '$username', '$action_note', (SELECT role_id FROM adminusers WHERE admin_id=$admin_id))");
    
    $reset_success = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Settings | Seven Dwarfs Boutique</title>
  
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
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }
  </style>
</head>

<body class="text-sm animate-fade-in">

<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: false
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

      <a href="settings.php" class="block px-4 py-3 active-nav flex items-center transition-all" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-cogs w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen">System Settings</span>
      </a>

      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- 🌸 Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300" :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition"><i class="fas fa-bars text-xl"></i></button>
          <h1 class="text-xl font-semibold">System Settings</h1>
      </div>
    </header>

    <section class="p-6 space-y-6">

        <?php if($update_success): ?>
        <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl animate-fade-in flex items-center shadow-sm">
            <i class="fas fa-check-circle mr-2"></i> Settings updated successfully!
        </div>
        <?php endif; ?>

        <?php if($reset_success): ?>
        <div class="bg-orange-50 border border-orange-200 text-orange-600 px-4 py-3 rounded-xl animate-fade-in flex items-center shadow-sm">
            <i class="fas fa-history mr-2"></i> System reset complete! All store data cleared.
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Store Configuration Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b bg-gray-50 flex items-center space-x-2">
                    <i class="fas fa-store text-[var(--rose)]"></i>
                    <h2 class="font-bold text-gray-700 uppercase tracking-wider">Store Configuration</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Store Name</label>
                            <input type="text" name="store_name" value="<?= htmlspecialchars($settings['store_name']); ?>" required
                                   class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Store Email</label>
                            <input type="email" name="store_email" value="<?= htmlspecialchars($settings['store_email']); ?>" required
                                   class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Contact Number</label>
                            <input type="text" name="contact" value="<?= htmlspecialchars($settings['contact']); ?>" required
                                   class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Address</label>
                            <textarea name="address" rows="3" required
                                   class="w-full px-4 py-2.5 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none transition"><?= htmlspecialchars($settings['address']); ?></textarea>
                        </div>
                        <button type="submit" name="update_settings" class="w-full bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white font-bold py-3 rounded-xl shadow-lg transition duration-300 uppercase tracking-widest text-xs">
                            Save Configuration
                        </button>
                    </form>
                </div>
            </div>

            <!-- Database Maintenance Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b bg-gray-50 flex items-center space-x-2">
                    <i class="fas fa-database text-blue-400"></i>
                    <h2 class="font-bold text-gray-700 uppercase tracking-wider">Database Maintenance</h2>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Backup -->
                    <div class="p-4 rounded-2xl bg-blue-50 border border-blue-100">
                        <h3 class="font-bold text-blue-700 flex items-center"><i class="fas fa-download mr-2"></i> System Backup</h3>
                        <p class="text-xs text-blue-600/70 mb-4 mt-1">Export all system data into a secure .sql file for safekeeping.</p>
                        <form action="backup_logic.php" method="POST">
                            <button type="submit" name="backup_db" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2.5 rounded-lg transition text-xs shadow-sm">
                                GENERATE BACKUP
                            </button>
                        </form>
                    </div>

                    <!-- Restore -->
                    <div class="p-4 rounded-2xl bg-red-50 border border-red-100">
                        <h3 class="font-bold text-red-700 flex items-center"><i class="fas fa-exclamation-triangle mr-2"></i> Emergency Restore</h3>
                        <p class="text-xs text-red-600/70 mb-4 mt-1">Warning: Restoring will permanently delete and replace all current data.</p>
                        <form action="restore_logic.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Are you absolutely sure? Current data will be lost.')">
                            <input type="file" name="backup_file" required class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-red-100 file:text-red-700 hover:file:bg-red-200 mb-4 cursor-pointer">
                            <button type="submit" name="restore_db" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2.5 rounded-lg transition text-xs shadow-sm">
                                RESTORE SYSTEM
                            </button>
                        </form>
                    </div>

                    <!-- Factory Reset -->
                    <div class="p-4 rounded-2xl bg-orange-50 border border-orange-100">
                        <h3 class="font-bold text-orange-700 flex items-center"><i class="fas fa-trash-alt mr-2"></i> Factory Reset</h3>
                        <p class="text-xs text-orange-600/70 mb-4 mt-1">Wipes all business data (Products, Orders, Logs). <b>Admins and Roles are kept.</b></p>
                        <form method="POST" onsubmit="return confirm('CRITICAL WARNING: This will permanently delete all store products, orders, and customer data. Proceed?')">
                            <button type="submit" name="reset_database" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-2.5 rounded-lg transition text-xs shadow-sm">
                                RESET ALL STORE DATA
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div> 
    </section>
  </main>
</div>

</body>
</html>