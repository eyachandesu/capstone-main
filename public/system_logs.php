<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';

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


function getLogs($conn) {
    $filter_role   = $_GET['role'] ?? '';
    $filter_action = $_GET['action'] ?? '';
    $search        = $_GET['search'] ?? '';

    $where  = [];
    $params = [];
    $types  = '';

    if (!empty($filter_role)) {
        $where[] = "r.role_name = ?";
        $params[] = $filter_role;
        $types .= 's';
    }
    if (!empty($filter_action)) {
        $where[] = "l.action = ?";
        $params[] = $filter_action;
        $types .= 's';
    }
    if (!empty($search)) {
        $where[] = "(a.username LIKE ? OR CONCAT(a.first_name, ' ', a.last_name) LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $sql = "
      SELECT 
        l.log_id, l.user_id, r.role_name, l.action, l.created_at,
        CONCAT(a.first_name, ' ', a.last_name) AS full_name, a.username
      FROM system_logs l
      LEFT JOIN adminusers a ON l.user_id = a.admin_id
      LEFT JOIN roles r ON l.role_id = r.role_id
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY l.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Handle AJAX Request
if (isset($_GET['ajax'])) {
    $logs = getLogs($conn);
    if ($logs->num_rows > 0) {
        while ($log = $logs->fetch_assoc()) {
            $statusColor = ($log['action'] === 'Login') ? 'text-green-600 bg-green-50 border-green-200' : 'text-red-500 bg-red-50 border-red-200';
            $date = date('M d, Y h:i A', strtotime($log['created_at']));
            $user = htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'Unknown');
            $role = htmlspecialchars($log['role_name']);
            $action = htmlspecialchars($log['action']);
            
            echo "<tr class='hover:bg-gray-50 transition'>
                    <td class='px-6 py-4'>#{$log['log_id']}</td>
                    <td class='px-6 py-4 font-bold text-gray-800'>{$user}</td>
                    <td class='px-6 py-4 text-gray-500'>{$role}</td>
                    <td class='px-6 py-4'>
                        <span class='px-2.5 py-1 rounded-full text-xs font-semibold border {$statusColor}'>
                            {$action}
                        </span>
                    </td>
                    <td class='px-6 py-4 text-gray-500'>{$date}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='5' class='text-center py-8 text-gray-500'>No logs found matching your filters.</td></tr>";
    }
    exit; 
}


$logs = getLogs($conn); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Logs | Seven Dwarfs Boutique</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
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

      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-shopping-cart w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Orders</span>
      </a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-chart-line w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Cashier Sales</span>
      </a>
    
      <a href="system_logs.php" class="block px-4 py-3 active-nav flex items-center transition-all duration-300" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
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
          <h1 class="text-xl font-semibold">System Logs</h1>
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6 space-y-6">

        <!-- Filters -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-center gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Role</label>
              <select id="role" onchange="fetchLogs()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="Cashier">Cashier</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Action</label>
              <select id="action" onchange="fetchLogs()" class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">All Actions</option>
                <option value="Login">Login</option>
                <option value="Logout">Logout</option>
              </select>
            </div>

            <div class="ml-auto">
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search</label>
              <div class="relative">
                  <input id="search" type="search" placeholder="Search user..." oninput="debounceSearch()"
                  class="pl-9 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-64 bg-gray-50">
                  <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
              </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                      <tr>
                        <th class="px-6 py-3">Log ID</th>
                        <th class="px-6 py-3">User</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">Action</th>
                        <th class="px-6 py-3">Date & Time</th>
                      </tr>
                    </thead>
                    <tbody id="logTableBody" class="divide-y divide-gray-100 text-gray-700">
                      <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <?php 
                                $statusColor = ($log['action'] === 'Login') ? 'text-green-600 bg-green-50 border-green-200' : 'text-red-500 bg-red-50 border-red-200';
                            ?>
                          <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-gray-500">#<?= htmlspecialchars($log['log_id']); ?></td>
                            <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'Unknown'); ?></td>
                            <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($log['role_name']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold border <?= $statusColor ?>">
                                  <?= htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td>
                          </tr>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <tr><td colspan="5" class="text-center py-8 text-gray-500">No logs found.</td></tr>
                      <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div> 
    </section>

  </main>
</div>

<script>
let timeout = null;

// Function to fetch logs automatically
function fetchLogs() {
    // Get values from inputs
    const role = document.getElementById('role').value;
    const action = document.getElementById('action').value;
    const search = document.getElementById('search').value;
    
    // Create URL parameters
    const params = new URLSearchParams({
        ajax: '1',
        role: role,
        action: action,
        search: search
    });

    // Fetch data
    fetch('system_logs.php?' + params.toString())
        .then(response => response.text())
        .then(html => {
            document.getElementById('logTableBody').innerHTML = html;
        })
        .catch(error => console.error('Error loading logs:', error));
}

// Debounce function for Search input (waits 300ms after typing stops)
function debounceSearch() {
    clearTimeout(timeout);
    timeout = setTimeout(fetchLogs, 300);
}
</script>

</body>
</html>