<?php
require_once __DIR__ . '/../config/config.php';

// --- SETTINGS ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

$admin_id = $_SESSION['admin_id'] ?? null;

/* ========================================================
   HELPER: Get Detailed Stock Variations
======================================================== */
function getVariations($conn, $pid) { 
    $data = []; 
    $sql = "SELECT 
            st.stock_id,
            COALESCE(s.size, 'Free Size') as size, 
            COALESCE(c.color, 'Standard') as color, 
            st.current_qty as qty
            FROM stock st 
            LEFT JOIN sizes s ON st.size_id = s.size_id 
            LEFT JOIN colors c ON st.color_id = c.color_id 
            WHERE st.product_id = " . intval($pid);
    $res = $conn->query($sql); 
    while($r = $res->fetch_assoc()){ 
        $r['qty'] = intval($r['qty']);
        $data[] = $r; 
    } 
    return $data; 
}

/* ========================================================
   API: VERIFY ADMIN CREDENTIALS (MANAGER OVERRIDE)
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'verify_password') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    try {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if(empty($username) || empty($password)) {
            throw new Exception("Please enter Admin credentials.");
        }

        $stmt = $conn->prepare("SELECT admin_id, password_hash, role_id FROM adminusers WHERE username = ?");
        $stmt->bind_param("s", $username); 
        $stmt->execute(); 
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                if ($row['role_id'] == 2) {
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Authorization Failed: User is not an Admin.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect Admin password']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Admin username not found']);
        }
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit;
}

/* ========================================================
   API: GET ORDER DETAILS
======================================================== */
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    try {
        if (!isset($_GET['oid'])) throw new Exception("Missing Order ID");
        $oid = intval($_GET['oid']);
        $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $oid); $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) throw new Exception("Order #$oid not found.");
        $stmt->close();
        $sql = "SELECT oi.order_id, oi.product_id, oi.stock_id, oi.qty, oi.price, p.product_name, COALESCE(oi.size, 'Free Size') AS size_name, COALESCE(oi.color, 'Standard') AS color_name FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?";
        $stmt = $conn->prepare($sql); $stmt->bind_param("i", $oid); $stmt->execute();
        $result = $stmt->get_result(); $items = []; while ($row = $result->fetch_assoc()) { $items[] = $row; } $stmt->close();
        echo json_encode(['status' => 'success', 'items' => $items]);
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit;
}

/* ========================================================
   API: GET ORDER FROM ORDER NUMBER
======================================================== */

if (isset($_GET['action']) && $_GET['action'] === 'get_order_id_by_transaction') {

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    try {

        if (empty($_GET['tid'])) {
            throw new Exception("Missing Order Number");
        }

        // ORD-000085 -> 85
        $order_id = intval(str_replace("ORD-", "", $_GET['tid']));

        $stmt = $conn->prepare("
            SELECT order_id
            FROM orders
            WHERE order_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            echo json_encode([
                "status"=>"success",
                "order_id"=>$row['order_id']
            ]);

        } else {

            throw new Exception("Order not found.");

        }

        $stmt->close();

    } catch(Exception $e){

        echo json_encode([
            "status"=>"error",
            "message"=>$e->getMessage()
        ]);

    }

    exit;
}

/* ========================================================
   API: PROCESS REFUND
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'process_refund') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $conn->begin_transaction();
    try {
        if (!$admin_id) throw new Exception("Not authenticated");
        if (empty($input['items'])) throw new Exception("No items selected");
        $oid = intval($input['order_id']);

        $refund_stmt = $conn->prepare("INSERT INTO refunds (order_id, order_item_id, product_id, stock_id, size_id, color_id, refund_amount, refunded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $adj_stmt = $conn->prepare("INSERT INTO stock_adjustments (stock_id, quantity, type, reason, adjusted_by) VALUES (?, ?, ?, ?, ?)");
        $stock_upd_stmt = $conn->prepare("UPDATE stock SET current_qty = current_qty + ? WHERE stock_id = ?");

        $total_cash_refund = 0; 
        
        foreach ($input['items'] as $item) {
            $product_id = intval($item['product_id']);
            $stock_id   = intval($item['stock_id']);
            $refund_qty = intval($item['refund_qty']);
            $price      = floatval($item['price']);
            $action     = $item['action'];
            
            $sc = $conn->query("SELECT size_id, color_id FROM stock WHERE stock_id = $stock_id")->fetch_assoc();
            $oi = $conn->query("SELECT id FROM order_items WHERE order_id = $oid AND product_id = $product_id AND stock_id = $stock_id LIMIT 1")->fetch_assoc();
            $order_item_id = $oi['id'] ?? null;

            if ($action === 'damaged') {
                $amount = $refund_qty * $price;
                $total_cash_refund += $amount;
                $refund_stmt->bind_param("iiiiiddi", $oid, $order_item_id, $product_id, $stock_id, $sc['size_id'], $sc['color_id'], $amount, $admin_id);
                $refund_stmt->execute();
                $type = 'damaged'; $reason = "Order #$oid Return (Damaged/Refunded)";
                $adj_stmt->bind_param("iissi", $stock_id, $refund_qty, $type, $reason, $admin_id);
                $adj_stmt->execute();
            } 
            elseif ($action === 'restock') {
                $amount = 0; 
                $refund_stmt->bind_param("iiiiiddi", $oid, $order_item_id, $product_id, $stock_id, $sc['size_id'], $sc['color_id'], $amount, $admin_id);
                $refund_stmt->execute();
                $stock_upd_stmt->bind_param("ii", $refund_qty, $stock_id);
                $stock_upd_stmt->execute();
                $type = 'return_restock'; $reason = "Order #$oid Return (Sellable/No Refund)";
                $adj_stmt->bind_param("iissi", $stock_id, $refund_qty, $type, $reason, $admin_id);
                $adj_stmt->execute();
            }
        }

        if ($total_cash_refund > 0) {
            $conn->query("UPDATE orders SET total_amount = total_amount - $total_cash_refund, order_status_id = 4 WHERE order_id = $oid");
            $neg = -$total_cash_refund;
            $conn->query("INSERT INTO transactions (order_id, payment_method_id, total, order_status_id, date_time) VALUES ($oid, NULL, $neg, 4, NOW())");
            $msg = "Refund Processed: ₱" . number_format($total_cash_refund, 2) . " returned to customer.";
        } else {
            $conn->query("UPDATE orders SET order_status_id = 4 WHERE order_id = $oid"); 
            $msg = "Items Restocked. No cash refund issued (Sellable Condition).";
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'refund_total' => $total_cash_refund, 'message' => $msg]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// Cashier Info & Data Fetching
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id); $cashierRes->execute();
$cashier_name = $cashierRes->get_result()->fetch_assoc()['first_name'] ?? 'Unknown';
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$payments = $conn->query("SELECT payment_method_id, payment_method_name FROM payment_methods ORDER BY payment_method_id ASC");
$products = $conn->query("SELECT p.product_id, p.product_name, p.price_id AS price, p.image_url, c.category_name, COALESCE(SUM(s.current_qty), 0) AS total_stock FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN stock s ON p.product_id = s.product_id GROUP BY p.product_id ORDER BY p.product_id DESC");

/* ========================================================
   MAIN: CHECKOUT LOGIC
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $payment_method_id = intval($_POST['payment_method_id']);
    $cash_given = floatval($_POST['cash_given']);
    $total = floatval($_POST['total']); 
    $overall_discount_amt = floatval($_POST['discount_amount'] ?? 0);

    if (empty($cart)) {
        echo "<script>alert('Cart empty');</script>";
    } else {
        $pmRes = $conn->query("SELECT payment_method_name FROM payment_methods WHERE payment_method_id = $payment_method_id");
        $pmName = $pmRes ? strtolower($pmRes->fetch_assoc()['payment_method_name']) : '';
        if (strpos($pmName, 'gcash') !== false) { $cash_given = $total; $changes = 0; } 
        else { if (($cash_given - $total) < -0.01) { echo "<script>alert('Insufficient cash'); window.location.href='cashier_pos.php';</script>"; exit; } $changes = $cash_given - $total; }

        $conn->begin_transaction();
        try {
            $trx_id = "TRX-" . strtoupper(uniqid());
                $sql = "INSERT INTO orders (
                    admin_id,
                    total_amount,
                    discount_amount,
                    cash_given,
                    changes,
                    order_status_id,
                    created_at,
                    payment_method_id
                ) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "iddddi",
                    $admin_id,
                    $total,
                    $overall_discount_amt,
                    $cash_given,
                    $changes,
                    $payment_method_id
                );

                $stmt->execute();

                $order_id = $stmt->insert_id;

                $stmt->close();

            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, color, size, stock_id, qty, price, discount_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                $pid = intval($item['product_id']); $qty = intval($item['quantity']); $size = $item['size']; $color = $item['color'];
                $final_price = floatval($item['price']); 
                $discount_per_unit = floatval($item['discount_amount']);

                $st = $conn->prepare("SELECT st.stock_id, st.current_qty FROM stock st LEFT JOIN sizes s ON st.size_id = s.size_id LEFT JOIN colors c ON st.color_id = c.color_id WHERE st.product_id = ? AND (s.size = ? OR (? = 'Free Size' AND s.size IS NULL)) AND (c.color = ? OR (? = 'Standard' AND c.color IS NULL)) LIMIT 1");
                $st->bind_param("issss", $pid, $size, $size, $color, $color); $st->execute();
                $sd = $st->get_result()->fetch_assoc(); $st->close();

                if (!$sd || $sd['current_qty'] < $qty) throw new Exception("Out of stock: " . $item['name']);
                
                $stock_id = $sd['stock_id'];
                $itemStmt->bind_param("iissiidd", $order_id, $pid, $color, $size, $stock_id, $qty, $final_price, $discount_per_unit);
                $itemStmt->execute();
                $conn->query("UPDATE stock SET current_qty = current_qty - $qty WHERE stock_id = $stock_id");
            }
            $itemStmt->close();
            $conn->query("INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time) VALUES ($order_id, NULL, $payment_method_id, $total, 0, NOW())");
            $conn->commit();
            echo "<script>alert('Transaction successful!'); window.location.href='receipt.php?order_id=$order_id';</script>";
            exit;
        } catch (Exception $e) { $conn->rollback(); echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>"; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cashier POS | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
<style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .fade-in { animation: fadeIn 0.3s ease-out forwards; }
    #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .sidebar-text { transition: opacity 0.2s ease-in-out, transform 0.2s ease; white-space: nowrap; }
    .w-20 .sidebar-text { opacity: 0; transform: translateX(-10px); pointer-events: none; }
    .modal-backdrop { transition: opacity 0.3s ease, visibility 0.3s ease; }
    .modal-content { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease; }
    .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.6); }
</style>
</head>

<body class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

<!-- SIDEBAR -->
<aside id="sidebar" :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-white border-r border-slate-100 flex flex-col h-full z-30 shadow-sm relative shrink-0">
    <div class="flex items-center justify-between h-20 px-0 pl-6 border-b border-slate-100">
        <div class="flex items-center gap-2 overflow-hidden">
            <div class="shrink-0 text-xl font-bold text-rose-600 tracking-tight">SD</div>
            <div class="sidebar-text" :class="!sidebarOpen && 'opacity-0'">
                <h1 class="text-xl font-bold text-rose-600 tracking-tight">Seven Dwarfs</h1>
                <p class="text-[10px] font-semibold text-slate-300 uppercase tracking-widest">Boutique POS</p>
            </div>
        </div>
    </div>
    <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3 top-24 bg-white border border-slate-200 rounded-full p-1 shadow-md text-slate-400 hover:text-rose-600 transition-colors z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
    </button>
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto no-scrollbar">
        <a href="cashier_pos.php" class="flex items-center gap-3 px-3 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-all group overflow-hidden relative">
            <i class="fas fa-cash-register w-6 text-center text-lg"></i>
            <span class="sidebar-text">POS Terminal</span>
        </a>
        <a href="cashier_transactions.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden">
            <i class="fas fa-receipt w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
            <span class="sidebar-text">Transactions</span>
        </a>
        <a href="cashier_inventory.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden">
            <i class="fas fa-boxes w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
            <span class="sidebar-text">Inventory</span>
        </a>
    </nav>
    <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3 overflow-hidden">
        <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm shrink-0"><?= strtoupper(substr($cashier_name,0,1)) ?></div>
        <div class="sidebar-text overflow-hidden">
            <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p>
            <form action="/controllers/logout.php" method="POST"><button class="text-xs text-rose-500 font-medium hover:underline">Sign Out</button></form>
        </div>
    </div>
</aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden bg-[#f9fafb]">
        <header class="h-20 glass-header flex items-center justify-between px-6 z-20 absolute top-0 w-full">
            <h2 class="text-xl font-bold text-slate-800">Menu</h2>
            <div class="flex gap-3">
                <div class="relative"><select id="categoryFilter" class="appearance-none bg-white border border-slate-200 text-slate-600 py-2.5 pl-4 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-200 text-sm font-medium shadow-sm cursor-pointer hover:border-rose-300 transition-colors"><option value="">All Categories</option><?php while ($cat = $categories->fetch_assoc()): ?><option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']); ?></option><?php endwhile; ?></select></div>
                <div class="relative"><input id="productSearch" type="search" placeholder="Search products..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-200 w-64 shadow-sm hover:border-rose-300 transition-colors"><svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 pt-24 custom-scroll" id="productContainer">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-5" id="productGrid">
                <?php while ($p = $products->fetch_assoc()): $variations = getVariations($conn, $p['product_id']); $total_stock = intval($p['total_stock']); ?>
                <div class="group bg-white border border-slate-100 rounded-2xl p-4 flex flex-col justify-between transition-all duration-300 hover:shadow-xl hover:-translate-y-1 cursor-pointer <?= ($total_stock <= 0 ? 'opacity-60 grayscale' : '') ?>" onclick="<?= ($total_stock > 0 ? "openProductModal(this)" : "") ?>" data-id="<?= $p['product_id'] ?>" data-name="<?= htmlspecialchars($p['product_name']) ?>" data-price="<?= $p['price'] ?>" data-category="<?= htmlspecialchars($p['category_name']) ?>" data-variations='<?= htmlspecialchars(json_encode($variations), ENT_QUOTES) ?>' style="display: none;"> 
                    <div><div class="flex justify-between items-start mb-2"><span class="text-[10px] font-bold tracking-wide text-slate-400 uppercase bg-slate-100 px-2 py-1 rounded-md"><?= htmlspecialchars($p['category_name']) ?></span></div><h3 class="font-bold text-slate-800 leading-tight mb-1 line-clamp-2 min-h-[2.5rem] group-hover:text-rose-600 transition-colors"><?= htmlspecialchars($p['product_name']) ?></h3><div class="text-lg font-bold text-rose-600">₱<?= number_format($p['price'],2) ?></div></div>
                    <button class="w-full mt-4 py-2 rounded-xl font-semibold text-sm transition-colors active:scale-95 duration-150 <?= ($total_stock > 0 ? 'bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white' : 'bg-slate-100 text-slate-400 cursor-not-allowed') ?>"><?= $total_stock > 0 ? 'Add to Cart' : 'Out of Stock' ?></button>
                </div>
                <?php endwhile; ?>
            </div>
            <div id="productPagination" class="flex justify-center gap-2 mt-8 pb-4"></div>
        </div>
    </main>

    <!-- CART SIDEBAR -->
    <aside class="w-[400px] bg-white border-l border-slate-100 flex flex-col shadow-2xl z-40 shrink-0">
        <div class="h-20 glass-header flex items-center px-6 justify-between"><h3 class="font-bold text-lg text-slate-800 flex items-center gap-2"><i class="fas fa-shopping-bag text-rose-500"></i> Current Order</h3><span class="text-xs bg-rose-100 text-rose-600 px-2 py-1 rounded-full font-bold" id="itemCountBadge">0 items</span></div>
        <div class="flex-1 overflow-y-auto p-6 space-y-3 bg-[#f8f9fa]" id="cartList"></div>
        <div class="p-6 bg-white border-t border-slate-100 shadow-[0_-10px_40px_rgba(0,0,0,0.03)] z-10">
            <div class="mb-4 space-y-2">
                <div class="flex justify-between items-center text-sm"><span class="font-medium text-slate-500">Subtotal</span><span id="cartSubtotal" class="font-bold text-slate-700">₱0.00</span></div>
                <div class="flex justify-between items-center text-rose-500 text-sm hidden" id="discountRow"><span class="font-medium">Global Discount</span><span id="cartDiscountDisp" class="font-bold">-₱0.00</span></div>
                <div class="flex justify-between items-end border-t border-dashed border-slate-200 pt-3 mt-2"><div><span class="text-xs font-bold text-slate-400 uppercase tracking-wide">Total</span><button type="button" onclick="openGlobalDiscountModal()" class="block text-[10px] mt-1 text-rose-600 hover:text-rose-800 font-bold bg-rose-50 hover:bg-rose-100 px-2 py-1 rounded transition-colors">+ Global Discount</button></div><span id="cartTotal" class="text-3xl font-extrabold text-slate-800 tracking-tight">₱0.00</span></div>
            </div>
            <form method="POST" onsubmit="return handleCartSubmit()" class="space-y-4">
                <input type="hidden" name="cart_data" id="cartData"><input type="hidden" name="total" id="totalField"><input type="hidden" name="discount_amount" id="discountField" value="0">
                <div class="grid grid-cols-2 gap-3">
                    <div id="paymentMethodDiv" class="col-span-1 transition-all duration-300 ease-in-out"><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment</label><select name="payment_method_id" id="paymentMethodSelect" onchange="toggleCashFields()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-rose-200 outline-none cursor-pointer"><?php $payments->data_seek(0); while ($pay = $payments->fetch_assoc()): $isCash = (strcasecmp($pay['payment_method_name'], 'Cash') === 0); ?><option value="<?= $pay['payment_method_id'] ?>" <?= $isCash ? 'selected' : '' ?>><?= htmlspecialchars($pay['payment_method_name']) ?></option><?php endwhile; ?></select></div>
                    <div id="cashInputContainerWrapper" class="col-span-1 transition-all duration-300 ease-in-out overflow-hidden" style="max-height: 80px; opacity: 1;"><div id="cashInputContainer"><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cash Given</label><input type="number" name="cash_given" id="cashGiven" step="0.01" placeholder="0.00" oninput="updateChange()" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-right focus:ring-2 focus:ring-rose-200 outline-none transition-shadow"></div></div>
                </div>
                <div id="cartChange" class="min-h-[1.5rem] transition-all duration-300"></div>
                <div class="flex gap-3 pt-2"><button type="button" id="openReturnModal" class="p-3.5 rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors active:scale-95" title="Return Item"><i class="fas fa-undo"></i></button><button type="submit" name="checkout" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl py-3.5 shadow-lg shadow-rose-200/50 transition-all transform active:scale-[0.98] hover:-translate-y-0.5">Process Payment</button></div>
            </form>
        </div>
    </aside>

    <!-- PRODUCT OPTION MODAL -->
    <div id="optionModal" class="fixed inset-0 z-50 flex items-center justify-center invisible opacity-0 modal-backdrop"><div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div><div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl relative z-10 p-6 transform scale-95 opacity-0 modal-content" id="optionModalContent"><div class="flex justify-between items-center mb-5"><h4 class="text-lg font-bold text-slate-800" id="modalProductName">Select Options</h4><button onclick="closeModal()" class="text-slate-400 hover:text-rose-500 transition-colors text-2xl leading-none">&times;</button></div><div class="space-y-5"><div id="sizeDiv"><label class="text-xs font-bold text-slate-400 uppercase mb-2 block">Size</label><div id="sizeOptions" class="flex flex-wrap gap-2"></div></div><div id="colorDiv"><label class="text-xs font-bold text-slate-400 uppercase mb-2 block">Color</label><div id="colorOptions" class="flex flex-wrap gap-2"></div></div></div><div class="flex gap-3 mt-8"><button onclick="closeModal()" class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition active:scale-95">Cancel</button><button id="confirmAdd" class="flex-1 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-200 transition active:scale-95">Add Item</button></div></div></div>

    <!-- GLOBAL DISCOUNT MODAL -->
    <div id="discountModal" class="fixed inset-0 z-50 flex items-center justify-center invisible opacity-0 modal-backdrop"><div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeDiscountModal()"></div><div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl relative z-10 p-6 transform scale-95 opacity-0 modal-content" id="discountModalContent"><div class="flex justify-between items-center mb-5"><h4 class="text-lg font-bold text-slate-800">Global Discount</h4><button onclick="closeDiscountModal()" class="text-slate-400 hover:text-rose-500 transition text-2xl leading-none">&times;</button></div><div class="space-y-4"><div><label class="text-xs font-bold text-slate-400 uppercase mb-1 block">Value</label><input type="number" id="discountValue" placeholder="0" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold text-lg outline-none focus:ring-2 focus:ring-rose-200 transition-shadow"></div><div class="flex bg-slate-100 p-1 rounded-xl"><button type="button" id="btnTypePercent" onclick="toggleDiscountType('percent')" class="flex-1 py-2 rounded-lg text-sm font-bold bg-white text-rose-600 shadow-sm transition-all">Percent (%)</button><button type="button" id="btnTypeFixed" onclick="toggleDiscountType('fixed')" class="flex-1 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 transition-all">Fixed (₱)</button></div></div><div class="flex gap-3 mt-6"><button onclick="applyDiscount(0)" class="px-4 py-3 rounded-xl border border-slate-200 text-slate-500 font-bold hover:bg-slate-50 active:scale-95 transition">Reset</button><button onclick="confirmDiscount()" class="flex-1 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-200 active:scale-95 transition">Apply</button></div></div></div>

    <!-- ITEM DISCOUNT MODAL -->
    <div id="itemDiscountModal" class="fixed inset-0 z-50 flex items-center justify-center invisible opacity-0 modal-backdrop"><div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeItemDiscountModal()"></div><div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl relative z-10 p-6 transform scale-95 opacity-0 modal-content" id="itemDiscountModalContent"><div class="flex justify-between items-center mb-5"><h4 class="text-lg font-bold text-slate-800">Item Discount</h4><button onclick="closeItemDiscountModal()" class="text-slate-400 hover:text-rose-500 transition text-2xl leading-none">&times;</button></div><p class="text-sm text-slate-500 mb-4">Set percentage discount for <span id="itemDiscountName" class="font-bold text-slate-700"></span></p><div class="space-y-4"><div><label class="text-xs font-bold text-slate-400 uppercase mb-1 block">Discount Percent (%)</label><input type="number" id="itemDiscountPercent" placeholder="0" max="100" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold text-lg outline-none focus:ring-2 focus:ring-rose-200 transition-shadow"></div></div><div class="flex gap-3 mt-6"><button onclick="applyItemDiscount(0)" class="px-4 py-3 rounded-xl border border-slate-200 text-slate-500 font-bold hover:bg-slate-50 active:scale-95 transition">Remove</button><button onclick="confirmItemDiscount()" class="flex-1 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-200 active:scale-95 transition">Save</button></div></div></div>
    
    <!-- SECURITY CHECK MODAL -->
    <div id="securityModal" class="fixed inset-0 z-[60] flex items-center justify-center invisible opacity-0 modal-backdrop">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl relative z-10 p-6 transform scale-95 opacity-0 modal-content" id="securityModalContent">
            <div class="text-center mb-4">
                <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-3 text-xl"><i class="fas fa-user-shield"></i></div>
                <h4 class="text-lg font-bold text-slate-800">Admin Authorization</h4>
                <p class="text-xs text-slate-500">Manager override required for refund.</p>
            </div>
            
            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Admin Username</label>
            <input type="text" id="securityUsername" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-lg outline-none focus:ring-2 focus:ring-rose-200 mb-3" placeholder="Enter Username">
            
            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Admin Password</label>
            <input type="password" id="securityPassword" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-lg outline-none focus:ring-2 focus:ring-rose-200 mb-4" placeholder="••••••">
            
            <div class="flex gap-3">
                <button onclick="closeSecurityModal()" class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50">Cancel</button>
                <button onclick="verifyAndSubmitRefund()" class="flex-1 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg">Verify</button>
            </div>
        </div>
    </div>

    <!-- RETURN MODAL -->
    <div id="returnModalBackdrop" class="fixed inset-0 z-50 bg-slate-900/30 backdrop-blur-sm invisible opacity-0 transition-all duration-300"></div>
    <div id="returnModal" class="fixed inset-y-0 right-0 z-50 w-[500px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col">
        <div class="h-20 flex items-center justify-between px-6 border-b border-slate-100 bg-rose-50/50">
            <div>
                <h4 class="text-lg font-bold text-rose-800">Process Return</h4>
                <p class="text-xs text-rose-500">Look up order and select items</p>
            </div>
            <button id="closeReturnModal" class="text-slate-400 hover:text-rose-600 transition text-2xl">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 transition-all focus-within:ring-2 ring-rose-100">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Find Order</label>
                <div class="flex gap-2">
                    <input type="text" id="searchOrderId" class="flex-1 bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-rose-400 transition" placeholder="Enter Transaction ID...">
                    <button onclick="fetchOrderItems()" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition shadow-lg active:scale-95">Find</button>
                </div>
                <p id="orderMsg" class="text-xs mt-2 font-medium hidden"></p>
            </div>
            <div id="returnItemsContainer" class="hidden space-y-4 fade-in-up">
                <h5 class="font-bold text-slate-700">Select Items to Return</h5>
                <form id="refundForm" class="space-y-3">
                    <input type="hidden" name="order_id" id="finalOrderId">
                    <div id="itemsList" class="space-y-3"></div>
                </form>
            </div>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50">
            <button type="button" onclick="openSecurityModal()" id="submitRefundBtn" class="w-full bg-rose-600 text-white font-bold py-3 rounded-xl hover:bg-rose-700 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed active:scale-95" disabled>Confirm Refund</button>
        </div>
    </div>

    <script>
        let cart = []; let currentProduct = null; let activeDiscount = { type: 'percent', value: 0 }; let activeItemDiscountIndex = null; let modalState = { variations: [], selectedSize: null, selectedColor: null }; const itemsPerPage = 12; let currentPage = 1;
        const productCards = Array.from(document.querySelectorAll('.group[data-id]'));
        const searchInput = document.getElementById('productSearch');
        const catFilter = document.getElementById('categoryFilter');
        const paginationEl = document.getElementById('productPagination');
        
        function renderProducts() {
            if(!paginationEl) return;
            const q = searchInput.value.toLowerCase().trim();
            const cat = catFilter.value;
            const filtered = productCards.filter(el => {
                const name = (el.dataset.name || '').toLowerCase();
                const category = el.dataset.category || '';
                return name.includes(q) && (!cat || category === cat);
            });
            const totalPages = Math.ceil(filtered.length / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = 1;
            productCards.forEach(el => { el.style.display = 'none'; el.style.opacity = '0'; el.style.animation = 'none'; });
            const pageItems = filtered.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);
            pageItems.forEach((el, i) => { el.style.display = 'flex'; void el.offsetWidth; el.style.animation = `fadeInUp 0.4s ease-out ${i * 0.05}s forwards`; });
            paginationEl.innerHTML = '';
            if (totalPages > 1) {
                for (let i = 1; i <= totalPages; i++) {
                    const b = document.createElement('button');
                    b.textContent = i;
                    b.className = `w-8 h-8 rounded-lg text-sm font-bold transition-all ${i === currentPage ? 'bg-rose-600 text-white shadow-md scale-110' : 'bg-white text-slate-600 hover:bg-slate-100 border'}`;
                    b.onclick = () => { currentPage = i; renderProducts(); document.getElementById('productGrid').scrollIntoView({ behavior: 'smooth', block: 'start' }); };
                    paginationEl.appendChild(b);
                }
            }
        }
        if(searchInput) searchInput.addEventListener('input', () => { currentPage = 1; renderProducts(); });
        if(catFilter) catFilter.addEventListener('change', () => { currentPage = 1; renderProducts(); });

        /* --- UPDATED: RENDER CART WITH BIG BUTTON & STOCK DISPLAY --- */
        function renderCart() {
            const list = document.getElementById('cartList');
            if (!list) return;
            list.innerHTML = '';
            let subtotal = 0;
            let totalQty = 0;

            cart.forEach((item, i) => {
                const origPrice = parseFloat(item.original_price);
                const discountPct = parseFloat(item.discount_percent || 0);
                const discountedPrice = origPrice - (origPrice * (discountPct / 100));
                
                item.price = discountedPrice;
                item.discount_amount = (origPrice * (discountPct / 100));
                
                subtotal += item.price * item.quantity;
                totalQty += item.quantity;
                
                // Calculate stock remaining in cart context
                const remaining = item.max_stock - item.quantity;

                const div = document.createElement('div');
                div.className = "bg-white p-3 rounded-xl border border-slate-100 flex justify-between items-start shadow-sm relative overflow-hidden group hover:border-rose-200 transition-colors";
                div.style.animation = "fadeIn 0.3s ease-out";

                let priceHtml = `<div class="text-rose-600 font-bold mt-1">₱${(item.price*item.quantity).toFixed(2)}</div>`;
                if (discountPct > 0) {
                    priceHtml = `<div class="mt-1 flex flex-col"><span class="text-[10px] text-slate-400 line-through">₱${(origPrice*item.quantity).toFixed(2)}</span><span class="text-rose-600 font-bold">₱${(item.price*item.quantity).toFixed(2)} <span class="text-[10px] bg-rose-100 text-rose-600 px-1 rounded">-${discountPct}%</span></span></div>`;
                }

                div.innerHTML = `
                    <div class="flex-1 min-w-0 pr-2">
                        <div class="font-bold text-sm truncate text-slate-800 mb-1.5">${item.name}</div>
                        
                        <!-- BIG DISCOUNT BUTTON -->
                        <button type="button" onclick="openItemDiscount(${i})" class="w-full mb-2 bg-rose-500 hover:bg-rose-600 text-white text-xs font-bold py-2 px-3 rounded-lg shadow-sm flex items-center justify-center gap-2 transition-all active:scale-95">
                            <i class="fas fa-tag"></i> ${discountPct > 0 ? 'Edit Discount' : 'Apply Discount'}
                        </button>

                        <div class="text-xs text-slate-500 font-medium">
                            <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px]">${item.size||'-'}</span> 
                            <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px] ml-1">${item.color||'-'}</span>
                            <!-- STOCK DISPLAY -->
                            <span class="text-xs font-bold ${remaining < 5 ? 'text-rose-600' : 'text-slate-400'} ml-1">(${remaining} left)</span>
                        </div>
                        ${priceHtml}
                    </div>
                    <div class="flex flex-col items-end gap-2 z-10 pt-1">
                        <div class="flex items-center bg-slate-50 border border-slate-200 rounded-lg">
                            <button type="button" onclick="upd(${i},-1)" class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-l-lg transition">-</button>
                            <span class="text-xs font-bold w-6 text-center text-slate-700">${item.quantity}</span>
                            <button type="button" onclick="upd(${i},1)" class="w-7 h-7 flex items-center justify-center text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-r-lg transition">+</button>
                        </div>
                        <button type="button" onclick="rm(${i})" class="text-[10px] text-slate-400 hover:text-rose-500 font-medium transition-colors">Remove</button>
                    </div>`;
                
                list.appendChild(div);
            });

            if (cart.length === 0) {
                list.innerHTML = '<div class="h-full flex flex-col items-center justify-center text-slate-300 space-y-2 animate-pulse"><i class="fas fa-shopping-basket text-4xl"></i><p class="text-sm font-medium">Cart is empty</p></div>';
            }

            document.getElementById('itemCountBadge').textContent = `${totalQty} items`;
            let globalDiscountAmount = 0; 
            if (activeDiscount.type === 'percent') globalDiscountAmount = subtotal * (activeDiscount.value / 100); 
            else globalDiscountAmount = activeDiscount.value; 
            
            if (globalDiscountAmount > subtotal) globalDiscountAmount = subtotal; 
            const finalTotal = subtotal - globalDiscountAmount; 
            
            document.getElementById('cartSubtotal').textContent = '₱' + subtotal.toFixed(2); 
            document.getElementById('cartTotal').textContent = '₱' + finalTotal.toFixed(2); 
            
            const discRow = document.getElementById('discountRow'); 
            if (globalDiscountAmount > 0) { 
                discRow.classList.remove('hidden'); 
                discRow.style.display = 'flex'; 
                document.getElementById('cartDiscountDisp').textContent = '-₱' + globalDiscountAmount.toFixed(2); 
            } else { 
                discRow.classList.add('hidden'); 
            } 
            
            document.getElementById('totalField').value = finalTotal.toFixed(2); 
            document.getElementById('discountField').value = globalDiscountAmount.toFixed(2); 
            toggleCashFields(); 
            updateChange(); 
        }

        /* --- UPDATED: UPD FUNCTION RESPECTS MAX STOCK --- */
        window.upd = (i, d) => { 
            const item = cart[i];
            if (d > 0 && item.quantity >= item.max_stock) return alert("Max stock reached for this item.");
            item.quantity += d; 
            if (item.quantity < 1) item.quantity = 1; 
            renderCart(); 
        }

        window.rm = (i) => { cart.splice(i, 1); renderCart(); }
        window.prepareCartData = () => { document.getElementById('cartData').value = JSON.stringify(cart); }
        window.updateChange = () => { const t = parseFloat(document.getElementById('totalField').value) || 0; const cInput = document.getElementById('cashGiven'); if(!cInput) return; const c = parseFloat(cInput.value) || 0; const d = c - t; const changeEl = document.getElementById('cartChange'); if(changeEl.style.display !== 'none') changeEl.innerHTML = c > 0 ? (d >= -0.01 ? `<div class="text-green-700 font-bold text-sm bg-green-50 border border-green-100 p-3 rounded-xl flex justify-between items-center shadow-sm"><span>Change</span> <span>₱${d.toFixed(2)}</span></div>` : `<div class="text-rose-700 font-bold text-sm bg-rose-50 border border-rose-100 p-3 rounded-xl flex justify-between items-center shadow-sm"><span>Short</span> <span>₱${Math.abs(d).toFixed(2)}</span></div>`) : ''; }

        function openModalLogic(modal, content) { modal.classList.remove('invisible', 'opacity-0'); requestAnimationFrame(() => { content.classList.remove('opacity-0', 'scale-95'); content.classList.add('opacity-100', 'scale-100'); }); }
        function closeModalLogic(modal, content) { content.classList.remove('opacity-100', 'scale-100'); content.classList.add('opacity-0', 'scale-95'); modal.classList.add('opacity-0'); setTimeout(() => { modal.classList.add('invisible'); }, 300); }

        const modal = document.getElementById('optionModal'); const modalContent = document.getElementById('optionModalContent');
        window.openProductModal = (card) => { currentProduct = { id: card.dataset.id, name: card.dataset.name, price: parseFloat(card.dataset.price) }; modalState.variations = JSON.parse(card.dataset.variations || '[]'); modalState.selectedSize = null; modalState.selectedColor = null; document.getElementById('modalProductName').textContent = currentProduct.name; renderOptions(); openModalLogic(modal, modalContent); }
        window.closeModal = () => { closeModalLogic(modal, modalContent); }
        function renderOptions() {
            const uniqueSizes = [...new Set(modalState.variations.map(v => v.size))];
            const uniqueColors = [...new Set(modalState.variations.map(v => v.color))];
            const sDiv = document.getElementById('sizeOptions');
            const cDiv = document.getElementById('colorOptions');
            sDiv.innerHTML = '';
            cDiv.innerHTML = '';
            function getRemainingStock(size, color) { const stockObj = modalState.variations.find(v => v.size === size && v.color === color); if (!stockObj) return 0; const inCart = cart.find(i => i.product_id === currentProduct.id && i.size === size && i.color === color); return stockObj.qty - (inCart ? inCart.quantity : 0); }
            if (uniqueSizes.length > 0) { document.getElementById('sizeDiv').style.display = 'block'; uniqueSizes.forEach(size => { const btn = document.createElement('button'); btn.textContent = size; let remStock = null; if (modalState.selectedColor !== null) { remStock = getRemainingStock(size, modalState.selectedColor); } else { remStock = Math.max(...modalState.variations.filter(v => v.size === size).map(v => getRemainingStock(size, v.color))); } btn.title = `Remaining: ${remStock}`; const isAvailable = modalState.variations.some(v => v.size === size && getRemainingStock(size, v.color) > 0 && (modalState.selectedColor === null || v.color === modalState.selectedColor)); if (!isAvailable) { btn.className = 'px-4 py-2 border border-slate-100 rounded-lg text-sm text-slate-300 cursor-not-allowed bg-slate-50'; btn.disabled = true; } else if (modalState.selectedSize === size) { btn.className = 'px-4 py-2 border rounded-lg text-sm font-bold bg-rose-600 text-white border-rose-600 shadow-md transform scale-105 transition-all'; } else { btn.className = 'px-4 py-2 border border-slate-200 rounded-lg text-sm font-medium hover:border-rose-400 hover:text-rose-600 transition text-slate-700'; btn.onclick = () => { modalState.selectedSize = (modalState.selectedSize === size) ? null : size; renderOptions(); }; } btn.innerHTML += ` <span class="text-xs text-slate-400">(${remStock})</span>`; sDiv.appendChild(btn); }); } else { document.getElementById('sizeDiv').style.display = 'none'; modalState.selectedSize = 'Free Size'; }
            if (uniqueColors.length > 0) { document.getElementById('colorDiv').style.display = 'block'; uniqueColors.forEach(color => { const btn = document.createElement('button'); btn.textContent = color; let remStock = null; if (modalState.selectedSize !== null) { remStock = getRemainingStock(modalState.selectedSize, color); } else { remStock = Math.max(...modalState.variations.filter(v => v.color === color).map(v => getRemainingStock(v.size, color))); } btn.title = `Remaining: ${remStock}`; const isAvailable = modalState.variations.some(v => v.color === color && getRemainingStock(v.size, color) > 0 && (modalState.selectedSize === null || v.size === modalState.selectedSize)); if (!isAvailable) { btn.className = 'px-4 py-2 border border-slate-100 rounded-lg text-sm text-slate-300 cursor-not-allowed bg-slate-50'; btn.disabled = true; } else if (modalState.selectedColor === color) { btn.className = 'px-4 py-2 border rounded-lg text-sm font-bold bg-rose-600 text-white border-rose-600 shadow-md transform scale-105 transition-all'; } else { btn.className = 'px-4 py-2 border border-slate-200 rounded-lg text-sm font-medium hover:border-rose-400 hover:text-rose-600 transition text-slate-700'; btn.onclick = () => { modalState.selectedColor = (modalState.selectedColor === color) ? null : color; renderOptions(); }; } btn.innerHTML += ` <span class="text-xs text-slate-400">(${remStock})</span>`; cDiv.appendChild(btn); }); } else { document.getElementById('colorDiv').style.display = 'none'; modalState.selectedColor = 'Standard'; }
            let infoDiv = document.getElementById('stockInfoDiv'); if (!infoDiv) { infoDiv = document.createElement('div'); infoDiv.id = 'stockInfoDiv'; infoDiv.className = 'mt-3 text-xs font-bold text-slate-500'; sDiv.parentNode.parentNode.insertBefore(infoDiv, sDiv.parentNode.nextSibling); }
            let stockMsg = ''; if (modalState.selectedSize && modalState.selectedColor) { const rem = getRemainingStock(modalState.selectedSize, modalState.selectedColor); stockMsg = `Remaining stock: <span class="text-rose-600">${rem}</span>`; } else { stockMsg = 'Select size and color to see stock'; } infoDiv.innerHTML = stockMsg;
        }

        /* --- UPDATED: SAVE MAX STOCK WHEN ADDING --- */
        document.getElementById('confirmAdd').onclick = () => { 
            const hasSizes = document.getElementById('sizeDiv').style.display !== 'none'; 
            const hasColors = document.getElementById('colorDiv').style.display !== 'none'; 
            if (hasSizes && !modalState.selectedSize) return alert("Please select a size"); 
            if (hasColors && !modalState.selectedColor) return alert("Please select a color"); 
            
            const size = modalState.selectedSize || 'Free Size'; 
            const color = modalState.selectedColor || 'Standard'; 
            
            const stockItem = modalState.variations.find(v => v.size === size && v.color === color); 
            if (!stockItem || stockItem.qty <= 0) return alert("Selected combination is out of stock."); 
            
            const inCart = cart.find(i => i.product_id === currentProduct.id && i.size === size && i.color === color); 
            const remaining = stockItem.qty - (inCart ? inCart.quantity : 0); 
            if (remaining <= 0) return alert("No more stock available for this combination."); 
            
            if (inCart) { 
                if (inCart.quantity + 1 > stockItem.qty) return alert("Cannot add more (Stock limit reached)"); 
                inCart.quantity++; 
                // inCart.max_stock is already set
            } else { 
                cart.push({ 
                    product_id: currentProduct.id, 
                    name: currentProduct.name, 
                    price: currentProduct.price, 
                    original_price: currentProduct.price, 
                    discount_percent: 0, 
                    quantity: 1, 
                    size, 
                    color,
                    max_stock: stockItem.qty // Save max stock here
                }); 
            } 
            renderCart(); 
            closeModal(); 
        };

        const dModal = document.getElementById('discountModal'); const dContent = document.getElementById('discountModalContent');
        window.openGlobalDiscountModal = () => { document.getElementById('discountValue').value = activeDiscount.value > 0 ? activeDiscount.value : ''; toggleDiscountType(activeDiscount.type); openModalLogic(dModal, dContent); }
        window.closeDiscountModal = () => { closeModalLogic(dModal, dContent); }
        window.toggleDiscountType = (type) => { const btnP = document.getElementById('btnTypePercent'); const btnF = document.getElementById('btnTypeFixed'); const input = document.getElementById('discountValue'); if (type === 'percent') { btnP.className = "flex-1 py-2 rounded-lg text-sm font-bold bg-white text-rose-600 shadow-sm transition-all"; btnF.className = "flex-1 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-200 transition-all"; input.placeholder = "e.g., 10"; } else { btnF.className = "flex-1 py-2 rounded-lg text-sm font-bold bg-white text-rose-600 shadow-sm transition-all"; btnP.className = "flex-1 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-200 transition-all"; input.placeholder = "e.g., 100.00"; } input.dataset.type = type; }
        window.confirmDiscount = () => { const val = parseFloat(document.getElementById('discountValue').value) || 0; const type = document.getElementById('discountValue').dataset.type || 'percent'; activeDiscount = { type: type, value: val }; renderCart(); closeDiscountModal(); }
        window.applyDiscount = (resetVal) => { if (resetVal === 0) { document.getElementById('discountValue').value = ''; activeDiscount = { type: 'percent', value: 0 }; } renderCart(); closeDiscountModal(); }

        const idModal = document.getElementById('itemDiscountModal'); const idContent = document.getElementById('itemDiscountModalContent');
        window.openItemDiscount = (index) => { activeItemDiscountIndex = index; document.getElementById('itemDiscountName').textContent = cart[index].name; document.getElementById('itemDiscountPercent').value = cart[index].discount_percent || ''; openModalLogic(idModal, idContent); }
        window.closeItemDiscountModal = () => { closeModalLogic(idModal, idContent); }
        window.confirmItemDiscount = () => { const val = parseFloat(document.getElementById('itemDiscountPercent').value) || 0; if(val < 0 || val > 100) return alert("Invalid percentage"); cart[activeItemDiscountIndex].discount_percent = val; renderCart(); closeItemDiscountModal(); }
        window.applyItemDiscount = (val) => { cart[activeItemDiscountIndex].discount_percent = 0; renderCart(); closeItemDiscountModal(); }

        function toggleCashFields() { const select = document.getElementById('paymentMethodSelect'); if (!select) return; const isGcash = select.options[select.selectedIndex].text.toLowerCase().includes('gcash'); const wrapper = document.getElementById('cashInputContainerWrapper'); const payDiv = document.getElementById('paymentMethodDiv'); const cashInput = document.getElementById('cashGiven'); const changeDiv = document.getElementById('cartChange'); const total = document.getElementById('totalField').value || "0.00"; if (isGcash) { wrapper.style.maxHeight = '0px'; wrapper.style.opacity = '0'; wrapper.style.marginTop = '0'; payDiv.classList.remove('col-span-1'); payDiv.classList.add('col-span-2'); cashInput.removeAttribute('required'); cashInput.value = total; changeDiv.style.opacity = '0'; setTimeout(() => changeDiv.style.display = 'none', 300); } else { wrapper.style.maxHeight = '80px'; wrapper.style.opacity = '1'; payDiv.classList.remove('col-span-2'); payDiv.classList.add('col-span-1'); cashInput.setAttribute('required', 'required'); if(cashInput.value === total) cashInput.value = ''; changeDiv.style.display = 'block'; setTimeout(() => changeDiv.style.opacity = '1', 10); updateChange(); } }
        window.handleCartSubmit = () => { prepareCartData(); const select = document.getElementById('paymentMethodSelect'); if (select && select.selectedIndex >= 0 && select.options[select.selectedIndex].text.toLowerCase().includes('gcash')) { document.getElementById('cashGiven').value = document.getElementById('totalField').value; } return true; }

        const rBackdrop = document.getElementById('returnModalBackdrop'); const rModal = document.getElementById('returnModal');
        document.getElementById('openReturnModal').onclick = () => { rBackdrop.classList.remove('invisible', 'opacity-0'); rModal.classList.remove('translate-x-full'); document.getElementById('searchOrderId').value = ''; document.getElementById('returnItemsContainer').classList.add('hidden'); document.getElementById('itemsList').innerHTML = ''; document.getElementById('submitRefundBtn').disabled = true; document.getElementById('orderMsg').classList.add('hidden'); }
        const closeReturn = () => { rModal.classList.add('translate-x-full'); rBackdrop.classList.add('opacity-0'); setTimeout(() => rBackdrop.classList.add('invisible'), 300); }
        document.getElementById('closeReturnModal').onclick = closeReturn; rBackdrop.onclick = closeReturn;
        
        async function fetchOrderItems() {
            // UPDATED: 'mode' is hardcoded now since dropdown is removed
            const mode = 'transaction'; 
            const inputVal = document.getElementById('searchOrderId').value.trim();
            const msg = document.getElementById('orderMsg');
            
            if (!inputVal) return alert("Enter Transaction ID");
            
            msg.textContent = "Searching...";
            msg.className = "text-xs mt-2 text-slate-500 block animate-pulse";
            let oid = inputVal;
            
            try {
                if (mode === 'transaction') {
                    const response = await fetch(`?action=get_order_id_by_transaction&tid=${encodeURIComponent(inputVal)}`);
                    const textData = await response.text();
                    let data;
                    try { data = JSON.parse(textData); } catch (e) { console.error(textData); return alert("Server Error"); }
                    
                    if (data.status === 'error') {
                        msg.textContent = "❌ " + data.message;
                        msg.className = "text-xs mt-2 text-rose-500 font-bold block";
                        document.getElementById('returnItemsContainer').classList.add('hidden');
                        return;
                    }
                    oid = data.order_id;
                }
                
                const response = await fetch(`?action=get_order_details&oid=${oid}`);
                const textData = await response.text();
                let data;
                try { data = JSON.parse(textData); } catch (e) { console.error(textData); return alert("Server Error"); }
                
                if (data.status === 'error') {
                    msg.textContent = "❌ " + data.message;
                    msg.className = "text-xs mt-2 text-rose-500 font-bold block";
                    document.getElementById('returnItemsContainer').classList.add('hidden');
                    return;
                }
                
                msg.textContent = "✅ Found Order #" + oid;
                msg.className = "text-xs mt-2 text-green-600 font-bold block";
                document.getElementById('finalOrderId').value = oid;
                document.getElementById('returnItemsContainer').classList.remove('hidden');
                
                const list = document.getElementById('itemsList');
                list.innerHTML = '';
                data.items.forEach(item => {
                    const row = document.createElement('div');
                    row.className = "group flex items-start gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-rose-400 cursor-pointer transition shadow-sm";
                    row.onclick = (e) => { if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT') { const cb = row.querySelector('.item-cb'); cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); } };
                    row.innerHTML = `<div class="pt-1"><input type="checkbox" class="w-5 h-5 text-rose-600 item-cb focus:ring-rose-500 rounded" data-stock="${item.stock_id}" data-price="${item.price}" data-pid="${item.product_id}" data-qty="${item.qty}"></div><div class="flex-1"><div class="flex justify-between"><h6 class="font-bold text-sm text-slate-800">${item.product_name}</h6><span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded">₱${parseFloat(item.price).toFixed(2)}</span></div><div class="flex gap-2 mt-2"><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">${item.size_name}</span><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">${item.color_name}</span><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">Qty: ${item.qty}</span></div><div class="qty-control hidden mt-3 pt-3 border-t border-dashed border-slate-100 flex flex-wrap items-center gap-2"><div class="flex items-center gap-2"><span class="text-xs font-bold text-rose-600">Return Qty:</span><input type="number" min="1" max="${item.qty}" value="1" class="return-qty w-12 border rounded text-center text-xs font-bold" onclick="event.stopPropagation()"></div><div class="flex items-center gap-2 flex-1"><span class="text-xs font-bold text-slate-500">Condition:</span><select class="return-action w-full border border-slate-300 rounded text-xs py-1" onclick="event.stopPropagation()"><option value="restock">✅ Sellable (Restock)</option><option value="damaged">❌ Damaged (Discard)</option></select></div></div></div>`;
                    list.appendChild(row);
                });
                document.querySelectorAll('.item-cb').forEach(cb => cb.addEventListener('change', validateRefund));
            } catch (err) { console.error(err); msg.textContent = "Network Error"; }
        }
        function validateRefund() { let any = false; document.querySelectorAll('.item-cb').forEach(box => { const qtyDiv = box.closest('.group').querySelector('.qty-control'); if (box.checked) { any = true; qtyDiv.classList.remove('hidden'); qtyDiv.classList.add('flex'); } else { qtyDiv.classList.add('hidden'); qtyDiv.classList.remove('flex'); } }); document.getElementById('submitRefundBtn').disabled = !any; }
        
        const sModal = document.getElementById('securityModal'); const sContent = document.getElementById('securityModalContent');
        window.openSecurityModal = () => { document.getElementById('securityUsername').value = ''; document.getElementById('securityPassword').value = ''; openModalLogic(sModal, sContent); }
        window.closeSecurityModal = () => { closeModalLogic(sModal, sContent); }

        async function verifyAndSubmitRefund() {
            const user = document.getElementById('securityUsername').value;
            const pwd = document.getElementById('securityPassword').value; 
            
            if(!user || !pwd) return alert("Please enter Admin credentials");
            
            try {
                const res = await fetch('?action=verify_password', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({ username: user, password: pwd }) 
                });
                const data = await res.json();
                
                if(data.status !== 'success') return alert(data.message);
                
                closeSecurityModal();
                const items = []; const boxes = document.querySelectorAll('.item-cb:checked');
                for (const box of boxes) { const row = box.closest('.group'); const rQty = parseInt(row.querySelector('.return-qty').value); const rAction = row.querySelector('.return-action').value; const maxQty = parseInt(box.dataset.qty); if (rQty > maxQty || rQty <= 0) return alert("Invalid quantity"); items.push({ stock_id: box.dataset.stock, product_id: box.dataset.pid, qty: maxQty, refund_qty: rQty, price: box.dataset.price, action: rAction }); }
                if (!items.length) return;
                
                const refRes = await fetch('?action=process_refund', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ order_id: document.getElementById('finalOrderId').value, items: items }) });
                const refData = await refRes.json();
                if (refData.status === 'success') { alert(refData.message); closeReturn(); location.reload(); } else alert("Error: " + refData.message);
            } catch(e) { console.error(e); alert("System Error"); }
        }

        window.addEventListener('DOMContentLoaded', () => { renderProducts(); renderCart(); toggleCashFields(); });
    </script>
</body>
</html>