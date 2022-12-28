#!/bin/bash

while [ true ]; do

echo "开始检查python脚本是否运行..."
/bin/sleep 3600

cd /home/www/Swanlake/pyapi/
bscTokenNum=$(netstat -an|grep LISTEN | grep 8013)
echo $bscTokenNum
# 判断$bscTokenNum为空,此处意思为如果$bscTokenNum为空，那么重启
if ["$bscTokenNum"!=""];
then
    # 启动python脚本
    echo "python脚本[bsc币种新增地址量统计 端口8013]未运行, 重新启动中..."
    nohup python3 ./bscTokenNum.py >/dev/null &
    echo "python脚本[bsc币种新增地址量统计 端口8013]重启成功..."
else
    echo "python脚本[bsc币种新增地址量统计 端口8013]正在运行中..."
fi

done