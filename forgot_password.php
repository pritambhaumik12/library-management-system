<?php
require_once 'includes/functions.php';
user_header('Forgot Password'); // <-- This renders the header
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<style>
    /* * ==================================
     * NEW PAGE LAYOUT STYLING
     * ==================================
     */
    
    html {
        height: 100%;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(120deg, #f3e7e9 0%, #e3eeff 100%);
        color: #333;
        
        /* * This is the key fix:
         * 1. Make the body a vertical flex container.
         * 2. Make it take up the full screen height.
         */
        display: flex;
        flex-direction: column; /* Stack header, main, footer vertically */
        min-height: 100vh; /* Full viewport height */
        margin: 0;
    }

    /* * NEW WRAPPER for the content *between* header and footer
     */
    .page-content-wrapper {
        flex: 1; /* This makes this element *grow* to fill all available space */
        
        /* This part now centers the card */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }


    /* * ==================================
     * ATTRACTIVE CARD STYLING (Unchanged)
     * ==================================
     */
    
    .container {
        max-width: 550px;
        width: 100%;
        margin: 0 auto;
    }

    .forgot-card {
        background: #ffffff;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.07);
        border: 1px solid #ffffff;
        text-align: center;
        opacity: 0;
        transform: translateY(20px);
        animation: fadeInCard 0.6s ease-out forwards;
    }

    @keyframes fadeInCard {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card-icon-wrapper {
        margin-bottom: 20px;
    }
    
    .card-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #007bff, #0056b3);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 30px;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
    }

    .forgot-card h2 {
        margin-bottom: 15px;
        color: #343a40;
        font-size: 26px;
        font-weight: 600;
    }

    .forgot-card .text-muted {
        font-size: 1rem;
        color: #6c757d;
        margin-bottom: 30px;
        line-height: 1.6;
    }
    
    .alert-info-enhanced {
        background-color: #e6f7ff;
        border: 1px solid #b3e0ff;
        border-radius: 8px;
        padding: 20px;
        text-align: left;
        color: #0056b3;
        margin-bottom: 30px;
        display: flex;
        align-items: flex-start;
    }
    
    .alert-info-enhanced .alert-icon {
        font-size: 22px;
        color: #0056b3;
        margin-right: 15px;
        padding-top: 3px;
    }

    .alert-info-enhanced p {
        margin-bottom: 10px;
        font-size: 15px;
        line-height: 1.6;
        font-weight: 500;
        margin: 0;
    }
    
    .alert-info-enhanced p:last-child {
        margin-top: 10px;
    }

    .alert-info-enhanced strong {
        color: #004085;
    }

    .btn-gradient {
        width: 100%;  
        padding: 14px 20px;
        background: linear-gradient(90deg, #007bff, #0056b3);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
    }
    
    .btn-gradient:hover {
        background: linear-gradient(90deg, #0056b3, #007bff);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
    }
    
    .btn-gradient i {
        margin-right: 10px;
    }
</style>

<main class="page-content-wrapper">
    <div class="container">
        <div class="forgot-card">
            
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fas fa-lock-open"></i>
                </div>
            </div>
            
            <h2>Forgot Password</h2>
            <p class="text-muted">For security reasons, password reset requests must be handled by the library administration.</p>
            
            <div class="alert alert-info-enhanced">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="alert-text">
                    <p>Please contact the librarian with your <strong>Student/Employee ID</strong> to request a password reset.</p>
                    <p>The librarian can reset your password via the Admin Panel.</p>
                </div>
            </div>
            
            <a href="login.php" class="btn btn-gradient"><i class="fas fa-sign-in-alt"></i> Back to Login</a>
        </div>
    </div>
</main>
<?php
user_footer(); // <-- This renders the footer
close_db_connection($conn);
?>