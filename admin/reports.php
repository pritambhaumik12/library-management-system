<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

admin_header('Reporting');
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
    .mt-4 {
        margin-top: 25px;
    }
    .text-muted {
        font-size: 16px;
        color: #6c757d;
        margin-top: 0;
        margin-bottom: 25px;
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
    .btn i { margin-right: 5px; }

    /* Modern Form Inputs */
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
    }
    .form-group input[type="text"],
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
    .form-group input:focus {
        outline: none;
        border-color: #007bff;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

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
        height: 49px; /* Match input height */
        flex-shrink: 0;
    }

    /* Date Filter Form */
    .date-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
    }
    .date-filter-form label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0;
    }
    .date-filter-form input[type="date"] {
        padding: 10px 14px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 15px;
        background: #fff;
    }
    .date-filter-form .btn {
        padding-top: 11px;
        padding-bottom: 11px;
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
    
    /* Special Table Row Highlighting */
    .data-table tr.table-danger,
    .data-table tr.table-danger:hover {
        background-color: #f8d7da;
    }
    .data-table tr.table-danger td {
        color: #721c24;
    }
    .data-table .overdue-days {
        font-weight: 700;
        color: #c82333;
    }
    .table-total-row {
        background-color: #f8f9fa;
        font-weight: 700;
        border-top: 2px solid #dee2e6;
    }
    .table-total-row td {
        font-size: 16px !important;
    }
    
    /* Report Section Headers */
    .report-section h3 {
        font-size: 20px;
        font-weight: 600;
        color: #343a40;
        border-bottom: 2px solid #f1f1f1;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .report-subtitle {
        font-size: 20px;
        font-weight: 600;
        color: #343a40;
        margin-bottom: 20px;
    }

    /* Status Badges */
    .badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 13px;
        text-transform: capitalize;
    }
    .badge-success { /* Returned, Paid */
        background-color: #e6f7ec;
        color: #218838;
    }
    .badge-warning { /* Issued, Pending */
        background-color: #fff8e6;
        color: #e88b00;
    }
    .badge-danger { /* Lost */
        background-color: #f8d7da;
        color: #c82333;
    }
</style>

<div class="card">
    <h2><i class="fas fa-history"></i> Book Borrowing History</h2>
    
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-barcode"></i>
            <input type="search" name="book_uid" placeholder="Enter Book ID (Book UID)..." value="<?php echo htmlspecialchars($_GET['book_uid'] ?? ''); ?>" required>
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search History</button>
    </form>

    <?php if (isset($_GET['book_uid'])): 
        $book_uid = trim($_GET['book_uid']);
        
        $sql_history = "
            SELECT 
                tc.issue_date, tc.due_date, tc.return_date, tc.status,
                tm.full_name AS member_name, tm.member_uid,
                tb.title AS book_title,
                tf.fine_amount, tf.payment_status
            FROM 
                tbl_circulation tc
            JOIN 
                tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
            JOIN 
                tbl_books tb ON tbc.book_id = tb.book_id
            JOIN 
                tbl_members tm ON tc.member_id = tm.member_id
            LEFT JOIN
                tbl_fines tf ON tc.circulation_id = tf.circulation_id
            WHERE 
                tbc.book_uid = ?
            ORDER BY 
                tc.issue_date DESC
        ";
        $stmt = $conn->prepare($sql_history);
        $stmt->bind_param("s", $book_uid);
        $stmt->execute();
        $history_result = $stmt->get_result();
        $book_title = $history_result->num_rows > 0 ? $history_result->fetch_assoc()['book_title'] : 'Unknown Book';
        $history_result->data_seek(0); // Reset result pointer
    ?>
        <h3 class="report-subtitle">History for: <?php echo htmlspecialchars($book_title); ?> (<?php echo htmlspecialchars($book_uid); ?>)</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member Name (ID)</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Fine Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history_result->num_rows > 0): ?>
                        <?php while ($record = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['member_name']) . " (" . htmlspecialchars($record['member_uid']) . ")"; ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                                <td><?php echo $record['return_date'] ? date('M d, Y', strtotime($record['return_date'])) : 'N/A'; ?></td>
                                <td><span class="badge badge-<?php echo $record['status'] == 'Issued' ? 'warning' : ($record['status'] == 'Returned' ? 'success' : 'danger'); ?>"><?php echo $record['status']; ?></span></td>
                                <td>
                                    <?php if ($record['fine_amount']): ?>
                                        <?php echo get_setting($conn, 'currency_symbol') . number_format($record['fine_amount'], 2); ?>
                                        <span class="badge badge-<?php echo $record['payment_status'] == 'Paid' ? 'success' : 'warning'; ?>"><?php echo $record['payment_status']; ?></span>
                                    <?php else: ?>
                                        No Fine
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No borrowing history found for this Book ID.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card mt-4">
    <h2><i class="fas fa-list-alt"></i> Standard Reports</h2>
    
    <div class="report-section">
        <h3>1. Currently Issued Books</h3>
        <?php
        $sql_issued = "
            SELECT 
                tc.issue_date, tc.due_date,
                tm.full_name AS member_name, tm.member_uid,
                tb.title AS book_title,
                tbc.book_uid
            FROM 
                tbl_circulation tc
            JOIN 
                tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
            JOIN 
                tbl_books tb ON tbc.book_id = tb.book_id
            JOIN 
                tbl_members tm ON tc.member_id = tm.member_id
            WHERE 
                tc.status = 'Issued'
            ORDER BY 
                tc.due_date ASC
        ";
        $issued_result = $conn->query($sql_issued);
        ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Book Title (UID)</th>
                        <th>Issued To</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($issued_result->num_rows > 0): ?>
                        <?php while ($record = $issued_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['book_title']) . " (" . htmlspecialchars($record['book_uid']) . ")"; ?></td>
                                <td><?php echo htmlspecialchars($record['member_name']) . " (" . htmlspecialchars($record['member_uid']) . ")"; ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No books are currently issued.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section mt-4">
        <h3>2. Overdue Books</h3>
        <?php
        $sql_overdue = "
            SELECT 
                tc.issue_date, tc.due_date,
                tm.full_name AS member_name, tm.member_uid,
                tb.title AS book_title,
                tbc.book_uid,
                DATEDIFF(CURDATE(), tc.due_date) AS days_overdue
            FROM 
                tbl_circulation tc
            JOIN 
                tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
            JOIN 
                tbl_books tb ON tbc.book_id = tb.book_id
            JOIN 
                tbl_members tm ON tc.member_id = tm.member_id
            WHERE 
                tc.status = 'Issued' AND tc.due_date < CURDATE()
            ORDER BY 
                tc.due_date ASC
        ";
        $overdue_result = $conn->query($sql_overdue);
        ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Book Title (UID)</th>
                        <th>Issued To</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($overdue_result->num_rows > 0): ?>
                        <?php while ($record = $overdue_result->fetch_assoc()): ?>
                            <tr class="table-danger">
                                <td><?php echo htmlspecialchars($record['book_title']) . " (" . htmlspecialchars($record['book_uid']) . ")"; ?></td>
                                <td><?php echo htmlspecialchars($record['member_name']) . " (" . htmlspecialchars($record['member_uid']) . ")"; ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['issue_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                                <td class="overdue-days"><?php echo $record['days_overdue']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">No books are currently overdue.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-section mt-4">
        <h3>3. Fine Collection History</h3>
        <form method="GET" class="date-filter-form">
            <label for="start_date">From:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? date('Y-m-01')); ?>" required>
            <label for="end_date">To:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? date('Y-m-d')); ?>" required>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
        </form>
        
        <?php
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $currency = get_setting($conn, 'currency_symbol');

        $sql_fines_history = "
            SELECT 
                tf.fine_amount, tf.paid_on, tf.payment_method, tf.transaction_id,
                tm.full_name AS member_name, tm.member_uid,
                ta.full_name AS collected_by
            FROM 
                tbl_fines tf
            JOIN 
                tbl_members tm ON tf.member_id = tm.member_id
            LEFT JOIN
                tbl_admin ta ON tf.collected_by_admin_id = ta.admin_id
            WHERE 
                tf.payment_status = 'Paid' AND tf.paid_on BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY 
                tf.paid_on DESC
        ";
        $stmt = $conn->prepare($sql_fines_history);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $fines_history_result = $stmt->get_result();
        $total_collected = 0;
        ?>
        
        <p class="text-muted" style="margin-top: 20px; margin-bottom: 20px;">Showing paid fines from <strong><?php echo date('M d, Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('M d, Y', strtotime($end_date)); ?></strong>.</p>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Member Name (ID)</th>
                        <th>Amount</th>
                        <th>Paid On</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Collected By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($fines_history_result->num_rows > 0): ?>
                        <?php while ($record = $fines_history_result->fetch_assoc()): 
                            $total_collected += $record['fine_amount'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['member_name']) . " (" . htmlspecialchars($record['member_uid']) . ")"; ?></td>
                                <td><?php echo $currency . number_format($record['fine_amount'], 2); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($record['paid_on'])); ?></td>
                                <td><?php echo htmlspecialchars($record['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($record['transaction_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['collected_by'] ?? 'System'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <tr class="table-total-row">
                            <td style="text-align: right;"><strong>TOTAL COLLECTED:</strong></td>
                            <td colspan="5"><strong><?php echo $currency . number_format($total_collected, 2); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">No fines collected in this date range.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
admin_footer();
close_db_connection($conn);
?>