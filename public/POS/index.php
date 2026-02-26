<?php
session_start();
require_once '../../inc/config.php';
require_once '../../inc/db.php';
require_once '../../inc/auth.php';
require_once '../../inc/pos_saas_schema.php';

requireLogin();
requireDevice();
ensure_pos_saas_schema($pos_db);

// Get user info from session
$user_id = $_SESSION['pengguna_id'];
$user_nama = $_SESSION['pengguna_nama'];
$toko_id = $_SESSION['toko_id'];
$peran = $_SESSION['peran'];

// Get store information
$stmt_toko = $pos_db->prepare("SELECT * FROM toko WHERE toko_id = ? AND aktif = 1");
$stmt_toko->bind_param("i", $toko_id);
$stmt_toko->execute();
$result_toko = $stmt_toko->get_result();
$toko = $result_toko->fetch_assoc();

// Get logo path
$logo_path = '/public/assets/uploads/logo_toko_3.png';
if (!file_exists(__DIR__ . '/../assets/uploads/logo_toko_3.png')) {
    $logo_path = '/public/assets/img/logo.png';
}

// Day names in Indonesian
$hari_names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan_names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

$hari_ini = $hari_names[date('w')];
$tanggal_lengkap = date('d') . ' ' . $bulan_names[date('n') - 1] . ' ' . date('Y');

$shift_open = has_open_shift_today($pos_db, (int)$toko_id, (int)$user_id);
$kasir_href = $shift_open ? 'kasir.php' : 'tutup_kasir.php?need_open_shift=1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS AlbaOne - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray: #64748b;
            --light: #f8fafc;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
        }
        
        /* Header Styles */
        .header {
            background: var(--white);
            padding: 15px 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 10px;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .store-info {
            border-left: 2px solid #e2e8f0;
            padding-left: 20px;
        }
        
        .store-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .store-address {
            font-size: 12px;
            color: var(--gray);
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .datetime-box {
            text-align: right;
        }
        
        .day-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .full-date {
            font-size: 12px;
            color: var(--gray);
        }
        
        .status-box {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f0fdf4;
            border-radius: 20px;
            border: 1px solid #bbf7d0;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-text {
            font-size: 13px;
            font-weight: 600;
            color: #166534;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .user-role {
            font-size: 11px;
            color: var(--gray);
            text-transform: capitalize;
        }
        
        .logout-btn {
            padding: 10px 20px;
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
        }
        
        .welcome-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            font-size: 14px;
            color: var(--gray);
        }
        
        /* Navigation Menu */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .menu-category {
            background: var(--white);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-lg);
            transition: transform 0.3s;
        }
        
        .menu-category:hover {
            transform: translateY(-5px);
        }
        
        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .category-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .category-icon.transaksi {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }
        
        .category-icon.utility {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        
        .category-icon.maintenance {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .category-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .menu-items {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .menu-item:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.02);
        }

        .menu-item.disabled,
        .menu-item.disabled:hover {
            background: #e2e8f0;
            color: #94a3b8;
            border-color: #cbd5e1;
            transform: none;
            cursor: not-allowed;
        }
        
        .menu-item i {
            font-size: 18px;
            width: 25px;
            text-align: center;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .quick-btn {
            flex: 1;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .quick-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .quick-btn:disabled {
            background: #cbd5e1 !important;
            color: #64748b;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .quick-btn i {
            font-size: 28px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .header-left, .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .store-info {
                border-left: none;
                padding-left: 0;
                text-align: center;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-items {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <div class="logo-container">
                <img src="<?= $logo_path ?>" alt="Logo" class="logo-img" onerror="this.src='/public/assets/img/logo.png'">
                <span class="logo-text">AlbaOne</span>
            </div>
            <div class="store-info">
                <div class="store-name"><?= htmlspecialchars($toko['nama_toko'] ?? 'Toko Saya') ?></div>
                <div class="store-address"><?= htmlspecialchars($toko['alamat'] ?? 'Alamat belum diisi') ?></div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="datetime-box">
                <div class="day-name"><?= $hari_ini ?></div>
                <div class="full-date"><?= $tanggal_lengkap ?></div>
            </div>
            
            <div class="status-box">
                <div class="status-dot"></div>
                <span class="status-text">ONLINE</span>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user_nama, 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($user_nama) ?></span>
                    <span class="user-role"><?= htmlspecialchars($peran) ?></span>
                </div>
            </div>
            
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">Selamat Datang, <?= htmlspecialchars($user_nama) ?>!</h1>
            <p class="welcome-subtitle">Anda login sebagai <?= htmlspecialchars($peran) ?> - Silakan pilih menu di bawah untuk memulai transaksi</p>
            
            <div class="quick-actions">
                <button class="quick-btn" onclick="location.href='<?= htmlspecialchars($kasir_href) ?>'" <?= $shift_open ? '' : 'disabled title="Buka shift terlebih dahulu"' ?>>
                    <i class="fas fa-cash-register"></i>
                    Mulai Transaksi
                </button>
                <button class="quick-btn" onclick="location.href='price_check.php'" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                    <i class="fas fa-barcode"></i>
                    Price Check
                </button>
                <button class="quick-btn" onclick="location.href='data_barang.php'" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-box"></i>
                    Data Barang
                </button>
            </div>
        </div>
        
        <!-- Menu Grid -->
        <div class="menu-grid">
            
            <!-- TRANSAKSI -->
            <div class="menu-category">
                <div class="category-header">
                    <div class="category-icon transaksi">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h2 class="category-title">TRANSAKSI</h2>
                </div>
                <div class="menu-items">
                    <a href="kas.php" class="menu-item">
                        <i class="fas fa-cash-register"></i>
                        <span>Kas</span>
                    </a>
                    <a href="<?= htmlspecialchars($kasir_href) ?>" class="menu-item<?= $shift_open ? '' : ' disabled' ?>" <?= $shift_open ? '' : 'title="Buka shift terlebih dahulu"' ?>>
                        <i class="fas fa-user-tie"></i>
                        <span>Kasir</span>
                    </a>
                    <a href="price_check.php" class="menu-item">
                        <i class="fas fa-barcode"></i>
                        <span>Price Check</span>
                    </a>
                    <a href="data_barang.php" class="menu-item">
                        <i class="fas fa-boxes"></i>
                        <span>Data Barang</span>
                    </a>
                </div>
            </div>
            
            <!-- UTILITY -->
            <div class="menu-category">
                <div class="category-header">
                    <div class="category-icon utility">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h2 class="category-title">UTILITY</h2>
                </div>
                <div class="menu-items">
                    <a href="rekap_kasir.php" class="menu-item">
                        <i class="fas fa-chart-pie"></i>
                        <span>Rekap Kasir</span>
                    </a>
                    <a href="tutup_kasir.php" class="menu-item">
                        <i class="fas fa-door-closed"></i>
                        <span>Shift Kasir</span>
                    </a>
                    <a href="proses_data.php" class="menu-item">
                        <i class="fas fa-database"></i>
                        <span>Proses Data</span>
                    </a>
                    <a href="jurnal.php" class="menu-item">
                        <i class="fas fa-book"></i>
                        <span>Jurnal</span>
                    </a>
                </div>
            </div>
            
            <!-- MAINTENANCE -->
            <div class="menu-category">
                <div class="category-header">
                    <div class="category-icon maintenance">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h2 class="category-title">MAINTENANCE</h2>
                </div>
                <div class="menu-items">
                    <a href="../admin/settings.php" class="menu-item">
                        <i class="fas fa-sliders-h"></i>
                        <span>Configurasi</span>
                    </a>
                    <a href="../admin/users.php" class="menu-item">
                        <i class="fas fa-users-cog"></i>
                        <span>User</span>
                    </a>
                    <a href="#" class="menu-item" onclick="alert('Fitur Backup akan segera tersedia')">
                        <i class="fas fa-database"></i>
                        <span>Backup</span>
                    </a>
                    <a href="#" class="menu-item" onclick="alert('Fitur Repair akan segera tersedia')">
                        <i class="fas fa-wrench"></i>
                        <span>Repair</span>
                    </a>
                </div>
            </div>
            
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($toko['nama_toko'] ?? 'AlbaOne POS') ?>. All rights reserved.</p>
        <p>Powered by <a href="#">AlbaOne POS System</a></p>
    </footer>

    <script>
        // Update realtime clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const dayName = dayNames[now.getDay()];
            const dateString = now.getDate() + ' ' + ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'][now.getMonth()] + ' ' + now.getFullYear();
            
            document.querySelector('.day-name').textContent = dayName;
            document.querySelector('.full-date').textContent = dateString + ' ' + timeString;
        }
        
        setInterval(updateClock, 1000);
        updateClock();
        
        // Check online status
        window.addEventListener('online', function() {
            document.querySelector('.status-box').innerHTML = '<div class="status-dot"></div><span class="status-text">ONLINE</span>';
            document.querySelector('.status-box').style.background = '#f0fdf4';
            document.querySelector('.status-box').style.borderColor = '#bbf7d0';
        });
        
        window.addEventListener('offline', function() {
            document.querySelector('.status-box').innerHTML = '<div class="status-dot" style="background: #ef4444;"></div><span class="status-text" style="color: #dc2626;">OFFLINE</span>';
            document.querySelector('.status-box').style.background = '#fef2f2';
            document.querySelector('.status-box').style.borderColor = '#fecaca';
        });
    </script>
</body>
</html>
