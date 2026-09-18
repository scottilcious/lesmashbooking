-- Schema for scottde_lscbooking, extracted from the production dump on 2026-09-18.
-- No data. Used by tests/bootstrap.php to build the lsc_test database.

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `court` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `timeslot` varchar(10) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `non_member_id` int(11) DEFAULT NULL,
  `booking_status` varchar(255) NOT NULL,
  `booking_type` varchar(255) NOT NULL DEFAULT 'member booking',
  `daily_member_type` varchar(255) NOT NULL DEFAULT 'none',
  `payment` varchar(10) DEFAULT NULL,
  `transaction_id` int(11) NOT NULL,
  `payment_remark` varchar(255) DEFAULT NULL,
  `slip` varchar(255) DEFAULT NULL,
  `coach` varchar(255) DEFAULT NULL,
  `coach_extra_player` int(11) DEFAULT NULL,
  `coach_name` varchar(255) DEFAULT NULL,
  `booking_note` varchar(255) DEFAULT NULL,
  `non_member_info` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `member_number` int(11) DEFAULT NULL,
  `member_type` varchar(255) DEFAULT NULL,
  `member_status` varchar(255) DEFAULT NULL,
  `member_phone` varchar(255) DEFAULT NULL,
  `member_email` varchar(255) DEFAULT NULL,
  `member_password` varchar(255) DEFAULT NULL,
  `member_since` date DEFAULT NULL,
  `member_length` varchar(255) DEFAULT NULL,
  `last_renewed` date DEFAULT NULL,
  `member_expiration` date DEFAULT NULL,
  `new_price` int(11) DEFAULT 0,
  `discount` int(11) DEFAULT 0,
  `credit` decimal(10,2) DEFAULT 0.00,
  `member_note` varchar(255) NOT NULL DEFAULT 'none',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `non_members`;
CREATE TABLE `non_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_name` varchar(255) NOT NULL,
  `member_phone` varchar(255) NOT NULL,
  `member_email` varchar(255) NOT NULL,
  `member_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_title` varchar(255) DEFAULT NULL,
  `assoc_transaction_id` int(11) DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `non_member_id` int(11) DEFAULT NULL,
  `non_member_info` varchar(255) DEFAULT NULL,
  `transaction_amount` int(11) NOT NULL,
  `transaction_type` varchar(255) NOT NULL,
  `payment_type` varchar(255) DEFAULT NULL,
  `slip_url` varchar(255) DEFAULT NULL,
  `transaction_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `wait_list`;
CREATE TABLE `wait_list` (
  `wait_list_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `non_member_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `timeslot` varchar(255) NOT NULL,
  `member_type` varchar(255) DEFAULT NULL,
  `waitlist_note` varchar(255) DEFAULT NULL,
  `waitlist_status` varchar(255) DEFAULT NULL,
  `free_court` varchar(255) DEFAULT NULL,
  `waitlist_booking_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`wait_list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
