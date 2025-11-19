<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';
// Check if the currently logged-in user is a super admin
$is_logged_in_super_admin = is_super_admin($conn);

// --- Handle User Actions (Add, Edit, Delete, Reset Password) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- RESET PASSWORD ---
    if ($action === 'reset_password') {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $target_type = $_POST['target_type'] ?? ''; // 'member' or 'admin'
        $new_password = trim($_POST['new_password'] ?? '');

        if (empty($new_password)) {
            $error = "New password cannot be empty.";
        } else {
            if ($target_type === 'member') {
                // Admins and Super Admins can reset Member passwords
                $stmt = $conn->prepare("UPDATE tbl_members SET password = ? WHERE member_id = ?");
                $stmt->bind_param("si", $new_password, $target_id);
                if ($stmt->execute()) {
                    $message = "Member password updated successfully.";
                } else {
                    $error = "Error updating password: " . $conn->error;
                }
            } elseif ($target_type === 'admin') {
                // ONLY Super Admin can reset Admin passwords
                if ($is_logged_in_super_admin) {
                    $stmt = $conn->prepare("UPDATE tbl_admin SET password = ? WHERE admin_id = ?");
                    $stmt->bind_param("si", $new_password, $target_id);
                    if ($stmt->execute()) {
                        $message = "Librarian/System Admin password updated successfully.";
                    } else {
                        $error = "Error updating password: " . $conn->error;
                    }
                } else {
                    $error = "Access Denied: Only System Administrators can reset Librarian passwords.";
                }
            }
        }
    }

    // --- ADD USER (MEMBER OR ADMIN) ---
    elseif ($action === 'add_user') {
        $account_type = $_POST['account_type'] ?? 'member';
        $full_name = trim($_POST['full_name'] ?? '');
        $username_or_uid = trim($_POST['member_uid'] ?? ''); 
        $password = trim($_POST['password'] ?? 'password'); 

        if (empty($full_name) || empty($username_or_uid)) {
            $error = "Full Name and Username/Student ID are required.";
        } else {
            if ($account_type === 'member') {
                $email = trim($_POST['email'] ?? '');
                $department = trim($_POST['department'] ?? '');

                if (empty($department)) {
                     $error = "Department is required for members.";
                } else {
                    $stmt_check = $conn->prepare("SELECT member_id FROM tbl_members WHERE member_uid = ?");
                    $stmt_check->bind_param("s", $username_or_uid);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $error = "Member ID already exists.";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO tbl_members (member_uid, password, full_name, email, department) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $username_or_uid, $password, $full_name, $email, $department);
                        if ($stmt->execute()) {
                            $message = "Member '{$full_name}' added successfully.";
                        } else {
                            $error = "Error adding member: " . $conn->error;
                        }
                    }
                }
            } elseif ($account_type === 'admin') {
                $is_super_admin_to_create = ($is_logged_in_super_admin && isset($_POST['is_super_admin'])) ? 1 : 0;
                $role_name = $is_super_admin_to_create ? "System Administrator" : "Librarian";

                $stmt_check = $conn->prepare("SELECT admin_id FROM tbl_admin WHERE username = ?");
                $stmt_check->bind_param("s", $username_or_uid);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $error = "Username already exists.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO tbl_admin (username, password, full_name, is_super_admin) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $username_or_uid, $password, $full_name, $is_super_admin_to_create);
                    if ($stmt->execute()) {
                        $message = "{$role_name} '{$full_name}' added successfully.";
                    } else {
                        $error = "Error adding user: " . $conn->error;
                    }
                }
            }
        }
    }
    // --- EDIT MEMBER ---
    elseif ($action === 'edit_member') {
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
    } 
    // --- DELETE MEMBER ---
    elseif ($action === 'delete_member') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $stmt_check_issued = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_circulation WHERE member_id = ? AND status = 'Issued'");
        $stmt_check_issued->bind_param("i", $member_id);
        $stmt_check_issued->execute();
        $issued_count = $stmt_check_issued->get_result()->fetch_assoc()['count'];

        $stmt_check_fines = $conn->prepare("SELECT COUNT(*) AS count FROM tbl_fines WHERE member_id = ? AND payment_status = 'Pending'");
        $stmt_check_fines->bind_param("i", $member_id);
        $stmt_check_fines->execute();
        $fine_count = $stmt_check_fines->get_result()->fetch_assoc()['count'];

        if ($issued_count > 0) {
            $error = "Cannot delete member. They have {$issued_count} book(s) issued.";
        } elseif ($fine_count > 0) {
            $error = "Cannot delete member. They have outstanding fines.";
        } else {
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

// --- Fetch Data for Views ---
$search_query = trim($_GET['search'] ?? '');

// 1. Fetch Members
$sql_members = "SELECT * FROM tbl_members";
$params_mem = [];
$types_mem = '';
if (!empty($search_query)) {
    $sql_members .= " WHERE full_name LIKE ? OR member_uid LIKE ? OR department LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params_mem = [$search_term, $search_term, $search_term];
    $types_mem = 'sss';
}
$sql_members .= " ORDER BY member_id DESC";
$stmt_mem = $conn->prepare($sql_members);
if (!empty($params_mem)) $stmt_mem->bind_param($types_mem, ...$params_mem);
$stmt_mem->execute();
$members_result = $stmt_mem->get_result();

// 2. Fetch Admins (Librarians & SysAdmins)
$sql_admins = "SELECT * FROM tbl_admin";
$params_adm = [];
$types_adm = '';
if (!empty($search_query)) {
    $sql_admins .= " WHERE full_name LIKE ? OR username LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params_adm = [$search_term, $search_term];
    $types_adm = 'ss';
}
$sql_admins .= " ORDER BY admin_id DESC";
$stmt_adm = $conn->prepare($sql_admins);
if (!empty($params_adm)) $stmt_adm->bind_param($types_adm, ...$params_adm);
$stmt_adm->execute();
$admins_result = $stmt_adm->get_result();

admin_header('User Management');
?>

<style>
    /* Previous styles retained */
    .card { background: #ffffff; border-radius: 12px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07); padding: 30px; }
    .card h2 { font-size: 24px; font-weight: 600; color: #343a40; margin-top: 0; margin-bottom: 25px; }
    .card h2 i { margin-right: 10px; color: #007bff; }
    .mt-4 { margin-top: 25px; }
    .alert { padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; font-size: 15px; font-weight: 500; }
    .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
    .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-group { position: relative; margin-bottom: 15px; }
    .form-group .form-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 16px; z-index: 2; }
    .form-group input, .form-group select { width: 100%; padding: 14px 14px 14px 45px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 16px; background: #f9f9f9; transition: all 0.3s ease; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #007bff; background: #fff; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }
    .form-group-checkbox { display: flex; align-items: center; padding: 12px 15px; background: #f9f9f9; border-radius: 8px; border: 1px solid #ddd; }
    .form-group-checkbox input[type="checkbox"] { margin-right: 12px; width: 18px; height: 18px; flex-shrink: 0; }
    .btn-gradient { width: 100%; padding: 14px; background: linear-gradient(90deg, #007bff, #0056b3); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 18px; font-weight: 600; transition: all 0.3s ease; }
    .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3); }
    .btn { padding: 10px 18px; font-size: 14px; font-weight: 500; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s ease; }
    .btn-secondary { background-color: #6c757d; color: white; }
    .btn-danger { background-color: #dc3545; color: white; }
    .btn-info { background-color: #17a2b8; color: white; }
    .btn-warning { background-color: #ffc107; color: #212529; } 
    .btn-warning:hover { background-color: #e0a800; }
    .btn-sm { padding: 6px 12px; font-size: 13px; }
    .search-form { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
    .search-form .form-group { flex-grow: 1; margin-bottom: 0; }
    .search-form .btn { height: 49px; flex-shrink: 0; }
    .table-responsive { width: 100%; overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .data-table th, .data-table td { padding: 14px 16px; text-align: left; vertical-align: middle; font-size: 15px; }
    .data-table thead tr { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
    .data-table tbody tr { border-bottom: 1px solid #f1f1f1; }
    .data-table tbody tr:hover { background-color: #f9f9f9; }
    .badge { padding: 5px 10px; border-radius: 15px; font-weight: 600; font-size: 13px; }
    .badge-success { background-color: #e6f7ec; color: #218838; }
    .badge-danger { background-color: #f8d7da; color: #c82333; }
    .badge-warning { background-color: #fff3cd; color: #856404; }
    
    /* Modal Styling */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.6); }
    .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px 35px; border-radius: 12px; width: 90%; max-width: 550px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15); }
    .close-btn { color: #aaa; float: right; font-size: 30px; font-weight: bold; cursor: pointer; }
    .close-btn:hover { color: #333; }

    /* TABS STYLING */
    .tab-container { margin-top: 20px; border-bottom: 1px solid #ddd; margin-bottom: 20px; display: flex; gap: 10px;}
    .tab-button {
        background-color: transparent; border: none; outline: none; cursor: pointer;
        padding: 14px 20px; font-size: 16px; font-weight: 600; color: #6c757d;
        border-bottom: 3px solid transparent; transition: 0.3s;
    }
    .tab-button:hover { color: #007bff; }
    .tab-button.active { color: #007bff; border-bottom: 3px solid #007bff; }
    .tab-content { display: none; animation: fadeIn 0.5s; }
    .tab-content.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Password Confirmation Styling */
    .password-display {
        background: #f8f9fa;
        border: 1px dashed #ccc;
        padding: 5px 10px;
        font-family: monospace;
        font-size: 1.2em;
        color: #d63384;
        border-radius: 4px;
        margin-left: 5px;
    }
    .confirm-details p {
        font-size: 16px;
        margin: 10px 0;
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
                <option value="admin">Librarian (Staff)</option>
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
            <?php if ($is_logged_in_super_admin): ?>
                <div class="form-group-checkbox">
                    <input type="checkbox" name="is_super_admin" value="1" id="is_super_admin">
                    <label for="is_super_admin">Create as System Administrator</label>
                </div>
            <?php else: ?>
                <div class="form-group-checkbox">
                    <span class="form-text text-muted">Only a System Administrator can create other System Administrators.</span>
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
    <h2><i class="fas fa-users"></i> Manage Users</h2>
    
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-search"></i>
            <input type="search" name="search" placeholder="Search by Name, ID, or Department..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="members.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="tab-container">
        <button class="tab-button active" onclick="openTab('tabMembers')">Members List</button>
        <button class="tab-button" onclick="openTab('tabAdmins')">Librarians & System Administrator</button>
    </div>

    <div id="tabMembers" class="tab-content active">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Member ID</th>
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
                                <td><?php echo htmlspecialchars($member['department']); ?></td>
                                <td><span class="badge badge-<?php echo $member['status'] == 'Active' ? 'success' : 'danger'; ?>"><?php echo $member['status']; ?></span></td>
                                <td style="white-space: nowrap;">
                                    <button class="btn btn-sm btn-info edit-btn" 
                                        data-id="<?php echo $member['member_id']; ?>" 
                                        data-uid="<?php echo htmlspecialchars($member['member_uid']); ?>"
                                        data-name="<?php echo htmlspecialchars($member['full_name']); ?>" 
                                        data-email="<?php echo htmlspecialchars($member['email']); ?>" 
                                        data-dept="<?php echo htmlspecialchars($member['department']); ?>"
                                        data-status="<?php echo htmlspecialchars($member['status']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="btn btn-sm btn-warning pass-btn" 
                                        data-id="<?php echo $member['member_id']; ?>" 
                                        data-type="member"
                                        data-name="<?php echo htmlspecialchars($member['full_name']); ?>">
                                        <i class="fas fa-key"></i>
                                    </button>

                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this member?');">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No members found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tabAdmins" class="tab-content">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($admins_result->num_rows > 0): ?>
                        <?php while ($admin = $admins_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $admin['admin_id']; ?></td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                <td>
                                    <?php if($admin['is_super_admin']): ?>
                                        <span class="badge badge-success">System Administrator</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#e9ecef; color: #333;">Librarian</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_logged_in_super_admin): ?>
                                        <button class="btn btn-sm btn-warning pass-btn" 
                                            data-id="<?php echo $admin['admin_id']; ?>" 
                                            data-type="admin"
                                            data-name="<?php echo htmlspecialchars($admin['full_name']); ?>">
                                            <i class="fas fa-key"></i> Reset Pass
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:12px;">Restricted</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
        <h3>Edit Member Details</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_member">
            <input type="hidden" id="edit_member_id" name="member_id">
            
            <div class="form-group">
                <i class="form-icon fas fa-id-card"></i>
                <input type="text" id="edit_member_uid_display" disabled style="background:#e9ecef">
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-user"></i>
                <input type="text" id="edit_full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-envelope"></i>
                <input type="email" id="edit_email" name="email">
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-building"></i>
                <input type="text" id="edit_department" name="department" required>
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

<!-- PASSWORD RESET INPUT MODAL -->
<div id="passModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <span class="close-btn" onclick="closeModal('passModal')">&times;</span>
        <h3>Reset Password</h3>
        <p id="pass_user_name_display" style="margin-bottom: 20px; color: #666;"></p>
        
        <form method="POST" id="resetPasswordForm">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="pass_target_id" name="target_id">
            <input type="hidden" id="pass_target_type" name="target_type">
            
            <div class="form-group">
                <i class="form-icon fas fa-lock"></i>
                <input type="text" id="new_password_input" name="new_password" placeholder="Enter New Password" required>
            </div>
            
            <button type="button" class="btn-gradient" onclick="triggerPasswordConfirmation()">
                <i class="fas fa-arrow-right"></i> Proceed to Update
            </button>
        </form>
    </div>
</div>

<!-- CONFIRMATION MODAL -->
<div id="confirmPassModal" class="modal" style="z-index: 1050;">
    <div class="modal-content" style="max-width: 450px; border-top: 5px solid #ffc107;">
        <span class="close-btn" onclick="closeModal('confirmPassModal')">&times;</span>
        <h3 style="color: #333;"><i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i> Confirm Update</h3>
        
        <div class="confirm-details">
            <p>Are you sure you want to update the password for:</p>
            <p><strong>User:</strong> <span id="conf_name"></span></p>
            <p><strong>Role:</strong> <span id="conf_role" class="badge"></span></p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
            <p><strong>New Password:</strong> <span id="conf_pass" class="password-display"></span></p>
        </div>

        <div style="margin-top: 25px; display: flex; gap: 10px;">
            <!-- Cancel Button -->
            <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('confirmPassModal')">Cancel</button>
            <!-- Final Confirm Button -->
            <button type="button" class="btn-gradient" style="flex: 1;" onclick="submitFinalPasswordReset()">
                <i class="fas fa-check-circle"></i> Confirm Update
            </button>
        </div>
    </div>
</div>

<script>
// --- Tab Logic ---
function openTab(tabName) {
    var i;
    var x = document.getElementsByClassName("tab-content");
    var buttons = document.getElementsByClassName("tab-button");
    for (i = 0; i < x.length; i++) {
        x[i].style.display = "none";
        x[i].classList.remove("active");
    }
    for (i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove("active");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    
    // Highlight the clicked button
    event.currentTarget.classList.add("active");
}

// --- Form Toggle ---
function toggleAccountFields() {
    const accountType = document.getElementById('account_type').value;
    const memberFields = document.getElementById('member_fields');
    const adminFields = document.getElementById('admin_fields');
    const deptInput = document.getElementById('department');

    if (accountType === 'member') {
        memberFields.style.display = 'block';
        adminFields.style.display = 'none';
        deptInput.setAttribute('required', 'required');
    } else { 
        memberFields.style.display = 'none';
        adminFields.style.display = 'block';
        deptInput.removeAttribute('required');
    }
}
document.addEventListener('DOMContentLoaded', toggleAccountFields);

// --- Modal Logic ---
function closeModal(modalId) {
    document.getElementById(modalId).style.display = "none";
}

document.addEventListener('DOMContentLoaded', function() {
    // Edit Member Modal
    const editModal = document.getElementById('editModal');
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_member_id').value = this.dataset.id;
            document.getElementById('edit_member_uid_display').value = this.dataset.uid;
            document.getElementById('edit_full_name').value = this.dataset.name;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_department').value = this.dataset.dept;
            document.getElementById('edit_status').value = this.dataset.status;
            editModal.style.display = 'block';
        });
    });

    // --- Password Reset Logic ---

    // Global variables to hold temporary data for confirmation
    let tempPassId = '';
    let tempPassType = '';
    let tempPassName = '';

    // 1. Open the initial Input Modal
    const passModal = document.getElementById('passModal');
    document.querySelectorAll('.pass-btn').forEach(button => {
        button.addEventListener('click', function() {
            tempPassId = this.dataset.id;
            tempPassType = this.dataset.type; // 'member' or 'admin'
            tempPassName = this.dataset.name;
            
            let displayType = (tempPassType === 'admin') ? "LIBRARIAN / SYS ADMIN" : "MEMBER";
            
            document.getElementById('pass_target_id').value = tempPassId;
            document.getElementById('pass_target_type').value = tempPassType;
            document.getElementById('pass_user_name_display').innerText = "Resetting password for: " + tempPassName + " (" + displayType + ")";
            
            // Clear previous input
            document.getElementById('new_password_input').value = '';
            
            passModal.style.display = 'block';
        });
    });

    // 2. Trigger Confirmation Popup
    window.triggerPasswordConfirmation = function() {
        const newPass = document.getElementById('new_password_input').value.trim();
        
        // Basic Validation
        if (newPass === "") {
            alert("Please enter a new password.");
            return;
        }

        // Populate Confirmation Modal Data
        document.getElementById('conf_name').innerText = tempPassName;
        document.getElementById('conf_pass').innerText = newPass;
        
        const roleSpan = document.getElementById('conf_role');
        if(tempPassType === 'admin') {
            roleSpan.innerText = "Administrator / Librarian";
            roleSpan.className = "badge badge-warning"; // Yellow styling
        } else {
            roleSpan.innerText = "Member / Student";
            roleSpan.className = "badge badge-success"; // Green styling
        }

        // Hide Input Modal and Show Confirmation Modal
        document.getElementById('passModal').style.display = 'none';
        document.getElementById('confirmPassModal').style.display = 'block';
    };

    // 3. Final Submission
    window.submitFinalPasswordReset = function() {
        document.getElementById('resetPasswordForm').submit();
    };

    // Close modal on outside click
    window.addEventListener('click', function(event) {
        const editModal = document.getElementById('editModal');
        const passModal = document.getElementById('passModal');
        const confirmPassModal = document.getElementById('confirmPassModal');

        if (event.target == editModal) editModal.style.display = 'none';
        if (event.target == passModal) passModal.style.display = 'none';
        if (event.target == confirmPassModal) confirmPassModal.style.display = 'none';
    });
});
</script>

<?php
admin_footer();
close_db_connection($conn);
?>
