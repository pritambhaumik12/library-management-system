<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

$message = '';
$error = '';

// --- Handle Book Actions (Add, Edit, Delete) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $shelf_location = trim($_POST['shelf_location'] ?? '');

        if (empty($title) || empty($author) || empty($category) || $quantity <= 0 || empty($shelf_location)) {
            $error = "All fields are required, and quantity must be greater than 0.";
        } else {
            // 1. Insert the main book record
            $stmt = $conn->prepare("INSERT INTO tbl_books (title, author, isbn, category, total_quantity, available_quantity, shelf_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiis", $title, $author, $isbn, $category, $quantity, $quantity, $shelf_location);
            
            if ($stmt->execute()) {
                $book_id = $conn->insert_id;
                $success = true;

                // 2. Generate and insert individual book copies
                for ($i = 1; $i <= $quantity; $i++) {
                    $book_uid = generate_book_uid($book_id) . "-" . $i; // Unique ID for each copy
                    $stmt_copy = $conn->prepare("INSERT INTO tbl_book_copies (book_id, book_uid) VALUES (?, ?)");
                    $stmt_copy->bind_param("is", $book_id, $book_uid);
                    if (!$stmt_copy->execute()) {
                        $success = false;
                        break;
                    }
                }

                if ($success) {
                    $message = "Book '{$title}' and {$quantity} copies added successfully.";
                } else {
                    $error = "Book added, but failed to create all copies. Please check the database.";
                }
            } else {
                $error = "Error adding book: " . $conn->error;
            }
        }
    } elseif ($action === 'edit_book') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $shelf_location = trim($_POST['shelf_location'] ?? '');

        if (empty($title) || empty($author) || empty($category) || empty($shelf_location)) {
            $error = "All fields are required for editing.";
        } else {
            $stmt = $conn->prepare("UPDATE tbl_books SET title = ?, author = ?, isbn = ?, category = ?, shelf_location = ? WHERE book_id = ?");
            $stmt->bind_param("sssssi", $title, $author, $isbn, $category, $shelf_location, $book_id);
            
            if ($stmt->execute()) {
                $message = "Book details updated successfully.";
            } else {
                $error = "Error updating book: " . $conn->error;
            }
        }
    } elseif ($action === 'delete_book') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        
        // Check if any copies are currently issued
        $stmt_check = $conn->prepare("SELECT COUNT(tbc.copy_id) AS issued_count FROM tbl_book_copies tbc JOIN tbl_circulation tc ON tbc.copy_id = tc.copy_id WHERE tbc.book_id = ? AND tc.status = 'Issued'");
        $stmt_check->bind_param("i", $book_id);
        $stmt_check->execute();
        $issued_count = $stmt_check->get_result()->fetch_assoc()['issued_count'];

        if ($issued_count > 0) {
            $error = "Cannot delete book. {$issued_count} copies are currently issued.";
        } else {
            // Deleting the main book record will cascade delete all copies, reservations, etc.
            $stmt = $conn->prepare("DELETE FROM tbl_books WHERE book_id = ?");
            $stmt->bind_param("i", $book_id);
            
            if ($stmt->execute()) {
                $message = "Book and all its copies successfully deleted.";
            } else {
                $error = "Error deleting book: " . $conn->error;
            }
        }
    }
}

// --- Fetch Books for View/Search ---

$search_query = trim($_GET['search'] ?? '');
$sql = "SELECT b.*, (SELECT COUNT(copy_id) FROM tbl_book_copies WHERE book_id = b.book_id AND status = 'Available') AS available_copies 
        FROM tbl_books b";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?";
    $search_term = "%" . $search_query . "%";
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = 'ssss';
}

$sql .= " ORDER BY b.book_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books_result = $stmt->get_result();

admin_header('Book Management');
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
        margin-bottom: 15px; /* Added for modal stacking */
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
    .form-group input[type="number"],
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
    /* Readonly inputs in modal */
    .form-group input:read-only,
    .form-group input:disabled {
        background-color: #e9ecef;
        cursor: not-allowed;
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
        flex-wrap: wrap; /* Allow wrapping on small screens */
        gap: 10px;
        margin-bottom: 25px;
    }
    .search-form .form-group {
        flex-grow: 1; /* Input takes available space */
        margin-bottom: 0;
    }
    .search-form .btn {
        height: 49px; /* Match input height */
        flex-shrink: 0; /* Prevent buttons from shrinking */
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
        white-space: nowrap; /* Keep action buttons on one line */
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

    /* * ==================================
     * NEW MODAL STYLING
     * ==================================
     */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.6); /* Semi-transparent backdrop */
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
    <h2><i class="fas fa-plus-circle"></i> Add New Book</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="add_book">
        
        <div class="form-group">
            <i class="form-icon fas fa-book"></i>
            <input type="text" id="title" name="title" placeholder="Book Title" required>
        </div>
        <div class="form-group">
            <i class="form-icon fas fa-user-edit"></i>
            <input type="text" id="author" name="author" placeholder="Author" required>
        </div>
        <div class="form-group">
            <i class="form-icon fas fa-barcode"></i>
            <input type="text" id="isbn" name="isbn" placeholder="ISBN (Optional)">
        </div>
        <div class="form-group">
            <i class="form-icon fas fa-tags"></i>
            <input type="text" id="category" name="category" placeholder="Category" required>
        </div>
        <div class="form-group">
            <i class="form-icon fas fa-copy"></i>
            <input type="number" id="quantity" name="quantity" placeholder="Total Copies" required min="1">
        </div>
        <div class="form-group">
            <i class="form-icon fas fa-map-marker-alt"></i>
            <input type="text" id="shelf_location" name="shelf_location" placeholder="Shelf Location" required>
        </div>
        <div class="form-group full-width">
            <button type="submit" class="btn-gradient"><i class="fas fa-plus"></i> Add Book</button>
        </div>
    </form>
</div>

<div class="card mt-4">
    <h2><i class="fas fa-search"></i> View & Search Books</h2>
    
    <form method="GET" class="search-form">
        <div class="form-group">
            <i class="form-icon fas fa-search"></i>
            <input type="search" name="search" placeholder="Search by Title, Author, ISBN, or Category..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
        <?php if (!empty($search_query)): ?>
            <a href="books.php" class="btn btn-danger"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Total</th>
                    <th>Available</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($books_result->num_rows > 0): ?>
                    <?php while ($book = $books_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $book['book_id']; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo $book['total_quantity']; ?></td>
                            <td><span class="badge <?php echo $book['available_quantity'] > 0 ? 'badge-success' : 'badge-danger'; ?>"><?php echo $book['available_quantity']; ?></span></td>
                            <td><?php echo htmlspecialchars($book['shelf_location']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" data-id="<?php echo $book['book_id']; ?>" data-title="<?php echo htmlspecialchars($book['title']); ?>" data-author="<?php echo htmlspecialchars($book['author']); ?>" data-isbn="<?php echo htmlspecialchars($book['isbn']); ?>" data-category="<?php echo htmlspecialchars($book['category']); ?>" data-location="<?php echo htmlspecialchars($book['shelf_location']); ?>"><i class="fas fa-edit"></i> Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this book and all its copies? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="delete_book">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">No books found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3>Edit Book Details</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_book">
            <input type="hidden" id="edit_book_id" name="book_id">
            
            <div class="form-group">
                <i class="form-icon fas fa-hashtag"></i>
                <input type="text" id="edit_book_id_display" placeholder="Book ID" readonly disabled>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-book"></i>
                <input type="text" id="edit_title" name="title" placeholder="Book Title" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-user-edit"></i>
                <input type="text" id="edit_author" name="author" placeholder="Author" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-barcode"></i>
                <input type="text" id="edit_isbn" name="isbn" placeholder="ISBN (Optional)">
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-tags"></i>
                <input type="text" id="edit_category" name="category" placeholder="Category" required>
            </div>
            <div class="form-group">
                <i class="form-icon fas fa-map-marker-alt"></i>
                <input type="text" id="edit_location" name="shelf_location" placeholder="Shelf Location" required>
            </div>
            
            <button type="submit" class="btn-gradient"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<script>
// JavaScript for modal functionality (unchanged, as it's correct)
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editModal');
    const closeBtn = document.querySelector('.close-btn');
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const author = this.getAttribute('data-author');
            const isbn = this.getAttribute('data-isbn');
            const category = this.getAttribute('data-category');
            const location = this.getAttribute('data-location');

            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_book_id_display').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_isbn').value = isbn;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_location').value = location;

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