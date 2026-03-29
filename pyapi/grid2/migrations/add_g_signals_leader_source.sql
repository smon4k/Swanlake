-- ========================================
-- Leader 跟单：g_signals 来源与 OKX 成交对账字段
--
-- 若线上报错：Unknown column 'signal_source' in 'field list'
-- 说明本脚本未在该库执行，请在生产库执行后无需改代码。
--
-- 执行前可检查：SHOW COLUMNS FROM g_signals LIKE 'signal_source';
-- 若列已存在，勿重复执行第一段 ALTER（会报 Duplicate column）。
-- ========================================

ALTER TABLE `g_signals`
  ADD COLUMN `signal_source` varchar(32) NOT NULL DEFAULT 'api' COMMENT 'api/leader_copy/manual' AFTER `last_update_time`,
  ADD COLUMN `leader_account_id` int(11) DEFAULT NULL COMMENT 'Leader g_accounts.id' AFTER `signal_source`,
  ADD COLUMN `leader_bill_id` varchar(64) DEFAULT NULL COMMENT 'OKX billId' AFTER `leader_account_id`,
  ADD COLUMN `leader_ord_id` varchar(64) DEFAULT NULL COMMENT 'OKX ordId' AFTER `leader_bill_id`;

-- 同一 leader 同一 billId 只保留一条信号（与 insert_signal 去重一致）
ALTER TABLE `g_signals`
  ADD UNIQUE KEY `uk_leader_bill` (`leader_account_id`, `leader_bill_id`);
