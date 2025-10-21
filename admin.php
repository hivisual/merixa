<?php
session_start();

// --- KONFIGURASI ---
$PASSWORD = 'ganti-dengan-password-aman'; // Ganti password ini!
$PRODUCTS_FILE = 'products.json';
$PRICELIST_HTML_FILE = 'pricelist/index.html';
$IMG_DIR = 'assets/img/';
// --------------------

// --- FUNGSI LOGIN ---
if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION['loggedin'] = true;
    } else {
        $login_error = 'Password salah!';
    }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Tampilkan halaman login jika belum login
    echo '<!DOCTYPE html><html lang="id"><head><title>Login Admin</title><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5;} form{background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 300px;} h2{text-align: center; margin-bottom: 20px;} input[type=password], input[type=submit]{width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #ddd; box-sizing: border-box;} input[type=submit]{background: #007bff; color: white; cursor: pointer; border: none;} .error{color: red; text-align: center; margin-bottom: 15px;}</style>';
    echo '</head><body>';
    echo '<form method="post"><h2>Login Admin</h2>';
    if (isset($login_error)) {
        echo '<p class="error">' . $login_error . '</p>';
    }
    echo '<input type="password" name="password" placeholder="Password" required><br>';
    echo '<input type="submit" value="Login">';
    echo '</form></body></html>';
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
// --- AKHIR FUNGSI LOGIN ---


// --- FUNGSI-FUNGSI UTAMA ---

function getProducts() {
    global $PRODUCTS_FILE;
    if (!file_exists($PRODUCTS_FILE)) return [];
    $json = file_get_contents($PRODUCTS_FILE);
    return json_decode($json, true);
}

function saveProducts($products) {
    global $PRODUCTS_FILE;
    // Urutkan berdasarkan nama produk
    usort($products, function($a, $b) {
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    });
    file_put_contents($PRODUCTS_FILE, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getProductById($id) {
    $products = getProducts();
    foreach ($products as $product) {
        if ($product['id'] == $id) return $product;
    }
    return null;
}

function deleteProduct($id) {
    $products = getProducts();
    $products = array_filter($products, function($p) use ($id) {
        return $p['id'] != $id;
    });
    saveProducts(array_values($products));
    regeneratePricelist();
}

function handleImageUpload() {
    global $IMG_DIR;
    if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
        $allowed_types = ['image/svg+xml', 'image/svg'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['new_image']['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, $allowed_types)) {
            return ['error' => 'Error: Hanya file SVG yang diizinkan.'];
        }
        
        $filename = basename($_FILES['new_image']['name']);
        $target_path = $IMG_DIR . $filename;

        if (move_uploaded_file($_FILES['new_image']['tmp_name'], $target_path)) {
            return ['success' => $filename];
        } else {
            return ['error' => 'Error: Gagal mengunggah file.'];
        }
    }
    return ['success' => null];
}

function regeneratePricelist() {
    global $PRICELIST_HTML_FILE, $IMG_DIR;
    $products = getProducts();
    
    // Bagian Header dari file HTML asli Anda
    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricelist Produk Digital</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <div class="header">
        <h1>Merixa</h1>
        <p>ALL APP PREMIUM READY</p>
    </div>

    <div class="pricelist-grid">
HTML;

    // Generate setiap kartu produk
    foreach ($products as $product) {
        $product_name_safe = htmlspecialchars($product['name']);
        $product_image_safe = htmlspecialchars($product['image']);
        $image_path = "../" . $IMG_DIR . $product_image_safe;

        $html .= "\n        <div class=\"product-card\">";
        $html .= "\n            <div class=\"product-header\">";
        $html .= "\n                <img src=\"{$image_path}\" alt=\"{$product_name_safe} Logo\" class=\"product-logo\">";
        $html .= "\n                <span class=\"product-name\">{$product_name_safe}</span>";
        $html .= "\n            </div>";
        $html .= "\n            <div class=\"product-body\">";
        
        if (!empty($product['tiers'])) {
            foreach ($product['tiers'] as $tier) {
                $tier_title_safe = htmlspecialchars($tier['title']);
                $html .= "\n                <p>{$tier_title_safe}</p>";
                $html .= "\n                <ul>";
                $prices = explode("\n", str_replace("\r", "", $tier['prices']));
                foreach ($prices as $price) {
                    if (!empty(trim($price))) {
                        $price_safe = htmlspecialchars(trim($price));
                        $html .= "\n                    <li>{$price_safe}</li>";
                    }
                }
                $html .= "\n                </ul>";
            }
        }
        
        $html .= "\n            </div>";
        $html .= "\n        </div>";
    }

    // Bagian Footer dari file HTML asli Anda
    $html .= <<<HTML

        
    </div>

    <footer class="site-footer">
        <div class="footer-container">
            <h2>Tertarik untuk Memesan?</h2>
            <p>Hubungi kami melalui salah satu platform di bawah ini untuk respon cepat.</p>
            <div class="footer-contacts">
                <a href="#" class="contact-button whatsapp">
                    <i class="fa-brands fa-whatsapp"></i>
                    <span>Chat via WhatsApp</span>
                </a>
                <a href="#" class="contact-button telegram">
                    <i class="fa-brands fa-telegram"></i>
                    <span>Chat via Telegram</span>
                </a>
            </div>
        </div>
    </footer>

</body>
</html>
HTML;
    
    file_put_contents($PRICELIST_HTML_FILE, $html);
}


// --- LOGIKA PROSES FORM ---
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    
    // Handle upload dulu
    $uploadResult = handleImageUpload();
    if (isset($uploadResult['error'])) {
        $message = $uploadResult['error'];
        $action = isset($_POST['id']) ? 'edit' : 'add'; // Stay on the form page
    } else {
        $image_filename = $uploadResult['success'] ?? $_POST['image'];

        $tiers = [];
        if (isset($_POST['tier_title'])) {
            for ($i = 0; $i < count($_POST['tier_title']); $i++) {
                if (!empty($_POST['tier_title'][$i])) {
                    $tiers[] = [
                        'title' => $_POST['tier_title'][$i],
                        'prices' => $_POST['tier_prices'][$i]
                    ];
                }
            }
        }

        $new_product_data = [
            'id' => $_POST['id'] ?? time(),
            'name' => $_POST['name'],
            'image' => $image_filename,
            'tiers' => $tiers
        ];

        $products = getProducts();
        if (isset($_POST['id'])) { // Edit
            $found = false;
            foreach ($products as $i => $p) {
                if ($p['id'] == $_POST['id']) {
                    $products[$i] = $new_product_data;
                    $found = true;
                    break;
                }
            }
        } else { // Add
            $products[] = $new_product_data;
        }

        saveProducts($products);
        regeneratePricelist();
        header('Location: admin.php');
        exit;
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    deleteProduct($_GET['id']);
    header('Location: admin.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manajemen Pricelist</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h1, h2 { color: #222; }
        h1 { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 5px; color: #fff; font-weight: bold; }
        .btn-primary { background-color: #007bff; }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        td img { width: 24px; height: 24px; vertical-align: middle; margin-right: 10px; }
        td.actions { text-align: right; white-space: nowrap; }
        .actions a { margin-left: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input[type="text"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        textarea { min-height: 100px; resize: vertical; font-family: monospace; }
        .tier-block { border: 1px solid #e0e0e0; padding: 15px; border-radius: 5px; margin-bottom: 15px; }
        .tier-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .message { padding: 15px; background: #fce4e4; border: 1px solid #fcc2c3; color: #cc0000; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span>Manajemen Pricelist</span>
            <a href="admin.php?logout=1" class="btn btn-secondary">Logout</a>
        </h1>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <p><a href="?action=add" class="btn btn-primary">+ Tambah Produk Baru</a></p>
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>File Gambar</th>
                        <th class="actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $products = getProducts();
                    if (empty($products)): ?>
                        <tr><td colspan="3">Belum ada produk.</td></tr>
                    <?php else: 
                        foreach ($products as $product): ?>
                        <tr>
                            <td><img src="<?= $IMG_DIR . htmlspecialchars($product['image']) ?>" alt="logo"> <?= htmlspecialchars($product['name']) ?></td>
                            <td><?= htmlspecialchars($product['image']) ?></td>
                            <td class="actions">
                                <a href="?action=edit&id=<?= $product['id'] ?>">✏️ Edit</a>
                                <a href="?action=delete&id=<?= $product['id'] ?>" onclick="return confirm('Yakin ingin menghapus produk ini?')">🗑️ Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        
        <?php elseif ($action === 'add' || $action === 'edit'): 
            $product = null;
            $is_edit = $action === 'edit';
            if ($is_edit) {
                $product = getProductById($_GET['id']);
            }
        ?>
            <h2><?= $is_edit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Nama Produk</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="image">Gambar Produk (SVG)</label>
                    <select name="image" id="image">
                        <option value="">-- Pilih Gambar yang Ada --</option>
                        <?php
                        $files = scandir($IMG_DIR);
                        foreach ($files as $file) {
                            if (pathinfo($file, PATHINFO_EXTENSION) === 'svg') {
                                $selected = (isset($product['image']) && $product['image'] === $file) ? 'selected' : '';
                                echo "<option value='".htmlspecialchars($file)."' {$selected}>".htmlspecialchars($file)."</option>";
                            }
                        }
                        ?>
                    </select>
                    <p style="margin-top: 10px;">Atau unggah yang baru:</p>
                    <input type="file" name="new_image" accept=".svg,image/svg+xml">
                </div>

                <hr style="margin: 30px 0;">

                <h3>Detail Harga</h3>
                <div id="tiers-container">
                    <?php if (!empty($product['tiers'])): 
                        foreach ($product['tiers'] as $index => $tier): ?>
                        <div class="tier-block">
                             <div class="tier-header">
                                <strong>Tipe Harga #<?= $index + 1 ?></strong>
                                <button type="button" class="remove-tier btn btn-danger">Hapus Tipe</button>
                            </div>
                            <div class="form-group">
                                <label>Judul Tipe (e.g., Sharing, Private)</label>
                                <input type="text" name="tier_title[]" placeholder="Contoh: Sharing" value="<?= htmlspecialchars($tier['title']) ?>">
                            </div>
                            <div class="form-group">
                                <label>List Harga (satu per baris)</label>
                                <textarea name="tier_prices[]" placeholder="1 Bulan : 125k&#10;3 Bulan : 350k"><?= htmlspecialchars($tier['prices']) ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <button type="button" id="add-tier" class="btn btn-secondary">+ Tambah Tipe Harga</button>
                
                <hr style="margin: 30px 0;">

                <input type="submit" name="save_product" value="Simpan Produk" class="btn btn-primary">
                <a href="admin.php" style="margin-left: 10px;">Batal</a>
            </form>
            
            <script>
                document.getElementById('add-tier').addEventListener('click', function() {
                    const container = document.getElementById('tiers-container');
                    const tierCount = container.querySelectorAll('.tier-block').length;
                    const newTier = document.createElement('div');
                    newTier.className = 'tier-block';
                    newTier.innerHTML = `
                        <div class="tier-header">
                            <strong>Tipe Harga #${tierCount + 1}</strong>
                            <button type="button" class="remove-tier btn btn-danger">Hapus Tipe</button>
                        </div>
                        <div class="form-group">
                            <label>Judul Tipe (e.g., Sharing, Private)</label>
                            <input type="text" name="tier_title[]" placeholder="Contoh: Sharing">
                        </div>
                        <div class="form-group">
                            <label>List Harga (satu per baris)</label>
                            <textarea name="tier_prices[]" placeholder="1 Bulan : 125k&#10;3 Bulan : 350k"></textarea>
                        </div>
                    `;
                    container.appendChild(newTier);
                });

                document.getElementById('tiers-container').addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('remove-tier')) {
                        e.target.closest('.tier-block').remove();
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
