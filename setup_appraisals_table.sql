-- SQL Script to create the appraisals table
-- Execute this in your MySQL database (payslip_db)

CREATE TABLE IF NOT EXISTS `appraisals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_code` varchar(50) NOT NULL,
  `previous_basic` decimal(12, 2) NOT NULL,
  `increment_percentage` decimal(5, 2) NOT NULL,
  `new_basic` decimal(12, 2) NOT NULL,
  `appraisal_date` date NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `emp_code` (`emp_code`),
  CONSTRAINT `appraisals_ibfk_1` FOREIGN KEY (`emp_code`) REFERENCES `employees` (`emp_code`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

