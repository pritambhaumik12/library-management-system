<?php
require_once 'includes/functions.php';
require_member_login();
global $conn;

$member_id = $_SESSION['member_id'];
$currency = get_setting($conn, 'currency_symbol');

// --- Fetch Borrowing History ---
$sql_history = "
    SELECT 
        tc.issue_date, tc.due_date, tc.return_date, tc.status,
        tb.title, tb.author,
        tbc.book_uid,
        tf.fine_amount, tf.payment_status
    FROM 
        tbl_circulation tc
    JOIN 
        tbl_book_copies tbc ON tc.copy_id = tbc.copy_id
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
    LEFT JOIN
        tbl_fines tf ON tc.circulation_id = tf.circulation_id
    WHERE 
        tc.member_id = ?
    ORDER BY 
        tc.issue_date DESC
";
$stmt = $conn->prepare($sql_history);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$history_result = $stmt->get_result();

user_header('My Borrowing History');
?>

<style>
    /* * ==================================
     * ATTRACTIVE HISTORY STYLING
     * ==================================
     */

    /* Page Container */
    .history-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 15px;
    }

    /* Card Styling */
    .history-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        padding: 35px;
        border: 1px solid #eaeaea;
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .history-header {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #f4f4f4;
        padding-bottom: 20px;
    }

    .history-header h2 {
        font-size: 24px;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
    }

    .history-header h2 i {
        margin-right: 12px;
        color: #007bff;
        background: #e6f2ff;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 20px;
    }

    .history-header .text-muted {
        margin-left: auto;
        color: #7f8c8d;
        font-size: 14px;
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

    .data-table th {
        text-align: left;
        padding: 15px;
        background-color: #f8f9fa;
        color: #555;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 16px 15px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f1f1;
        color: #333;
        font-size: 15px;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .data-table tbody tr:hover {
        background-color: #f9fbff;
    }

    /* Book Title Styling */
    .book-info {
        font-weight: 600;
        color: #2c3e50;
    }
    .book-author {
        display: block;
        font-size: 13px;
        color: #7f8c8d;
        margin-top: 4px;
        font-weight: 400;
    }

    /* Badges */
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .badge-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .badge-warning { /* For 'Issued' */
        background-color: #fff3cd;
        color: #856404;
    }
    
    .badge-danger { /* For 'Lost' or Overdue logic if added */
        background-color: #f8d7da;
        color: #721c24;
    }

    .fine-tag {
        font-weight: 600;
        color: #e74c3c;
    }
    .no-fine {
        color: #27ae60;
        font-size: 13px;
        font-style: italic;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 0;
        color: #95a5a6;
    }
    .empty-state i {
        font-size: 40px;
        margin-bottom: 15px;
        display: block;
        opacity: 0.5;
    }
</style>

<div class="history-container">
    <div class="history-card">
        
        <div class="history-header">
            <h2><i class="fas fa-history"></i> Borrowing History</h2>
            <span class="text-muted">Total Records: <?php echo $history_result->num_rows; ?></span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Book Details</th>
                        <th>Book ID (UID)</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Fine Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history_result->num_rows > 0): ?>
                        <?php while ($record = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="book-info"><?php echo htmlspecialchars($record['title']); ?></div>
                                    <span class="book-author">by <?php echo htmlspecialchars($record['author']); ?></span>
                                </td>
                                
                                <td>
                                    <span style="font-family: monospace; background: #f0f0f0; padding: 4px 8px; border-radius: 4px; color: #555;">
                                        <?php echo htmlspecialchars($record['book_uid']); ?>
                                    </span>
                                </td>
                                
                                <td><?php echo date('M d, Y', strtotime($record['issue_date'])); ?></td>
                                
                                <td><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                                
                                <td>
                                    <?php 
                                        if ($record['return_date']) {
                                            echo date('M d, Y', strtotime($record['return_date'])); 
                                        } else {
                                            echo '<span style="color: #aaa;">--</span>';
                                        }
                                    ?>
                                </td>
                                
                                <td>
                                    <?php 
                                        $statusClass = 'badge-secondary';
                                        if ($record['status'] === 'Issued') $statusClass = 'badge-warning';
                                        if ($record['status'] === 'Returned') $statusClass = 'badge-success';
                                        if ($record['status'] === 'Lost') $statusClass = 'badge-danger';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $record['status']; ?></span>
                                </td>
                                
                                <td>
                                    <?php if ($record['fine_amount'] > 0): ?>
                                        <div class="fine-tag">
                                            <?php echo $currency . number_format($record['fine_amount'], 2); ?>
                                        </div>
                                        <?php if ($record['payment_status'] === 'Paid'): ?>
                                            <span class="badge badge-success" style="font-size: 10px; padding: 3px 8px;">Paid</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger" style="font-size: 10px; padding: 3px 8px;">Pending</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-fine"><i class="fas fa-check-circle"></i> None</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-book-open"></i>
                                    <p>You haven't borrowed any books yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
user_footer();
close_db_connection($conn);
?>