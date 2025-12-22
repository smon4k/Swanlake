-- 信号恢复机制 V2 - 优化版本
-- 新增字段支持部分成功恢复

ALTER TABLE g_signals 
ADD COLUMN IF NOT EXISTS success_accounts JSON DEFAULT NULL 
COMMENT '成功处理的账户列表';

ALTER TABLE g_signals 
ADD COLUMN IF NOT EXISTS failed_accounts JSON DEFAULT NULL 
COMMENT '失败的账户列表（需要恢复）';

ALTER TABLE g_signals 
ADD COLUMN IF NOT EXISTS last_update_time DATETIME DEFAULT CURRENT_TIMESTAMP 
COMMENT '最后更新时间（用于超时检测）';

-- 注意：pair_id 在信号表中已存在，用于关联开仓订单

-- 创建索引以优化查询性能
CREATE INDEX IF NOT EXISTS idx_signal_processing 
ON g_signals(status, name, last_update_time);

-- 查询所有 processing 信号示例
-- SELECT id, name, direction, size, success_accounts, failed_accounts, last_update_time 
-- FROM g_signals 
-- WHERE status = 'processing' 
-- ORDER BY last_update_time ASC;
