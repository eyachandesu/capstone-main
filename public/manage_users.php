<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';

// 1. Initialize Admin Session Data
$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";
$admin_role_id = $_SESSION['role_id']; // This is YOUR role ID

// 2. Handle Search Input
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 3. Build the SQL Query
// ✅ Added 'a.role_id' to the SELECT list so we can check roles inside the loop
$sql = "SELECT a.admin_id, a.username, CONCAT(a.first_name, ' ', a.last_name) AS full_name, 
               r.role_name, a.role_id, a.status_id, a.created_at 
        FROM adminusers a 
        LEFT JOIN roles r ON a.role_id = r.role_id 
        WHERE 1=1";

$params = [];
$types = "";

// 4. Add Search Logic (Using Prepared Statements)
if (!empty($search)) {
    $sql .= " AND (a.username LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss"; 
}

$sql .= " ORDER BY a.created_at DESC";

// 5. Execute Query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Seven Dwarfs</title>

<!-- Libraries -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
<link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    .active { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    @view-transition { navigation: auto; }
    @keyframes fadeInSlide { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }
    #nprogress .bar { background: var(--rose) !important; height: 3px !important; }
</style>
</head>

<body class="text-sm animate-fade-in">

<div class="flex min-h-screen" 
     x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, userMenu: true, productMenu: false }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- Sidebar -->
  <aside class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out overflow-y-auto"
         :class="sidebarOpen ? 'w-64' : 'w-20'">
    <div class="p-5 border-b flex items-center h-20" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden" x-show="sidebarOpen">SevenDwarfs</h2>
    </div>

    <!-- Admin Profile -->
    <div class="p-5 border-b flex items-center h-24" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
      <div x-show="sidebarOpen" class="whitespace-nowrap overflow-hidden">
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">Dashboard</span>
      </a>

      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg text-[var(--rose)]"></i><span x-show="sidebarOpen" class="text-[var(--rose)] font-semibold">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <ul x-show="userMenu" class="space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="manage_users.php" class="block py-2 active flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user w-4 mr-2"></i><span x-show="sidebarOpen">Users</span></a></li>
          <li><a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user-tag w-4 mr-2"></i><span x-show="sidebarOpen">Roles</span></a></li>
        </ul>
      </div>
      
      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i><span x-show="sidebarOpen">Suppliers</span>
      </a>

      <!-- Product Menu -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg"></i><span x-show="sidebarOpen">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-tags w-4 mr-2"></i><span x-show="sidebarOpen">Category</span></a></li>
          <li><a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-box w-4 mr-2"></i><span x-show="sidebarOpen">Product</span></a></li>
          <li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-boxes w-4 mr-2"></i><span x-show="sidebarOpen">Stock In</span></a></li>
          <li><a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-warehouse w-4 mr-2"></i><span x-show="sidebarOpen">Inventory</span></a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-shopping-cart w-5 text-center text-lg"></i><span x-show="sidebarOpen">Orders</span>
      </a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-chart-line w-5 text-center text-lg"></i><span x-show="sidebarOpen">Cashier Sales</span>
      </a>
      <a href="system_logs.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-file-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Logs</span>
      </a>
          <a href="settings.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-cogs w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Settings</span></a>

      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i><span x-show="sidebarOpen">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition"><i class="fas fa-bars text-xl"></i></button>
          <h1 class="text-xl font-semibold">User Management</h1>
      </div>
    </header>

    <section class="p-6">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-xl border border-red-200 flex items-center animate-fade-in">
                <i class="fas fa-exclamation-circle mr-3"></i><?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-xl border border-green-200 flex items-center animate-fade-in">
                <i class="fas fa-check-circle mr-3"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="text-lg font-bold text-gray-800">Administrator Accounts</h2>
                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <form method="GET" action="" class="flex items-center relative">
                        <i class="fas fa-search absolute left-3 text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" 
                               class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-full md:w-64"
                               placeholder="Search users...">
                    </form>
                    <a href="add_user.php" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center justify-center gap-2 font-medium text-sm">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">Full Name</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($user['username']); ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($user['full_name']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($user['status_id'] == 1): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-1.5"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1.5"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <?php 
                                                // ✅ Secure Logic:
                                                // 1. You cannot deactivate/delete YOURSELF.
                                                // 2. Only Admins (Role ID 2) can deactivate/delete others.
                                                
                                                $is_self = ($user['admin_id'] == $admin_id);
                                                $can_manage = ($admin_role_id == 2); // Only Admins can manage
                                            ?>

                                            <!-- Edit (Always Allowed for everyone, or restrict if needed) -->
                                            <a href="edit_user.php?id=<?= $user['admin_id']; ?>" 
                                               class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($can_manage): ?>
                                                <!-- Toggle Status -->
                                                <?php if ($is_self): ?>
                                                    <button disabled class="text-gray-400 bg-gray-100 p-2 rounded-lg cursor-not-allowed opacity-60">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="toggle_user.php?id=<?= $user['admin_id']; ?>&status=<?= $user['status_id']==1?2:1; ?>" 
                                                       class="text-yellow-500 hover:text-yellow-700 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-lg transition"
                                                       title="<?= $user['status_id']==1 ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas <?= $user['status_id']==1 ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Delete -->
                                                <?php if ($is_self): ?>
                                                    <button disabled class="text-gray-400 bg-gray-100 p-2 rounded-lg cursor-not-allowed opacity-60">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="delete_user.php?id=<?= $user['admin_id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this user?')" 
                                                       class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; // End $can_manage ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500 flex flex-col items-center">
                                    <div class="bg-gray-100 p-4 rounded-full mb-3"><i class="fas fa-search text-gray-400 text-2xl"></i></div>
                                    <p>No users found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                                    <a href="manage_users.php" class="text-[var(--rose)] text-xs font-bold mt-2 hover:underline">Clear Search</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div> 
    </section>
  </main>
</div>

<script>
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
</script>

</body>
</html>