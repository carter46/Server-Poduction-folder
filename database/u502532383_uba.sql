-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 19, 2026 at 02:08 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u502532383_uba`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_bank_account_settings`
--

CREATE TABLE `access_bank_account_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `access_bank_account_settings`
--

INSERT INTO `access_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'YAHUZA ABDULLAHI IBR', '0051931777', 939662719.07, '2026-02-05 11:58:03', '2026-08-18 21:51:03');

-- --------------------------------------------------------

--
-- Table structure for table `access_bank_transactions`
--

CREATE TABLE `access_bank_transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `access_bank_transactions`
--

INSERT INTO `access_bank_transactions` (`id`, `reference`, `session_id`, `reference_id`, `amount`, `currency`, `beneficiary_name`, `beneficiary_bank`, `beneficiary_account`, `sender_account`, `sender_name`, `purpose`, `status`, `transaction_date`, `created_at`) VALUES
(5, '7818312470', '000015260818214116311911997056', 'EXTTRF|1581141413066854', 3000000000.00, 'NGN', 'SULEMAN   ISAH', 'Zenith Bank', '1005861580', '0051931777', 'YAHUZA ABDULLAHI IBR', 'LAND PAYMENT', 'SUCCESSFUL', '2026-08-18 21:41:16', '2026-08-18 21:41:16'),
(6, '9316560936', '000015260818215103280009477853', 'EXTTRF|8067373128046731', 2500005000.00, 'NGN', 'SULEMAN   ISAH', 'Zenith Bank', '1005861580', '0051931777', 'YAHUZA ABDULLAHI IBR', 'LAND PAYMENT', 'SUCCESSFUL', '2026-08-18 21:51:03', '2026-08-18 21:51:03');

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `email`, `created_at`, `last_login`) VALUES
(1, 'admin', 'Secretpass0721//', 'admin@ubadashboard.com', '2025-11-28 22:40:23', '2026-08-18 21:43:23');

-- --------------------------------------------------------

--
-- Table structure for table `bank_status`
--

CREATE TABLE `bank_status` (
  `id` int(11) NOT NULL,
  `bank_code` varchar(20) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `status` enum('full_logs','weak_logs','pending_request','post_no_debit','fixed_account') NOT NULL DEFAULT 'full_logs',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_status`
--

INSERT INTO `bank_status` (`id`, `bank_code`, `bank_name`, `status`, `created_at`, `updated_at`) VALUES
(1, '033', 'UBA', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(2, '011', 'First Bank', 'weak_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(3, '044', 'Access Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(4, '070', 'Fidelity Bank', 'weak_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(5, '058', 'Guaranty Trust Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(6, '030', 'Heritage Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(7, '301', 'Jaiz Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(8, '082', 'Keystone Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(9, '232', 'Sterling Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(10, '032', 'Union Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(11, '215', 'Unity Bank', 'post_no_debit', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(12, '035', 'Wema Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(13, '057', 'Zenith Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(14, '50211', 'Kuda Bank', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(15, '50515', 'Moniepoint', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(16, '999992', 'OPay', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33'),
(17, '100033', 'PalmPay', 'full_logs', '2025-12-04 22:12:52', '2026-08-18 22:35:33');

-- --------------------------------------------------------

--
-- Table structure for table `bvn_status`
--

CREATE TABLE `bvn_status` (
  `id` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bvn_status`
--

INSERT INTO `bvn_status` (`id`, `status`, `updated_at`) VALUES
(1, 'active', '2026-08-18 22:11:26');

-- --------------------------------------------------------

--
-- Table structure for table `customer_id_status`
--

CREATE TABLE `customer_id_status` (
  `id` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `customer_id_status`
--

INSERT INTO `customer_id_status` (`id`, `status`, `updated_at`) VALUES
(1, 'active', '2026-08-18 21:22:13');

-- --------------------------------------------------------

--
-- Table structure for table `first_bank_account_settings`
--

CREATE TABLE `first_bank_account_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `first_bank_account_settings`
--

INSERT INTO `first_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'EJEAGBASI DORATHY GINIKA', '3043421821', 702934279.98, '2025-12-03 23:30:00', '2026-07-01 21:55:31');

-- --------------------------------------------------------

--
-- Table structure for table `first_bank_transactions`
--

CREATE TABLE `first_bank_transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flutterwave_settings`
--

CREATE TABLE `flutterwave_settings` (
  `id` int(11) NOT NULL,
  `test_public_key` varchar(255) DEFAULT NULL,
  `test_secret_key` varchar(255) DEFAULT NULL,
  `test_encryption_key` varchar(255) DEFAULT NULL,
  `live_public_key` varchar(255) DEFAULT NULL,
  `live_secret_key` varchar(255) DEFAULT NULL,
  `live_encryption_key` varchar(255) DEFAULT NULL,
  `use_live` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `flutterwave_settings`
--

INSERT INTO `flutterwave_settings` (`id`, `test_public_key`, `test_secret_key`, `test_encryption_key`, `live_public_key`, `live_secret_key`, `live_encryption_key`, `use_live`, `updated_at`) VALUES
(1, NULL, NULL, NULL, 'FLWPUBK-99a783b2b9fd25b02a8238d6fa96ced8-X', 'FLWSECK-9b01a633ff862db95a400c5a12a88acc-19ef67942b9vt-X', '9b01a633ff86806ec7bde316', 1, '2026-07-03 19:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `license_keys`
--

CREATE TABLE `license_keys` (
  `id` int(11) NOT NULL,
  `license_key` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `license_keys`
--

INSERT INTO `license_keys` (`id`, `license_key`, `is_active`, `created_at`) VALUES
(1, '47637335b3eac7cba69ba88e24323489c341386b2e5a2bfb4495212b86e6091d', 0, '2025-12-05 20:29:19'),
(2, '7173beb07af40fac3a24a09684daae051a7c58a86656e774fe2b5778898bcef0', 0, '2025-12-05 20:58:31'),
(3, '902d0387f7ca1f906d39942440c497ac115f26db29c5ae35a8cf105ae5e6d026', 0, '2025-12-08 19:45:45'),
(4, '62627a3179e9ed8b0debd4f50a9dd8548b197c7b4a07df5b0da3f94139572293', 0, '2025-12-09 20:19:28'),
(5, '52aa864778c4adccf93b110146a240e8cc32ba18523e10c4f12f39e48bce9c6c', 0, '2025-12-12 23:08:40'),
(6, 'd3dc81785d1094f10a1f083c1095db0ac20dae89c45340f187819d67733e69b8', 0, '2025-12-13 07:19:28'),
(7, 'de60b95f555f7276984f134964b8cacce5d46121b80b1dbd01f51c43c745d9c8', 0, '2025-12-13 07:25:26'),
(8, 'f8f503e2196383a20110e95c1f06ea9defe5a7308dc6645b2b37bb4770471b42', 0, '2025-12-17 19:27:26'),
(9, '84d57745fc28a4f78ae2dc7734cfbed8076e7fd44c42a8c968838706293e79b1', 0, '2025-12-23 09:29:15'),
(10, 'e32e6a4a7b54e73bd432df8e2b2f97405c16b20293bdeb7613a20fba81f19e38', 0, '2025-12-31 04:23:13'),
(11, 'e23bc48b6f1dac9bd02e3c543bdc3b4fa5d9a36b927766785b632bb352b3b92c', 0, '2026-01-16 21:20:37'),
(12, 'c0c6c7908eb4bfcfe26849f53fd2256d595a6f87eadc846cc5d016a44414dc16', 0, '2026-01-19 18:16:34'),
(13, 'c479a80f67845c476992abd8a84f8ce5d0578a9d13ca2b2734a86c65c79167e2', 0, '2026-01-29 22:35:12'),
(14, 'e8622bf16ec628d3ff40018ffad31e1695d078211d4c3b3b1662c5713b0a2b75', 0, '2026-01-29 22:36:27'),
(15, 'd0030c03209af39ea728d1f5d1a173ecf085b16dfebf1c069b3a627b0db6b3f5', 0, '2026-02-16 12:07:22'),
(16, '25ca775c9b99dbb98b2e8a62e2310ec989d8f52ea7381bf9fcfb57816b053c19', 0, '2026-02-25 19:57:43'),
(17, '79eeceb272efc60bea067cde673ff3726ec4f4cdf0c266e4a59ae4ddfde04d85', 0, '2026-03-25 14:16:09'),
(18, '603520e1053abc018079802ebfe1f49a505cbcd98eda00b7b683fcb5c4b97f4e', 0, '2026-05-10 12:04:09'),
(19, '0c01cbf29a0ba0913d1898898d8260e1b3513326a6d55973dc0172295c79474b', 0, '2026-05-13 15:34:20'),
(20, 'b541b0c671848b55cce32a5b90b2f5a0d0519c40c505f71eda9e7270b062249d', 0, '2026-06-24 07:41:40'),
(21, 'afd4fb53334024524bb11993eca958dab98fde24f7a85efb73682938221a5c61', 0, '2026-07-03 22:02:36'),
(22, '810585e087233dae02ae5e05b366d77672047b0bc9bbf4f1cd9cac0968960c90', 0, '2026-07-27 12:39:25'),
(23, 'ff01df0b3de42be085838fa80f6fba63ec48a890d7da091da001b5d0b4455bb8', 0, '2026-07-27 12:39:30'),
(24, '8d6de0a6f40211236733258059a922f75eda4a51eef870c157f536b2ecd26d78', 0, '2026-07-27 12:40:33'),
(25, '07ae59fa38b007669667c7661eaae492422af293d4641a1a8902382066d2a46c', 0, '2026-07-30 14:49:40'),
(26, 'd777e7a8cfa65a18338df120a81d169b9bf0e67027b8d69eb60e27c85c9c9d5b', 0, '2026-07-30 14:58:51'),
(27, '02a0756b22fc537917bb7e6381cafa50be1289c409b391779bd111f1f0e667de', 0, '2026-08-04 08:41:19'),
(28, 'e9a0567b8f131aa1380e44debe0b53f8f94cbd49369c46615f89d9845f857a34', 0, '2026-08-10 21:31:38'),
(29, '6800e1ea695e63058fc7f172291735c9034fdcea0596a86b33f6fed102944fd6', 1, '2026-08-11 05:26:21');

-- --------------------------------------------------------

--
-- Table structure for table `license_settings`
--

CREATE TABLE `license_settings` (
  `id` int(11) NOT NULL,
  `purchase_email` varchar(255) NOT NULL DEFAULT 'support@ubadashboard.com',
  `renewal_gate` enum('off','on') NOT NULL DEFAULT 'off',
  `software_activated` enum('no','yes') NOT NULL DEFAULT 'no',
  `normal_delay_seconds` int(11) NOT NULL DEFAULT 15,
  `renewal_delay_seconds` int(11) NOT NULL DEFAULT 25,
  `expected_signature` varchar(255) NOT NULL DEFAULT 'UBA-RENEWAL-SIG-A8829F0D11D992A',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `license_settings`
--

INSERT INTO `license_settings` (`id`, `purchase_email`, `renewal_gate`, `software_activated`, `normal_delay_seconds`, `renewal_delay_seconds`, `expected_signature`, `updated_at`) VALUES
(1, 'evacuation.log@proton.me', 'on', 'yes', 10, 10, 'UBA-RENEWAL-SIG-A8829F0D11D992A', '2026-08-08 18:57:46');

-- --------------------------------------------------------

--
-- Table structure for table `mobile_device_tokens`
--

CREATE TABLE `mobile_device_tokens` (
  `id` int(11) NOT NULL,
  `bank_code` varchar(20) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `fcm_token` varchar(512) NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `mobile_device_tokens`
--

INSERT INTO `mobile_device_tokens` (`id`, `bank_code`, `account_number`, `fcm_token`, `updated_at`) VALUES
(1, 'ZENITH', '1010974394', 'c_gdNpb2Tua5QrmT05ZCN7:APA91bFezu8GIK99IhCztfwUV-EIFFkdjdk3mLk1EgWH-4TMp5wTnD6Eqdhz2vAVmeg2r9pn8ugUyJTXwchc2-nAnZmjn77MfG5LAkFZi8MFHVf3qObnje8', '2026-08-12 19:40:59'),
(6, 'ZENITH', '1311795728', 'dLl285i-SNiMDxCy89yJA6:APA91bHya9DbtEv9nmZAIMB8RXbinwnm5lRzvXOUbx_Seg-53Jm45yJ81l6Ol4Aa-0eYzstPaTl9ay6brjGIvs0IoJGE-tED_6c_OoVOA0ZK0ll32RWAxhY', '2026-08-17 15:43:45'),
(12, 'WEMA', '0125813950', 'eayDX02EQ4y3K1QqejAyvV:APA91bEBKRwLQCrPB245q6G3mQIUxIZM4wjbWtU5gWRcIADWy_Jhm61GPx7TnufuM6Mb74vp2C__ux-50q81hGkHE8NnF2nFBfmhF9kJaDzix69Kux9IgnU', '2026-08-18 22:41:55');

-- --------------------------------------------------------

--
-- Table structure for table `mobile_sessions`
--

CREATE TABLE `mobile_sessions` (
  `id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `bank_code` varchar(20) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name_snapshot` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `mobile_sessions`
--

INSERT INTO `mobile_sessions` (`id`, `token`, `bank_code`, `account_number`, `account_name_snapshot`, `expires_at`, `created_at`) VALUES
(12, 'bc92df46c944f09e8c55fd080f548caaa6947a1b8f06be4ee8f439e6e942ac76', 'ZENITH', '1005861580', 'SULEMAN   ISAH', '2026-08-25 21:45:22', '2026-08-18 21:45:22'),
(13, '42c966627f211c9fd4f4d046655613f8e98e83793e2605f08b0b0e5880bcc47c', 'WEMA', '0125813950', 'SPLENDID HOOD ENTERPRISE', '2026-08-25 22:41:54', '2026-08-18 22:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `mobile_settings`
--

CREATE TABLE `mobile_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) DEFAULT NULL,
  `fcm_server_key` varchar(512) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `mobile_settings`
--

INSERT INTO `mobile_settings` (`id`, `password_hash`, `fcm_server_key`, `updated_at`) VALUES
(1, '$2y$10$ZMhVYPuwaZzqCaGr/jX2G.iycviMJcFbkUha0xQkNjNr8nlanHWIC', NULL, '2026-08-18 21:43:39');

-- --------------------------------------------------------

--
-- Table structure for table `paystack_settings`
--

CREATE TABLE `paystack_settings` (
  `id` int(11) NOT NULL,
  `test_public_key` varchar(255) DEFAULT NULL,
  `test_secret_key` varchar(255) DEFAULT NULL,
  `live_public_key` varchar(255) DEFAULT NULL,
  `live_secret_key` varchar(255) DEFAULT NULL,
  `test_key` varchar(255) DEFAULT NULL,
  `live_key` varchar(255) DEFAULT NULL,
  `use_live` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `paystack_settings`
--

INSERT INTO `paystack_settings` (`id`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `test_key`, `live_key`, `use_live`, `updated_at`) VALUES
(1, NULL, NULL, 'pk_live_b9c40ecf618fbab864012c1532a336f0ac497aad', 'sk_live_ecc963ab7c4417ea9a2051cefb9287ef18ba1cce', NULL, 'sk_live_fc6a9d6fed91eadb4226db9b61408ab614c2533f', 1, '2026-05-11 14:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `platform_status`
--

CREATE TABLE `platform_status` (
  `id` int(11) NOT NULL,
  `status` enum('on','off') NOT NULL DEFAULT 'on',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_status`
--

INSERT INTO `platform_status` (`id`, `status`, `updated_at`) VALUES
(1, 'on', '2026-08-18 22:10:52');

-- --------------------------------------------------------

--
-- Table structure for table `uba_account_settings`
--

CREATE TABLE `uba_account_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 670473471.10,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uba_account_settings`
--

INSERT INTO `uba_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'AUTOGRAPH CONSTRUCTION LIMITED', '1022090307', 16182892120.09, '2025-11-28 22:40:23', '2026-08-13 07:24:56');

-- --------------------------------------------------------

--
-- Table structure for table `uba_transactions`
--

CREATE TABLE `uba_transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `uba_transactions`
--

INSERT INTO `uba_transactions` (`id`, `reference`, `session_id`, `reference_id`, `amount`, `currency`, `beneficiary_name`, `beneficiary_bank`, `beneficiary_account`, `sender_account`, `sender_name`, `purpose`, `status`, `transaction_date`, `created_at`) VALUES
(13, '2523674745', '000015260813072456837583610062', 'EXTTRF|5093839028232453', 1300000000.00, 'NGN', 'SPLENDID HOOD ENTERPRISE', 'Zenith Bank', '1311795728', '1022090307', 'AUTOGRAPH CONSTRUCTION LIMITED', NULL, 'SUCCESSFUL', '2026-08-13 07:24:56', '2026-08-13 07:24:56');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `license_key_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `license_key_id`, `created_at`, `last_login`, `password_changed_at`) VALUES
(12, 'LONGINCOM247', 'LONGINCOM247', 29, '2026-02-16 12:06:53', '2026-07-11 11:13:10', '2026-07-01 21:07:05'),
(14, 'cart44', 'Secretpass0721//', 29, '2026-06-23 20:22:12', '2026-08-18 12:32:00', '2026-06-23 20:22:12'),
(15, 'VeloNexus', 'VeloNexus7777//@', 29, '2026-07-11 10:21:10', '2026-08-18 22:03:28', '2026-07-24 15:07:40'),
(16, 'VortexRider_92', ' k7#M9$pL2!vX8@qN', 29, '2026-08-04 08:40:53', NULL, '2026-08-04 08:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `wema_bank_account_settings`
--

CREATE TABLE `wema_bank_account_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wema_bank_account_settings`
--

INSERT INTO `wema_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'OLAYIWOLA ALABI SEUN', '0247599431', 62073446.16, '2026-08-18 12:31:24', '2026-08-18 22:41:23');

-- --------------------------------------------------------

--
-- Table structure for table `wema_bank_transactions`
--

CREATE TABLE `wema_bank_transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wema_bank_transactions`
--

INSERT INTO `wema_bank_transactions` (`id`, `reference`, `session_id`, `reference_id`, `amount`, `currency`, `beneficiary_name`, `beneficiary_bank`, `beneficiary_account`, `sender_account`, `sender_name`, `purpose`, `status`, `transaction_date`, `created_at`) VALUES
(3, 'AFBSSST-1a017056ee9589c0b0', '000015260818223707277486355131', 'EXTTRF|6814513198094893', 256700.00, 'NGN', 'SULEMAN   ISAH', 'Zenith Bank', '1005861580', '0247599431', 'OLAYIWOLA ALABI SEUN', NULL, 'SUCCESSFUL', '2026-08-18 22:37:07', '2026-08-18 22:37:07'),
(4, 'AFBSSST-1a01709582f2c99ae3', '000015260818224123677565062563', 'EXTTRF|7638203878614621', 700000000.00, 'NGN', 'SPLENDID HOOD ENTERPRISE', 'ALAT by WEMA', '0125813950', '0247599431', 'OLAYIWOLA ALABI SEUN', NULL, 'SUCCESSFUL', '2026-08-18 22:41:23', '2026-08-18 22:41:23');

-- --------------------------------------------------------

--
-- Table structure for table `zenith_bank_account_settings`
--

CREATE TABLE `zenith_bank_account_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL DEFAULT 'AUTOGRAPH CONSTRUCTION LIMITED',
  `account_number` varchar(50) NOT NULL DEFAULT '1022090307',
  `balance` decimal(15,2) NOT NULL DEFAULT 4192401.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zenith_bank_account_settings`
--

INSERT INTO `zenith_bank_account_settings` (`id`, `account_name`, `account_number`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'ABDULSALAMI ABUKAKAR', '1005028477', 1355660590.30, '2026-02-05 11:58:03', '2026-08-12 01:51:44');

-- --------------------------------------------------------

--
-- Table structure for table `zenith_bank_transactions`
--

CREATE TABLE `zenith_bank_transactions` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `beneficiary_name` varchar(255) NOT NULL,
  `beneficiary_bank` varchar(255) NOT NULL,
  `beneficiary_account` varchar(50) NOT NULL,
  `sender_account` varchar(50) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `purpose` varchar(500) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'SUCCESSFUL',
  `transaction_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zenith_bank_transactions`
--

INSERT INTO `zenith_bank_transactions` (`id`, `reference`, `session_id`, `reference_id`, `amount`, `currency`, `beneficiary_name`, `beneficiary_bank`, `beneficiary_account`, `sender_account`, `sender_name`, `purpose`, `status`, `transaction_date`, `created_at`) VALUES
(3, '1948160872', '000015260812015144549551571948', 'EXTTRF|0110561056234064', 100000.00, 'NGN', 'VICTORIA CROWN PLAZA LTD A/C II', 'Zenith Bank', '1010974394', '1005028477', 'ABDULSALAMI ABUKAKAR', NULL, 'SUCCESSFUL', '2026-08-12 01:51:44', '2026-08-12 01:51:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_bank_account_settings`
--
ALTER TABLE `access_bank_account_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `access_bank_transactions`
--
ALTER TABLE `access_bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ben_acct_32185db4` (`beneficiary_account`);

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bank_status`
--
ALTER TABLE `bank_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_code` (`bank_code`);

--
-- Indexes for table `bvn_status`
--
ALTER TABLE `bvn_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_id_status`
--
ALTER TABLE `customer_id_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `first_bank_account_settings`
--
ALTER TABLE `first_bank_account_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `first_bank_transactions`
--
ALTER TABLE `first_bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ben_acct_aa2ec200` (`beneficiary_account`);

--
-- Indexes for table `flutterwave_settings`
--
ALTER TABLE `flutterwave_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `license_keys`
--
ALTER TABLE `license_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`);

--
-- Indexes for table `license_settings`
--
ALTER TABLE `license_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mobile_device_tokens`
--
ALTER TABLE `mobile_device_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mobile_device` (`bank_code`,`account_number`);

--
-- Indexes for table `mobile_sessions`
--
ALTER TABLE `mobile_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mobile_session_token` (`token`),
  ADD KEY `idx_mobile_session_account` (`bank_code`,`account_number`);

--
-- Indexes for table `mobile_settings`
--
ALTER TABLE `mobile_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paystack_settings`
--
ALTER TABLE `paystack_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `platform_status`
--
ALTER TABLE `platform_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uba_account_settings`
--
ALTER TABLE `uba_account_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uba_transactions`
--
ALTER TABLE `uba_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ben_acct_76775def` (`beneficiary_account`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `license_key_id` (`license_key_id`);

--
-- Indexes for table `wema_bank_account_settings`
--
ALTER TABLE `wema_bank_account_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wema_bank_transactions`
--
ALTER TABLE `wema_bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ben_acct_265fe65c` (`beneficiary_account`);

--
-- Indexes for table `zenith_bank_account_settings`
--
ALTER TABLE `zenith_bank_account_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `zenith_bank_transactions`
--
ALTER TABLE `zenith_bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ben_acct_f87b987e` (`beneficiary_account`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_bank_account_settings`
--
ALTER TABLE `access_bank_account_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `access_bank_transactions`
--
ALTER TABLE `access_bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bank_status`
--
ALTER TABLE `bank_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1123;

--
-- AUTO_INCREMENT for table `bvn_status`
--
ALTER TABLE `bvn_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_id_status`
--
ALTER TABLE `customer_id_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `first_bank_account_settings`
--
ALTER TABLE `first_bank_account_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `first_bank_transactions`
--
ALTER TABLE `first_bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `flutterwave_settings`
--
ALTER TABLE `flutterwave_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `license_keys`
--
ALTER TABLE `license_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `license_settings`
--
ALTER TABLE `license_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mobile_device_tokens`
--
ALTER TABLE `mobile_device_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `mobile_sessions`
--
ALTER TABLE `mobile_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `paystack_settings`
--
ALTER TABLE `paystack_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `platform_status`
--
ALTER TABLE `platform_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uba_account_settings`
--
ALTER TABLE `uba_account_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uba_transactions`
--
ALTER TABLE `uba_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `wema_bank_account_settings`
--
ALTER TABLE `wema_bank_account_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wema_bank_transactions`
--
ALTER TABLE `wema_bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `zenith_bank_account_settings`
--
ALTER TABLE `zenith_bank_account_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `zenith_bank_transactions`
--
ALTER TABLE `zenith_bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `admin_sessions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`license_key_id`) REFERENCES `license_keys` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
