-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 04 Agu 2026 pada 06.11
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_invgenerator`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `billing_master`
--

CREATE TABLE `billing_master` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `billing_master`
--

INSERT INTO `billing_master` (`id`, `nama`, `created_at`) VALUES
(1, 'PASIRKADU', '2026-08-04 01:43:05'),
(2, 'PASIRHUNI', '2026-08-04 02:47:32'),
(3, 'TEREPDAMAR', '2026-08-04 03:05:48'),
(4, 'LINDA NURALINDA', '2026-08-04 01:43:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `company_settings`
--

CREATE TABLE `company_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `company_name` varchar(150) NOT NULL DEFAULT 'PLANETFlow',
  `contact_name` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL DEFAULT '',
  `website` varchar(150) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `payment_info` text DEFAULT NULL,
  `invoice_note` text DEFAULT NULL,
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `contact_name`, `phone`, `email`, `website`, `address`, `payment_info`, `invoice_note`, `logo_path`, `updated_at`) VALUES
(1, 'PLANETFlow', '', '', '', '', '', '', '', '', '2026-08-04 02:15:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customer_accounts`
--

CREATE TABLE `customer_accounts` (
  `id` int(11) NOT NULL,
  `customer_awalan` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `customer_accounts`
--

INSERT INTO `customer_accounts` (`id`, `customer_awalan`, `username`, `password`, `full_name`, `is_active`, `created_at`) VALUES
(1, 'AKH', '@akharikah', 'Arikah', 'Arikah', 1, '2026-08-04 01:43:05'),
(2, 'NCN', '@ncnncun', 'Ncun', 'Ncun', 1, '2026-08-04 02:29:29'),
(3, 'UJH', '@ujhjuheni', 'Juheni', 'Juheni', 1, '2026-08-04 02:30:53'),
(4, 'NN1', '@nn1sahani', 'Sahani', 'Sahani', 1, '2026-08-04 02:34:38'),
(5, 'MM1', '@mm1mami', 'Mami', 'Mami', 1, '2026-08-04 02:36:11'),
(6, 'DD1', '@dd1dodi', 'Dodi', 'Dodi', 1, '2026-08-04 02:37:37'),
(7, 'RD1', '@rd1ramdani', 'Ramdani', 'Ramdani', 1, '2026-08-04 02:44:51'),
(8, 'B31', '@b31beti', 'Beti', 'Beti', 1, '2026-08-04 02:47:32'),
(9, 'BUN', '@bunburhan', 'Burhan', 'Burhan', 1, '2026-08-04 02:49:22'),
(10, 'P1N', '@p1npian', 'Pian', 'Pian', 1, '2026-08-04 02:50:57'),
(11, 'WR4', '@wr4wira', 'Wira', 'Wira', 1, '2026-08-04 02:53:06'),
(12, '1RO', '@1rosarah', 'Sarah', 'Sarah', 1, '2026-08-04 02:54:09'),
(13, 'U7G', '@u7gujang', 'Ujang', 'Ujang', 1, '2026-08-04 02:56:01'),
(14, 'MDL', '@mdldull', 'Dull', 'Dull', 1, '2026-08-04 02:57:29'),
(15, 'N1K', '@n1kninik', 'Ninik', 'Ninik', 1, '2026-08-04 02:58:34'),
(16, '1PH', '@1phipah', 'Ipah', 'Ipah', 1, '2026-08-04 02:59:36'),
(17, 'S4R', '@s4rrtsarman', 'Rt Sarman', 'Rt Sarman', 1, '2026-08-04 03:01:04'),
(18, 'U5M', '@u5musum', 'Usum', 'Usum', 1, '2026-08-04 03:02:39'),
(19, 'MRD', '@mrdmurid', 'Murid', 'Murid', 1, '2026-08-04 03:03:41'),
(20, 'JAS', '@jasjasir', 'Jasir', 'Jasir', 1, '2026-08-04 03:05:48'),
(21, 'NTA', '@ntanita', 'Nita', 'Nita', 1, '2026-08-04 03:06:43'),
(22, 'PDK', '@pdkhkayat', 'H kayat', 'H kayat', 1, '2026-08-04 03:07:58'),
(23, 'PDL', '@pdlsarta', 'Sarta', 'Sarta', 1, '2026-08-04 03:08:46'),
(24, '9PR', '@9prgopur', 'Gopur', 'Gopur', 1, '2026-08-04 03:16:46'),
(25, 'SMK', '@smksarmank', 'Sarman k', 'Sarman k', 1, '2026-08-04 03:17:51'),
(26, '4R1', '@4r1arti', 'Arti', 'Arti', 1, '2026-08-04 03:19:15'),
(27, 'LMN', '@lmnlamin', 'Lamin', 'Lamin', 1, '2026-08-04 03:21:53'),
(28, 'NY4', '@ny4mursidi', 'Mursidi', 'Mursidi', 1, '2026-08-04 03:24:01'),
(29, 'MAS', '@masmaskuncir', 'Maskuncir', 'Maskuncir', 1, '2026-08-04 03:25:31'),
(30, 'SDB', '@sdbsabda', 'Sabda', 'Sabda', 1, '2026-08-04 03:27:57'),
(31, 'UUN', '@uunuun', 'UUN', 'UUN', 1, '2026-08-04 03:28:48'),
(32, 'UNK', '@unkujangpedes', 'Ujang pedes', 'Ujang pedes', 1, '2026-08-04 03:30:19'),
(33, '54R', '@54rsair', 'Sair', 'Sair', 1, '2026-08-04 03:31:27'),
(34, 'SN1', '@sn1sani', 'Sani', 'Sani', 1, '2026-08-04 03:32:41'),
(35, 'HP3', '@hp3kuple', 'Kuple', 'Kuple', 1, '2026-08-04 03:33:45'),
(36, 'E5H', '@e5hesih', 'Esih', 'Esih', 1, '2026-08-04 03:38:07'),
(37, '5RN', '@5rnsarudin', 'Sarudin', 'Sarudin', 1, '2026-08-04 03:39:06'),
(38, 'BRN', '@brnbahrudin', 'Bahrudin', 'Bahrudin', 1, '2026-08-04 03:39:52'),
(39, 'ZZM', '@zzmsajum', 'Sajum', 'Sajum', 1, '2026-08-04 03:40:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customer_paket_harga`
--

CREATE TABLE `customer_paket_harga` (
  `id` int(11) NOT NULL,
  `awalan` varchar(10) NOT NULL,
  `paket` varchar(100) NOT NULL,
  `harga` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `customer_paket_harga`
--

INSERT INTO `customer_paket_harga` (`id`, `awalan`, `paket`, `harga`) VALUES
(4, 'AKH', '12 JAM', 4500.00),
(5, 'AKH', '6 JAM', 2500.00),
(6, 'AKH', '7 HARI', 15000.00),
(7, 'AKH', '30 HARI', 50000.00),
(8, 'AKH', '3 JAM', 1500.00),
(9, 'AKH', '24 JAM', 6000.00),
(10, 'AKH', '3 HARI', 9000.00),
(11, 'AKH', 'VC 12 JAM', 2500.00),
(12, 'AKH', 'VC 24 JAM', 4500.00),
(13, 'NCN', '3 JAM', 1500.00),
(14, 'NCN', '6 JAM', 2500.00),
(15, 'NCN', '12 JAM', 6000.00),
(16, 'NCN', 'VC 12 JAM', 2500.00),
(17, 'NCN', '24 JAM', 6000.00),
(18, 'NCN', 'VC 24 JAM', 4500.00),
(19, 'NCN', '3 HARI', 9000.00),
(20, 'NCN', '7 HARI', 15000.00),
(21, 'NCN', '30 HARI', 50000.00),
(22, 'UJH', '3 JAM', 1500.00),
(23, 'UJH', '6 JAM', 2500.00),
(24, 'UJH', '12 JAM', 4500.00),
(25, 'UJH', 'VC 12 JAM', 2500.00),
(26, 'UJH', '24 JAM', 6000.00),
(27, 'UJH', 'VC 24 JAM', 4500.00),
(28, 'UJH', '3 HARI', 9000.00),
(29, 'UJH', '7 HARI', 15000.00),
(30, 'UJH', '30 HARI', 50000.00),
(31, 'NN1', '3 JAM', 1500.00),
(32, 'NN1', '6 JAM', 2000.00),
(33, 'NN1', '12 JAM', 4000.00),
(34, 'NN1', 'VC 12 JAM', 2500.00),
(35, 'NN1', '24 JAM', 6000.00),
(36, 'NN1', 'VC 24 JAM', 4500.00),
(37, 'NN1', '3 HARI', 9000.00),
(38, 'NN1', '7 HARI', 15000.00),
(39, 'NN1', '30 HARI', 50000.00),
(40, 'MM1', '3 JAM', 1500.00),
(41, 'MM1', '6 JAM', 2000.00),
(42, 'MM1', '12 JAM', 4000.00),
(43, 'MM1', 'VC 12 JAM', 2500.00),
(44, 'MM1', '24 JAM', 5000.00),
(45, 'MM1', 'VC 24 JAM', 4500.00),
(46, 'MM1', '3 HARI', 8000.00),
(47, 'MM1', '7 HARI', 16000.00),
(48, 'MM1', '30 HARI', 50000.00),
(49, 'DD1', '3 JAM', 1500.00),
(50, 'DD1', '6 JAM', 2000.00),
(51, 'DD1', '12 JAM', 4000.00),
(52, 'DD1', 'VC 12 JAM', 2500.00),
(53, 'DD1', '24 JAM', 5000.00),
(54, 'DD1', 'VC 24 JAM', 4500.00),
(55, 'DD1', '3 HARI', 8000.00),
(56, 'DD1', '7 HARI', 15000.00),
(57, 'DD1', '30 HARI', 50000.00),
(58, 'RD1', '3 JAM', 1500.00),
(59, 'RD1', '6 JAM', 2500.00),
(60, 'RD1', '12 JAM', 4500.00),
(61, 'RD1', 'VC 12 JAM', 2500.00),
(62, 'RD1', '24 JAM', 6000.00),
(63, 'RD1', 'VC 24 JAM', 4500.00),
(64, 'RD1', '3 HARI', 9000.00),
(65, 'RD1', '7 HARI', 15000.00),
(66, 'RD1', '30 HARI', 50000.00),
(67, 'B31', '3 JAM', 1500.00),
(68, 'B31', '6 JAM', 2500.00),
(69, 'B31', '12 JAM', 4500.00),
(70, 'B31', 'VC 12 JAM', 2500.00),
(71, 'B31', '24 JAM', 6000.00),
(72, 'B31', 'VC 24 JAM', 4500.00),
(73, 'B31', '3 HARI', 9000.00),
(74, 'B31', '7 HARI', 15000.00),
(75, 'B31', '30 HARI', 55000.00),
(76, 'BUN', '3 JAM', 1300.00),
(77, 'BUN', '6 JAM', 2000.00),
(78, 'BUN', '12 JAM', 4000.00),
(79, 'BUN', 'VC 12 JAM', 2500.00),
(80, 'BUN', '24 JAM', 5000.00),
(81, 'BUN', 'VC 24 JAM', 4500.00),
(82, 'BUN', '3 HARI', 8000.00),
(83, 'BUN', '7 HARI', 15000.00),
(84, 'BUN', '30 HARI', 50000.00),
(85, 'P1N', '3 JAM', 1500.00),
(86, 'P1N', '6 JAM', 2500.00),
(87, 'P1N', '12 JAM', 4500.00),
(88, 'P1N', 'VC 12 JAM', 2500.00),
(89, 'P1N', '24 JAM', 7000.00),
(90, 'P1N', 'VC 24 JAM', 4500.00),
(91, 'P1N', '3 HARI', 9000.00),
(92, 'P1N', '7 HARI', 20000.00),
(93, 'P1N', '30 HARI', 55000.00),
(94, 'WR4', '3 JAM', 1500.00),
(95, 'WR4', '6 JAM', 2500.00),
(96, 'WR4', '12 JAM', 4500.00),
(97, 'WR4', 'VC 12 JAM', 2500.00),
(98, 'WR4', '24 JAM', 7000.00),
(99, 'WR4', 'VC 24 JAM', 4500.00),
(100, 'WR4', '3 HARI', 9000.00),
(101, 'WR4', '7 HARI', 20000.00),
(102, 'WR4', '30 HARI', 55000.00),
(103, '1RO', '3 JAM', 1500.00),
(104, '1RO', '6 JAM', 2500.00),
(105, '1RO', '12 JAM', 4500.00),
(106, '1RO', 'VC 12 JAM', 2500.00),
(107, '1RO', '24 JAM', 7000.00),
(108, '1RO', 'VC 24 JAM', 4500.00),
(109, '1RO', '3 HARI', 9000.00),
(110, '1RO', '7 HARI', 20000.00),
(111, '1RO', '30 HARI', 50000.00),
(112, 'U7G', '3 JAM', 1500.00),
(113, 'U7G', '6 JAM', 2500.00),
(114, 'U7G', '12 JAM', 4500.00),
(115, 'U7G', 'VC 12 JAM', 2500.00),
(116, 'U7G', '24 JAM', 7000.00),
(117, 'U7G', 'VC 24 JAM', 4500.00),
(118, 'U7G', '3 HARI', 9000.00),
(119, 'U7G', '7 HARI', 20000.00),
(120, 'U7G', '30 HARI', 55000.00),
(121, 'MDL', '3 JAM', 1500.00),
(122, 'MDL', '6 JAM', 2500.00),
(123, 'MDL', '12 JAM', 4500.00),
(124, 'MDL', 'VC 12 JAM', 2500.00),
(125, 'MDL', '24 JAM', 7000.00),
(126, 'MDL', 'VC 24 JAM', 4500.00),
(127, 'MDL', '3 HARI', 9000.00),
(128, 'MDL', '7 HARI', 20000.00),
(129, 'MDL', '30 HARI', 55000.00),
(130, 'N1K', '3 JAM', 1500.00),
(131, 'N1K', '6 JAM', 2500.00),
(132, 'N1K', '12 JAM', 4500.00),
(133, 'N1K', 'VC 12 JAM', 2500.00),
(134, 'N1K', '24 JAM', 7000.00),
(135, 'N1K', 'VC 24 JAM', 4500.00),
(136, 'N1K', '3 HARI', 9000.00),
(137, 'N1K', '7 HARI', 20000.00),
(138, 'N1K', '30 HARI', 55000.00),
(139, '1PH', '3 JAM', 1500.00),
(140, '1PH', '6 JAM', 2500.00),
(141, '1PH', '12 JAM', 4500.00),
(142, '1PH', 'VC 12 JAM', 2500.00),
(143, '1PH', '24 JAM', 7000.00),
(144, '1PH', 'VC 24 JAM', 4500.00),
(145, '1PH', '3 HARI', 9000.00),
(146, '1PH', '7 HARI', 20000.00),
(147, '1PH', '30 HARI', 55000.00),
(148, 'S4R', '3 JAM', 1500.00),
(149, 'S4R', '6 JAM', 2500.00),
(150, 'S4R', '12 JAM', 4500.00),
(151, 'S4R', 'VC 12 JAM', 2500.00),
(152, 'S4R', '24 JAM', 7000.00),
(153, 'S4R', 'VC 24 JAM', 4500.00),
(154, 'S4R', '3 HARI', 9000.00),
(155, 'S4R', '7 HARI', 20000.00),
(156, 'S4R', '30 HARI', 55000.00),
(157, 'U5M', '3 JAM', 1500.00),
(158, 'U5M', '6 JAM', 2500.00),
(159, 'U5M', '12 JAM', 4500.00),
(160, 'U5M', 'VC 12 JAM', 2500.00),
(161, 'U5M', '24 JAM', 7000.00),
(162, 'U5M', 'VC 24 JAM', 4500.00),
(163, 'U5M', '3 HARI', 9000.00),
(164, 'U5M', '7 HARI', 20000.00),
(165, 'U5M', '30 HARI', 55000.00),
(166, 'MRD', '3 JAM', 1500.00),
(167, 'MRD', '6 JAM', 2500.00),
(168, 'MRD', '12 JAM', 4500.00),
(169, 'MRD', 'VC 12 JAM', 2500.00),
(170, 'MRD', '24 JAM', 7000.00),
(171, 'MRD', 'VC 24 JAM', 4500.00),
(172, 'MRD', '3 HARI', 9000.00),
(173, 'MRD', '7 HARI', 20000.00),
(174, 'MRD', '30 HARI', 55000.00),
(175, 'JAS', '3 JAM', 1500.00),
(176, 'JAS', '6 JAM', 3000.00),
(177, 'JAS', '12 JAM', 5000.00),
(178, 'JAS', 'VC 12 JAM', 2500.00),
(179, 'JAS', '24 JAM', 7000.00),
(180, 'JAS', 'VC 24 JAM', 4500.00),
(181, 'JAS', '3 HARI', 9000.00),
(182, 'JAS', '7 HARI', 20000.00),
(183, 'JAS', '30 HARI', 55000.00),
(184, 'NTA', '3 JAM', 1500.00),
(185, 'NTA', '6 JAM', 3000.00),
(186, 'NTA', '12 JAM', 5000.00),
(187, 'NTA', 'VC 12 JAM', 2500.00),
(188, 'NTA', '24 JAM', 7000.00),
(189, 'NTA', 'VC 24 JAM', 4500.00),
(190, 'NTA', '3 HARI', 9000.00),
(191, 'NTA', '7 HARI', 20000.00),
(192, 'NTA', '30 HARI', 55000.00),
(193, 'PDK', '3 JAM', 1500.00),
(194, 'PDK', '6 JAM', 3000.00),
(195, 'PDK', '12 JAM', 5000.00),
(196, 'PDK', 'VC 12 JAM', 2500.00),
(197, 'PDK', '24 JAM', 7000.00),
(198, 'PDK', 'VC 24 JAM', 4500.00),
(199, 'PDK', '3 HARI', 9000.00),
(200, 'PDK', '7 HARI', 20000.00),
(201, 'PDK', '30 HARI', 55000.00),
(202, 'PDL', '3 JAM', 1500.00),
(203, 'PDL', '6 JAM', 3000.00),
(204, 'PDL', '12 JAM', 5000.00),
(205, 'PDL', 'VC 12 JAM', 2500.00),
(206, 'PDL', '24 JAM', 7000.00),
(207, 'PDL', 'VC 24 JAM', 4500.00),
(208, 'PDL', '3 HARI', 9000.00),
(209, 'PDL', '7 HARI', 20000.00),
(210, 'PDL', '30 HARI', 55000.00),
(211, '9PR', '3 JAM', 1500.00),
(212, '9PR', '6 JAM', 3000.00),
(213, '9PR', '12 JAM', 5000.00),
(214, '9PR', 'VC 12 JAM', 2500.00),
(215, '9PR', '24 JAM', 7000.00),
(216, '9PR', 'VC 24 JAM', 4500.00),
(217, '9PR', '3 HARI', 9000.00),
(218, '9PR', '7 HARI', 20000.00),
(219, '9PR', '30 HARI', 55000.00),
(220, 'SMK', '3 JAM', 1500.00),
(221, 'SMK', '6 JAM', 2500.00),
(222, 'SMK', '12 JAM', 4500.00),
(223, 'SMK', 'VC 12 JAM', 2500.00),
(224, 'SMK', '24 JAM', 7000.00),
(225, 'SMK', 'VC 24 JAM', 4500.00),
(226, 'SMK', '3 HARI', 9000.00),
(227, 'SMK', '7 HARI', 20000.00),
(228, 'SMK', '30 HARI', 50000.00),
(229, '4R1', '3 JAM', 1500.00),
(230, '4R1', '6 JAM', 2500.00),
(231, '4R1', '12 JAM', 4500.00),
(232, '4R1', 'VC 12 JAM', 2500.00),
(233, '4R1', '24 JAM', 7000.00),
(234, '4R1', 'VC 24 JAM', 4500.00),
(235, '4R1', '3 HARI', 9000.00),
(236, '4R1', '7 HARI', 20000.00),
(237, '4R1', '30 HARI', 55000.00),
(238, 'LMN', '3 JAM', 1500.00),
(239, 'LMN', '6 JAM', 3000.00),
(240, 'LMN', '12 JAM', 5000.00),
(241, 'LMN', 'VC 12 JAM', 2500.00),
(242, 'LMN', '24 JAM', 7000.00),
(243, 'LMN', 'VC 24 JAM', 4500.00),
(244, 'LMN', '3 HARI', 9000.00),
(245, 'LMN', '7 HARI', 20000.00),
(246, 'LMN', '30 HARI', 55000.00),
(247, 'NY4', '3 JAM', 1500.00),
(248, 'NY4', '6 JAM', 3000.00),
(249, 'NY4', '12 JAM', 5000.00),
(250, 'NY4', 'VC 12 JAM', 2500.00),
(251, 'NY4', '24 JAM', 7000.00),
(252, 'NY4', 'VC 24 JAM', 4500.00),
(253, 'NY4', '3 HARI', 9000.00),
(254, 'NY4', '7 HARI', 20000.00),
(255, 'NY4', '30 HARI', 55000.00),
(256, 'MAS', '3 JAM', 1500.00),
(257, 'MAS', '6 JAM', 3000.00),
(258, 'MAS', '12 JAM', 5000.00),
(259, 'MAS', 'VC 12 JAM', 2500.00),
(260, 'MAS', '24 JAM', 7000.00),
(261, 'MAS', 'VC 24 JAM', 4500.00),
(262, 'MAS', '3 HARI', 9000.00),
(263, 'MAS', '7 HARI', 20000.00),
(264, 'MAS', '30 HARI', 55000.00),
(265, 'SDB', '3 JAM', 1500.00),
(266, 'SDB', '6 JAM', 2500.00),
(267, 'SDB', '12 JAM', 4500.00),
(268, 'SDB', 'VC 12 JAM', 2500.00),
(269, 'SDB', '24 JAM', 7000.00),
(270, 'SDB', 'VC 24 JAM', 4500.00),
(271, 'SDB', '3 HARI', 9000.00),
(272, 'SDB', '7 HARI', 20000.00),
(273, 'SDB', '30 HARI', 55000.00),
(274, 'UUN', '3 JAM', 1500.00),
(275, 'UUN', '6 JAM', 2500.00),
(276, 'UUN', '12 JAM', 4500.00),
(277, 'UUN', 'VC 12 JAM', 2500.00),
(278, 'UUN', '24 JAM', 7000.00),
(279, 'UUN', 'VC 24 JAM', 4500.00),
(280, 'UUN', '3 HARI', 9000.00),
(281, 'UUN', '7 HARI', 20000.00),
(282, 'UUN', '30 HARI', 55000.00),
(283, 'UNK', '3 JAM', 1500.00),
(284, 'UNK', '6 JAM', 3000.00),
(285, 'UNK', '12 JAM', 5000.00),
(286, 'UNK', 'VC 12 JAM', 2500.00),
(287, 'UNK', '24 JAM', 7000.00),
(288, 'UNK', 'VC 24 JAM', 4500.00),
(289, 'UNK', '3 HARI', 9000.00),
(290, 'UNK', '7 HARI', 20000.00),
(291, 'UNK', '30 HARI', 55000.00),
(292, '54R', '3 JAM', 1500.00),
(293, '54R', '6 JAM', 2500.00),
(294, '54R', '12 JAM', 4500.00),
(295, '54R', 'VC 12 JAM', 2500.00),
(296, '54R', '24 JAM', 7000.00),
(297, '54R', 'VC 24 JAM', 4500.00),
(298, '54R', '3 HARI', 9000.00),
(299, '54R', '7 HARI', 20000.00),
(300, '54R', '30 HARI', 55000.00),
(301, 'SN1', '3 JAM', 1500.00),
(302, 'SN1', '6 JAM', 2500.00),
(303, 'SN1', '12 JAM', 4500.00),
(304, 'SN1', 'VC 12 JAM', 2500.00),
(305, 'SN1', '24 JAM', 7000.00),
(306, 'SN1', 'VC 24 JAM', 4500.00),
(307, 'SN1', '3 HARI', 9000.00),
(308, 'SN1', '7 HARI', 20000.00),
(309, 'SN1', '30 HARI', 55000.00),
(310, 'HP3', '3 JAM', 1500.00),
(311, 'HP3', '6 JAM', 3000.00),
(312, 'HP3', '12 JAM', 5000.00),
(313, 'HP3', 'VC 12 JAM', 2500.00),
(314, 'HP3', '24 JAM', 7000.00),
(315, 'HP3', 'VC 24 JAM', 4500.00),
(316, 'HP3', '3 HARI', 9000.00),
(317, 'HP3', '7 HARI', 20000.00),
(318, 'HP3', '30 HARI', 55000.00),
(319, 'E5H', 'VC 12 JAM', 2500.00),
(320, 'E5H', 'VC 24 JAM', 4500.00),
(321, 'E5H', '3 HARI', 9000.00),
(322, 'E5H', '7 HARI', 17000.00),
(323, 'E5H', '30 HARI', 55000.00),
(324, '5RN', 'VC 12 JAM', 2500.00),
(325, '5RN', 'VC 24 JAM', 4500.00),
(326, '5RN', '3 HARI', 9000.00),
(327, '5RN', '7 HARI', 17000.00),
(328, '5RN', '30 HARI', 55000.00),
(329, 'BRN', 'VC 12 JAM', 2500.00),
(330, 'BRN', 'VC 24 JAM', 4500.00),
(331, 'BRN', '3 HARI', 9000.00),
(332, 'BRN', '7 HARI', 17000.00),
(333, 'BRN', '30 HARI', 55000.00),
(334, 'ZZM', 'VC 12 JAM', 2500.00),
(335, 'ZZM', 'VC 24 JAM', 4500.00),
(336, 'ZZM', '3 HARI', 9000.00),
(337, 'ZZM', '7 HARI', 17000.00),
(338, 'ZZM', '30 HARI', 55000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `generated_documents`
--

CREATE TABLE `generated_documents` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `document_type` varchar(10) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `saved_name` varchar(100) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `billing_id` int(11) DEFAULT NULL,
  `generated_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `generated_documents`
--

INSERT INTO `generated_documents` (`id`, `file_id`, `document_type`, `original_name`, `saved_name`, `file_size`, `billing_id`, `generated_by_user_id`, `created_at`) VALUES
(1, 4, 'EXCEL', 'EXCEL_04-08-2026_05-01-57.xlsx', 'excel_4084_04-08-2026.xlsx', 7275, NULL, 1, '2026-08-04 03:01:57'),
(2, 4, 'PDF', 'PDF_04-08-2026_05-01-58.pdf', 'pdf_3003_04-08-2026.pdf', 37763, NULL, 1, '2026-08-04 03:01:58'),
(3, 5, 'EXCEL', 'EXCEL_04-08-2026_05-42-51.xlsx', 'excel_3962_04-08-2026.xlsx', 7276, NULL, 1, '2026-08-04 03:42:51'),
(4, 5, 'PDF', 'PDF_04-08-2026_05-42-52.pdf', 'pdf_7699_04-08-2026.pdf', 37018, NULL, 1, '2026-08-04 03:42:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `awalan` varchar(10) NOT NULL,
  `total_harga` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `invoices`
--

INSERT INTO `invoices` (`id`, `file_id`, `awalan`, `total_harga`, `created_at`) VALUES
(1, 1, 'AKH', 1281500.00, '2026-08-04 00:41:35'),
(2, 1, 'NCN', 537000.00, '2026-08-04 00:41:35'),
(3, 1, 'DD1', 465000.00, '2026-08-04 00:41:35'),
(4, 1, 'UJH', 306500.00, '2026-08-04 00:41:35'),
(5, 2, 'AKH', 1281500.00, '2026-08-04 00:44:18'),
(6, 2, 'NCN', 537000.00, '2026-08-04 00:44:18'),
(7, 2, 'DD1', 465000.00, '2026-08-04 00:44:18'),
(8, 2, 'UJH', 306500.00, '2026-08-04 00:44:18'),
(11, 3, 'BRN', 42500.00, '2026-08-04 02:21:35'),
(12, 3, '5RN', 58000.00, '2026-08-04 02:21:35'),
(13, 3, 'E5H', 106500.00, '2026-08-04 02:21:35'),
(14, 4, 'AKH', 1281500.00, '2026-08-04 02:25:15'),
(15, 4, 'NCN', 537000.00, '2026-08-04 02:25:15'),
(16, 4, 'DD1', 465000.00, '2026-08-04 02:25:15'),
(17, 4, 'UJH', 306500.00, '2026-08-04 02:25:15'),
(33, 5, 'AKH', 1281500.00, '2026-08-04 03:42:24'),
(34, 5, 'NCN', 537000.00, '2026-08-04 03:42:24'),
(35, 5, 'DD1', 465000.00, '2026-08-04 03:42:24'),
(36, 5, 'UJH', 306500.00, '2026-08-04 03:42:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `paket_master`
--

CREATE TABLE `paket_master` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `paket_master`
--

INSERT INTO `paket_master` (`id`, `nama`, `created_at`) VALUES
(1, '3 JAM', '2026-08-04 00:41:35'),
(2, '6 JAM', '2026-08-04 00:41:35'),
(3, '12 JAM', '2026-08-04 00:41:35'),
(4, 'VC 12 JAM', '2026-08-04 02:21:35'),
(5, '24 JAM', '2026-08-04 02:25:15'),
(6, 'VC 24 JAM', '2026-08-04 02:21:35'),
(7, '3 HARI', '2026-08-04 00:41:35'),
(8, '7 HARI', '2026-08-04 00:41:35'),
(9, '30 HARI', '2026-08-04 00:41:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `prefix_customers`
--

CREATE TABLE `prefix_customers` (
  `id` int(11) NOT NULL,
  `awalan` varchar(10) NOT NULL,
  `nama_pelanggan` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `billing_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `prefix_customers`
--

INSERT INTO `prefix_customers` (`id`, `awalan`, `nama_pelanggan`, `alamat`, `telepon`, `billing_id`) VALUES
(2, 'AKH', 'Arikah', '', '', 1),
(3, 'NCN', 'Ncun', '', '', 1),
(4, 'UJH', 'Juheni', '', '', 1),
(5, 'NN1', 'Sahani', '', '', 4),
(6, 'MM1', 'Mami', '', '', 4),
(7, 'DD1', 'Dodi', '', '', 1),
(8, 'RD1', 'Ramdani', '', '', 2),
(9, 'B31', 'Beti', '', '', 2),
(10, 'BUN', 'Burhan', '', '', 2),
(11, 'P1N', 'Pian', '', '', 2),
(12, 'WR4', 'Wira', '', '', 2),
(13, '1RO', 'Sarah', '', '', 2),
(14, 'U7G', 'Ujang', '', '', 2),
(15, 'MDL', 'Dull', '', '', 2),
(16, 'N1K', 'Ninik', '', '', 2),
(17, '1PH', 'Ipah', '', '', 2),
(18, 'S4R', 'Rt Sarman', '', '', 2),
(19, 'U5M', 'Usum', '', '', 2),
(20, 'MRD', 'Murid', '', '', 2),
(21, 'JAS', 'Jasir', '', '', 2),
(22, 'NTA', 'Nita', '', '', 2),
(23, 'PDK', 'H kayat', '', '', 2),
(24, 'PDL', 'Sarta', '', '', 2),
(25, '9PR', 'Gopur', '', '', 2),
(26, 'SMK', 'Sarman k', '', '', 2),
(27, '4R1', 'Arti', '', '', 2),
(28, 'LMN', 'Lamin', '', '', 2),
(29, 'NY4', 'Mursidi', '', '', 2),
(30, 'MAS', 'Maskuncir', '', '', 2),
(31, 'SDB', 'Sabda', '', '', 2),
(32, 'UUN', 'UUN', '', '', 2),
(33, 'UNK', 'Ujang pedes', '', '', 2),
(34, '54R', 'Sair', '', '', 2),
(35, 'SN1', 'Sani', '', '', 2),
(36, 'HP3', 'Kuple', '', '', 2),
(37, 'E5H', 'Esih', '', '', 3),
(38, '5RN', 'Sarudin', '', '', 3),
(39, 'BRN', 'Bahrudin', '', '', 3),
(40, 'ZZM', 'Sajum', '', '', 3);

-- --------------------------------------------------------

--
-- Struktur dari tabel `rekap`
--

CREATE TABLE `rekap` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL DEFAULT 1,
  `awalan` varchar(10) NOT NULL,
  `paket` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `total_biaya` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rekap`
--

INSERT INTO `rekap` (`id`, `file_id`, `awalan`, `paket`, `jumlah`, `total_biaya`) VALUES
(1, 1, 'AKH', '12 JAM', 100, 400000.00),
(2, 1, 'AKH', '6 JAM', 119, 238000.00),
(3, 1, 'AKH', '7 HARI', 35, 525000.00),
(4, 1, 'AKH', '3 HARI', 1, 8000.00),
(5, 1, 'NCN', '7 HARI', 16, 240000.00),
(6, 1, 'NCN', '12 JAM', 28, 112000.00),
(7, 1, 'NCN', '3 JAM', 10, 13000.00),
(8, 1, 'NCN', '24 JAM', 19, 95000.00),
(9, 1, 'DD1', '12 JAM', 2, 8000.00),
(10, 1, 'DD1', '30 HARI', 6, 300000.00),
(11, 1, 'DD1', '7 HARI', 10, 150000.00),
(12, 1, 'DD1', '24 JAM', 1, 5000.00),
(13, 1, 'DD1', '6 JAM', 1, 2000.00),
(14, 1, 'UJH', '6 JAM', 32, 64000.00),
(15, 1, 'UJH', '12 JAM', 17, 68000.00),
(16, 1, 'UJH', '7 HARI', 10, 150000.00),
(17, 2, 'AKH', '12 JAM', 100, 400000.00),
(18, 2, 'AKH', '6 JAM', 119, 238000.00),
(19, 2, 'AKH', '7 HARI', 35, 525000.00),
(20, 2, 'AKH', '3 HARI', 1, 8000.00),
(21, 2, 'NCN', '7 HARI', 16, 240000.00),
(22, 2, 'NCN', '12 JAM', 28, 112000.00),
(23, 2, 'NCN', '3 JAM', 10, 13000.00),
(24, 2, 'NCN', '24 JAM', 19, 95000.00),
(25, 2, 'DD1', '12 JAM', 2, 8000.00),
(26, 2, 'DD1', '30 HARI', 6, 300000.00),
(27, 2, 'DD1', '7 HARI', 10, 150000.00),
(28, 2, 'DD1', '24 JAM', 1, 5000.00),
(29, 2, 'DD1', '6 JAM', 1, 2000.00),
(30, 2, 'UJH', '6 JAM', 32, 64000.00),
(31, 2, 'UJH', '12 JAM', 17, 68000.00),
(32, 2, 'UJH', '7 HARI', 10, 150000.00),
(33, 3, 'BRN', 'VC 12 JAM', 3, 6000.00),
(34, 3, 'BRN', 'VC 24 JAM', 4, 16000.00),
(35, 3, 'BRN', '7 HARI', 1, 15000.00),
(36, 3, '5RN', 'VC 24 JAM', 8, 32000.00),
(37, 3, '5RN', 'VC 12 JAM', 2, 4000.00),
(38, 3, '5RN', '7 HARI', 1, 15000.00),
(39, 3, 'E5H', 'VC 24 JAM', 6, 24000.00),
(40, 3, 'E5H', '7 HARI', 2, 30000.00),
(41, 3, 'E5H', 'VC 12 JAM', 11, 22000.00),
(42, 3, 'E5H', '3 HARI', 2, 16000.00),
(43, 4, 'AKH', '12 JAM', 100, 400000.00),
(44, 4, 'AKH', '6 JAM', 119, 238000.00),
(45, 4, 'AKH', '7 HARI', 35, 525000.00),
(46, 4, 'AKH', '3 HARI', 1, 8000.00),
(47, 4, 'NCN', '7 HARI', 16, 240000.00),
(48, 4, 'NCN', '12 JAM', 28, 112000.00),
(49, 4, 'NCN', '3 JAM', 10, 13000.00),
(50, 4, 'NCN', '24 JAM', 19, 95000.00),
(51, 4, 'DD1', '12 JAM', 2, 8000.00),
(52, 4, 'DD1', '30 HARI', 6, 300000.00),
(53, 4, 'DD1', '7 HARI', 10, 150000.00),
(54, 4, 'DD1', '24 JAM', 1, 5000.00),
(55, 4, 'DD1', '6 JAM', 1, 2000.00),
(56, 4, 'UJH', '6 JAM', 32, 64000.00),
(57, 4, 'UJH', '12 JAM', 17, 68000.00),
(58, 4, 'UJH', '7 HARI', 10, 150000.00),
(59, 5, 'AKH', '12 JAM', 100, 400000.00),
(60, 5, 'AKH', '6 JAM', 119, 238000.00),
(61, 5, 'AKH', '7 HARI', 35, 525000.00),
(62, 5, 'AKH', '3 HARI', 1, 8000.00),
(63, 5, 'NCN', '7 HARI', 16, 240000.00),
(64, 5, 'NCN', '12 JAM', 28, 112000.00),
(65, 5, 'NCN', '3 JAM', 10, 13000.00),
(66, 5, 'NCN', '24 JAM', 19, 95000.00),
(67, 5, 'DD1', '12 JAM', 2, 8000.00),
(68, 5, 'DD1', '30 HARI', 6, 300000.00),
(69, 5, 'DD1', '7 HARI', 10, 150000.00),
(70, 5, 'DD1', '24 JAM', 1, 5000.00),
(71, 5, 'DD1', '6 JAM', 1, 2000.00),
(72, 5, 'UJH', '6 JAM', 32, 64000.00),
(73, 5, 'UJH', '12 JAM', 17, 68000.00),
(74, 5, 'UJH', '7 HARI', 10, 150000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `rincian`
--

CREATE TABLE `rincian` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL DEFAULT 1,
  `kode` varchar(100) NOT NULL,
  `awalan` varchar(10) NOT NULL,
  `paket` varchar(100) NOT NULL,
  `biaya` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `saved_name` varchar(100) NOT NULL,
  `total_rows` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `periode` varchar(100) NOT NULL,
  `tanggal` date NOT NULL,
  `uploaded_by_user_id` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `uploaded_files`
--

INSERT INTO `uploaded_files` (`id`, `saved_name`, `total_rows`, `periode`, `tanggal`, `uploaded_by_user_id`, `uploaded_at`) VALUES
(1, 'upload_2295_04-08-2026.xlsx', 407, '01 s/d 15 Agustus', '2026-08-04', 1, '2026-08-04 00:41:35'),
(2, 'upload_8531_04-08-2026.xlsx', 407, '01 s/d 15 Agustus', '2026-08-04', 1, '2026-08-04 00:44:18'),
(3, 'upload_9856_04-08-2026.xlsx', 40, '67', '2026-08-04', 1, '2026-08-04 02:21:35'),
(4, 'upload_5058_04-08-2026.xlsx', 407, '333', '2026-08-04', 1, '2026-08-04 02:25:15'),
(5, 'upload_9807_04-08-2026.xlsx', 407, '9999', '2026-08-04', 191, '2026-08-04 03:42:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', 'admin', 'Administrator', 'admin', 1, '2026-08-04 00:13:27'),
(191, '111', '111', '111', 'user', 1, '2026-08-04 03:25:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_billing_access`
--

CREATE TABLE `user_billing_access` (
  `user_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user_billing_access`
--

INSERT INTO `user_billing_access` (`user_id`, `billing_id`) VALUES
(191, 2);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `billing_master`
--
ALTER TABLE `billing_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `customer_accounts`
--
ALTER TABLE `customer_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_awalan` (`customer_awalan`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `customer_paket_harga`
--
ALTER TABLE `customer_paket_harga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cust_paket` (`awalan`,`paket`);

--
-- Indeks untuk tabel `generated_documents`
--
ALTER TABLE `generated_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `saved_name` (`saved_name`),
  ADD KEY `idx_generated_file_id` (`file_id`),
  ADD KEY `idx_generated_created_at` (`created_at`),
  ADD KEY `idx_generated_billing` (`billing_id`),
  ADD KEY `idx_generated_user` (`generated_by_user_id`);

--
-- Indeks untuk tabel `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_file_awalan` (`file_id`,`awalan`),
  ADD KEY `idx_invoice_customer` (`awalan`,`file_id`);

--
-- Indeks untuk tabel `paket_master`
--
ALTER TABLE `paket_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `prefix_customers`
--
ALTER TABLE `prefix_customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `awalan` (`awalan`),
  ADD KEY `idx_billing_id` (`billing_id`),
  ADD KEY `idx_customer_name` (`nama_pelanggan`,`awalan`);

--
-- Indeks untuk tabel `rekap`
--
ALTER TABLE `rekap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_file_awalan_paket` (`file_id`,`awalan`,`paket`);

--
-- Indeks untuk tabel `rincian`
--
ALTER TABLE `rincian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_id` (`file_id`);

--
-- Indeks untuk tabel `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `saved_name` (`saved_name`),
  ADD KEY `idx_uploaded_date` (`tanggal`,`id`),
  ADD KEY `idx_uploaded_user` (`uploaded_by_user_id`),
  ADD KEY `idx_uploaded_created` (`uploaded_at`,`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `user_billing_access`
--
ALTER TABLE `user_billing_access`
  ADD PRIMARY KEY (`user_id`,`billing_id`),
  ADD KEY `idx_access_billing` (`billing_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `billing_master`
--
ALTER TABLE `billing_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `customer_accounts`
--
ALTER TABLE `customer_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT untuk tabel `customer_paket_harga`
--
ALTER TABLE `customer_paket_harga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=339;

--
-- AUTO_INCREMENT untuk tabel `generated_documents`
--
ALTER TABLE `generated_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `paket_master`
--
ALTER TABLE `paket_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT untuk tabel `prefix_customers`
--
ALTER TABLE `prefix_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT untuk tabel `rekap`
--
ALTER TABLE `rekap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT untuk tabel `rincian`
--
ALTER TABLE `rincian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1262;

--
-- AUTO_INCREMENT untuk tabel `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=325;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
