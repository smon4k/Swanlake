-- ========================================
-- 账户余额同步 - 数据库表变更脚本
-- 2025-02-13
-- ========================================

-- 修改 g_accounts 表，添加总余额字段
ALTER TABLE `g_accounts` 
ADD COLUMN `total_balance` decimal(20,8) DEFAULT 0 COMMENT '账户总余额（交易+资金+余币宝）' AFTER `auto_loan`,
ADD COLUMN `balance_updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '余额更新时间' AFTER `total_balance`;

-- 创建索引提高查询性能
ALTER TABLE `g_accounts` 
ADD INDEX `idx_total_balance` (`total_balance`),
ADD INDEX `idx_balance_updated_at` (`balance_updated_at`);

-- ========================================
-- 说明
-- ========================================
-- total_balance: 账户总余额，为以下三类余额的总和：
--   - 交易账户余额
--   - 资金账户余额
--   - 余币宝余额
-- 
-- balance_updated_at: 最后一次余额更新的时间戳
-- 
-- 每小时自动同步一次
