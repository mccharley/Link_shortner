-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2020 at 07:52 PM
-- Server version: 10.4.8-MariaDB
-- PHP Version: 7.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `linksdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `link_mapping`
--

CREATE TABLE `link_mapping` (
  `entry` int(100) NOT NULL,
  `long_link` varchar(500) NOT NULL,
  `short_link_ID` varchar(6) NOT NULL,
  `op_time` datetime NOT NULL,
  `entry_source_ip` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='long to short link mapping';

--
-- Dumping data for table `link_mapping`
--

INSERT INTO `link_mapping` (`entry`, `long_link`, `short_link_ID`, `op_time`, `entry_source_ip`) VALUES
(71, 'google.com', 're0772', '2019-12-17 18:45:59', '216.58.223.238'),
(1, 'https://rarbgaccess.org/torrents.php?search=Gigantosaurus+S01', '60u62K', '2019-11-25 14:13:56', '104.31.17.3'),
(3, 'https://stackoverflow.com/questions/17058349/mysqli-object-returned-instead-of-result', '6679c5', '2019-11-26 18:13:36', '151.101.1.69'),
(29, 'https://stackoverflow.com/questions/42186938/sql-syntax-error-mariadb-server-version-for-the-right-syntax-to-use-near-hotma', '9QZ554', '2019-11-26 18:49:31', '151.101.1.69'),
(28, 'https://www.codementor.io/codementorteam/how-to-build-app-from-scratch-beginner-programmer-7z0atq56w', '6mN161', '2019-11-26 18:39:35', '34.237.57.234'),
(30, 'https://www.youtube.com/watch?v=X6xY4Ouydas&list=PL0eyrZgxdwhypQiZnYXM7z7-OTkcMgGPh&index=4', '1CuSzs', '2019-11-26 18:57:41', '216.58.223.238'),
(72, 'stackoverflow.com/questions/35342013/calling-method-from-another-class-in-php', 'Sv37uJ', '2019-12-18 00:46:52', '151.101.129.69'),
(32, 'www.gloworld.com/ng/support/gloworld-shops', 'Bp8O8b', '2019-12-17 15:19:38', '197.211.49.5'),
(77, 'yahoomail.com', 'wdMkOT', '2019-12-18 01:17:03', '192.168.106.1');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `link_mapping`
--
ALTER TABLE `link_mapping`
  ADD UNIQUE KEY `long_link` (`long_link`),
  ADD UNIQUE KEY `short_link_id` (`short_link_ID`),
  ADD KEY `entry` (`entry`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `link_mapping`
--
ALTER TABLE `link_mapping`
  MODIFY `entry` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
