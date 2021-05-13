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
    private $apolloSdkClient;
    private $configServerUrl = '';
    private $saveConfigDir = '';
    private $clusterName = '';
    private $secret = '';
    private $appId = '';
    private $appIdSeparator = ',';
    private $appNamespacePortal = 'application';
    private $quiet = false;

    public function __construct() {
        //初始化基础参数
        $this->initBaseParams();
        //初始化\ApolloSdk\Config\Client
        $config = ['config_server_url' => $this->configServerUrl];
        if(!empty($this->clusterName)) {//集群名称
            $config['cluster_name'] = $this->clusterName;
        }
        if(!empty($this->secret)) {//密钥
            $config['secret'] = $this->secret;
        }
        $this->apolloSdkClient = new Client($config);
    }

    /**
     * 检查阿波罗服务器地址是否为合法的地址
     * @author fengzhibin
     * @date 2021-03-19
     */
    private function checkConfigServerUrl() {
        if(Helpers\is_legal_url($this->configServerUrl) === false) {
            $this->outputErrorMsg('请传入合法的config-server-url链接');
        }
        $errorMsg = '';
        try {
            $guzzleHttpClient = new GuzzleHttpClient([
                'http_errors' => false,
                'timeout' => 5,
                'connect_timeout' => 5
            ]);
            $response = $guzzleHttpClient->get($this->configServerUrl);
            $statusCode = (int)$response->getStatusCode();
            if($statusCode !== 404) {
                $errorMsg = "http状态码为{$statusCode}，配置中心根接口的状态码应该为404，请检查config-server-url链接";
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
            $this->outputErrorMsg("通过curl请求{$this->configServerUrl}时产生错误，错误信息如下：".PHP_EOL.$errorMsg.PHP_EOL);
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
Usage: php apollo-clientd.php [options] -- [args...]

必填参数：
--config-server-url        配置中心地址
--save-config-dir          保存配置文件的目录
--appid                    应用id，支持监听单个或者多个应用，格式例子：
                           单个应用id：demo
                           多个应用id：demo1,demo2,demo3

可选参数：
-h [--help]                显示帮助信息
-q [--quit]                是否开启静默模式屏蔽运行日志
--cluster-name             集群名，默认为default
--secret                   访问密钥
--appid-separator          应用id分隔符，默认为英文逗号，参考--appid多个应用id的格式
                           例如--appid-separator=";"，这样就可以用;分隔应用id
--app-namespace-portal     应用下保存namepsace列表的配置入口，默认每个应用以application作为获取namepsace列表的入口
--check-config-server-url  是否跳过自动检查config-server-url环节，默认启动时会请求config-server-url检查其是否为阿波罗配置中心，
                           传入--check-config-server-url=0可以跳过这个检查

例子：
php apollo-clientd.php --config-server-url="http://configserver.apollo.com" --appid="demo" --save-config-dir="/data/apollo"
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
            'h::q::',
            [
                'config-server-url:',
                'save-config-dir:',
                'cluster-name:',
                'secret:',
                'appid:',
                'appid-separator:',
                'help',
                'quiet',
                'check-config-server-url:'
            ]
        );
        if(isset($opt['help']) || isset($opt['h'])) {
            $this->outputHelpInfo();
        }
        $checkConfigServerUrl = 1;
        if(isset($opt['check-config-server-url'])) {
            $checkConfigServerUrl = (int)$opt['check-config-server-url'];
        }
        if(empty($opt['config-server-url'])) {
            $this->outputErrorMsg('必须传入--config-server-url参数');
        }
        $this->configServerUrl = $opt['config-server-url'];
        if($checkConfigServerUrl === 1) {
            $this->checkConfigServerUrl();
        }
        if(empty($opt['save-config-dir'])) {
            $this->outputErrorMsg('必须传入--save-config-dir参数');
        }
        $this->saveConfigDir = $opt['save-config-dir'];
        if(empty($opt['appid'])) {
            $this->outputErrorMsg('必须传入--appid参数');
        }
        $this->appId = $opt['appid'];
        if(!empty($opt['cluster-name'])) {
            $this->clusterName = $opt['cluster-name'];
        }
        if(!empty($opt['secret'])) {
            $this->secret = $opt['secret'];
        }
        if(!empty($opt['appid-separator'])) {
            $this->appIdSeparator = $opt['appid-separator'];
        }
        if(isset($opt['q']) || isset($opt['quiet'])) {
            $this->quiet = true;
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
        $result = explode($this->appIdSeparator, $this->appId);
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
        $result = [];
        //获取所有应用id
        $allAppIdList = $this->getAllAppIdList();
        if(!empty($allAppIdList)) {
            //构造批量获取配置的数据结构
            $appNamespaceData = [];
            foreach($allAppIdList as &$appId) {
                $appNamespaceData[$appId][$this->appNamespacePortal] = '';
            }
            unset($appId);
            //通过namespace入口获取所有的namespace列表
            $namespaceListConfigData = $this->apolloSdkClient->multiGetConfig($appNamespaceData);
            if(!empty($namespaceListConfigData)) {
                //组装成特定格式
                foreach($namespaceListConfigData as $appId => &$value) {
                    $result[$appId] = $this->formatPortalConfigData($value[$this->appNamespacePortal]);
                }
            }
            unset($appNamespaceData);
        }
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
    private function formatPortalConfigData($configData, $appNamespacePortal = null) {
        if(is_null($appNamespacePortal)) {
            $appNamespacePortal = $this->appNamespacePortal;
        }
        if(empty($configData)) {
            return [];
        }
        $res = array_keys(array_filter($configData));
        array_unshift($res, $appNamespacePortal);
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
                if($namespaceName === $this->appNamespacePortal) {
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


