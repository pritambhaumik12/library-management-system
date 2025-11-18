<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';
$step = $_POST['step'] ?? 1;
$member_id = $_POST['member_id'] ?? null;
$copy_id = $_POST['copy_id'] ?? null;
$reservation_id = $_POST['reservation_id'] ?? null;
$issue_type = 'book_uid'; // Only direct issue is supported now
$admin_id = $_SESSION['admin_id'];

// --- Step 2: Validate Input and Fetch Details ---
if ($step == 2) {
    $book_uid = trim($_POST['book_uid'] ?? '');
    $member_uid = trim($_POST['member_uid'] ?? '');

    if (empty($book_uid) || empty($member_uid)) {
        $error = "Both Book UID and Member ID are required.";
        $step = 1;
    } else {
        // 1. Validate Book Copy
        $sql_copy = "SELECT tbc.copy_id, tbc.status, tb.title, tb.author, tb.book_id FROM tbl_book_copies tbc JOIN tbl_books tb ON tbc.book_id = tb.book_id WHERE tbc.book_uid = ?";
        $stmt_copy = $conn->prepare($sql_copy);
        $stmt_copy->bind_param("s", $book_uid);
        $stmt_copy->execute();
        $copy_result = $stmt_copy->get_result();

        if ($copy_result->num_rows === 0) {
            $error = "Book Copy (UID: " . htmlspecialchars($book_uid) . ") not found.";
            $step = 1;
        } else {
            $book_copy = $copy_result->fetch_assoc();
            $copy_id = $book_copy['copy_id'];

            if ($book_copy['status'] !== 'Available') {
                $error = "Book Copy (UID: " . htmlspecialchars($book_uid) . ") is currently **" . htmlspecialchars($book_copy['status']) . "** and cannot be issued.";
                $step = 1;
            } else {
                // 2. Validate Member
                $sql_member = "SELECT member_id, full_name, member_uid, email, department FROM tbl_members WHERE member_uid = ? AND status = 'Active'";
                $stmt_member = $conn->prepare($sql_member);
                $stmt_member->bind_param("s", $member_uid);
                $stmt_member->execute();
                $member_result = $stmt_member->get_result();

                if ($member_result->num_rows === 0) {
                    $error = "Member ID (" . htmlspecialchars($member_uid) . ") not found or member is inactive.";
                    $step = 1;
                } else {
                    $member_details = $member_result->fetch_assoc();
                    $member_id = $member_details['member_id'];

                    // 3. Check borrowing limit
                    $max_borrow_limit = get_setting($conn, 'max_borrow_limit');
                    $sql_count = "SELECT COUNT(*) AS current_borrowed FROM tbl_circulation WHERE member_id = ? AND status = 'Issued'";
                    $stmt_count = $conn->prepare($sql_count);
                    $stmt_count->bind_param("i", $member_id);
                    $stmt_count->execute();
                    $borrow_count = $stmt_count->get_result()->fetch_assoc()['current_borrowed'];

                    if ($borrow_count >= $max_borrow_limit) {
                        $error = "Member has reached the maximum borrowing limit of " . htmlspecialchars($max_borrow_limit) . " books.";
                        $step = 1;
                    } else {
                        // All checks passed, proceed to confirmation (Step 3)
                        $book_details = $book_copy;
                        $issue_details = [
                            'book_uid' => $book_uid,
                            'title' => $book_copy['title'],
                            'author' => $book_copy['author'],
                            'member_uid' => $member_uid,
                            'member_name' => $member_details['full_name'],
                            'is_reservation' => false,
                            'copy_id' => $copy_id,
                            'member_id' => $member_id,
                            'reservation_id' => 0,
                            'reservation_uid' => 'N/A',
                        ];
                        $step = 3;
                    }
                }
            }
        }
    }
}

// --- Step 3: Confirmation and Final Issue ---
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_issue') {
    $member_id = (int)$_POST['member_id'];
    $copy_id = (int)$_POST['copy_id'];
    $reservation_id = (int)$_POST['reservation_id'];
    $is_reservation = (bool)$_POST['is_reservation'];
    $book_id = (int)$_POST['book_id']; // Need book_id for available_quantity update

    $max_borrow_days = get_setting($conn, 'max_borrow_days');
    $due_date = date('Y-m-d', strtotime("+" . $max_borrow_days . " days"));

    // Start Transaction
    $conn->begin_transaction();
    $success = true;

    try {
        // 1. Insert into Circulation
        $sql_circ = "INSERT INTO tbl_circulation (copy_id, member_id, issue_date, due_date, status, issued_by_admin_id) VALUES (?, ?, NOW(), ?, 'Issued', ?)";
        $stmt_circ = $conn->prepare($sql_circ);
        $stmt_circ->bind_param("iisi", $copy_id, $member_id, $due_date, $admin_id);
        if (!$stmt_circ->execute()) {
            throw new Exception("Error inserting circulation record: " . $stmt_circ->error);
        }

        // 2. Update Book Copy Status
        $sql_copy_update = "UPDATE tbl_book_copies SET status = 'Issued' WHERE copy_id = ?";
        $stmt_copy_update = $conn->prepare($sql_copy_update);
        $stmt_copy_update->bind_param("i", $copy_id);
        if (!$stmt_copy_update->execute()) {
            throw new Exception("Error updating book copy status: " . $stmt_copy_update->error);
        }

        // 3. Update Book Title available_quantity
        $sql_book_update = "UPDATE tbl_books SET available_quantity = available_quantity - 1 WHERE book_id = (SELECT book_id FROM tbl_book_copies WHERE copy_id = ?)";
        $stmt_book_update = $conn->prepare($sql_book_update);
        $stmt_book_update->bind_param("i", $copy_id);
        if (!$stmt_book_update->execute()) {
            throw new Exception("Error updating book available quantity: " . $stmt_book_update->error);
        }

        // Commit Transaction
        $conn->commit();
        $message = "Book successfully issued to " . htmlspecialchars($_POST['member_name']) . ". Due Date: " . date('M d, Y', strtotime($due_date));
        $step = 1; // Reset to step 1
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Issuance failed: " . $e->getMessage();
        $step = 1; // Reset to step 1 with error
    }
}

admin_header('Issue Book');
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

    /* Modern Form Inputs */
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
    }
    .form-group input[type="text"] {
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
    .form-group input:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
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
    .detail-card .due-date-text {
        font-weight: 700;
        color: #dc3545;
    }
    .detail-card .issue-type-text {
        font-weight: 700;
        color: #17a2b8;
    }
    
    /* --- Step 3 Button Styles --- */
    .confirmation-actions {
        display: flex;
        flex-wrap: wrap; /* Allow wrapping on small screens */
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
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
    }
    
    @media (max-width: 768px) {
        .confirmation-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="card">
    <h2><i class="fas fa-exchange-alt"></i> Issue Book</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <p class="text-muted">Enter the Book UID and Member ID to issue the book.</p>

        <form method="POST">
            <input type="hidden" name="step" value="2">
            <div class="form-group">
                <i class="form-icon fas fa-barcode"></i>
                <input type="text" id="book_uid" name="book_uid" placeholder="Book UID (Barcode)" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-id-card"></i>
                <input type="text" id="member_uid" name="member_uid" placeholder="Member ID" required>
            </div>
            <button type="submit" class="btn-gradient"><i class="fas fa-arrow-right"></i> Next: Check Details</button>
        </form>

    <?php elseif ($step == 3): ?>
        <h3 style="font-size: 22px; font-weight: 600; margin-bottom: 20px;">Confirmation Details</h3>
        <p class="text-muted">Please verify the details below before confirming the book issuance.</p>

        <div class="confirmation-grid">
            <div class="detail-card book-detail">
                <h4><i class="fas fa-book"></i> Book Details</h4>
                <p><strong>Book UID:</strong> <?php echo htmlspecialchars($issue_details['book_uid']); ?></p>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($issue_details['title']); ?></p>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($issue_details['author']); ?></p>
                <p><strong>Issue Type:</strong> <span class="issue-type-text">Direct Issue</span></p>
            </div>

            <div class="detail-card member-detail">
                <h4><i class="fas fa-user"></i> Member Details</h4>
                <p><strong>Member ID:</strong> <?php echo htmlspecialchars($issue_details['member_uid']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($issue_details['member_name']); ?></p>
                <p><strong>Borrowed:</strong> <?php echo htmlspecialchars($borrow_count); ?> / <?php echo htmlspecialchars($max_borrow_limit); ?></p>
                <p><strong>Due Date:</strong> <span class="due-date-text"><?php echo date('M d, Y', strtotime("+" . get_setting($conn, 'max_borrow_days') . " days")); ?></span></p>
            </div>
        </div>

        <form method="POST" class="confirmation-actions mt-4">
            <input type="hidden" name="step" value="3">
            <input type="hidden" name="action" value="confirm_issue">
            <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($issue_details['member_id']); ?>">
            <input type="hidden" name="copy_id" value="<?php echo htmlspecialchars($issue_details['copy_id']); ?>">
            <input type="hidden" name="reservation_id" value="0">
            <input type="hidden" name="is_reservation" value="0">
            <input type="hidden" name="member_name" value="<?php echo htmlspecialchars($issue_details['member_name']); ?>">
            <input type="hidden" name="book_id" value="<?php echo htmlspecialchars($book_copy['book_id'] ?? $reservation_data['book_id']); ?>">

            <button type="submit" class="btn-success-gradient btn-lg"><i class="fas fa-check"></i> Confirm and Issue Book</button>
            <a href="issue_book.php" class="btn btn-secondary btn-lg"><i class="fas fa-times"></i> Cancel</a>
        </form>

    <?php endif; ?>
</div>

<?php
admin_footer();
close_db_connection($conn);
?>