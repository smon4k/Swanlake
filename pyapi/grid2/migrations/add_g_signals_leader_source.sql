-- ========================================
-- Leader 跟单：g_signals 来源与 OKX 成交对账字段
-- 上线前在目标库执行本脚本后再部署代码。
-- ========================================

ALTER TABLE `g_signals`
  ADD COLUMN `signal_source` varchar(32) NOT NULL DEFAULT 'api' COMMENT 'api/leader_copy/manual' AFTER `last_update_time`,
  ADD COLUMN `leader_account_id` int(11) DEFAULT NULL COMMENT 'Leader g_accounts.id' AFTER `signal_source`,
  ADD COLUMN `leader_bill_id` varchar(64) DEFAULT NULL COMMENT 'OKX billId' AFTER `leader_account_id`,
  ADD COLUMN `leader_ord_id` varchar(64) DEFAULT NULL COMMENT 'OKX ordId' AFTER `leader_bill_id`;

ALTER TABLE `g_signals`
  ADD UNIQUE KEY `uk_leader_bill` (`leader_account_id`, `leader_bill_id`);
