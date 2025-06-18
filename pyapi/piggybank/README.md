# grid2 task for piggybank


## 安装依赖
```
pip3 install -r requirements.txt


## 启动任务
```
sudo systemctl start piggybank.service
```

### 重启任务
```
sudo systemctl restart piggybank.service
```

### 查看任务状态
```
sudo systemctl status piggybank.service
```

### 查看任务日志
```
sudo journalctl -u piggybank.service
```

### 启动脚本位置
```
/etc/systemd/system/piggybank.service
```

### systemctl 重置所有配置
```
sudo systemctl daemon-reload 
```