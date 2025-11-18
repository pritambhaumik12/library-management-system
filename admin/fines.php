<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';
$currency = get_setting($conn, 'currency_symbol');
$search_query = trim($_GET['search'] ?? ''); // Global search for both tables

// --- Handle Fine Collection ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fine_id = (int)($_POST['fine_id'] ?? 0);

    if ($action === 'collect_fine') {
        $payment_method = trim($_POST['payment_method'] ?? '');
        $transaction_id = trim($_POST['transaction_id'] ?? NULL);
        $admin_id = $_SESSION['admin_id'];
        $paid_on = date('Y-m-d H:i:s');

        if (empty($payment_method)) {
            $error = "Payment method is required.";
        } elseif ($payment_method !== 'Cash' && empty($transaction_id)) {
            $error = "Transaction ID is required for non-cash payments.";
        } else {
            $stmt = $conn->prepare("UPDATE tbl_fines SET payment_status = 'Paid', payment_method = ?, transaction_id = ?, paid_on = ?, collected_by_admin_id = ? WHERE fine_id = ? AND payment_status = 'Pending'");
            $stmt->bind_param("sssii", $payment_method, $transaction_id, $paid_on, $admin_id, $fine_id);
            
            if ($stmt->execute() && $conn->affected_rows > 0) {
                $message = "Fine ID {$fine_id} successfully marked as Paid. You can now generate the receipt.";
            } else {
                $error = "Error collecting fine or fine was already paid.";
            }
        }
    }
}

// --- 1. Fetch Outstanding Fines (With Search) ---
$sql_pending = "
    SELECT 
        tf.fine_id, tf.fine_amount, tf.fine_date, tf.payment_status,
        tm.full_name AS member_name, tm.member_uid,
        tb.title AS book_title,
        tbc.book_uid
    FROM 
        tbl_fines tf
    JOIN 
        tbl_members tm ON tf.member_id = tm.member_id
    JOIN 
        tbl_circulation tc ON tf.circulation_id = tc.circulation_id
    JOIN 
        tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
";

$pending_where = ["tf.payment_status = 'Pending'"];
$pending_params = [];
$pending_types = '';

if (!empty($search_query)) {
    // Search logic for Pending
    $pending_where[] = "(tf.fine_id = ? OR tm.full_name LIKE ? OR tm.member_uid LIKE ? OR tb.title LIKE ? OR tbc.book_uid LIKE ? OR tf.fine_amount = ?)";
    $term_like = "%" . $search_query . "%";
    $pending_params = [$search_query, $term_like, $term_like, $term_like, $term_like, $search_query];
    $pending_types = 'ssssss';
}

$sql_pending .= " WHERE " . implode(' AND ', $pending_where);
$sql_pending .= " ORDER BY tf.fine_date ASC";

$stmt_pending = $conn->prepare($sql_pending);
if (!empty($pending_params)) {
    $stmt_pending->bind_param($pending_types, ...$pending_params);
}
$stmt_pending->execute();
$pending_fines_result = $stmt_pending->get_result();


// --- 2. Fetch Paid Fines (With Search including Method/Transaction) ---
$sql_paid = "
    SELECT 
        tf.fine_id, tf.fine_amount, tf.paid_on, tf.payment_method, tf.transaction_id,
        tm.full_name AS member_name, tm.member_uid,
        tb.title AS book_title,
        tbc.book_uid
    FROM 
        tbl_fines tf
    JOIN 
        tbl_members tm ON tf.member_id = tm.member_id
    JOIN 
        tbl_circulation tc ON tf.circulation_id = tc.circulation_id
    JOIN 
        tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
";

$paid_where = ["tf.payment_status = 'Paid'"];
$paid_params = [];
$paid_types = '';

if (!empty($search_query)) {
    // Search logic for Paid (Includes Method and Transaction ID)
    $paid_where[] = "(tf.fine_id = ? OR tm.full_name LIKE ? OR tm.member_uid LIKE ? OR tf.fine_amount = ? OR tf.payment_method LIKE ? OR tf.transaction_id LIKE ?)";
    $term_like = "%" . $search_query . "%";
    $paid_params = [$search_query, $term_like, $term_like, $search_query, $term_like, $term_like];
    $paid_types = 'ssssss';
}

$sql_paid .= " WHERE " . implode(' AND ', $paid_where);
$sql_paid .= " ORDER BY tf.paid_on DESC";

// Limit results if NO search is active to keep page clean
if (empty($search_query)) {
    $sql_paid .= " LIMIT 10";
}

$stmt_paid = $conn->prepare($sql_paid);
if (!empty($paid_params)) {
    $stmt_paid->bind_param($paid_types, ...$paid_params);
}
$stmt_paid->execute();
$paid_fines_result = $stmt_paid->get_result();


admin_header('Fine Management');
?>

<style>
    /* * ==================================
     * NEW ATTRACTIVE STYLING
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
    .text-muted {
        font-size: 16px;
        color: #6c757d;
        margin-top: -15px;
        margin-bottom: 25px;
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
    .btn-success { background-color: #28a745; color: white; }
    .btn-success:hover { background-color: #218838; }
    .btn-info { background-color: #17a2b8; color: white; }
    .btn-info:hover { background-color: #138496; }
    .btn-sm { padding: 6px 12px; font-size: 13px; }
    .btn i { margin-right: 5px; }

    /* --- Search Form Styles --- */
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
    .form-group input[type="search"] {
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
    .form-group input[type="search"]:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    .btn-secondary { background-color: #6c757d; color: white; }
    .btn-secondary:hover { background-color: #5a6268; }
    .btn-danger { background-color: #dc3545; color: white; }
    .btn-danger:hover { background-color: #c82333; }

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

    /* Status Badges */
    .badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 13px;
        text-transform: capitalize;
    }
    .badge-warning { background-color: #fff8e6; color: #e88b00; }
    .badge-danger { background-color: #f8d7da; color: #c82333; font-weight: 700; }

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
    .modal-content .text-danger { color: #dc3545; font-size: 18px; }
    .modal-content p { font-size: 16px; }

    /* Modal Form Styling */
    .form-group {
        position: relative;
        margin-bottom: 20px;
    }
    .form-group .form-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #aaa;
        font-size: 16px;
        z-index: 2;
    }
    .form-group input[type="text"], .form-group select {
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
    .form-group select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236c757d%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 10px;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }
    
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

    @keyframes fadeInBackdrop { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideInModal { from { opacity: 0; transform: translateY(-50px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="card">
    <h2 style="margin-bottom: 20px;"><i class="fas fa-search"></i> Search Fines</h2>
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-search"></i>
            <input type="search" name="search" placeholder="Search by Member, Fine ID, Amount, Method, Transaction ID..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="fines.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="card mt-4">
    <h2><i class="fas fa-money-bill-wave" style="color: #dc3545;"></i> Outstanding Fines</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fine ID</th>
                    <th>Member Name (ID)</th>
                    <th>Book Title (UID)</th>
                    <th>Amount</th>
                    <th>Fine Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pending_fines_result->num_rows > 0): ?>
                    <?php while ($fine = $pending_fines_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $fine['fine_id']; ?></td>
                            <td><?php echo htmlspecialchars($fine['member_name']) . " (" . htmlspecialchars($fine['member_uid']) . ")"; ?></td>
                            <td><?php echo htmlspecialchars($fine['book_title']) . " (" . htmlspecialchars($fine['book_uid']) . ")"; ?></td>
                            <td><span class="badge badge-danger"><?php echo $currency . number_format($fine['fine_amount'], 2); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($fine['fine_date'])); ?></td>
                            <td><span class="badge badge-warning"><?php echo $fine['payment_status']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-success collect-fine-btn" 
                                    data-id="<?php echo $fine['fine_id']; ?>" 
                                    data-member="<?php echo htmlspecialchars($fine['member_name']); ?>"
                                    data-amount="<?php echo number_format($fine['fine_amount'], 2); ?>"
                                    data-currency="<?php echo $currency; ?>">
                                    <i class="fas fa-hand-holding-usd"></i> Collect Fine
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            <?php echo !empty($search_query) ? 'No outstanding fines matching search.' : 'No outstanding fines.'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <h2><i class="fas fa-receipt" style="color: #28a745;"></i> Paid Fines History</h2>
    <p class="text-muted">
        <?php echo !empty($search_query) ? 'Showing search results for paid fines.' : 'Showing the 10 most recent paid fines.'; ?>
    </p>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fine ID</th>
                    <th>Member Name</th>
                    <th>Amount</th>
                    <th>Paid On</th>
                    <th>Method</th>
                    <th>Transaction ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paid_fines_result->num_rows > 0): ?>
                    <?php while ($fine = $paid_fines_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $fine['fine_id']; ?></td>
                            <td><?php echo htmlspecialchars($fine['member_name']); ?></td>
                            <td><?php echo $currency . number_format($fine['fine_amount'], 2); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($fine['paid_on'])); ?></td>
                            <td><?php echo htmlspecialchars($fine['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($fine['transaction_id'] ?? '-'); ?></td>
                            <td>
                                <a href="generate_receipt.php?fine_id=<?php echo $fine['fine_id']; ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-print"></i> Receipt</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                             <?php echo !empty($search_query) ? 'No paid fines matching search.' : 'No paid fines found.'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="collectFineModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Collect Fine Payment</h3>
        <form method="POST">
            <input type="hidden" name="action" value="collect_fine">
            <input type="hidden" id="collect_fine_id" name="fine_id">
            
            <p>Member: <strong id="modal_member_name"></strong></p>
            <p>Amount Due: <strong id="modal_fine_amount" class="text-danger"></strong></p>

            <div class="form-group">
                <i class="form-icon fas fa-credit-card"></i>
                <select id="payment_method" name="payment_method" required onchange="toggleTransactionId(this.value)">
                    <option value="">-- Select Method --</option>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    <option value="Online Transfer">Online Transfer</option>
                </select>
            </div>
            
            <div class="form-group" id="transaction_id_group" style="display:none;">
                <i class="form-icon fas fa-hashtag"></i>
                <input type="text" id="transaction_id" name="transaction_id" placeholder="Transaction ID (Required)">
            </div>
            
            <button type="submit" class="btn-gradient"><i class="fas fa-check"></i> Confirm Payment</button>
        </form>
    </div>
</div>

<script>
function toggleTransactionId(method) {
    const group = document.getElementById('transaction_id_group');
    const input = document.getElementById('transaction_id');
    if (method !== 'Cash' && method !== '') {
        group.style.display = 'block';
        input.setAttribute('required', 'required');
    } else {
        group.style.display = 'none';
        input.removeAttribute('required');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('collectFineModal');
    const closeBtn = modal.querySelector('.close-btn');
    const collectButtons = document.querySelectorAll('.collect-fine-btn');

    collectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const member = this.getAttribute('data-member');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');

            document.getElementById('collect_fine_id').value = id;
            document.getElementById('modal_member_name').textContent = member;
            document.getElementById('modal_fine_amount').textContent = currency + amount;
            
            // Reset form fields
            document.getElementById('payment_method').value = '';
            document.getElementById('transaction_id').value = '';
            toggleTransactionId('');

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