<?php
/**
 * SDK.php
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

use EasyWeChat\Cache\Adapters\AdapterInterface as Cache;
use EasyWeChat\Core\Http;
use EasyWeChat\Support\JSON;

/**
 * Class SDK
 *
 * @package EasyWeChat\Js
 */
class SDK
{

    /**
     * App id.
     *
     * @var string
     */
    protected $appId;

    /**
     * App secret.
     *
     * @var string
     */
    protected $secret;

    /**
     * Cacher.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Http client.
     *
     * @var http
     */
    protected $http;

    /**
     * Current URI.
     *
     * @var string
     */
    protected $url;

    const API_TICKET = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi';

    /**
     * Constructor.
     *
     * @param string $appId
     * @param string $secret
     * @param Cache  $cache
     * @param Http   $http
     */
    public function __construct($appId, $secret, Cache $cache, Http $http)
    {
        $this->appId  = $appId;
        $this->secret = $secret;
        $this->cache  = $cache;
        $this->http   = $http;
    }

    /**
     * Get config json for jsapi.
     *
     * @param array $APIs
     * @param bool  $debug
     * @param bool  $beta
     * @param bool  $json
     *
     * @return array|string
     */
    public function config(array $APIs, $debug = false, $beta = false, $json = true)
    {
        $signPackage = $this->getSignaturePackage();

        $base = array(
                 'debug' => $debug,
                 'beta'  => $beta,
                );
        $config = array_merge($base, $signPackage, array('jsApiList' => $APIs));

        return $json ? JSON::encode($config) : $config;
    }

    /**
     * Return jsapi config as a PHP array.
     *
     * @param array $APIs
     * @param bool  $debug
     * @param bool  $beta
     *
     * @return array
     */
    public function getConfigArray(array $APIs, $debug = false, $beta = false)
    {
        return $this->config($APIs, $debug, $beta, false);
    }

    /**
     * Get jsticket.
     *
     * @return string
     */
    public function getTicket()
    {
        $key = 'overtrue.wechat.jsapi_ticket'.$this->appId;

        // for php 5.3
        $appId     = $this->appId;
        $secret    = $this->secret;
        $cache     = $this->cache;
        $http      = $this->http;
        $apiTicket = self::API_TICKET;

        return $this->cache->get(
            $key,
            function ($key) use ($appId, $secret, $cache, $http, $apiTicket) {
                $result = $http->get($apiTicket);

                $cache->set($key, $result['ticket'], $result['expires_in']);

                return $result['ticket'];
            }
        );
    }

    /**
     * Build signature.
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
     * Sign the params.
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
     * Set current url.
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
     * Get current url.
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
     * Return random string.
     *
     * @return string
     */
    public function getNonce()
    {
        return uniqid('rand_');
    }
}//end class