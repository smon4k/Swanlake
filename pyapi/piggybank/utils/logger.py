import logging
from logging.handlers import TimedRotatingFileHandler
from pathlib import Path
import sys

def setup_logger(name: str, log_dir: str = "logs", capture_print: bool = False) -> logging.Logger:
    """设置并返回一个日志记录器，支持每天自动轮换 info.log 到日期文件"""

    Path(log_dir).mkdir(exist_ok=True)

    logger = logging.getLogger(name)
    logger.setLevel(logging.INFO)

    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')

    # 日志文件：logs/info.log，每天0点轮换，保留7天旧日志
    log_file = Path(log_dir) / "info.log"
    file_handler = TimedRotatingFileHandler(
        filename=log_file,
        when="midnight",        # 每天午夜轮换
        interval=1,
        backupCount=7,          # 最多保留 7 天的日志文件
        encoding='utf-8',
        utc=False               # 如果你部署在 UTC 时区，则设置为 True
    )
    file_handler.setFormatter(formatter)
    file_handler.suffix = "%Y%m%d.log"  # 轮换后的文件名后缀格式

    # 控制台输出
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)

    # 清除旧处理器
    logger.handlers = []
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)

    # 可选：捕获 print()
    if capture_print:
        class PrintToLogger:
            def __init__(self, logger, level=logging.INFO):
                self.logger = logger
                self.level = level

            def write(self, msg):
                if msg.strip():
                    self.logger.log(self.level, msg.strip())

            def flush(self):
                pass

        sys.stdout = PrintToLogger(logger)
        sys.stderr = PrintToLogger(logger, logging.ERROR)

    return logger
