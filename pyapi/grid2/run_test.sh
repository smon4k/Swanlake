run#!/bin/bash

cd /Users/qinlh/www/Swanlake/pyapi/grid2

echo "开始运行测试..."
echo "当前目录: $(pwd)"
echo "Python版本: $(python3 --version)"
echo ""

# 运行测试
python3 test_lightweight.py

echo ""
echo "测试完成！"
echo "查看日志: cat test_lightweight.log"

