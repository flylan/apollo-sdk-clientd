<?php
set_time_limit(0);
ini_set('display_errors', 'On');
error_reporting(E_ALL);

use ApolloSdk\Config\Client;
use ApolloSdk\Clientd\Storage;
use ApolloSdk\Helpers;
use GuzzleHttp\Client as GuzzleHttpClient;

require dirname(dirname(__FILE__)).'/vendor/autoload.php';

if(!Helpers\is_cli_mode()) {
    die('【ERROR】只能运行在cli模式下');
}

class App {
    const VERSION = '1.0.0';//当前版本

    private $apolloSdkClient;
    private $quiet = false;
    private $saveConfigDir = '';
    private $appNamespaceList = [];
    private $appNamespaceListPortal = 'application';

    public function __construct() {
        //初始化基础参数
        $this->initBaseParams();
        //初始化额外参数
        $this->initExtraParams();
    }

    /**
     * 检查阿波罗配置中心
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function checkServer($server) {
        if(Helpers\is_legal_url($server) === false) {
            $this->outputErrorMsg('阿波罗配置中心链接格式异常，不是合法的url');
        }
        $errorMsg = '';
        try {
            $guzzleHttpClient = new GuzzleHttpClient([
                'http_errors' => false,
                'timeout' => 5,
                'connect_timeout' => 5
            ]);
            $response = $guzzleHttpClient->get($server);
            $statusCode = (int)$response->getStatusCode();
            if($statusCode !== 404) {
                $errorMsg = "http状态码为{$statusCode}，配置中心根接口的状态码应该为404，请检查阿波罗配置中心链接";
            } else {
                $jsonDecodeBody = [];
                $body = (string)$response->getBody();
                if(!empty($body)) {
                    $jsonDecodeBody = json_decode($body, true);
                }
                if(!isset($jsonDecodeBody['status'])) {
                    $errorMsg = '接口返回数据中没有status字段，原始内容为：'.$body.PHP_EOL.PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
        if(!empty($errorMsg)) {
            $this->outputErrorMsg("通过curl请求{$this->server}时产生错误，错误信息如下：".PHP_EOL.$errorMsg.PHP_EOL);
        }
    }

    /**
     * 输出帮助信息
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function outputHelpInfo() {
        $help = <<<EOF
帮助信息:
Usage: php ./bin/apollo-clientd.php [options] -- [args...]

必填参数：
--server                      配置中心地址，格式例子：http://config-server.apollo.com
--conf-portal                 apollo-clientd的配置入口，格式为：应用id/namespace/key，格式例子：demo/test/apollo-clientd
                              
可选参数：
-h [--help]                   显示帮助信息
-q [--quit]                   是否开启静默模式屏蔽运行日志
-v [--version]                查看当前版本
--cluster-name                集群名，默认为default
--secret                      访问密钥
--skip-check-server           是否跳过自动检查server环节，默认启动时会请求server检查其是否为阿波罗配置中心，
                              启动时增加--skip-check-server参数可以跳过这个检查
--conf-portal-separator       默认conf-portal参数的分隔符为/，通过这个参数可以改变conf-portal的分隔符，例如--conf-portal-separator=";"，
                              这样--conf-portal就变成demo;test;clientd_config
                                   
更详细的使用说明参考：https://github.com/fengzhibin/apollo-sdk-clientd
EOF;

        echo $help;
        echo PHP_EOL;
        echo PHP_EOL;
        exit;
    }

    /**
     * 初始化基础参数
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function initBaseParams() {
        $opt = getopt(
            'h::q::v::',
            [
                'help',
                'quiet',
                'version',
                'server:',
                'cluster-name:',
                'secret:',
                'skip-check-server'
            ]
        );
        //输出帮助信息
        if(empty($opt) || isset($opt['help']) || isset($opt['h'])) {
            $this->outputHelpInfo();
        }
        //输出版本号
        if(isset($opt['v']) || isset($opt['version'])) {
            die('当前版本：'.self::VERSION.'，获取更多版本请访问：https://github.com/fengzhibin/apollo-sdk-clientd/releases'.PHP_EOL);
        }
        //静默模式
        if(isset($opt['q']) || isset($opt['quiet'])) {
            $this->quiet = true;
        }
        //阿波罗配置中心地址
        if(empty($opt['server'])) {
            $this->outputErrorMsg('必须传入--server参数');
        }
        //检查配置中心地址
        if(!isset($opt['skip-check-server'])) {
            $this->checkServer($opt['server']);
        }
        //初始化\ApolloSdk\Config\Client配置
        $config = ['config_server_url' => $opt['server']];
        //集群名称
        if(!empty($opt['cluster-name'])) {
            $config['cluster_name'] = $opt['cluster-name'];
        }
        //访问密钥
        if(!empty($opt['secret'])) {
            $config['secret'] = $opt['secret'];
        }
        $this->apolloSdkClient = new Client($config);
    }

    /**
     * 初始化额外参数
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function initExtraParams() {
        $opt = getopt(
            '',
            [
                'conf-portal:',
                'conf-portal-separator:'
            ]
        );
        //客户端启动时读取运行配置的入口
        if(empty($opt['conf-portal'])) {
            $this->outputErrorMsg('必须传入--conf-portal参数');
        }
        $confPortalSeparator = empty($opt['conf-portal-separator'])?'/':$opt['conf-portal-separator'];
        $confPortal = explode($confPortalSeparator, $opt['conf-portal']);
        if(count($confPortal) !== 3) {
            $this->outputErrorMsg("--conf-portal参数格式错误，正确的格式为： appid{$confPortalSeparator}namespace{$confPortalSeparator}key");
        }
        list($appId, $namespaceName, $key) = $confPortal;
        $tmp = $this->apolloSdkClient->getConfig($appId, $namespaceName);
        if(empty($tmp[$key])) {
            $this->outputErrorMsg("应用：{$appId}，namespace：{$namespaceName}下配置为空");
        }
        if(!Helpers\is_json($tmp[$key])) {
            $this->outputErrorMsg("apollo-clientd运行配置必须为json格式");
        }
        $config = json_decode($tmp[$key], true);
        unset($tmp);
        if(empty($config['app_namespace_list'])) {
            $this->outputErrorMsg("apollo-clientd运行配置没有配置app_namespace_list参数");
        }
        if(empty($config['save_config_dir'])) {
            $this->outputErrorMsg("apollo-clientd运行配置没有配置save_config_dir参数");
        }
        $this->appNamespaceList = $config['app_namespace_list'];
        $this->saveConfigDir = $config['save_config_dir'];
        if(!empty($config['app_namespace_list_portal'])) {
            $this->appNamespaceListPortal = $config['app_namespace_list_portal'];
        }
    }

    /**
     * 输出调试信息
     * @author fengzhibin
     * @date 2021-03-19
     */
    public function outputDebugMsg($msg, $data = []) {
        if($this->quiet === false) {
            echo $msg.(!empty($data)?':':'').PHP_EOL;
            if(!empty($data)) {
                if(is_array($data)) {
                    print_r($data);
                } else {
                    echo $data;
                }
            }
            echo PHP_EOL;
        }
    }

    /**
     * 输出错误信息
     * @author fengzhibin
     * @date 2021-03-19
     */
    public function outputErrorMsg($msg) {
        die('【ERROR】'.$msg.PHP_EOL);
    }

    /**
     * 获取所有待监控的应用id
     * @return array
     * @author fengzhibin
     * @date 2021-03-19
     */
    public function getAllAppIdList() {
        $result = array_keys($this->appNamespaceList);
        $this->outputDebugMsg('初始化监听的appid列表', $result);
        return $result;
    }

    /**
     * 获取所有待监控应用下的namespace列表
     * @return array
     * @author fengzhibin
     * @date 2021-03-19
     */
    public function getAllAppNamespaceList() {
        //获取没有配置namespace列表的应用
        $appNamespaceData = [];
        foreach($this->appNamespaceList as $appId => $namespaceList) {
            if(empty($namespaceList)) {
                $appNamespaceData[$appId][$this->appNamespaceListPortal] = '';
            }
        }
        //通过namespace入口获取所有的namespace列表
        if(!empty($appNamespaceData)) {
            $namespaceListConfigData = $this->apolloSdkClient->multiGetConfig($appNamespaceData);
            if(!empty($namespaceListConfigData)) {
                //组装成特定格式
                foreach($namespaceListConfigData as $appId => &$value) {
                    $this->appNamespaceList[$appId] = $this->formatPortalConfigData($value[$this->appNamespaceListPortal]);
                }
            }
        }
        $result = $this->appNamespaceList;
        $this->outputDebugMsg('初始化监听的namespace列表，格式为应用id => namespace列表', $result);
        foreach($result as $key => &$value) {
            if(empty($value)) {
                $this->outputDebugMsg("应用{$key}没有配置namespace列表，被过滤了");
                unset($result[$key]);
            }
        }
        if(empty($result)) {
            $this->outputErrorMsg('没有找到任何namespace信息，请检查应用namespace的配置'.PHP_EOL);
        }
        return $result;
    }

    /**
     * 格式化入口列表数据
     * @return array
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function formatPortalConfigData($configData, $appNamespaceListPortal = null) {
        if(is_null($appNamespaceListPortal)) {
            $appNamespaceListPortal = $this->appNamespaceListPortal;
        }
        if(empty($configData)) {
            return [];
        }
        $res = array_keys(array_filter($configData));
        array_unshift($res, $appNamespaceListPortal);
        return array_unique($res);
    }

    /**
     * 开始运行
     * @author fengzhibin
     * @date 2021-03-19
     */
    public function run() {
        //获取所有应用namespace信息
        $allAppNamespaceList = $this->getAllAppNamespaceList();
        //构造监听多个应用的数据结构
        $appNotificationsData = [];
        foreach($allAppNamespaceList as $appId => &$namespaceList) {
            foreach($namespaceList as &$namespace) {
                $appNotificationsData[$appId][$namespace] = -1;
            }
        }
        //开始监听应用变化
        $this->apolloSdkClient->listenMultiAppConfigUpdate(
            $appNotificationsData,
            function(
                $appId,
                $namespaceName,
                $newConfig,
                $notificationId,
                &$namespaceNotificationMapping
            ) {
                $this->outputDebugMsg("【".date('Y-m-d H:i:s')."】应用id：{$appId}的namespace：{$namespaceName}发生了配置变化");
                //更新了入口namespace，需要同时更新映射数组
                if($namespaceName === $this->appNamespaceListPortal) {
                    $newMapping = [];
                    $namespaceData = $this->formatPortalConfigData($newConfig);
                    if(!empty($namespaceData)) {
                        foreach($namespaceData as $_appId => &$_namespaceList) {
                            if(isset($namespaceNotificationMapping[$_appId])) {
                                $newMapping[$_appId] = $namespaceNotificationMapping[$_appId];
                            } else {
                                $newMapping[$_appId] = -1;
                            }
                        }
                        $namespaceNotificationMapping = $newMapping;
                        unset($_appId, $_namespaceList);
                    }
                    unset($newMapping, $namespaceData);
                }
                //保存新配置到指定目录
                Storage::singleton($appId, $namespaceName, $this->saveConfigDir)->storeConfigData($newConfig);
            }
        );
    }
}

(new App())->run();


