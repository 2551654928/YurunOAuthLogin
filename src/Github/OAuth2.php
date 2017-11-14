<?php
namespace Yurun\OAuthLogin\Github;

use Yurun\OAuthLogin\Base;
use Yurun\OAuthLogin\ApiException;

class OAuth2 extends Base
{
	/**
	 * 授权接口域名
	 */
	const AUTH_DOMAIN = 'https://github.com/';

	/**
	 * api接口域名
	 */
	const API_DOMAIN = 'https://api.github.com/';

	/**
	 * 是否在登录页显示注册，默认false
	 * @var bool
	 */
	public $allowSignup = false;

	/**
	 * 获取登录授权url地址
	 * @param string $name 跟在域名后的文本
	 * @param array $params GET参数
	 * @return string
	 */
	public function getAuthLoginUrl($name, $params = array())
	{
		return static::AUTH_DOMAIN . $name . (empty($params) ? '' : ('?' . $this->http_build_query($params)));
	}

	/**
	 * 获取url地址
	 * @param string $name 跟在域名后的文本
	 * @param array $params GET参数
	 * @return string
	 */
	public function getUrl($name, $params = array())
	{
		return static::API_DOMAIN . $name . (empty($params) ? '' : ('?' . $this->http_build_query($params)));
	}

	/**
	 * 第一步:获取登录页面跳转url
	 * @param string $callbackUrl 登录回调地址
	 * @param string $state 状态值，不传则自动生成，随后可以通过->state获取。用于第三方应用防止CSRF攻击，成功授权后回调时会原样带回。一般为每个用户登录时随机生成state存在session中，登录回调中判断state是否和session中相同
	 * @param array $scope 请求用户授权时向用户显示的可进行授权的列表。可空
	 * @return string
	 */
	public function getAuthUrl($callbackUrl = null, $state = null, $scope = null)
	{
		return $this->getAuthLoginUrl('login/oauth/authorize', array(
			'client_id'			=>	$this->appid,
			'redirect_uri'		=>	null === $callbackUrl ? $this->callbackUrl : $callbackUrl,
			'scope'				=>	null === $scope ? $this->scope : $scope,
			'state'				=>	$this->getState($state),
			'allow_signup'		=>	$this->allowSignup,
		));
	}

	/**
	 * 第二步:处理回调并获取access_token。与getAccessToken不同的是会验证state值是否匹配，防止csrf攻击。
	 * @param string $storeState 存储的正确的state
	 * @param string $code 第一步里$redirectUri地址中传过来的code，为null则通过get参数获取
	 * @param string $state 回调接收到的state，为null则通过get参数获取
	 * @return string
	 */
	protected function __getAccessToken($storeState, $code = null, $state = null)
	{
		$this->result = json_decode($this->http->accept('application/json')->get($this->getAuthLoginUrl('login/oauth/access_token', array(
			'client_id'			=>	$this->appid,
			'client_secret'		=>	$this->appSecret,
			'code'				=>	isset($code) ? $code : (isset($_GET['code']) ? $_GET['code'] : ''),
			'redirect_uri'		=>	$this->callbackUrl,
			'state'				=>	isset($state) ? $state : (isset($_GET['state']) ? $_GET['state'] : ''),
		)))->body, true);
		if(isset($this->result['error']))
		{
			throw new ApiException($this->result['error'], 0);
		}
		else
		{
			return $this->accessToken = $this->result['access_token'];
		}
	}

	/**
	 * 获取用户资料
	 * @param string $accessToken
	 * @return array
	 */
	public function getUserInfo($accessToken = null)
	{
		$this->result = json_decode($this->http->ua('YurunOAuthLogin')->get($this->getUrl('user', array(
			'access_token'			=>	null === $accessToken ? $this->accessToken : $accessToken,
		)))->body, true);
		if(isset($this->result['message']))
		{
			throw new ApiException($this->result['message'], 0);
		}
		else
		{
			$this->openid = $this->result['id'];
			return $this->result;
		}
	}
	
	/**
	 * 刷新AccessToken续期
	 * @param string $refreshToken
	 * @return bool
	 */
	public function refreshToken($refreshToken)
	{
		// 不支持
		return false;
	}

	/**
	 * 检验授权凭证AccessToken是否有效
	 * @param string $accessToken
	 * @return bool
	 */
	public function validateAccessToken($accessToken = null)
	{
		try
		{
			$this->getUserInfo($accessToken);
			return true;
		}
		catch(ApiException $e)
		{
			return false;
		}
	}

}