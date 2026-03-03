-- --------------------------------------------------------
-- Database: `expenses_management`
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `expenses_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `expenses_management`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `profile_picture` VARCHAR(255) DEFAULT 'default_profile.png',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `expenses`
-- --------------------------------------------------------

CREATE TABLE `expenses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `Date` DATE NOT NULL,
  `ItemName` VARCHAR(255) NOT NULL,
  `Category` VARCHAR(255) NOT NULL,
  `Amount` DECIMAL(10,2) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_user` (`user_id`),
  CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `income`
-- --------------------------------------------------------

CREATE TABLE `income` (
  `incomeID` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `source` VARCHAR(100) DEFAULT NULL,
  `income_date` DATE DEFAULT NULL,
  `date_added` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`incomeID`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `income_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `budgets`
-- --------------------------------------------------------

CREATE TABLE `budgets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `period` ENUM('monthly', 'weekly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_budget_user` (`user_id`),
  CONSTRAINT `fk_budget_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Sample data for table `users`
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `status`, `profile_picture`) VALUES
(18, 'Sujan Katuwal', 'suzand.katuwal@gmail.com', '$2y$10$Hw9OQMi3Nute9D8dkExYV.7Krux.UkM3dy..d5CdeRRE57I2L2co.', 'admin', '2025-03-11 13:01:33', 'approved', '67d03a6ade50b_RyomenSuzan.jpg'),
(20, 'Sujan Katuwal', 'sujankatuwal29@gmail.com', '$2y$10$TMfryqy3MO8ehi15uPBbj.vx6ox9sed49tdn0HS3exB11OUScEBwm', 'user', '2025-03-11 13:08:05', 'approved', '67d0386e07811_EDV new pic.JPG'),
(24, 'Sujan Katuwal', 'sujankatuwal00@gmail.com', '$2y$10$k.qV7IbrkzI65LD0llZuTe/PfS2odkwrqPw4cp14rsD/XCLW.YNia', 'user', '2025-03-24 05:01:32', 'approved', 'default_profile.png');

-- --------------------------------------------------------
-- Sample data for table `expenses`
-- --------------------------------------------------------

INSERT INTO `expenses` (`id`, `Date`, `ItemName`, `Category`, `Amount`, `user_id`) VALUES
(24, '2025-03-20', 'Petrol', 'Transportation', 1000.00, 20),
(25, '2025-03-16', 'buss charge', 'Transportation', 500.00, 20),
(26, '2025-03-12', 'coca cola', 'Food & Drinks', 5000.00, 20),
(27, '2025-03-24', 'exam feee', 'Miscellaneous', 1000.00, 24),
(28, '2025-03-27', 'Movie', 'Entertainment', 1000.00, 20),
(29, '2025-03-27', 'Kitchen sink', 'Housing', 1000.00, 20),
(30, '2025-03-27', 'watch', 'Personal Care', 1000.00, 20),
(31, '2025-03-27', 'Pizza', 'Food & Drinks', 10000.00, 20),
(32, '2025-03-27', 'Movie', 'Entertainment', 5000.00, 20),
(33, '2025-03-27', 'Fridge', 'Housing', 6000.00, 20),
(34, '2025-03-27', 'Mobile', 'Personal Care', 8000.00, 20),
(35, '2025-03-27', 'Petrol', 'Transportation', 7000.00, 20);

-- --------------------------------------------------------
-- Sample data for table `income`
-- --------------------------------------------------------

INSERT INTO `income` (`incomeID`, `user_id`, `amount`, `source`, `income_date`, `date_added`) VALUES
(11, 20, 40000.00, 'salary', '2025-03-17', '2025-03-17 10:25:46'),
(12, 24, 10000.00, 'freelance', '2025-03-24', '2025-03-24 05:03:26');
