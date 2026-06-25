<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/config.php';

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
  header("Location: login.php");
  exit;
}

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
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

// 🔹 Fetch categories
$categories = [];
$res = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $res->fetch_assoc()) {
  $categories[] = $row;
}

// 🔹 Selected category filter
$selectedCategory = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$searchTerm = "%$searchQuery%";

// 🔹 Fetch products (REMOVED: Supplier Joins and Supplier Price)
$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.description,
        p.price_id,        -- Selling Price
        p.image_url,
        c.category_name,
        GROUP_CONCAT(DISTINCT col.color ORDER BY col.color SEPARATOR ', ') AS colors,
        GROUP_CONCAT(DISTINCT sz.size ORDER BY sz.size SEPARATOR ', ') AS sizes
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock st ON p.product_id = st.product_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

$conditions = [];
$params = [];
$types = "";

// Category filter
if ($selectedCategory !== 'all') {
  $conditions[] = "p.category_id = ?";
  $params[] = $selectedCategory;
  $types .= "i";
}

// Search filter
if (!empty($searchQuery)) {
  $conditions[] = "(p.product_name LIKE ? OR p.description LIKE ?)";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $types .= "ss";
}

// Apply conditions
if (!empty($conditions)) {
  $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY p.product_id ORDER BY p.product_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Process products
$products = [];
while ($row = $result->fetch_assoc()) {
  $imageList = [];
  $raw = trim($row['image_url'] ?? '');

  if ($raw && str_starts_with($raw, '[')) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded))
      $imageList = $decoded;
  } elseif ($raw) {
    $imageList = array_filter(array_map('trim', explode(',', $raw)));
  }

  $displayImages = [];
  if (!empty($imageList)) {
    foreach ($imageList as $img) {
      $img = trim($img);
      if (!str_contains($img, 'uploads/'))
        $img = 'uploads/products/' . $img;
      $displayImages[] = htmlspecialchars($img);
    }
  } else {
    $displayImages[] = 'uploads/products/default.png';
  }

  $row['display_images'] = $displayImages;
  $products[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Products | Seven Dwarfs Boutique</title>

  <!-- Libraries -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

  <!-- NProgress (Loading Bar) -->
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />

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

    [x-cloak] {
      display: none !important;
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

    @view-transition {
      navigation: auto;
    }

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
        userMenu: false, 
        productMenu: true
     }" x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

    <!-- 🌸 Sidebar -->
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

        <!-- User Management -->
        <div>
          <button @click="userMenu = !userMenu"
            class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300"
            :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
            <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
              <i class="fas fa-users-cog w-5 text-center text-lg"></i>
              <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
            </div>
            <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': userMenu }"></i>
          </button>
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
              <a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Roles">
                <i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Roles</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- Product Management (Active) -->
        <div>
          <button @click="productMenu = !productMenu"
            class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300"
            :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
            <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
              <i class="fas fa-box-open w-5 text-center text-lg text-[var(--rose)]"></i>
              <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">Product
                Management</span>
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
              <a href="products.php" class="block py-2 active flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Product">
                <i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Product</span>
              </a>
            </li>
            <li>
              <a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center"
                :class="sidebarOpen ? '' : 'justify-center'" title="Stock">
                <i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
                <span x-show="sidebarOpen">Stock In</span>
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

    <!-- 🌸 Main Content -->
    <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out"
      :class="sidebarOpen ? 'ml-64' : 'ml-20'">

      <!-- Header -->
      <header
        class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
        :class="sidebarOpen ? 'left-64' : 'left-20'">

        <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen"
            class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
            <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">Products</h1>
        </div>
      </header>

      <!-- 📄 Page Content -->
      <section class="p-6">

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">

          <!-- Filters -->
          <div class="flex flex-wrap justify-between items-center gap-4">
            <div class="flex gap-4 items-center flex-wrap">
              <form method="GET" id="categoryForm" class="flex items-center gap-2">
                <label class="text-gray-700 font-medium text-xs uppercase tracking-wide">Category:</label>
                <select name="category" onchange="document.getElementById('categoryForm').submit()"
                  class="px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)] cursor-pointer bg-white">
                  <option value="all">All</option>
                        <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id']; ?>" <?= ($selectedCategory == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['category_name']); ?>
                    </option>
                        <?php endforeach; ?>
                </select>
              </form>

              <div class="flex items-center relative">
                <i class="fas fa-search absolute left-3 text-gray-400"></i>
                <input type="text" id="searchBox" placeholder="Search product..."
                  class="pl-10 pr-4 py-2 border rounded-lg w-64 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
                <input type="hidden" id="selectedCategory" value="<?= htmlspecialchars($selectedCategory) ?>">
              </div>
            </div>

            <a href="add_product.php"
              class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white text-sm font-medium rounded-lg px-4 py-2 shadow-sm transition flex items-center gap-2">
              <i class="fas fa-plus"></i> Add Product
            </a>
          </div>

          <!-- Product Table -->
          <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                <tr>
                  <th class="px-6 py-3">Images</th>
                  <th class="px-6 py-3">Product Info</th>
                  <th class="px-6 py-3">Selling Price</th>
                  <!-- REMOVED: Supplier Price Column -->
                  <th class="px-6 py-3">Category</th>
                  <th class="px-6 py-3 text-center">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if (!empty($products)): ?>
                          <?php foreach ($products as $product): ?>
                    <tr class="hover:bg-gray-50 transition">

                      <!-- Images -->
                      <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-2" x-data="{ open: false, imageSrc: '' }">
                                  <?php foreach ($product['display_images'] as $img): ?>
                            <img src="<?= $img ?>"
                              class="w-12 h-12 rounded-lg border shadow-sm object-cover cursor-pointer hover:scale-105 transition-transform duration-200"
                              @click="imageSrc = '<?= $img ?>'; open = true"
                              onerror="this.src='uploads/products/default.png';">
                                  <?php endforeach; ?>

                          <!-- Modal Preview -->
                          <div x-show="open" x-cloak x-transition @click="open = false"
                            class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                            <div class="relative bg-white rounded-xl shadow-lg p-2 max-w-md w-full">
                              <button @click="open = false"
                                class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 text-2xl font-bold z-10">&times;</button>
                              <img :src="imageSrc" alt="Preview" class="w-full h-auto rounded-lg object-contain">
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Product Info -->
                      <td class="px-6 py-4">
                        <p class="font-bold text-gray-800"><?= htmlspecialchars($product['product_name']); ?></p>
                        <p class="text-xs text-gray-500 truncate w-48 mt-1">
                          <?= htmlspecialchars($product['description']); ?></p>
                        <div class="text-xs text-gray-400 mt-1 flex flex-col gap-0.5">
                          <span>Colors: <?= $product['colors'] ?: 'None'; ?></span>
                          <span>Sizes: <?= $product['sizes'] ?: 'None'; ?></span>
                        </div>
                      </td>

                      <!-- Selling Price -->
                      <td class="px-6 py-4 font-bold text-[var(--rose)]">
                        ₱<?= number_format($product['price_id'], 2); ?>
                      </td>

                      <!-- Category -->
                      <td class="px-6 py-4">
                        <span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-xs font-medium">
                                      <?= htmlspecialchars($product['category_name']); ?>
                        </span>
                      </td>

                      <!-- Actions -->
                      <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                          <a href="edit_product.php?id=<?= $product['product_id']; ?>"
                            class="text-yellow-600 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-lg transition shadow-sm"
                            title="Edit">
                            <i class="fas fa-pen text-xs"></i>
                          </a>
                          <a href="delete_product.php?id=<?= $product['product_id']; ?>"
                            onclick="return confirm('Are you sure you want to delete this product?')"
                            class="text-red-600 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition shadow-sm"
                            title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center text-gray-500 py-10">No products found.</td>
                  </tr>
                        <?php endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </section>

    </main>
  </div>

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

    // Live Search Logic (Debounced)
    const searchBox = document.getElementById('searchBox');
    const categorySelect = document.querySelector('select[name="category"]');
    const tableBody = document.querySelector('table tbody');

    function debounce(fn, delay) {
      let timeout;
      return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
      };
    }

    const fetchProducts = debounce(() => {
      const search = searchBox.value.trim();
      const category = categorySelect.value;
      const params = new URLSearchParams({ search, category });

      // Use fetch to get the updated HTML content without reloading
      fetch(`products.php?${params.toString()}`)
        .then(res => res.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newTbody = doc.querySelector('table tbody');
          if (newTbody) tableBody.innerHTML = newTbody.innerHTML;
        });
    }, 300);

    searchBox.addEventListener('input', fetchProducts);
    categorySelect.addEventListener('change', fetchProducts);
  </script>

</body>

</html>