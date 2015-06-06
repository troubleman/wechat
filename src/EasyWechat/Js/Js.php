<?php
/**
 * Js.php
 *
 * Part of EasyWeChat.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author    overtrue <i@overtrue.me>
 * @copyright 2015 overtrue <i@overtrue.me>
 * @link      https://github.com/overtrue
 * @link      http://overtrue.me
 */

namespace EasyWeChat\Js;

use EasyWeChat\Support\JSON;

/**
 * 微信 JSSDK
 */
class Js
{

    /**
     * 应用ID
     *
     * @var string
     */
    protected $appId;

    /**
     * 应用secret
     *
     * @var string
     */
    protected $appSecret;

    /**
     * Cache对象
     *
     * @var Cache
     */
    protected $cache;

    /**
     * 当前URL
     *
     * @var string
     */
    protected $url;

    const API_TICKET = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi';

    /**
     * constructor
     *
     * <pre>
     * $config:
     *
     * array(
     *  'app_id' => YOUR_APPID,  // string mandatory;
     *  'secret' => YOUR_SECRET, // string mandatory;
     * )
     * </pre>
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->appId     = $config['app_id'];
        $this->appSecret = $config['secret'];
        $this->cache     = new Cache($this->appId);
    }

    /**
     * 获取JSSDK的配置数组
     *
     * @param array $APIs
     * @param bool  $debug
     * @param bool  $json
     *
     * @return array
     */
    public function config(array $APIs, $debug = false, $json = true)
    {
        $signPackage = $this->getSignaturePackage();

        $config = array_merge(array('debug' => $debug), $signPackage, array('jsApiList' => $APIs));

        return $json ? JSON::encode($config) : $config;
    }

    /**
     * 获取jsticket
     *
     * @return string
     */
    public function getTicket()
    {
        $key = 'overtrue.wechat.jsapi_ticket'.$this->appId;

        // for php 5.3
        $appId     = $this->appId;
        $appSecret = $this->appSecret;
        $cache     = $this->cache;
        $apiTicket = self::API_TICKET;

        return $this->cache->get(
            $key,
            function ($key) use ($appId, $appSecret, $cache, $apiTicket) {
                $http  = new Http(new AccessToken(array('app_id' => $appId, 'secret' => $appSecret)));

                $result = $http->get($apiTicket);

                $cache->set($key, $result['ticket'], $result['expires_in']);

                return $result['ticket'];
            }
        );
    }

    /**
     * 签名
     *
     * @param string $url
     * @param string $nonce
     * @param int    $timestamp
     *
     * @return array
     */
    public function getSignaturePackage($url = null, $nonce = null, $timestamp = null)
    {
        $url       = $url ? $url : $this->getUrl();
        $nonce     = $nonce ? $nonce : $this->getNonce();
        $timestamp = $timestamp ? $timestamp : time();
        $ticket    = $this->getTicket();

        $sign = array(
                 'appId'     => $this->appId,
                 'nonceStr'  => $nonce,
                 'timestamp' => $timestamp,
                 'url'       => $url,
                 'signature' => $this->getSignature($ticket, $nonce, $timestamp, $url),
                );

        return $sign;
    }

    /**
     * 生成签名
     *
     * @param string $ticket
     * @param string $nonce
     * @param int    $timestamp
     * @param string $url
     *
     * @return string
     */
    public function getSignature($ticket, $nonce, $timestamp, $url)
    {
        return sha1("jsapi_ticket={$ticket}&noncestr={$nonce}&timestamp={$timestamp}&url={$url}");
    }

    /**
     * 设置当前URL
     *
     * @param string $url
     *
     * @return Js
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * 获取当前URL
     *
     * @return string
     */
    public function getUrl()
    {
        if ($this->url) {
            return $this->url;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] === 443) ? 'https://' : 'http://';

        return $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }

    /**
     * 获取随机字符串
     *
     * @return string
     */
    public function getNonce()
    {
        return uniqid('rand_');
    }
}