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
	private $url = 'https://api.hbdm.com'; //正式地址
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

	function contract_index() {
		//echo nl2br("---------获取合约指数信息-----------------\n");
		$this->api_method = "/api/v1/contract_index";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}

	function contract_price_limit() {
		//echo nl2br("---------获取合约最高限价和最低限价-----------------\n");
		//$this->api_method = "/api/v1/contract_price_limit?symbol=BTC&contract_type=this_week";
		$this->api_method = "/api/v1/contract_price_limit";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}

	function contract_open_interest() {
		//echo nl2br("---------获取合约当前可用合约总持仓量-----------------\n");
		$this->api_method = "/api/v1/contract_open_interest";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}

	function market_depth() {
		//echo nl2br("---------获取行情深度数据-----------------\n");
		$this->api_method = "/market/depth";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}
	function market_history() {
		//echo nl2br("--------- 获取K线数据-----------------\n");
		$this->api_method = "/market/history/kline?symbol=BTC_CQ&period=1min&size=200";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}
	function market_merged($symbol) {
		//echo nl2br("---------获取聚合行情-----------------\n");
		$this->api_method = "/market/detail/merged?symbol=$symbol";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}
	function market_trade() {
		//echo nl2br("---------获取市场最近成交记录-----------------\n");
		$this->api_method = "/market/trade?symbol=BTC_CQ";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}
	function market_history_trade() {
		//echo nl2br("---------批量获取获取最近的交易记录-----------------\n");
		$this->api_method = "/market/history/trade?symbol=BTC_CQ&size=100";
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}

	function account_accounts($sub_id=0) {
		//echo nl2br("---------母用户查询子用户各币种账户余额-----------------\n");
		$this->api_method = "/v1/account/accounts/" . $sub_id;
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
	}


	function contract_account_info($symbol = '') {
		//echo nl2br("---------获取用户账户信息-----------------\n");
		$this->api_method = "/api/v1/contract_account_info";
		$this->req_method = 'POST';
		$postdata = [
			'symbol' => $symbol
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}

	function contract_position_info($symbol = '') {
		//echo nl2br("---------获取用户持仓信息-----------------\n");
		$this->api_method = "/api/v1/contract_position_info";
		$this->req_method = 'POST';
		$postdata = [
			'symbol' => $symbol
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		//var_dump($return);
		return json_decode($return,true);
	}

	function contract_order() {
		echo nl2br("---------合约下单-----------------\n");
		$this->api_method = "/api/v1/contract_order";
		$this->req_method = 'POST';
		$postdata = [
			"symbol"=>"BTC",
			"contract_type"=>"quarter",
			"price"=>"6375.01",
			"volume"=>9.0,
			"direction"=>"buy",
			"offset"=>"open",
			"lever_rate"=>"20",
			"order_price_type"=>"limit"
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return $return;
	}

	function contract_match_result($symbol,$contract_code) {
		//echo nl2br("---------获取用户持仓信息-----------------\n");
		$this->api_method = "/api/v1/contract_matchresults";
		$this->req_method = 'POST';
		$postdata = [
			'symbol' => $symbol,
			'trade_type' => 0,
			'create_date' =>7,
			'contract_code' => $contract_code,
			'page_index' => 1,
			'page_size' => 50,
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}

	
	function swap_account_info($contract_code, $type) {
		//echo nl2br("---------获取U本位币本位用户账户信息-----------------\n");
		if($type == 2) {
			$this->api_method = "/linear-swap-api/v1/swap_account_info";
		} 
		if($type == 3) {
			$this->api_method = "/swap-api/v1/swap_account_info";
		}
		$this->req_method = 'POST';
		$postdata = [];
		if(!empty($contract_code)) {
			$postdata = [
				'contract_code' => $contract_code
			];
		}
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}

	function swap_account_position_info($contract_code, $type) {
		//echo nl2br("---------获取U本位币本位用户持仓信息-----------------\n");
		if($type == 2) {
			$this->api_method = "/linear-swap-api/v1/swap_account_position_info";
		} 
		if($type == 3) {
			$this->api_method = "/swap-api/v1/swap_account_position_info";
		}
		$this->req_method = 'POST';
		$postdata = [
			'contract_code' => $contract_code
		];
		$url = $this->create_sign_url($postdata);
		$return = $this->curl($url, $postdata);
		return json_decode($return,true);
	}

	function swap_market_bbo($contract_code, $type) {
		//echo nl2br("---------获取市场最优挂单-----------------\n");
		if($type == 2) {
			$this->api_method = "/linear-swap-ex/market/bbo?contract_code=$contract_code";
		} 
		if($type == 3) {
			$this->api_method = "/swap-ex/market/bbo?contract_code=$contract_code";
		}
		$this->req_method = 'GET';
		return $this->curl($this->url . $this->api_method);
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
