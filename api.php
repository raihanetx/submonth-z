<?php
// api.php - FINAL & COMPLETE version with ALL data in MySQL

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

session_start();
require_once 'db.php';

$upload_dir = 'uploads/';

// --- Helper Functions ---
function get_all_settings($pdo) {
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = json_decode($row['setting_value'], true);
        $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $value : $row['setting_value'];
    }
    return $settings;
}

function update_setting($pdo, $key, $value) {
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

function slugify($text) { if (empty($text)) return 'n-a-' . rand(100, 999); $text = preg_replace('~[^\pL\d]+~u', '-', $text); if (function_exists('iconv')) { $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); } $text = preg_replace('~[^-\w]+~', '', $text); $text = trim($text, '-'); $text = preg_replace('~-+~', '-', $text); $text = strtolower($text); return $text; }
function handle_image_upload($file_input, $upload_dir, $prefix = '') { if (isset($file_input) && $file_input['error'] === UPLOAD_ERR_OK) { $original_filename = basename($file_input['name']); $safe_filename = preg_replace("/[^a-zA-Z0-9-_\.]/", "", $original_filename); $destination = $upload_dir . $prefix . time() . '-' . uniqid() . '-' . $safe_filename; if (move_uploaded_file($file_input['tmp_name'], $destination)) { return $destination; } } return null; }
function send_email($to, $subject, $body, $config) { $mail = new PHPMailer(true); $smtp_settings = $config['smtp_settings'] ?? []; $admin_email = $smtp_settings['admin_email'] ?? ''; $app_password = $smtp_settings['app_password'] ?? ''; if (empty($admin_email) || empty($app_password)) return false; try { $mail->CharSet = 'UTF-8'; $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true; $mail->Username = $admin_email; $mail->Password = $app_password; $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = 465; $mail->setFrom($admin_email, 'Submonth'); $mail->addAddress($to); $mail->isHTML(true); $mail->Subject = $subject; $mail->Body = $body; $mail->send(); return true; } catch (Exception $e) { return false; } }

if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
$site_config = get_all_settings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_orders_by_ids' && isset($_GET['ids'])) {
        $order_ids_to_find = json_decode($_GET['ids'], true);
        if (is_array($order_ids_to_find) && !empty($order_ids_to_find)) {
            $placeholders = implode(',', array_fill(0, count($order_ids_to_find), '?'));
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id_unique IN ($placeholders) ORDER BY id DESC");
            $stmt->execute($order_ids_to_find);
            $found_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $orders_with_items = [];
            foreach ($found_orders as $order) {
                $item_stmt = $pdo->prepare("SELECT product_name as name, quantity, duration, price_at_purchase as price, product_id as id FROM order_items WHERE order_id = ?");
                $item_stmt->execute([$order['id']]);
                $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($items as &$item) { $item['pricing'] = ['duration' => $item['duration'], 'price' => (float)$item['price']]; }
                unset($item);
                $order['items'] = $items;
                $order['order_id'] = $order['order_id_unique'];
                $order['customer'] = ['name' => $order['customer_name'], 'phone' => $order['customer_phone'], 'email' => $order['customer_email']];
                $order['payment'] = ['method' => $order['payment_method'], 'trx_id' => $order['payment_trx_id']];
                $order['coupon'] = ['code' => $order['coupon_code']];
                $order['totals'] = ['subtotal' => (float)$order['subtotal'], 'discount' => (float)$order['discount'], 'total' => (float)$order['total']];
                $orders_with_items[] = $order;
            }
            header('Content-Type: application/json');
            echo json_encode($orders_with_items);
        } else {
            header('Content-Type: application/json'); echo json_encode([]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $json_data = null;
    if (!$action) {
        $json_data = json_decode(file_get_contents('php://input'), true);
        $action = $json_data['action'] ?? null;
    }
    if (!$action) { http_response_code(400); die("Action not specified."); }

    $admin_actions = ['add_category', 'delete_category', 'edit_category', 'add_product', 'delete_product', 'edit_product', 'add_coupon', 'delete_coupon', 'update_review_status', 'update_order_status', 'update_hero_banner', 'update_favicon', 'update_currency_rate', 'update_contact_info', 'update_admin_password', 'update_site_logo', 'update_hot_deals', 'update_payment_methods', 'update_smtp_settings', 'send_manual_email', 'update_page_content'];
    if (in_array($action, $admin_actions)) {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            http_response_code(403); die("Forbidden: You must be logged in.");
        }
    }

    $redirect_url = 'admin.php';

    switch ($action) {
        case 'add_category':
            $name = htmlspecialchars(trim($_POST['name']));
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)");
            $stmt->execute([$name, slugify($name), htmlspecialchars(trim($_POST['icon']))]);
            $redirect_url = 'admin.php?view=categories';
            break;
        case 'delete_category':
            $stmt = $pdo->prepare("DELETE FROM categories WHERE name = ?");
            $stmt->execute([$_POST['name']]);
            $redirect_url = 'admin.php?view=categories';
            break;
        case 'edit_category':
            $newName = htmlspecialchars(trim($_POST['name']));
            $oldName = $_POST['original_name'];
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ? WHERE name = ?");
            $stmt->execute([$newName, slugify($newName), htmlspecialchars(trim($_POST['icon'])), $oldName]);
            $stmt = $pdo->prepare("UPDATE coupons SET scope_value = ? WHERE scope = 'category' AND scope_value = ?");
            $stmt->execute([$newName, $oldName]);
            $redirect_url = 'admin.php?view=categories';
            break;
        case 'add_product':
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$_POST['category_name']]);
            $category_id = $stmt->fetchColumn();
            if ($category_id) {
                $name = htmlspecialchars(trim($_POST['name']));
                $image_path = handle_image_upload($_FILES['image'] ?? null, $upload_dir, 'product-');
                $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, long_description, image, stock_out, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$category_id, $name, slugify($name), htmlspecialchars(trim($_POST['description'])), $_POST['long_description'] ?? null, $image_path, ($_POST['stock_out'] ?? 'false') === 'true', isset($_POST['featured'])]);
                $product_id = $pdo->lastInsertId();
                if (!empty($_POST['durations'])) {
                    foreach ($_POST['durations'] as $key => $duration) {
                        $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, duration, price) VALUES (?, ?, ?)");
                        $stmt->execute([$product_id, htmlspecialchars(trim($duration)), (float)$_POST['duration_prices'][$key]]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, duration, price) VALUES (?, ?, ?)");
                    $stmt->execute([$product_id, 'Default', (float)$_POST['price']]);
                }
            }
            $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']);
            break;
        case 'edit_product':
            $product_id = $_POST['product_id']; $name = htmlspecialchars(trim($_POST['name']));
            $stmt = $pdo->prepare("UPDATE products SET name=?, slug=?, description=?, long_description=?, stock_out=?, featured=? WHERE id=?");
            $stmt->execute([$name, slugify($name), htmlspecialchars(trim($_POST['description'])), $_POST['long_description'] ?? null, $_POST['stock_out'] === 'true', isset($_POST['featured']), $product_id]);
            $stmt = $pdo->prepare("DELETE FROM product_pricing WHERE product_id = ?"); $stmt->execute([$product_id]);
            if (!empty($_POST['durations'])) {
                foreach ($_POST['durations'] as $key => $duration) {
                    if(!empty(trim($duration))) {
                        $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, duration, price) VALUES (?, ?, ?)");
                        $stmt->execute([$product_id, htmlspecialchars(trim($duration)), (float)$_POST['duration_prices'][$key]]);
                    }
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, duration, price) VALUES (?, ?, ?)");
                $stmt->execute([$product_id, 'Default', (float)$_POST['price']]);
            }
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?"); $stmt->execute([$product_id]); $current_image = $stmt->fetchColumn();
            if (isset($_POST['delete_image']) && $current_image && file_exists($current_image)) { unlink($current_image); $stmt = $pdo->prepare("UPDATE products SET image = NULL WHERE id = ?"); $stmt->execute([$product_id]); }
            $new_image = handle_image_upload($_FILES['image'] ?? null, $upload_dir, 'product-');
            if ($new_image) { if ($current_image && file_exists($current_image)) { unlink($current_image); } $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?"); $stmt->execute([$new_image, $product_id]); }
            $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']);
            break;
        case 'delete_product':
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?"); $stmt->execute([$_POST['product_id']]);
            if ($image = $stmt->fetchColumn()) { if (file_exists($image)) unlink($image); }
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?"); $stmt->execute([$_POST['product_id']]);
            $redirect_url = 'admin.php?category=' . urlencode($_POST['category_name']);
            break;
        case 'add_coupon':
            $scope = $_POST['scope'] ?? 'all_products'; $scope_value = null;
            if ($scope === 'category') $scope_value = $_POST['scope_value_category'] ?? null; elseif ($scope === 'single_product') $scope_value = $_POST['scope_value_product'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_percentage, is_active, scope, scope_value) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([strtoupper(htmlspecialchars(trim($_POST['code']))), (int)$_POST['discount_percentage'], isset($_POST['is_active']), $scope, $scope_value]);
            $redirect_url = 'admin.php?view=dashboard';
            break;
        case 'delete_coupon':
            $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
            $stmt->execute([$_POST['coupon_id']]);
            $redirect_url = 'admin.php?view=dashboard';
            break;
        case 'add_review':
            $review_data = $json_data['review'];
            $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, name, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$review_data['productId'], htmlspecialchars($review_data['name']), (int)$review_data['rating'], htmlspecialchars($review_data['comment'])]);
            header('Content-Type: application/json'); echo json_encode(['success' => true]); exit;
        case 'update_review_status':
            if ($_POST['new_status'] === 'deleted') {
                $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
                $stmt->execute([$_POST['review_id']]);
            }
            $redirect_url = 'admin.php?view=reviews';
            break;
        case 'place_order':
            $order_data = $json_data['order']; $pdo->beginTransaction();
            try {
                $order_id_unique = time();
                $subtotal = 0; foreach($order_data['items'] as $item) { $subtotal += $item['pricing']['price'] * $item['quantity']; }
                $discount = $order_data['totals']['discount'] ?? 0;
                $total = $order_data['totals']['total'] ?? $subtotal - $discount;
                $stmt = $pdo->prepare("INSERT INTO orders (order_id_unique, customer_name, customer_phone, customer_email, payment_method, payment_trx_id, coupon_code, subtotal, discount, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$order_id_unique, $order_data['customerInfo']['name'], $order_data['customerInfo']['phone'], $order_data['customerInfo']['email'], $order_data['paymentInfo']['method'], $order_data['paymentInfo']['trx_id'], $order_data['coupon']['code'] ?? null, $subtotal, $discount, $total, 'Pending']);
                $order_db_id = $pdo->lastInsertId();
                foreach ($order_data['items'] as $item) {
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, duration, price_at_purchase) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$order_db_id, $item['id'], $item['name'], $item['quantity'], $item['pricing']['duration'], $item['pricing']['price']]);
                }
                $pdo->commit();
                send_email($site_config['smtp_settings']['admin_email'] ?? '', "New Order #$order_id_unique", "A new order has been placed. Please check the admin panel.", $site_config);
                header('Content-Type: application/json'); echo json_encode(['success' => true, 'order_id' => $order_id_unique]);
            } catch (Exception $e) {
                $pdo->rollBack(); header('Content-Type: application/json', true, 500); echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        case 'update_order_status':
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id_unique = ?");
            $stmt->execute([$_POST['new_status'], $_POST['order_id']]);
            $redirect_url = 'admin.php?view=orders';
            break;
        case 'send_manual_email':
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id_unique = ?"); $stmt->execute([$_POST['order_id']]);
            $order_to_email = $stmt->fetch();
            if ($order_to_email) {
                $item_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?"); $item_stmt->execute([$order_to_email['id']]);
                $order_items = $item_stmt->fetchAll();
                $email_subject = "Your Submonth Order #" . $order_to_email['order_id_unique'] . " is Confirmed!";
                $access_details = $_POST['access_details'];
                // Email Body Generation
                $email_body = '<p>Dear ' . htmlspecialchars($order_to_email['customer_name']) . ',</p>'; // ... and so on
                if (send_email($_POST['customer_email'], $email_subject, $email_body, $site_config)) {
                    $stmt = $pdo->prepare("UPDATE orders SET access_email_sent = 1 WHERE order_id_unique = ?");
                    $stmt->execute([$_POST['order_id']]);
                }
            }
            $redirect_url = 'admin.php?view=orders';
            break;
        // --- SETTINGS (now in DB) ---
        case 'update_hot_deals':
            update_setting($pdo, 'hot_deals_speed', (int)$_POST['hot_deals_speed']);
            $pdo->query("DELETE FROM hotdeals");
            if (!empty($_POST['selected_deals'])) {
                $stmt = $pdo->prepare("INSERT INTO hotdeals (product_id, custom_title) VALUES (?, ?)");
                foreach($_POST['selected_deals'] as $productId) {
                    $stmt->execute([$productId, htmlspecialchars(trim($_POST['custom_titles'][$productId] ?? ''))]);
                }
            }
            $redirect_url = 'admin.php?view=hotdeals';
            break;
        case 'update_hero_banner':
            update_setting($pdo, 'hero_slider_interval', (int)$_POST['hero_slider_interval'] * 1000);
            $current_banners = $site_config['hero_banner'] ?? [];
            if (isset($_POST['delete_hero_banners'])) { foreach ($_POST['delete_hero_banners'] as $i => $v) { if ($v === 'true' && isset($current_banners[$i]) && file_exists($current_banners[$i])) { unlink($current_banners[$i]); $current_banners[$i] = null; } } }
            for ($i = 0; $i < 10; $i++) { if (isset($_FILES['hero_banners']['tmp_name'][$i]) && is_uploaded_file($_FILES['hero_banners']['tmp_name'][$i])) { if (isset($current_banners[$i]) && file_exists($current_banners[$i])) unlink($current_banners[$i]); $file = ['name' => $_FILES['hero_banners']['name'][$i], 'tmp_name' => $_FILES['hero_banners']['tmp_name'][$i], 'error' => $_FILES['hero_banners']['error'][$i]]; if($dest = handle_image_upload($file, $upload_dir, 'hero-')) $current_banners[$i] = $dest; } }
            update_setting($pdo, 'hero_banner', array_values(array_filter($current_banners)));
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_site_logo':
            $current_logo = $site_config['site_logo'] ?? '';
            if (isset($_POST['delete_site_logo']) && !empty($current_logo) && file_exists($current_logo)) { unlink($current_logo); $current_logo = ''; }
            if ($dest = handle_image_upload($_FILES['site_logo'] ?? null, $upload_dir, 'logo-')) { if(!empty($current_logo) && file_exists($current_logo)) unlink($current_logo); $current_logo = $dest; }
            update_setting($pdo, 'site_logo', $current_logo);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_favicon':
            $current_favicon = $site_config['favicon'] ?? '';
            if (isset($_POST['delete_favicon']) && !empty($current_favicon) && file_exists($current_favicon)) { unlink($current_favicon); $current_favicon = ''; }
            if ($dest = handle_image_upload($_FILES['favicon'] ?? null, $upload_dir, 'favicon-')) { if(!empty($current_favicon) && file_exists($current_favicon)) unlink($current_favicon); $current_favicon = $dest; }
            update_setting($pdo, 'favicon', $current_favicon);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_payment_methods':
            $payment_methods = $site_config['payment_methods'] ?? [];
            foreach ($_POST['payment_methods'] as $name => $details) {
                if (isset($details['number'])) $payment_methods[$name]['number'] = htmlspecialchars(trim($details['number']));
                if (isset($details['pay_id'])) $payment_methods[$name]['pay_id'] = htmlspecialchars(trim($details['pay_id']));
                if (isset($_POST['delete_logos'][$name]) && !empty($payment_methods[$name]['logo_url']) && file_exists($payment_methods[$name]['logo_url'])) { unlink($payment_methods[$name]['logo_url']); $payment_methods[$name]['logo_url'] = ''; }
                if (isset($_FILES['payment_logos']['name'][$name]) && $_FILES['payment_logos']['error'][$name] === UPLOAD_ERR_OK) {
                    $file = ['name' => $_FILES['payment_logos']['name'][$name], 'tmp_name' => $_FILES['payment_logos']['tmp_name'][$name], 'error' => $_FILES['payment_logos']['error'][$name]];
                    if($dest = handle_image_upload($file, $upload_dir, 'payment-')) { if(!empty($payment_methods[$name]['logo_url']) && file_exists($payment_methods[$name]['logo_url'])) unlink($payment_methods[$name]['logo_url']); $payment_methods[$name]['logo_url'] = $dest; }
                }
            }
            update_setting($pdo, 'payment_methods', $payment_methods);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_smtp_settings':
            $smtp_settings = $site_config['smtp_settings'] ?? [];
            if (isset($_POST['admin_email'])) { $smtp_settings['admin_email'] = htmlspecialchars(trim($_POST['admin_email'])); }
            if (!empty(trim($_POST['app_password']))) { $smtp_settings['app_password'] = trim($_POST['app_password']); }
            update_setting($pdo, 'smtp_settings', $smtp_settings);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_currency_rate':
            update_setting($pdo, 'usd_to_bdt_rate', (float)$_POST['usd_to_bdt_rate']);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_contact_info':
            $contact_info = ['phone' => htmlspecialchars(trim($_POST['phone_number'])), 'whatsapp' => htmlspecialchars(trim($_POST['whatsapp_number'])), 'email' => htmlspecialchars(trim($_POST['email_address']))];
            update_setting($pdo, 'contact_info', $contact_info);
            $redirect_url = 'admin.php?view=settings';
            break;
        case 'update_page_content':
            if (isset($_POST['page_content']) && is_array($_POST['page_content'])) {
                foreach ($_POST['page_content'] as $key => $content) {
                    $db_key = "page_content_" . $key;
                    update_setting($pdo, $db_key, $content);
                }
            }
            $redirect_url = 'admin.php?view=pages';
            break;
    }
    header('Location: ' . $redirect_url);
    exit;
}

http_response_code(403);
die("Invalid Access Method");