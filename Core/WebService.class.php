<?php

class WebService {
	
	private $ch;
	private $username;
	private $password;
	private $url;
	private $request_method = "POST";
	private $request_params;
	private $module;
	private $content;
	private $function;
	private $cookiefile;
	private $auth;
	
	public static function getWebServiceObject($name){
		if(file_exists(ContentManager::getProjectPath().'/webservice/'.$name.'.php')){
			require_once(ContentManager::getProjectPath().'/webservice/'.$name.'.php');
			return new $name();
		}else{
			throw new Exception("Webservice does not exist. PLease ensure to have the correct location.");
		}
	}
	
	public function __construct($url, $username = false, $password = false){
		$this->setUrl($url);
		$this->username = $username;
		$this->password = $password;
		$this->ch = curl_init();
		$this->cookiefile = sys_get_temp_dir().__CLASS__.date("d-m-Y H:i:s", strtotime("now"));
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookiefile);
		curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt($this->ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
		if($username != false && $password != false){
			$this->auth();
		}
	}
	
	protected function auth(){ 
		$this->setRequestMethod("post");
		$posts = array("username" => $this->username, "password" => $this->password);
		$this->setRequestParams($posts);
		$this->auth = true;
		$answer = $this->exec();
		$answer = json_decode($answer, true);
		if($answer['error']){
			throw new Exception("Authentication failed. Please check given credentials.");
		}
		$this->auth = false;
		curl_setopt($this->ch, CURLOPT_POST, false);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, "");
	}
	 
	protected function setRequestMethod($type){
		$type = strtoUpper($type);
		switch($type){
			case "GET":
				$this->request_method = "GET";
				curl_setopt($this->ch, CURLOPT_HTTPGET, true);
				curl_setopt($this->ch, CURLOPT_POST, false);
				break;
			case "PUT":
			case "DELETE":
			default:
				$this->request_method = "POST";
				curl_setopt($this->ch, CURLOPT_HTTPGET, false);
				curl_setopt($this->ch, CURLOPT_POST, true);
				
		}
		return $this;
	}
	
	protected function setRequestParams($params){
		if(is_array($params)){
			$this->request_params = json_encode($params);
		}else{
			$array = array("value" => $params);
			$this->request_params = json_encode($array);
		}
		return $this;
	}
	
	protected function getRequestParams(){
		if(isset($this->request_params)){
			return "jsonparams=".$this->request_params;
		}else{
			return "";
		}
	}
	
	protected function setUrl($url){
		$this->url = $url;
	}
	
	protected function getUrl(){
		$return = $this->url;
		if(isset($this->module) && isset($this->content) && isset($this->function)){
			$return .= '/';
			$return .= $this->module;
			$return .= '/';
			$return .= $this->content;
			$return .= '/';
			$return .= $this->function;
			$return .= '/';
		}
		return $return;
	}
	
	protected function setVerifyPeer($boolean = false){
		if($boolean){
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 1);
		}else{
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0);
		}
	}
	
	protected function setModule($module){
		$this->module = $module;
		return $this;
	}
	
	protected function setContent($content){
		$this->content = $content;
		return $this;
	}
	
	protected function setFunction($function){
		$this->function = $function;
		return $this;
	}
	
	protected function exec(){
		if($this->auth == false && (isset($this->module) === false || isset($this->content) === false || isset($this->function) === false)){
			throw new Exception("You must specify a module, content and function to access propper api functions.");
		}
		if($this->request_method == "POST"){
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->getRequestParams());
			curl_setopt($this->ch, CURLOPT_URL, $this->getUrl());
		}else{
			curl_setopt($this->ch, CURLOPT_URL, $this->getUrl().'?'.$this->getRequestParams());
		}
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array("NetroWorksWebService:".true,
														"Content-Type:application/json"));
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		$answer = curl_exec($this->ch);
		if(curl_error($this->ch)){
			throw new Exception("Curl Request failed. Try again or check connection to remote host. Curl Error message was: ".curl_error($this->ch));
		}
		return $answer;
	}
}