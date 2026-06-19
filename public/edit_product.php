<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// 🔹 Initialize variables
$error_msg = "";
$success_msg = "";

// ✅ Get product ID safely
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    echo "Invalid product ID.";
    exit();
}

// ✅ Fetch categories for dropdown
$categories = [];
$catQuery = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $catQuery->fetch_assoc()) {
    $categories[] = $row;
}

// (Removed Supplier Fetching)

// ✅ Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Product not found!";
    exit();
}

// ✅ Handle existing images
$existingImages = [];
if (!empty($product['image_url'])) {
    $trimmed = trim($product['image_url']);
    if (str_starts_with($trimmed, '[')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $existingImages = $decoded;
        }
    } else {
        $existingImages = array_filter(array_map('trim', explode(',', $product['image_url'])));
    }
}

// ---------------------------------------------------------
// 🚀 HANDLE FORM SUBMISSION
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name   = trim($_POST['product_name']);
    $category_id    = (int)$_POST['category_id'];
    // (Removed supplier_id from POST)

    // 🛑 VALIDATION 1: Check Empty Fields
    if (empty($product_name) || empty($category_id)) {
        $error_msg = "Product Name and Category are required.";
    }
    // 🛑 VALIDATION 2: Check Duplicate Name (Excluding current ID)
    else {
        $checkStmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? AND product_id != ?");
        $checkStmt->bind_param("si", $product_name, $product_id);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $error_msg = "A product with this name already exists.";
        }
        $checkStmt->close();
    }

    // ✅ PROCEED IF NO ERRORS
    if (empty($error_msg)) {
        
        // 🖼 Handle image uploads
        $uploadedImages = [];
        $uploadDir = 'uploads/products/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    
                    // Check Image Type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = mime_content_type($tmp_name);
                    
                    if (in_array($fileType, $allowedTypes)) {
                        $fileName = basename($_FILES['images']['name'][$key]);
                        $targetPath = $uploadDir . uniqid() . '_' . $fileName;

                        if (move_uploaded_file($tmp_name, $targetPath)) {
                            $uploadedImages[] = $targetPath;
                        }
                    }
                }
            }
            // Replace old images with new ones if upload successful
            $finalImages = !empty($uploadedImages) ? $uploadedImages : $existingImages;
        } else {
            $finalImages = $existingImages; // keep existing if no new upload
        }

        $imageString = json_encode($finalImages, JSON_UNESCAPED_SLASHES);

        // ✅ Update database (Removed supplier_id)
        $updateStmt = $conn->prepare("
            UPDATE products
            SET product_name = ?, category_id = ?, image_url = ?
            WHERE product_id = ?
        ");
        
        // Types: s (string name), i (int category), s (string images), i (int id)
        $updateStmt->bind_param(
            "sisi",
            $product_name,
            $category_id,
            $imageString,
            $product_id
        );

        if ($updateStmt->execute()) {
            header("Location: products.php?updated=1");
            exit();
        } else {
            $error_msg = "Database error: Failed to update product.";
        }
        $updateStmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Product | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
      --rose-light: #fff0f3;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f3f4f6;
      color: #374151;
    }
    /* Custom Scrollbar for aesthetics */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    
    /* Input Focus Styles */
    .input-field {
        transition: all 0.2s ease-in-out;
    }
    .input-field:focus {
        border-color: var(--rose);
        box-shadow: 0 0 0 4px var(--rose-light);
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">

  <div class="max-w-4xl w-full bg-white shadow-xl rounded-2xl overflow-hidden">
    
    <!-- Header -->
    <div class="bg-white border-b border-gray-100 p-6 flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <span class="bg-[var(--rose-light)] text-[var(--rose)] p-2 rounded-lg"><i class="fas fa-pen-nib"></i></span>
          Edit Product
        </h2>
        <p class="text-gray-400 text-sm mt-1">Update product details and inventory settings.</p>
      </div>
      <a href="products.php" 
         class="group flex items-center gap-2 text-gray-500 hover:text-[var(--rose)] transition font-medium text-sm">
        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Back to List
      </a>
    </div>

    <!-- Content -->
    <div class="p-8">
        
        <!-- 🛑 Error Alert -->
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <div>
                    <p class="font-bold text-sm">Validation Error</p>
                    <p class="text-sm"><?= htmlspecialchars($error_msg) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-8">
            
            <!-- Section 1: Basic Info -->
            <div>
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-bold mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 gap-6">
                    <!-- Product Name -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2 text-sm">Product Name <span class="text-red-500">*</span></label>
                        <input 
                        type="text" 
                        name="product_name" 
                        value="<?= htmlspecialchars($_POST['product_name'] ?? $product['product_name']); ?>" 
                        required
                        placeholder="e.g., Floral Summer Dress"
                        class="input-field w-full border border-gray-300 rounded-xl px-4 py-3 outline-none text-gray-700 placeholder-gray-400 bg-gray-50 focus:bg-white"
                        >
                    </div>

                    <!-- Category Row (Full width now that Supplier is gone) -->
                    <div class="w-full">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2 text-sm">Category <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select name="category_id" required class="input-field w-full border border-gray-300 rounded-xl px-4 py-3 outline-none appearance-none bg-gray-50 focus:bg-white cursor-pointer">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id']; ?>" <?= (($cat['category_id'] == ($_POST['category_id'] ?? $product['category_id'])) ? 'selected' : ''); ?>>
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            <!-- Section 2: Pricing -->
            <!-- Selling Price and Supplier Price fields are removed -->

            <!-- Section 3: Images -->
            <div>
                <h3 class="text-sm uppercase tracking-wider text-gray-500 font-bold mb-4">Product Images</h3>
                
                <!-- Current Images Gallery -->
                <?php if (!empty($existingImages)): ?>
                <div class="mb-4">
                    <p class="text-xs text-gray-500 mb-2">Current Images:</p>
                    <div class="flex flex-wrap gap-3">
                        <?php foreach($existingImages as $img): ?>
                            <div class="relative group">
                                <img src="<?= htmlspecialchars($img) ?>" class="w-20 h-20 object-cover rounded-lg border border-gray-200 shadow-sm">
                                <div class="absolute inset-0 bg-black/10 rounded-lg hidden group-hover:block transition"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Modern File Upload Area -->
                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer bg-gray-50 hover:bg-[var(--rose-light)] hover:border-[var(--rose)] transition-colors">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-500"><span class="font-semibold text-[var(--rose)]">Click to upload</span> new images</p>
                        <p class="text-xs text-gray-400 mt-1">PNG, JPG, WebP (Replaces old images)</p>
                    </div>
                    <input type="file" name="images[]" multiple accept="image/*" class="hidden" onchange="showFileCount(this)">
                </label>
                <p id="file-count" class="text-xs text-center text-gray-500 mt-2 h-4"></p>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-4 pt-6">
                <a href="products.php" class="text-gray-500 hover:text-gray-800 font-medium text-sm transition">
                    Cancel
                </a>
                <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-8 py-3 rounded-xl shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all font-semibold">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
  </div>

<script>
function showFileCount(input) {
    const countDisplay = document.getElementById('file-count');
    if (input.files && input.files.length > 0) {
        countDisplay.textContent = `${input.files.length} new file(s) selected`;
        countDisplay.classList.add('text-[var(--rose)]', 'font-medium');
    } else {
        countDisplay.textContent = '';
    }
}
</script>
</body>
</html>