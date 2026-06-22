CREATE TABLE IF NOT EXISTS `g_trade_symbols` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '现货/基础交易对，如 BTC-USDT',
  `swap_symbol` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '合约交易对，如 BTC-USDT-SWAP',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0停用',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序值，越小越靠前',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_symbol` (`symbol`),
  UNIQUE KEY `uniq_swap_symbol` (`swap_symbol`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Grid 支持的交易对配置表';

INSERT INTO `g_trade_symbols` (`symbol`, `swap_symbol`, `status`, `sort`)
VALUES
  ('BTC-USDT', 'BTC-USDT-SWAP', 1, 10),
  ('ETH-USDT', 'ETH-USDT-SWAP', 1, 20),
  ('BNB-USDT', 'BNB-USDT-SWAP', 1, 30),
  ('DOGE-USDT', 'DOGE-USDT-SWAP', 1, 40),
  ('SOL-USDT', 'SOL-USDT-SWAP', 1, 50),
  ('HYPE-USDT', 'HYPE-USDT-SWAP', 1, 60)
ON DUPLICATE KEY UPDATE
  `swap_symbol` = VALUES(`swap_symbol`),
  `status` = VALUES(`status`),
  `sort` = VALUES(`sort`);
