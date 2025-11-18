<?php
require_once '../includes/functions.php';
require_admin_login();
global $conn;

// --- Fetch Dashboard Statistics ---
$stats = [
    'total_books' => 0,
    'total_members' => 0,
    'total_issued' => 0,
    'total_overdue' => 0,
    'pending_reservations' => 0,
];

// Total Books (Total unique book titles)
$result = $conn->query("SELECT SUM(total_quantity) AS count FROM tbl_books");
$stats['total_books'] = $result->fetch_assoc()['count'] ?? 0;

// Total Members
$result = $conn->query("SELECT COUNT(*) AS count FROM tbl_members WHERE status = 'Active'");
$stats['total_members'] = $result->fetch_assoc()['count'] ?? 0;

// Total Books Issued (Currently issued)
$result = $conn->query("SELECT COUNT(*) AS count FROM tbl_circulation WHERE status = 'Issued'");
$stats['total_issued'] = $result->fetch_assoc()['count'] ?? 0;

// Total Overdue Books (Currently issued and past due date)
$result = $conn->query("SELECT COUNT(*) AS count FROM tbl_circulation WHERE status = 'Issued' AND due_date < CURDATE()");
$stats['total_overdue'] = $result->fetch_assoc()['count'] ?? 0;

// Pending Reservations
$result = $conn->query("SELECT COUNT(*) AS count FROM tbl_reservations WHERE status = 'Pending'");
$stats['pending_reservations'] = $result->fetch_assoc()['count'] ?? 0;

// --- UI Rendering ---
admin_header('Dashboard');
?>

<style>
    /* * ==================================
     * ATTRACTIVE DASHBOARD STYLING
     * ==================================
     */
    
    .dashboard-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .dashboard-header h2 {
        font-size: 28px;
        font-weight: 600;
        color: #343a40;
    }

    /* Responsive Grid for Stat Cards */
    .dashboard-grid {
        display: grid;
        /* Creates 5 columns on large screens, and fewer as screen shrinks */
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
    }

    /* Individual Stat Card Styling */
    .stat-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
        padding: 25px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    /* Icon Background */
    .stat-card .icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        margin-right: 20px;
        flex-shrink: 0; /* Prevents icon from shrinking */
    }

    /* Text Details */
    .stat-card .details p {
        font-size: 16px;
        color: #6c757d;
        margin: 0;
        font-weight: 500;
    }

    .stat-card .details h3 {
        font-size: 28px;
        color: #343a40;
        margin: 5px 0 0 0;
        font-weight: 700;
    }

    /* * ==================================
     * ICON GRADIENT COLORS
     * ==================================
     */
    
    /* Total Books (Blue) */
    .stat-card.total-books .icon {
        background: linear-gradient(135deg, #007bff, #0056b3);
    }
    
    /* Total Members (Green) */
    .stat-card.total-members .icon {
        background: linear-gradient(135deg, #28a745, #218838);
    }
    
    /* Books Issued (Orange) */
    .stat-card.books-issued .icon {
        background: linear-gradient(135deg, #fd7e14, #e85a00);
    }
    
    /* Overdue Books (Red) */
    .stat-card.overdue-books .icon {
        background: linear-gradient(135deg, #dc3545, #c82333);
    }

    /* Pending Reservations (Purple) */
    .stat-card.pending-reservations .icon {
        background: linear-gradient(135deg, #6f42c1, #5a32a3);
    }

</style>

<div class="dashboard-header">
    <h2>Library At a Glance</h2>
</div>

<div class="dashboard-grid">
    <div class="stat-card total-books">
        <div class="icon"><i class="fas fa-book"></i></div>
        <div class="details">
            <p>Total Books</p>
            <h3><?php echo $stats['total_books']; ?></h3>
	    <a href="../admin/books.php">View Details ></a>

        </div>
    </div>
    
    <div class="stat-card total-members">
        <div class="icon"><i class="fas fa-users"></i></div>
        <div class="details">
            <p>Total Members</p>
            <h3><?php echo $stats['total_members']; ?></h3>
	    <a href="../admin/members.php">View Details ></a>
        </div>
    </div>
    
    <div class="stat-card books-issued">
        <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        <div class="details">
            <p>Books Issued</p>
            <h3><?php echo $stats['total_issued']; ?></h3>
	    <a href="../admin/book_copies.php">View Details ></a>
        </div>
    </div>
    
    <div class="stat-card overdue-books">
        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="details">
            <p>Overdue Books</p>
            <h3><?php echo $stats['total_overdue']; ?></h3>
	    <a href="../admin/reports.php">View Details ></a>
        </div>
    </div>
    
    </div>

<?php
admin_footer();
close_db_connection($conn);
?>