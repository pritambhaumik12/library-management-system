-- Database: `lms_db`
-- Host: XAMPP (localhost)

-- --------------------------------------------------------
-- 1. Table structure for `tbl_admin` (Librarian/Admin Accounts)
-- --------------------------------------------------------
CREATE TABLE `tbl_admin` (
  `admin_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL, -- Stored in plain text as requested by user
  `full_name` VARCHAR(255) NOT NULL,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0, -- 1 for super admin (can access settings), 0 for regular librarian
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Admin User (Username: admin, Password: password)
INSERT INTO `tbl_admin` (`username`, `password`, `full_name`, `is_super_admin`) VALUES
('admin', 'password', 'System Administrator', 1);

-- --------------------------------------------------------
-- 2. Table structure for `tbl_settings` (System Configuration)
-- --------------------------------------------------------
CREATE TABLE `tbl_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Settings
INSERT INTO `tbl_settings` (`setting_key`, `setting_value`, `description`) VALUES
('library_name', 'Central Library LMS', 'The name of the library'),
('fine_per_day', '5', 'Fine amount per day for overdue books'),
('currency_symbol', '₹', 'Currency symbol for fines'),
('max_borrow_days', '14', 'Maximum number of days a book can be borrowed'),
('max_borrow_limit', '3', 'Maximum number of books a member can borrow at one time');

-- --------------------------------------------------------
-- 3. Table structure for `tbl_books` (Book Catalog)
-- --------------------------------------------------------
CREATE TABLE `tbl_books` (
  `book_id` INT(11) NOT NULL AUTO_INCREMENT, -- Internal ID for the book title
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `isbn` VARCHAR(20) UNIQUE,
  `category` VARCHAR(100) NOT NULL,
  `total_quantity` INT(11) NOT NULL,
  `available_quantity` INT(11) NOT NULL,
  `shelf_location` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table structure for `tbl_book_copies` (Individual Book Copies)
-- --------------------------------------------------------
CREATE TABLE `tbl_book_copies` (
  `copy_id` INT(11) NOT NULL AUTO_INCREMENT,
  `book_id` INT(11) NOT NULL,
  `book_uid` VARCHAR(50) NOT NULL UNIQUE, -- Serialized ID (e.g., BWU/BOOK/101)
  `status` ENUM('Available', 'Issued', 'Reserved', 'Lost') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`copy_id`),
  FOREIGN KEY (`book_id`) REFERENCES `tbl_books`(`book_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table structure for `tbl_members` (Student/Faculty Accounts)
-- --------------------------------------------------------
CREATE TABLE `tbl_members` (
  `member_id` INT(11) NOT NULL AUTO_INCREMENT,
  `member_uid` VARCHAR(50) NOT NULL UNIQUE, -- Student/Employee ID (used as login username)
  `password` VARCHAR(255) NOT NULL, -- Stored in plain text as requested by user
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) UNIQUE,
  `department` VARCHAR(100) NOT NULL,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Table structure for `tbl_circulation` (Issue and Return History)
-- --------------------------------------------------------
CREATE TABLE `tbl_circulation` (
  `circulation_id` INT(11) NOT NULL AUTO_INCREMENT,
  `copy_id` INT(11) NOT NULL,
  `member_id` INT(11) NOT NULL,
  `issue_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `return_date` DATE DEFAULT NULL,
  `status` ENUM('Issued', 'Returned', 'Overdue') NOT NULL DEFAULT 'Issued',
  `issued_by_admin_id` INT(11) NOT NULL,
  `returned_by_admin_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`circulation_id`),
  FOREIGN KEY (`copy_id`) REFERENCES `tbl_book_copies`(`copy_id`),
  FOREIGN KEY (`member_id`) REFERENCES `tbl_members`(`member_id`),
  FOREIGN KEY (`issued_by_admin_id`) REFERENCES `tbl_admin`(`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Table structure for `tbl_fines` (Fine Tracking)
-- --------------------------------------------------------
CREATE TABLE `tbl_fines` (
  `fine_id` INT(11) NOT NULL AUTO_INCREMENT,
  `circulation_id` INT(11) NOT NULL,
  `member_id` INT(11) NOT NULL,
  `fine_amount` DECIMAL(10, 2) NOT NULL,
  `fine_date` DATE NOT NULL, -- Date fine was calculated (usually return_date)
  `payment_status` ENUM('Pending', 'Paid') NOT NULL DEFAULT 'Pending',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `paid_on` TIMESTAMP NULL DEFAULT NULL,
  `collected_by_admin_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`fine_id`),
  FOREIGN KEY (`circulation_id`) REFERENCES `tbl_circulation`(`circulation_id`),
  FOREIGN KEY (`member_id`) REFERENCES `tbl_members`(`member_id`),
  FOREIGN KEY (`collected_by_admin_id`) REFERENCES `tbl_admin`(`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
