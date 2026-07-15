<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/admin_only.php';

// config.php already calls session_start() with a working save path.
// Do not call session_start() again here, and do not call it before
// config.php - PHP's CLI server uses a different php.ini than XAMPP's
// Apache, and its default session path isn't writable by your user.

// ✅ Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// ✅ Fetch available roles securely
$roles = [];
$role_query = "SELECT role_id, role_name FROM roles";
$role_result = $conn->query($role_query);
if ($role_result) {
    while ($row = $role_result->fetch_assoc()) {
        $roles[] = $row;
    }
}

// ✅ Handle form submission securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid CSRF token!";
        header("Location: /add_user.php");
        exit();
    }

    // Input sanitization
    $username   = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
    $password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8');
    $last_name  = htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8');
    $role_id    = (int) $_POST['role_id'];
    $status_id  = 1; // ✅ default Active
    $created_at = date("Y-m-d H:i:s");

    // ✅ Basic Validation
    if (empty($username) || empty($first_name) || empty($last_name)) {
        $_SESSION['message'] = "All fields are required.";
        header("Location: /add_user.php");
        exit();
    }

    // ✅ Password match check
    if ($password !== $confirm_password) {
        $_SESSION['message'] = "Passwords do not match!";
        header("Location: /add_user.php");
        exit();
    }

    // ✅ Password strength check
    if (!preg_match("/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d!@#$%^&*]{8,}$/", $password)) {
        $_SESSION['message'] = "Password must be at least 8 characters long and include at least one number.";
        header("Location: /add_user.php");
        exit();
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // ✅ Check if username already exists
    $check_query = "SELECT admin_id FROM adminusers WHERE username = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result(); // Store result to check num_rows

    if ($check_stmt->num_rows > 0) {
        $check_stmt->close(); // Close immediately
        $_SESSION['message'] = "Error: Username already exists!";
        header("Location: /add_user.php");
        exit();
    }

    // Close the check statement strictly before starting the next one
    $check_stmt->close();

    // ✅ Insert new user
    // FIX: Create a fresh variable for binding to break reference links from the previous statement
    $insert_username = $username;

    $sql = "INSERT INTO adminusers (username, password_hash, role_id, status_id, created_at, first_name, last_name)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind parameters using the new variable
        $stmt->bind_param("ssissss", $insert_username, $password_hash, $role_id, $status_id, $created_at, $first_name, $last_name);

        if ($stmt->execute()) {
            // ✅ Add to logs
            $log_sql = "INSERT INTO system_logs (user_id, username, role_id, action) VALUES (?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            if ($log_stmt) {
                $action = "Added a new user: " . $insert_username;
                // $_SESSION['username'] isn't set anywhere at login yet, so fall
                // back to a safe default rather than passing an undefined key
                // (which throws a warning and inserts NULL/empty into the log).
                $actingUsername = $_SESSION['username'] ?? ('admin_id_' . $_SESSION['admin_id']);
                $log_stmt->bind_param("isis", $_SESSION['admin_id'], $actingUsername, $_SESSION['role_id'], $action);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $_SESSION['success'] = "User added successfully!";
            header("Location: /manage_users.php");
            exit();
        } else {
            error_log("DB Error: " . $stmt->error);
            $_SESSION['message'] = "Database error: " . $stmt->error;
            header("Location: /add_user.php");
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "Failed to prepare database statement.";
        header("Location: /add_user.php");
        exit();
    }

    // NOTE: don't $conn->close() here if other includes on this page
    // (or ones loaded via the router) might still need $conn afterward.
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --rose: #d37689;
  --rose-hover: #b75f6f;
}
body {
  font-family: 'Poppins', sans-serif;
  background-color: #f9fafb;
}
.card {
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  padding: 2rem;
}
input, select, button {
  transition: all 0.2s ease;
}
</style>

<script>
function validatePassword() {
  const pw = document.getElementById("password").value;
  const cpw = document.getElementById("confirm_password").value;
  const msg = document.getElementById("pw_message");

  if (!pw || !cpw) {
    msg.textContent = "";
    return;
  }

  if (pw !== cpw) {
    msg.textContent = "Passwords do not match ❌";
    msg.classList.remove("text-green-600");
    msg.classList.add("text-red-600");
  } else {
    msg.textContent = "Passwords match ✅";
    msg.classList.remove("text-red-600");
    msg.classList.add("text-green-600");
  }
}
</script>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-10">

  <div class="card w-full max-w-2xl">
    <!-- Header -->
    <div class="text-center mb-8">
      <h2 class="text-3xl font-bold text-[var(--rose)]">➕ Add New User</h2>
      <p class="text-gray-500 text-sm mt-1">Fill in the details to create a new admin account</p>
    </div>

    <!-- Flash Message -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-700 font-medium text-center">
        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?>
      </div>
    <?php elseif (isset($_SESSION['success'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-green-100 text-green-700 font-medium text-center">
        <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form action="/add_user.php" method="POST" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? ''; ?>">


      <!-- First / Last Name -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-1">First Name</label>
          <input type="text" name="first_name" required value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Last Name</label>
          <input type="text" name="last_name" required value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
      </div>

    <!-- Username -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Username</label>
        <input type="text" name="username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
      </div>

      <!-- Password -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-1">Password</label>
          <input type="password" id="password" name="password" required
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none"
            oninput="validatePassword()">
          <p class="text-xs text-gray-500 mt-1">Minimum 8 characters, include at least one number.</p>
        </div>

        <div>
          <label class="block text-gray-700 font-medium mb-1">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none"
            oninput="validatePassword()">
          <p id="pw_message" class="text-sm mt-1"></p>
        </div>
      </div>

      <!-- Role -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Role</label>
        <select name="role_id" required
          class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
          <option value="" disabled selected>Select a role</option>
          <?php foreach ($roles as $role): ?>
            <option value="<?= (int) $role['role_id']; ?>" <?= (isset($_POST['role_id']) && $_POST['role_id'] == $role['role_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($role['role_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit"
          class="flex-1 bg-[var(--rose)] text-white px-6 py-3 rounded-lg font-semibold shadow-md hover:bg-[var(--rose-hover)] active:scale-95 transition-all">
          <i class="fas fa-user-plus mr-2"></i> Add User
        </button>
        <a href="/manage_users.php"
          class="flex-1 bg-gray-100 text-gray-700 text-center px-6 py-3 rounded-lg font-medium hover:bg-gray-200 shadow-sm active:scale-95 transition-all">
          Cancel
        </a>
      </div>
    </form>
  </div>

</body>
</html>