# Welcome to Library Management System (LMS)
A comprehensive, web-based Library Management System built using PHP and MySQL. This system allows librarians to manage books, members, and circulation (issues/returns), while allowing members (students/faculty) to browse the catalog and view their borrowing history.

**_🚀 Features_**

**Admin Panel (Librarian)**
Dashboard: View real-time statistics (Total books, issued books, overdue, etc.).

Book Management: Add, edit, and delete books and manage individual book copies.

Member Management: Register and manage student/faculty members.

Circulation: Issue and Return books with automatic fine calculation.

Fines: Track and collect payments for overdue books.

Reports: View borrowing history, overdue lists, and financial reports.

Settings: Configure library rules (fine amount per day, borrowing limits, etc.).

**Member Panel (User)**

Catalog Search: Search for books by title, author, or category.

My Dashboard: View current borrowings and outstanding fines.

History: View complete history of borrowed and returned books.

Profile: Update personal details and change password.

**_🛠️ Tech Stack_**

Frontend: HTML5, CSS3, JavaScript (Vanilla)

Backend: PHP (Native)

Database: MySQL / MariaDB

Server: Apache (via XAMPP/WAMP)

**📂 Project Structure**
```

lms_project/
├── admin/              # Admin panel files (Protected)
├── assets/             # CSS and JS files
├── includes/           # Database connection and helper functions
├── dashboard.php       # User dashboard
├── index.php           # Landing page
├── login.php           # User login
├── search.php          # Public catalog search
└── lms_db_setup.sql    # Database import file

```
**⚙️ Installation & Setup**
Follow these steps to get the project running on your local machine.

1. Prerequisites
Install XAMPP (recommended), WAMP, or MAMP.

Ensure Apache and MySQL are running.

2. File Setup
Download or Clone this repository.

Copy the lms_project folder.

Paste it into your server's root directory:

XAMPP: C:\xampp\htdocs\

WAMP: C:\wamp64\www\

MAMP: /Applications/MAMP/htdocs/

3. Database Setup
Open your browser and go to phpMyAdmin (http://localhost/phpmyadmin).

Create a new database named: lms_db

Click on the Import tab.

Choose the file lms_db_setup.sql (located in the project root) and click Import.

4. Configuration (Optional)
By default, the project is configured for XAMPP settings (User: root, Password: ). If your database uses a password, edit includes/db_connect.php:

**_PHP_**
```
$servername = "localhost";
$username = "root";      // Change if different
$password = "";          // Add your MySQL password here
$dbname = "lms_db";
```
**🖥️ Usage**

*Access the System*

Open your browser and visit: http://localhost/lms_project/

Default Login Credentials

**Admin Login:**
```
URL: http://localhost/lms_project/admin/login.php

Username: admin

Password: password
```
**Member Login:**
```
URL: http://localhost/lms_project/login.php
```

Note: You must create a member via the Admin Panel first. The default password for new members is set to 'password'.

**_⚠️ Security Note:_**

This project is designed for educational purposes. Passwords are currently stored in plain text to make the code easier to understand for beginners. For a production environment, please update the code to use password_hash() and password_verify().

**_📜 License:_**

This project is open-source and available only for educational use.
