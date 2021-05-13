<?php
namespace ApolloSdk\Clientd;
use ApolloSdk\Helpers;

class Storage {

    protected $appId = '';
    protected $namespaceName = '';
    protected $saveConfigDir = '';

    /**
     * 单例
     * @param string $appId 应用id
     * @param string $namespaceName namespace名称
     * @param string $saveConfigDir 保存配置文件的目录
     * @return self
     */
    public static function singleton($appId, $namespaceName, $saveConfigDir) {
        $staticCacheKey = md5($appId.'_'.$namespaceName.'_'.$saveConfigDir);
        static $obj = null;
        if(!isset($obj[$staticCacheKey])) {
            $obj[$staticCacheKey] = new self();
            $obj[$staticCacheKey]->appId = $appId;
            $obj[$staticCacheKey]->namespaceName = $namespaceName;
            $obj[$staticCacheKey]->saveConfigDir = $saveConfigDir;
        }
        return $obj[$staticCacheKey];
    }

    /**
     * 保存配置数据
     * @param mixed $configData 配置数据
     * @author fengzhibin
     * @return bool
     * @date 2021-04-09
     */
    public function storeConfigData($configData) {
        if(
            empty($configData) ||
            (
                is_string($configData) &&
                !Helpers\is_json($configData)
            )
        ) {
            return false;
        }
        if(is_array($configData)) {
            $configData = json_encode($configData);
        }
        //获取配置文件名
        $fileName = $this->getConfigFileAbsolutePath();
        if(empty($fileName)) {
            return false;
        }
        //生成相应的目录
        $dirname = dirname($fileName);
        if(!file_exists($dirname)) {
            mkdir($dirname, 0755, true);
        }
        //保存配置
        return (bool)file_put_contents($fileName, $configData);
    }

    /**
     * 读取配置数据
     * @author fengzhibin
     * @return string
     * @date 2021-04-09
     */
    public function getConfigData() {
        //获取配置文件名
        $fileName = $this->getConfigFileAbsolutePath();
        //读取配置
        if(!empty($fileName) && file_exists($fileName)) {
            return @file_get_contents($fileName);
        }
        return '';
    }

    /**
     * 获取配置文件的绝对路径
     * @author fengzhibin
     * @return string
     * @date 2021-04-09
     */
    public function getConfigFileAbsolutePath() {
        if(
            empty($this->appId) ||
            empty($this->namespaceName) ||
            empty($this->saveConfigDir)
        ) {
            return '';
        }
        $ds = DIRECTORY_SEPARATOR;//目录分隔符
        return rtrim($this->saveConfigDir, $ds)."{$ds}{$this->appId}{$ds}".str_replace('.', $ds, $this->namespaceName).'.json';
    }
}