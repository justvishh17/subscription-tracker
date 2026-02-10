-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 08, 2026 at 01:21 PM
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
-- Database: `subtrackk`
--

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email_or_user` varchar(100) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `browser` varchar(500) DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `login_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `user_id`, `ip_address`, `browser`, `status`, `login_time`) VALUES
(3, 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 'success', '2026-02-06 04:49:51'),
(4, 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', 'success', '2026-02-05 18:36:21'),
(5, 10, '::1', 'Chrome on Windows', 'success', '2026-02-08 09:12:29'),
(6, 10, '::1', 'Unknown', 'success', '2026-02-08 09:18:50'),
(7, 10, '::1', 'Unknown', 'success', '2026-02-08 09:21:16'),
(8, 10, '::1', 'Unknown', 'success', '2026-02-08 09:33:53'),
(9, 10, '::1', 'Unknown', 'success', '2026-02-08 09:58:43'),
(10, 10, '::1', 'Unknown', 'success', '2026-02-08 10:02:41'),
(11, 10, '::1', 'Unknown', 'success', '2026-02-08 10:05:36'),
(12, 10, '::1', 'Unknown', 'success', '2026-02-08 10:07:32'),
(13, 10, '::1', 'Unknown', 'success', '2026-02-08 10:11:49'),
(14, 10, '::1', 'Unknown', 'success', '2026-02-08 10:18:58'),
(15, 10, '::1', 'Unknown', 'success', '2026-02-08 11:10:54');

-- --------------------------------------------------------

--
-- Table structure for table `service_catalog`
--

CREATE TABLE `service_catalog` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `plans_json` text NOT NULL,
  `logo_icon` varchar(50) DEFAULT 'fas fa-cube'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_catalog`
--

INSERT INTO `service_catalog` (`id`, `name`, `category`, `plans_json`, `logo_icon`) VALUES
(1, 'Netflix', 'Entertainment', '[\r\n    {\"name\":\"Standard with ads\",\"price\":6.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Standard\",\"price\":15.49,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium (4K)\",\"price\":22.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-netflix'),
(2, 'Spotify', 'Entertainment', '[\r\n    {\"name\":\"Individual\",\"price\":11.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Duo\",\"price\":16.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Student\",\"price\":5.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-spotify'),
(3, 'Apple TV+', 'Entertainment', '[\r\n    {\"name\":\"Monthly\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Yearly\",\"price\":99.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Apple One (Individual)\",\"price\":19.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Apple One (Family)\",\"price\":25.95,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-apple'),
(4, 'Disney+', 'Entertainment', '[\r\n    {\"name\":\"Basic (With Ads)\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium (No Ads)\",\"price\":15.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Annual\",\"price\":159.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Duo Basic (Hulu Bundle)\",\"price\":10.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-video'),
(5, 'YouTube Premium', 'Entertainment', '[\r\n    {\"name\":\"Individual\",\"price\":13.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family\",\"price\":22.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Student\",\"price\":7.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":139.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-youtube'),
(6, 'Hulu', 'Entertainment', '[\r\n    {\"name\":\"With Ads\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"No Ads\",\"price\":18.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Hulu + Live TV (Ads)\",\"price\":81.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-tv'),
(7, 'Max (HBO)', 'Entertainment', '[\r\n    {\"name\":\"With Ads\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Ad-Free\",\"price\":16.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Ultimate Ad-Free\",\"price\":20.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Ad-Free Yearly\",\"price\":169.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-film'),
(8, 'Amazon Prime', 'Utilities', '[\r\n    {\"name\":\"Monthly\",\"price\":14.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":139.00,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Student Monthly\",\"price\":7.49,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-amazon'),
(9, 'Peacock', 'Entertainment', '[\r\n    {\"name\":\"Premium\",\"price\":7.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Plus\",\"price\":13.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Annual\",\"price\":79.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-crow'),
(10, 'Paramount+', 'Entertainment', '[\r\n    {\"name\":\"Essential\",\"price\":7.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"with Showtime\",\"price\":12.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Essential Annual\",\"price\":59.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-mountain'),
(11, 'Crunchyroll', 'Entertainment', '[\r\n    {\"name\":\"Fan\",\"price\":7.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Mega Fan\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Mega Fan Yearly\",\"price\":99.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-dragon'),
(12, 'Audible', 'Personal', '[\r\n    {\"name\":\"Plus\",\"price\":7.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Plus\",\"price\":14.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Plus (2 Credits)\",\"price\":22.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual (12 Credits)\",\"price\":149.50,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-book-open'),
(13, 'Apple Music', 'Entertainment', '[\r\n    {\"name\":\"Individual\",\"price\":10.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family\",\"price\":16.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Student\",\"price\":5.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-apple'),
(14, 'SoundCloud', 'Entertainment', '[\r\n    {\"name\":\"Go\",\"price\":4.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Go+\",\"price\":9.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-soundcloud'),
(15, 'Tidal', 'Entertainment', '[\r\n    {\"name\":\"HiFi\",\"price\":10.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"HiFi Plus\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family\",\"price\":16.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-wave-square'),
(16, 'Xbox Game Pass', 'Entertainment', '[\r\n    {\"name\":\"Core\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Console\",\"price\":10.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"PC\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Ultimate\",\"price\":16.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-xbox'),
(17, 'PlayStation Plus', 'Entertainment', '[\r\n    {\"name\":\"Essential\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Extra\",\"price\":14.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium\",\"price\":17.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Essential Yearly\",\"price\":79.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-playstation'),
(18, 'Nintendo Switch Online', 'Entertainment', '[\r\n    {\"name\":\"Individual (12 Mo)\",\"price\":19.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Expansion Pack\",\"price\":49.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Family\",\"price\":34.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-gamepad'),
(19, 'Discord Nitro', 'Utilities', '[\r\n    {\"name\":\"Basic\",\"price\":2.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Nitro\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Nitro Yearly\",\"price\":99.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-discord'),
(20, 'Twitch', 'Entertainment', '[\r\n    {\"name\":\"Tier 1 Sub\",\"price\":5.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Tier 2 Sub\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Tier 3 Sub\",\"price\":24.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Turbo\",\"price\":11.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-twitch'),
(21, 'GeForce Now', 'Entertainment', '[\r\n    {\"name\":\"Priority\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Ultimate\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Priority (6 Month)\",\"price\":49.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-microchip'),
(22, 'Google One', 'Utilities', '[\r\n    {\"name\":\"Basic (100GB)\",\"price\":1.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Standard (200GB)\",\"price\":2.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium (2TB)\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Basic Yearly\",\"price\":19.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Premium Yearly\",\"price\":99.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-google'),
(23, 'iCloud+', 'Utilities', '[\r\n    {\"name\":\"50GB\",\"price\":0.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"200GB\",\"price\":2.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"2TB\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"6TB\",\"price\":29.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-apple'),
(24, 'Dropbox', 'Work/Tools', '[\r\n    {\"name\":\"Plus\",\"price\":11.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Professional\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Plus Yearly\",\"price\":119.88,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-dropbox'),
(25, 'NordVPN', 'Utilities', '[\r\n    {\"name\":\"Standard Monthly\",\"price\":12.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Plus Monthly\",\"price\":13.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Standard 1-Year\",\"price\":59.88,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Standard 2-Year\",\"price\":83.76,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-shield-alt'),
(26, 'ExpressVPN', 'Utilities', '[\r\n    {\"name\":\"Monthly\",\"price\":12.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"6 Months\",\"price\":59.95,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"12 Months\",\"price\":99.95,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-shield-alt'),
(27, 'LastPass', 'Utilities', '[\r\n    {\"name\":\"Premium\",\"price\":3.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Families\",\"price\":4.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Yearly\",\"price\":36.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-key'),
(28, '1Password', 'Utilities', '[\r\n    {\"name\":\"Individual\",\"price\":2.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Families\",\"price\":4.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Individual Yearly\",\"price\":35.88,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-key'),
(29, 'ChatGPT', 'Work/Tools', '[\r\n    {\"name\":\"Plus\",\"price\":20.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Team\",\"price\":25.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-robot'),
(30, 'Claude Pro', 'Work/Tools', '[\r\n    {\"name\":\"Pro\",\"price\":20.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-brain'),
(31, 'GitHub Copilot', 'Work/Tools', '[\r\n    {\"name\":\"Individual\",\"price\":10.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Yearly\",\"price\":100.00,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-github'),
(32, 'Midjourney', 'Work/Tools', '[\r\n    {\"name\":\"Basic\",\"price\":10.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Standard\",\"price\":30.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Pro\",\"price\":60.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Mega\",\"price\":120.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-palette'),
(33, 'Perplexity', 'Work/Tools', '[\r\n    {\"name\":\"Pro Monthly\",\"price\":20.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Pro Yearly\",\"price\":200.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-search'),
(34, 'JetBrains', 'Work/Tools', '[\r\n    {\"name\":\"All Products Pack\",\"price\":28.90,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"IntelliJ IDEA\",\"price\":16.90,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"All Products Yearly\",\"price\":289.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-code'),
(35, 'Vercel', 'Work/Tools', '[\r\n    {\"name\":\"Pro\",\"price\":20.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-network-wired'),
(36, 'Heroku', 'Work/Tools', '[\r\n    {\"name\":\"Eco\",\"price\":5.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Basic\",\"price\":7.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Standard\",\"price\":25.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-server'),
(37, 'DigitalOcean', 'Work/Tools', '[\r\n    {\"name\":\"Droplet Basic\",\"price\":6.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Droplet Premium\",\"price\":7.00,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-digital-ocean'),
(38, 'Adobe CC', 'Work/Tools', '[\r\n    {\"name\":\"Photography (20GB)\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Single App\",\"price\":22.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"All Apps\",\"price\":59.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Student All Apps\",\"price\":19.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-adobe'),
(39, 'Figma', 'Work/Tools', '[\r\n    {\"name\":\"Professional\",\"price\":15.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Professional Yearly\",\"price\":144.00,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Organization\",\"price\":45.00,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-figma'),
(40, 'Canva', 'Work/Tools', '[\r\n    {\"name\":\"Pro Monthly\",\"price\":14.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Pro Yearly\",\"price\":119.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Teams\",\"price\":29.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-paint-brush'),
(41, 'Notion', 'Work/Tools', '[\r\n    {\"name\":\"Plus Monthly\",\"price\":10.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Plus Yearly\",\"price\":96.00,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Business\",\"price\":18.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-sticky-note'),
(42, 'Microsoft 365', 'Work/Tools', '[\r\n    {\"name\":\"Personal Monthly\",\"price\":6.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Personal Yearly\",\"price\":69.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Family Monthly\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Family Yearly\",\"price\":99.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-microsoft'),
(43, 'Slack', 'Work/Tools', '[\r\n    {\"name\":\"Pro\",\"price\":8.75,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Business+\",\"price\":15.00,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-slack'),
(44, 'Zoom', 'Work/Tools', '[\r\n    {\"name\":\"Pro Monthly\",\"price\":15.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Pro Yearly\",\"price\":149.90,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Business\",\"price\":21.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-video'),
(45, 'Trello', 'Work/Tools', '[\r\n    {\"name\":\"Standard\",\"price\":6.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium\",\"price\":12.50,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-trello'),
(46, 'Evernote', 'Work/Tools', '[\r\n    {\"name\":\"Personal\",\"price\":14.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Professional\",\"price\":17.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Personal Yearly\",\"price\":129.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-elephant'),
(47, 'Duolingo', 'Personal', '[\r\n    {\"name\":\"Super Monthly\",\"price\":12.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Super Family\",\"price\":29.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Super Yearly\",\"price\":83.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-language'),
(48, 'Strava', 'Personal', '[\r\n    {\"name\":\"Monthly\",\"price\":11.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":79.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-running'),
(49, 'MyFitnessPal', 'Personal', '[\r\n    {\"name\":\"Premium Monthly\",\"price\":19.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Annual\",\"price\":79.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-apple-alt'),
(50, 'Peloton', 'Personal', '[\r\n    {\"name\":\"App One\",\"price\":12.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"App+ field\",\"price\":24.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"All-Access\",\"price\":44.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-bicycle'),
(51, 'Calm', 'Personal', '[\r\n    {\"name\":\"Annual\",\"price\":69.99,\"cycle\":\"Yearly\"},\r\n    {\"name\":\"Lifetime\",\"price\":399.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-spa'),
(52, 'Headspace', 'Personal', '[\r\n    {\"name\":\"Monthly\",\"price\":12.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":69.99,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-brain'),
(53, 'Tinder', 'Personal', '[\r\n    {\"name\":\"Plus\",\"price\":7.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Gold\",\"price\":24.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Platinum\",\"price\":29.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-fire'),
(54, 'Bumble', 'Personal', '[\r\n    {\"name\":\"Boost\",\"price\":16.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium\",\"price\":39.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Plus\",\"price\":49.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-heart'),
(55, 'X Premium (Twitter)', 'Personal', '[\r\n    {\"name\":\"Basic\",\"price\":3.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium\",\"price\":8.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium+\",\"price\":16.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Premium Annual\",\"price\":84.00,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-twitter'),
(56, 'LinkedIn Premium', 'Work/Tools', '[\r\n    {\"name\":\"Career\",\"price\":39.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Business\",\"price\":59.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Sales Navigator\",\"price\":99.99,\"cycle\":\"Monthly\"}\r\n]', 'fab fa-linkedin'),
(57, 'Telegram Premium', 'Personal', '[\r\n    {\"name\":\"Monthly\",\"price\":4.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":39.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-telegram'),
(58, 'Snapchat+', 'Personal', '[\r\n    {\"name\":\"Monthly\",\"price\":3.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Yearly\",\"price\":29.99,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-snapchat'),
(59, 'Uber One', 'Utilities', '[\r\n    {\"name\":\"Monthly\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":96.00,\"cycle\":\"Yearly\"}\r\n]', 'fab fa-uber'),
(60, 'DoorDash', 'Utilities', '[\r\n    {\"name\":\"DashPass\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"DashPass Student\",\"price\":4.99,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-hamburger'),
(61, 'Instacart+', 'Utilities', '[\r\n    {\"name\":\"Monthly\",\"price\":9.99,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":99.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-carrot'),
(62, 'Walmart+', 'Utilities', '[\r\n    {\"name\":\"Monthly\",\"price\":12.95,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Annual\",\"price\":98.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-shopping-cart'),
(63, 'NY Times', 'Personal', '[\r\n    {\"name\":\"Basic Digital\",\"price\":4.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"All Access\",\"price\":25.00,\"cycle\":\"Monthly\"}\r\n]', 'fas fa-newspaper'),
(64, 'Coursera', 'Personal', '[\r\n    {\"name\":\"Plus Monthly\",\"price\":59.00,\"cycle\":\"Monthly\"},\r\n    {\"name\":\"Plus Annual\",\"price\":399.00,\"cycle\":\"Yearly\"}\r\n]', 'fas fa-graduation-cap');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `site_name` varchar(100) DEFAULT 'SubTrack',
  `currency_symbol` varchar(10) DEFAULT '$',
  `allow_signups` tinyint(4) DEFAULT 1,
  `maintenance_mode` tinyint(4) DEFAULT 0,
  `allow_signup` tinyint(1) DEFAULT 1,
  `announcement_text` varchar(255) DEFAULT '',
  `announcement_active` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `site_name`, `currency_symbol`, `allow_signups`, `maintenance_mode`, `allow_signup`, `announcement_text`, `announcement_active`) VALUES
(1, 'SubTrack', '$', 1, 0, 1, 'helloooooooooooooooo', 0);

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `billing_period` enum('Monthly','Yearly') DEFAULT 'Monthly',
  `start_date` date NOT NULL,
  `next_due_date` date NOT NULL,
  `status` enum('active','archived','cancelled') DEFAULT 'active',
  `category` varchar(50) NOT NULL DEFAULT 'Other',
  `cancel_url` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Card',
  `is_trial` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  `auto_delete_at` timestamp NULL DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `last_used` date DEFAULT curdate(),
  `snooze_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `service_name`, `price`, `currency`, `billing_period`, `start_date`, `next_due_date`, `status`, `category`, `cancel_url`, `payment_method`, `is_trial`, `created_at`, `archived_at`, `auto_delete_at`, `tags`, `last_used`, `snooze_until`) VALUES
(21, 4, 'Max', 16.99, 'USD', 'Monthly', '2026-02-03', '2026-03-03', 'active', 'Entertainment', '', 'Card', 0, '2026-02-03 19:20:55', NULL, NULL, NULL, '2026-02-05', NULL),
(22, 7, 'ChatGPT', 20.00, 'USD', 'Monthly', '2026-02-04', '2026-03-04', 'active', 'Work/Tools', '', 'Card', 0, '2026-02-03 19:20:55', NULL, NULL, NULL, '2026-02-05', NULL),
(23, 4, 'Evernote', 14.99, 'USD', 'Monthly', '2026-02-01', '2026-03-01', 'active', 'Work/Tools', '', 'Card', 0, '2026-02-03 19:20:55', NULL, NULL, NULL, '2026-02-05', NULL),
(24, 4, 'ChatGPT', 20.00, 'USD', 'Monthly', '2026-01-01', '2026-02-01', 'active', 'Work/Tools', '', 'Card', 0, '2026-02-03 19:20:55', NULL, NULL, NULL, '2026-02-05', NULL),
(32, 10, 'PlayStation Plus', 9.99, 'USD', 'Monthly', '2026-02-13', '2026-03-13', 'archived', 'Entertainment', '', 'Card', 0, '2026-02-03 19:20:55', '2026-02-08 11:33:19', '2026-02-11 11:33:19', NULL, '2026-02-05', NULL),
(33, 10, 'Duolingo', 83.99, 'USD', 'Yearly', '2026-02-27', '2027-02-27', 'archived', 'Personal', '', 'Card', 0, '2026-02-03 19:20:55', '2026-02-07 19:12:03', '2026-02-10 19:12:03', NULL, '2026-02-05', NULL),
(34, 10, 'Max', 99.99, 'USD', 'Yearly', '2026-02-01', '2027-02-01', 'archived', 'Entertainment', '', 'Card', 0, '2026-02-03 19:20:55', '2026-02-07 19:11:58', '2026-02-10 19:11:58', NULL, '2026-02-05', NULL),
(36, 11, 'Netflix', 15.49, 'USD', 'Monthly', '2026-02-01', '2026-03-01', 'active', 'Entertainment', '', 'Card', 0, '2026-02-04 04:47:52', NULL, NULL, NULL, '2026-02-05', NULL),
(37, 11, 'Apple Music', 109.00, 'USD', 'Yearly', '2026-02-12', '2027-02-12', 'active', 'Entertainment', '', 'Card', 0, '2026-02-04 04:50:09', NULL, NULL, NULL, '2026-02-05', NULL),
(38, 10, 'SoundCloud', 9.99, 'USD', 'Monthly', '2026-02-20', '2026-03-20', 'archived', 'Entertainment', '', 'Card', 0, '2026-02-05 11:40:18', '2026-02-08 11:33:25', '2026-02-11 11:33:25', NULL, '2026-02-05', NULL),
(39, 10, 'NordVPN', 12.99, 'USD', 'Monthly', '2026-02-28', '2026-03-28', 'archived', 'Work/Tools', '', 'Card', 0, '2026-02-05 11:40:26', '2026-02-08 11:33:29', '2026-02-11 11:33:29', NULL, '2026-02-05', NULL),
(40, 10, 'Dropbox', 11.99, 'USD', 'Monthly', '2026-02-21', '2026-03-21', 'archived', 'Work/Tools', '', 'Card', 0, '2026-02-05 11:40:34', '2026-02-08 11:33:22', '2026-02-11 11:33:22', NULL, '2026-02-05', NULL),
(43, 10, 'Adobe CC', 9.99, 'USD', 'Monthly', '2026-02-05', '2026-03-05', 'archived', 'Work/Tools', '', 'Card', 0, '2026-02-05 13:01:52', '2026-02-08 11:33:05', '2026-02-11 11:33:05', NULL, '2026-02-05', NULL),
(44, 13, 'Netflix', 6.99, 'USD', 'Monthly', '2026-02-20', '2026-03-20', 'active', 'Entertainment', '', 'Card', 0, '2026-02-05 13:29:31', NULL, NULL, NULL, '2026-02-05', NULL),
(45, 13, 'Disney+', 7.99, 'USD', 'Monthly', '2026-02-06', '2026-03-06', 'active', 'Entertainment', '', 'Card', 0, '2026-02-05 13:29:37', NULL, NULL, NULL, '2026-02-05', NULL),
(47, 10, 'Strava', 11.99, 'USD', 'Monthly', '2026-01-04', '2026-02-04', 'archived', 'Personal', '', 'Card', 0, '2026-02-05 17:36:47', '2026-02-08 11:32:52', '2026-02-11 11:32:52', '', '2026-02-05', NULL),
(48, 10, 'Spotify', 11.99, 'USD', 'Monthly', '2026-01-06', '2026-03-06', 'archived', 'Entertainment', '', 'Card', 0, '2026-02-05 17:38:39', '2026-02-08 11:33:12', '2026-02-11 11:33:12', '', '2026-02-05', NULL),
(49, 10, 'Tinder', 100.00, 'USD', 'Monthly', '2026-01-06', '2026-03-06', 'archived', 'Personal', '', 'Card', 0, '2026-02-05 18:05:18', '2026-02-08 11:33:08', '2026-02-11 11:33:08', '', '2026-02-05', NULL),
(50, 10, 'Apple Music', 16.99, 'USD', 'Monthly', '2026-01-07', '2026-03-07', 'archived', 'Entertainment', '', 'Card', 0, '2026-02-06 04:19:41', '2026-02-08 11:33:15', '2026-02-11 11:33:15', '', '2026-02-06', NULL),
(52, 10, 'Midjourney', 10.00, 'USD', 'Monthly', '2026-01-09', '2026-02-09', 'archived', 'Work/Tools', NULL, 'Card', 0, '2026-02-08 11:17:48', '2026-02-08 11:32:55', '2026-02-11 11:32:55', '', '2026-02-08', '2026-02-10 17:00:33'),
(53, 10, 'Asana', 10.99, 'USD', 'Monthly', '2026-01-09', '2026-02-09', 'archived', 'Work/Tools', NULL, 'Card', 0, '2026-02-08 11:30:08', '2026-02-08 11:33:01', '2026-02-11 11:33:01', '', '2026-02-08', '2026-02-10 17:00:31'),
(54, 10, 'Apple TV+', 99.99, 'USD', 'Yearly', '2026-01-09', '2027-01-09', 'active', 'Entertainment', NULL, 'Card', 0, '2026-02-08 11:36:02', NULL, '2026-02-11 11:55:01', '', '2026-02-08', NULL),
(55, 10, 'Figma', 144.00, 'USD', 'Yearly', '2026-01-09', '2027-01-09', 'archived', 'Work/Tools', NULL, 'Card', 0, '2026-02-08 11:40:06', '2026-02-08 11:41:08', '2026-02-11 11:41:08', '', '2026-02-08', NULL),
(56, 10, 'NY Times', 25.00, 'USD', 'Monthly', '2026-01-09', '2026-02-09', 'active', 'Personal', NULL, 'Card', 0, '2026-02-08 11:40:30', NULL, NULL, '', '2026-02-08', '2026-02-10 17:10:35'),
(57, 10, 'DoorDash', 4.99, 'USD', 'Monthly', '2026-02-27', '2026-03-27', 'active', 'Utilities', NULL, 'Card', 0, '2026-02-08 11:54:52', NULL, '2026-02-11 11:54:57', '', '2026-02-08', NULL),
(58, 10, 'Netflix', 22.99, 'USD', 'Monthly', '2026-01-09', '2026-02-09', 'active', 'Entertainment', NULL, 'Card', 0, '2026-02-08 12:03:54', NULL, NULL, '', '2026-02-08', NULL),
(59, 10, 'Disney+', 159.99, 'USD', 'Yearly', '2026-02-05', '2027-02-05', 'active', 'Entertainment', NULL, 'Card', 0, '2026-02-08 12:11:37', NULL, NULL, '', '2026-02-08', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_tags`
--

CREATE TABLE `subscription_tags` (
  `subscription_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tag_name` varchar(50) DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT '#808080'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin') DEFAULT 'user',
  `security_question` varchar(255) NOT NULL DEFAULT 'What is your pet name?',
  `security_answer` varchar(255) NOT NULL DEFAULT 'dog',
  `profile_pic` varchar(255) DEFAULT 'default.png',
  `bio` text DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `budget_limit` decimal(10,2) DEFAULT 500.00,
  `full_name` varchar(100) DEFAULT '',
  `phone_number` varchar(20) DEFAULT '',
  `location` varchar(100) DEFAULT '',
  `status` varchar(20) DEFAULT 'active',
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `role`, `security_question`, `security_answer`, `profile_pic`, `bio`, `reset_token`, `token_expiry`, `budget_limit`, `full_name`, `phone_number`, `location`, `status`, `two_factor_enabled`, `two_factor_secret`) VALUES
(4, 'vishallll', 'vishalpiprotar7@gmail.com', 'vishallll@123', '2026-02-02 14:27:33', 'user', 'What is your favorite color?', 'red', 'default.png', NULL, NULL, NULL, 500.00, '', '', '', 'active', 0, NULL),
(5, 'hiten', 'hiten@gmail.com', '$2y$10$hPHpuPDLfBu7XFVlYHS.euDUVN7SiWE2674u07gBx/2UmjntLoUOe', '2026-02-03 02:32:50', 'user', 'What is your pet name?', 'dog', 'default.png', NULL, NULL, NULL, 500.00, '', '', '', 'banned', 0, NULL),
(6, 'divya', 'div@gmail.com', '$2y$10$I3/sBCmgAzihgbQtqnC7e.AyLyPXjPHPgjFvkkEeitxxhlsGG6UI.', '2026-02-03 02:55:46', 'user', 'What is your pet name?', 'sheru', 'default.png', NULL, '229205', '2026-02-08 11:06:28', 500.00, '', '', '', 'banned', 0, NULL),
(7, 'ronak', 'ronak@gmail.com', '$2y$10$ZWt3Fk9WOoZJjaG4mAyK2e9OASId4SeiW8ygiCgNg0qkXLptdaX8i', '2026-02-03 05:24:54', 'user', 'What city were you born in?', '2005', 'default.png', '', NULL, NULL, 500.00, 'ronak karena', '+918460237242', 'ahemdabad', 'banned', 0, NULL),
(9, 'admin', 'admin@subtrack.com', '$2y$10$TQdxV68qOFulB638JhAKweLzndolt6y2uC5ZmJ0FplbEZP7lXZHmG', '2026-02-03 06:32:05', 'admin', 'What is your pet name?', 'dog', 'default.png', NULL, NULL, NULL, 500.00, '', '', '', 'active', 0, NULL),
(10, 'justvishal', 'vishu@gmail.com', '$2y$10$g4ImD/alySDv4xA6V9NfoOQpYde3MbfQYIcFM3atV8DKKFltJENRu', '2026-02-03 14:25:17', 'user', 'What city were you born in?', 'bhanvad', 'user_10_1770130481.jpg', 'hello\r\n', NULL, NULL, 300.00, '', '', '', 'active', 0, NULL),
(11, 'vedant', 'vedant@gmail.com', '$2y$10$TFeRq4bvyXaIMRP77hKePOaMq.ZC0L/kDQCewMlM3ykFzV8gmNtIy', '2026-02-04 04:46:45', 'user', 'What is your favorite color?', 'black', 'default.png', NULL, NULL, NULL, 10.00, '', '', '', 'active', 0, NULL),
(12, 'janki', 'janki@gmail.com', '$2y$10$E0EuFHS35CEOyw0rBlfWsuSB63LiYTaMYhaSSeP75aBXj0TKY0uQK', '2026-02-05 11:57:47', 'user', 'What city were you born in?', '2000', 'default.png', NULL, NULL, NULL, 500.00, '', '', '', 'active', 0, NULL),
(13, 'Nobita', 'shizuka@gmail.com', '$2y$10$GGIYfxviDPmhWUKISBGUF.3WEic1p5rAFbt7m4k23HnrBgOvgt6Wm', '2026-02-05 13:25:44', 'user', 'What is your favorite color?', 'pink', 'user_13_1770298128.jpeg', NULL, NULL, NULL, 500.00, '', '', '', 'active', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_or_user` (`email_or_user`,`attempt_time`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_catalog`
--
ALTER TABLE `service_catalog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `subscription_tags`
--
ALTER TABLE `subscription_tags`
  ADD PRIMARY KEY (`subscription_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `service_catalog`
--
ALTER TABLE `service_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_tags`
--
ALTER TABLE `subscription_tags`
  ADD CONSTRAINT `subscription_tags_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
