Ctrip Apollo PHP Client
=======================

## 特性
- 支持apollo配置变更的实时获取
- 支持单进程监听多个应用配置变更

## 安装
git clone git@github.com:fengzhibin/apollo-configd.git

cd apollo-configd

composer install -vvvo

## 简单例子
```bash
# 监听单个应用，并把配置通过json格式保存在/data/apollo下
php ./bin/apollo-clientd.php --config-server-url="http://apollo-configserver.demo.com" --appid="demo" --save-config-dir="/data/apollo"

# 监听多个应用
php ./bin/apollo-clientd.php --config-server-url="http://apollo-configserver.demo.com" --appid="demo1,demo2,demo3" --save-config-dir="/data/apollo"
```

## 参数说明
必选参数

|  参数   | 说明  | 默认值  |
|  ----  | ----  | ----  |
| --config-server-url  | Apollo配置服务的地址| 无 |
| --save-config-dir  | 保存从阿波罗服务器读取到的配置文件的目录| 无 |
| --appid   | 应用id | 无 |

可选参数

| 参数                       | 说明                        |默认值|
| ----                      | ----                        |----|
| -h                        | 显示帮助信息                  |无|
| --help                    | 同-h                        |无|
| --q                       | 开启静默模式，屏蔽运行时日志    |无|
| --quiet                   | 同-q                        |无|
| --secret                  | 访问密钥                     |无|
| --cluster-name            | 集群名                      |default|
| --appid-separator         | 应用id分隔符                 |,|
| --app-namespace-portal    | 应用namepsace列表配置入口     |application|
| --check-config-server-url | 是否自动检查config-server-url的有效性     |1|

完整的参数说明可以通过-h或者--help查看
```bash
php ./bin/apollo-clientd.php -h 或者 php apollo-clientd.php --help
```