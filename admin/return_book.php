<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';
$step = $_POST['step'] ?? 1;
$book_uid = $_POST['book_uid'] ?? '';
$admin_id = $_SESSION['admin_id'];
$return_date = date('Y-m-d');
$currency = get_setting($conn, 'currency_symbol');

// --- Helper function to fetch circulation record details ---
function fetch_circulation_details($conn, $book_uid) {
    $stmt = $conn->prepare("
        SELECT 
            tc.circulation_id, tc.member_id, tc.issue_date, tc.due_date, tbc.copy_id, tbc.book_id, tb.title, tb.author,
            tm.full_name, tm.member_uid
        FROM 
            tbl_circulation tc
        JOIN 
            tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
        JOIN
            tbl_books tb ON tbc.book_id = tb.book_id
        JOIN
            tbl_members tm ON tc.member_id = tm.member_id
        WHERE 
            tbc.book_uid = ? AND tc.status = 'Issued'
    ");
    $stmt->bind_param("s", $book_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// --- Step 2: Validate Input and Show Details ---
if ($step == 2) {
    $book_uid = trim($_POST['book_uid'] ?? '');
    if (empty($book_uid)) {
        $error = "Please enter the Book ID (Book UID).";
        $step = 1;
    } else {
        $record = fetch_circulation_details($conn, $book_uid);

        if (!$record) {
            $error = "Book ID <strong>{$book_uid}</strong> is not currently issued.";
            $step = 1;
        } else {
            $due_date = $record['due_date'];
            $fine_amount = calculate_fine($conn, $due_date, $return_date);
            $overdue_days = max(0, floor((strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24)));
            $fine_per_day = get_setting($conn, 'fine_per_day');

            $return_details = [
                'circulation_id' => $record['circulation_id'],
                'member_id' => $record['member_id'],
                'copy_id' => $record['copy_id'],
                'book_id' => $record['book_id'],
                'book_uid' => $book_uid,
                'title' => $record['title'],
                'author' => $record['author'],
                'member_name' => $record['full_name'],
                'member_uid' => $record['member_uid'],
                'issue_date' => $record['issue_date'],
                'due_date' => $due_date,
                'fine_amount' => $fine_amount,
                'overdue_days' => $overdue_days,
                'fine_per_day' => $fine_per_day,
            ];
            $step = 3; // Move to confirmation/fine processing step
        }
    }
}
// --- Step 3: Process Fine or Final Return ---
elseif ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $circulation_id = (int)$_POST['circulation_id'];
    $member_id = (int)$_POST['member_id'];
    $copy_id = (int)$_POST['copy_id'];
    $book_id = (int)$_POST['book_id'];
    $fine_amount = (float)$_POST['fine_amount'];
    $book_uid = $_POST['book_uid'];

    // Re-fetch details for display if transaction fails
    $record = fetch_circulation_details($conn, $book_uid);
    if ($record) {
        $due_date = $record['due_date'];
        $overdue_days = max(0, floor((strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24)));
        $fine_per_day = get_setting($conn, 'fine_per_day');
        $return_details = [
            'circulation_id' => $record['circulation_id'],
            'member_id' => $record['member_id'],
            'copy_id' => $record['copy_id'],
            'book_id' => $record['book_id'],
            'book_uid' => $book_uid,
            'title' => $record['title'],
            'author' => $record['author'],
            'member_name' => $record['full_name'],
            'member_uid' => $record['member_uid'],
            'issue_date' => $record['issue_date'],
            'due_date' => $due_date,
            'fine_amount' => $fine_amount,
            'overdue_days' => $overdue_days,
            'fine_per_day' => $fine_per_day,
        ];
    }

    if ($action === 'process_return_and_fine' && $fine_amount > 0) {
        // This action is submitted from the new payment modal
        $payment_method = trim($_POST['payment_method'] ?? '');
        $transaction_id = trim($_POST['transaction_id'] ?? NULL);
        
        if (empty($payment_method)) {
            $error = "Payment method is required.";
            $step = 3; // Stay on confirmation step
        } elseif ($payment_method !== 'Cash' && empty($transaction_id)) {
            $error = "Transaction ID is required for non-cash payments.";
            $step = 3; // Stay on confirmation step
        } else {
            // All-in-one transaction: Create fine, pay fine, and return book
            $conn->begin_transaction();
            try {
                // 1. Insert Fine Record
                $stmt = $conn->prepare("INSERT INTO tbl_fines (circulation_id, member_id, fine_amount, fine_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iids", $circulation_id, $member_id, $fine_amount, $return_date);
                if (!$stmt->execute()) throw new Exception("Error inserting fine record: " . $stmt->error);
                $fine_id = $conn->insert_id;

                // 2. Mark Fine as Paid
                $paid_on = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("UPDATE tbl_fines SET payment_status = 'Paid', payment_method = ?, transaction_id = ?, paid_on = ?, collected_by_admin_id = ? WHERE fine_id = ?");
                $stmt->bind_param("sssii", $payment_method, $transaction_id, $paid_on, $admin_id, $fine_id);
                if (!$stmt->execute()) throw new Exception("Error marking fine as paid: " . $stmt->error);

                // 3. Update Circulation Record (Return Book)
                $stmt = $conn->prepare("UPDATE tbl_circulation SET status = 'Returned', return_date = ?, returned_by_admin_id = ? WHERE circulation_id = ?");
                $stmt->bind_param("sii", $return_date, $admin_id, $circulation_id);
                if (!$stmt->execute()) throw new Exception("Error updating circulation record: " . $stmt->error);

                // 4. Update Book Copy Status to Available
                $stmt = $conn->prepare("UPDATE tbl_book_copies SET status = 'Available' WHERE copy_id = ?");
                $stmt->bind_param("i", $copy_id);
                if (!$stmt->execute()) throw new Exception("Error updating book copy status: " . $stmt->error);

                // 5. Increment available_quantity for the book title
                $stmt = $conn->prepare("UPDATE tbl_books SET available_quantity = available_quantity + 1 WHERE book_id = ?");
                $stmt->bind_param("i", $book_id);
                if (!$stmt->execute()) throw new Exception("Error updating book available quantity: " . $stmt->error);

                $conn->commit();
                $message = "Book returned and fine of {$currency}" . number_format($fine_amount, 2) . " processed successfully. <a href='generate_receipt.php?fine_id={$fine_id}' target='_blank' class='btn btn-sm btn-info' style='margin-left: 10px; text-decoration: none;'><i class='fas fa-print'></i> Print Receipt</a>";
                $step = 1; // Reset to step 1

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Transaction failed: " . $e->getMessage();
                $step = 3; // Stay on fine step
            }
        }

    } elseif ($action === 'confirm_return' && $fine_amount == 0) {
        // Final Return (no fine)
        $conn->begin_transaction();
        try {
            // 1. Update Circulation Record
            $stmt = $conn->prepare("UPDATE tbl_circulation SET status = 'Returned', return_date = ?, returned_by_admin_id = ? WHERE circulation_id = ?");
            $stmt->bind_param("sii", $return_date, $admin_id, $circulation_id);
            if (!$stmt->execute()) {
                throw new Exception("Error updating circulation record: " . $stmt->error);
            }

            // 2. Update Book Copy Status to Available
            $stmt = $conn->prepare("UPDATE tbl_book_copies SET status = 'Available' WHERE copy_id = ?");
            $stmt->bind_param("i", $copy_id);
            if (!$stmt->execute()) {
                throw new Exception("Error updating book copy status: " . $stmt->error);
            }

            // 3. Increment available_quantity for the book title
            $stmt = $conn->prepare("UPDATE tbl_books SET available_quantity = available_quantity + 1 WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            if (!$stmt->execute()) {
                throw new Exception("Error updating book available quantity: " . $stmt->error);
            }

            $conn->commit();
            $message = "Book <strong>{$book_uid}</strong> ({$return_details['title']}) returned successfully and is now available.";
            $step = 1; // Reset to step 1

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Book return failed: " . $e->getMessage();
            $step = 3; // Stay on fine step if possible, otherwise reset
        }
    }
}

admin_header('Return Book');
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
        margin-top: 0;
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

    /* Modern Form Inputs (Used in all forms) */
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
    .form-group input[type="text"],
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
    
    /* Gradient Buttons */
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

    /* --- Step 3 Confirmation Styles --- */
    .confirmation-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    .detail-card {
        background: #f8f9fa;
        padding: 20px 25px;
        border-radius: 8px;
    }
    .detail-card.book-detail {
        border-left: 5px solid #007bff; /* Blue for book */
    }
    .detail-card.member-detail {
        border-left: 5px solid #28a745; /* Green for member */
    }
    .detail-card h4 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 20px;
        font-weight: 600;
        color: #343a40;
    }
    .detail-card p {
        font-size: 16px;
        line-height: 1.6;
        margin: 0 0 12px 0;
        color: #495057;
    }
    .detail-card p:last-child {
        margin-bottom: 0;
    }
    .detail-card p strong {
        font-weight: 600;
        color: #343a40;
        min-width: 120px;
        display: inline-block;
    }
    .badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 13px;
        text-transform: capitalize;
    }
    .badge-success {
        background-color: #e6f7ec;
        color: #218838;
    }
    .badge-danger {
        background-color: #f8d7da;
        color: #c82333;
    }
    
    /* Fine Status Card */
    .fine-status-box {
        padding: 25px;
        border-radius: 12px;
        text-align: center;
    }
    .fine-status-box h3 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
    }
    .fine-status-box.due {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
    }
    .fine-status-box.due .text-danger {
        color: #721c24 !important;
    }
    .fine-status-box.clear {
        background: #d4edda;
        border: 1px solid #c3e6cb;
    }
    .fine-status-box.clear .text-success {
        color: #155724 !important;
    }

    /* --- Action Button Styles --- */
    .confirmation-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .btn-lg {
        padding: 14px 24px;
        font-size: 18px;
        font-weight: 600;
        flex: 1 1 200px; /* Allow buttons to grow and wrap */
        text-align: center;
        text-decoration: none;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-success-gradient {
        background: linear-gradient(90deg, #28a745, #218838);
        color: white;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
    }
    .btn-success-gradient:hover {
        background: linear-gradient(90deg, #218838, #28a745);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
    }
    .btn-danger-gradient {
        background: linear-gradient(90deg, #dc3545, #c82333);
        color: white;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2);
    }
    .btn-danger-gradient:hover {
        background: linear-gradient(90deg, #c82333, #dc3545);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
    }
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
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
    .modal-content .text-danger {
        color: #dc3545;
    }

    @keyframes fadeInBackdrop {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideInModal {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .confirmation-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <h2><i class="fas fa-undo-alt"></i> Return Book</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <p class="text-muted">Enter the Book UID to initiate the return process.</p>
        <form method="POST">
            <input type="hidden" name="step" value="2">
            <div class="form-group">
                <i class="form-icon fas fa-barcode"></i>
                <input type="text" id="book_uid" name="book_uid" required placeholder="e.g., LMS/BOOK/00001-1">
            </div>
            <button type="submit" class="btn-gradient"><i class="fas fa-arrow-right"></i> Next: Check Details</button>
        </form>

    <?php elseif ($step == 3): 
        
        if (!isset($return_details)) {
            // This block handles a rare error where details are lost
            $error = "Error: Circulation record details lost. Please restart the return process.";
            $step = 1;
            echo '<div class="alert alert-danger">' . $error . '</div>';
            echo '<a href="return_book.php" class="btn btn-secondary">Start New Return</a>';
            admin_footer();
            close_db_connection($conn);
            exit;
        }
        
        $fine_amount = $return_details['fine_amount'];
        $overdue_days = $return_details['overdue_days'];
        $fine_per_day = $return_details['fine_per_day'];
    ?>
        <h3 style="font-size: 22px; font-weight: 600; margin-bottom: 20px;">Return Confirmation and Fine Check</h3>
        <p class="text-muted">Please verify the details below.</p>

        <div class="confirmation-grid">
            <div class="detail-card book-detail">
                <h4><i class="fas fa-book"></i> Book Details</h4>
                <p><strong>Book UID:</strong> <?php echo htmlspecialchars($return_details['book_uid']); ?></p>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($return_details['title']); ?></p>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($return_details['author']); ?></p>
                <p><strong>Issue Date:</strong> <?php echo date('M d, Y', strtotime($return_details['issue_date'])); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($return_details['due_date'])); ?></p>
            </div>

            <div class="detail-card member-detail">
                <h4><i class="fas fa-user"></i> Borrower Details</h4>
                <p><strong>Member ID:</strong> <?php echo htmlspecialchars($return_details['member_uid']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($return_details['member_name']); ?></p>
                <p><strong>Overdue:</strong> <span class="badge <?php echo $overdue_days > 0 ? 'badge-danger' : 'badge-success'; ?>"><?php echo $overdue_days; ?> days</span></p>
                <p><strong>Fine Rate:</strong> <?php echo $currency . number_format($fine_per_day, 2); ?> / day</p>
            </div>
        </div>

        <div class="fine-status-box mt-4 <?php echo $fine_amount > 0 ? 'due' : 'clear'; ?>">
            <h3>Fine Status: 
                <?php if ($fine_amount > 0): ?>
                    <span class="text-danger"><?php echo $currency . number_format($fine_amount, 2); ?> Outstanding</span>
                <?php else: ?>
                    <span class="text-success">No Fine Due</span>
                <?php endif; ?>
            </h3>
        </div>

        <div class="confirmation-actions mt-4">
            <?php if ($fine_amount > 0): ?>
                <button type="button" id="collectFineBtn" class="btn-danger-gradient btn-lg"><i class="fas fa-money-bill-wave"></i> Process Fine (Collect Payment)</button>
            <?php else: ?>
                <form method="POST" style="display:contents;"> <input type="hidden" name="step" value="3">
                    <input type="hidden" name="action" value="confirm_return">
                    <input type="hidden" name="circulation_id" value="<?php echo $return_details['circulation_id']; ?>">
                    <input type="hidden" name="member_id" value="<?php echo $return_details['member_id']; ?>">
                    <input type="hidden" name="copy_id" value="<?php echo $return_details['copy_id']; ?>">
                    <input type="hidden" name="book_id" value="<?php echo $return_details['book_id']; ?>">
                    <input type="hidden" name="fine_amount" value="0">
                    <input type="hidden" name="book_uid" value="<?php echo $return_details['book_uid']; ?>">
                    <button type="submit" class="btn-success-gradient btn-lg"><i class="fas fa-check"></i> Confirm Book Return</button>
                </form>
            <?php endif; ?>
            <a href="return_book.php" class="btn btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
        </div>
        
    <?php endif; ?>
</div>

<?php if ($step == 3 && isset($return_details) && $return_details['fine_amount'] > 0): ?>
<div id="collectFineModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Collect Fine and Return Book</h3>
        
        <form method="POST">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="action" value="process_return_and_fine">
            <input type="hidden" name="circulation_id" value="<?php echo $return_details['circulation_id']; ?>">
            <input type="hidden" name="member_id" value="<?php echo $return_details['member_id']; ?>">
            <input type="hidden" name="copy_id" value="<?php echo $return_details['copy_id']; ?>">
            <input type="hidden" name="book_id" value="<?php echo $return_details['book_id']; ?>">
            <input type="hidden" name="fine_amount" value="<?php echo $return_details['fine_amount']; ?>">
            <input type="hidden" name="book_uid" value="<?php echo $return_details['book_uid']; ?>">
            
            <p style="font-size: 16px;">Member: <strong><?php echo htmlspecialchars($return_details['member_name']); ?></strong></p>
            <p style="font-size: 18px;">Amount Due: <strong id="modal_fine_amount" class="text-danger"><?php echo $currency . number_format($return_details['fine_amount'], 2); ?></strong></p>

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
            
            <button type="submit" class="btn-gradient" style="width: 100%;"><i class="fas fa-check"></i> Confirm Payment and Return Book</button>
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
    if (!modal) return; // Exit if modal is not on the page

    const closeBtn = modal.querySelector('.close-btn');
    const collectButton = document.getElementById('collectFineBtn');

    if (collectButton) {
        collectButton.addEventListener('click', function() {
            // Reset form fields on open
            document.getElementById('payment_method').value = '';
            document.getElementById('transaction_id').value = '';
            toggleTransactionId('');
            modal.style.display = 'block';
        });
    }

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
<?php endif; ?>

<?php
admin_footer();
close_db_connection($conn);
?>