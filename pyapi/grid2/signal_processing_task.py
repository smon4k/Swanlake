import asyncio
from datetime import datetime
from decimal import Decimal
import logging
import os
import time
from typing import Any, Dict
import redis
from database import Database
from stop_loss_task import StopLossTask
from trading_bot_config import TradingBotConfig
from savings_task import SavingsTask
from collections import defaultdict
from common_functions import cancel_all_orders, get_account_balance, get_exchange, get_market_price, get_market_precision, get_max_position_value, get_total_positions, open_position, get_client_order_id

class SignalProcessingTask:
    """交易信号处理类"""
    def __init__(self, config: TradingBotConfig, db: Database, signal_lock: asyncio.Lock, stop_loss_task: StopLossTask, account_locks: defaultdict, busy_accounts: set):
        self.db = db
        self.config = config
        self.running = True
        self.signal_lock = signal_lock
        self.stop_loss_task = stop_loss_task  # 保存引用
        self.max_workers = 5  # 并发 worker 数，可调节
        # self.account_locks = defaultdict(asyncio.Lock)  # 每个 account_id 一个锁
        self.redis = redis.Redis(host="localhost", port=6379, decode_responses=True)
        self.pubsub = self.redis.pubsub()
        self.account_locks = account_locks  # 外部传入的账户锁
        self.busy_accounts = busy_accounts  # 外部传入的忙碌账户集合
        self.active_tasks: set[asyncio.Task] = set()  # 用于跟踪正在运行的任务


    async def signal_processing_task(self):
        """信号调度任务，支持多个信号并发"""
        # 订阅频道
        self.pubsub.subscribe("signal_channel")
        # print("✅ 已订阅 signal_channel 等待唤醒...")
        logging.info("✅ 已订阅 signal_channel 等待唤醒...")
        while getattr(self, 'running', True):
            try:
                # print("🔍 信号调度任务运行中...")
                # ✅ 把阻塞的 get_message 放到线程池
                message = await asyncio.to_thread(
                    self.pubsub.get_message,
                    True,  # ignore_subscribe_messages
                    1      # timeout
                )
                if message:
                    print("📩 收到通知:", message)
                    asyncio.create_task(self.dispatch_signals())

                await asyncio.sleep(self.config.check_interval)


            except Exception as e:
                print(f"信号调度异常: {e}")
                logging.error(f"信号调度异常: {e}")
                await asyncio.sleep(5)

    async def dispatch_signals(self):
        # 从数据库取信号并处理
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    "SELECT * FROM g_signals WHERE status='pending' LIMIT 10"  # 一次取多条
                )
                signals = cursor.fetchall()
            conn.close()

            if signals:
                # 🚀 多个信号同时处理
                tasks = [self.handle_single_signal(signal) for signal in signals]
                await asyncio.gather(*tasks)
            else:
                await asyncio.sleep(self.config.check_interval)
        except Exception as e:
            print(f"处理信号异常: {e}")
            logging.error(f"处理信号异常: {e}")
    
    async def _run_single_account_signal(self, signal: dict, account_id: int):
        """单账户信号处理：完成后立即释放 busy 状态"""
        lock = self.account_locks[account_id]
        async with lock:
            self.busy_accounts.add(account_id)
            try:
                # print(f"🎯 账户 {account_id} 开始执行信号 {signal['id']}")
                logging.info(f"🎯 账户 {account_id} 开始执行信号 {signal['id']}")

                await self.process_signal(signal, account_id)

                # ✅ 成功时返回结果
                return {
                    "success": True,
                    "msg": "ok",
                    "account_id": account_id,
                    "data": None  # 或返回订单结果
                }

            except Exception as e:
                print(f"❌ 账户 {account_id} 信号处理失败: {e}")
                logging.error(f"❌ 账户 {account_id} 信号处理失败: {e}")
                return {
                    "success": False,
                    "msg": str(e),
                    "account_id": account_id
                }
            finally:
                self.busy_accounts.discard(account_id)
                # print(f"🔓 账户 {account_id} 已释放")
                logging.info(f"🔓 账户 {account_id} 已释放")

    def _is_close_signal(self, signal):
        # 判断是否是平仓
        return (
            (signal["direction"] == "long" and signal["size"] == 0)
            or (signal["direction"] == "short" and signal["size"] == 0)
        )
    
    async def handle_single_signal(self, signal):
        """单条信号的处理逻辑"""
        try:
            signal_id = signal['id']
            print(f"🚦 开始处理信号 {signal_id} ...")
            logging.info(f"🚦 开始处理信号 {signal_id} ...")

            if signal['name'] not in self.db.tactics_accounts_cache:
                print("🚫 无对应账户策略信号")
                logging.info("🚫 无对应账户策略信号")
                # 仍更新状态为 processed
                self._update_signal_status(signal_id, 'processed')
                return

            account_tactics_list = self.db.tactics_accounts_cache[signal['name']]
            is_close_signal = self._is_close_signal(signal)

            # 🟡 用于追踪所有任务是否完成
            all_done = asyncio.Future()
            running_tasks = set()
            task_results = {}  # account_id -> result dict or exception
            task_lock = asyncio.Lock()  # 保护 task_results 写入

            # ✅ 并发执行每个账户
            start_time = time.time()
            for account_id in account_tactics_list:
                task = asyncio.create_task(
                    self._run_single_account_signal(signal, account_id)
                )
                running_tasks.add(task)
                # self.active_tasks.add(task)

                # 任务完成后从 running_tasks 移除，并记录结果
                def done_callback(t, acc_id=account_id):
                    running_tasks.discard(t)
                    # 记录结果
                    asyncio.create_task(self._record_task_result(t, acc_id, task_results, task_lock))

                    # 检查是否全部完成
                    if len(running_tasks) == 0 and not all_done.done():
                        all_done.set_result(True)

                task.add_done_callback(done_callback)

            # 🔥 等待所有任务完成（在后台处理，不阻塞主流程）
            # 但我们需要等 all_done 才能判断是否执行 handle_close_position_update
            await all_done

            # ✅ 所有任务已完成，检查结果
            all_success = True
            async with task_lock:
                for acc_id, res in task_results.items():
                    if isinstance(res, Exception):
                        logging.error(f"⚠️ 账户 {acc_id} 执行异常: {res}")
                        all_success = False
                    elif not res.get("success", False):
                        logging.warning(f"⚠️ 账户 {acc_id} 执行失败: {res.get('msg', 'unknown')}")
                        all_success = False

            # ✅ 如果是平仓信号，且全部成功，才执行后续逻辑
            # print(f"平仓信号: {is_close_signal}, 全部成功: {all_success}")
            if is_close_signal:
                if all_success:
                    await self.handle_close_position_update(signal)
                    logging.info(f"✅ 平仓信号 {signal_id} 已触发 handle_close_position_update")
                else:
                    logging.warning(f"⚠️ 平仓信号 {signal_id} 未全部成功，跳过 handle_close_position_update")

            # ✅ 更新信号状态
            self._update_signal_status(signal_id, 'processed')
            # print(f"✅ 信号 {signal_id} 处理完成")
            end_time = time.time()  
            print(f"✅ 所有账户任务已启动, 耗时 {end_time - start_time:.2f} 秒")
            logging.info(f"✅ 信号 {signal_id} 处理完成")

        except Exception as e:
            print(f"❌ 信号 {signal_id} 处理异常: {e}")
            logging.error(f"❌ 信号 {signal_id} 处理异常: {e}")
            self._update_signal_status(signal_id, 'failed')
    
    async def _record_task_result(self, task, account_id, result_dict, lock):
        """记录任务结果，线程安全"""
        async with lock:
            try:
                result = task.result()  # 可能抛出异常
                result_dict[account_id] = result
            except Exception as e:
                result_dict[account_id] = e

    def _update_signal_status(self, signal_id, status):
        """更新信号状态（独立方法，避免重复）"""
        try:
            conn = self.db.get_db_connection()
            with conn.cursor() as cursor:
                cursor.execute(
                    "UPDATE g_signals SET status=%s WHERE id=%s",
                    (status, signal_id)
                )
            conn.commit()
        except Exception as e:
            logging.error(f"❌ 更新信号 {signal_id} 状态失败: {e}")
        finally:
            conn.close()

    async def process_signal(self, signal: Dict[str, Any], account_id: str) -> Dict[str, Any]:
        """
        处理单个账户的信号
        :param signal: 信号 dict
        :param account_id: 账户 ID
        :return: { "account_id": xx, "success": bool, "msg": str }
        """
        start_time = time.time()
        try:
            logging.info(f"➡️ 账户 {account_id} 开始处理信号 {signal['id']} ...")

            # Step 1: 校验账户是否可用
            account_info = self.db.account_cache[account_id]   
            if not account_info:
                msg = "账户信息不存在"
                logging.warning(f"⚠️ {msg} (account_id={account_id})")
                return {"account_id": account_id, "success": False, "msg": msg}
            side =  'buy' if signal['direction'] == 'long' else 'sell'  # 'buy' 或 'sell'
            # Step 2: 根据信号执行动作
            if (side == 'buy' and signal["size"] == 1) or (side == 'sell' and signal["size"] == -1): # 开仓
                await self._open_position(account_id, signal, account_info)
            elif (side == 'buy' and signal["size"] == 0) or (side == 'sell' and signal["size"] == 0): # 平仓
                await self._close_position(account_id, signal, account_info)
            else:
                msg = "未识别的信号类型"
                logging.error(msg)
                return {"account_id": account_id, "success": False, "msg": msg}

            logging.info(f"✅ 账户 {account_id} 完成信号 {signal['id']} 处理")
            return {"account_id": account_id, "success": True, "msg": "OK"}

        except asyncio.TimeoutError:
            msg = "处理超时"
            logging.error(f"⏱️ {msg} (account_id={account_id})")
            return {"account_id": account_id, "success": False, "msg": msg}

        except Exception as e:
            msg = f"异常: {e}"
            logging.error(f"❌ 信号 {signal['id']} 账户 {account_id} 处理失败: {e}")
            return {"account_id": account_id, "success": False, "msg": msg}
    
     # ----------------- 具体交易逻辑拆分 -----------------
    async def _open_position(self, account_id, signal, account_info):
        """
        开仓
        :param account_id: 账户 ID
        :param signal: 信号 dict
        :param account_info: 账户信息 dict
        :return: None
        """
        try:
            start_time = time.time()
            logging.info(f"🟢 [开仓] {account_id} {signal['symbol']} size={signal['size']}")
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            # TODO: 调用交易 API 下单
            strategy_info = await self.db.get_strategy_info(signal['name'])
            # 1.1 开仓前先平掉反向仓位
            await self.cleanup_opposite_positions(account_id, signal['symbol'], signal['direction'])

            # 1.2 取消所有未成交的订单
            await cancel_all_orders(self, exchange, account_id, signal['symbol']) # 取消所有未成交的订单
            
            if os.getenv("IS_LOCAL", "0") == "2":  # 本地调试不执行理财
                # 1.3 处理理财数据进行赎回操作
                await self.handle_financing_redeem(signal, account_id, account_info, exchange)

            # 理财状态为2时不开仓            
            if account_info.get('financ_state') == 2:
                return
            end_time = time.time()
            # print(f"🟢 账户 {account_id} 信号 {signal['id']} {end_time - start_time:.2f} 秒")
            side =  'buy' if signal['direction'] == 'long' else 'sell'  # 'buy' 或 'sell'
            # 1.3 开仓
            open_position = await self.handle_open_position(
                account_id,
                signal['symbol'],
                signal['direction'],
                side,
                signal['price']
            )

            if not open_position:
                return
            #1.4 处理记录开仓方向数据
            # has_open_position = await self.db.has_open_position(name, side)
            # if has_open_position:
            await self.db.update_signals_trade_by_id(signal['id'], {
                'pair_id': signal['id'],
                'position_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'count_profit_loss': strategy_info['count_profit_loss'],
                'stage_profit_loss': strategy_info['stage_profit_loss'],
            })
            end_time = time.time()
            # print(f"🟢 账户 {account_id} 信号 {signal['id']} 开仓处理完成, 耗时 {end_time - start_time:.2f} 秒")
            logging.info(f"🟢 账户 {account_id} 信号 {signal['id']} 开仓处理完成, 耗时 {end_time - start_time:.2f} 秒")
            # await asyncio.sleep(0.1)  # 模拟耗时
        except Exception as e:
            logging.error(f"❌ 开仓异常: {e}", exc_info=True)

    async def _close_position(self, account_id, signal, account_info):
        """
        平仓
        :param account_id: 账户 ID
        :param signal: 信号 dict
        :param account_info: 账户信息
        :return: { "account_id": xx, "success": bool, "msg": str }
        """
        try:
            logging.info(f"🔄 [平仓] {account_id} {signal['symbol']}")
            exchange = await get_exchange(self, account_id)
            if not exchange:
                return
            # TODO: 调用交易 API 平仓
            await cancel_all_orders(self, exchange, account_id, signal['symbol'], True) # 取消所有未成交的订单


            # 1.6 平掉反向仓位
            await self.cleanup_opposite_positions(account_id, signal['symbol'], signal['direction'])

            if os.getenv("IS_LOCAL", "0") == "2":  # 本地调试不执行购买理财
                # 1.7 进行余币宝理财
                await self.handle_financing_purchase(exchange, account_id, account_info, signal)
            await asyncio.sleep(0.1)
        except Exception as e:
            logging.error(f"❌ 平仓异常: {e}", exc_info=True)

    async def handle_financing_redeem(self, signal, account_id, account_info, exchange):
        """
        根据信号和账户的理财状态开仓之前进行处理余币宝赎回 / 资金划转 / 自动借币
        :param signal: 信号 dict
        :param account_id: 账户 ID
        :param account_info: 账户信息 dict
        :param exchange: 交易所对象
        """
        savings_task = SavingsTask(self.db, account_id)

        try:
            financ_state = account_info.get("financ_state")

            # 1️⃣ 理财模式（1: 开启理财, 2: 只做理财）
            if financ_state in (1, 2):
                yubibao_balance = await savings_task.get_saving_balance("USDT")
                market_precision = await get_market_precision(exchange, signal['symbol'])

                logging.info(f"余币宝余额: {account_id} {yubibao_balance}")
                if yubibao_balance > 0:
                    await savings_task.redeem_savings("USDT", yubibao_balance)
                else:
                    funding_balance = await get_account_balance(exchange, signal['symbol'], 'funding')
                    funding_balance_size = funding_balance.quantize(
                        Decimal(market_precision['amount']), rounding="ROUND_DOWN"
                    )
                    if funding_balance_size > 0:
                        logging.info(f"开始赎回资金账户余额到交易账户: {account_id} {funding_balance_size}")
                        await savings_task.transfer("USDT", funding_balance_size, from_acct="6", to_acct="18")
                    else:
                        logging.info(f"无法赎回资金账户余额到交易账户: {account_id} {funding_balance_size}")

            # 2️⃣ 借币开仓模式（3: 借币开仓）
            elif financ_state == 3:
                logging.info(f"开始借贷: {account_id} {account_info.get('auto_loan')}")
                if account_info.get("auto_loan") == 0:  # 如果未开启自动借币
                    is_auto_borrow = await savings_task.set_auto_borrow(True)
                    logging.info(f"设置自动借币结果: {is_auto_borrow}")
                    if is_auto_borrow:
                        await self.db.update_account_info(account_id, {"auto_loan": 1})

        except Exception as e:
            logging.error(f"处理理财逻辑失败: account_id={account_id}, error={e}")

    async def handle_financing_purchase(self, exchange, account_id, account_info, signal):
        """
        根据信号和账户的理财状态，平仓以后进行购买理财 处理理财购买
        :param exchange: 交易所对象
        :param account_id: 账户 ID
        :param account_info: 账户信息 dict
        :param signal: 信号 dict
        """
        try:
            if account_info.get('financ_state') == 1:  # 理财状态开启
                trading_balance = await get_account_balance(exchange, signal['symbol'], 'trading')
                market_precision = await get_market_precision(exchange, signal['symbol'])
                trading_balance_size = trading_balance.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')

                logging.info(f"交易账户余额: {account_id} {trading_balance_size}")
                if trading_balance_size > 0:
                    logging.info(f"购买理财: {account_id} {trading_balance_size}")
                    savings_task = SavingsTask(self.db, account_id)
                    await savings_task.purchase_savings("USDT", trading_balance_size)
                else:
                    logging.error(f"❌ 无法购买理财: {account_id} {trading_balance_size}")
        except Exception as e:
            logging.error(f"❌ 购买理财异常: {e}", exc_info=True)
    
    async def handle_close_position_update(self, signal: dict):
        """处理平仓后数据更新"""
        try:
            sign_id = signal['id']
            symbol = signal['symbol']
            name = signal['name']
            pos_side = signal['direction'] # 'long' 或 'short'
            side = 'buy' if pos_side == 'long' else 'sell'  # 'buy' 或 'sell'
            size = signal['size']      # 1, 0, -1
            price = signal['price']    # 0.00001
            direction = 'long' if side == 'sell' else 'short'
            has_open_position = await self.db.get_latest_signal_by_name_and_direction(name, direction)
            if has_open_position:
                open_price = Decimal(str(has_open_position['price']))
                close_price = Decimal(str(price))
                open_side = 'buy' if side == 'sell' else 'sell'
                if open_side == 'buy':
                    loss_profit = close_price - open_price
                else:
                    loss_profit = open_price - close_price
                loss_profit_normal = format(loss_profit, 'f')
                is_profit = float(loss_profit_normal) > 0

                print(f"处理平仓后数据: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}")
                logging.info(f"处理平仓后数据: {name} {symbol} {side} {size} at {price}, Profit: {loss_profit_normal}, Is Profit: {is_profit}")

                # 获取策略表连续几次亏损 
                strategy_info = await self.db.get_strategy_info(name)
                #计算总盈亏
                count_profit_loss = strategy_info.get('count_profit_loss', 0) # 总盈亏
                stage_profit_loss = strategy_info.get('stage_profit_loss', 0) # 阶段性盈亏

                stage_profit_loss_num = float(stage_profit_loss) + float(loss_profit_normal) # 阶段性盈亏累加
                if stage_profit_loss_num > 0:
                    stage_profit_loss_num = 0 # 如果阶段性盈亏大于0才清0

                if float(loss_profit_normal) > 0: # 盈利
                    profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                    if profit_loss > 0:
                        count_profit_loss = profit_loss
                    else:
                        count_profit_loss = float(loss_profit_normal)
                else:
                    profit_loss = float(count_profit_loss) + float(loss_profit_normal)
                    count_profit_loss = profit_loss

                await self.db.update_max_position_by_tactics(name, is_profit, sign_id, loss_profit_normal, open_price) # 批量更新指定策略所有账户最大仓位数据

                await self.db.update_strategy_loss_number(name, count_profit_loss, stage_profit_loss_num) # 更新盈亏策略记录
                print(f"策略 {name} 更新总盈亏: {count_profit_loss}, 阶段盈亏: {stage_profit_loss_num}")
                logging.info(f"策略 {name} 更新总盈亏: {count_profit_loss}, 阶段盈亏: {stage_profit_loss_num}")

                strategy_info = await self.db.get_strategy_info(name)
                await self.db.update_signals_trade_by_id(sign_id, {
                    'pair_id': has_open_position['pair_id'],
                    'position_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                    'loss_profit': loss_profit_normal,
                    'count_profit_loss': strategy_info['count_profit_loss'],
                    'stage_profit_loss': strategy_info['stage_profit_loss'],
                })
        except Exception as e:
            print(f"处理平仓后数据异常: {e}")
            logging.error(f"处理平仓后数据异常: {e}")


    # ---------- 核心子方法 ----------
    def parse_operation(self, action: str, size: int) -> dict:
        """解析信号类型"""
        if action == 'buy':
            if size == 1:   # 买入开多
                return {'type': 'open', 'side': 'buy', 'direction': 'long'}
            elif size == 0: # 买入平空
                return {'type': 'close', 'side': 'buy', 'direction': 'short'}
        else:  # sell
            if size == -1:  # 卖出开空
                return {'type': 'open', 'side': 'sell', 'direction': 'short'}
            elif size == 0: # 卖出平多
                return {'type': 'close', 'side': 'sell', 'direction': 'long'}
        raise ValueError(f"无效信号组合: action={action}, size={size}")

    async def cleanup_opposite_positions(self, account_id: int, symbol: str, direction: str):
        """平掉一个方向的仓位（双向持仓），并更新数据库订单为已平仓"""
        exchange = await get_exchange(self, account_id)
        if not exchange:
            return

        try:
            positions = await exchange.fetch_positions_for_symbol(symbol, {'instType': 'SWAP'})
            if not positions:
                print(f"无持仓信息 用户 {account_id}")
                logging.warning(f"无持仓信息 用户 {account_id}")
                return

            opposite_direction = 'short' if direction == 'long' else 'long'
            total_size = Decimal('0')

            for pos in positions:
                pos_side = pos.get('side') or pos.get('posSide') or ''
                pos_size = Decimal(str(pos.get('contracts') or pos.get('positionAmt') or 0))
                if pos_size == 0 or pos_side.lower() != opposite_direction:
                    continue
                total_size += abs(pos_size)

            if total_size == 0:
                print(f"无反向持仓需要平仓：{opposite_direction}")
                logging.info(f"无反向持仓需要平仓：{opposite_direction}")
                return

            close_side = 'sell' if opposite_direction == 'long' else 'buy'
            market_price = await get_market_price(exchange, symbol)
            client_order_id = await get_client_order_id()

            close_order = await open_position(
                self,
                account_id,
                symbol,
                close_side,
                opposite_direction,
                float(total_size),
                None,
                'market',
                client_order_id,
                True  # reduceOnly=True
            )

            if close_order:
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': close_order['id'],
                    'clorder_id': client_order_id,
                    'price': float(market_price),
                    'executed_price': None,
                    'quantity': float(total_size),
                    'pos_side': opposite_direction,
                    'order_type': 'market',
                    'side': close_side,
                    'status': 'filled',
                    'is_clopos': 1,
                    'position_group_id': '',
                })

                await self.db.mark_orders_as_closed(account_id, symbol, opposite_direction)
                print(f"用户 {account_id} 成功平掉{opposite_direction}方向总持仓：{total_size}")
                logging.info(f"用户 {account_id} 成功平掉{opposite_direction}方向总持仓：{total_size}")

            else:
                print(f"用户 {account_id} 平仓失败，方向: {opposite_direction}，数量: {total_size}")
                logging.info(f"用户 {account_id} 平仓失败，方向: {opposite_direction}，数量: {total_size}")

        except Exception as e:
            print(f"用户 {account_id} 清理反向持仓出错: {e}")
            logging.error(f"用户 {account_id} 清理反向持仓出错: {e}")
        finally:
            await exchange.close()

                
    async def handle_open_position(self, account_id: int, symbol: str, pos_side: str, side: str, price: Decimal):
        try:
            """处理开仓"""
            # print(f"⚡ 开仓操作: {account_id} {pos_side} {side} {price} {symbol}")
            logging.info(f"⚡ 开仓操作: {account_id} {pos_side} {side} {price} {symbol}")
            exchange = await get_exchange(self, account_id)
            # 1. 平掉反向仓位
            # await self.cleanup_opposite_positions(account_id, symbol, pos_side)
            total_position_value = await get_total_positions(self, account_id, symbol, 'SWAP') # 获取总持仓价值
            # print("总持仓数", total_position_value)
            logging.info(f"用户 {account_id} 总持仓数：{total_position_value}")
            if total_position_value is None:
                # print(f"总持仓数获取失败")
                logging.error(f"用户 {account_id} 总持仓数获取失败")
                return
            market_precision = await get_market_precision(exchange, symbol) # 获取市场精度

            total_position_quantity = 0
            if(total_position_value > 0):
                total_position_quantity = Decimal(total_position_value) * Decimal(market_precision['amount']) * price # 计算总持仓价值
                # print("总持仓价值", total_position_quantity)
                logging.info(f"用户 {account_id} 总持仓价值：{total_position_quantity}")
            
            # 2. 计算开仓量
            # price = await get_market_price(exchange, symbol)
            commission_price_difference = Decimal(self.db.account_config_cache[account_id].get('commission_price_difference'))
            price_float = price * (commission_price_difference / 100) # 计算价格浮动比例
            # print("价格浮动比例", price_float, commission_price_difference)
            if(pos_side == 'short'): # 做空
                price = price - price_float # 信号价 - 价格浮动比例
            elif(pos_side =='long'): # 做多
                price = price + price_float # 信号价 + 价格浮动比例

            balance = await get_account_balance(exchange, symbol, 'trading')
            # print(f"账户余额: {balance}")
            logging.info(f"用户 {account_id} 账户余额: {balance}")
            if balance is None:
                print(f"用户 {account_id} 账户余额获取失败")
                logging.error(f"用户 {account_id} 账户余额获取失败")
                return
        
            max_position = await get_max_position_value(self, account_id, symbol) # 获取配置文件对应币种最大持仓
            position_percent = Decimal(self.db.account_config_cache[account_id].get('position_percent'))
            # max_balance = max_position * position_percent #  最大仓位数 * 开仓比例
            # if balance >= max_balance: # 超过最大仓位限制
            #     balance = max_position
            # print(f"最大开仓数量: {max_balance}")
            logging.info(f"用户 {account_id} 最大开仓数量: {max_position}")
            size = await self.calculate_position_size(market_precision, max_position, position_percent, price, account_id)
            # print(f"开仓价: {price}")
            logging.info(f"用户 {account_id} 开仓价: {price} {position_percent}")
            # print(f"开仓量: {size}")
            print(f"用户 {account_id} 开仓量: {size} {market_precision['amount']}")
            logging.info(f"用户 {account_id} 开仓量: {size} {market_precision['amount']}")
            # logging.info(f"开仓量: {size}")
            size_total_quantity = Decimal(size) * Decimal(market_precision['amount']) * price
            # print(f"开仓价值: {size_total_quantity}")
            logging.info(f"用户 {account_id} 开仓价值: {size_total_quantity}")
            if size <= 0:
                # print(f"开仓量为0，不执行开仓")
                logging.info(f"用户 {account_id} 开仓量为0，不执行开仓")
                return

            # 3. 判断当前币种是否超过最大持仓
            # if size_total_quantity >= max_position:
            #     print(f"开仓量超过最大仓位限制，不执行开仓")
            #     logging.info(f"开仓量超过最大仓位限制，不执行开仓")
            #     return
            
            # 4. 判断所有仓位是否超过最大持仓量
            total_size_position_quantity = 0
            if total_position_quantity > 0:
                total_size_position_quantity = Decimal(total_position_quantity) + Decimal(size_total_quantity)

            # print("开仓以及总持仓价值", total_size_position_quantity)
            logging.info(f"用户 {account_id} 开仓以及总持仓价值：{total_size_position_quantity}")
            if total_size_position_quantity >= max_position: # 总持仓价值大于等于最大持仓
                logging.info(f"用户 {account_id} 最大持仓数：{max_position}")
                # print(f"最大持仓数：{max_position}")
                logging.info(f"用户 {account_id} 总持仓数大于等于最大持仓，不执行挂单")
                # print(f"总持仓数大于等于最大持仓，不执行挂单")
                return
            
            # 3. 获取市场价格
            client_order_id = await get_client_order_id()
            # 4. 下单并记录
            order = await open_position(
                self,
                account_id, 
                symbol, 
                side, 
                pos_side, 
                float(size), 
                float(price), 
                'limit',
                client_order_id
            )
            # print("order", order)
            if order:
                await self.db.add_order({
                    'account_id': account_id,
                    'symbol': symbol,
                    'order_id': order['id'],
                    'clorder_id': client_order_id,
                    'price': float(price),
                    'executed_price': None,
                    'quantity': float(size),
                    'pos_side': pos_side,
                    'order_type': 'limit',
                    'side': side, 
                    'status': 'live',
                    'position_group_id': '',
                })
                logging.info(f"用户 {account_id} 开仓成功")
                return True
            else:
                # print(f"用户 {account_id} 开仓失败")
                logging.error(f"用户 {account_id} 开仓失败")
                return False
        except Exception as e:
            print(f"用户 {account_id} 开仓异常: {e}")
            logging.error(f"用户 {account_id} 开仓异常: {e}")
            return False
        finally:
            await exchange.close()

    async def calculate_position_size(self, market_precision: object, balance: Decimal, position_percent: Decimal, price: float, account_id: int) -> Decimal:
        """计算仓位大小"""
        try:
            # market_precision = await get_market_precision(exchange, symbol, 'SWAP')
            # print("market_precision", market_precision, price)

            position_size = (balance * position_percent) / (price * Decimal(market_precision['contract_size']))
            position_size = position_size.quantize(Decimal(market_precision['amount']), rounding='ROUND_DOWN')

            # total_position = Decimal(self.db.account_config_cache[account_id].get('total_position', 0)) # 获取配置文件对应币种最大持仓
            # return min(position_size, total_position)
            return position_size
        except Exception as e:
            print(f"用户 {account_id} 计算仓位失败: {e}")
            return Decimal('0')
