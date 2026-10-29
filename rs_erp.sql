-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 04, 2026 at 03:08 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rs_erp`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
('040f61b3-53a2-40ea-9a94-1898f550e664', 'ada31420-9318-4814-8409-6db7cdd00ad9', 'Account Created via Signup', '{\"email\":\"rita.tester@example.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:12:57'),
('1ccbb706-e834-4a44-aeff-73ed1b43c09b', 'u-staff-0001', 'User Logged In', '{\"email\":\"staff@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:34'),
('2a9f5108-bb9b-40ca-8ff4-990c5737dad4', 'u-admin-0001', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:13'),
('35e7d709-2684-48b0-b85b-b46f21a9a0fb', 'u-staff-0001', 'User Logged In', '{\"email\":\"staff@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:13'),
('39d4fad8-ce4b-44f5-8912-e4179738b26b', 'u-student-0001', 'Processed Paystack Installment Payment', '{\"reference\":\"PAYSTACK_REF_4A9X0VEPY\",\"amount\":290}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-09-18 12:36:16'),
('404426e5-10f8-47d4-ab12-7c051d955fdb', 'u-student-0001', 'User Logged Out', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:19:11'),
('50914954-7db7-4d8b-8bc3-21ddc28e356e', 'u-client-0001', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:13'),
('5a373dbe-da83-4881-b556-6a7bcd44b0f9', 'u-student-0001', 'User Logged In', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 06:42:47'),
('8dcf4ede-380e-45f3-915f-ae6bd3354017', 'u-student-0001', 'User Logged In', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-09-18 12:18:55'),
('996be969-80bd-4f95-83d5-f057a9ce76a0', 'u-student-0001', 'User Logged Out', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 06:53:15'),
('b8766581-d0f3-4305-ab39-01f2830cc76a', 'u-manager-0001', 'User Logged In', '{\"email\":\"manager@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:13'),
('cb74811b-2521-41ae-b4dd-bc1f7bd6f00f', 'u-student-0001', 'Enrolled in Course', '{\"course_id\":\"course-cloud-002\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:01:15'),
('d25c0d9f-9e99-4152-b0e1-1b0cc4f9860a', 'u-admin-0001', 'Updated account settings preferences', NULL, '127.0.0.1', 'curl/8.18.0', '2026-10-01 09:52:58'),
('d29a4ca7-e8e9-4dbe-b657-5ffa4f8842ad', 'u-student-0001', 'User Logged In', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:15:14'),
('df8aefb9-8252-4a51-816e-e64fd1ef01bb', 'u-student-0001', 'User Logged In', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', '2026-10-01 14:01:05'),
('e579661e-0049-425a-acd4-0b22b08efdb6', 'u-student-0001', 'User Logged In', '{\"email\":\"student@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 06:33:42'),
('ff9e373f-4ccb-443d-8397-8fb2b9890815', 'u-admin-0001', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-01 07:13:35');

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` varchar(36) NOT NULL,
  `client_id` varchar(36) NOT NULL,
  `assessor_id` varchar(36) DEFAULT NULL,
  `type` varchar(100) NOT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`questions`)),
  `score` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `status` enum('PENDING','SUBMITTED','GRADED','OVERDUE') DEFAULT 'PENDING',
  `submission` varchar(255) DEFAULT NULL,
  `grade` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `course_id`, `student_id`, `title`, `description`, `due_date`, `status`, `submission`, `grade`, `feedback`, `submitted_at`, `graded_at`, `created_at`, `updated_at`) VALUES
('as-001', 'course-fullstack-001', 'stud-rec-0001', 'Build a Secure Parameterized Database Wrapper', 'Implement a reusable db_query helper with strict bind_param type checking and transaction controls.', '2026-09-23 11:30:50', 'PENDING', NULL, NULL, NULL, NULL, NULL, '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `date` date NOT NULL,
  `status` enum('PRESENT','ABSENT','LATE','EXCUSED') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balances`
--

CREATE TABLE `balances` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `balances`
--

INSERT INTO `balances` (`id`, `user_id`, `amount`, `currency`, `created_at`, `updated_at`) VALUES
('bal-admin-0001', 'u-admin-0001', 0.00, 'USD', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('bal-client-0001', 'u-client-0001', 0.00, 'USD', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('bal-manager-0001', 'u-manager-0001', 0.00, 'USD', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('bal-staff-0001', 'u-staff-0001', 0.00, 'USD', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('bal-student-0001', 'u-student-0001', 0.00, 'USD', '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `certificate_id` varchar(100) NOT NULL,
  `qr_code` varchar(100) NOT NULL,
  `digital_signature` text NOT NULL,
  `issue_date` datetime DEFAULT current_timestamp(),
  `is_valid` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','COMPLETED','CANCELLED') DEFAULT 'ACTIVE',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course_id`, `name`, `description`, `start_date`, `end_date`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
('cls-001', 'course-fullstack-001', 'Cohort Alpha (Fall 2026)', 'Live weekend coding cohort covering fullstack architectures.', '2026-09-18 11:30:50', '2026-12-17 11:30:50', 30, 'ACTIVE', '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `class_resources`
--

CREATE TABLE `class_resources` (
  `id` varchar(36) NOT NULL,
  `class_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('VIDEO','DOCUMENT','PRESENTATION','AUDIO','LINK','OTHER') NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `uploaded_by` varchar(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_staff`
--

CREATE TABLE `class_staff` (
  `id` varchar(36) NOT NULL,
  `class_id` varchar(36) NOT NULL,
  `staff_id` varchar(36) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_staff`
--

INSERT INTO `class_staff` (`id`, `class_id`, `staff_id`, `role`, `assigned_at`) VALUES
('cst-001', 'cls-001', 'staff-rec-0001', 'Lead Instructor', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `class_students`
--

CREATE TABLE `class_students` (
  `id` varchar(36) NOT NULL,
  `class_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `assigned_by` varchar(100) DEFAULT NULL,
  `status` enum('ACTIVE','SUSPENDED','DROPPED','COMPLETED') DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_students`
--

INSERT INTO `class_students` (`id`, `class_id`, `student_id`, `assigned_at`, `assigned_by`, `status`) VALUES
('cs-001', 'cls-001', 'stud-rec-0001', '2026-09-18 11:30:50', NULL, 'ACTIVE');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `client_id` varchar(50) NOT NULL,
  `company_name` varchar(200) DEFAULT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `user_id`, `client_id`, `company_name`, `contact_person`, `website`, `industry`) VALUES
('17c0bb82-02e6-4279-a135-cd73829d4c61', 'ada31420-9318-4814-8409-6db7cdd00ad9', 'CL-87231', 'Rita Corp', 'Rita Tester', NULL, NULL),
('a934680b-7ab9-421b-8b5f-ef150006145b', '6a7967d8-0394-47ef-baac-3bab2101b3d9', 'CL-42836', 'Xander Corp', 'Zephania Willis', NULL, NULL),
('client-rec-0001', 'u-client-0001', 'CL-90210', 'Apex Global Technologies', 'David Miller', 'https://apextech.example.com', 'Fintech & Cloud Solutions'),
('f77e86f4-01a2-4a8e-9da3-276d68e181e4', 'ef80d5b4-3a3f-4d56-8396-b0e13166653c', 'CL-001', 'Acme Corporation', 'Alice Johnson', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` varchar(36) NOT NULL,
  `complaint_id` varchar(50) NOT NULL,
  `customer_id` varchar(36) NOT NULL,
  `customer_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MANAGER') NOT NULL,
  `title` varchar(191) NOT NULL,
  `details` text NOT NULL,
  `status` enum('OPEN','ADDRESSED','SORTED','CLOSED') DEFAULT 'OPEN',
  `priority` enum('LOW','MEDIUM','HIGH','URGENT') DEFAULT 'MEDIUM',
  `assigned_to` varchar(36) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `price` decimal(15,2) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `level` enum('BEGINNER','INTERMEDIATE','ADVANCED') DEFAULT 'BEGINNER',
  `mode` enum('ONLINE','OFFLINE','HYBRID') DEFAULT 'ONLINE',
  `status` enum('ACTIVE','INACTIVE','DRAFT','ARCHIVED') DEFAULT 'ACTIVE',
  `max_enrollment` int(11) DEFAULT NULL,
  `allow_installment` tinyint(1) DEFAULT 0,
  `installment_count` int(11) DEFAULT NULL,
  `installment_amount` decimal(15,2) DEFAULT NULL,
  `instructor_id` varchar(36) DEFAULT NULL,
  `category_id` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `price`, `duration`, `level`, `mode`, `status`, `max_enrollment`, `allow_installment`, `installment_count`, `installment_amount`, `instructor_id`, `category_id`, `created_at`, `updated_at`) VALUES
('course-cloud-002', 'Enterprise Cloud & DevOps Infrastructure', 'Hands-on enterprise systems deployment, container orchestration, and server security hardening.', NULL, 1200.00, '16 Weeks', 'ADVANCED', 'ONLINE', 'ACTIVE', 40, 1, 4, 300.00, 'staff-rec-0001', 'cat-cloud-0002', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('course-fullstack-001', 'Fullstack Web Architecture with Modern PHP & MySQL', 'Master production web application design with procedural and modular PHP, MySQL prepared statements, Tailwind CSS, and RESTful APIs.', NULL, 850.00, '12 Weeks', 'INTERMEDIATE', 'ONLINE', 'ACTIVE', 50, 1, 3, 290.00, 'staff-rec-0001', 'cat-web-0001', '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `course_categories`
--

CREATE TABLE `course_categories` (
  `id` varchar(36) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_categories`
--

INSERT INTO `course_categories` (`id`, `name`, `description`) VALUES
('cat-cloud-0002', 'Cloud Architecture', 'DevOps, CI/CD pipelines, containerization and enterprise systems.'),
('cat-web-0001', 'Web Development', 'Full-stack software engineering, modern PHP, and frontend architectures.');

-- --------------------------------------------------------

--
-- Table structure for table `course_resources`
--

CREATE TABLE `course_resources` (
  `id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `type` enum('VIDEO','DOCUMENT','PRESENTATION','AUDIO','LINK','OTHER') NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` varchar(36) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `client_id` varchar(36) DEFAULT NULL,
  `project_id` varchar(36) DEFAULT NULL,
  `uploaded_by` varchar(100) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `current_version` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_versions`
--

CREATE TABLE `document_versions` (
  `id` varchar(36) NOT NULL,
  `document_id` varchar(36) NOT NULL,
  `version_number` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `uploaded_by` varchar(100) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `change_summary` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `enrollment_date` datetime DEFAULT current_timestamp(),
  `status` enum('ACTIVE','COMPLETED','DROPPED','SUSPENDED') DEFAULT 'ACTIVE',
  `progress` int(11) DEFAULT 0,
  `completion_date` datetime DEFAULT NULL,
  `payment_type` enum('FULL','INSTALLMENT') DEFAULT 'FULL',
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `amount_due` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `progress`, `completion_date`, `payment_type`, `total_paid`, `amount_due`) VALUES
('7950388b-fa51-4f07-aff0-49992ba2abe5', 'stud-rec-0001', 'course-cloud-002', '2026-10-01 14:01:15', 'ACTIVE', 0, NULL, 'FULL', 1200.00, 0.00),
('enr-0001', 'stud-rec-0001', 'course-fullstack-001', '2026-09-18 11:30:50', 'ACTIVE', 33, NULL, 'INSTALLMENT', 580.00, 290.00);

-- --------------------------------------------------------

--
-- Table structure for table `expense_records`
--

CREATE TABLE `expense_records` (
  `id` varchar(36) NOT NULL,
  `category` enum('SALARIES','INFRASTRUCTURE','MARKETING','OPERATIONS','EQUIPMENT','UTILITIES','OTHER') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `receipt` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `course_id` varchar(36) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_resolved` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `student_id`, `course_id`, `title`, `content`, `category`, `tags`, `is_pinned`, `is_resolved`, `created_at`, `updated_at`) VALUES
('post-001', 'stud-rec-0001', 'course-fullstack-001', 'Best practice for securing mysqli prepared statements against type casting?', 'When binding decimal currency values in PHP mysqli, should we declare them as \"d\" or format them as strings \"s\" with precise decimal scale?', 'Coding', '[\"PHP\", \"MySQL\", \"Security\"]', 1, 0, '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `forum_post_likes`
--

CREATE TABLE `forum_post_likes` (
  `id` varchar(36) NOT NULL,
  `post_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_replies`
--

CREATE TABLE `forum_replies` (
  `id` varchar(36) NOT NULL,
  `post_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_solution` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_reply_likes`
--

CREATE TABLE `forum_reply_likes` (
  `id` varchar(36) NOT NULL,
  `reply_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `income_records`
--

CREATE TABLE `income_records` (
  `id` varchar(36) NOT NULL,
  `source` enum('TRAINING','SALES','SERVICES','CONSULTING','CERTIFICATION','OTHER') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `category` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `income_records`
--

INSERT INTO `income_records` (`id`, `source`, `amount`, `description`, `date`, `category`, `created_at`) VALUES
('2bb4f0f5-3584-4656-a75b-7fa323be7a96', 'TRAINING', 290.00, 'Installment #2 Course payment: PAYSTACK_REF_4A9X0VEPY', '2026-09-18 12:36:16', 'LMS Course Fee', '2026-09-18 12:36:16'),
('6fc40817-efd1-4a14-a701-b87344d1af2d', 'TRAINING', 1200.00, 'Manual enrollment course fee: student_id stud-rec-0001', '2026-10-01 14:01:15', 'LMS Course Fee', '2026-10-01 14:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `installment_payments`
--

CREATE TABLE `installment_payments` (
  `id` varchar(36) NOT NULL,
  `enrollment_id` varchar(36) NOT NULL,
  `installment_number` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `due_date` datetime NOT NULL,
  `paid_date` datetime DEFAULT NULL,
  `status` enum('PENDING','PAID','OVERDUE','CANCELLED') DEFAULT 'PENDING',
  `paystack_reference` varchar(191) DEFAULT NULL,
  `paystack_access_code` varchar(191) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `installment_payments`
--

INSERT INTO `installment_payments` (`id`, `enrollment_id`, `installment_number`, `amount`, `due_date`, `paid_date`, `status`, `paystack_reference`, `paystack_access_code`, `transaction_id`, `reminder_sent`, `reminder_sent_at`, `created_at`, `updated_at`) VALUES
('inst-001', 'enr-0001', 1, 290.00, '2026-09-08 11:30:50', '2026-09-18 11:30:50', 'PAID', 'PAYSTACK_REF_INIT_001', NULL, NULL, 0, NULL, '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('inst-002', 'enr-0001', 2, 290.00, '2026-09-20 11:30:50', '2026-09-18 12:36:16', 'PAID', 'PAYSTACK_REF_4A9X0VEPY', NULL, NULL, 0, NULL, '2026-09-18 11:30:50', '2026-09-18 12:36:16'),
('inst-003', 'enr-0001', 3, 290.00, '2026-10-20 11:30:50', NULL, 'PENDING', NULL, NULL, NULL, 0, NULL, '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` varchar(36) NOT NULL,
  `module_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `content` text DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `order_num` int(11) NOT NULL,
  `is_free` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `module_id`, `title`, `content`, `video_url`, `duration`, `order_num`, `is_free`) VALUES
('les-001', 'mod-001', '1.1 Relational Architecture & UTF8MB4 Collation', 'Detailed notes on creating high-performance relational schemas with strict foreign keys and indexed columns.', 'https://www.w3schools.com/html/mov_bbb.mp4', '25 Mins', 1, 1),
('les-002', 'mod-001', '1.2 Native Parameterized mysqli Statements', 'Preventing SQL injections via strict type declarations and transaction rollbacks.', 'https://www.w3schools.com/html/mov_bbb.mp4', '35 Mins', 2, 0),
('les-003', 'mod-002', '2.1 Cryptographic Session Tokens & CSRF Defense', 'Implementing stateless and session-based CSRF guards for POST endpoints.', 'https://www.w3schools.com/html/mov_bbb.mp4', '30 Mins', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_completions`
--

CREATE TABLE `lesson_completions` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lesson_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `completed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lesson_completions`
--

INSERT INTO `lesson_completions` (`id`, `student_id`, `lesson_id`, `completed_at`) VALUES
('8f3f342b-7797-46cd-ad67-26592377b76c', 'stud-rec-0001', 'les-001', '2026-10-01 06:52:49');

-- --------------------------------------------------------

--
-- Table structure for table `meetings`
--

CREATE TABLE `meetings` (
  `id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` varchar(36) NOT NULL,
  `staff_id` varchar(36) DEFAULT NULL,
  `meeting_date` datetime NOT NULL,
  `duration` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `meeting_url` varchar(255) DEFAULT NULL,
  `status` enum('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED','RESCHEDULED') DEFAULT 'SCHEDULED',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `order_num` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `course_id`, `title`, `description`, `order_num`, `is_active`) VALUES
('mod-001', 'course-fullstack-001', 'Module 1: High-Performance Database Design', 'Schema normalization, indexing, and parameterized query execution.', 1, 1),
('mod-002', 'course-fullstack-001', 'Module 2: Authentication, Security & CSRF Hardening', 'Session token storage, CSRF defense, and XSS sanitization.', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'GENERAL',
  `priority` enum('LOW','MEDIUM','HIGH') DEFAULT 'MEDIUM',
  `is_read` tinyint(1) DEFAULT 0,
  `archived` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `priority`, `is_read`, `archived`, `link`, `metadata`, `created_at`) VALUES
('aabf26bf-b05b-4da9-ad4b-26b6d50f50f6', 'u-student-0001', 'Installment Payment Successful', 'Payment of $290.00 has been successfully processed.', 'FINANCE', 'MEDIUM', 0, 0, NULL, NULL, '2026-09-18 12:36:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` varchar(36) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `type` enum('DEPOSIT','MILESTONE','FINAL','COURSE_FEE','LATE_FEE') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `method` enum('PAYSTACK','CREDIT_CARD','BANK_TRANSFER','USSD','CASH','MOBILE_MONEY') NOT NULL,
  `status` enum('PENDING','COMPLETED','FAILED','REFUNDED','OVERDUE') DEFAULT 'PENDING',
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `paystack_reference` varchar(191) DEFAULT NULL,
  `paystack_access_code` varchar(191) DEFAULT NULL,
  `student_id` varchar(36) DEFAULT NULL,
  `client_id` varchar(36) DEFAULT NULL,
  `project_id` varchar(36) DEFAULT NULL,
  `course_id` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `transaction_id`, `type`, `amount`, `method`, `status`, `description`, `due_date`, `paid_at`, `receipt_url`, `paystack_reference`, `paystack_access_code`, `student_id`, `client_id`, `project_id`, `course_id`, `created_at`, `updated_at`) VALUES
('3b14cfb9-37ea-440b-8a58-fdf2a73fd590', 'TXN-6892658', 'COURSE_FEE', 290.00, 'PAYSTACK', 'COMPLETED', 'Installment #2 Course payment: PAYSTACK_REF_4A9X0VEPY', NULL, '2026-09-18 12:36:16', NULL, 'PAYSTACK_REF_4A9X0VEPY', NULL, 'stud-rec-0001', NULL, NULL, 'course-fullstack-001', '2026-09-18 12:36:16', '2026-09-18 12:36:16'),
('f2fee556-b563-4939-aeb9-50f27c60f850', 'TXN-9334349', 'COURSE_FEE', 1200.00, 'CASH', 'COMPLETED', 'Manual enrollment course fee: student_id stud-rec-0001', NULL, '2026-10-01 14:01:15', NULL, NULL, NULL, 'stud-rec-0001', NULL, NULL, 'course-cloud-002', '2026-10-01 14:01:15', '2026-10-01 14:01:15');

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` varchar(36) NOT NULL,
  `staff_id` varchar(36) NOT NULL,
  `review_period` varchar(100) NOT NULL,
  `rating` int(11) DEFAULT 1,
  `feedback` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `review_date` datetime DEFAULT current_timestamp(),
  `reviewed_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `user_id`, `full_name`, `bio`) VALUES
('a7c2d40e-348e-4d6b-a509-c6cfb51f2d81', 'ada31420-9318-4814-8409-6db7cdd00ad9', 'Rita Tester', NULL),
('prof-admin-0001', 'u-admin-0001', 'Alexander Vance', 'Registered ADMIN at Royal Solutions ERP command platform.'),
('prof-client-0001', 'u-client-0001', 'David Miller', 'Registered CLIENT at Royal Solutions ERP command platform.'),
('prof-manager-0001', 'u-manager-0001', 'Eleanor Sterling', 'Registered MANAGER at Royal Solutions ERP command platform.'),
('prof-staff-0001', 'u-staff-0001', 'Marcus Chen', 'Registered STAFF at Royal Solutions ERP command platform.'),
('prof-student-0001', 'u-student-0001', 'Jane Smith', 'Registered STUDENT at Royal Solutions ERP command platform.');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` varchar(36) NOT NULL,
  `project_id` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `client_id` varchar(36) NOT NULL,
  `supervisor_id` varchar(36) DEFAULT NULL,
  `status` enum('PLANNING','IN_PROGRESS','COMPLETED','ON_HOLD','CANCELLED') DEFAULT 'PLANNING',
  `budget` decimal(15,2) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `progress` int(11) DEFAULT 0,
  `category` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_id`, `title`, `description`, `client_id`, `supervisor_id`, `status`, `budget`, `start_date`, `end_date`, `progress`, `category`, `created_at`, `updated_at`) VALUES
('proj-001', 'PRJ-5501', 'Enterprise Fintech Dashboard & Payment Hub', 'Production development of client cloud platform.', 'client-rec-0001', 'staff-rec-0001', 'IN_PROGRESS', 14500.00, '2026-09-18 11:30:50', '2026-11-17 11:30:50', 45, 'Fintech SaaS', '2026-09-18 11:30:50', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `project_milestones`
--

CREATE TABLE `project_milestones` (
  `id` varchar(36) NOT NULL,
  `project_id` varchar(36) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_milestones`
--

INSERT INTO `project_milestones` (`id`, `project_id`, `title`, `description`, `due_date`, `completed`, `completed_at`) VALUES
('ms-001', 'proj-001', 'Phase 1: Architecture & UI System', 'Glassmorphism dark theme interface layout.', '2026-09-11 11:30:50', 1, '2026-09-18 11:30:50'),
('ms-002', 'proj-001', 'Phase 2: Core Data Models & API Hooks', 'MySQL parameterized query endpoints.', '2026-10-02 11:30:50', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_team_members`
--

CREATE TABLE `project_team_members` (
  `id` varchar(36) NOT NULL,
  `project_id` varchar(36) NOT NULL,
  `staff_id` varchar(36) NOT NULL,
  `role` varchar(100) NOT NULL,
  `join_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_team_members`
--

INSERT INTO `project_team_members` (`id`, `project_id`, `staff_id`, `role`, `join_date`) VALUES
('ptm-001', 'proj-001', 'staff-rec-0001', 'Technical Lead & Supervisor', '2026-09-18 11:30:50');

-- --------------------------------------------------------

--
-- Table structure for table `project_updates`
--

CREATE TABLE `project_updates` (
  `id` varchar(36) NOT NULL,
  `project_id` varchar(36) NOT NULL,
  `author` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` varchar(36) NOT NULL,
  `proposal_id` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `client_id` varchar(36) NOT NULL,
  `assigned_to` varchar(36) DEFAULT NULL,
  `status` enum('DRAFT','PENDING','APPROVED','REJECTED','EXPIRED') DEFAULT 'DRAFT',
  `amount` decimal(15,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `services` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`services`)),
  `timeline` varchar(100) NOT NULL,
  `valid_until` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `response_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `proposal_id`, `title`, `client_id`, `assigned_to`, `status`, `amount`, `category`, `description`, `services`, `timeline`, `valid_until`, `created_at`, `updated_at`, `response_date`) VALUES
('prop-001', 'PROP-9021', 'Enterprise Fintech Dashboard & Payment Hub', 'client-rec-0001', 'staff-rec-0001', 'APPROVED', 14500.00, 'Fintech SaaS', 'Comprehensive ERP integration with Paystack, multi-currency wallets, and reporting ledgers.', '[\"Architecture Design\", \"Database Setup\", \"Payment Gateway Hook\", \"Quality Assurance\"]', '8 Weeks', '2026-10-02 11:30:50', '2026-09-18 11:30:50', '2026-09-18 11:30:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `questionnaires`
--

CREATE TABLE `questionnaires` (
  `id` varchar(36) NOT NULL,
  `client_id` varchar(36) NOT NULL,
  `title` varchar(191) NOT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`questions`)),
  `responses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`responses`)),
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `course_id` varchar(36) NOT NULL,
  `exam_type` varchar(100) NOT NULL,
  `score` int(11) NOT NULL,
  `max_score` int(11) NOT NULL,
  `grade` varchar(10) NOT NULL,
  `exam_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `hire_date` datetime NOT NULL,
  `salary` decimal(15,2) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','ON_LEAVE','TERMINATED') DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `staff_id`, `employee_id`, `department`, `position`, `hire_date`, `salary`, `status`) VALUES
('staff-rec-0001', 'u-staff-0001', 'STF-1001', 'EMP-5001', 'Software Engineering', 'Lead Software Instructor', '2024-01-15 00:00:00', 7500.00, 'ACTIVE');

-- --------------------------------------------------------

--
-- Table structure for table `staff_updates`
--

CREATE TABLE `staff_updates` (
  `id` varchar(36) NOT NULL,
  `staff_id` varchar(36) NOT NULL,
  `type` enum('ASSESSMENT','QUERY','PROMOTION','TERMINATION','LEAVE','TRAINING') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `created_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `enrollment_date` datetime DEFAULT current_timestamp(),
  `preferred_time` varchar(100) DEFAULT NULL,
  `lecture_mode` enum('ONLINE','OFFLINE','HYBRID') DEFAULT 'ONLINE',
  `payment_method` varchar(50) DEFAULT NULL,
  `profile_complete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `enrollment_date`, `preferred_time`, `lecture_mode`, `payment_method`, `profile_complete`) VALUES
('7af43d8d-098f-4833-a618-3452d1604249', '2444401c-ef5e-4fa5-bc84-90ec4441714d', 'STUD-001', '2026-08-11 22:46:45', NULL, 'ONLINE', NULL, 0),
('88c6101d-7d73-4b8e-927f-b2afc9289564', '208016b0-2eec-4a17-a4d0-1dc1a03e14a7', 'STUD-52515', '2026-08-14 01:38:33', NULL, 'OFFLINE', NULL, 0),
('stud-rec-0001', 'u-student-0001', 'STUD-88210', '2026-09-18 11:30:50', NULL, 'ONLINE', 'PAYSTACK', 1);

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` varchar(36) NOT NULL,
  `ticket_id` varchar(50) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` text NOT NULL,
  `status` enum('OPEN','IN_PROGRESS','RESOLVED','CLOSED','ESCALATED') DEFAULT 'OPEN',
  `priority` enum('LOW','MEDIUM','HIGH','URGENT') DEFAULT 'MEDIUM',
  `category` varchar(100) DEFAULT NULL,
  `student_id` varchar(36) DEFAULT NULL,
  `client_id` varchar(36) DEFAULT NULL,
  `assigned_to` varchar(36) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_responses`
--

CREATE TABLE `ticket_responses` (
  `id` varchar(36) NOT NULL,
  `ticket_id` varchar(36) NOT NULL,
  `author` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_staff` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('STUDENT','CLIENT','STAFF','ADMIN','MANAGER') DEFAULT 'STUDENT',
  `referral_code` varchar(50) DEFAULT NULL,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-admin-0001', 'admin@royalsolutions.com', 'admin', 'Alexander', 'Vance', '+1 (555) 019-2831', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'ADMIN', NULL, '2026-10-01 10:17:34', 1, 'dev123', '2026-09-18 11:30:50', '2026-10-01 10:17:34'),
('u-client-0001', 'client@royalsolutions.com', 'client_acme', 'David', 'Miller', '+1 (555) 016-5590', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'CLIENT', NULL, '2026-10-01 10:17:36', 1, 'dev123', '2026-09-18 11:30:50', '2026-10-01 10:17:36'),
('u-manager-0001', 'manager@royalsolutions.com', 'manager', 'Eleanor', 'Sterling', '+1 (555) 018-9921', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'MANAGER', NULL, '2026-09-18 12:00:52', 1, 'dev123', '2026-09-18 11:30:50', '2026-09-18 12:00:52'),
('u-staff-0001', 'staff@royalsolutions.com', 'staff_instructor', 'Marcus', 'Chen', '+1 (555) 014-4482', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'STAFF', NULL, '2026-10-01 10:17:34', 1, 'dev123', '2026-09-18 11:30:50', '2026-10-01 10:17:34'),
('u-student-0001', 'student@royalsolutions.com', 'student_jane', 'Jane', 'Smith', '+1 (555) 012-7711', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'STUDENT', NULL, '2026-10-04 04:38:31', 1, 'dev123', '2026-09-18 11:30:50', '2026-10-04 04:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `users_admin`
--

CREATE TABLE `users_admin` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('ADMIN','MANAGER') DEFAULT 'ADMIN',
  `referral_code` varchar(50) DEFAULT NULL,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_admin`
--

INSERT INTO `users_admin` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-admin-0001', 'admin@royalsolutions.com', 'admin', 'Alexander', 'Vance', '+1 (555) 019-2831', 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'ADMIN', NULL, '2026-09-18 12:00:52', 1, 'dev123', '2026-09-18 11:30:50', '2026-09-18 12:00:52'),
('u-manager-0001', 'manager@royalsolutions.com', 'manager', 'Eleanor', 'Sterling', '+1 (555) 018-9921', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'MANAGER', NULL, '2026-09-18 12:00:52', 1, 'dev123', '2026-09-18 11:30:50', '2026-09-18 12:00:52');

-- --------------------------------------------------------

--
-- Table structure for table `users_client`
--

CREATE TABLE `users_client` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('CLIENT') DEFAULT 'CLIENT',
  `referral_code` varchar(50) DEFAULT NULL,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_client`
--

INSERT INTO `users_client` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('ada31420-9318-4814-8409-6db7cdd00ad9', 'rita.tester@example.com', NULL, 'Rita', 'Tester', '+2348011112222', NULL, 'ACTIVE', 'CLIENT', NULL, '2026-10-01 07:12:57', 1, 'client123', '2026-10-01 07:12:57', '2026-10-01 07:12:57'),
('u-client-0001', 'client@royalsolutions.com', 'client_acme', 'David', 'Miller', '+1 (555) 016-5590', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'CLIENT', NULL, '2026-09-18 12:00:52', 1, 'dev123', '2026-09-18 11:30:50', '2026-09-18 12:00:52');

-- --------------------------------------------------------

--
-- Table structure for table `users_staff`
--

CREATE TABLE `users_staff` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('STAFF') DEFAULT 'STAFF',
  `referral_code` varchar(50) DEFAULT NULL,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_staff`
--

INSERT INTO `users_staff` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-staff-0001', 'staff@royalsolutions.com', 'staff_instructor', 'Marcus', 'Chen', '+1 (555) 014-4482', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'STAFF', NULL, '2026-09-18 12:00:52', 1, 'dev123', '2026-09-18 11:30:50', '2026-09-18 12:00:52');

-- --------------------------------------------------------

--
-- Table structure for table `users_student`
--

CREATE TABLE `users_student` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('STUDENT') DEFAULT 'STUDENT',
  `referral_code` varchar(50) DEFAULT NULL,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_student`
--

INSERT INTO `users_student` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-student-0001', 'student@royalsolutions.com', 'student_jane', 'Jane', 'Smith', '+1 (555) 012-7711', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=200&auto=format&fit=crop', 'ACTIVE', 'STUDENT', NULL, '2026-10-04 04:31:48', 1, 'dev123', '2026-09-18 11:30:50', '2026-10-04 04:31:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `theme` varchar(50) DEFAULT 'system',
  `language` varchar(50) DEFAULT 'english',
  `timezone` varchar(100) DEFAULT 'Africa/Lagos',
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `push_notifications` tinyint(1) DEFAULT 1,
  `assignment_reminders` tinyint(1) DEFAULT 1,
  `grade_alerts` tinyint(1) DEFAULT 1,
  `class_reminders` tinyint(1) DEFAULT 1,
  `announcements` tinyint(1) DEFAULT 1,
  `marketing_emails` tinyint(1) DEFAULT 0,
  `privacy_visibility` varchar(50) DEFAULT 'public',
  `show_email` tinyint(1) DEFAULT 0,
  `show_phone` tinyint(1) DEFAULT 0,
  `data_sharing` tinyint(1) DEFAULT 1,
  `analytics` tinyint(1) DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `login_alerts` tinyint(1) DEFAULT 1,
  `session_timeout` varchar(50) DEFAULT '30',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `theme`, `language`, `timezone`, `email_notifications`, `sms_notifications`, `push_notifications`, `assignment_reminders`, `grade_alerts`, `class_reminders`, `announcements`, `marketing_emails`, `privacy_visibility`, `show_email`, `show_phone`, `data_sharing`, `analytics`, `two_factor_enabled`, `login_alerts`, `session_timeout`, `created_at`, `updated_at`) VALUES
('f025dae6-e871-4028-bd9c-1b49e65f1f06', 'ada31420-9318-4814-8409-6db7cdd00ad9', 'system', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-01 07:12:57', '2026-10-01 07:12:57'),
('set-admin-0001', 'u-admin-0001', 'system', 'english', 'Africa/Lagos', 0, 0, 0, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-09-18 11:30:50', '2026-10-01 09:52:58'),
('set-client-0001', 'u-client-0001', 'system', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('set-manager-0001', 'u-manager-0001', 'system', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('set-staff-0001', 'u-staff-0001', 'system', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-09-18 11:30:50', '2026-09-18 11:30:50'),
('set-student-0001', 'u-student-0001', 'system', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-09-18 11:30:50', '2026-09-18 11:30:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `assessor_id` (`assessor_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_date` (`student_id`,`date`);

--
-- Indexes for table `balances`
--
ALTER TABLE `balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_id` (`certificate_id`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `class_resources`
--
ALTER TABLE `class_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `class_staff`
--
ALTER TABLE `class_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_class_staff` (`class_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_class_student` (`class_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `complaint_id` (`complaint_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `course_categories`
--
ALTER TABLE `course_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `course_resources`
--
ALTER TABLE `course_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_course` (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `expense_records`
--
ALTER TABLE `expense_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `forum_post_likes`
--
ALTER TABLE `forum_post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_post_student_like` (`post_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `forum_reply_likes`
--
ALTER TABLE `forum_reply_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reply_student_like` (`reply_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `income_records`
--
ALTER TABLE `income_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `paystack_reference` (`paystack_reference`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module_id` (`module_id`);

--
-- Indexes for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_lesson` (`student_id`,`lesson_id`),
  ADD KEY `fk_lc_lesson` (`lesson_id`);

--
-- Indexes for table `meetings`
--
ALTER TABLE `meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_user_archived` (`user_id`,`archived`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD UNIQUE KEY `paystack_reference` (`paystack_reference`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `profiles`
--
ALTER TABLE `profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_id` (`project_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_team_members`
--
ALTER TABLE `project_team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_project_staff` (`project_id`,`staff_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `project_updates`
--
ALTER TABLE `project_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proposal_id` (`proposal_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `questionnaires`
--
ALTER TABLE `questionnaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `staff_updates`
--
ALTER TABLE `staff_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_id` (`ticket_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `ticket_responses`
--
ALTER TABLE `ticket_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `users_admin`
--
ALTER TABLE `users_admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `users_client`
--
ALTER TABLE `users_client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `users_staff`
--
ALTER TABLE `users_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `users_student`
--
ALTER TABLE `users_student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`assessor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_resources`
--
ALTER TABLE `class_resources`
  ADD CONSTRAINT `class_resources_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_resources_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_staff`
--
ALTER TABLE `class_staff`
  ADD CONSTRAINT `class_staff_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_staff_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `course_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_resources`
--
ALTER TABLE `course_resources`
  ADD CONSTRAINT `course_resources_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_versions`
--
ALTER TABLE `document_versions`
  ADD CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `forum_post_likes`
--
ALTER TABLE `forum_post_likes`
  ADD CONSTRAINT `forum_post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_post_likes_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD CONSTRAINT `forum_replies_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_replies_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_reply_likes`
--
ALTER TABLE `forum_reply_likes`
  ADD CONSTRAINT `forum_reply_likes_ibfk_1` FOREIGN KEY (`reply_id`) REFERENCES `forum_replies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_reply_likes_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `installment_payments`
--
ALTER TABLE `installment_payments`
  ADD CONSTRAINT `installment_payments_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD CONSTRAINT `fk_lc_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `meetings`
--
ALTER TABLE `meetings`
  ADD CONSTRAINT `meetings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meetings_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `performance_reviews_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_milestones`
--
ALTER TABLE `project_milestones`
  ADD CONSTRAINT `project_milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_team_members`
--
ALTER TABLE `project_team_members`
  ADD CONSTRAINT `project_team_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_team_members_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_updates`
--
ALTER TABLE `project_updates`
  ADD CONSTRAINT `project_updates_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proposals_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `questionnaires`
--
ALTER TABLE `questionnaires`
  ADD CONSTRAINT `questionnaires_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_updates`
--
ALTER TABLE `staff_updates`
  ADD CONSTRAINT `staff_updates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_tickets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_responses`
--
ALTER TABLE `ticket_responses`
  ADD CONSTRAINT `ticket_responses_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
