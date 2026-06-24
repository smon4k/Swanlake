ALTER TABLE `g_signals`
  ADD COLUMN IF NOT EXISTS `sl` decimal(18,8) DEFAULT NULL COMMENT '自定义止损价' AFTER `lev`,
  ADD COLUMN IF NOT EXISTS `tp` decimal(18,8) DEFAULT NULL COMMENT '自定义止盈价' AFTER `sl`;

ALTER TABLE `g_signal_recovery_tasks`
  ADD COLUMN IF NOT EXISTS `signal_sl` decimal(18,8) DEFAULT NULL COMMENT '信号自定义止损价' AFTER `signal_lev`,
  ADD COLUMN IF NOT EXISTS `signal_tp` decimal(18,8) DEFAULT NULL COMMENT '信号自定义止盈价' AFTER `signal_sl`;
