/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50726
Source Host           : localhost:3306
Source Database       : test

Target Server Type    : MYSQL
Target Server Version : 50726
File Encoding         : 65001

Date: 2021-05-20 23:09:55
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for apy
-- ----------------------------
DROP TABLE IF EXISTS `apy`;
CREATE TABLE `apy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dex_btcb_usdt` varchar(255) DEFAULT NULL,
  `mdex_busd_usdt` varchar(255) DEFAULT NULL,
  `bzx_ibusd` varchar(255) DEFAULT NULL,
  `bzx_ibnb` varchar(255) DEFAULT NULL,
  `bzx_ieth` varchar(255) DEFAULT NULL,
  `bzx_ibtc` varchar(255) DEFAULT NULL,
  `bzx_iusdt` varchar(255) DEFAULT NULL,
  `cake_belt_bnb` varchar(255) DEFAULT NULL,
  `cake_eps_bnb` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of apy
-- ----------------------------
INSERT INTO `apy` VALUES ('1', '0.29960372089752543', '0.305520625144164', '0.13845655799793537', '0.10000000', '0.10000000', '0.10000000', '0.10000000', '0.10000000', '0.100000000');
