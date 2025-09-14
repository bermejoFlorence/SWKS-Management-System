-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2025 at 04:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `swks_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `aca_coordinator_details`
--

CREATE TABLE `aca_coordinator_details` (
  `coor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coor_name` varchar(200) NOT NULL,
  `coor_email` varchar(200) NOT NULL,
  `profile_pic` varchar(200) NOT NULL,
  `created_at` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aca_coordinator_details`
--

INSERT INTO `aca_coordinator_details` (`coor_id`, `user_id`, `coor_name`, `coor_email`, `profile_pic`, `created_at`) VALUES
(1, 4, 'Arjomel Aguilar', 'arjomel.aguilar@cbsua.edu.ph', 'uploads/profile_689994346e50d6.56876569.jpg', '2025-08-11 14:56:52');

-- --------------------------------------------------------

--
-- Table structure for table `adviser_details`
--

CREATE TABLE `adviser_details` (
  `adviser_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `adviser_fname` varchar(200) NOT NULL,
  `adviser_email` varchar(200) NOT NULL,
  `profile_pic` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adviser_details`
--

INSERT INTO `adviser_details` (`adviser_id`, `user_id`, `adviser_fname`, `adviser_email`, `profile_pic`) VALUES
(18, 21, 'Florencio Bermejo', 'florencio.bermejo@cbsua.edu.ph', 'uploads/profile_6891afc994f642.69770119.png');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_requests`
--

CREATE TABLE `borrow_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','validated','approved','rejected','returned','cancelled') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_requests`
--

INSERT INTO `borrow_requests` (`request_id`, `user_id`, `org_id`, `purpose`, `status`, `created_at`, `validated_at`, `approved_at`) VALUES
(15, 16, 4, 'for event', 'validated', '2025-08-08 07:44:07', '2025-08-10 17:43:21', NULL),
(16, 16, 4, 'for beautiful event', 'validated', '2025-08-10 09:40:41', '2025-08-10 15:40:54', NULL),
(17, 16, 4, 'testing', 'validated', '2025-08-10 11:05:05', '2025-08-10 17:23:33', NULL),
(18, 16, 4, 'testing for', 'validated', '2025-08-10 11:46:20', '2025-08-10 17:46:28', NULL),
(19, 16, 4, 'adsas', 'approved', '2025-08-10 11:47:15', '2025-08-10 17:51:31', NULL),
(20, 16, 4, 'dsa', 'pending', '2025-08-17 04:09:08', NULL, NULL),
(21, 16, 4, 'dasd', 'validated', '2025-08-17 04:09:13', '2025-08-17 11:10:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `borrow_request_items`
--

CREATE TABLE `borrow_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_request_items`
--

INSERT INTO `borrow_request_items` (`id`, `request_id`, `item_id`, `quantity_requested`) VALUES
(15, 15, 1, 1),
(16, 16, 2, 1),
(17, 17, 1, 1),
(18, 18, 2, 1),
(19, 19, 2, 3),
(20, 20, 2, 1),
(21, 21, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `forum_comment`
--

CREATE TABLE `forum_comment` (
  `comment_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_comment`
--

INSERT INTO `forum_comment` (`comment_id`, `post_id`, `user_id`, `comment_text`, `created_at`) VALUES
(72, 86, 4, 'fdsfsdfsd', '2025-08-08 10:35:01'),
(73, 91, 21, 'maganda', '2025-08-11 15:01:15'),
(74, 90, 4, 'aaa', '2025-08-11 15:02:27'),
(75, 91, 4, 'dasda', '2025-08-11 15:39:20'),
(76, 91, 21, 'dasdasda', '2025-08-11 15:39:37'),
(77, 91, 4, 'sfdad', '2025-08-11 15:40:58'),
(78, 91, 16, 'ers', '2025-08-15 20:46:08'),
(79, 91, 4, 'dasdsa', '2025-08-16 22:59:49'),
(80, 91, 16, 'dsada', '2025-08-16 23:00:20'),
(81, 93, 4, 'fdsfsdfsd', '2025-08-16 23:01:28'),
(82, 92, 16, 'fdrsafd', '2025-08-16 23:03:09'),
(83, 94, 4, 'dsadsa', '2025-08-17 21:42:49');

-- --------------------------------------------------------

--
-- Table structure for table `forum_post`
--

CREATE TABLE `forum_post` (
  `post_id` int(11) NOT NULL,
  `org_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `attachment` varchar(200) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forum_post`
--

INSERT INTO `forum_post` (`post_id`, `org_id`, `user_id`, `title`, `content`, `attachment`, `created_at`) VALUES
(80, 11, 4, 'frasds', 'dsfsaf', '[\"uploads\\/attach_6893545d5040f9.47780401.jfif\"]', '2025-08-06 21:10:53'),
(81, 11, 4, 'fdsafsa', 'sadfasfda', '[\"uploads\\/attach_689354718899e0.76236787.jfif\"]', '2025-08-06 21:11:13'),
(82, 11, 4, 'fawko', 'falwidhf', '[\"uploads\\/attach_68949efb30d489.43759952.jpg\"]', '2025-08-07 20:41:31'),
(83, 11, 4, 'sample 1', 'samle 2', '[\"uploads\\/attach_68949fab65aff4.96433300.jpg\"]', '2025-08-07 20:44:27'),
(84, 11, 4, 'sample 2', 'samplee 2', '[\"uploads\\/attach_6894a14fe662a8.77102131.png\"]', '2025-08-07 20:51:27'),
(85, 11, 4, 'fdsardf', 'fsdaasf', '', '2025-08-07 20:59:06'),
(86, 11, 4, 'sdxasf', 'fsdafa', '[\"uploads\\/attach_68955cc2c90c89.40304354.jfif\"]', '2025-08-08 10:11:14'),
(87, 11, 4, 'dsadsa', 'dasdas', '[\"uploads\\/attach_68958a264ee960.53672912.png\"]', '2025-08-08 13:24:54'),
(88, 4, 21, 'edwewq', 'ewqew', '', '2025-08-08 13:37:22'),
(89, 11, 4, 'ewqe', 'qeweqwe', '', '2025-08-08 13:42:26'),
(90, 11, 4, 'sample 1', 'sample 1', '[\"uploads\\/attach_689994864c10b6.13921843.jpeg\"]', '2025-08-11 14:58:14'),
(91, 4, 21, 'sample', 'this is for chorales', '[\"uploads\\/attach_689994df52cca7.57317092.jpeg\"]', '2025-08-11 14:59:43'),
(92, 11, 16, 'dadasd', 'dsadsa', '', '2025-08-16 22:55:54'),
(93, 11, 4, '54', '5554', '', '2025-08-16 23:01:20'),
(94, 4, 16, 'fsdsd', 'fsdfsd', '', '2025-08-17 11:55:37');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `item_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `org_id` int(11) DEFAULT NULL,
  `image` varchar(250) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`item_id`, `name`, `description`, `quantity_available`, `status`, `org_id`, `image`, `created_at`) VALUES
(1, 'Sample 2', 'Sample 2', 0, 'active', NULL, 'uploads/inventory/item_1753965503_1049.jpg', '2025-07-31 20:13:27'),
(2, 'Sample 3', 'sample 3', 103, 'active', NULL, 'uploads/inventory/item_1754191946_9875.jpg', '2025-08-03 11:32:26');

-- --------------------------------------------------------

--
-- Table structure for table `member_details`
--

CREATE TABLE `member_details` (
  `member_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `ay` varchar(10) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `guardian` varchar(100) DEFAULT NULL,
  `guardian_address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `preferred_org` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','deactivated') NOT NULL,
  `date_submitted` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_details`
--

INSERT INTO `member_details` (`member_id`, `user_id`, `full_name`, `nickname`, `ay`, `gender`, `course`, `year_level`, `birthdate`, `age`, `address`, `contact_number`, `email`, `mother_name`, `mother_occupation`, `father_name`, `father_occupation`, `guardian`, `guardian_address`, `profile_picture`, `preferred_org`, `status`, `date_submitted`) VALUES
(16, 16, 'Joshua Lerin', 'Joshua', 'none', 'Male', 'Bachelor of Secondary Major in English', '1st Year', '2001-06-26', 24, 'Cabusao, Camarines Sur', '09328432972394', 'joshua.lerin@cbsua.edu.ph', 'Mama', 'Mamako', 'Papa', 'Papako', 'Mamapapa', 'cabusao', 'profile_6895881f30bbc3.58993771.png', '4', 'approved', '2025-08-08');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'new_post',
  `message` text DEFAULT NULL,
  `is_seen` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `org_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`notification_id`, `user_id`, `post_id`, `comment_id`, `type`, `message`, `is_seen`, `created_at`, `org_id`) VALUES
(271, 21, 80, NULL, 'forum_post', 'New forum post: frasds', 1, '2025-08-06 21:10:53', 4),
(275, 21, 81, NULL, 'forum_post', 'New forum post: fdsafsa', 1, '2025-08-06 21:11:13', 4),
(279, 21, 82, NULL, 'forum_post', 'New forum post: fawko', 1, '2025-08-07 20:41:31', 4),
(283, 21, 83, NULL, 'forum_post', 'New forum post: sample 1', 1, '2025-08-07 20:44:27', 4),
(287, 21, 84, NULL, 'forum_post', 'New forum post: sample 2', 0, '2025-08-07 20:51:27', 4),
(291, 21, 85, NULL, 'forum_post', 'New forum post: fdsardf', 1, '2025-08-07 20:59:06', 4),
(295, 21, 86, NULL, 'forum_post', 'New forum post: sdxasf', 1, '2025-08-08 10:11:14', 4),
(297, 4, 86, 72, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-08 10:35:01', 11),
(299, 21, 86, 72, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-08 10:35:01', 11),
(302, 21, NULL, NULL, 'membership_form', 'New membership application received for your organization.', 1, '2025-08-08 13:16:15', 4),
(303, 4, 87, NULL, 'forum_post', 'New forum post: dsadsa', 1, '2025-08-08 13:24:54', NULL),
(304, 16, 87, NULL, 'forum_post', 'New forum post: dsadsa', 0, '2025-08-08 13:24:54', 4),
(305, 21, 87, NULL, 'forum_post', 'New forum post: dsadsa', 1, '2025-08-08 13:24:54', 4),
(306, 21, 88, NULL, 'forum_post', 'New forum post: edwewq', 1, '2025-08-08 13:37:22', 4),
(307, 16, 88, NULL, 'forum_post', 'New forum post: edwewq', 1, '2025-08-08 13:37:22', 4),
(308, 4, 88, NULL, 'forum_post', 'New forum post: edwewq', 0, '2025-08-08 13:37:22', 4),
(309, 4, 89, NULL, 'forum_post', 'New forum post: ewqe', 1, '2025-08-08 13:42:26', NULL),
(310, 16, 89, NULL, 'forum_post', 'New forum post: ewqe', 1, '2025-08-08 13:42:26', 4),
(311, 21, 89, NULL, 'forum_post', 'New forum post: ewqe', 1, '2025-08-08 13:42:26', 4),
(312, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-08 13:44:07', 4),
(313, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-10 15:40:41', 4),
(314, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #16 from Joshua Lerin: 1 × Sample 3 — forwarded to admin.', 0, '2025-08-10 15:40:55', 4),
(315, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-10 17:05:05', 4),
(316, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #17 from Joshua Lerin: 1 × Sample 2 — forwarded to admin.', 0, '2025-08-10 17:23:33', 4),
(317, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #15 from Joshua Lerin: 1 × Sample 2 — forwarded to admin.', 0, '2025-08-10 17:43:21', 4),
(318, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-10 17:46:20', 4),
(319, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #18 from Joshua Lerin: 1 × Sample 3 — forwarded to admin.', 1, '2025-08-10 17:46:28', 4),
(320, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-10 17:47:15', 4),
(321, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #19 from Joshua Lerin: 3 × Sample 3 — forwarded to admin.', 1, '2025-08-10 17:51:31', 4),
(322, 4, 90, NULL, 'forum_post', 'New forum post: sample 1', 1, '2025-08-11 14:58:14', NULL),
(323, 16, 90, NULL, 'forum_post', 'New forum post: sample 1', 1, '2025-08-11 14:58:14', 4),
(324, 21, 90, NULL, 'forum_post', 'New forum post: sample 1', 1, '2025-08-11 14:58:14', 4),
(325, 22, 90, NULL, 'forum_post', 'New forum post: sample 1', 0, '2025-08-11 14:58:14', 10),
(326, 23, 90, NULL, 'forum_post', 'New forum post: sample 1', 0, '2025-08-11 14:58:14', 10),
(327, 21, 91, NULL, 'forum_post', 'New forum post: sample', 1, '2025-08-11 14:59:43', 4),
(328, 16, 91, NULL, 'forum_post', 'New forum post: sample', 1, '2025-08-11 14:59:43', 4),
(329, 4, 91, NULL, 'forum_post', 'New forum post: sample', 1, '2025-08-11 14:59:43', 4),
(330, 4, 90, 74, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-11 15:02:27', 11),
(331, 16, 90, 74, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-11 15:02:27', 11),
(332, 21, 90, 74, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-11 15:02:27', 11),
(333, 22, 90, 74, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-11 15:02:27', 11),
(334, 23, 90, 74, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 0, '2025-08-11 15:02:27', 11),
(335, 21, 91, 75, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on your post.', 1, '2025-08-11 15:39:20', 4),
(336, 21, 91, 77, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on your post.', 1, '2025-08-11 15:40:58', 4),
(337, 21, 91, 78, 'forum_comment', '<b>Joshua Lerin (Chorales Member)</b> commented on your post.', 1, '2025-08-15 20:46:08', 4),
(338, 21, 91, 79, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on your post.', 1, '2025-08-16 22:59:49', 4),
(339, 21, 91, 80, 'forum_comment', '<b>Joshua Lerin (Chorales Member)</b> commented on your post.', 1, '2025-08-16 23:00:20', 4),
(340, 4, 93, NULL, 'forum_post', 'New forum post: 54', 1, '2025-08-16 23:01:20', NULL),
(341, 16, 93, NULL, 'forum_post', 'New forum post: 54', 0, '2025-08-16 23:01:20', 4),
(342, 21, 93, NULL, 'forum_post', 'New forum post: 54', 1, '2025-08-16 23:01:20', 4),
(343, 22, 93, NULL, 'forum_post', 'New forum post: 54', 0, '2025-08-16 23:01:20', 10),
(344, 23, 93, NULL, 'forum_post', 'New forum post: 54', 0, '2025-08-16 23:01:20', 10),
(345, 4, 93, 81, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-16 23:01:28', 11),
(346, 16, 93, 81, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 0, '2025-08-16 23:01:28', 11),
(347, 21, 93, 81, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 1, '2025-08-16 23:01:28', 11),
(348, 22, 93, 81, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 0, '2025-08-16 23:01:28', 11),
(349, 23, 93, 81, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on post.', 0, '2025-08-16 23:01:28', 11),
(350, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-17 10:09:09', 4),
(351, 21, NULL, NULL, 'borrow_request', 'Joshua Lerin (Chorales Member)  has submitted a borrow request.', 1, '2025-08-17 10:09:13', 4),
(352, 4, NULL, NULL, 'borrow_validated', '[Chorales] Adviser validated request #21 from Joshua Lerin: 1 × Sample 3 — forwarded to admin.', 1, '2025-08-17 11:10:56', 4),
(353, 21, 94, NULL, 'forum_post', 'New forum post: fsdsd', 1, '2025-08-17 11:55:37', 4),
(354, 4, 94, NULL, 'forum_post', 'New forum post: fsdsd', 1, '2025-08-17 11:55:37', 0),
(355, 16, 94, 83, 'forum_comment', '<b>Arjomel Aguilar (Aca Coordinator)</b> commented on your post.', 0, '2025-08-17 21:42:49', 4);

-- --------------------------------------------------------

--
-- Table structure for table `organization`
--

CREATE TABLE `organization` (
  `org_id` int(11) NOT NULL,
  `org_name` varchar(200) NOT NULL,
  `org_desc` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization`
--

INSERT INTO `organization` (`org_id`, `org_name`, `org_desc`) VALUES
(1, 'Majorettess', 'A group specializing in baton twirling and dance routines, often performing during school events, parades, and sports competitions.'),
(2, 'Marching Band', 'A musical ensemble that performs synchronized music and movement, usually during parades, ceremonies, and athletic events.'),
(3, 'Performing Arts Ensemble', 'A group focused on theatrical performances, combining acting, dancing, and singing in stage productions and cultural shows.'),
(4, 'Chorales', 'A vocal group dedicated to singing choral arrangements, participating in concerts, school masses, and musical competitionss.'),
(7, 'KAMFIL', 'A student organization that promotes Filipino language, culture, and literature through various academic and cultural activities.'),
(8, 'Wasiwas/Colorguard', 'A group that adds visual flair to performances with flag, rifle, and sabre routines, often accompanying the marching band.'),
(9, 'Gurit', 'An organization for aspiring writers and poets, encouraging creativity through literary workshops, contests, and publications.'),
(10, 'Literary Arts Group', 'A club that fosters a love for reading and writing, engaging members in literary discussions, book clubs, and creative writing events.'),
(11, 'SWKS', 'General/All organizations forum'),
(13, 'CIT', 'Computer Lab'),
(14, 'Expanded', 'To expand');

-- --------------------------------------------------------

--
-- Table structure for table `org_events`
--

CREATE TABLE `org_events` (
  `event_id` int(11) NOT NULL,
  `org_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `org_events`
--

INSERT INTO `org_events` (`event_id`, `org_id`, `title`, `start_datetime`, `end_datetime`, `all_day`, `description`, `color`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 4, 'Practice', '2025-08-06 00:00:00', NULL, 1, 'Practice lang', '#198754', 21, '2025-08-14 11:32:13', '2025-08-14 11:32:13'),
(2, 4, 'Rehearsal', '2025-08-07 13:01:00', '2025-08-07 15:00:00', 0, '', '#198754', 21, '2025-08-15 05:00:23', '2025-08-15 05:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `org_id` int(11) DEFAULT NULL,
  `user_email` varchar(200) NOT NULL,
  `user_password` varchar(200) NOT NULL,
  `user_role` enum('admin','adviser','member','') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `org_id`, `user_email`, `user_password`, `user_role`, `created_at`) VALUES
(4, NULL, 'arjomel.aguilar@cbsua.edu.ph', '$2y$10$WFM4nmWKk45rUn6GqzRQq.hWTMCAnWqskwdPqCMl2VzbEWbUYk9A6', 'admin', '2025-05-27 00:00:00'),
(16, 4, 'joshua.lerin@cbsua.edu.ph', '$2y$10$bc7YNZA1KqVI0RhN7l.pKulFnz4ARJTMB9wELDmAEGrSHlu5.Eo1S', 'member', '2025-08-08 13:16:52'),
(21, 4, 'florencio.bermejo@cbsua.edu.ph', '$2y$10$/8ODDc0gh12knZhoC49w9eeljgkN0GzfO4opYjvLjCtaI32DXUAyK', 'adviser', '2025-08-05 14:36:00'),
(22, 10, 'juandelacruz@cbsua.edu.ph', '$2y$10$5LahgAf8Mdt1e/KgFViwou4nzuu/55RN0OQrlnIOpE0Tn4YjkA3Jy', 'adviser', '2025-08-11 14:51:43'),
(23, 10, 'johndoe@cbsua.edu.ph', '$2y$10$5LahgAf8Mdt1e/KgFViwou4nzuu/55RN0OQrlnIOpE0Tn4YjkA3Jy', 'member', '2025-08-11 14:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `web_settings`
--

CREATE TABLE `web_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) DEFAULT NULL,
  `type` enum('carousel','about') NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `department_head` varchar(255) DEFAULT NULL,
  `head_profile` varchar(255) DEFAULT NULL,
  `org_chart` varchar(255) DEFAULT NULL,
  `status` enum('visible','hidden') DEFAULT 'visible',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `web_settings`
--

INSERT INTO `web_settings` (`setting_id`, `user_id`, `org_id`, `type`, `image_path`, `description`, `department_head`, `head_profile`, `org_chart`, `status`, `created_at`) VALUES
(1, 4, NULL, 'carousel', 'uploads/1754277254_received_2245914292490705.jpeg', 'eventsass', NULL, NULL, NULL, 'visible', '2025-08-04 11:14:14'),
(2, 4, NULL, 'carousel', 'uploads/1754280467_received_681923841332987.jpeg', 'group picture', NULL, NULL, NULL, 'visible', '2025-08-04 12:07:47'),
(9, 4, NULL, 'about', NULL, 'Ang Sentro ng Wika, Kultura at Sining (SWKS) ay nagsisilbing pangunahing tanggapan sa pagpapaunlad, pagpapayabong, at pagpapalaganap ng wikang Filipino, kulturang Pilipino, at sining sa loob ng paaralan at komunidad. Layunin ng SWKS na magsagawa ng mga proyekto, seminar, at aktibidad na magpapalalim sa pag-unawa at pagpapahalaga ng mga estudyante at guro sa sariling wika, kultura, at sining.\r\n\r\nNakikipag-ugnayan ang SWKS sa iba’t ibang departamento upang maisulong ang mga programang tumutugon sa pangangailangan ng pamayanang Pilipino tungo sa makabago ngunit makabayang pag-aaral at pagkilos.', 'Arjomel Aguilar', 'uploads/head_profile_1754312239.jpg', 'uploads/org_chart_1754312239.webp', 'visible', '2025-08-04 20:48:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aca_coordinator_details`
--
ALTER TABLE `aca_coordinator_details`
  ADD PRIMARY KEY (`coor_id`),
  ADD KEY `aca_coordinator_details_ibfk_1` (`user_id`);

--
-- Indexes for table `adviser_details`
--
ALTER TABLE `adviser_details`
  ADD PRIMARY KEY (`adviser_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_borrow_user` (`user_id`),
  ADD KEY `fk_borrow_org` (`org_id`);

--
-- Indexes for table `borrow_request_items`
--
ALTER TABLE `borrow_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `forum_comment`
--
ALTER TABLE `forum_comment`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forum_post`
--
ALTER TABLE `forum_post`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `org_id` (`org_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `member_details`
--
ALTER TABLE `member_details`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `comment_id` (`comment_id`);

--
-- Indexes for table `organization`
--
ALTER TABLE `organization`
  ADD PRIMARY KEY (`org_id`);

--
-- Indexes for table `org_events`
--
ALTER TABLE `org_events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `user_ibfk_1` (`org_id`);

--
-- Indexes for table `web_settings`
--
ALTER TABLE `web_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `org_id` (`org_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aca_coordinator_details`
--
ALTER TABLE `aca_coordinator_details`
  MODIFY `coor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adviser_details`
--
ALTER TABLE `adviser_details`
  MODIFY `adviser_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `borrow_request_items`
--
ALTER TABLE `borrow_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `forum_comment`
--
ALTER TABLE `forum_comment`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `forum_post`
--
ALTER TABLE `forum_post`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member_details`
--
ALTER TABLE `member_details`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=356;

--
-- AUTO_INCREMENT for table `organization`
--
ALTER TABLE `organization`
  MODIFY `org_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `org_events`
--
ALTER TABLE `org_events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `web_settings`
--
ALTER TABLE `web_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `aca_coordinator_details`
--
ALTER TABLE `aca_coordinator_details`
  ADD CONSTRAINT `aca_coordinator_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `adviser_details`
--
ALTER TABLE `adviser_details`
  ADD CONSTRAINT `adviser_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD CONSTRAINT `fk_borrow_org` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_borrow_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `borrow_request_items`
--
ALTER TABLE `borrow_request_items`
  ADD CONSTRAINT `borrow_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `borrow_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrow_request_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_comment`
--
ALTER TABLE `forum_comment`
  ADD CONSTRAINT `forum_comment_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_post` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_comment_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `forum_post`
--
ALTER TABLE `forum_post`
  ADD CONSTRAINT `forum_post_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`),
  ADD CONSTRAINT `forum_post_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `member_details`
--
ALTER TABLE `member_details`
  ADD CONSTRAINT `member_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `notification_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `forum_post` (`post_id`),
  ADD CONSTRAINT `notification_ibfk_3` FOREIGN KEY (`comment_id`) REFERENCES `forum_comment` (`comment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `user_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `web_settings`
--
ALTER TABLE `web_settings`
  ADD CONSTRAINT `web_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `web_settings_ibfk_2` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
