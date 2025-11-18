<?php
require_once 'includes/functions.php';
global $conn;

$message = '';
$error = '';
$member_id = $_SESSION['member_id'] ?? null;



// --- Fetch Books for Search ---
$search_query = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM tbl_books";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " WHERE title LIKE ? OR author LIKE ? OR category LIKE ? OR isbn LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = 'ssss';
}

$sql .= " ORDER BY title ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books_result = $stmt->get_result();

user_header('Search Catalog');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
    /* * ==================================
     * NEW ATTRACTIVE SEARCH STYLING
     * ==================================
     */
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8f9fa; /* Consistent light background */
    }

    /* Use a standard content-width container */
    .container {
        max-width: 1100px;
        margin: 20px auto;
        padding: 0 20px;
    }

    /* Main Card Styling */
    .card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
        padding: 25px 30px;
        transition: all 0.3s ease;
        border: none;
        margin-bottom: 30px;
    }

    .card h2 {
        color: #0056b3;
        font-weight: 600;
        margin-bottom: 25px;
        font-size: 1.5rem;
    }

    .card h2 i {
        margin-right: 12px;
        color: #007bff;
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

    /* Modern Search Form */
    .search-form-user {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
    }

    .search-form-user input[type="text"] {
        flex: 1; /* Makes the input field grow */
        padding: 14px 18px;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 16px;
        font-family: 'Poppins', sans-serif;
        background: #f9f9f9;
        transition: all 0.3s;
    }
    
    .search-form-user input[type="text"]:focus {
        border-color: #007bff;
        background: #fff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
    }

    /* Button Styling */
    .btn {
        padding: 14px 20px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        border: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #0069d9 0%, #004a99 100%);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(108, 117, 125, 0.2);
    }
    .btn-secondary:hover {
        background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
        box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        transform: translateY(-2px);
    }

    /* Book List Styling */
    .book-list-container {
        display: grid;
        gap: 15px;
    }

    .book-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; /* For responsiveness */
        padding: 20px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        background: #fff;
        transition: all 0.3s ease;
    }

    .book-item:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
        border-color: #007bff;
    }

    .book-details {
        flex: 1;
        min-width: 300px; /* Prevents squishing */
        padding-right: 20px;
    }

    .book-details h3 {
        color: #0056b3;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0 0 10px;
    }

    .book-details p {
        margin: 4px 0;
        color: #555;
    }
    .book-details p strong {
        color: #333;
    }

    .book-status {
        flex-shrink: 0;
        width: 260px; /* Fixed width for alignment */
        text-align: right;
    }

    .availability-status {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }

    .text-success {
        color: #28a745;
    }
    .text-danger {
        color: #dc3545;
    }

    .availability-status i {
        margin-right: 8px;
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
    .badge-info {
        background-color: #cce5ff;
        color: #004085;
    }
    
    .no-results {
        text-align: center;
        font-size: 1.1rem;
        color: #6c757d;
        padding: 40px;
        background: #f9f9f9;
        border-radius: 8px;
    }

</style>

<div class="container">
    <div class="card">
        <h2><i class="fas fa-search"></i> Search Library Catalog</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="GET" class="search-form-user">
            <input type="text" name="search" placeholder="Search by Title, Author, Category, or ISBN..." value="<?php echo htmlspecialchars($search_query); ?>" aria-label="Search Catalog">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search_query)): ?>
                <a href="search.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>

        <div class="book-list-container">
            <?php if ($books_result->num_rows > 0): ?>
                <?php while ($book = $books_result->fetch_assoc()): 
                    $available_qty = $book['available_quantity'];
                    $total_qty = $book['total_quantity'];
                    $is_available = $available_qty > 0;
                ?>
                    <div class="book-item">
                        <div class="book-details">
                            <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category']); ?></p>
                            <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></p>
                            <p><strong>Shelf:</strong> <?php echo htmlspecialchars($book['shelf_location']); ?></p>
                        </div>
                        <div class="book-status">
                            <p class="availability-status">
                                <?php if ($is_available): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Available</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-times-circle"></i> Unavailable</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-muted" style="margin: 5px 0;">
                                (<?php echo $available_qty; ?> of <?php echo $total_qty; ?> copies)
                            </p>
                            
                            <?php if (!$is_available): ?>
                                <span class="badge badge-info mt-2">All copies are currently issued.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-results">No books found matching your search criteria.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
user_footer();
close_db_connection($conn);
?>