import logging
from datetime import datetime
from pathlib import Path
import sys

def setup_logger(name: str, log_dir: str = "logs", capture_print: bool = False) -> logging.Logger:
    """设置并返回一个日志记录器"""
    Path(log_dir).mkdir(exist_ok=True)
    
    logger = logging.getLogger(name)
    logger.setLevel(logging.INFO)
    
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    
    # 文件处理器
    log_file = Path(log_dir) / f"{datetime.now().strftime('%Y%m%d')}.log"
    file_handler = logging.FileHandler(log_file, mode='w', encoding='utf-8')
    file_handler.setFormatter(formatter)
    
    # 控制台处理器
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    
    # 先移除所有现有处理器，避免重复添加
    logger.handlers = []
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    if capture_print:
        class PrintToLogger:
            def __init__(self, logger, level=logging.INFO):
                self.logger = logger
                self.level = level
                
            def write(self, msg):
                if msg.strip():
                    if msg and len(msg) > 1 and msg != "^":
                        self.logger.log(self.level, msg)

                    
            def flush(self):
                pass
                
        sys.stdout = PrintToLogger(logger)
        sys.stderr = PrintToLogger(logger, logging.ERROR)
    
    return logger