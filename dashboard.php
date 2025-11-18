<?php
require_once 'includes/functions.php';
require_member_login();
global $conn;

$member_id = $_SESSION['member_id'];
$currency = get_setting($conn, 'currency_symbol');

// --- Fetch Member Details ---
$stmt = $conn->prepare("SELECT * FROM tbl_members WHERE member_id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member_details = $stmt->get_result()->fetch_assoc();

// --- Handle Profile Update/Password Change ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');

        $stmt = $conn->prepare("UPDATE tbl_members SET full_name = ?, email = ?, department = ? WHERE member_id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $department, $member_id);
        if ($stmt->execute()) {
            $_SESSION['member_full_name'] = $full_name;
            $member_details['full_name'] = $full_name;
            $member_details['email'] = $email;
            $member_details['department'] = $department;
            $message = "Profile updated successfully!";
        } else {
            $error = "Error updating profile.";
        }
    } elseif ($action === 'change_password') {
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // **SECURITY NOTE**: Password stored in plain text as requested by user.
            $stmt = $conn->prepare("UPDATE tbl_members SET password = ? WHERE member_id = ?");
            $stmt->bind_param("si", $new_password, $member_id);
            if ($stmt->execute()) {
                $message = "Password changed successfully!";
            } else {
                $error = "Error changing password.";
            }
        }
    }
}

// --- Fetch Borrowed Books ---
$sql_borrowed = "
    SELECT 
        tc.issue_date, tc.due_date,
        tb.title, tb.author,
        tbc.book_uid
    FROM 
        tbl_circulation tc
    JOIN 
        tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
    WHERE 
        tc.member_id = ? AND tc.status = 'Issued'
    ORDER BY 
        tc.due_date ASC
";
$stmt = $conn->prepare($sql_borrowed);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$borrowed_books = $stmt->get_result();

// --- Reservation feature removed ---
$reservations = (object)['num_rows' => 0]; // Mock empty result for display logic

// --- Fetch Outstanding Fines ---
$sql_fines = "
    SELECT 
        tf.fine_id, tf.fine_amount, tf.fine_date,
        tb.title
    FROM 
        tbl_fines tf
    JOIN 
        tbl_circulation tc ON tf.circulation_id = tc.circulation_id
    JOIN 
        tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
    WHERE 
        tf.member_id = ? AND tf.payment_status = 'Pending'
    ORDER BY 
        tf.fine_date DESC
";
$stmt = $conn->prepare($sql_fines);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$fines = $stmt->get_result();
$total_fine = 0;
$fines_data = []; // Initialize array
while ($fine_row = $fines->fetch_assoc()) {
    $total_fine += $fine_row['fine_amount'];
    $fines_data[] = $fine_row;
}
$fines->data_seek(0); // Reset pointer for display

// This function call would be in your user_header.php
// For this example, I'm embedding the styles directly.
user_header('My Dashboard');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* * ==================================
     * NEW ATTRACTIVE DASHBOARD STYLING
     * ==================================
     */

    /* Apply Poppins font to the body, assuming user_style.css doesn't override it */
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8f9fa; /* Lighter gray background */
    }

    /* Modern Grid Layout */
    .dashboard-grid-user {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 30px;
        padding: 30px;
    }

    /* Modern Card Styling */
    .card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
        padding: 25px 30px;
        transition: all 0.3s ease;
        border: none;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .card h2 {
        color: #0056b3;
        font-weight: 600;
        margin-bottom: 20px;
        border-bottom: 2px solid #eee;
        padding-bottom: 15px;
        font-size: 1.5rem;
    }

    .card h2 i {
        margin-right: 12px;
        color: #007bff;
    }

    /* Tab navigation for Profile Card */
    .profile-tabs {
        display: flex;
        border-bottom: 2px solid #ddd;
        margin-bottom: 25px;
    }

    .tab-link {
        padding: 10px 20px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        color: #555;
        transition: all 0.3s;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px; /* Aligns with the container border */
    }

    .tab-link:hover {
        color: #007bff;
    }

    .tab-link.active {
        color: #007bff;
        border-bottom: 2px solid #007bff;
        font-weight: 600;
    }

    /* Tab content panes */
    .tab-pane {
        display: none;
        animation: fadeIn 0.5s;
    }

    .tab-pane.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Modern Form Styling */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        font-weight: 500;
        color: #495057;
        font-size: 14px;
        margin-bottom: 8px;
        display: block;
    }

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 16px;
        font-family: 'Poppins', sans-serif;
        background: #f9f9f9;
        transition: all 0.3s;
    }

    .form-group input:focus {
        border-color: #007bff;
        background: #fff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
    }

    .form-group input:disabled {
        background: #e9ecef;
        color: #6c757d;
        cursor: not-allowed;
    }

    /* Button Styling */
    .btn {
        padding: 12px 20px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    /* Re-style your existing button classes */
    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #0069d9 0%, #004a99 100%);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
    }
    .btn-danger:hover {
        background: linear-gradient(135deg, #c82333 0%, #a22430 100%);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
        border: none;
    }
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .btn-lg {
        padding: 14px 24px;
        font-size: 18px;
    }

    /* Alert Styling */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
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

    /* Modern Table Styling */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }

    .data-table {
        width: 90%;
        border-collapse: collapse;
        margin-top: 20px;
	
    }

    .data-table thead tr {
        background-color: #f4f6f8;
        border-bottom: 2px solid #ddd;
    }

    .data-table th,
    .data-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }

    .data-table th {
        font-weight: 600;
        color: #333;
    }

    .data-table tbody tr:hover {
        background-color: #f9f9f9;
    }

    .data-table .table-danger-row,
    .data-table .table-danger-row:hover {
        background-color: #f8d7da !important;
        color: #721c24;
        font-weight: 500;
    }

    /* Badge Styling */
    .badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-danger {
        background-color: #f8d7da;
        color: #721c24;
    }
    .badge-warning {
        background-color: #fff3cd;
        color: #856404;
    }
    /* You might not have this one, but good to have */
    .badge-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    /* Fines Card Specific */
    .total-fine {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: #333;
    }
    .total-fine span {
        font-size: 1.75rem;
        font-weight: 700;
        color: #dc3545;
    }
    
    .text-muted {
        color: #6c757d !important;
        font-size: 14px;
    }

    /* History Card Specific */
    .history-card {
        text-align: center;
        background: linear-gradient(135deg, #f5f7fa 0%, #e0e8f0 100%);
    }
    .history-card h2 {
        color: #343a40;
    }
    .history-card p {
        font-size: 1.1rem;
        color: #495057;
        margin-bottom: 25px;
    }

</style>

<div class="dashboard-grid-user">
    <div class="card profile-card" id="profile">
        <h2><i class="fas fa-user-circle"></i> My Profile</h2>

        <div class="profile-tabs">
            <button class="tab-link active" onclick="openProfileTab(event, 'profile-details')">
                <i class="fas fa-user-edit"></i> Profile Details
            </button>
            <button class="tab-link" onclick="openProfileTab(event, 'password-change')">
                <i class="fas fa-key"></i> Change Password
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tab-content">
            <div id="profile-details" class="tab-pane active">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Member ID (Username)</label>
                        <input type="text" value="<?php echo htmlspecialchars($member_details['member_uid']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($member_details['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($member_details['email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($member_details['department']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>

            <div id="password-change" class="tab-pane">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-key"></i> Change Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card borrowed-card" id="borrowed">
        <h2><i class="fas fa-book-open"></i> Books Currently Borrowed</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Book ID (UID)</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($borrowed_books->num_rows > 0): ?>
                        <?php while ($book = $borrowed_books->fetch_assoc()): ?>
                            <?php 
                                $is_overdue = strtotime($book['due_date']) < time();
                                $status_class = $is_overdue ? 'badge-danger' : 'badge-warning';
                                $status_text = $is_overdue ? 'OVERDUE' : 'Issued';
                            ?>
                            <tr class="<?php echo $is_overdue ? 'table-danger-row' : ''; ?>">
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['book_uid']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($book['due_date'])); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">You have no books currently borrowed.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    
    <div class="card history-card">
        <h2><i class="fas fa-history"></i> My Borrowing History</h2>
        <p>View a complete log of every book you have ever borrowed.</p>
        <a href="history.php" class="btn btn-secondary btn-lg"><i class="fas fa-eye"></i> View Full History</a>
    </div>
</div>

<script>
    function openProfileTab(evt, tabName) {
        // Get all elements with class="tab-pane" and hide them
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-pane");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="tab-link" and remove the class "active"
        tablinks = document.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }

        // Show the current tab, and add an "active" class to the button that opened the tab
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

    // On page load, click the first tab by default
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelector('.tab-link').click();
    });
</script>

<?php
user_footer();
close_db_connection($conn);
?>