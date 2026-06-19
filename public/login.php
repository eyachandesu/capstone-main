<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/config.php';

// Prevent output buffering issues
if (headers_sent($file, $line)) {
    die("⚠️ Error: Output started in $file on line $line. Remove spaces before <?php in that file.");
}

$error = ""; // Variable to hold error messages

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"])) {
    $login_input = trim($_POST["login"]); 
    $password = $_POST["password"];

    // 🔹 1. Check Admin/Staff Table
    $sql = "SELECT admin_id, admin_email, username, password_hash, role_id, status_id 
            FROM adminusers 
            WHERE admin_email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $db_email, $db_username, $db_password_hash, $role_id, $status_id);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            // 🛑 CHECK STATUS
            if ($status_id != 1) {
                $error = "🚫 Your account is deactivated. Please contact the administrator.";
            } else {
                // ✅ Login Success
                $_SESSION["loggedin"] = true;
                $_SESSION["admin_id"] = $admin_id;
                $_SESSION["role_id"]  = $role_id;
                $_SESSION["username"] = $db_username; // Added so the sidebar shows your name!

                $log_action = "Login";
                
                $log_sql = "INSERT INTO system_logs (user_id, username, role_id, action) VALUES (?, ?, ?, ?)";
                
                if ($log_stmt = $conn->prepare($log_sql)) {
                    $log_stmt->bind_param("isis", $admin_id, $db_username, $role_id, $log_action);
                    $log_stmt->execute();
                    $log_stmt->close();
                }

                $update_sql = "UPDATE adminusers SET last_logged_in = NOW() WHERE admin_id = ?";
                if ($update_stmt = $conn->prepare($update_sql)) {
                    $update_stmt->bind_param("i", $admin_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                // 🛑 CRITICAL FIX: Force PHP to save the session BEFORE redirecting
                session_write_close(); 

                if ($role_id == 2) {
                    header("Location: dashboard.php");
                } elseif ($role_id == 1) {
                    header("Location: cashier_pos.php");
                } else {
                    $error = "Error: Invalid Role assigned.";
                }
                exit;
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        // 🔹 2. Check Customer Table (if not found in Admin)
        $stmt->close(); // Close previous statement
        
        $sql = "SELECT customer_id, email, password_hash, status_id FROM customers WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($customer_id, $db_email, $db_password_hash, $cust_status_id);
            $stmt->fetch();

            if (password_verify($password, $db_password_hash)) {
                // 🛑 CHECK CUSTOMER STATUS
                if ($cust_status_id != 1) {
                    $error = "🚫 Your account is deactivated.";
                } else {
                    // ✅ Login Success
                    $_SESSION["loggedin"] = true;
                    $_SESSION["role"] = "Customer";
                    $_SESSION["customer_id"] = $customer_id; // Added safety fallback
                    $_SESSION["email"] = $db_email;
                    
                    // Note: We do NOT log customers into 'system_logs' because that table 
                    // has a Foreign Key linking 'user_id' to 'adminusers'. 
                    
                    // 🛑 CRITICAL FIX: Force PHP to save the session BEFORE redirecting
                    session_write_close();

                    header("Location: customerside/homepage.php");
                    exit;
                }
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "Account not found.";
        }
    }
    
    $stmt->close();
    $conn->close();
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seven Dwarfs | Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style> body { font-family: 'Poppins', sans-serif; } </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-pink-200 via-white to-pink-400">
  
  <div class="glassmorphism-container w-full max-w-md p-8 rounded-3xl shadow-2xl border border-pink-200 backdrop-blur-lg bg-white/80 transition-transform duration-300 hover:scale-[1.01]">
      
      <!-- Logo -->
      <div class="flex flex-col items-center mb-6">
        <img src="img/logo2.png" alt="Seven Dwarfs Logo" class="h-32 w-32 shadow-lg rounded-full border-4 border-pink-200 mb-2 object-cover" />
        <h2 class="text-3xl font-extrabold text-center text-pink-600 mt-2 tracking-wide drop-shadow-sm">Seven Dwarfs</h2>
      </div>
      
      <h3 class="text-xl font-semibold text-center text-gray-700 mb-6">System Login</h3>

      <!-- 🔴 DISPLAY ERROR MESSAGE HERE -->
      <?php if (!empty($error)): ?>
        <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md text-sm flex items-start animate-pulse">
            <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
            <div><?php echo $error; ?></div>
        </div>
      <?php endif; ?>
      
      <form method="post" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
              <i class="fas fa-user"></i>
            </span>
            <input type="text" name="login" required placeholder="Enter your username"
              class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg bg-white/90
                focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent transition" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
              <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="password" id="password" required placeholder="••••••••"
              class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg bg-white/90
                focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent pr-10 transition" />
            <button type="button" onclick="togglePassword()"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-pink-500 focus:outline-none">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
        <div class="pt-2">
          <button type="submit"
            class="w-full bg-gradient-to-r from-pink-400 to-pink-500 text-white py-2.5 rounded-lg font-semibold shadow-lg hover:shadow-xl hover:from-pink-500 hover:to-pink-600 transition-all duration-200 transform hover:-translate-y-0.5">
            Login
          </button>
        </div>
      </form>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById("password");
      const eyeIcon = document.getElementById("eyeIcon");
      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = "password";
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>