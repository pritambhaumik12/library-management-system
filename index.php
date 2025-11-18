<?php
require_once 'includes/functions.php';
global $conn;

// Redirect to member login if not logged in, otherwise show a welcome page
if (!is_member_logged_in()) {
    redirect('login.php');
}

user_header('Welcome');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
    /* * ==================================
     * NEW ATTRACTIVE WELCOME STYLING
     * ==================================
     */
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Override body if user_header doesn't set it */
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #ece9e6 0%, #ffffff 100%);
        color: #333;
    }

    .welcome-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh; /* Use min-height to fill screen */
        padding: 40px 20px;
    }

    .welcome-card {
        background: #ffffff;
        padding: 40px 50px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 650px;
        text-align: center;
        border: 1px solid #e0e0e0;
        animation: fadeIn 0.6s ease-out;
    }

    .welcome-card h1 {
        color: #0056b3;
        font-weight: 700;
        font-size: 2.2rem;
        margin-bottom: 15px;
    }

    .welcome-card .lead {
        font-size: 1.25rem;
        color: #495057;
        margin-bottom: 30px;
        font-weight: 400;
    }

    /* Main Action Buttons */
    .button-group {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 35px;
        flex-wrap: wrap; /* Allow wrapping on small screens */
    }

    /* Base button styles to match dashboard */
    .btn {
        padding: 14px 28px;
        font-size: 17px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        border: none;
    }

    .btn i {
        margin-right: 10px;
    }

    .btn-lg {
        padding: 16px 32px;
        font-size: 18px;
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

    /* Quick Links Section */
    .info-section {
        margin-top: 30px;
        border-top: 1px solid #eee;
        padding-top: 30px;
        text-align: left;
    }

    .info-section h2 {
        font-weight: 600;
        color: #333;
        font-size: 1.5rem;
        margin-bottom: 20px;
        text-align: center;
    }

    .info-section ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .info-section li {
        margin-bottom: 12px;
    }

    .info-section li a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #343a40;
        background: #f8f9fa;
        padding: 14px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 16px;
        border: 1px solid #e9ecef;
    }

    .info-section li a:hover {
        background: #e9ecef;
        color: #0056b3;
        transform: translateX(5px);
        border-color: #d1d9e2;
    }

    .info-section li a i {
        margin-right: 15px;
        color: #007bff;
        width: 20px; /* Ensures consistent icon spacing */
        text-align: center;
    }
</style>

<div class="welcome-container">
    <div class="welcome-card">
        <h1><i class="fas fa-book-reader" style="color: #007bff;"></i> Welcome to the Library!</h1>
        <p class="lead">Hello, <strong><?php echo htmlspecialchars($_SESSION['member_full_name'] ?? 'Member'); ?></strong>. Your journey to knowledge starts here.</p>
        
        <div class="button-group">
            <a href="dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-user-circle"></i> My Dashboard</a>
            <a href="search.php" class="btn btn-secondary btn-lg"><i class="fas fa-search"></i> Search Catalog</a>
        </div>
        
        <div class="info-section">
            <h2>Quick Links</h2>
            <ul>
                <li><a href="dashboard.php#borrowed"><i class="fas fa-book-open"></i> View Currently Borrowed Books</a></li>
                <li><a href="dashboard.php#fines"><i class="fas fa-money-bill-wave"></i> View Outstanding Fines</a></li>
                <li><a href="dashboard.php#profile"><i class="fas fa-cog"></i> Update My Profile/Password</a></li>
            </ul>
        </div>
    </div>
</div>

<?php
user_footer();
close_db_connection($conn);
?>