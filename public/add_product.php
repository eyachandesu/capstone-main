<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';

$preselectedCategoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

// Fetch dropdown data (Removed Supplier Fetch)
$category_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");

// -----------------------------
// Server-side handling
// -----------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name   = trim($_POST['product_name'] ?? '');
    // Removed Supplier Price
    $category_id    = isset($_POST['category']) ? intval($_POST['category']) : 0;
    // Removed Supplier ID

    $errors = [];

    // Validation Logic
    if ($product_name === '') $errors[] = "Product name is required.";
    if ($category_id <= 0) $errors[] = "Please select a valid category.";
    // Removed Supplier ID Validation

    // Check category exists
    $cat_check = $conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
    $cat_check->bind_param("i", $category_id);
    $cat_check->execute();
    if ($cat_check->get_result()->num_rows === 0) $errors[] = "Selected category does not exist.";
    $cat_check->close();

    // Removed Supplier Check

    // Duplicate check
    $dup_check = $conn->prepare("SELECT product_id FROM products WHERE LOWER(product_name) = LOWER(?) LIMIT 1");
    $dup_check->bind_param("s", $product_name);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) $errors[] = "A product with that name already exists.";
    $dup_check->close();

    // Image validation
    $image_filenames = [];
    $has_images = !empty($_FILES['images']['name'][0]);
    
    if (!$has_images) {
        $errors[] = "Please upload at least one product image.";
    } else {
        $allowed = ["jpg", "jpeg", "png", "gif", "webp"];
        foreach ($_FILES['images']['name'] as $i => $filename) {
            if (empty($filename)) continue;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = "Only JPG, PNG, WEBP and GIF images are allowed.";
                break;
            }
            if ($_FILES['images']['error'][$i] !== 0) {
                $errors[] = "Image upload failed. Try again.";
                break;
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        // Keep input values
        $_SESSION['old_inputs'] = $_POST;
        header("Location: add_product.php");
        exit;
    }

    // Process Uploads
    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    foreach ($_FILES['images']['name'] as $i => $filename) {
        if (empty($filename)) continue;
        $tmp_name = $_FILES['images']['tmp_name'][$i];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $unique_name = uniqid('prod_') . '.' . $ext;
        $target_file = $target_dir . $unique_name;
        if (move_uploaded_file($tmp_name, $target_file)) {
            $image_filenames[] = $target_file;
        }
    }

    $image_url_str = json_encode($image_filenames, JSON_UNESCAPED_SLASHES);

    // Insert (Removed supplier_price and supplier_id)
    $sql = "INSERT INTO products (product_name, category_id, image_url, created_at)
            VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    // Updated bind_param to match 3 variables: s (string), i (int), s (string)
    $stmt->bind_param("sis", $product_name, $category_id, $image_url_str);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Product added successfully!";
        unset($_SESSION['old_inputs']); // Clear old inputs
        header("Location: products.php");
        exit;
    } else {
        $_SESSION['error'] = "Database error: " . $stmt->error;
        header("Location: add_product.php");
        exit;
    }
}

// Retrieve old inputs if validation failed
$old = $_SESSION['old_inputs'] ?? [];
unset($_SESSION['old_inputs']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Product | Seven Dwarfs Boutique</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --rose: #e59ca8; /* Matched the rose color from your products page */
  --rose-hover: #d27b8c;
  --rose-light: #fff0f3;
}
body {
  font-family: 'Poppins', sans-serif;
  background-color: #f3f4f6;
  color: #374151;
}
.input-field {
    transition: all 0.2s ease-in-out;
}
.input-field:focus {
    border-color: var(--rose);
    box-shadow: 0 0 0 4px var(--rose-light);
    background-color: #fff;
}
/* Custom Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
</style>
</head>

<body class="min-h-screen flex items-center justify-center p-6">

  <div class="max-w-4xl w-full bg-white shadow-xl rounded-2xl overflow-hidden">
    
    <!-- Header -->
    <div class="bg-white border-b border-gray-100 p-6 flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <span class="bg-[var(--rose-light)] text-[var(--rose)] p-2 rounded-lg"><i class="fas fa-plus"></i></span>
          Add New Product
        </h2>
        <p class="text-gray-400 text-sm mt-1">Create a new item for your inventory.</p>
      </div>
      <a href="products.php" class="group flex items-center gap-2 text-gray-500 hover:text-[var(--rose)] transition font-medium text-sm">
        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Back to List
      </a>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['error'])): ?>
      <div class="m-6 mb-0 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg flex items-start gap-3">
        <i class="fas fa-exclamation-circle mt-0.5"></i>
        <div>
            <p class="font-bold text-sm">Validation Error</p>
            <p class="text-sm leading-relaxed"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Content -->
    <div class="p-8">
      <form id="productForm" method="POST" enctype="multipart/form-data" class="space-y-8" novalidate>
        
        <!-- Section 1: General Info -->
        <div>
          <h3 class="text-xs uppercase tracking-wider text-gray-500 font-bold mb-4">General Information</h3>
          <div>
            <label class="block text-gray-700 font-semibold mb-2 text-sm">Product Name <span class="text-red-500">*</span></label>
            <div class="relative">
                <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-tag"></i></span>
                <input type="text" name="product_name" id="product_name" required
                  value="<?= htmlspecialchars($old['product_name'] ?? '') ?>"
                  placeholder="e.g. Summer Floral Dress"
                  class="input-field w-full border border-gray-300 rounded-xl pl-10 pr-4 py-3 outline-none bg-gray-50 text-gray-800 placeholder-gray-400 font-medium">
            </div>
          </div>
        </div>

        <hr class="border-gray-100">

        <!-- Section 3: Organization -->
        <div>
          <h3 class="text-xs uppercase tracking-wider text-gray-500 font-bold mb-4">Organization</h3>
          <!-- Removed Grid, made full width since Supplier is gone -->
          <div class="w-full">
            <!-- Category -->
            <div>
              <label class="block text-gray-700 font-semibold mb-2 text-sm">Category <span class="text-red-500">*</span></label>
              <div class="relative">
                <span class="absolute left-4 top-3.5 text-gray-400"><i class="fas fa-layer-group"></i></span>
                <select name="category" id="category" required
                  class="input-field w-full border border-gray-300 rounded-xl pl-10 pr-10 py-3 outline-none bg-gray-50 appearance-none cursor-pointer">
                  <option value="">Select Category</option>
                  <?php 
                    if ($category_result) {
                        $category_result->data_seek(0);
                        while ($row = $category_result->fetch_assoc()): 
                  ?>
                    <option value="<?= $row['category_id'] ?>" <?= (($old['category'] ?? $preselectedCategoryId) == $row['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($row['category_name']) ?>
                    </option>
                  <?php endwhile; } ?>
                </select>
                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none text-xs"></i>
              </div>
            </div>
            <!-- Supplier Field Removed Here -->
          </div>
        </div>

        <hr class="border-gray-100">

        <!-- Section 4: Images -->
        <div>
            <h3 class="text-xs uppercase tracking-wider text-gray-500 font-bold mb-4">Product Media</h3>
            
            <!-- Upload Area -->
            <label class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-[var(--rose-light)] hover:border-[var(--rose)] transition-all group">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <div class="w-12 h-12 rounded-full bg-gray-100 group-hover:bg-white flex items-center justify-center mb-3 transition-colors">
                        <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 group-hover:text-[var(--rose)] transition-colors"></i>
                    </div>
                    <p class="text-sm text-gray-500"><span class="font-semibold text-[var(--rose)]">Click to upload</span> images</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP (Max 5MB)</p>
                </div>
                <input id="images" type="file" name="images[]" accept="image/*" multiple class="hidden" required>
            </label>
            <p id="file-count" class="text-xs text-right text-gray-400 mt-2 h-4"></p>

            <!-- Image Preview Container -->
            <div id="imgPreview" class="grid grid-cols-4 md:grid-cols-6 gap-4 mt-4 empty:hidden"></div>
        </div>

        <!-- Footer Buttons -->
        <div class="flex items-center justify-end gap-4 pt-4">
            <a href="products.php" class="text-gray-500 hover:text-gray-800 font-medium text-sm transition">
                Cancel
            </a>
            <button type="submit" id="submitBtn"
                class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-8 py-3 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all font-semibold flex items-center gap-2">
                <i class="fas fa-check"></i> Save Product
            </button>
        </div>

      </form>
    </div>
  </div>

  <!-- JavaScript Logic -->
  <script>
    // 1. Image Preview & Count Logic
    const imagesInput = document.getElementById('images');
    const imgPreview = document.getElementById('imgPreview');
    const fileCount = document.getElementById('file-count');

    imagesInput.addEventListener('change', function() {
      imgPreview.innerHTML = '';
      const files = Array.from(this.files);

      // Update count text
      if (files.length > 0) {
          fileCount.textContent = `${files.length} file(s) selected`;
          fileCount.classList.add('text-[var(--rose)]');
      } else {
          fileCount.textContent = '';
      }

      // Generate Previews
      files.forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
          const div = document.createElement('div');
          div.className = 'relative group aspect-square';
          
          const img = document.createElement('img');
          img.src = e.target.result;
          img.className = 'w-full h-full object-cover rounded-lg border border-gray-200 shadow-sm';
          
          div.appendChild(img);
          imgPreview.appendChild(div);
        };
        reader.readAsDataURL(file);
      });
    });

    // 2. Client-Side Validation (Basic visual cues)
    document.getElementById('productForm').addEventListener('submit', function(e) {
        let isValid = true;
        const requiredInputs = this.querySelectorAll('[required]');
        
        requiredInputs.forEach(input => {
            if (!input.value) {
                isValid = false;
                input.classList.add('border-red-400', 'bg-red-50');
                // Remove error style on focus
                input.addEventListener('input', function() {
                    this.classList.remove('border-red-400', 'bg-red-50');
                }, {once: true});
            }
        });

        if (!isValid) {
            e.preventDefault();
            // Simple shake animation or scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    // 3. Auto-Capitalize Product Name
    document.getElementById('product_name').addEventListener('blur', function() {
        if(this.value) {
            this.value = this.value.replace(/\w\S*/g, (w) => (w.replace(/^\w/, (c) => c.toUpperCase())));
        }
    });
  </script>
</body>
</html>