-- 为 g_signals 增加 lev 字段，按最大仓位比例开仓。
ALTER TABLE `g_signals`
  ADD COLUMN `lev` decimal(10,4) NOT NULL DEFAULT '1.0000' COMMENT '最大仓位使用比例' AFTER `size`;

-- 为恢复任务补充 signal_lev，确保失败补开沿用原始信号仓位比例。
ALTER TABLE `g_signal_recovery_tasks`
  ADD COLUMN `signal_lev` decimal(10,4) NOT NULL DEFAULT '1.0000' COMMENT '信号仓位比例' AFTER `signal_size`;
