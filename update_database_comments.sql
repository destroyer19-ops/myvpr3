-- Create comments table for N-gage chat
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` varchar(255) NOT NULL,
  `time` varchar(255) NOT NULL,
  `property` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `xid` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
