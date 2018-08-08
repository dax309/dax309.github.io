---
layout: false
---
<?php
/*
 * 请勿使用windows下的记事本修改本文件。推荐使用 notepad++
 *  * 版本v2.5
 *  1.500页面优化
 *  2.数据请求增加重试,减少请求失败
 * 版本v2.4
 * 新增加cdn节点检测
 *  版本 v2.3
 *  1.500页面展示
 * 版本 v2.2
 * 1.增加mbstring检测
 * *版本 v2.1
 * 1.增加cache检测
 * 2.增加显示debug信息限制
 * 版本 v2.0
 * 1.documentUrl 做兼容解决部分用户二级目录报错
 * 2.增加debug信息方便为用户定位错误
 *
 * 版本 v1.1.0
 * 升级日志：
 * 1、添加自动更新
 * 2、修正缓存的BUG
 * 3、添加自动清理cache
 * 4、实现http code的转发
 *
 * 版本 v1.0.1
 * 升级日志：
 * 1、修正第一次无法打开，需要刷新才能打开的BUG
 * 2、添加对二级目录的支持
 * 3、添加对非index.php文件名的支持。
 *
 * */
$appId = '1145099';  // 站点的APPID （请勿修改和泄漏）
$appKey = '874B8BAE0B4CD085B3D56647FAEE16B0';// 站点的APP KEY（请勿修改和泄漏）
$proxyVersion = 9;
//===============================================================================
//===============================================================================
//===============================================================================
//================ 请勿修改以下程序 ====================
//===============================================================================
//===============================================================================
//===============================================================================

if (!defined("DTK_TYPE")) {
    define("DTK_TYPE", true);
}
$test_env = strrpos(@$_SERVER['HTTP_USER_AGENT'], 'test') === false ? false : true;
$requestUrl = @$_SERVER["REQUEST_URI"];


$documentUrl = @$_SERVER["PHP_SELF"];
if (empty($documentUrl)) {
    $documentUrl = @$_SERVER["SCRIPT_NAME"];
}
if (empty($documentUrl)) {
    $documentUrl = @$_SERVER["DOCUMENT_URI"];
}
if (empty($documentUrl)) {
    $documentUrl = $requestUrl;
    $str_pos = strpos($requestUrl, '?');
    if ($str_pos !== false) {
        $documentUrl = substr($requestUrl, 0, $str_pos);
    }
}
$file = @$_SERVER["DOCUMENT_ROOT"] . $documentUrl . 'req.php';
if (file_exists($file)) {
    require_once $file;
    exit;
} else {

    header("Content-type: text/html; charset=utf-8");
    echo 'cms 版本：' . $proxyVersion . '<br>';
    echo 'php 版本：' . PHP_VERSION . '<br>';
    if ($test_env) {
        echo 'CMS-HOST: ' . @$_SERVER["HTTP_HOST"] . '<br>';
        echo 'DOCUMENT-URL: ' . $documentUrl . '<br>';
        echo 'REQUEST-URL: ' . $requestUrl . '<br>';
    }
    if (function_exists('curl_init')) {
        echo 'curl 已经开启 ' . '<br>';
    } else {
        echo 'curl <span style="color: red">未开启,请先开启curl扩展，否则无法运行,请联系您的空间或者服务器提供商</span>' . '<br>';
    }
    if (function_exists('mb_substr')) {
        echo 'mbstring 已经开启 ' . '<br>';
    } else {
        echo 'mbstring <span style="color: red">未开启,请先开启mbstring扩展，否则无法运行</span>' . '<br>';
    }
    $test_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cache';
    $test_file = $test_dir . '/test.txt';
    if (!is_dir($test_dir)) {
        @mkdir($test_dir);
    }
    @file_put_contents($test_file, 'test');
    if (file_exists($test_file)) {
        echo 'cache：有效<br>';
    } else {
        echo 'cache <span style="color: red">无效,请先设置目录读写权限,请联系您的空间或者服务器提供商</span>' . '<br>';
        exit;
    }
    echo '......正在下载文件.......<br>';
    $httpHelper = new HttpHelperes($appId, $appKey, $proxyVersion, $documentUrl);
    $s = $httpHelper->upgrade();
    if ($s === false) {
        echo '......文件下载失败,请刷新页面重试,如果还是失败请联系客服.......<br>';
        echo '......如果是香港或者国外虚拟主机服务器，请联系您的主机供应商优化网络CDN.......<br>';

        if(function_exists('gethostbyname')){
            echo 'www.dataoke.com  --CDN 节点 ---'.gethostbyname("www.dataoke.com");
            echo '......可以尝试修改服务器DNS解析  然后ping 以上网址测试是否正常.......<br>';
        }
    } else {
        echo '......文件下载成功.......<br>';
        echo '------5秒后跳转----------<br>';
        echo '------<a href="' . $requestUrl . '">或者点击立即跳转</a>----------<br>';

        echo '<script>window.setTimeout("window.location=\'' . $requestUrl . '\'",5000);</script>';
        exit;
    }


}

class HttpHelperes
{
    protected $appId;
    protected $key;
    protected $documentUrl;
    protected $proxyVersion;
    protected $upgradeUrl = "http://www.dataoke.com/pmc/upgrade.html";

    public $httpCode = 200;

    public function __construct($appId, $key, $proxyVersion, $documentUrl)
    {
        $this->appId = $appId;
        $this->key = $key;
        $this->proxyVersion = $proxyVersion;
        $this->documentUrl = $documentUrl;
    }


    /**
     * @param $url
     * @param $requestUrl
     * @param array $param
     * @param string $method
     * @param bool $isAjax
     * @param string $cookie
     * @param string $refer
     * @param null $userAgent
     * @param bool $checkNewVersion
     * @return string
     */
    public function getHtml($url, $requestUrl, $param = array(), $method = 'GET', $isAjax = null, $cookie = NULL, $refer = null, $userAgent = null, $checkNewVersion = true,$re_try=true)
    {
        if (strpos($requestUrl, 'auth') !== false) {
            $url .= '/auth';
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 1);
        empty($refer) && $refer = @$_SERVER['HTTP_REFERER'];
        $ua = $userAgent;
        empty($ua) && $ua = @$_SERVER['HTTP_USER_AGENT'];
        $curl_time = $re_try == true ? 40 : 120;
        curl_setopt($ch, CURLOPT_TIMEOUT, $curl_time);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_REFERER, $refer);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $header = array(
            'APPID: ' . $this->appId,
            'APPKEY: ' . $this->key,
            'PROXY-VERSION: ' . $this->proxyVersion,
            'CMS-HOST: ' . @$_SERVER["HTTP_HOST"],
            'DOCUMENT-URL: ' . $this->documentUrl,
            'REQUEST-URL: ' . $requestUrl,
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if (empty($cookie)) {
            $cookie = $_COOKIE;
        }
        if (is_array($cookie)) {
            $str = '';
            foreach ($cookie as $k => $v) {
                $str .= $k . '=' . $v . '; ';
            }
            $cookie = $str;
        }
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        if ($param) {
            $urlInfo = parse_url($url);
            $q = array();
            if (isset($urlInfo['query']) && !empty($urlInfo['query'])) {
                parse_str($urlInfo['query'], $q);
            }
            $q = array_merge($q, $param);
            $cUrl = sprintf('%s://%s%s%s%s',
                $urlInfo['scheme'],
                $urlInfo['host'],
                isset($urlInfo['port']) ? ':' . $urlInfo['port'] : '',
                isset($urlInfo['path']) ? $urlInfo['path'] : '',
                count($q) ? '?' . http_build_query($q) : '');
            curl_setopt($ch, CURLOPT_URL, $cUrl);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        $r = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = mb_substr($r, 0, $headerSize);
        $r = mb_substr($r, $headerSize);
        $this->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unset($ch);
        $headers = explode("\r\n", $header);

        $expires = time() + 300;
        foreach ($headers as $h) {
            $h = trim($h);
            if (empty($h) || preg_match('/^(HTTP|Connection|EagleId|Server|X\-Powered\-By|Date|Transfer\-Encoding|Content)/i', $h)) {
                continue;
            }
            if (strpos($h, 'expires:') !== false) {
                $temp_arr = explode(':', $h);
                if (!empty($temp_arr[1]) && is_numeric(trim($temp_arr[1]))) {
                    $expires = intval(trim($temp_arr[1]));
                }
            }
            if (strpos($h, 'Cookie') !== false) {

                $h = explode(':', $h);
                if (!empty($h[1])) {
                    $h = explode('=', $h[1]);
                    if (!empty($h[0]) && !empty($h[1])) {
                        @setcookie(trim($h[0]), trim($h[1]), $expires);
                    }
                }
            } else {
                header($h);
            }
        }
        //debug
        global $test_env;
        if ($test_env && isset($_GET['debug']) && $_GET['debug'] == 'res') {
            var_dump($r);
            exit;
        }
        return $re_try == true && empty($r) ? $this->getHtml($url, $requestUrl, $param, $method, $isAjax, $cookie, $refer, $userAgent, $checkNewVersion, false) : $r;

    }

    public function upgrade()
    {
        $php = $this->getHtml($this->upgradeUrl, '', array(), 'GET', false, null, null, null, false);
        if ($php === false || strlen($php) < 500) {
            return false;
        }
        $php = @json_decode($php, true);
        if (empty($php['appid']) || empty($php['appkey']) || empty($php['content']) || $php['appid'] !== $this->appId) {
            return false;
        }
        $file = @$_SERVER["DOCUMENT_ROOT"] . $this->documentUrl;
        $result = @file_put_contents($file, $php['content'], LOCK_EX);
        if ($result === false) {
            $file .= 'req.php';
            $result = @file_put_contents($file, $php['content'], LOCK_EX);
        }
        return $result;
    }


}
