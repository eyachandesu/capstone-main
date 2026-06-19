<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// ✅ Validate user ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid User ID!";
    header("Location: manage_users.php");
    exit();
}

$admin_id = (int) $_GET['id'];

// ✅ Fetch user details including role
$stmt = $conn->prepare("SELECT admin_id, username, admin_email, first_name, last_name, role_id FROM adminusers WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = "User not found!";
    header("Location: manage_users.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// ✅ Fetch roles for dropdown
$roles = [];
$roleQuery = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name ASC");
while ($row = $roleQuery->fetch_assoc()) {
    $roles[] = $row;
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $updates = [];
    $params = [];
    $types = "";

    $username = trim($_POST['username']);
    $email = trim($_POST['admin_email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role_id = (int) $_POST['role_id'];

    if (!empty($username) && $username !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $username;
        $types .= "s";
    }
    if (!empty($email) && $email !== $user['admin_email']) {
        $updates[] = "admin_email = ?";
        $params[] = $email;
        $types .= "s";
    }
    if (!empty($first_name) && $first_name !== $user['first_name']) {
        $updates[] = "first_name = ?";
        $params[] = $first_name;
        $types .= "s";
    }
    if (!empty($last_name) && $last_name !== $user['last_name']) {
        $updates[] = "last_name = ?";
        $params[] = $last_name;
        $types .= "s";
    }

    // ✅ Role update
    if ($role_id != $user['role_id']) {
        $updates[] = "role_id = ?";
        $params[] = $role_id;
        $types .= "i";
    }

    // ✅ Password update
    if (!empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['message'] = "Passwords do not match!";
            header("Location: edit_user.php?id=$admin_id");
            exit();
        } elseif (strlen($_POST['new_password']) < 6) {
            $_SESSION['message'] = "Password must be at least 6 characters long!";
            header("Location: edit_user.php?id=$admin_id");
            exit();
        } else {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
    }

    // ✅ Apply updates
    if (!empty($updates)) {
        $query = "UPDATE adminusers SET " . implode(", ", $updates) . " WHERE admin_id = ?";
        $params[] = $admin_id;
        $types .= "i";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['success'] = "User details updated successfully!";
            header("Location: manage_users.php");
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "No changes were made.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit User | Seven Dwarfs Boutique</title>
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
input, button, select {
  transition: all 0.2s ease;
}
</style>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-10">

  <div class="card w-full max-w-2xl">
    <div class="text-center mb-8">
      <h2 class="text-3xl font-bold text-[var(--rose)]">✏️ Edit User</h2>
      <p class="text-gray-500 text-sm mt-1">Modify user details or reset their password</p>
    </div>

    <!-- 🔔 Flash Messages -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-700 font-medium text-center">
        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
      </div>
    <?php elseif (isset($_SESSION['success'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-green-100 text-green-700 font-medium text-center">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="edit_user.php?id=<?= $admin_id; ?>" class="space-y-5">
      
 <!-- First & Last Name -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-1">First Name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']); ?>" 
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']); ?>" 
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
      </div>


      <!-- Username -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($user['username']); ?>" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
      </div>

  
      <!-- Role -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Role</label>
        <select name="role_id"
          class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)]">

          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['role_id']; ?>"
              <?= ($user['role_id'] == $r['role_id']) ? 'selected' : ''; ?>>
              <?= htmlspecialchars($r['role_name']); ?>
            </option>
          <?php endforeach; ?>

        </select>
      </div>

      <!-- Password -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-1">New Password</label>
          <input type="password" name="new_password"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
          <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password.</p>
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Confirm Password</label>
          <input type="password" name="confirm_password"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit"
          class="flex-1 bg-[var(--rose)] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[var(--rose-hover)] shadow-md">
          <i class="fas fa-save mr-2"></i> Update User
        </button>
        <a href="manage_users.php"
          class="flex-1 bg-gray-100 text-gray-700 px-6 py-3 text-center rounded-lg font-medium hover:bg-gray-200 shadow-sm">
          Cancel
        </a>
      </div>

    </form>
  </div>

</body>
</html>
