-- 为 g_signals 增加 partial 状态，用于表示主流程结束但仍有失败账户待恢复。
ALTER TABLE `g_signals`
  MODIFY `status` enum('pending','processing','partial','processed','failed') COLLATE utf8_unicode_ci DEFAULT 'pending';

-- 为账户增加冻结状态字段，命中硬错误后可直接跳过后续自动开仓。
ALTER TABLE `g_accounts`
  ADD COLUMN `trade_status` enum('normal','blocked') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '交易状态 normal:正常 blocked:冻结' AFTER `auto_loan`,
  ADD COLUMN `trade_block_reason` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '冻结原因' AFTER `trade_status`,
  ADD COLUMN `trade_blocked_at` datetime DEFAULT NULL COMMENT '冻结时间' AFTER `trade_block_reason`;

-- 新增失败账户恢复任务表，按 signal_id + account_id 维度记录恢复进度。
CREATE TABLE IF NOT EXISTS `g_signal_recovery_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `signal_id` int(11) NOT NULL COMMENT '信号ID',
  `account_id` int(11) NOT NULL COMMENT '账户ID',
  `strategy_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL COMMENT '策略名',
  `symbol` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '币种',
  `direction` enum('long','short') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '方向',
  `signal_type` enum('open','close') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'open' COMMENT '信号类型',
  `signal_price` decimal(18,8) DEFAULT NULL COMMENT '信号价格',
  `signal_size` int(3) DEFAULT NULL COMMENT '信号size',
  `signal_lev` decimal(10,4) NOT NULL DEFAULT '1.0000' COMMENT '信号仓位比例',
  `status` enum('pending','retrying','success','failed','blocked') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '恢复状态',
  `retry_count` int(11) NOT NULL DEFAULT '0' COMMENT '重试次数',
  `max_retry_count` int(11) NOT NULL DEFAULT '3' COMMENT '最大重试次数',
  `first_failed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '首次失败时间',
  `last_retry_at` datetime DEFAULT NULL COMMENT '最后重试时间',
  `next_retry_at` datetime DEFAULT NULL COMMENT '下次重试时间',
  `resolved_at` datetime DEFAULT NULL COMMENT '解决时间',
  `error_code` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '错误码',
  `error_message` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '错误信息',
  `error_detail` text COLLATE utf8_unicode_ci COMMENT '错误详情',
  `failure_stage` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '失败阶段',
  `last_order_id` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT '最近订单ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_signal_account` (`signal_id`,`account_id`),
  KEY `idx_status_next_retry` (`status`,`next_retry_at`),
  KEY `idx_signal_status` (`signal_id`,`status`),
  KEY `idx_account_status` (`account_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='失败账户恢复任务表，按信号和账户维度记录恢复重试状态';
