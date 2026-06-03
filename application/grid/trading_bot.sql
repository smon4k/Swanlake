/*
 Navicat Premium Dump SQL

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 90500 (9.5.0)
 Source Host           : localhost:3306
 Source Schema         : trading_bot

 Target Server Type    : MySQL
 Target Server Version : 90500 (9.5.0)
 File Encoding         : 65001

 Date: 03/06/2026 05:50:42
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for g_account_histor_position
-- ----------------------------
DROP TABLE IF EXISTS `g_account_histor_position`;
CREATE TABLE `g_account_histor_position` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL COMMENT '用户id ',
  `amount` decimal(18,8) NOT NULL COMMENT '仓位最新值',
  `datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '变更时间',
  `sign_id` int NOT NULL COMMENT '关联信号ID',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- ----------------------------
-- Table structure for g_accounts
-- ----------------------------
DROP TABLE IF EXISTS `g_accounts`;
CREATE TABLE `g_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '账户名称',
  `exchange` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_passphrase` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `add_time` datetime NOT NULL,
  `status` int NOT NULL DEFAULT '1' COMMENT '状态 0：无效 1：有效	',
  `is_position` int NOT NULL COMMENT '是否持仓 1：持仓 0：不持仓',
  `financ_state` int NOT NULL DEFAULT '1' COMMENT '理财状态 1：购买理财 3：借贷 2： 只做理财',
  `auto_loan` int NOT NULL DEFAULT '0' COMMENT '是否开启自动借币0：未开启 1： 已开启',
  `total_balance` decimal(18,8) DEFAULT NULL COMMENT '账户余额',
  `balance_updated_at` datetime DEFAULT NULL COMMENT '余额更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- ----------------------------
-- Table structure for g_accounts_copy
-- ----------------------------
DROP TABLE IF EXISTS `g_accounts_copy`;
CREATE TABLE `g_accounts_copy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '账户名称',
  `exchange` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_secret` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `api_passphrase` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1' COMMENT '状态 0：无效 1：有效	',
  `is_position` int NOT NULL COMMENT '是否持仓 1：持仓 0：不持仓',
  `add_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `financ_state` int NOT NULL DEFAULT '1' COMMENT '理财状态 1：购买理财 3：借贷 2： 只做理财',
  `auto_loan` int NOT NULL DEFAULT '0' COMMENT '是否开启自动借币0：未开启 1： 已开启',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- ----------------------------
-- Table structure for g_config
-- ----------------------------
DROP TABLE IF EXISTS `g_config`;
CREATE TABLE `g_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int NOT NULL,
  `multiple` decimal(10,4) DEFAULT '3.0000' COMMENT '多空倍数',
  `position_percent` decimal(10,4) DEFAULT '0.8000' COMMENT '开仓比例(80%)',
  `total_position` decimal(20,4) DEFAULT '5000.0000' COMMENT '总仓位 USDT',
  `stop_profit_loss` decimal(10,4) DEFAULT '0.0070' COMMENT '止盈止损百分比',
  `grid_step` decimal(10,4) DEFAULT '0.0020' COMMENT '网格步长',
  `grid_percent_list` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT '网格比例配置',
  `max_position_list` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci COMMENT '币种最大仓位数配置',
  `commission_price_difference` decimal(10,2) DEFAULT '50.00',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- ----------------------------
-- Table structure for g_orders
-- ----------------------------
DROP TABLE IF EXISTS `g_orders`;
CREATE TABLE `g_orders` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '订单ID，唯一标识',
  `account_id` int NOT NULL COMMENT '账户ID，关联用户账户',
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '发生时间，记录订单的创建时间',
  `symbol` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '交易对，如 BTC-USDT',
  `position_group_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '分组id',
  `profit` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '利润',
  `order_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '交易所返回的订单ID',
  `clorder_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '自定义订单ID',
  `side` enum('buy','sell') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '订单方向：buy=买入，sell=卖出',
  `order_type` enum('limit','market','conditional','oco') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '订单类型：limit=限价单，market=市价单',
  `pos_side` enum('long','short') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'long' COMMENT '持仓方向 long：买入  short：卖出 ',
  `quantity` decimal(18,8) NOT NULL COMMENT '订单数量',
  `price` decimal(18,8) DEFAULT NULL COMMENT '委托价格（限价单适用）',
  `executed_price` decimal(18,8) DEFAULT NULL COMMENT '成交均价（如果已成交）',
  `status` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT 'live' COMMENT '订单状态 (live, partially_filled, filled, canceled, closed, order_failed)',
  `is_clopos` int NOT NULL DEFAULT '0' COMMENT '是否平仓0：未平仓 1：已平仓',
  `fill_time` datetime DEFAULT NULL COMMENT '成交时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '订单创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '订单最后更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_orders_recent_filled` (`account_id`,`symbol`,`status`,`updated_at`),
  KEY `idx_orders_by_symbol_status` (`account_id`,`symbol`,`status`,`order_type`)
) ENGINE=MyISAM AUTO_INCREMENT=4092 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='交易订单表，记录所有执行的交易订单';

-- ----------------------------
-- Table structure for g_quantify_account_details
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_account_details`;
CREATE TABLE `g_quantify_account_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `currency` varchar(100) DEFAULT NULL COMMENT '币种名称',
  `balance` decimal(18,10) DEFAULT NULL COMMENT '余额',
  `time` datetime DEFAULT NULL COMMENT '最新更新时间',
  `state` int DEFAULT '1' COMMENT '状态 0： 无效 1：有效',
  `valuation` decimal(18,10) DEFAULT NULL COMMENT 'USDT估值',
  `price` decimal(18,10) DEFAULT '0.0000000000' COMMENT '价格',
  `date` date DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id_2` (`account_id`,`currency`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='账户币种余额明细';

-- ----------------------------
-- Table structure for g_quantify_account_positions
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_account_positions`;
CREATE TABLE `g_quantify_account_positions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `currency` varchar(100) DEFAULT NULL COMMENT '币种名称',
  `mgn_mode` varchar(100) DEFAULT NULL COMMENT '保证金模式 cross：全仓\nisolated：逐仓\n',
  `pos_side` varchar(100) DEFAULT NULL COMMENT '持仓方向 long：双向持仓多头\nshort：双向持仓空头',
  `pos` decimal(18,10) DEFAULT '0.0000000000' COMMENT '持仓数量',
  `avg_px` decimal(18,10) DEFAULT '0.0000000000' COMMENT '开仓均价',
  `margin_balance` decimal(18,10) DEFAULT NULL COMMENT '保证金余额',
  `margin_ratio` decimal(18,10) DEFAULT NULL COMMENT '保证金率\n',
  `time` datetime DEFAULT NULL COMMENT '最新更新时间',
  `state` int DEFAULT '1' COMMENT '状态 0： 无效 1：有效',
  `date` date DEFAULT NULL COMMENT '时间',
  `upl` decimal(18,10) DEFAULT NULL COMMENT '未实现收益\n',
  `upl_ratio` decimal(18,10) DEFAULT NULL COMMENT '未实现收益率\n',
  `mark_px` decimal(18,10) DEFAULT '0.0000000000' COMMENT '标记价格',
  `max_upl_rate` decimal(18,10) DEFAULT '0.0000000000' COMMENT '最大收益率',
  `min_upl_rate` decimal(18,10) DEFAULT '0.0000000000' COMMENT '最小收益率',
  `rate_average` decimal(18,10) DEFAULT '0.0000000000' COMMENT '最大最小平均',
  `trade_id` varchar(100) DEFAULT NULL COMMENT '最新成交ID\n',
  `u_time` varchar(100) DEFAULT NULL COMMENT '最近一次持仓更新时间，Unix时间戳的毫秒数格式，如 1597026383085\n',
  `c_time` varchar(100) DEFAULT NULL COMMENT '持仓创建时间，Unix时间戳的毫秒数格式，如 1597026383085\n',
  `type` int DEFAULT '1' COMMENT '平仓状态 1：待平仓 2：已平仓',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id` (`account_id`,`currency`,`trade_id`),
  KEY `state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='账户币种持仓信息';

-- ----------------------------
-- Table structure for g_quantify_account_positions_rate
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_account_positions_rate`;
CREATE TABLE `g_quantify_account_positions_rate` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `currency` varchar(100) DEFAULT NULL COMMENT '币种名称',
  `time` datetime DEFAULT NULL COMMENT '最新更新时间',
  `pos_side` varchar(100) DEFAULT NULL COMMENT '持仓方向 long：双向持仓多头\nshort：双向持仓空头',
  `rate_num` decimal(18,10) NOT NULL DEFAULT '0.0000000000' COMMENT '收益率',
  `trade_id` varchar(100) DEFAULT NULL COMMENT '最新成交ID\n',
  `u_time` varchar(100) DEFAULT NULL COMMENT '最近一次持仓更新时间，Unix时间戳的毫秒数格式，如 1597026383085\n',
  `c_time` varchar(100) DEFAULT NULL COMMENT '持仓创建时间，Unix时间戳的毫秒数格式，如 1597026383085\n',
  `avg_price` decimal(18,10) DEFAULT '0.0000000000' COMMENT '标记价格或者平仓价格',
  `opening_price` decimal(18,10) DEFAULT '0.0000000000' COMMENT '开仓价格',
  `pos_id` varchar(100) DEFAULT NULL COMMENT '持仓id',
  `upl` decimal(18,10) DEFAULT NULL COMMENT '收益',
  `mark_price` decimal(18,10) DEFAULT '0.0000000000' COMMENT '标记价格',
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`,`currency`,`trade_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='账户币种持仓信息 收益率历史记录表';

-- ----------------------------
-- Table structure for g_quantify_account_trade_okx_details
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_account_trade_okx_details`;
CREATE TABLE `g_quantify_account_trade_okx_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `currency` varchar(100) DEFAULT NULL COMMENT '币种名称',
  `symbol` varchar(100) DEFAULT NULL COMMENT '交易对 产品 ID\n',
  `trade_id` bigint DEFAULT NULL COMMENT 'Trade ID 最新成交id',
  `order_id` bigint DEFAULT NULL COMMENT '订单id',
  `price` decimal(36,10) DEFAULT NULL COMMENT '最新成交价格',
  `qty` decimal(36,10) DEFAULT NULL COMMENT '最新成交量',
  `quote_qty` decimal(36,10) DEFAULT '0.0000000000' COMMENT '成交金额',
  `trade_time` datetime DEFAULT NULL COMMENT '成交时间',
  `side` varchar(100) DEFAULT NULL COMMENT '订单方向 buy：买 sell：卖',
  `bill_id` bigint DEFAULT NULL COMMENT '账单 ID',
  `time` datetime DEFAULT NULL COMMENT '更新时间',
  `quote_total_price` decimal(36,10) DEFAULT '0.0000000000' COMMENT '成交总价',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id` (`account_id`,`currency`,`bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='获取账户交易对交易明细数据';

-- ----------------------------
-- Table structure for g_quantify_dividend_record
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_dividend_record`;
CREATE TABLE `g_quantify_dividend_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `amount` decimal(36,10) DEFAULT NULL COMMENT '数量',
  `total_profit` decimal(36,10) DEFAULT NULL COMMENT '总分润',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `time` datetime DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='分润记录表';

-- ----------------------------
-- Table structure for g_quantify_equity_monitoring
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_equity_monitoring`;
CREATE TABLE `g_quantify_equity_monitoring` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `date` date DEFAULT NULL COMMENT '日期',
  `principal` decimal(36,10) DEFAULT '0.0000000000' COMMENT '累计本金',
  `total_balance` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总结余',
  `yubibao_balance` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '理财余额',
  `daily_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '日利润',
  `daily_profit_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '日利润率',
  `average_day_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '平均日利率',
  `average_year_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '平均年利率',
  `profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '利润',
  `profit_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '利润率',
  `price` decimal(36,10) DEFAULT '0.0000000000' COMMENT '币价',
  `up_time` datetime DEFAULT NULL COMMENT '更新时间',
  `total_share_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总分润',
  `total_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总利润',
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`,`account_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='量化账户监控数据统计\n';

-- ----------------------------
-- Table structure for g_quantify_equity_monitoring_total
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_equity_monitoring_total`;
CREATE TABLE `g_quantify_equity_monitoring_total` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL COMMENT '日期',
  `principal` decimal(36,10) DEFAULT '0.0000000000' COMMENT '累计本金',
  `total_balance` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总结余',
  `daily_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '日利润',
  `daily_profit_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '日利润率',
  `average_day_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '平均日利率',
  `average_year_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '平均年利率',
  `profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '利润',
  `profit_rate` decimal(36,10) DEFAULT '0.0000000000' COMMENT '利润率',
  `price` decimal(36,10) DEFAULT '0.0000000000' COMMENT '币价',
  `up_time` datetime DEFAULT NULL COMMENT '更新时间',
  `total_share_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总分润',
  `total_profit` decimal(36,10) DEFAULT '0.0000000000' COMMENT '总利润',
  `yubibao_balance` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '理财余额',
  PRIMARY KEY (`id`),
  UNIQUE KEY `date` (`date`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='量化账户监控数据统计\n';

-- ----------------------------
-- Table structure for g_quantify_inout_gold
-- ----------------------------
DROP TABLE IF EXISTS `g_quantify_inout_gold`;
CREATE TABLE `g_quantify_inout_gold` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_id` int DEFAULT NULL COMMENT '账户id',
  `amount` decimal(10,4) DEFAULT NULL COMMENT '数量',
  `type` int DEFAULT NULL COMMENT '类型 1：入金 2：出金',
  `total_balance` decimal(10,4) DEFAULT NULL COMMENT '总结余',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `time` datetime DEFAULT NULL COMMENT '时间',
  `bill_id` varchar(100) DEFAULT NULL COMMENT '账单id',
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=COMPACT COMMENT='币安 出入金记录表';

-- ----------------------------
-- Table structure for g_signals
-- ----------------------------
DROP TABLE IF EXISTS `g_signals`;
CREATE TABLE `g_signals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pair_id` int NOT NULL DEFAULT '0' COMMENT '配对id',
  `name` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL COMMENT '策略名字',
  `account_id` int NOT NULL DEFAULT '0' COMMENT '账户id',
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `symbol` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `direction` enum('long','short') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'long：做多 short：做空',
  `price` decimal(18,8) NOT NULL COMMENT '信号价格',
  `size` int NOT NULL,
  `position_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '进出场时间',
  `loss_profit` decimal(18,8) DEFAULT '0.00000000' COMMENT '盈亏（正为盈利，负为亏损）	',
  `count_profit_loss` decimal(18,8) DEFAULT '0.00000000' COMMENT '总盈亏',
  `stage_profit_loss` decimal(18,8) DEFAULT NULL COMMENT '阶段盈亏',
  `status` enum('pending','processed','processing','failed') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'pending',
  `success_accounts` json DEFAULT NULL COMMENT '成功处理的账户列表',
  `failed_accounts` json DEFAULT NULL COMMENT '失败的账户列表（需要恢复）',
  `last_update_time` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '最后更新时间（用于超时检测）',
  `signal_source` varchar(32) COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'api' COMMENT 'api/leader_copy/manual',
  `leader_account_id` int DEFAULT NULL COMMENT 'Leader g_accounts.id',
  `leader_bill_id` varchar(64) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'OKX billId',
  `leader_ord_id` varchar(64) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'OKX ordId',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leader_bill` (`leader_account_id`,`leader_bill_id`),
  KEY `symbol` (`symbol`),
  KEY `direction` (`direction`,`size`),
  KEY `idx_signal_processing` (`status`,`name`,`last_update_time`)
) ENGINE=MyISAM AUTO_INCREMENT=553 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- ----------------------------
-- Table structure for g_strategy
-- ----------------------------
DROP TABLE IF EXISTS `g_strategy`;
CREATE TABLE `g_strategy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '策略名字',
  `loss_number` int NOT NULL DEFAULT '0' COMMENT '亏损次数',
  `max_position` int NOT NULL DEFAULT '0' COMMENT '最大仓位',
  `min_position` int NOT NULL DEFAULT '0' COMMENT '最小仓位',
  `count_profit_loss` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '总盈亏',
  `stage_profit_loss` decimal(18,8) DEFAULT '0.00000000' COMMENT '阶段盈亏',
  `stop_loss_percent` decimal(18,8) DEFAULT '0.00000000' COMMENT '止损比例',
  `open_coefficient` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '开仓系数',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` int NOT NULL COMMENT '状态 1：有效 0：无效',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Table structure for g_strategy_trade
-- ----------------------------
DROP TABLE IF EXISTS `g_strategy_trade`;
CREATE TABLE `g_strategy_trade` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `strategy_name` varchar(64) NOT NULL COMMENT '策略标识',
  `open_time` datetime NOT NULL COMMENT '开仓时间',
  `open_side` enum('buy','sell') NOT NULL COMMENT '开仓方向',
  `open_price` decimal(18,8) NOT NULL COMMENT '开仓价格',
  `close_time` datetime DEFAULT NULL COMMENT '平仓时间',
  `close_side` enum('buy','sell') DEFAULT NULL COMMENT '平仓方向',
  `close_price` decimal(18,8) DEFAULT NULL COMMENT '平仓价格',
  `loss_profit` decimal(18,8) DEFAULT '0.00000000' COMMENT '利润（正为盈利，负为亏损）',
  `count_profit_loss` decimal(18,8) NOT NULL DEFAULT '0.00000000' COMMENT '总盈亏',
  `symbol` varchar(32) NOT NULL COMMENT '交易对，例如 BTC/USDT',
  `exchange` varchar(32) NOT NULL COMMENT '交易所标识，如 binance, okx',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `status` int NOT NULL DEFAULT '0' COMMENT '是否平仓',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci ROW_FORMAT=COMPACT COMMENT='策略交易记录';

-- ----------------------------
-- Table structure for tb_transaction_sync_log
-- ----------------------------
DROP TABLE IF EXISTS `tb_transaction_sync_log`;
CREATE TABLE `tb_transaction_sync_log` (
  `id` bigint NOT NULL COMMENT '主键ID（雪花ID）',
  `oid` varchar(64) NOT NULL COMMENT '组织ID',
  `platform` varchar(32) NOT NULL COMMENT '平台类型',
  `sync_type` tinyint NOT NULL COMMENT '同步类型：1=增量同步，2=历史回溯',
  `query_type` tinyint NOT NULL COMMENT '查询类型：1=下单时间，2=完成时间，3=更新时间',
  `start_time` datetime NOT NULL COMMENT '同步的时间范围开始',
  `end_time` datetime NOT NULL COMMENT '同步的时间范围结束',
  `status` varchar(32) NOT NULL COMMENT '同步状态：pending/running/success/failed/partial',
  `total_hours` int NOT NULL DEFAULT '0' COMMENT '总小时数',
  `success_hours` int NOT NULL DEFAULT '0' COMMENT '成功小时数',
  `failed_hours` int NOT NULL DEFAULT '0' COMMENT '失败小时数',
  `synced_count` int NOT NULL DEFAULT '0' COMMENT '同步成功的订单数',
  `updated_count` int NOT NULL DEFAULT '0' COMMENT '更新的订单数',
  `failed_count` int NOT NULL DEFAULT '0' COMMENT '失败的订单数',
  `api_call_count` int NOT NULL DEFAULT '0' COMMENT 'API调用次数',
  `duration_ms` int NOT NULL DEFAULT '0' COMMENT '执行耗时（毫秒）',
  `error_message` text COMMENT '错误信息（JSON数组格式）',
  `started_at` datetime DEFAULT NULL COMMENT '开始执行时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_oid_platform_time` (`oid`,`platform`,`start_time`),
  KEY `idx_status_started` (`status`,`started_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='交易同步日志表';

SET FOREIGN_KEY_CHECKS = 1;
