<?php

namespace hbdm;

// 定义参数
define('ACCOUNT_ID', '123456'); // your account ID 
define('ACCESS_KEY','XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXX'); // your ACCESS_KEY
define('SECRET_KEY', 'XXXXXXX-XXXXXXX-XXXXXX-XXXXX'); // your SECRET_KEY


/**
* 火币合约REST API库
*/
class hbdm {
// 	private $url = 'https://api.hbdm.com'; //正式地址
	private $url = 'https://api.huobi.pro'; //正式地址
	private $api = '';
	public $api_method = '';
	public $req_method = '';
	public $access_key = '';
	public $secret_key = '';
	// private $runtimeConfig = [];
	function __construct($access_key,$secret_key) {
		// $this->runtimeConfig = include CMF_ROOT . "data/conf/config.php";
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->api = parse_url($this->url)['host'];
		date_default_timezone_set("Etc/GMT+0");
	}
	/**
	* 无认证 API
	*/

	function contract_contract_info() {
		//echo nl2br("---------获取账户余额示例-----------------\n");
		$this->api_method = "/api/v1/contract_contract_info";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}
    
    // 获取子用户列表
	function get_sub_user_list($fromId='') {
		//echo nl2br("---------获取平台资产总估值-----------------\n");
		$this->api_method = "/v2/sub-user/user-list";
		$this->req_method = 'GET';
		$postdata = [];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}
	
	// 获取子用户账户余额
	function get_subuser_aggregate_balance() {
		//echo nl2br("---------获取平台资产总估值-----------------\n");
		$this->api_method = "/v1/subuser/aggregate-balance";
		$this->req_method = 'GET';
		$postdata = [];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}
	
    // 获取平台资产总估值
	function get_account_valuation($accountType='', $valuationCurrency='') {
		//echo nl2br("---------获取平台资产总估值-----------------\n");
		$this->api_method = "/v2/account/valuation";
		$this->req_method = 'GET';
		$postdata = [
			'accountType' => $accountType,
			'valuationCurrency' => $valuationCurrency,
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}

	/**
	* 工具方法
	*/
	// 生成验签URL
	function create_sign_url($append_param = []) {
		// 验签参数
		$param = [
			'AccessKeyId' => $this->access_key,
			'SignatureMethod' => 'HmacSHA256',
			'SignatureVersion' => 2,
			'Timestamp' => date('Y-m-d\TH:i:s', time())
		];
		if ($append_param) {
			foreach($append_param as $k=>$ap) {
				$param[$k] = $ap; 
			}
		}
		return 'https://'.$this->api.$this->api_method.'?'.$this->bind_param($param);
	}
	// 组合参数
	function bind_param($param) {
		$u = [];
		$sort_rank = [];
		foreach($param as $k=>$v) {
			$u[] = $k."=".urlencode($v);
			$sort_rank[] = ord($k);
		}
		asort($u);
		$u[] = "Signature=".urlencode($this->create_sig($u));
		return implode('&', $u);
	}
	// 生成签名
	function create_sig($param) {
		$sign_param_1 = $this->req_method."\n".$this->api."\n".$this->api_method."\n".implode('&', $param);
		$signature = hash_hmac('sha256', $sign_param_1, $this->secret_key, true);
		return base64_encode($signature);
	}
	function curl($url,$postdata=[]) {
		// $proxy='http://127.0.0.1:7890';
		$proxy='';
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		if ($this->req_method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
		}
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_HEADER,0);
		curl_setopt($ch, CURLOPT_TIMEOUT,60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		if(!empty($proxy)) {
			curl_setopt($ch, CURLOPT_PROXY, $proxy); 
		} 
		curl_setopt ($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			]);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		return $output;
	}
}

?>
