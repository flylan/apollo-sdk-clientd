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
```bash
git clone git@github.com:fengzhibin/apollo-configd.git

cd apollo-configd

composer install -vvvo

php ./bin/apollo-clientd.php --help
```

第二种基于phar包运行
```bash
wget "https://github.com/fengzhibin/apollo-sdk-clientd/releases/download/1.0.0/apollo-clientd.phar"

php apollo-clientd.phar --help
```

## 简单例子
```bash
php ./bin/apollo-clientd.php --server="http://apollo-configserver.demo.com" --conf-portal="demo/test/apollo-clientd"

或者

php apollo-clientd.phar --server="http://apollo-configserver.demo.com" --conf-portal="demo/test/apollo-clientd"
```

## 参数说明
cli启动参数

|参数|说明|默认值|
|----|----|----|
|--server|Apollo配置中心服务的地址| 无 |
|--conf-portal| 读取apollo-clientd运行配置的入口| 无 |
|-h                        | 显示帮助信息                  |无|
|--help                    | 同-h                        |无|
|--q                       | 开启静默模式，屏蔽运行时日志    |无|
|--quiet                   | 同-q                        |无|
|--secret                  | 访问密钥                     |无|
|--cluster-name            | 集群名                      |default|
|--skip-check-server | 是否跳过启动时检查Apollo配置中心是否合法的检查    |无|
|--conf-portal-separator  | conf-portal参数的分隔符 |/|

## 关于--conf-portal参数说明
--conf-portal参数用于将一些额外的参数（例如应用id，namespace信息等）保存在阿波罗配置中心，
这样就不必在apollo-clientd启动时写死了，这也减少了apollo-clientd启动参数个数， 
程序会在启动的时候到这配置入口去读取配置

这个参数格式为{appid}/{namespace}/{key}，以下图作为例子说明

![Screenshot](https://raw.githubusercontent.com/fengzhibin/apollo-sdk-clientd/master/images/extra.png)

--conf-portal=apollo-sdk-clientd/hello_world/world

## 额外参数配置（json格式）
```json
{
  "app_namespace_list": {//应用的namespace配置
    "demo1": [//应用id
      "application",//namespace
      "test1",//namespace
      "test2",//namespace
      "test3"//namespace
    ],
    "demo2": [],//不配置namespace列表，程序会走指定入口读取，参考下面的说明
    "demo3": []
  },
  "save_config_dir": "/data/apollo",//从阿波罗配置中心读取的配置，缓存在这个目录下
  "app_namespace_list_portal": "application"//应用下保存namespace列表的入口
}
```

如果不在app_namespace_list里面配置应用的namespace列表（例如应用demo2和demo3），
程序启动的时候会尝试通过以app_namespace_list_portal参数为入口读取namespace列表，格式如下：
![Screenshot](https://raw.githubusercontent.com/fengzhibin/apollo-sdk-clientd/master/images/portal.png)

程序会通过这个namespace读取当前应用下的namespace列表

完整示意图如下（只需要把当前应用下的namespace都配置在入口即可）：

![Screenshot](https://raw.githubusercontent.com/fengzhibin/apollo-sdk-clientd/master/images/portal_full.png)

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

