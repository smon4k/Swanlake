# grid2 task for grid2


## 安装依赖
```
pip3 install -r requirements.txt

## 启动脚本目录位置
/etc/systemd/system

## 启动任务
```
sudo systemctl start grid2.service
sudo systemctl start grid2-api.service
```

### 重启任务
```
sudo systemctl restart grid2.service
sudo systemctl restart grid2-api.service
```

### 查看任务状态
```
sudo systemctl status grid2.service
sudo systemctl status grid2-api.service
```

### 查看任务日志
```
sudo journalctl -u grid2.service
sudo journalctl -u grid2-api.service
```

### 启动脚本位置
```
Task1： /etc/systemd/system/grid2.service
Task2： /etc/systemd/system/grid2-api.service
```

### systemctl 重置所有配置
```
sudo systemctl daemon-reload 
```