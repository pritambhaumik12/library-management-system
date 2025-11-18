<?php
// Start session for all pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

// Include database connection
require_once 'db_connect.php';

// --- General Utility Functions ---

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function get_setting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM tbl_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

// --- Admin Authentication Functions ---

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        redirect('login.php');
    }
}

function is_super_admin($conn) {
    if (!is_admin_logged_in()) {
        return false;
    }
    $admin_id = $_SESSION['admin_id'];
    $stmt = $conn->prepare("SELECT is_super_admin FROM tbl_admin WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['is_super_admin'] == 1;
    }
    return false;
}

// --- Member Authentication Functions ---

function is_member_logged_in() {
    return isset($_SESSION['member_id']);
}

function require_member_login() {
    if (!is_member_logged_in()) {
        redirect('login.php');
    }
}

// --- Book Utility Functions ---

function generate_book_uid($internal_id) {
    return "LMS/BOOK/" . str_pad($internal_id, 5, '0', STR_PAD_LEFT);
}

function generate_member_uid($internal_id) {
    return "LMS/MEM/" . str_pad($internal_id, 5, '0', STR_PAD_LEFT);
}

// --- Fine Utility Functions ---

function calculate_fine($conn, $due_date, $return_date) {
    $fine_per_day = (float)get_setting($conn, 'fine_per_day');
    
    $due = new DateTime($due_date);
    $return = new DateTime($return_date);
    
    if ($return > $due) {
        $interval = $return->diff($due);
        $days_late = $interval->days;
        return $days_late * $fine_per_day;
    }
    
    return 0.00;
}

// --- HTML/UI Utility Functions (ATTRACTIVE DESIGN UPDATE) ---

/**
 * Helper to check active page for sidebar highlighting
 */
function is_active_page($page_name) {
    return basename($_SERVER['PHP_SELF']) == $page_name ? 'active' : '';
}

function admin_header($title) {
    global $conn;
    $library_name = htmlspecialchars(get_setting($conn, 'library_name') ?? 'LMS Admin');
    $admin_name = htmlspecialchars($_SESSION['admin_full_name'] ?? 'Admin');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' | ' . $library_name . '</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #007bff;
            --primary-dark: #0056b3;
            --sidebar-bg: linear-gradient(180deg, #2c3e50 0%, #000000 100%);
            --text-color: #333;
            --bg-color: #f4f6f9;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* Layout Structure */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: #fff;
            flex-shrink: 0;
            transition: all 0.3s;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .sidebar-header i {
            color: var(--primary-color);
        }

        .sidebar-menu {
            list-style: none;
            padding: 15px 10px;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #aab0b6;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        .sidebar-menu li a i {
            width: 25px;
            font-size: 16px;
            margin-right: 10px;
            text-align: center;
        }

        .sidebar-menu li a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar-menu li a.active {
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        
        .sidebar-menu li a.logout-link {
            color: #dc3545;
            margin-top: 20px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        .sidebar-menu li a.logout-link:hover {
            background-color: #dc3545;
            color: white;
        }

        /* Content Area Styling */
        .content {
            flex-grow: 1;
            margin-left: 260px; /* Matches sidebar width */
            display: flex;
            flex-direction: column;
            width: calc(100% - 260px);
        }

        /* Top Navbar */
        .navbar {
            background-color: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .navbar h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #e9ecef;
        }
        
        .user-info i {
            color: var(--primary-color);
            font-size: 20px;
        }
        
        .user-info span {
            font-weight: 500;
            font-size: 14px;
            color: #495057;
        }

        /* Page Content Container */
        .page-content {
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h3, .sidebar-menu li a span { display: none; }
            .sidebar-menu li a { justify-content: center; padding: 15px 0; }
            .sidebar-menu li a i { margin: 0; font-size: 20px; }
            .content { margin-left: 70px; width: calc(100% - 70px); }
            .navbar h1 { font-size: 16px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-book-reader"></i> LMS</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="' . is_active_page('index.php') . '"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                
                <li style="margin-top: 10px; padding-left: 15px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600;"><span>Catalog</span></li>
                <li><a href="books.php" class="' . is_active_page('books.php') . '"><i class="fas fa-book"></i> <span>Book Management</span></a></li>
                <li><a href="book_copies.php" class="' . is_active_page('book_copies.php') . '"><i class="fas fa-barcode"></i> <span>Copy Status</span></a></li>
                
                <li style="margin-top: 10px; padding-left: 15px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600;"><span>Users & Circ</span></li>
                <li><a href="members.php" class="' . is_active_page('members.php') . '"><i class="fas fa-users"></i> <span>Members</span></a></li>
                <li><a href="issue_book.php" class="' . is_active_page('issue_book.php') . '"><i class="fas fa-exchange-alt"></i> <span>Issue Book</span></a></li>
                <li><a href="return_book.php" class="' . is_active_page('return_book.php') . '"><i class="fas fa-undo-alt"></i> <span>Return Book</span></a></li>

                <li style="margin-top: 10px; padding-left: 15px; font-size: 12px; text-transform: uppercase; color: #6c757d; font-weight: 600;"><span>Admin</span></li>
                <li><a href="fines.php" class="' . is_active_page('fines.php') . '"><i class="fas fa-money-bill-wave"></i> <span>Fines</span></a></li>
                <li><a href="reports.php" class="' . is_active_page('reports.php') . '"><i class="fas fa-chart-line"></i> <span>Reports</span></a></li>';
    
    if (is_super_admin($conn)) {
        echo '<li><a href="settings.php" class="' . is_active_page('settings.php') . '"><i class="fas fa-cogs"></i> <span>Settings</span></a></li>';
    }
    
    echo '      <li><a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </aside>

        <main class="content">
            <header class="navbar">
                <h1>' . $title . '</h1>
                <div class="user-info">
                    <div style="text-align: right; line-height: 1.2; margin-right: 5px;">
                        <span style="display: block; font-weight: 600; color: #333;">' . $admin_name . '</span>
                        <span style="display: block; font-size: 11px; color: #888;">Administrator</span>
                    </div>
                    <i class="fas fa-user-circle" style="font-size: 32px;"></i>
                </div>
            </header>
            <div class="page-content">';
}

function admin_footer() {
    echo '      </div> </main>
    </div>
</body>
</html>';
}

// --- USER PORTAL HEADERS (For Front-End Users) ---

function user_header($title) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Portal | ' . htmlspecialchars($title) . '</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            color: #333;
        }
        
        /* Navbar */
        .user-navbar {
            background: #fff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 0 40px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo {
            font-size: 22px;
            font-weight: 700;
            color: #007bff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-navbar nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
        }
        
        .user-navbar nav a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .user-navbar nav a:hover {
            color: #007bff;
            background: rgba(0, 123, 255, 0.05);
        }
        
        .user-navbar nav a i {
            margin-right: 6px;
        }
        
        /* Main Content */
        .user-content {
            padding: 40px 20px;
            min-height: 80vh;
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* Footer */
        .user-footer {
            background: #fff;
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eaeaea;
        }
    </style>
</head>
<body>
    <header class="user-navbar">
        <div class="logo"><i class="fas fa-book-open"></i> LMS Portal</div>
        <nav>
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="search.php"><i class="fas fa-search"></i> Search Catalog</a></li>';
    
    if (is_member_logged_in()) {
        echo '<li><a href="dashboard.php"><i class="fas fa-user-circle"></i> My Dashboard</a></li>
              <li><a href="logout.php" style="color: #dc3545;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>';
    } else {
        echo '<li><a href="login.php" style="background: #007bff; color: white; padding: 8px 20px;"><i class="fas fa-sign-in-alt"></i> Login</a></li>';
    }
    
    echo '  </ul>
        </nav>
    </header>
    <main class="user-content">
        <div class="container">';
}

function user_footer() {
    echo '      </div>
    </main>
    <footer class="user-footer">
        <p>&copy; ' . date('Y') . ' Library Management System. All Rights Reserved.</p>
    </footer>
</body>
</html>';
}
?>