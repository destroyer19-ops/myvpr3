-- Create rate_limits table for basic throttling
CREATE TABLE `rate_limits` (
  `rl_key` varchar(191) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `reset_at` datetime NOT NULL,
  PRIMARY KEY (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
