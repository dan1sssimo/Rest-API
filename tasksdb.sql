-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 21, 2022 at 07:01 PM
-- Server version: 5.7.24
-- PHP Version: 8.0.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tasksdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblimages`
--

CREATE TABLE `tblimages` (
  `id` bigint(20) NOT NULL COMMENT 'Image ID Number - Primary Key',
  `title` varchar(255) NOT NULL COMMENT 'Image Title',
  `filename` varchar(30) NOT NULL COMMENT 'Image Filename',
  `mimetype` varchar(255) NOT NULL COMMENT 'Image Mimetype ( image/png )',
  `taskid` bigint(20) NOT NULL COMMENT 'Task ID Number = Foreing Key'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table to store task images';

--
-- Dumping data for table `tblimages`
--

INSERT INTO `tblimages` (`id`, `title`, `filename`, `mimetype`, `taskid`) VALUES
(5, 'Image 1 Title', 'Name.jpg', 'image/jpeg', 12),
(6, 'Image 1 Title1', 'Name1.jpg', 'image/jpeg', 12);

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

CREATE TABLE `tblsessions` (
  `id` bigint(20) NOT NULL COMMENT 'Session ID',
  `userid` bigint(20) NOT NULL COMMENT 'User ID',
  `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
  `accesstokenexpiry` datetime NOT NULL COMMENT 'Access Token Expiry DateTime',
  `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Refresh Token',
  `refreshtokenexpiry` datetime NOT NULL COMMENT 'Refresh Token Expiry DateTime'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Sessions Table';

--
-- Dumping data for table `tblsessions`
--

INSERT INTO `tblsessions` (`id`, `userid`, `accesstoken`, `accesstokenexpiry`, `refreshtoken`, `refreshtokenexpiry`) VALUES
(2, 2, 'OTlhNGZlNjQ4MWJiOGZhOGRjNDU5YTAwNDE5OWFiNjUxMDUyNDA4MzU4ZDFjMjI5MTY2ODUxNjcwMg==', '2022-11-15 15:11:42', 'NjJhZTAwOTRhODY0ZWExNTdhYjMzNmY2NWY1MjM2MmZiNjMzYjM1NmE5NTU4YmVjMTY2ODUxNjcwMg==', '2022-11-29 14:51:42'),
(3, 2, 'NzJmM2Y3MDk1NmJhZmRmMzNlMWI0MjA4MmY5NTUxYTFiNjVlNWFlYmU1NjI3ODViMTY2OTAyODgyMQ==', '2022-11-21 13:27:01', 'MzFjOTkyNjA3MmM5NDgyNjg5N2M3Yjg3YjAyNzE1ODUwNWI0NzhkMWQ4Mzk4ZmJiMTY2OTAyODgyMQ==', '2022-12-05 13:07:01'),
(4, 1, 'NzI0NDY2NzE3MzM3YzBlZmE4ZmJlOTJlMDQ5ZWEwZTg0MzdjOWIxNDk0ZmIzNzI5MTY2OTAyODk2NQ==', '2022-11-21 13:29:25', 'MTk5MTEzM2M5OTI2NTMzN2U3NTk2MjkwZjcxMmYwZjYwZWQ3NjZhMGU1NGMyMjg1MTY2OTAyODk2NQ==', '2022-12-05 13:09:25'),
(5, 1, 'NmI5ZmU1ZTMxMWUzOWQyNjk1ZjNiZTE1ZTEwNmU2ZjdhNGM0NmI3YmE5MjRjODYyMTY2OTAzNTM5Mw==', '2022-11-21 15:16:33', 'Y2E3ZDExNTU2MGI2YjZmZWQ3NWIxZjc1MjY1OThlMDRjNjJjOWE4MTkzMGVlNjI1MTY2OTAzNTM5Mw==', '2022-12-05 14:56:33'),
(6, 1, 'ZWQxZGEyZWM1N2VlM2I3YmFkMTkwMWViMjQyYTdiMTczZmZiNmQ1NzBlMWRkZWZhMTY2OTAzNjkzMw==', '2022-11-21 15:42:13', 'ZTkzZDUzMTMzNjA2NTJjNzBmZjI2NzJlZTY4OTM3ZWY2MTVkMzZmZDRhZTBhODZmMTY2OTAzNjkzMw==', '2022-12-05 15:22:13'),
(7, 1, 'OTYyZDQ0ZjNmZTQ2NDUyODZlYTEyYmY0NDBiZWE5ZGQ0Y2EzNzU4OWRiMDk0MTA2MTY2OTA1Mzc5Ng==', '2022-11-21 20:23:16', 'Y2ZkZGY5ZjJjOWRlNDA4YTJkNTAxMzJmYTFkZGI4MWE5MDQ4NDc5Yzc3MTdiMzJhMTY2OTA1Mzc5Ng==', '2022-12-05 20:03:16');

-- --------------------------------------------------------

--
-- Table structure for table `tbltasks`
--

CREATE TABLE `tbltasks` (
  `id` bigint(20) NOT NULL COMMENT 'Task ID - Primary Key',
  `title` varchar(255) NOT NULL COMMENT 'Task Title',
  `description` mediumtext COMMENT 'Task Description',
  `deadline` datetime DEFAULT NULL COMMENT 'Task Deadline',
  `completed` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Task Completed Status',
  `userid` bigint(20) NOT NULL COMMENT 'User ID of owner of task'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Tasks table';

--
-- Dumping data for table `tbltasks`
--

INSERT INTO `tbltasks` (`id`, `title`, `description`, `deadline`, `completed`, `userid`) VALUES
(12, 'Danissimo eat', NULL, NULL, 'Y', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
  `id` bigint(20) NOT NULL COMMENT 'User ID',
  `fullname` varchar(255) NOT NULL COMMENT 'Users FullName',
  `username` varchar(255) NOT NULL COMMENT 'Users UserName',
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Users Password',
  `useractive` enum('N','Y') NOT NULL DEFAULT 'Y' COMMENT 'Is User Active',
  `loginattempts` int(1) NOT NULL DEFAULT '0' COMMENT 'Attempts to log in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Users Table';

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `fullname`, `username`, `password`, `useractive`, `loginattempts`) VALUES
(1, 'Danylo Savchenko', 'danissimo', '$2y$10$8gWabqanHe.sDlZ/s/aj4uAnVz1TwO2ucKLGymF24zt07oD7keBfy', 'Y', 0),
(2, 'Sofia Kabachok', 'kabachok2003', '$2y$10$qvMoXp1IJMX3dtuyWyOnYeITCft8yHRvYOGjel5KykwKa9IMfZcs.', 'Y', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblimages`
--
ALTER TABLE `tblimages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `filenamefortaskid` (`taskid`,`filename`);

--
-- Indexes for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accesstoken` (`accesstoken`),
  ADD UNIQUE KEY `refreshtoken` (`refreshtoken`),
  ADD KEY `sessionuserid_fk` (`userid`);

--
-- Indexes for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taskuserid_fk` (`userid`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblimages`
--
ALTER TABLE `tblimages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Image ID Number - Primary Key', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tblsessions`
--
ALTER TABLE `tblsessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Session ID', AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbltasks`
--
ALTER TABLE `tbltasks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Task ID - Primary Key', AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'User ID', AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblimages`
--
ALTER TABLE `tblimages`
  ADD CONSTRAINT `imagetasksid_fk` FOREIGN KEY (`taskid`) REFERENCES `tbltasks` (`id`);

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);

--
-- Constraints for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD CONSTRAINT `taskuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
