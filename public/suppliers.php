<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $adminStmt = $conn->prepare($query);
    $adminStmt->bind_param("i", $admin_id);
    $adminStmt->execute();
    $result = $adminStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $adminStmt->close();
}


if (isset($_POST['add_supplier'])) {

    $supplier_name  = trim($_POST['supplier_name']);
    $supplier_email = trim($_POST['supplier_email'] ?? '');
    $supplier_phone = trim($_POST['supplier_phone'] ?? '');

    if (!empty($supplier_name)) {
        $sql = "INSERT INTO suppliers (supplier_name, supplier_email, supplier_phone, created_at)
                VALUES (?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $supplier_name, $supplier_email, $supplier_phone);
        $stmt->execute();
        $stmt->close();

        header("Location: suppliers.php?success=1");
        exit;
    }
}


if (isset($_POST['edit_supplier'])) {

    $id    = intval($_POST['supplier_id']);
    $name  = trim($_POST['supplier_name']);
    $email = trim($_POST['supplier_email']);
    $phone = trim($_POST['supplier_phone']);

    if (!empty($name)) {
        $stmt = $conn->prepare("
            UPDATE suppliers 
            SET supplier_name=?, supplier_email=?, supplier_phone=? 
            WHERE supplier_id=?
        ");
        $stmt->bind_param("sssi", $name, $email, $phone, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: suppliers.php?updated=1");
        exit;
    }
}


if (isset($_POST['delete_supplier'])) {

    $delete_id = intval($_POST['delete_id']);

    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        header("Location: suppliers.php?deleted=1");
        exit;
    }
}

$suppliers = $conn->query("
    SELECT supplier_id, supplier_name, supplier_email, supplier_phone, created_at
    FROM suppliers
    ORDER BY created_at ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Suppliers | Seven Dwarfs Boutique</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

<!-- Global State Wrapper -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: false,
        addModal: false,
        editModal: false,
        deleteModal: false,

        editData: { id: '', name: '', email: '', phone: '' },
        deleteData: { id: '', name: '' },

        openEdit(id, name, email, phone) {
            this.editData = { id, name, email, phone };
            this.editModal = true;
        },

        openDelete(id, name) {
            this.deleteData = { id, name };
            this.deleteModal = true;
        }
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
        <a href="suppliers.php" class="block px-4 py-3 active-nav flex items-center transition-all duration-300" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
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
      <a href="settings.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'"><i class="fas fa-cogs w-5 text-center text-lg"></i><span x-show="sidebarOpen">System Settings</span></a>

      <a href="/controllers/logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
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
          <h1 class="text-xl font-semibold">Suppliers</h1>
      </div>

      <button @click="addModal = true" class="bg-white text-[var(--rose)] px-4 py-2 rounded-lg shadow font-bold text-xs flex items-center gap-2 hover:bg-gray-50 transition">
        <i class="fas fa-plus"></i> Add Supplier
      </button>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6 space-y-6">

        <!-- Alerts -->
        <?php if (isset($_GET['success'])): ?>
          <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg shadow-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Supplier added successfully.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
          <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg shadow-sm flex items-center gap-2">
            <i class="fas fa-pen-square"></i> Supplier updated successfully.
          </div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
          <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg shadow-sm flex items-center gap-2">
            <i class="fas fa-trash-alt"></i> Supplier deleted successfully.
          </div>
        <?php endif; ?>

        <!-- Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="overflow-x-auto rounded-lg border border-gray-100">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Supplier Name</th>
                            <th class="px-6 py-3">Date Added</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Phone</th>
                            <th class="px-6 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if ($suppliers->num_rows > 0): ?>
                          <?php while ($row = $suppliers->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition">
                              <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($row['supplier_name']); ?></td>
                              <td class="px-6 py-4"><?= date('M d, Y', strtotime($row['created_at'])); ?></td>
                              <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($row['supplier_email'] ?: 'N/A'); ?></td>
                              <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($row['supplier_phone'] ?: 'N/A'); ?></td>
                              <td class="px-6 py-4 text-center">
                                <div class="flex justify-center gap-2">
                                    <button
                                      @click="openEdit(
                                        '<?= $row['supplier_id'] ?>',
                                        '<?= htmlspecialchars($row['supplier_name'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['supplier_email'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['supplier_phone'], ENT_QUOTES) ?>'
                                      )"
                                      class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition"
                                      title="Edit">
                                      <i class="fas fa-pen"></i>
                                    </button>
                                    <button
                                      @click="openDelete(
                                        '<?= $row['supplier_id'] ?>',
                                        '<?= htmlspecialchars($row['supplier_name'], ENT_QUOTES) ?>'
                                      )"
                                      class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition"
                                      title="Delete">
                                      <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr><td colspan="5" class="text-center py-8 text-gray-500">No suppliers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div> 
    </section>

  </main>

  <!-- ADD MODAL -->
  <div x-show="addModal" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm animate-fade-in">
    <div @click.away="addModal = false" class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all">
      <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Add New Supplier</h3>
      <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="add_supplier" value="1">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Supplier Name</label>
            <input type="text" name="supplier_name" required class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
            <input type="email" name="supplier_email" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Phone</label>
            <input type="text" name="supplier_phone" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="addModal=false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancel</button>
            <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg text-sm font-medium transition">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <div x-show="editModal" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm animate-fade-in">
    <div @click.away="editModal = false" class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 transform transition-all">
      <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Edit Supplier</h3>
      <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="edit_supplier" value="1">
        <input type="hidden" name="supplier_id" x-model="editData.id">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Supplier Name</label>
            <input type="text" name="supplier_name" x-model="editData.name" required class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
            <input type="email" name="supplier_email" x-model="editData.email" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Phone</label>
            <input type="text" name="supplier_phone" x-model="editData.phone" class="border border-gray-300 w-full p-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="editModal=false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancel</button>
            <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg text-sm font-medium transition">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- DELETE MODAL -->
  <div x-show="deleteModal" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4 backdrop-blur-sm animate-fade-in">
    <div @click.away="deleteModal = false" class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 transform transition-all text-center">
      <div class="text-red-500 text-4xl mb-3"><i class="fas fa-exclamation-circle"></i></div>
      <h3 class="text-xl font-bold text-gray-800 mb-2">Delete Supplier?</h3>
      <p class="text-gray-600 mb-6">Are you sure you want to delete <span class="font-bold text-gray-800" x-text="deleteData.name"></span>? This action cannot be undone.</p>
      
      <form method="POST" class="flex justify-center gap-3">
        <input type="hidden" name="delete_supplier" value="1">
        <input type="hidden" name="delete_id" x-model="deleteData.id">
        <button type="button" @click="deleteModal=false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">Cancel</button>
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Delete</button>
      </form>
    </div>
  </div>

</div>

</body>
</html>