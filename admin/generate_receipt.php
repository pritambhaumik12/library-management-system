<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$fine_id = (int)($_GET['fine_id'] ?? 0);
$currency = get_setting($conn, 'currency_symbol');

if ($fine_id === 0) {
    die("Invalid Fine ID.");
}

// Fetch fine and related details
$sql = "
    SELECT 
        tf.fine_id, tf.fine_amount, tf.paid_on, tf.payment_method, tf.transaction_id, tf.fine_date,
        tm.full_name AS member_name, tm.member_uid,
        tb.title AS book_title,
        tbc.book_uid,
        ta.full_name AS collected_by_admin,
        tc.issue_date, tc.due_date
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
    LEFT JOIN
        tbl_admin ta ON tf.collected_by_admin_id = ta.admin_id
    WHERE 
        tf.fine_id = ? AND tf.payment_status = 'Paid'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $fine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Fine record not found or not yet paid.");
}

$fine_data = $result->fetch_assoc();
$library_name = get_setting($conn, 'library_name');

// HTML for the receipt
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fine Receipt #<?php echo $fine_id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .receipt-container {
            width: 80mm; /* Standard receipt width */
            margin: 0 auto;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #ccc; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 1.2em; }
        .header p { margin: 2px 0; font-size: 0.8em; }
        .details, .book-details, .payment-details { margin-bottom: 15px; font-size: 0.9em; }
        .details p, .book-details p, .payment-details p { margin: 5px 0; }
        .details strong, .book-details strong, .payment-details strong { float: right; }
        .total { border-top: 1px dashed #ccc; padding-top: 10px; font-size: 1.1em; font-weight: bold; text-align: right; }
        .footer { text-align: center; margin-top: 20px; font-size: 0.7em; }
        .print-button { text-align: center; margin-top: 20px; }
        .print-button button { padding: 10px 20px; font-size: 1em; cursor: pointer; }

        @media print {
            body { background-color: #fff; }
            .receipt-container { border: none; box-shadow: none; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="header">
        <h1><?php echo htmlspecialchars($library_name); ?></h1>
	<h3><p>Central Library</p></h3>
        <p>Fine Payment Receipt</p>
        <p>Date: <?php echo date('M d, Y H:i:s', strtotime($fine_data['paid_on'])); ?></p>
    </div>

    <div class="details">
        <p>Receipt No: <strong>#<?php echo htmlspecialchars($fine_data['fine_id']); ?></strong></p>
        <p>Received From: <strong><?php echo htmlspecialchars($fine_data['member_name']); ?></strong></p>
        <p>Member ID: <strong><?php echo htmlspecialchars($fine_data['member_uid']); ?></strong></p>
        <p>Date Paid: <strong><?php echo date('M d, Y H:i:s', strtotime($fine_data['paid_on'])); ?></strong></p>
    </div>

    <div class="book-details">
        <p style="font-weight: bold; border-bottom: 1px solid #eee;">Book Details (Overdue)</p>
        <p>Book Title: <strong><?php echo htmlspecialchars($fine_data['book_title']); ?></strong></p>
        <p>Book ID (UID): <strong><?php echo htmlspecialchars($fine_data['book_uid']); ?></strong></p>
        <p>Issue Date: <strong><?php echo date('M d, Y', strtotime($fine_data['issue_date'])); ?></strong></p>
        <p>Due Date: <strong><?php echo date('M d, Y', strtotime($fine_data['due_date'])); ?></strong></p>
        <?php 
            $overdue_days = max(0, floor((strtotime($fine_data['fine_date']) - strtotime($fine_data['due_date'])) / (60 * 60 * 24)));
            $fine_per_day = get_setting($conn, 'fine_per_day');
        ?>
        <p>Overdue Days: <strong><?php echo $overdue_days; ?></strong></p>
        <p>Fine Per Day: <strong><?php echo $currency . number_format($fine_per_day, 2); ?></strong></p>
        <p>Fine Calculated On: <strong><?php echo date('M d, Y', strtotime($fine_data['fine_date'])); ?></strong></p>
    </div>

    <div class="payment-details">
        <p style="font-weight: bold; border-bottom: 1px solid #eee;">Payment Information</p>
        <p>Payment Method: <strong><?php echo htmlspecialchars($fine_data['payment_method']); ?></strong></p>
        <?php if ($fine_data['transaction_id']): ?>
            <p>Transaction ID: <strong><?php echo htmlspecialchars($fine_data['transaction_id']); ?></strong></p>
        <?php endif; ?>
        <p>Fine Received By: <strong><?php echo htmlspecialchars($fine_data['collected_by_admin'] ?? 'N/A'); ?></strong></p>
    </div>

    <div class="total">
        Total Fine Amount: <span><?php echo $currency; ?> <?php echo number_format($fine_data['fine_amount'], 2); ?></span>
    </div>

    <div class="footer">
        <p>Thank you for your payment.</p>
    </div>
</div>

<div class="print-button">
    <button onclick="window.print()"><i class="fas fa-print"></i> Print Receipt / Save as PDF</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
</body>
</html>
<?php close_db_connection($conn); ?>
