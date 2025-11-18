<?php
require_once '../includes/functions.php';
global $conn;

// If already logged in, redirect to dashboard
if (is_admin_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Prepare the SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT admin_id, password, full_name, is_super_admin FROM tbl_admin WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // **SECURITY NOTE**: Password stored in plain text as requested by user.
            if ($password === $admin['password']) {
                // Login successful
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_full_name'] = $admin['full_name'];
                $_SESSION['is_super_admin'] = $admin['is_super_admin'];
                redirect('index.php');
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LMS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* * ==================================
         * ATTRACTIVE LOGIN STYLING
         * ==================================
         */
        
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden; /* Important for rounded corners */
        }

        /* * =========================
         * Left Branding Panel
         * =========================
         */
        .login-panel-left {
            flex: 1;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .login-panel-left .brand-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .login-panel-left h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }

        .login-panel-left p {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.6;
            opacity: 0.9;
        }

        /* * =========================
         * Right Login Form Panel
         * =========================
         */
        .login-panel-right {
            flex: 1;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-container h2 {
            margin-bottom: 30px;
            color: #333;
            font-size: 26px;
            font-weight: 600;
        }
        
        .login-container h2 i {
            margin-right: 10px;
            color: #007bff;
        }

        /* Modernized Form Group */
        .login-form .form-group {
            margin-bottom: 20px;
            position: relative; /* For icon positioning */
        }

        .login-form .form-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 16px;
        }

        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 14px 14px 14px 45px; /* Left padding for icon */
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        
        .login-form input[type="text"]:focus,
        .login-form input[type="password"]:focus {
            outline: none;
            border-color: #007bff;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }

        /* Gradient Button */
        .login-form button {
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

        .login-form button:hover {
            background: linear-gradient(90deg, #0056b3, #007bff);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }
        
        .login-form button i {
            margin-right: 8px;
        }
        
        /* New Error Message Style */
        .error-message {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            text-align: left;
        }

        /* Member Portal Link */
        .member-login-link {
            margin-top: 25px;
            font-size: 0.95em;
        }
        
        .member-login-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .member-login-link a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* * =========================
         * Responsive Design
         * =========================
         */
        @media (max-width: 900px) {
            .login-panel-left {
                display: none; /* Hide left panel on smaller screens */
            }
            .login-wrapper {
                max-width: 500px;
                min-height: auto;
            }
            .login-panel-right {
                padding: 40px 30px;
            }
        }
        
        @media (max-width: 500px) {
             body {
                padding: 0;
             }
            .login-wrapper {
                border-radius: 0;
                width: 100%;
                min-height: 100vh;
            }
            .login-panel-right {
                padding: 30px 20px;
            }
            .login-container h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        
        <div class="login-panel-left">
            <i class="brand-icon fas fa-book-reader"></i>
            <h2>LMS Admin Panel</h2>
            <p>Welcome back. Please log in to manage the library system, members, and catalog.</p>
        </div>

        <div class="login-panel-right">
            <div class="login-container">
                <h2><i class="fas fa-lock"></i> Admin Login</h2>
                
                <?php if ($error): ?>
                    <p class="error-message"><?php echo $error; ?></p>
                <?php endif; ?>
                
                <form class="login-form" method="POST">
                    <div class="form-group">
                        <i class="form-icon fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Username" required>
                    </div>
                    <div class="form-group">
                        <i class="form-icon fas fa-key"></i>
                        <input type="password" id="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit"><i class="fas fa-sign-in-alt"></i> Log In</button>
                </form>

                <div class="member-login-link">
                    <a href="../">
                        <i class="fas fa-user-graduate"></i> Go to Member Portal
                    </a>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
<?php close_db_connection($conn); ?>