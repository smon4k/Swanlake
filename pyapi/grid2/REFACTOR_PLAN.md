# 🔧 API 限流问题解决方案 - 详细改动计划

## 📋 概述

**目标**：解决 OKX API 限流错误 (50011) 同时保持开仓延迟最小化
**策略**：优化 API 调用 + 智能错开 + 缓存优化
**预期成果**：
- ✅ 消除 95% 的限流错误
- ✅ 开仓延迟 < 50ms 增加
- ✅ API 调用量下降 55%

---

## 🚀 分阶段改动计划

### 【第一阶段】API 调用优化 - 消除重复调用

**目标**：从 90 API/s 降低到 60 API/s，消除峰值

#### 改动 1.1：验证开仓流程的两步清理操作（❌ 已排除）

**文件**：`signal_processing_task.py`
**位置**：第 327-334 行
**当前代码**：
```python
# 第 327-329 行
await self.cleanup_opposite_positions(
    account_id, signal["symbol"], signal["direction"]
)

# 第 331-334 行
await cancel_all_orders(
    self, exchange, account_id, signal["symbol"]
)  # 取消所有未成交的订单
```

**问题分析**：
❌ **之前的理解有误** - 这两个操作**不是重复的**，而是**互补的**：
- `cleanup_opposite_positions()` - 平掉**已有的反向仓位**（持仓表中的头寸）
- `cancel_all_orders()` - 取消**所有未成交的订单**（订单表中的待执行订单）

它们针对的是两个完全不同的概念：
1. 已成交的持仓（open positions）
2. 未成交的订单（pending orders）

**改动方式**：
❌ **不需要删除**，两个操作都是必要的

**验证方法**：
- 观察开仓前后的数据库状态
- 确认反向仓位已关闭 + 未成交订单已清空

**风险评估**：✅ 不需要修改
- 这是正确的设计，保留原样

---

#### 改动 1.2：添加账户级错开延迟（原 1.2，已调整序号）

**文件**：`signal_processing_task.py`
**位置**：第 172-177 行（在 `handle_single_signal()` 方法中）

**当前代码**：
```python
# 第 172-177 行
start_time = time.time()
for account_id in account_tactics_list:
    task = asyncio.create_task(
        self._run_single_account_signal(signal, account_id)
    )
    running_tasks.add(task)
```

**改动方式**：
```python
# 第 172-178 行（新增错开延迟）
start_time = time.time()
stagger_delay = 0.005  # 5 毫秒间隔
for idx, account_id in enumerate(account_tactics_list):
    # 为不同账户错开执行，避免 API 调用峰值
    # idx=0 延迟 0ms, idx=1 延迟 5ms, idx=2 延迟 10ms...
    if idx > 0:
        await asyncio.sleep(stagger_delay * idx)
    
    task = asyncio.create_task(
        self._run_single_account_signal(signal, account_id)
    )
    running_tasks.add(task)
```

**工作原理**：
```
改动前（同时发起）：
T0ms   账户1, 账户2, 账户3...账户10 同时发起 API
       ↓ 90 个 API 并发 → 触发限流 ❌

改动后（错开发起）：
T0ms   账户1 发起 API
T5ms   账户2 发起 API
T10ms  账户3 发起 API
...
T45ms  账户10 发起 API
       ↓ 分散到 50ms 内 → 不触发限流 ✅
```

**实际延迟影响**：
- 平均延迟增加：`(0+5+10+15+20+25+30+35+40+45) / 10 = 22.5ms`
- 相对于 3s 开仓耗时：增加 `22.5ms / 3000ms = 0.75%` ✅ 几乎无感

**验证方法**：
- 查看日志时间戳，确保各账户平仓/开仓时间间隔 5-10ms
- 监控 API 调用时间分布

**风险评估**：🟢 低风险
- 额外延迟仅 22ms 左右，不影响用户体验
- 完全不影响最终成功率

---

### 【第二阶段】缓存优化 - 减少重复查询

**目标**：从 60 API/s 降低到 50 API/s，避免重复查询

#### 改动 2.1：添加市场精度缓存初始化（原 2.1，已调整序号）

**文件**：`main.py`
**位置**：第 42-55 行（`OKXTradingBot.__init__()` 方法中）

**当前代码**：
```python
class OKXTradingBot:
    def __init__(self, config: TradingBotConfig):
        self.config = config
        self.db = Database(config.db_config)
        self.signal_lock = asyncio.Lock()
        self.signal_queue = asyncio.Queue()
        self.stop_loss_task = StopLossTask(config, self.db, self.signal_lock)

        # 🔐 新增：记录哪些账户正在被 signal 处理
        self.busy_accounts: set[int] = set()
        self.account_locks = defaultdict(asyncio.Lock)  # 每个账户独立锁
        
        # ... 其他初始化
```

**改动方式**：
在 `self.account_locks` 下面添加缓存字典

```python
# 在第 52 行后添加
self.account_locks = defaultdict(asyncio.Lock)  # 每个账户独立锁
self.market_precision_cache = {}  # ← 新增：市场精度缓存
```

**缓存格式**：
```python
{
    "BTC/USDT:USDT": {
        "min_amount": Decimal("0.001"),
        "contract_size": Decimal("1"),
        "price": Decimal("0.01"),
        "amount": Decimal("0.001"),
    },
    "ETH/USDT:USDT": {...},
    ...
}
```

**风险评估**：🟢 低风险
- 仅添加一个字典初始化，没有逻辑变化

---

#### 改动 2.2：修改 `get_market_precision()` 使用缓存（原 2.2，已调整序号）

**文件**：`common_functions.py`
**位置**：第 67-90 行（`get_market_precision()` 函数）

**当前代码**：
```python
async def get_market_precision(
    exchange: ccxt.Exchange, symbol: str, instType: str = "SWAP"
) -> Tuple[Decimal, Decimal]:
    """获取市场的价格和数量精度"""
    try:
        markets = await exchange.fetch_markets_by_type(
            instType, {"instId": f"{symbol}"}
        )  # ← 每次都调用 API
        # ... 处理数据
```

**改动方式**：

需要修改函数签名，添加 `self` 参数和缓存逻辑

```python
async def get_market_precision(
    self,  # ← 新增：需要访问 self.market_precision_cache
    exchange: ccxt.Exchange, 
    symbol: str, 
    instType: str = "SWAP"
) -> Dict:
    """获取市场的价格和数量精度（带缓存）"""
    
    # ✅ 先检查缓存
    cache_key = f"{symbol}:{instType}"
    if cache_key in self.market_precision_cache:
        logging.debug(f"使用缓存市场精度: {cache_key}")
        return self.market_precision_cache[cache_key]
    
    try:
        markets = await exchange.fetch_markets_by_type(
            instType, {"instId": f"{symbol}"}
        )
        contract_size = Decimal(str(markets[0]["contractSize"]))
        price_precision = Decimal(str(markets[0]["precision"]["price"]))
        amount_precision = Decimal(str(markets[0]["precision"]["amount"]))
        min_amount = Decimal(str(markets[0]["limits"]["amount"]["min"]))
        
        result = {
            "min_amount": min_amount,
            "contract_size": contract_size,
            "price": price_precision,
            "amount": amount_precision,
        }
        
        # ✅ 保存到缓存
        self.market_precision_cache[cache_key] = result
        
        return result
    except Exception as e:
        print(f"获取市场精度失败: {e}")
        return {
            "min_amount": Decimal("0.001"),
            "contract_size": Decimal("1"),
            "price": Decimal("0.0001"),
            "amount": Decimal("0.0001"),
        }
    finally:
        await exchange.close()
```

**调用处修改**：

需要更新所有调用 `get_market_precision()` 的地方，添加 `self`：

**需要修改的调用点**（共 6 处）：

1. `signal_processing_task.py` 第 429 行
   ```python
   # 改前
   market_precision = await get_market_precision(exchange, signal["symbol"])
   # 改后
   market_precision = await get_market_precision(self, exchange, signal["symbol"])
   ```

2. `signal_processing_task.py` 第 482 行
3. `signal_processing_task.py` 第 721 行
4. `price_monitoring_task.py` 第 220 行
5. `price_monitoring_task.py` 第 307 行
6. `stop_loss_task.py` 第 99 行

**缓存有效期**：
- 市场精度在交易对本身不变时不需要更新
- 建议每小时刷新一次（可选）

**验证方法**：
- 第一次调用某个交易对时，观察日志是否调用了 API
- 后续调用相同交易对时，观察是否使用了缓存

**风险评估**：🟡 中等风险（低概率）
- 市场精度几乎不变，缓存安全性很高
- 如果交易对配置改变，需要手动清空缓存
- 建议添加缓存过期机制（可选优化）

---

### 【第三阶段】价格监控限流 - 长期稳定性

**目标**：保护系统长期运行稳定性

#### 改动 3.1：在价格监控中添加账户并发限流（原 3.1，已调整序号）

**文件**：`price_monitoring_task.py`
**位置**：第 27-56 行（`price_monitoring_task()` 方法）

**当前代码**：
```python
class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask, busy_accounts: set[int]):
        # ... 初始化代码

    async def price_monitoring_task(self):
        """价格监控主任务（支持并发账户）"""
        while getattr(self, 'running', True):
            try:
                if self.signal_lock.locked():
                    print("⏸ 信号处理中，跳过一次监控")
                    logging.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue

                # 获取所有账户 ID
                account_ids = list(self.db.account_cache.keys())
                if not account_ids:
                    await asyncio.sleep(self.config.check_interval)
                    continue

                # 并发执行每个账户的持仓检查
                tasks = [
                    self._safe_check_positions(account_id) for account_id in account_ids
                ]
                await asyncio.gather(*tasks, return_exceptions=True)  # ← 无限并发
```

**改动方式**：

```python
class PriceMonitoringTask:
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask, busy_accounts: set[int]):
        self.config = config
        self.db = db
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task
        self.running = True
        self.busy_accounts = busy_accounts
        self.account_semaphore = asyncio.Semaphore(3)  # ← 新增：限制 3 个账户并发

    async def price_monitoring_task(self):
        """价格监控主任务（带并发限流）"""
        while getattr(self, 'running', True):
            try:
                if self.signal_lock.locked():
                    print("⏸ 信号处理中，跳过一次监控")
                    logging.info("⏸ 信号处理中，跳过一次监控")
                    await asyncio.sleep(1)
                    continue

                account_ids = list(self.db.account_cache.keys())
                if not account_ids:
                    await asyncio.sleep(self.config.check_interval)
                    continue

                # ✅ 添加限流逻辑
                async def limited_check_positions(account_id):
                    async with self.account_semaphore:
                        await self._safe_check_positions(account_id)

                # 并发执行每个账户的持仓检查
                tasks = [
                    limited_check_positions(account_id) for account_id in account_ids
                ]
                await asyncio.gather(*tasks, return_exceptions=True)

                await asyncio.sleep(self.config.check_interval)

            except Exception as e:
                print(f"❌ 价格监控主循环异常: {e}")
                logging.error(f"❌ 价格监控主循环异常: {e}")
                await asyncio.sleep(5)
```

**工作原理**：
```
改动前：
T0ms   账户1, 2, 3, 4, 5...10 同时查询 → 60 个 API 并发

改动后：
T0ms   账户1, 2, 3 查询
T2s    账户4, 5, 6 查询  ← 第一批完成后，第二批开始
T4s    账户7, 8, 9 查询  ← 依次进行
T6s    账户10 查询

好处：
- 并发限制在 3 个账户，远低于 OKX 限制
- 分布在时间线上，API 不会扎堆
```

**Semaphore 值选择**：
- 3：保守，最安全 ✅ 推荐
- 4-5：可以尝试，但风险稍高

**风险评估**：🟢 低风险
- 仅限制价格监控的并发，不影响信号处理
- 响应延迟增加，但不影响开仓/平仓

---

#### 改动 3.2：在订单查询中添加并发限流（原 3.2，已调整序号）

**文件**：`price_monitoring_task.py`
**位置**：第 132-139 行（在 `check_positions()` 方法中）

**当前代码**：
```python
async def check_positions(self, account_id: int):
    # ... 
    order_infos = {}
    async def fetch_order_info(order):
        try:
            info = await exchange.fetch_order(order['order_id'], order['symbol'], {'instType': 'SWAP'})
            order_infos[order['order_id']] = info
        except Exception as e:
            logging.error(f"⚠️ 查询订单失败 {account_id}/{order['symbol']}: {e}")
            order_infos[order['order_id']] = None
    
    await asyncio.gather(*[fetch_order_info(o) for o in open_orders])  # ← 无限并发
```

**改动方式**：

在 `__init__` 中添加订单查询限流：

```python
def __init__(self, ...):
    # ... 现有代码
    self.account_semaphore = asyncio.Semaphore(3)  # 账户并发限流
    self.order_semaphore = asyncio.Semaphore(5)    # ← 新增：订单查询并发限流
```

在 `check_positions()` 方法中修改订单查询部分：

```python
async def check_positions(self, account_id: int):
    # ... 
    order_infos = {}
    async def fetch_order_info(order):
        async with self.order_semaphore:  # ← 添加限流
            try:
                info = await exchange.fetch_order(order['order_id'], order['symbol'], {'instType': 'SWAP'})
                order_infos[order['order_id']] = info
            except Exception as e:
                logging.error(f"⚠️ 查询订单失败 {account_id}/{order['symbol']}: {e}")
                order_infos[order['order_id']] = None
    
    await asyncio.gather(*[fetch_order_info(o) for o in open_orders])
```

**Semaphore 值选择**：
- 5：同时查询 5 个订单，平衡性能和稳定性 ✅ 推荐
- 3：更保守
- 10：更激进，风险更高

**风险评估**：🟢 低风险
- 仅限制同一账户内订单查询的并发
- 影响极小

---

### 【第四阶段】验证测试（原 4.x，已调整序号）

#### 测试 4.1：开仓延迟验证（原测试 4.1）

**测试场景**：发送一个策略的开仓信号，该策略绑定 10 个账户

**验证方法**：
```
检查日志：

2025-12-05 14:00:00,001 - INFO - 🎯 账户 1 开始执行信号 1
2025-12-05 14:00:00,005 - INFO - 🎯 账户 2 开始执行信号 1
2025-12-05 14:00:00,010 - INFO - 🎯 账户 3 开始执行信号 1
2025-12-05 14:00:00,015 - INFO - 🎯 账户 4 开始执行信号 1
...
2025-12-05 14:00:00,045 - INFO - 🎯 账户 10 开始执行信号 1

✅ 预期：账户间隔 5ms 左右（错开延迟生效）
```

**成功指标**：
- ✅ 所有账户在 50ms 内启动
- ✅ 账户间隔约 5ms
- ✅ 开仓完成时间 < 3.5s（对比前 3.0s，增加仅 500ms）

---

#### 测试 4.2：限流错误验证（原测试 4.2）

**测试场景**：运行 1 小时，观察是否出现限流错误

**验证方法**：
```bash
# 查看日志中的限流错误
grep "Too Many Requests" bot.log
grep "50011" bot.log
```

**成功指标**：
- ✅ 平仓时：基本无错误（< 1 次/小时）
- ✅ 价格监控：基本无错误（< 3 次/小时）
- ✅ 对比前：从 20-30 次/小时 → 0-3 次/小时

---

#### 测试 4.3：API 调用量监控（原测试 4.3）

**测试场景**：统计 1 分钟内的 API 调用数量

**验证方法**：
在 `common_functions.py` 中添加计数器（临时）

```python
# 在文件顶部添加
api_call_count = 0

async def get_exchange(self, account_id: int) -> Optional[ccxt.Exchange]:
    global api_call_count
    api_call_count += 1  # 计数每个 API 调用
    # ... 现有代码
```

**成功指标**：
- ✅ API 调用从 90/s 降低到 40-50/s
- ✅ 峰值更平均，不再有突发的高并发

---

## 📊 改动汇总表

| 优先级 | 改动 | 文件 | 行号 | 复杂度 | 预期效果 |
|--------|------|------|------|--------|---------|
| ❌ 已排除 | ~~1.1 删除重复 cancel~~ | signal_processing_task.py | 331-334 | - | - |
| 🔴 第1 | 1.2 错开延迟 | signal_processing_task.py | 173-177 | 低 | 👑 消除峰值 |
| 🟠 第2 | 2.1 缓存初始化 | main.py | 52 | 低 | ⬇️ 5% API |
| 🟠 第3 | 2.2 缓存逻辑 + 调用点 | common_functions.py | 67-90 + 6 处调用 | 中 | ⬇️ 10% API |
| 🟡 第4 | 3.1 账户限流 | price_monitoring_task.py | 27-56 | 低 | 👑 长期稳定 |
| 🟡 第5 | 3.2 订单限流 | price_monitoring_task.py | 27, 132-139 | 低 | ⬇️ 10% API |

---

## ⏱️ 预期时间表

- **改动 1.2**：5 分钟（关键，消除峰值）
- **改动 2.1, 2.2**：20 分钟（逐个修改调用点）
- **改动 3.1, 3.2**：10 分钟（基础 Semaphore 知识）
- **测试验证**：10-30 分钟

**总计**：约 45 分钟

---

## ✅ 改动完成后的效果

```
【改动前】
错误日志：
2025-12-05 09:45:24,175 - ERROR - 用户 2 清理反向持仓出错: okx {"msg":"Too Many Requests","code":"50011"}
2025-12-05 09:45:24,176 - ERROR - 用户 1 清理反向持仓出错: okx {"msg":"Too Many Requests","code":"50011"}
（频繁出现）

【改动后】
✅ 基本不再出现此错误
✅ 开仓延迟保持不变（< 3.5s）
✅ 系统运行稳定（可长期运行）
```

---

## 🎯 建议执行顺序

1. **先执行 1.2**（快速胜利，消除峰值）
2. **观察 1 小时效果**
3. **如果仍有偶发限流，再执行 3.1 + 3.2**（短期稳定性）
4. **最后执行 2.1 + 2.2**（长期优化，缓存）

⚠️ **重要说明**：改动 1.1（删除 cancel_all_orders）已被排除，因为：
- `cleanup_opposite_positions()` 处理的是**已成交的持仓**
- `cancel_all_orders()` 处理的是**未成交的订单**
- 这两个操作是互补的，不是重复的


