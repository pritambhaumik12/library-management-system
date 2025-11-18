# Welcome to Library Management System (LMS)

A comprehensive, web-based Library Management System built using **PHP** and **MySQL**. This system allows librarians to manage books, members, and circulation (issues/returns), while allowing members (students/faculty) to browse the catalog and view their borrowing history.

## 🚀 Features

### Admin Panel (Librarian)
* **Dashboard:** View real-time statistics (Total books, issued books, overdue, etc.).
* **Book Management:** Add, edit, and delete books and manage individual book copies.
* **Member Management:** Register and manage student/faculty members.
* **Circulation:** Issue and Return books with automatic fine calculation.
* **Fines:** Track and collect payments for overdue books.
* **Reports:** View borrowing history, overdue lists, and financial reports.
* **Settings:** Configure library rules (fine amount per day, borrowing limits, etc.).

### Member Panel (User)
* **Catalog Search:** Search for books by title, author, or category.
* **My Dashboard:** View current borrowings and outstanding fines.
* **History:** View complete history of borrowed and returned books.
* **Profile:** Update personal details and change password.

## 🛠️ Tech Stack
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
* **Backend:** PHP (Native)
* **Database:** MySQL / MariaDB
* **Server:** Apache (via XAMPP/WAMP)

## 📂 Project Structure

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





# Library Management System (LMS) - User Manual

This manual provides a comprehensive guide to using the **Library Management System**. It is divided into two main sections for the different user roles: **Administrators** and **Members**.

## Table of Contents
* [Part 1: Admin User Manual](#part-1-admin-user-manual)
* [Part 2: Member User Manual](#part-2-member-user-manual)

---

## Part 1: Admin User Manual

The **Admin Panel** is designed for librarians to manage the entire library operation, including the catalog, user accounts, circulation, and system settings.

### 1. Access and Login
To access the administrative dashboard, navigate to the admin login URL in your browser.

**Login Credentials**
Enter your assigned username and password. If you are setting this up for the first time, use the default super admin credentials:
* **Username:** `admin`
* **Password:** `password`

> **Note:** It is highly recommended to change the default password immediately after your first login for security purposes.

### 2. Dashboard Overview
Upon successful login, you will be greeted by the **Dashboard**. This central hub provides real-time statistics about the library's status.

**Key Metrics Displayed:**
* **Total Books:** The count of unique titles in the catalog.
* **Total Members:** The number of active student/faculty accounts.
* **Books Issued:** The number of books currently borrowed.
* **Overdue Books:** A count of books that have exceeded their return date.

### 3. Book Management
Navigate to the **Book Management** section via the sidebar to maintain the library catalog.

#### Adding a New Book
1.  Click on **Add New Book**.
2.  Fill in the required details:
    * *Title*
    * *Author*
    * *ISBN*
    * *Category*
    * *Shelf Location*
3.  Specify the **Total Quantity**. The system will automatically generate unique `Book UIDs` for every copy (e.g., `LMS/BOOK/00001-1`).

#### Editing and Deleting
* **Edit:** Click the <ins>Edit</ins> button next to a book to modify its details.
* **Delete:** Click the <ins>Delete</ins> button to remove a title.
    > **Warning:** You cannot delete a book if any of its copies are currently issued to a member.

### 4. Circulation Operations
This section handles the core library functions of issuing and returning books.

#### Issuing a Book
1.  Go to the **Issue Book** page.
2.  Enter the **Book UID** (scan the barcode or type it manually).
3.  Enter the **Student/Employee ID**.
4.  Click **Issue**. The system will validate the member's borrowing limit before confirming.

#### Returning a Book
1.  Go to the **Return Book** page.
2.  Enter the **Book UID**.
3.  The system will calculate fines automatically based on the return date.
    * If the book is **Overdue**, the fine amount will be displayed.
    * You must collect the fine payment (Cash/Card) to complete the return process.

### 5. Member Management
Administrators can manage user accounts from the **Members** page.

* **Add Member:** Create new accounts for students or faculty. You will need to assign a *Student ID*, *Name*, and *Department*.
* **Status:** You can toggle a member's status between `Active` and `Inactive`. Inactive members cannot borrow books.

### 6. System Settings
**Super Admins** have exclusive access to the **Settings** page to configure global rules.

| Setting | Description |
| :--- | :--- |
| **Library Name** | The name displayed on the portal header and receipts. |
| **Fine Per Day** | The monetary amount charged for each day a book is late. |
| **Currency** | The symbol used for financial transactions (e.g., `$`, `₹`). |
| **Borrowing Limit** | The maximum number of books a user can hold at once. |

---

## Part 2: Member User Manual

The **Member Portal** allows students and faculty to browse the collection and manage their account.

### 1. Login
Access the portal using your **Student/Employee ID** and password.
* If you do not have an account, please contact the librarian.
* Default password for new accounts is usually set to `password`.

### 2. My Dashboard
Your personal dashboard highlights your current library activity.

**Profile Card**
* View your personal details.
* Use the **Change Password** tab to update your credentials.

**Status Lists**
* **Borrowed Books:** A list of books you currently have, along with their due dates.
* **Outstanding Fines:** Any unpaid fines from previous late returns.

### 3. Searching the Catalog
Use the **Search** feature to find books without needing to log in.
1.  Enter a keyword (Title, Author, or Category).
2.  Press **Enter** or click the search icon.
3.  Review the results to see if a book is **Available** or **Unavailable**.

### 4. Borrowing History
Click on **My History** to view a complete log of your library usage.
* `Issue Date`
* `Return Date`
* `Fine Status` (Paid/Pending)

This history serves as a permanent record of all materials you have utilized from the library.
