-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 03, 2026 at 06:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `user_id`, `user_type`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
('0a3704e3-8e14-4603-871e-a9523f1d8a3f', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'Scheduled Video Meeting', '{\"meeting_id\":\"a37d7f19-8b3d-4385-88fa-8356ce104614\",\"title\":\"Kickoff call\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:08'),
('13db30e8-ce75-4f11-945e-dc9ac9146e79', 'u-client-0001', 'STUDENT', 'Requested meeting', '{\"meeting_id\":\"1e004c48-0e42-4cd9-8520-dd4e2265bd84\",\"title\":\"Consequat Quam dignissimos qui cupiditate consequatur cillum ducimus quia nemo sunt omnis pariatur In sunt veniam ut\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 12:44:28'),
('158990c3-c9c3-4950-84cc-6fd5703724f0', 'u-student-0001', 'STUDENT', 'Updated profile details', '{\"full_name\":\"Test Student\"}', '::1', 'curl/8.18.0', '2026-10-31 08:37:47'),
('1718a680-f72c-45bc-bbd7-52689f9cb056', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Uploaded document to vault', '{\"file\":\"brief.txt\",\"project_id\":\"78841043-fe2e-49f0-97d7-b6df95d7211b\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('196862f0-95a6-4aad-b385-f7cb6a8edfb5', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Client paid invoice', '{\"payment_id\":\"ff2e93a9-db82-4cfa-9e40-fc9de6e61bcb\",\"reference\":\"PSK-2B9E4A28EEC2\",\"amount\":125000}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('1b5400fa-0a5d-476b-b409-50c4be59690e', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-30 12:36:41'),
('202ea4a9-f641-47eb-b196-eeaa44696729', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 11:58:25'),
('28a23e2a-924e-42db-9082-f54b61c6fc8f', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 10:09:45'),
('2b6034d8-3a5b-444f-8653-597154fdc1d9', 'u-student-0001', 'STUDENT', 'Removed profile photo', NULL, '::1', 'curl/8.18.0', '2026-10-31 08:37:28'),
('3ab08890-136e-4a84-bb1f-b8e0830815e8', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('3c37a197-3319-4b25-bf37-30b48282af2e', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-30 23:41:42'),
('3c6f02cb-1b9a-496f-b843-72301d4e169e', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Submitted project request', '{\"proposal\":\"PROP-FA9FE627\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('3c9acf2b-9f3e-4da8-8083-7612e6923bdb', 'be015c0a-a318-455d-aa5e-6361c0310f90', 'STUDENT', 'User Logged In', '{\"email\":\"sakan@example.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-31 06:47:51'),
('3fb15227-57c4-49ef-aee5-55decf37ab6b', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 14:11:37'),
('3fe56e1e-0c04-4078-a7b2-4b58b23b269e', 'eea33d78-9484-47f1-be2d-fc03b53e4130', 'STUDENT', 'Account Created via Signup', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:21'),
('41bb7d30-90d8-4037-a07d-ff507548e473', 'u-admin-0001', 'STUDENT', 'Project delivery action: toggle_milestone', '{\"project_id\":\"27121603-df76-4147-9f8e-0f54535bf3b9\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('43278a70-db09-49ce-95c8-19693f06dee0', 'u-admin-0001', 'STUDENT', 'Project delivery action: issue_invoice', '{\"project_id\":\"27121603-df76-4147-9f8e-0f54535bf3b9\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('49cd93f0-086d-4c38-839f-19b0f58312a6', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Requested meeting', '{\"meeting_id\":\"bf9abf40-0d90-4897-ac69-8fd1ca2d8e52\",\"title\":\"Discuss my site\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('4df5a794-18f1-4a5e-bd26-5c20f96fdca3', 'a0e79b5a-5501-442f-8f3f-215e504e87ff', 'STUDENT', 'Account Created via Signup', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:21'),
('510be0f3-8668-4a5a-9ba8-803dbc8073a3', 'a0e79b5a-5501-442f-8f3f-215e504e87ff', 'STUDENT', 'Uploaded document to vault', '{\"file\":\"brief.txt\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:35'),
('51a423ea-e739-4324-9c1f-467e34f29db3', 'be015c0a-a318-455d-aa5e-6361c0310f90', 'STUDENT', 'Referred a student', '{\"email\":\"royaleducityapp@gmail.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-31 06:49:56'),
('56529c5c-14c5-443e-a270-d0266ff546d2', 'u-student-0001', 'STUDENT', 'Updated profile photo', NULL, '::1', 'curl/8.18.0', '2026-10-31 08:37:47'),
('56ee189c-0d18-4a40-8ffd-10cbf428cfeb', 'u-admin-0001', 'STUDENT', 'Meeting confirm', '{\"meeting_id\":\"bf9abf40-0d90-4897-ac69-8fd1ca2d8e52\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('69ef3c5e-0485-4b6b-98eb-ec21ab14a154', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'Approved Sales Proposal', '{\"proposal_id\":\"901f487a-3ff4-483e-861a-459647d4e324\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('6bc7bcc6-b47a-4c87-b30c-e69bfd45c226', 'u-admin-0001', 'STUDENT', 'Paid marketer commission', '{\"commission\":\"0c163185-769c-4656-8158-f520b862bffe\"}', '127.0.0.1', 'Unknown', '2026-10-30 11:46:45'),
('70fa472f-c80e-444b-89c2-ef35976e3067', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 11:30:40'),
('75c05813-99ca-44dc-9a97-fae1752d4941', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('7711f37c-abee-4bc2-8c16-d451a3f22a04', 'u-admin-0001', 'STUDENT', 'Initiated project from proposal', '{\"project_title\":\"Vault test\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('7723077d-0eba-4e85-91cd-f730ab89c998', 'u-student-0001', 'STUDENT', 'Updated profile photo', NULL, '::1', 'curl/8.18.0', '2026-10-31 08:37:47'),
('8d8b1723-3396-4f9e-9a2d-5e88c01dcf35', 'zz-test-cuser', 'STUDENT', 'Submitted project request', '{\"proposal\":\"PROP-51A1D1AE\"}', '127.0.0.1', 'Unknown', '2026-10-30 11:46:46'),
('90535273-4539-4136-a9eb-354fc3834776', '16496ca6-6335-4f91-b6c8-37284f96d728', 'STUDENT', 'Submitted project request', '{\"proposal\":\"PROP-6A559DB8\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 11:58:25'),
('92a1d9db-3aa8-469d-8c25-9ac36c71fcd3', 'u-admin-0001', 'STUDENT', 'Project delivery action: post_update', '{\"project_id\":\"27121603-df76-4147-9f8e-0f54535bf3b9\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('92c8b5c4-bdb6-4068-a21c-1147af776b02', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'Submitted project request', '{\"proposal\":\"PROP-901F487A\",\"estimate\":4300}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:51'),
('92d73da2-4fb9-4f16-b0ab-57bfe6388d58', 'u-admin-0001', 'STUDENT', 'Sent proposal to client', '{\"proposal\":\"PROP-FA9FE627\",\"amount\":250000}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('98bfc866-57c7-4494-a60a-128bdf8ca11c', 'u-client-0001', 'STUDENT', 'Submitted project request', '{\"proposal\":\"PROP-69F738BB\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 12:21:47'),
('9ed5f4fc-9280-4ad6-a8f7-6000b9a85a7a', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Rolled back document version', '{\"document\":\"brief.txt\",\"version\":1}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:07'),
('9f2ef697-94c0-4004-b4ff-ae6a29903e66', 'u-admin-0001', 'STUDENT', 'Meeting reschedule', '{\"meeting_id\":\"bf9abf40-0d90-4897-ac69-8fd1ca2d8e52\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('a883b1f7-dc0d-472d-b36c-35675eba160f', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-30 09:54:29'),
('aa3f4e37-0b67-4315-9148-81187b4ad368', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-30 09:05:12'),
('ac052db0-1ec3-4bc8-84a8-61c4f66db479', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:51'),
('b46e25e8-57cb-4ade-a68c-7b2921602653', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-28 16:40:34'),
('b56ab46f-6eda-45e3-8938-3f124617dffe', 'u-student-0001', 'STUDENT', 'Removed profile photo', NULL, '::1', 'curl/8.18.0', '2026-10-31 08:37:47'),
('b8590215-5c67-4abc-a7fa-6c24df89db1a', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Uploaded document to vault', '{\"file\":\"brief.txt\",\"project_id\":\"78841043-fe2e-49f0-97d7-b6df95d7211b\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('bd8f69a4-4c52-45c9-ac77-4533b98e3a93', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'curl/8.18.0', '2026-10-26 18:42:59'),
('bdf97e0e-4248-4a05-a7bd-0e5eec7525e1', 'a0e79b5a-5501-442f-8f3f-215e504e87ff', 'STUDENT', 'User Logged In', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:34'),
('c0d856d2-faf7-40bf-a90e-f2045e523cab', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:05'),
('c4647298-8ece-493d-9860-8e55575af243', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Approved Sales Proposal', '{\"proposal_id\":\"fa9fe627-ec51-4720-a77f-3f781ec33224\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:06'),
('c9bd8020-8a80-41f4-8b4d-6d64b74cd667', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'Client paid invoice', '{\"payment_id\":\"1ece258e-b66f-476e-931f-40fc8debec93\",\"reference\":\"PSK-F5BAAB9B2D5F\",\"amount\":2100}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('cbe40a62-e573-4f1a-ad7a-657eccabe2c9', 'a0e79b5a-5501-442f-8f3f-215e504e87ff', 'STUDENT', 'Uploaded document to vault', '{\"file\":\"brief.txt\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:35'),
('cdaf374a-f6c0-4c98-b0e8-870f4bac7e61', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 15:13:56'),
('cf252285-41b3-47be-9d5b-309716724f0d', '16496ca6-6335-4f91-b6c8-37284f96d728', 'STUDENT', 'Account Created via Signup', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 11:58:24'),
('d3f389c5-8ec5-4c62-924b-e95d26dd294f', 'be8a6707-5b21-4208-aca4-4f7031a1d22d', 'STUDENT', 'Account Created via Signup', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 12:36:05'),
('dc611631-acf1-4f17-8fc0-a096d7a5f974', 'u-admin-0001', 'STUDENT', 'Created marketer account', '{\"email\":\"sakan@example.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-30 23:47:28'),
('ddd74085-804e-4247-bb2b-ae6334950616', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'User Logged In', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:33:08'),
('df347908-8bfa-415a-916c-7a8cbbd9e66b', 'u-admin-0001', 'STUDENT', 'Initiated project from proposal', '{\"project_title\":\"Inventory web app\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:52'),
('e362b55e-daf3-4375-a44e-2276ec0191ee', 'u-admin-0001', 'STUDENT', 'Sent proposal to client', '{\"proposal\":\"PROP-6A559DB8\",\"amount\":1800}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 11:58:25'),
('e3988d61-b205-41c8-b1fd-bd3d7fa9c3ed', 'u-client-0001', 'STUDENT', 'User Logged In', '{\"email\":\"client@royalsolutions.com\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-28 23:01:07'),
('eb278280-d30d-4567-9926-a4654f37885f', 'u-client-0001', 'STUDENT', 'Uploaded document to vault', '{\"file\":\"04.png\",\"project_id\":\"proj-001\"}', '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36', '2026-10-29 12:55:32'),
('f04d368b-6716-4111-9886-4aaab7210881', '4678d40a-384b-474a-aabf-3430c7794d62', 'STUDENT', 'Account Created via Signup', '{\"email\":\"e2e.tester@example.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:51'),
('f9a48309-b76e-4e5b-9ac4-eaf0ef504458', 'u-admin-0001', 'STUDENT', 'Sent proposal to client', '{\"proposal\":\"PROP-901F487A\",\"amount\":4200}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:51'),
('fd6e3b8b-bdb4-4ce3-aba1-a43e1e916e3c', 'u-admin-0001', 'STUDENT', 'User Logged In', '{\"email\":\"admin@royalsolutions.com\"}', '127.0.0.1', 'Python-urllib/3.14', '2026-10-29 10:32:28');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `course_id`, `student_id`, `title`, `description`, `due_date`, `status`, `submission`, `grade`, `feedback`, `submitted_at`, `graded_at`, `created_at`, `updated_at`) VALUES
('as-001', 'course-fullstack-001', 'stud-rec-0001', 'Build a Secure Parameterized Database Wrapper', 'Implement a reusable db_query helper with strict bind_param type checking and transaction controls.', '2026-10-31 18:13:05', 'PENDING', NULL, NULL, NULL, NULL, NULL, '2026-10-26 18:13:05', '2026-10-26 18:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `date` date NOT NULL,
  `status` enum('PRESENT','ABSENT','LATE','EXCUSED') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balances`
--

CREATE TABLE `balances` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `balances`
--

INSERT INTO `balances` (`id`, `user_id`, `user_type`, `amount`, `currency`, `created_at`, `updated_at`) VALUES
('bal-admin-0001', 'u-admin-0001', 'ADMIN', 0.00, 'USD', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('bal-client-0001', 'u-client-0001', 'CLIENT', 0.00, 'USD', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('bal-manager-0001', 'u-manager-0001', 'ADMIN', 0.00, 'USD', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('bal-staff-0001', 'u-staff-0001', 'STAFF', 0.00, 'USD', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('bal-student-0001', 'u-student-0001', 'STUDENT', 0.00, 'USD', '2026-10-26 18:13:05', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `course_id`, `name`, `description`, `start_date`, `end_date`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
('cls-001', 'course-fullstack-001', 'Cohort Alpha (Fall 2026)', 'Live weekend coding cohort covering fullstack architectures.', '2026-10-26 18:13:05', '2027-01-24 18:13:05', 30, 'ACTIVE', '2026-10-26 18:13:05', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_staff`
--

INSERT INTO `class_staff` (`id`, `class_id`, `staff_id`, `role`, `assigned_at`) VALUES
('cst-001', 'cls-001', 'staff-rec-0001', 'Lead Instructor', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_students`
--

INSERT INTO `class_students` (`id`, `class_id`, `student_id`, `assigned_at`, `assigned_by`, `status`) VALUES
('cs-001', 'cls-001', 'stud-rec-0001', '2026-10-26 18:13:05', NULL, 'ACTIVE');

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
  `industry` varchar(100) DEFAULT NULL,
  `referred_by` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `user_id`, `client_id`, `company_name`, `contact_person`, `website`, `industry`, `referred_by`) VALUES
('client-rec-0001', 'u-client-0001', 'CL-90210', 'Apex Global Technologies', 'David Miller', 'https://apextech.example.com', 'Fintech & Cloud Solutions', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `price`, `duration`, `level`, `mode`, `status`, `max_enrollment`, `allow_installment`, `installment_count`, `installment_amount`, `instructor_id`, `category_id`, `created_at`, `updated_at`) VALUES
('course-cloud-002', 'Enterprise Cloud & DevOps Infrastructure', 'Hands-on enterprise systems deployment, container orchestration, and server security hardening.', NULL, 1200.00, '16 Weeks', 'ADVANCED', 'ONLINE', 'ACTIVE', 40, 1, 4, 300.00, 'staff-rec-0001', 'cat-cloud-0002', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('course-fullstack-001', 'Fullstack Web Architecture with Modern PHP & MySQL', 'Master production web application design with procedural and modular PHP, MySQL prepared statements, Tailwind CSS, and RESTful APIs.', NULL, 850.00, '12 Weeks', 'INTERMEDIATE', 'ONLINE', 'ACTIVE', 50, 1, 3, 290.00, 'staff-rec-0001', 'cat-web-0001', '2026-10-26 18:13:05', '2026-10-26 18:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `course_categories`
--

CREATE TABLE `course_categories` (
  `id` varchar(36) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `file_name`, `file_path`, `file_size`, `mime_type`, `client_id`, `project_id`, `uploaded_by`, `uploaded_at`, `current_version`) VALUES
('75b6214d-31d2-49e5-bd7e-ec4b856aff7a', '04.png', '/uploads/documents/6ae334348cd09_04.png', '978.7 KB', 'image/png', 'client-rec-0001', 'proj-001', 'David Miller', '2026-10-29 12:55:32', 1);

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
  `change_summary` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `amount_due` decimal(15,2) DEFAULT 0.00,
  `batch_id` varchar(36) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `admission_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `progress`, `completion_date`, `payment_type`, `total_paid`, `amount_due`, `batch_id`, `start_date`, `end_date`, `admission_sent_at`) VALUES
('enr-0001', 'stud-rec-0001', 'course-fullstack-001', '2026-10-26 18:13:05', 'ACTIVE', 33, NULL, 'INSTALLMENT', 290.00, 580.00, 'cls-001', '2026-10-26', '2027-01-24', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `student_id`, `course_id`, `title`, `content`, `category`, `tags`, `is_pinned`, `is_resolved`, `created_at`, `updated_at`) VALUES
('post-001', 'stud-rec-0001', 'course-fullstack-001', 'Best practice for securing mysqli prepared statements against type casting?', 'When binding decimal currency values in PHP mysqli, should we declare them as \"d\" or format them as strings \"s\" with precise decimal scale?', 'Coding', '[\"PHP\", \"MySQL\", \"Security\"]', 1, 0, '2026-10-26 18:13:05', '2026-10-26 18:13:05');

-- --------------------------------------------------------

--
-- Table structure for table `forum_post_likes`
--

CREATE TABLE `forum_post_likes` (
  `id` varchar(36) NOT NULL,
  `post_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_replies`
--

CREATE TABLE `forum_replies` (
  `id` varchar(36) NOT NULL,
  `post_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `content` text NOT NULL,
  `is_solution` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_reply_likes`
--

CREATE TABLE `forum_reply_likes` (
  `id` varchar(36) NOT NULL,
  `reply_id` varchar(36) NOT NULL,
  `student_id` varchar(36) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `installment_payments`
--

INSERT INTO `installment_payments` (`id`, `enrollment_id`, `installment_number`, `amount`, `due_date`, `paid_date`, `status`, `paystack_reference`, `paystack_access_code`, `transaction_id`, `reminder_sent`, `reminder_sent_at`, `created_at`, `updated_at`) VALUES
('inst-001', 'enr-0001', 1, 290.00, '2026-10-16 18:13:05', '2026-10-26 18:13:05', 'PAID', 'PAYSTACK_REF_INIT_001', NULL, NULL, 0, NULL, '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('inst-002', 'enr-0001', 2, 290.00, '2026-10-28 18:13:05', NULL, 'PENDING', NULL, NULL, NULL, 0, NULL, '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('inst-003', 'enr-0001', 3, 290.00, '2026-11-27 18:13:05', NULL, 'PENDING', NULL, NULL, NULL, 0, NULL, '2026-10-26 18:13:05', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `student_id` varchar(36) NOT NULL,
  `lesson_id` varchar(36) NOT NULL,
  `completed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketer_commissions`
--

CREATE TABLE `marketer_commissions` (
  `id` varchar(36) NOT NULL,
  `marketer_id` varchar(36) NOT NULL,
  `source` enum('PROJECT','STUDENT') NOT NULL,
  `project_id` varchar(36) DEFAULT NULL,
  `referral_id` varchar(36) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `base_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rate` decimal(5,2) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('EARNED','PAID','CANCELLED') DEFAULT 'EARNED',
  `payout_ref` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketer_student_referrals`
--

CREATE TABLE `marketer_student_referrals` (
  `id` varchar(36) NOT NULL,
  `marketer_id` varchar(36) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `course_id` varchar(36) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `status` enum('PENDING','CONFIRMED','REJECTED') DEFAULT 'PENDING',
  `review_note` varchar(255) DEFAULT NULL,
  `reviewed_by` varchar(36) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `marketer_student_referrals`
--

INSERT INTO `marketer_student_referrals` (`id`, `marketer_id`, `full_name`, `email`, `phone`, `course_id`, `note`, `status`, `review_note`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
('2ffaf3ae-6a06-4cca-bccb-9be32bac6566', 'be015c0a-a318-455d-aa5e-6361c0310f90', 'Victor Ikechukwu', 'royaleducityapp@gmail.com', NULL, NULL, NULL, 'PENDING', NULL, NULL, NULL, '2026-10-31 06:49:56');

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
  `status` enum('REQUESTED','SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED','RESCHEDULED') DEFAULT 'SCHEDULED',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meetings`
--

INSERT INTO `meetings` (`id`, `title`, `description`, `client_id`, `staff_id`, `meeting_date`, `duration`, `location`, `meeting_url`, `status`, `created_at`, `updated_at`) VALUES
('1e004c48-0e42-4cd9-8520-dd4e2265bd84', 'Consequat Quam dignissimos qui cupiditate consequatur cillum ducimus quia nemo sunt omnis pariatur In sunt veniam ut', 'Quas perspiciatis d', 'client-rec-0001', NULL, '2027-01-13 10:40:00', 30, NULL, NULL, 'REQUESTED', '2026-10-29 12:44:28', '2026-10-29 12:44:28');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'GENERAL',
  `priority` enum('LOW','MEDIUM','HIGH') DEFAULT 'MEDIUM',
  `is_read` tinyint(1) DEFAULT 0,
  `archived` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `user_type`, `title`, `message`, `type`, `priority`, `is_read`, `archived`, `link`, `metadata`, `created_at`) VALUES
('037b397b-86b8-47d3-aa63-d3607effa487', 'u-manager-0001', 'ADMIN', 'Student referral to confirm', 'Suki Sweeney referred Victor Ikechukwu (royaleducityapp@gmail.com). Confirm once they have registered.', 'CRM', 'HIGH', 0, 0, '/admin/marketers.php?tab=referrals', NULL, '2026-10-31 06:49:56'),
('426dbec2-18fe-4ad9-a38a-d74aedd9b61d', 'u-admin-0001', 'ADMIN', 'Client uploaded a document', 'Apex Global Technologies uploaded \'04.png\' to project Enterprise Fintech Dashboard & Payment Hub.', 'CRM', 'HIGH', 0, 0, '/admin/project_control.php?project_id=proj-001', NULL, '2026-10-29 12:55:32'),
('58d4efce-3f60-40db-a8c8-034838b2d661', 'u-manager-0001', 'ADMIN', 'New project request', 'Apex Global Technologies requested \'Consequat Odio doloribus sed est qui labore voluptate magna excepteur\' (Website revamp / redesign). Review the brief and set a price.', 'CRM', 'HIGH', 0, 0, '/admin/proposals.php', NULL, '2026-10-29 12:21:47'),
('75468e3b-70d8-463e-a9c7-2710846aabb8', 'u-admin-0001', 'ADMIN', 'Student referral to confirm', 'Suki Sweeney referred Victor Ikechukwu (royaleducityapp@gmail.com). Confirm once they have registered.', 'CRM', 'HIGH', 0, 0, '/admin/marketers.php?tab=referrals', NULL, '2026-10-31 06:49:56'),
('754e9eab-9da6-4489-acd1-bd220c57b3ab', 'u-manager-0001', 'ADMIN', 'Client uploaded a document', 'Apex Global Technologies uploaded \'04.png\' to project Enterprise Fintech Dashboard & Payment Hub.', 'CRM', 'HIGH', 0, 0, '/admin/project_control.php?project_id=proj-001', NULL, '2026-10-29 12:55:32'),
('c4e76ded-1de5-4d12-9f4e-5d8ec55ccb2b', 'u-admin-0001', 'ADMIN', 'New project request', 'Apex Global Technologies requested \'Consequat Odio doloribus sed est qui labore voluptate magna excepteur\' (Website revamp / redesign). Review the brief and set a price.', 'CRM', 'HIGH', 0, 0, '/admin/proposals.php', NULL, '2026-10-29 12:21:47'),
('d3fe2e11-0208-4bf8-a3e0-c48eb6afc74e', 'u-manager-0001', 'ADMIN', 'Meeting requested', 'Apex Global Technologies requested \'Consequat Quam dignissimos qui cupiditate consequatur cillum ducimus quia nemo sunt omnis pariatur In sunt veniam ut\' for Jan 13, 10:40. Confirm or reschedule it.', 'CRM', 'HIGH', 0, 0, '/admin/meetings.php', NULL, '2026-10-29 12:44:28'),
('d805507d-0653-47b3-9a0b-1a35c860c0e4', 'u-admin-0001', 'ADMIN', 'Meeting requested', 'Apex Global Technologies requested \'Consequat Quam dignissimos qui cupiditate consequatur cillum ducimus quia nemo sunt omnis pariatur In sunt veniam ut\' for Jan 13, 10:40. Confirm or reschedule it.', 'CRM', 'HIGH', 0, 0, '/admin/meetings.php', NULL, '2026-10-29 12:44:28');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

CREATE TABLE `profiles` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `user_id`, `user_type`, `full_name`, `bio`, `created_at`, `updated_at`) VALUES
('b9a6c4d5-d5f0-497b-9a1e-446ce705e615', 'be015c0a-a318-455d-aa5e-6361c0310f90', 'MARKETER', 'Suki Sweeney', NULL, '2026-10-30 23:47:28', '2026-10-30 23:47:28'),
('prof-admin-0001', 'u-admin-0001', 'ADMIN', 'Alexander Vance', 'Registered ADMIN at Royal Solutions ERP command platform.', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('prof-client-0001', 'u-client-0001', 'CLIENT', 'David Miller', 'Registered CLIENT at Royal Solutions ERP command platform.', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('prof-manager-0001', 'u-manager-0001', 'ADMIN', 'Eleanor Sterling', 'Registered ADMIN at Royal Solutions ERP command platform.', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('prof-staff-0001', 'u-staff-0001', 'STAFF', 'Marcus Chen', 'Registered STAFF at Royal Solutions ERP command platform.', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('prof-student-0001', 'u-student-0001', 'STUDENT', 'Jane Smith', 'Registered STUDENT at Royal Solutions ERP command platform.', '2026-10-26 18:13:05', '2026-10-31 08:38:02');

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `proposal_id` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_id`, `title`, `description`, `client_id`, `supervisor_id`, `status`, `budget`, `start_date`, `end_date`, `progress`, `category`, `created_at`, `updated_at`, `proposal_id`) VALUES
('proj-001', 'PRJ-5501', 'Enterprise Fintech Dashboard & Payment Hub', 'Production development of client cloud platform.', 'client-rec-0001', 'staff-rec-0001', 'IN_PROGRESS', 14500.00, '2026-10-26 18:13:05', '2026-12-25 18:13:05', 45, 'Fintech SaaS', '2026-10-26 18:13:05', '2026-10-26 18:13:05', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_milestones`
--

INSERT INTO `project_milestones` (`id`, `project_id`, `title`, `description`, `due_date`, `completed`, `completed_at`) VALUES
('ms-001', 'proj-001', 'Phase 1: Architecture & UI System', 'Glassmorphism light interface layout.', '2026-10-19 18:13:05', 1, '2026-10-26 18:13:05'),
('ms-002', 'proj-001', 'Phase 2: Core Data Models & API Hooks', 'MySQL parameterized query endpoints.', '2026-11-09 18:13:05', 0, NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_team_members`
--

INSERT INTO `project_team_members` (`id`, `project_id`, `staff_id`, `role`, `join_date`) VALUES
('ptm-001', 'proj-001', 'staff-rec-0001', 'Technical Lead & Supervisor', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `response_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `proposal_id`, `title`, `client_id`, `assigned_to`, `status`, `amount`, `category`, `description`, `services`, `timeline`, `valid_until`, `response_date`, `created_at`, `updated_at`) VALUES
('69f738bb-d52a-4aa6-9f84-4424018f50a2', 'PROP-69F738BB', 'Consequat Odio doloribus sed est qui labore voluptate magna excepteur', 'client-rec-0001', NULL, 'DRAFT', 0.00, 'Website Design / Revamp', 'Rem numquam deleniti', '[\"Website revamp \\/ redesign\",\"Esse Nam error nihi\",\"Something else\",\"Online payments\",\"Admin dashboard\",\"Reports & analytics\"]', 'ASAP', '2026-11-12 12:21:47', NULL, '2026-10-29 12:21:47', '2026-10-30 11:37:48'),
('prop-001', 'PROP-9021', 'Enterprise Fintech Dashboard & Payment Hub', 'client-rec-0001', 'staff-rec-0001', 'APPROVED', 14500.00, 'Fintech SaaS', 'Comprehensive ERP integration with Paystack, multi-currency wallets, and reporting ledgers.', '[\"Architecture Design\", \"Database Setup\", \"Payment Gateway Hook\", \"Quality Assurance\"]', '8 Weeks', '2026-11-09 18:13:05', NULL, '2026-10-26 18:13:05', '2026-10-26 18:13:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questionnaires`
--

INSERT INTO `questionnaires` (`id`, `client_id`, `title`, `questions`, `responses`, `completed_at`, `created_at`, `updated_at`) VALUES
('383f480c-959a-494a-9ca1-e8e7c32d0973', 'client-rec-0001', 'Consequat Odio doloribus sed est qui labore voluptate magna excepteur', '{\"title\":\"Project request\",\"version\":\"3.0\"}', '{\"inputs\":{\"project_title\":\"Consequat Odio doloribus sed est qui labore voluptate magna excepteur\",\"service\":\"website_redesign\",\"description\":\"Rem numquam deleniti\",\"features\":\"Esse Nam error nihi\\r\\nSomething else\\r\\nOnline payments\\r\\nAdmin dashboard\\r\\nReports & analytics\",\"current_site\":\"https:\\/\\/example.com\",\"references\":\"Aliquip aut atque do\",\"timeline\":\"asap\",\"budget_note\":\"Around 150,000\"},\"service_label\":\"Website revamp \\/ redesign\"}', '2026-10-29 12:21:47', '2026-10-29 12:21:47', '2026-10-29 12:21:47');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `user_id`, `student_id`, `enrollment_date`, `preferred_time`, `lecture_mode`, `payment_method`, `profile_complete`) VALUES
('stud-rec-0001', 'u-student-0001', 'STUD-88210', '2026-10-26 18:13:05', NULL, 'ONLINE', 'PAYSTACK', 1);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users_admin`
--

INSERT INTO `users_admin` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-admin-0001', 'admin@royalsolutions.com', 'admin', 'Alexander', 'Vance', '+1 (555) 019-2831', NULL, 'ACTIVE', 'ADMIN', NULL, '2026-10-31 08:36:49', 1, 'dev123', '2026-10-26 18:13:05', '2026-10-31 08:36:49'),
('u-manager-0001', 'manager@royalsolutions.com', 'manager', 'Eleanor', 'Sterling', '+1 (555) 018-9921', NULL, 'ACTIVE', 'ADMIN', NULL, '2026-10-31 08:36:49', 1, 'dev123', '2026-10-26 18:13:05', '2026-10-31 08:36:49');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users_client`
--

INSERT INTO `users_client` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-client-0001', 'client@royalsolutions.com', 'client_acme', 'David', 'Miller', '+1 (555) 016-5590', NULL, 'ACTIVE', 'CLIENT', NULL, '2026-10-31 08:36:49', 1, 'dev123', '2026-10-26 18:13:05', '2026-10-31 08:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `users_marketer`
--

CREATE TABLE `users_marketer` (
  `id` varchar(36) NOT NULL,
  `email` varchar(191) NOT NULL,
  `username` varchar(191) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED','PENDING_VERIFICATION') DEFAULT 'ACTIVE',
  `role` enum('MARKETER') DEFAULT 'MARKETER',
  `referral_code` varchar(50) DEFAULT NULL,
  `project_commission_rate` decimal(5,2) DEFAULT 10.00,
  `student_commission_rate` decimal(5,2) DEFAULT 10.00,
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users_marketer`
--

INSERT INTO `users_marketer` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `project_commission_rate`, `student_commission_rate`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('be015c0a-a318-455d-aa5e-6361c0310f90', 'sakan@example.com', NULL, 'Suki', 'Sweeney', '+1 (497) 931-9817', NULL, 'ACTIVE', 'MARKETER', 'RS89821B', 7.00, 5.00, '2026-10-31 07:12:56', 0, 'dev123', '2026-10-30 23:47:28', '2026-10-31 07:12:56');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users_staff`
--

INSERT INTO `users_staff` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-staff-0001', 'staff@royalsolutions.com', 'staff_instructor', 'Marcus', 'Chen', '+1 (555) 014-4482', NULL, 'ACTIVE', 'STAFF', NULL, '2026-10-31 08:36:49', 1, 'dev123', '2026-10-26 18:13:05', '2026-10-31 08:36:49');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users_student`
--

INSERT INTO `users_student` (`id`, `email`, `username`, `first_name`, `last_name`, `phone`, `avatar`, `status`, `role`, `referral_code`, `last_active`, `email_verified`, `password_hash`, `created_at`, `updated_at`) VALUES
('u-student-0001', 'student@royalsolutions.com', 'student_jane', 'Jane', 'Smith', '+1 (555) 012-7711', NULL, 'ACTIVE', 'STUDENT', NULL, '2026-10-31 08:43:40', 1, 'dev123', '2026-10-26 18:13:05', '2026-10-31 08:43:40');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `user_type` enum('STUDENT','CLIENT','STAFF','ADMIN','MARKETER') NOT NULL,
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
  `profile_visibility` varchar(50) DEFAULT 'public',
  `show_email` tinyint(1) DEFAULT 0,
  `show_phone` tinyint(1) DEFAULT 0,
  `data_sharing` tinyint(1) DEFAULT 1,
  `analytics` tinyint(1) DEFAULT 1,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `login_alerts` tinyint(1) DEFAULT 1,
  `session_timeout` varchar(50) DEFAULT '30',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `user_type`, `language`, `timezone`, `email_notifications`, `sms_notifications`, `push_notifications`, `assignment_reminders`, `grade_alerts`, `class_reminders`, `announcements`, `marketing_emails`, `profile_visibility`, `show_email`, `show_phone`, `data_sharing`, `analytics`, `two_factor_enabled`, `login_alerts`, `session_timeout`, `created_at`, `updated_at`) VALUES
('6c562826-379f-465e-ade0-75edd8703cdc', 'be015c0a-a318-455d-aa5e-6361c0310f90', 'MARKETER', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-30 23:47:28', '2026-10-30 23:47:28'),
('set-admin-0001', 'u-admin-0001', 'ADMIN', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('set-client-0001', 'u-client-0001', 'CLIENT', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('set-manager-0001', 'u-manager-0001', 'ADMIN', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('set-staff-0001', 'u-staff-0001', 'STAFF', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-26 18:13:05', '2026-10-26 18:13:05'),
('set-student-0001', 'u-student-0001', 'STUDENT', 'english', 'Africa/Lagos', 1, 0, 1, 1, 1, 1, 1, 0, 'public', 0, 0, 1, 1, 0, 1, '30', '2026-10-26 18:13:05', '2026-10-26 18:13:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`user_type`);

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
  ADD KEY `idx_user_type` (`user_id`,`user_type`);

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
  ADD UNIQUE KEY `client_id` (`client_id`),
  ADD KEY `idx_clients_referred_by` (`referred_by`);

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
  ADD KEY `course_id` (`course_id`),
  ADD KEY `fk_enrollments_batch` (`batch_id`);

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
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `marketer_commissions`
--
ALTER TABLE `marketer_commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_id` (`project_id`),
  ADD UNIQUE KEY `referral_id` (`referral_id`),
  ADD KEY `marketer_id` (`marketer_id`);

--
-- Indexes for table `marketer_student_referrals`
--
ALTER TABLE `marketer_student_referrals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_referral_email` (`email`),
  ADD KEY `marketer_id` (`marketer_id`),
  ADD KEY `course_id` (`course_id`);

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
  ADD KEY `idx_user_type` (`user_id`,`user_type`),
  ADD KEY `idx_user_read` (`user_id`,`user_type`,`is_read`),
  ADD KEY `idx_user_created` (`user_id`,`user_type`,`created_at`),
  ADD KEY `idx_user_archived` (`user_id`,`user_type`,`archived`);

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
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_id`,`user_type`);

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
  ADD KEY `idx_user_type` (`user_id`,`user_type`);

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
-- Indexes for table `users_marketer`
--
ALTER TABLE `users_marketer`
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
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_id`,`user_type`);

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
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_client` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_enrollments_batch` FOREIGN KEY (`batch_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `lesson_completions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_completions_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `marketer_commissions`
--
ALTER TABLE `marketer_commissions`
  ADD CONSTRAINT `marketer_commissions_ibfk_1` FOREIGN KEY (`marketer_id`) REFERENCES `users_marketer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketer_commissions_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `marketer_commissions_ibfk_3` FOREIGN KEY (`referral_id`) REFERENCES `marketer_student_referrals` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `marketer_student_referrals`
--
ALTER TABLE `marketer_student_referrals`
  ADD CONSTRAINT `marketer_student_referrals_ibfk_1` FOREIGN KEY (`marketer_id`) REFERENCES `users_marketer` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marketer_student_referrals_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_updates`
--
ALTER TABLE `staff_updates`
  ADD CONSTRAINT `staff_updates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users_student` (`id`) ON DELETE CASCADE;

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
