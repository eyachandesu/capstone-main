<?php

require_once __DIR__ . '/../config/config.php';

// ✅ FIX 3: Parse your validation cookie into the $error variable
$error = '';
if (isset($_COOKIE['validation_message']) && ($_COOKIE['validation_type'] ?? '') === 'error') {
  $error = htmlspecialchars($_COOKIE['validation_message']);

  // Expire cookies so they clear out upon page refresh
  setcookie("validation_message", "", time() - 3600, "/");
  setcookie("validation_type", "", time() - 3600, "/");
}

// Generate a CSRF token for this form. config.php starts the session,
// so $_SESSION is available here.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Seven Dwarfs | Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-pink-200 via-white to-pink-400">

  <div
    class="glassmorphism-container w-full max-w-md p-8 rounded-3xl shadow-2xl border border-pink-200 backdrop-blur-lg bg-white/80 transition-transform duration-300 hover:scale-[1.01]">

    <!-- Logo -->
    <div class="flex flex-col items-center mb-6">
      <img src="img/logo2.png" alt="Seven Dwarfs Logo"
        class="h-32 w-32 shadow-lg rounded-full border-4 border-pink-200 mb-2 object-cover" />
      <h2 class="text-3xl font-extrabold text-center text-pink-600 mt-2 tracking-wide drop-shadow-sm">Seven Dwarfs</h2>
    </div>

    <h3 class="text-xl font-semibold text-center text-gray-700 mb-6">System Login</h3>

    <!-- 🔴 DISPLAY ERROR MESSAGE HERE -->
    <?php if (!empty($error)): ?>
      <div
        class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md text-sm flex items-start animate-pulse">
        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
        <div><?php echo $error; ?></div>
      </div>
    <?php endif; ?>

    <form action="/controllers/login_handler.php" method="post" class="space-y-5">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" />
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
            <i class="fas fa-user"></i>
          </span>
          <input type="text" name="username" required placeholder="Enter your username" class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg bg-white/90
                focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent transition" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
            <i class="fas fa-lock"></i>
          </span>
          <input type="password" name="password" id="password" required placeholder="••••••••" class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg bg-white/90
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