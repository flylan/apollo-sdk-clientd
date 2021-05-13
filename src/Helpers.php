<?php
namespace ApolloSdk\Helpers;
use ApolloSdk\Clientd\Storage;

/**
 * 获取阿波罗配置
 * @param string $key 配置key
 * @param mixed $default 如果阿波罗中没有配置指定的key值，默认的返回值
 * @param mixed $namespaceName 配置key所属的namespace
 * @param mixed $appId 配置key所属的应用id
 * @param mixed $saveConfigDir 保存阿波罗配置文件的目录
 * @author fengzhibin
 * @return mixed
 * @date 2021-04-09
 */
function get_config($key, $default = '', $namespaceName = null, $appId = null, $saveConfigDir = null) {
    //从常量读取
    $getParamFromConstantOrEnv = function ($paramName) {
        $value = null;
        if(defined($paramName)) {//从常量读取
            $value = constant($paramName);
        } elseif(!empty($tmp = getenv($paramName))) {//从环境变量读取
            $value = $tmp;
        }
        return $value;
    };
    if(is_null($appId)) {
        $appId = $getParamFromConstantOrEnv('APOLLO_SDK_APPID');
    }
    if(is_null($namespaceName)) {
        $namespaceName = $getParamFromConstantOrEnv('APOLLO_SDK_NAMESPACE_NAME');
    }
    if(is_null($saveConfigDir)) {
        $saveConfigDir = $getParamFromConstantOrEnv('APOLLO_SDK_SAVE_CONFIG_DIR');
    }
    if(
        empty($appId) ||
        empty($namespaceName) ||
        empty($saveConfigDir)
    ) {
        return $default;
    }

    $staticCacheKey = md5($appId.'_'.$namespaceName.'_'.$saveConfigDir);
    static $allConfig = [];
    if(!isset($allConfig[$staticCacheKey])) {
        $fileString = Storage::singleton($appId, $namespaceName, $saveConfigDir)->getConfigData();
        if(
            !empty($fileString) &&
            is_json($fileString)
        ) {
            $allConfig[$staticCacheKey] = json_decode($fileString, true);
        } else {
            $allConfig[$staticCacheKey] = [];
        }
    }
    //返回该key的值
    $value = isset($allConfig[$staticCacheKey][$key])?$allConfig[$staticCacheKey][$key]:$default;
    if(is_json($value)) {
        $tmp = json_decode($value, true);
        is_array($tmp) && $value = $tmp;
        unset($tmp);
    }
    return $value;
}

/**
 * 判断字符串是否为json
 * @param string $string 字符串
 * @author fengzhibin
 * @return bool
 * @date 2021-04-09
 */
function is_json($string) {
    if(!is_numeric($string) && is_string($string)) {//是字符串
        $string = trim($string);
        $firstCharacter = substr($string, 0, 1);
        $lastCharacter = substr($string, -1);
        if(
            (
                $firstCharacter === '{' &&
                $lastCharacter === '}'
            ) ||
            (
                $firstCharacter === '[' &&
                $lastCharacter === ']'
            )
        ) {
            return true;
        }
    }
    return false;
}

/**
 * 判断当前运行模式是否cli模式
 * @author fengzhibin
 * @return bool
 * @date 2021-04-09
 */
function is_cli_mode() {
    return strpos(php_sapi_name(), 'cli') !== false;
}

/**
 * 判断是否为合法的url链接
 * @param string $url url链接
 * @author fengzhibin
 * @return bool
 * @date 2021-04-09
 */
function is_legal_url($url) {
    if(
        !empty($url) &&
        preg_match('/http[s]?:\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $url)
    ) {
        return true;
    }
    return false;
}