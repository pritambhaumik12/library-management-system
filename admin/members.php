<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';
// Check if the currently logged-in user is a super admin
$is_logged_in_super_admin = is_super_admin($conn);

// --- Handle User Actions (Add, Edit, Delete) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD USER (MEMBER OR ADMIN) ---
    if ($action === 'add_user') {
        $account_type = $_POST['account_type'] ?? 'member';
        $full_name = trim($_POST['full_name'] ?? '');
        $username_or_uid = trim($_POST['member_uid'] ?? ''); // Use this for both username and member_uid
        $password = trim($_POST['password'] ?? 'password'); // Default password

        if (empty($full_name) || empty($username_or_uid)) {
            $error = "Full Name and Username/Student ID are required.";
        } else {
            if ($account_type === 'member') {
                // --- ADD A MEMBER ---
                $email = trim($_POST['email'] ?? '');
                $department = trim($_POST['department'] ?? '');

                if (empty($department)) {
                     $error = "Full Name, Student/Employee ID, and Department are required for members.";
                } else {
                    // Check if member_uid already exists in tbl_members
                    $stmt_check = $conn->prepare("SELECT member_id FROM tbl_members WHERE member_uid = ?");
                    $stmt_check->bind_param("s", $username_or_uid);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $error = "Member ID (Student/Employee ID) already exists.";
                    } else {
                        // Insert the new member
                        $stmt = $conn->prepare("INSERT INTO tbl_members (member_uid, password, full_name, email, department) VALUES (?, ?, ?, ?, ?)");
                        // **SECURITY NOTE**: Password stored in plain text as requested by user.
                        $stmt->bind_param("sssss", $username_or_uid, $password, $full_name, $email, $department);
                        
                        if ($stmt->execute()) {
                            $message = "Member '{$full_name}' added successfully. Member ID: {$username_or_uid}. Default Password: {$password}.";
                        } else {
                            $error = "Error adding member: " . $conn->error;
                        }
                    }
                }

            } elseif ($account_type === 'admin') {
                // --- ADD AN ADMIN ---
                
                // Security: Only a super admin can create another super admin.
                $is_super_admin_to_create = ($is_logged_in_super_admin && isset($_POST['is_super_admin'])) ? 1 : 0;

                // Check if username already exists in tbl_admin
                $stmt_check = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE username = ?");
                $stmt_check->bind_param("s", $username_or_uid);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $error = "Admin username '{$username_or_uid}' already exists.";
                } else {
                    // Insert the new admin
                    $stmt = $conn->prepare("INSERT INTO tbl_admin (username, password, full_name, is_super_admin) VALUES (?, ?, ?, ?)");
                    // **SECURITY NOTE**: Password stored in plain text as requested by user.
                    $stmt->bind_param("sssi", $username_or_uid, $password, $full_name, $is_super_admin_to_create);
                    
                    if ($stmt->execute()) {
                        $message = "Admin '{$full_name}' (Username: {$username_or_uid}) added successfully. Default Password: {$password}.";
                    } else {
                        $error = "Error adding admin: " . $conn->error;
                    }
                }
            }
        }
    } elseif ($action === 'edit_member') {
        // --- EDIT MEMBER (Unchanged) ---
        $member_id = (int)($_POST['member_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if (empty($full_name) || empty($department) || empty($status)) {
            $error = "All fields are required for editing.";
        } else {
            $stmt = $conn->prepare("UPDATE tbl_members SET full_name = ?, email = ?, department = ?, status = ? WHERE member_id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $department, $status, $member_id);
            
            if ($stmt->execute()) {
                $message = "Member details updated successfully.";
            } else {
                $error = "Error updating member: " . $conn->error;
            }
        }
    } elseif ($action === 'delete_member') {
        // --- DELETE MEMBER (Unchanged) ---
        $member_id = (int)($_POST['member_id'] ?? 0);
        
        // Check for currently issued books or outstanding fines
        $stmt_check_issued = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_circulation WHERE member_id = ? AND status = 'Issued'");
        $stmt_check_issued->bind_param("i", $member_id);
        $stmt_check_issued->execute();
        $issued_count = $stmt_check_issued->get_result()->fetch_assoc()['count'];

        $stmt_check_fines = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_fines WHERE member_id = ? AND payment_status = 'Pending'");
        $stmt_check_fines->bind_param("i", $member_id);
        $stmt_check_fines->execute();
        $fine_count = $stmt_check_fines->get_result()->fetch_assoc()['count'];

        if ($issued_count > 0) {
            $error = "Cannot delete member. They currently have {$issued_count} book(s) issued.";
        } elseif ($fine_count > 0) {
            $error = "Cannot delete member. They have {$fine_count} outstanding fine(s).";
        } else {
            // Deleting the member will cascade delete reservations and fine history
            $stmt = $conn->prepare("DELETE FROM tbl_members WHERE member_id = ?");
            $stmt->bind_param("i", $member_id);
            
            if ($stmt->execute()) {
                $message = "Member successfully deleted.";
            } else {
                $error = "Error deleting member: " . $conn->error;
            }
        }
    }
}

// --- Fetch Members for View/Search (This table still only shows members) ---

$search_query = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM tbl_members";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " WHERE full_name LIKE ? OR member_uid LIKE ? OR email LIKE ? OR department LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = 'ssss';
}

$sql .= " ORDER BY member_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members_result = $stmt->get_result();

admin_header('Member Management');
?>

<style>
    /* * ==================================
     * NEW ATTRACTIVE STYLING (Copied from books.php)
     * ==================================
     */

    /* Card Styling */
    .card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
        padding: 30px;
    }
    .card h2 {
        font-size: 24px;
        font-weight: 600;
        color: #343a40;
        margin-top: 0;
        margin-bottom: 25px;
    }
    .card h2 i {
        margin-right: 10px;
        color: #007bff;
    }
    .mt-4 {
        margin-top: 25px;
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

    /* Modern Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }

    /* Modern Form Inputs (Used in all forms) */
    .form-group {
        position: relative;
        margin-bottom: 15px;
    }
    .form-group .form-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
        font-size: 16px;
        z-index: 2; /* Ensure icon is above select */
    }
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="number"],
    .form-group input[type="search"],
    .form-group select {
        width: 100%;
        padding: 14px 14px 14px 45px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 16px;
        font-family: 'Poppins', sans-serif;
        background: #f9f9f9;
        transition: all 0.3s ease;
    }
    
    /* Select specific styling */
    .form-group select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236c757d%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 10px;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    .form-group input:read-only,
    .form-group input:disabled {
        background-color: #e9ecef;
        cursor: not-allowed;
    }

    /* Checkbox styling */
    .form-group-checkbox {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        background: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #ddd;
    }
    .form-group-checkbox input[type="checkbox"] {
        margin-right: 12px;
        width: 18px;
        height: 18px;
        accent-color: #007bff;
        flex-shrink: 0;
    }
    .form-group-checkbox label {
        margin: 0;
        font-weight: 500;
        color: #495057;
        font-size: 15px;
    }
    .form-group-checkbox .form-text {
        margin-left: auto;
        color: #6c757d;
        font-size: 14px;
        flex-shrink: 0;
        padding-left: 10px;
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
        font-size: 18px;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
    }
    .btn-gradient:hover {
        background: linear-gradient(90deg, #0056b3, #007bff);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
    }
    .btn-gradient i {
        margin-right: 8px;
    }

    /* Standard Button Styles */
    .btn {
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
    }
    .btn-secondary { background-color: #6c757d; color: white; }
    .btn-secondary:hover { background-color: #5a6268; }
    .btn-danger { background-color: #dc3545; color: white; }
    .btn-danger:hover { background-color: #c82333; }
    .btn-info { background-color: #17a2b8; color: white; }
    .btn-info:hover { background-color: #138496; }
    .btn-sm { padding: 6px 12px; font-size: 13px; }
    .btn i { margin-right: 5px; }


    /* Search Form */
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 25px;
    }
    .search-form .form-group {
        flex-grow: 1;
        margin-bottom: 0;
    }
    .search-form .btn {
        height: 49px;
        flex-shrink: 0;
    }

    /* Modern Data Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .data-table th, .data-table td {
        padding: 14px 16px;
        text-align: left;
        vertical-align: middle;
        font-size: 15px;
    }
    .data-table thead tr {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }
    .data-table thead th {
        font-weight: 600;
        color: #495057;
    }
    .data-table tbody tr {
        border-bottom: 1px solid #f1f1f1;
        transition: background-color 0.2s ease;
    }
    .data-table tbody tr:last-child {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background-color: #f9f9f9;
    }
    .data-table td:last-child {
        white-space: nowrap;
    }

    /* Table Badges */
    .badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 13px;
    }
    .badge-success {
        background-color: #e6f7ec;
        color: #218838;
    }
    .badge-danger {
        background-color: #f8d7da;
        color: #c82333;
    }

    /* Modal Styling */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6);
        animation: fadeInBackdrop 0.3s ease;
    }
    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 30px 35px;
        border-radius: 12px;
        width: 90%;
        max-width: 550px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        animation: slideInModal 0.4s ease-out;
    }
    .modal-content h3 {
        font-size: 22px;
        font-weight: 600;
        margin-top: 0;
        margin-bottom: 25px;
    }
    .close-btn {
        color: #aaa;
        float: right;
        font-size: 30px;
        font-weight: bold;
        line-height: 1;
        transition: color 0.3s ease;
    }
    .close-btn:hover,
    .close-btn:focus {
        color: #333;
        text-decoration: none;
        cursor: pointer;
    }

    @keyframes fadeInBackdrop {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideInModal {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="card">
    <h2><i class="fas fa-user-plus"></i> Add New User</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add_user">
        
        <div class="form-group">
            <i class="form-icon fas fa-users-cog"></i>
            <select id="account_type" name="account_type" required onchange="toggleAccountFields()">
                <option value="member" selected>Member (Student/Faculty)</option>
                <option value="admin">Admin (Librarian)</option>
            </select>
        </div>
        
        <div class="form-group">
            <i class="form-icon fas fa-user"></i>
            <input type="text" id="full_name" name="full_name" placeholder="Full Name" required>
        </div>
        
        <div class="form-group">
            <i class="form-icon fas fa-id-card"></i>
            <input type="text" id="member_uid" name="member_uid" placeholder="Username / Student ID" required>
        </div>

        <div id="member_fields" class="form-group full-width">
            <div class="form-grid"> <div class="form-group" style="margin-bottom: 0;">
                    <i class="form-icon fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Email (Optional)">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <i class="form-icon fas fa-building"></i>
                    <input type="text" id="department" name="department" placeholder="Department" required>
                </div>
            </div>
        </div>

        <div id="admin_fields" style="display: none;" class="form-group full-width">
            <?php if ($is_logged_in_super_admin): // Only show checkbox to other super admins ?>
                <div class="form-group-checkbox">
                    <input type="checkbox" name="is_super_admin" value="1" id="is_super_admin">
                    <label for="is_super_admin">Create as Super Admin</label>
                    <span class="form-text text-muted">Can change system settings.</span>
                </div>
            <?php else: ?>
                <div class="form-group-checkbox">
                    <span class="form-text text-muted">Only a Super Admin can create other Super Admins.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group full-width">
            <i class="form-icon fas fa-key"></i>
            <input type="text" id="password" name="password" value="password" placeholder="Initial Password">
        </div>

        <div class="form-group full-width">
            <button type="submit" class="btn-gradient"><i class="fas fa-user-plus"></i> Register User</button>
        </div>
    </form>
</div>

<div class="card mt-4">
    <h2><i class="fas fa-users"></i> View & Search Members</h2>
    
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-search"></i>
            <input type="search" name="search" placeholder="Search by Name, ID, Email, or Department..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="members.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Member ID</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members_result->num_rows > 0): ?>
                    <?php while ($member = $members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $member['member_id']; ?></td>
                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['member_uid']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo htmlspecialchars($member['department']); ?></td>
                            <td><span class="badge badge-<?php echo $member['status'] == 'Active' ? 'success' : 'danger'; ?>"><?php echo $member['status']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" 
                                    data-id="<?php echo $member['member_id']; ?>" 
                                    data-uid="<?php echo htmlspecialchars($member['member_uid']); ?>"
                                    data-name="<?php echo htmlspecialchars($member['full_name']); ?>" 
                                    data-email="<?php echo htmlspecialchars($member['email']); ?>" 
                                    data-dept="<?php echo htmlspecialchars($member['department']); ?>"
                                    data-status="<?php echo htmlspecialchars($member['status']); ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this member? This will also delete their history and reservations.');">
                                    <input type="hidden" name="action" value="delete_member">
                                    <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">No members found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Edit Member Details</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_member">
            <input type="hidden" id="edit_member_id" name="member_id">
            
            <div class="form-group">
                <i class="form-icon fas fa-id-card"></i>
                <input type="text" id="edit_member_uid_display" placeholder="Member ID (Username)" readonly disabled>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-user"></i>
                <input type="text" id="edit_full_name" name="full_name" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-envelope"></i>
                <input type="email" id="edit_email" name="email" placeholder="Email (Optional)">
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-building"></i>
                <input type="text" id="edit_department" name="department" placeholder="Department" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-toggle-on"></i>
                <select id="edit_status" name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <button type="submit" class="btn-gradient"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<script>
function toggleAccountFields() {
    const accountType = document.getElementById('account_type').value;
    const memberFields = document.getElementById('member_fields');
    const adminFields = document.getElementById('admin_fields');
    const deptInput = document.getElementById('department');

    if (accountType === 'member') {
        memberFields.style.display = 'block';
        adminFields.style.display = 'none';
        // Make department required for members
        deptInput.setAttribute('required', 'required');
    } else { // admin
        memberFields.style.display = 'none';
        adminFields.style.display = 'block';
        // Make department not required for admins
        deptInput.removeAttribute('required');
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', toggleAccountFields);

// Modal handling script (unchanged)
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editModal');
    const closeBtn = modal.querySelector('.close-btn');
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const uid = this.getAttribute('data-uid');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const dept = this.getAttribute('data-dept');
            const status = this.getAttribute('data-status');

            document.getElementById('edit_member_id').value = id;
            document.getElementById('edit_member_uid_display').value = uid;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_department').value = dept;
            document.getElementById('edit_status').value = status;

            modal.style.display = 'block';
        });
    });

    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php
admin_footer();
close_db_connection($conn);
?>