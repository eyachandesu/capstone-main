<?php
session_start();
require_once __DIR__ . '/../init.php';


$user = checkAuth('admin');
$adminId = $user->user_id ?? '';
$adminRole = $user->role ?? 'admin';


/* 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit;
}

$admin_id = $_SESSION['admin_id'];*/

// 🧩 Fetch current admin details
$stmt = $conn->prepare("
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, r.role_name 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";

// ➕ Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
  $role_name = trim($_POST['role_name']);
  if (!empty($role_name)) {
    // Check if role name already exists
    $check_stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $check_stmt->bind_param("s", $role_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
      $_SESSION['message'] = "Role name already exists!";
      $check_stmt->close();
    } else {
      $check_stmt->close();
      $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
      $stmt->bind_param("s", $role_name);
      $stmt->execute();
      $_SESSION['success'] = "Role added successfully!";
      $stmt->close();
    }
  } else {
    $_SESSION['message'] = "Role name cannot be empty!";
  }
  header("Location: manage_roles.php");
  exit();
}

// ✏️ Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
  $role_id = intval($_POST['role_id']);
  $role_name = trim($_POST['role_name']);

  if ($role_id == 2) {
    $_SESSION['message'] = "Admin role cannot be renamed!";
  } else {
    $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
    $stmt->bind_param("si", $role_name, $role_id);
    $stmt->execute();
    $_SESSION['success'] = "Role updated successfully!";
    $stmt->close();
  }
  header("Location: manage_roles.php");
  exit();
}

// 🗑️ Delete Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
  $role_id = intval($_POST['role_id']);

  // Only allow Admins to delete, and not their own role
  $current_admin_id = $_SESSION['admin_id'];
  $admin_check = $conn->prepare("SELECT role_id FROM adminusers WHERE admin_id = ?");
  $admin_check->bind_param("i", $current_admin_id);
  $admin_check->execute();
  $admin_check->bind_result($current_role_id);
  $admin_check->fetch();
  $admin_check->close();

  if ($current_role_id != 2) {
    $_SESSION['message'] = "Only Admin users can delete roles!";
  } elseif ($role_id == $current_role_id) {
    $_SESSION['message'] = "You cannot delete your own role while logged in!";
  } else {
    $check = $conn->prepare("SELECT COUNT(*) FROM adminusers WHERE role_id = ?");
    $check->bind_param("i", $role_id);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
      $_SESSION['message'] = "Cannot delete role assigned to users!";
    } else {
      $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
      $stmt->bind_param("i", $role_id);
      $stmt->execute();
      $_SESSION['success'] = "Role deleted successfully!";
      $stmt->close();
    }
  }
  header("Location: manage_roles.php");
  exit();
}

// 🔎 Search Logic
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
  $s = $conn->real_escape_string($search);
  $search_query = " WHERE role_name LIKE '%$s%' ";
}

// 📌 Fetch Roles
$roles = $conn->query("SELECT * FROM roles $search_query ORDER BY role_id ASC");

// Notifications (Mock logic for consistency)
$newOrdersNotif = 0;
$lowStockNotif = 0;
$totalNotif = 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Roles | Seven Dwarfs</title>

  <!-- Libraries -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

  <!-- NProgress (Loading Bar) -->
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

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

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--rose);
      border-radius: 3px;
    }

    /* Sidebar specific */
    .active {
      background-color: #fce8eb;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    /* 1. View Transitions API */
    @view-transition {
      navigation: auto;
    }

    /* 2. Fade In Animation */
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

    /* 3. NProgress Customization */
    #nprogress .bar {
      background: var(--rose) !important;
      height: 3px !important;
    }

    #nprogress .peg {
      box-shadow: 0 0 10px var(--rose), 0 0 5px var(--rose) !important;
    }
  </style>
</head>

<body class="text-sm animate-fade-in">

  <!-- Global State Wrapper -->
  <div class="flex min-h-screen" x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: true, 
        productMenu: false 
     }" x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

    <!-- 🌸 Sidebar (Dynamic Width) -->
    <aside
      class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden"
      :class="sidebarOpen ? 'w-64' : 'w-20'">
      <!-- Logo -->
      <div class="p-5 border-b flex items-center h-20 transition-all duration-300"
        :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300"
          x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
      </div>

      <!-- Admin Profile -->
      <div class="p-5 border-b flex items-center h-24 transition-all duration-300"
        :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
        <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-4 space-y-1">
        <a href="dashboard.php"
          class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center"
          :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
          <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i>
          <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
        </a>

        <!-- User Management (Active) -->
        <div>
          <button @click="userMenu = !userMenu"
            class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300"
            :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
            <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
              <i class="fas fa-users-cog w-5 text-center text-lg text-[var(--rose)]"></i>
              <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">User
                Management</span>
            </div>
            <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': userMenu }"></i>
          </button>
          <!-- Submenu -->
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
              <a href="manage_roles.php" class="block py-2 active flex items-center"
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
                <span x-show="sidebarOpen">Stock Mgt</span>
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

    <!-- 🌸 Main Content (Dynamic Margin) -->
    <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out"
      :class="sidebarOpen ? 'ml-64' : 'ml-20'">

      <!-- Header (Dynamic Position) -->
      <header
        class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
        :class="sidebarOpen ? 'left-64' : 'left-20'">

        <div class="flex items-center gap-4">
          <!-- Toggle Button -->
          <button @click="sidebarOpen = !sidebarOpen"
            class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">Manage Roles</h1>
        </div>

        <div class="flex items-center gap-4">

        </div>
      </header>

      <!-- 📄 Page Content -->
      <section class="p-6">

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['message'])): ?>
          <div
            class="mb-4 p-4 bg-red-100 text-red-700 rounded-xl border border-red-200 flex items-center animate-fade-in">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <?= $_SESSION['message'];
            unset($_SESSION['message']); ?>
          </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
          <div
            class="mb-4 p-4 bg-green-100 text-green-700 rounded-xl border border-green-200 flex items-center animate-fade-in">
            <i class="fas fa-check-circle mr-3"></i>
            <?= $_SESSION['success'];
            unset($_SESSION['success']); ?>
          </div>
        <?php endif; ?>

        <!-- The White Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

          <!-- Card Header -->
          <div class="px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <h2 class="text-lg font-bold text-gray-800">Role List</h2>

            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
              <!-- Search Form -->
              <form method="GET" action="" class="flex items-center relative">
                <i class="fas fa-search absolute left-3 text-gray-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>"
                  class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-full md:w-64"
                  placeholder="Search roles...">
              </form>

              <!-- Add Button -->
              <button onclick="openModal('addModal')"
                class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center justify-center gap-2 font-medium text-sm">
                <i class="fas fa-plus"></i> Add Role
              </button>
            </div>
          </div>

          <!-- Table (Centered) -->
          <div class="overflow-x-auto">
            <table
              class="w-full text-left text-sm text-gray-600 md:w-3/4 mx-auto my-6 border border-gray-100 rounded-lg">
              <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs border-b">
                <tr>
                  <th class="px-6 py-3 text-left tracking-wider">Role Name</th>
                  <th class="px-6 py-3 text-center tracking-wider w-32">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if ($roles->num_rows > 0): ?>
                  <?php while ($role = $roles->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition duration-150">
                      <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($role['role_name']); ?></td>
                      <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                          <!-- Edit -->
                          <button onclick="openEditModal(<?= $role['role_id']; ?>, '<?= $role['role_name']; ?>')"
                            class="flex items-center justify-center text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                          </button>
                          <?php
                          // Only Admins can see the delete button and modal, and cannot delete their own role
                          $roleId = isset($role['role_id']) ? $role['role_id'] : null;
                          $adminRoleId = isset($admin['role_id']) ? $admin['role_id'] : null;
                          $isCurrentAdminRole = ($roleId !== null && $adminRoleId !== null && $roleId == $adminRoleId);
                          $isAdmin = ($admin['role_name'] === 'Admin' || $admin['role_name'] === 'Administrator');
                          ?>
                          <?php if ($isAdmin && $roleId !== null): ?>
                            <?php if ($role['role_name'] === 'Admin' || $role['role_name'] === 'Administrator'): ?>
                              <!-- Delete disabled for Admin role -->
                              <button disabled
                                class="flex items-center justify-center text-red-300 bg-red-50 p-2 rounded-lg transition cursor-not-allowed opacity-50"
                                title="Cannot delete the Admin role">
                                <i class="fas fa-trash"></i>
                              </button>
                            <?php elseif (!$isCurrentAdminRole): ?>
                              <!-- Delete enabled for Admins except their own role -->
                              <button onclick="openDeleteModal(<?= $roleId; ?>)"
                                class="flex items-center justify-center text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition"
                                title="Delete">
                                <i class="fas fa-trash"></i>
                              </button>
                            <?php else: ?>
                              <!-- Delete disabled for currently logged-in Admin, but modal still present -->
                              <button disabled onclick="openDeleteModal(<?= $roleId; ?>)"
                                class="flex items-center justify-center text-red-300 bg-red-50 p-2 rounded-lg transition cursor-not-allowed opacity-50"
                                title="Cannot delete your own role">
                                <i class="fas fa-trash"></i>
                              </button>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="2" class="px-6 py-8 text-center text-gray-500">
                      No roles found.
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

  <!-- ➕ Add Role Modal -->
  <div id="addModal"
    class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 backdrop-blur-sm animate-fade-in">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-96 transform transition-all">
      <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Add New Role</h2>
      <form method="POST">
        <label class="block text-gray-700 text-sm font-bold mb-2">Role Name</label>
        <input type="text" name="role_name" required
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-6 focus:outline-none focus:ring-2 focus:ring-[var(--rose)] focus:border-transparent">
        <div class="flex justify-end gap-3">
          <button type="button" onclick="closeModal('addModal')"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">Cancel</button>
          <button type="submit" name="add_role"
            class="px-4 py-2 bg-[var(--rose)] text-white rounded-lg hover:bg-[var(--rose-hover)] transition font-medium">Save
            Role</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ✏️ Edit Role Modal -->
  <div id="editModal"
    class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 backdrop-blur-sm animate-fade-in">
    <div class="bg-white p-6 rounded-xl shadow-2xl w-96 transform transition-all">
      <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Edit Role</h2>
      <form method="POST">
        <input type="hidden" name="role_id" id="editRoleId">
        <label class="block text-gray-700 text-sm font-bold mb-2">Role Name</label>
        <input type="text" name="role_name" id="editRoleName" required
          class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-6 focus:outline-none focus:ring-2 focus:ring-[var(--rose)] focus:border-transparent">
        <div class="flex justify-end gap-3">
          <button type="button" onclick="closeModal('editModal')"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">Cancel</button>
          <button type="submit" name="edit_role"
            class="px-4 py-2 bg-[var(--rose)] text-white rounded-lg hover:bg-[var(--rose-hover)] transition font-medium">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 🗑️ Delete Role Modal -->
  <?php if ($admin['role_name'] === 'Admin' || $admin['role_name'] === 'Administrator'): ?>
    <div id="deleteModal"
      class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 backdrop-blur-sm animate-fade-in">
      <div class="bg-white p-6 rounded-xl shadow-2xl w-96 transform transition-all">
        <h2 class="text-xl font-bold text-gray-800 mb-2">Delete Role</h2>
        <p class="mb-6 text-gray-600 text-sm">Are you sure you want to delete this role? This action cannot be undone.</p>
        <form method="POST">
          <input type="hidden" name="role_id" id="deleteRoleId">
          <div class="flex justify-end gap-3">
            <button type="button" onclick="closeModal('deleteModal')"
              class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">Cancel</button>
            <button type="submit" name="delete_role"
              class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition font-medium">Delete</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Scripts -->
  <script>
    // NProgress Logic
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
          if (link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript') || link.target === '_blank') return;
          NProgress.start();
        });
      });
      window.addEventListener('load', () => NProgress.done());
      window.addEventListener('pageshow', () => NProgress.done());
    });

    // Modal Logic
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openEditModal(id, name) {
      document.getElementById("editRoleId").value = id;
      document.getElementById("editRoleName").value = name;
      openModal('editModal');
    }

    function openDeleteModal(id) {
      document.getElementById("deleteRoleId").value = id;
      openModal('deleteModal');
    }
  </script>

</body>

</html>