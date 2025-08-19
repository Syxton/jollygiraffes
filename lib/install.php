<?php
// --
// -- Table structure for table `accounts`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `accounts` (
  `aid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `password` varchar(4) COLLATE utf8_unicode_ci NOT NULL,
  `meal_status` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'paid',
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`aid`),
  KEY `password` (`password`,`admin`),
  KEY `deleted` (`deleted`),
  KEY `meal_status` (`meal_status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Necessary data for table `accounts`
// --

$SQL = "
INSERT INTO `accounts` (`aid`, `name`, `password`, `meal_status`, `admin`, `deleted`) VALUES
(1, 'Admin', '1234', 'none', 1, 0);";

execute_db_sql($SQL);


// --
// -- Table structure for table `activity`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `activity` (
  `actid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` tinyint(4) NOT NULL,
  `aid` int(11) NOT NULL DEFAULT '0',
  `chid` int(11) NOT NULL,
  `cid` int(11) NOT NULL DEFAULT '0',
  `evid` int(11) NOT NULL,
  `tag` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `timelog` int(11) NOT NULL,
  PRIMARY KEY (`actid`),
  KEY `chid` (`chid`,`tag`,`timelog`),
  KEY `eid` (`evid`),
  KEY `cid` (`cid`),
  KEY `pid` (`pid`),
  KEY `aid` (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `billing`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT '0',
  `aid` int(11) NOT NULL DEFAULT '0',
  `fromdate` int(11) NOT NULL DEFAULT '0',
  `todate` int(11) NOT NULL DEFAULT '0',
  `owed` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `receipt` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`,`aid`,`fromdate`,`todate`,`owed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `billing_override`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `billing_override` (
  `oid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `aid` int(11) NOT NULL,
  `bill_by` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `perday` int(11) DEFAULT NULL,
  `fulltime` int(11) DEFAULT NULL,
  `minimumactive` int(11) DEFAULT NULL,
  `minimuminactive` int(11) DEFAULT NULL,
  `vacation` int(11) DEFAULT NULL,
  `multiple_discount` int(11) DEFAULT NULL,
  `consider_full` int(11) DEFAULT NULL,
  `discount_rule` int(11) DEFAULT NULL,
  `payahead` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`oid`),
  UNIQUE KEY `oid` (`oid`),
  KEY `pid` (`pid`,`aid`),
  KEY `payahead` (`payahead`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `billing_payments`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `billing_payments` (
  `payid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `aid` int(11) NOT NULL,
  `payment` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `timelog` int(11) NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`payid`),
  KEY `billid` (`pid`,`aid`,`payment`),
  KEY `timelog` (`timelog`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `billing_perchild`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `billing_perchild` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT '0',
  `chid` int(11) NOT NULL DEFAULT '0',
  `fromdate` int(11) NOT NULL DEFAULT '0',
  `todate` int(11) NOT NULL DEFAULT '0',
  `bill` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `receipt` text COLLATE utf8_unicode_ci NOT NULL,
  `exempt` int(1) NOT NULL DEFAULT '0',
  `days_attending` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chid` (`chid`,`fromdate`,`todate`,`bill`),
  KEY `pid` (`pid`),
  KEY `exempt` (`exempt`),
  KEY `enrollment` (`days_attending`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `children`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `children` (
  `chid` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `first` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `last` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `sex` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `birthdate` int(11) NOT NULL DEFAULT '0',
  `grade` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`chid`),
  KEY `aid` (`aid`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `contacts`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `contacts` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `first` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `last` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `relation` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `primary_address` tinyint(1) NOT NULL DEFAULT '0',
  `home_address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone1` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `phone2` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `phone3` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `employer` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `employer_address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone4` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `hours` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `emergency` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`),
  KEY `aid` (`aid`,`emergency`),
  KEY `deleted` (`deleted`),
  KEY `primary_address` (`primary_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `documents`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `documents` (
  `did` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT '0',
  `cid` int(11) NOT NULL DEFAULT '0',
  `actid` int(11) NOT NULL DEFAULT '0',
  `chid` int(11) NOT NULL,
  `tag` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `filename` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `timelog` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`did`),
  KEY `chid` (`chid`),
  KEY `aid` (`aid`,`cid`,`actid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `documents_tags`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `documents_tags` (
  `tag` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `textcolor` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'black',
  `color` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'silver',
  `title` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Dumping data for table `documents_tags`
// --

$SQL = "
INSERT INTO `documents_tags` (`tag`, `textcolor`, `color`, `title`) VALUES
('avatar', 'black', 'silver', 'avatar');";

execute_db_sql($SQL);


// --
// -- Table structure for table `employee`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `employee` (
  `employeeid` int(11) NOT NULL AUTO_INCREMENT,
  `first` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` int(4) NOT NULL,
  `deleted` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`employeeid`),
  KEY `pin` (`password`),
  KEY `deleted` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `employee_activity`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `employee_activity` (
  `actid` int(11) NOT NULL AUTO_INCREMENT,
  `employeeid` int(11) NOT NULL,
  `evid` int(11) NOT NULL,
  `tag` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `timelog` int(11) NOT NULL,
  PRIMARY KEY (`actid`),
  KEY `chid` (`tag`,`timelog`),
  KEY `eid` (`evid`),
  KEY `pid` (`employeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);

// --
// -- Table structure for table `employee_timecard`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `employee_timecard` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employeeid` int(11) NOT NULL,
  `fromdate` int(11) NOT NULL,
  `todate` int(11) NOT NULL,
  `hours` float NOT NULL,
  `hours_override` float NOT NULL DEFAULT '0',
  `wage` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employeeid` (`employeeid`,`fromdate`,`todate`,`hours`,`wage`),
  KEY `hours_override` (`hours_override`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `employee_wage`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `employee_wage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employeeid` int(11) NOT NULL,
  `wage` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `dategiven` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employeeid` (`employeeid`,`wage`,`dategiven`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `enrollments`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `enrollments` (
  `eid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `chid` int(11) NOT NULL,
  `days_attending` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `exempt` tinyint(1) NOT NULL DEFAULT '0',
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eid`),
  KEY `pid` (`pid`,`chid`),
  KEY `deleted` (`deleted`),
  KEY `days_attending` (`days_attending`),
  KEY `exempt` (`exempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `events`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `events` (
  `evid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `tag` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `sort` int(11) NOT NULL,
  PRIMARY KEY (`evid`),
  KEY `etag` (`tag`,`sort`),
  KEY `pid` (`pid`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Dumping data for table `events`
// --

$SQL = "
INSERT INTO `events` (`evid`, `pid`, `tag`, `sort`) VALUES
(1, 0, 'in', 1),
(2, 0, 'out', 2);";

execute_db_sql($SQL);


// --
// -- Table structure for table `events_required_notes`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `events_required_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evid` int(11) NOT NULL,
  `rnid` int(11) NOT NULL,
  `sort` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `evid` (`evid`,`rnid`),
  KEY `order` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `events_tags`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `events_tags` (
  `tag` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `textcolor` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'black',
  `color` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'silver',
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Dumping data for table `events_tags`
// --

$SQL = "
INSERT INTO `events_tags` (`tag`, `title`, `textcolor`, `color`) VALUES
('in', 'Check In', 'green', 'white'),
('out', 'Check Out', 'red', 'white');";

execute_db_sql($SQL);


// --
// -- Table structure for table `notes`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `notes` (
  `nid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT '0',
  `aid` int(11) NOT NULL DEFAULT '0',
  `cid` int(11) NOT NULL DEFAULT '0',
  `actid` int(11) NOT NULL DEFAULT '0',
  `chid` int(11) NOT NULL DEFAULT '0',
  `employeeid` int(11) NOT NULL DEFAULT '0',
  `rnid` int(11) NOT NULL DEFAULT '0',
  `tag` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `note` text COLLATE utf8_unicode_ci NOT NULL,
  `data` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `timelog` int(11) NOT NULL,
  `notify` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`nid`),
  KEY `chid` (`chid`),
  KEY `aid` (`aid`,`cid`,`actid`),
  KEY `data` (`data`),
  KEY `rnid` (`rnid`),
  KEY `pid` (`pid`),
  KEY `notify` (`notify`),
  KEY `employeeid` (`employeeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `notes_required`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `notes_required` (
  `rnid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `tag` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `question_type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rnid`),
  KEY `type` (`type`,`tag`),
  KEY `deleted` (`deleted`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `notes_tags`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `notes_tags` (
  `tag` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8_unicode_ci NOT NULL,
  `textcolor` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'black',
  `color` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'silver',
  UNIQUE KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Dumping data for table `notes_tags`
// --

$SQL = "
INSERT INTO `notes_tags` (`tag`, `title`, `textcolor`, `color`) VALUES
('account', 'Account ', '#ccffff', '#339966'),
('behavior', 'Behavior', '#000000', '#C0C0C0'),
('injury', 'Injury', '#ffffff', '#993300'),
('medical', 'Medical', '#993300', '#ffffff'),
('parent_inquiry', 'Parent Inquiry', '#ffffff', '#00ccff'),
('request', 'Request', '#ffffff', '#333399');";

execute_db_sql($SQL);


// --
// -- Table structure for table `programs`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `programs` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `timeopen` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `timeclosed` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(4) NOT NULL DEFAULT '0',
  `active` tinyint(4) NOT NULL DEFAULT '0',
  `perday` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `fulltime` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `minimumactive` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `minimuminactive` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `vacation` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `multiple_discount` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `consider_full` tinyint(1) NOT NULL DEFAULT '5',
  `bill_by` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'enrollment',
  `discount_rule` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `payahead` tinyint(1) NOT NULL DEFAULT '0',
  `fein` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`pid`),
  KEY `deleted` (`deleted`),
  KEY `active` (`active`),
  KEY `perday` (`perday`,`fulltime`),
  KEY `vacation` (`vacation`),
  KEY `minimum` (`minimumactive`),
  KEY `multiple_discount` (`multiple_discount`),
  KEY `consider_full` (`consider_full`),
  KEY `bill_by` (`bill_by`),
  KEY `discount_rule` (`discount_rule`),
  KEY `minimumactive` (`minimumactive`),
  KEY `payahead` (`payahead`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Table structure for table `version`
// --

$SQL = "
CREATE TABLE IF NOT EXISTS `version` (
  `version` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  KEY `version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

execute_db_sql($SQL);


// --
// -- Dumping data for table `version`
// --

$SQL = "INSERT INTO `version` (`version`) VALUES('2020022000');";

execute_db_sql($SQL);
