Ctrip Apollo PHP Client
=======================
## 说明
这个仓库基于apollo-sdk/config实现了常驻的阿波罗客户端，从指定阿波罗配置中心实时拉取应用的配置到本地，
通过json格式化之后缓存在本地目录

## 特性
- 支持apollo配置变更的实时获取
- 支持单进程监听多个应用配置变更

## 安装（提供两种方式）
第一种基于git仓库运行

git clone git@github.com:fengzhibin/apollo-configd.git

cd apollo-configd

composer install -vvvo

第二种基于phar包运行

wget "https://github.com/fengzhibin/apollo-sdk-clientd/releases/download/v1.0.1/apollo-clientd.phar"
php apollo-clientd.phar

## 简单例子
```bash
基于git仓库运行和phar包运行的参数是一模一样的

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

## 关于应用的namespace列表说明
默认所有应用都把该应用下的namespace列表通过指定方式配置在--app-namespace-portal下（默认为application），
apollo-clientd启动时会自动去读取namespace列表，开启配置监听，配置格式参考下图
![Screenshot](https://raw.githubusercontent.com/fengzhibin/apollo-sdk-clientd/master/images/portal.png)

## 业务端读取配置
业务上需要读取阿波罗配置时，引入apollo-sdk/clientd这个composer包即可，参考以下步骤
```bash
composer require apollo-sdk/clientd
```

代码上引入composer
```php
require 'vendor/autoload.php';
```

例子：
```php
<?php
require 'vendor/autoload.php';

$key = 'hello';//namespace下的各业务key
$appId = 'demo';//支持通过全局变量或者环境变量赋值，变量名为：APOLLO_SDK_APPID
$namespaceName = 'test';//支持通过全局变量或者环境变量赋值，变量名为：APOLLO_SDK_NAMESPACE_NAME
$saveConfigDir = '/data/apollo';//支持通过全局变量或者环境变量赋值，变量名为：APOLLO_SDK_SAVE_CONFIG_DIR

//1.传统传参方式
var_dump(\ApolloSdk\Helpers\get_config($key, '', $namespaceName, $appId, $saveConfigDir));

//2.常量配置方式（对于单应用id场景比较适用，不需要每个调用都传递重复的应用id参数和保存配置目录参数）
在框架入口处配置常量，例如laravel框架根目录public/index.php里面配置以下的值
defined('APOLLO_SDK_APPID', $appId);
defined('APOLLO_SDK_SAVE_CONFIG_DIR', $saveConfigDir);
//defined('APOLLO_SDK_NAMESPACE_NAME', $namespaceName);

//然后业务代码就可以不需要传递这些已经定义好的常量了
var_dump(\ApolloSdk\Helpers\get_config($key, '', $namespaceName);

