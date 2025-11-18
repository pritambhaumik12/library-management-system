<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

// --- Fetch Book Copies for View/Search ---

$search_query = trim($_GET['search'] ?? '');
$sql = "
    SELECT 
        tbc.book_uid, tbc.status,
        tb.title, tb.author
    FROM 
        tbl_book_copies tbc
    JOIN 
        tbl_books tb ON tbc.book_id = tb.book_id
";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " WHERE tbc.book_uid LIKE ? OR tb.title LIKE ? OR tb.author LIKE ? OR tbc.status LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = 'ssss';
}

$sql .= " ORDER BY tbc.book_uid ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$copies_result = $stmt->get_result();

admin_header('Book Copy Status');
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
        margin-bottom: 10px; /* Reduced margin */
    }
    .card h2 i {
        margin-right: 10px;
        color: #007bff;
    }
    .card .text-muted {
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
    .btn-danger { background-color: #dc3545; color: white; }
    .btn-danger:hover { background-color: #c82333; }
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
    
    /* * ==================================
     * NEW STATUS BADGES
     * ==================================
     */
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
    .badge-warning {
        background-color: #fff8e6;
        color: #e88b00;
    }
    .badge-primary {
        background-color: #e6f0ff;
        color: #0056b3;
    }
    .badge-danger {
        background-color: #f8d7da;
        color: #c82333;
    }
    .badge-info {
        background-color: #e6f7ff;
        color: #0056b3;
    }

</style>

<div class="card">
    <h2><i class="fas fa-barcode"></i> All Book Copies (UIDs) Status</h2>
    <p class="text-muted">This page shows the status of every unique book copy in the library, identified by its Book UID.</p>
    
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-search"></i>
            <input type="search" name="search" placeholder="Search by Book UID, Title, Author, or Status..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="book_copies.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Book UID</th>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Current Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($copies_result->num_rows > 0): ?>
                    <?php while ($copy = $copies_result->fetch_assoc()): ?>
                        <?php
                            $status_class = 'badge-info'; // Default
                            if ($copy['status'] === 'Available') $status_class = 'badge-success';
                            if ($copy['status'] === 'Issued') $status_class = 'badge-warning';
                            if ($copy['status'] === 'Reserved') $status_class = 'badge-primary';
                            if ($copy['status'] === 'Lost') $status_class = 'badge-danger';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($copy['book_uid']); ?></strong></td>
                            <td><?php echo htmlspecialchars($copy['title']); ?></td>
                            <td><?php echo htmlspecialchars($copy['author']); ?></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $copy['status']; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">No book copies found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
admin_footer();
close_db_connection($conn);
?>