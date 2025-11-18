<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

// Only super admin can access this page
if (!is_super_admin($conn)) {
    $_SESSION['error_message'] = "Access Denied: You do not have permission to view system settings.";
    redirect('index.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $library_name = $_POST['library_name'] ?? '';
    $fine_per_day = $_POST['fine_per_day'] ?? '';
    $currency_symbol = $_POST['currency_symbol'] ?? '';
    $max_borrow_days = $_POST['max_borrow_days'] ?? '';
    $max_borrow_limit = $_POST['max_borrow_limit'] ?? '';

    // Basic validation
    if (!is_numeric($fine_per_day) || $fine_per_day < 0) {
        $error = "Fine per day must be a non-negative number.";
    } elseif (!is_numeric($max_borrow_days) || $max_borrow_days < 1) {
        $error = "Max borrowing days must be a positive integer.";
    } elseif (!is_numeric($max_borrow_limit) || $max_borrow_limit < 1) {
        $error = "Max borrowing limit must be a positive integer.";
    } else {
        // Array of settings to update
        $settings_to_update = [
            'library_name' => $library_name,
            'fine_per_day' => $fine_per_day,
            'currency_symbol' => $currency_symbol,
            'max_borrow_days' => $max_borrow_days,
            'max_borrow_limit' => $max_borrow_limit,
        ];

        $success_count = 0;
        foreach ($settings_to_update as $key => $value) {
            $stmt = $conn->prepare("UPDATE tbl_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->bind_param("ss", $value, $key);
            if ($stmt->execute()) {
                $success_count++;
            }
        }

        if ($success_count == count($settings_to_update)) {
            $message = "System settings updated successfully!";
        } else {
            $error = "An error occurred while updating some settings.";
        }
    }
}

// Fetch current settings for display
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM tbl_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

admin_header('System Configuration');
?>

<style>
    /* * ==================================
     * ATTRACTIVE SETTINGS STYLING
     * ==================================
     */

    /* Card Styling */
    .card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
        padding: 30px;
        max-width: 800px; /* Limit width for better readability */
        margin: 0 auto; /* Center the card */
    }
    .card h2 {
        font-size: 24px;
        font-weight: 600;
        color: #343a40;
        margin-top: 0;
        margin-bottom: 25px;
        border-bottom: 2px solid #f1f1f1;
        padding-bottom: 15px;
    }
    .card h2 i {
        margin-right: 10px;
        color: #007bff;
    }

    /* Alert Boxes */
    .alert {
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 500;
        border: 1px solid transparent;
    }
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    /* Settings Grid Layout */
    .settings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Two columns */
        gap: 25px;
    }
    .full-width {
        grid-column: 1 / -1; /* Span both columns */
    }

    /* Modern Form Inputs */
    .form-group {
        position: relative;
        margin-bottom: 0; /* Gap handled by grid */
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
        font-size: 14px;
    }
    .form-group .form-icon {
        position: absolute;
        left: 15px;
        top: 43px; /* Adjusted based on label height */
        color: #aaa;
        font-size: 16px;
        z-index: 2;
    }
    .form-group input[type="text"],
    .form-group input[type="number"] {
        width: 100%;
        padding: 12px 12px 12px 45px; /* Left padding for icon */
        border: 1px solid #ddd;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 15px;
        font-family: 'Poppins', sans-serif;
        background: #f9f9f9;
        transition: all 0.3s ease;
    }
    .form-group input:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    
    /* Helper Text */
    .form-text {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #6c757d;
    }

    /* Gradient Button */
    .btn-gradient {
        width: 100%;
        padding: 14px;
        background: linear-gradient(90deg, #007bff, #0056b3);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
        margin-top: 10px;
    }
    .btn-gradient:hover {
        background: linear-gradient(90deg, #0056b3, #007bff);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
    }
    .btn-gradient i {
        margin-right: 8px;
    }
    
    @media (max-width: 600px) {
        .settings-grid {
            grid-template-columns: 1fr; /* Stack columns on mobile */
        }
    }
</style>

<div class="card">
    <h2><i class="fas fa-cogs"></i> Library Operational Rules</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="settings-grid">
        
        <div class="form-group full-width">
            <label for="library_name">Library Name</label>
            <i class="form-icon fas fa-university"></i>
            <input type="text" id="library_name" name="library_name" value="<?php echo htmlspecialchars($settings['library_name'] ?? ''); ?>" required>
            <small class="form-text">The name of the library, shown in headers and receipts.</small>
        </div>

        <div class="form-group">
            <label for="currency_symbol">Currency Symbol</label>
            <i class="form-icon fas fa-money-bill-wave"></i>
            <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? ''); ?>" required maxlength="5">
            <small class="form-text">The symbol used for all fines (e.g., ₹, $).</small>
        </div>

        <div class="form-group">
            <label for="fine_per_day">Fine Amount Per Day</label>
            <i class="form-icon fas fa-hand-holding-usd"></i>
            <input type="number" step="0.01" id="fine_per_day" name="fine_per_day" value="<?php echo htmlspecialchars($settings['fine_per_day'] ?? ''); ?>" required>
            <small class="form-text">Charged for each day a book is overdue.</small>
        </div>

        <div class="form-group">
            <label for="max_borrow_days">Max Borrowing Days</label>
            <i class="form-icon fas fa-clock"></i>
            <input type="number" id="max_borrow_days" name="max_borrow_days" value="<?php echo htmlspecialchars($settings['max_borrow_days'] ?? ''); ?>" required min="1">
            <small class="form-text">Days before a book becomes overdue.</small>
        </div>

        <div class="form-group">
            <label for="max_borrow_limit">Max Borrowing Limit</label>
            <i class="form-icon fas fa-layer-group"></i>
            <input type="number" id="max_borrow_limit" name="max_borrow_limit" value="<?php echo htmlspecialchars($settings['max_borrow_limit'] ?? ''); ?>" required min="1">
            <small class="form-text">Max books a member can hold at once.</small>
        </div>

        <div class="form-group full-width">
            <button type="submit" class="btn-gradient"><i class="fas fa-save"></i> Save Settings</button>
        </div>
    </form>
</div>

<?php
admin_footer();
close_db_connection($conn);
?>