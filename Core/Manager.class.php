<?php


class Manager {
	
	protected static $project;
	protected static $project_path;
	protected static $staging;
	protected static $module;
	protected static $content;
	protected static $function;
	protected static $requested_module;
	protected static $requested_content;
	protected static $requested_function;
	protected static $requested_params = null;
	protected static $user_mode = FALSE;
	protected static $guest_mode = FALSE;
	protected static $user_contents;
	protected static $guest_contents;
	protected static $default_page;
	protected static $um_default;
	protected static $gm_default;
	protected static $controller;
	protected static $default_content_error;
	protected static $default_function_error;
	protected static $dbcheck; 
	
	/**
	 * Function to print the documentation of this Framework. 
	 * Could be helpful for firsttime use.
	 * Notice: Once executed the program will be exited.
	 * 
	 */
	public function printHelp(){
		$help = file_get_contents(realpath(dirname(__FILE__)).'/../README');
		if(isset($_SERVER['HTTP_USER_AGENT'])){
			$help = nl2br($help);
		} 
		echo $help;
		exit();
	}
	
	public function __construct(){
		//Do nothing. Don't want to initialize an object
	}
	
	/**
	 * Function to sanitize anykind of possible user inputs.
	 * 
	 * @param string|array $input
	 * @return string|array
	 */
	public static function sanitizeInput($input){
		if(is_array($input)){
			foreach($input as $key =>$in){
				$input[$key] = self::sanitizeInput($in);
			}
		}else{
			$input = trim($input);
			$input = stripslashes($input);
			$input = strip_tags($input);
			$input = htmlspecialchars($input);
		}
		return $input;
	}
	
	/**
	 * Setter for projectname.
	 * 
	 * @param string $projectname
	 * @return boolean
	 */
	public static function setProject($projectname){
		if(strlen($projectname)){
			self::$project = $projectname;
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Getter for projectname
	 * 
	 * @return string
	 */
	public static function getProject(){
		return self::$project;
	}
	
	/**
	 * Setter for projectpath
	 * 
	 * @param string $path
	 * @throws Exception
	 */
	public static function setProjectPath($path){
		if(file_exists($path)){
			self::$project_path = $path;
		}else{
			throw new Exception("Given path doesn't exist. Aborting.");
		}
	}
	
	/**
	 * Getter for projectpath.
	 * 
	 * @return string
	 */
	public static function getProjectPath(){
		return self::$project_path;
	}
	
	/**
	 * Setter for staging.
	 * 
	 * @param string $staging
	 * @return boolean
	 */
	public static function setStaging($staging){
		$staging = strtoupper($staging);
		switch($staging){
			case "RELEASE":
				self::$staging = $staging;
				break;
			case "TEST":
				self::$staging = $staging;
				break;
			case "LIVE":
				self::$staging = $staging;
				break;
			default:
				self::$staging = "RELEASE";
		}
		return true;
	}
	
	/**
	 * Getter for staging.
	 * 
	 * @return string
	 */
	public static function getStaging(){
		return self::$staging;
	}
	
	/**
	 * Function for setting up the ORM. Connecting to the database which is given in a specific file 
	 * project_path/configs/database_conf.php
	 * 
	 * @throws Exception
	 */
	public static function setupORM($type, $host, $db, $user, $password){
		try{
			R::addDatabase(self::getProject(), $type.":host=".$host.";dbname=".$db, $user, $password, true);
			R::selectDatabase(self::getProject());
			if(R::testConnection() === false){
				throw new Exception("Failed to open connection.");
			}
		}catch(Exception $e){
			self::$dbcheck = false;
			return true;
			//exit("Error occured while ORM Setup. Contact your Development Team. No database connection could be established. Error: ".$e->getMessage());
		}
		self::$dbcheck = true;
		return true;
	}
	
	
	public static function isDBSet(){
		if(isset(self::$dbcheck) === false){
			self::$dbcheck = false;
		}
		return self::$dbcheck;
	}
	
	public static function setDefaultPage($module, $content, $function){
		if(file_exists(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			if(method_exists($content, $function)){
				self::$default_page['module'] = $module;
				self::$default_page['content'] = $content;
				self::$default_page['function'] = $function;
			}else{
				throw new Exception("Given function for default page does not exist. Please check your controller.");
			}
		}else{
			throw new Exception("Given controller for default page does not exist. Please check your controller");
		}
	}
	
	/**
	 * Setter for mode with user management module.
	 * 
	 * @param boolean $flag
	 */
	public static function setUserMode($flag){
		if($flag === true){
			self::$user_mode = true;
		}else{
			self::$user_mode = false;
		}
	}
	
	/**
	 * Getter for user_mode;
	 * 
	 * @return boolean
	 */
	public static function getUserMode(){
		return self::$user_mode;
	}
	
	/**
	 * Setter for Guest mode. Initializing the user Management module with also guest mode.
	 * 
	 * @param boolean $flag
	 */
	public static function setGuestMode($flag){
		if($flag === true){
			self::$guest_mode = true;
		}else{
			self::$guest_mode = false;
		}
	}
	
	/**
	 * Getter for guest_mode flag.
	 * 
	 * @return boolean
	 */
	public static function getGuestMode(){
		return self::$guest_mode;
	}
	
	/**
	 * Setter for user contents.
	 * Provide contents as "[modulename]_[contentname]" within an array
	 * 
	 * @param array $contents
	 */
	public static function setUserContents($contents){
		if(is_array($contents)){
			self::$user_contents = $contents;
			self::$user_contents[] = self::$default_content_error['module'].'_'.self::$default_content_error['content'];
		}else{
			self::setUserMode(false);
		}
	}
	
	/**
	 * Getter for user contents.
	 * 
	 * @return array
	 */
	public static function getUserContents(){
		return self::$user_contents;
	}
	
	/**
	 * Setter for guest contents. 
	 * Provide contents as "[modulename]_[contentname]" within an array
	 * 
	 * @param array $contents
	 */
	public static function setGuestContents($contents){
		if(is_array($contents)){
			self::$guest_contents = $contents;
			self::$guest_contents[] = self::$default_content_error['module'].'_'.self::$default_content_error['content'];
		}else{
			self::setGuestMode(false);
		}
	}
	
	/**
	 * Getter for guest contents.
	 * 
	 * @return array
	 */
	public static function getGuestContents(){
		return self::$guest_contents;
	}
	
	/**
	 * Function to set default landing page if User Mode is set.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public static function setUserModeDefaultPage($module, $content, $function){
		if(file_exists(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			if(method_exists($content, $function)){
				self::$um_default['module'] = $module;
				self::$um_default['content'] = $content;
				self::$um_default['function'] = $function;
			}else{
				throw new Exception("Given function for UserMode default page does not exist. Please check your controller.");
			}
		}else{
			throw new Exception("Given controller for UserMode default page does not exist. Please check your controller.");
		}
	}
	
	/**
	 * Function to set default landing page if Guest Mode is set.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public static function setGuestModeDefaultPage($module, $content, $function){
		if(file_exists(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			if(method_exists($content, $function)){
				self::$gm_default['module'] = $module;
				self::$gm_default['content'] = $content;
				self::$gm_default['function'] = $function;
			}else{
				throw new Exception("Given function for GuestMode default page does not exist. Please check your controller.");
			}
		}else{
			throw new Exception("Given controller does not exist. Please check your controller.");
		}
	}
	
	/**
	 * Function to set the default error page if a content is requested which is not available.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public static function setDefaultContentError($module, $content, $function){
		if(file_exists(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			if(method_exists($content, $function)){
				self::$default_content_error['module'] = $module;
				self::$default_content_error['content'] = $content;
				self::$default_content_error['function'] = $function;
			}else{
				throw new Exception("Given default content error function does not exist. Please check your controller.");
			}
		}else{
			throw new Exception("Given default content error controller does not exist. Please check your controller.");
		}
	}
	
	/**
	 * Function to set the default error page if a function is requested which is not available.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public static function setDefaultFunctionError($module, $content, $function){
		if(file_exists(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(self::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			if(method_exists($content, $function)){
				self::$default_function_error['module'] = $module;
				self::$default_function_error['content'] = $content;
				self::$default_function_error['function'] = $function;
			}else{
				throw new Exception("Given default function error function deos not exist. Please check your controller.");
			}
		}else{
			throw new Exception("Givne default function error controller does not exist. Please check your controller.");
		}
	}
	
	/**
	 * Setter for module
	 * 
	 * @param string $module
	 * @return boolean
	 */
	protected static function setModule($module){
		$module = self::sanitizeInput($module);
		self::$requested_module = $module;
		if(is_string($module) && strlen($module) > 0){
			if(file_exists(sprintf(self::$project_path.'/controller/%s', $module))){
				self::$module = $module;
				return true;
			}
			self::$module = self::$default_content_error['module'];
			self::$content = self::$default_content_error['content'];
			self::$function = self::$default_content_error['function'];
			return true;
		}
		if(isset(self::$default_page['module']) === false){
			throw new Exception("No default controller is set. Please use function ".__CLASS__."::setDefaultPage().");
		}
		self::$module = self::$default_page['module'];
		return true;
	}
	
	/**
	 * Getter for module
	 * 
	 * @return string
	 */
	public static function getModule(){
		return self::$module;
	}
	
	/**
	 * Getter for initially requested module.
	 * 
	 * @return string
	 */
	public static function getRequestedModule(){
		return self::$requested_module;
	}
	
	/**
	 * Setter for Content 
	 * 
	 * @param string $content
	 * @throws Exception
	 * @return boolean
	 */
	protected static function setContent($content){
		$content = self::sanitizeInput($content);
		self::$requested_content = $content;
		if(isset(self::$content)){
			return true;
		}
		if(isset(self::$module) && is_string($content) && strlen($content) > 0){
			if(file_exists(sprintf(self::getProjectPath().'/controller/'.self::getModule().'/%s.php', $content))){
				require_once(sprintf(self::getProjectPath().'/controller/'.self::getModule().'/%s.php', $content));
				if(class_exists($content)){
					self::$content = $content;
					return true;
				}
			}
			self::$module = self::$default_content_error['module'];
			self::$content = self::$default_content_error['content'];
			self::$function = self::$default_content_error['function'];
			return true;
		}
		if(isset(self::$default_page['content']) === false){
			throw new Exception("No default controller is set. Please use function ".__CLASS__."::setDefaultPage().");
		}
		self::$content = self::$default_page['content'];
		return true;
	}
	
	/**
	 * Getter for content
	 * @return string
	 */
	public static function getContent(){
		return self::$content;
	}
	
	/**
	 * Getter for requested content
	 * 
	 * @return string
	 */
	public static function getRequestedContent(){
		return self::$requested_content;
	}
	
	/**
	 * Setter for function
	 * @param string $function
	 * @throws Exception
	 * @return boolean
	 */
	protected static function setFunction($function){
		$function = self::sanitizeInput($function);
		self::$requested_function = $function;
		if(isset(self::$function)){
			return true;
		}
		if(strlen($function) > 0){
			if(isset(self::$module) && isset(self::$content) && method_exists(self::$content, $function)){
				self::$function = $function;
				return true;
			}else{
				self::$module = self::$default_content_error['module'];
				self::$content = self::$default_content_error['content'];
				self::$function = self::$default_content_error['function'];
				return true;
			}
		}
		if(isset(self::$default_page['function']) === false){
			throw new Exception("No default controller function is set. Please use function ".__CLASS__."::setDefaultPage().");
		}
		self::$function = self::$default_page['function'];
		return true;
	}
	
	/**
	 * Getter for function.
	 * 
	 * @return string
	 */
	public static function getFunction(){
		return self::$function;
	}
	
	/**
	 * Getter for initially requested function.
	 * 
	 * @return string
	 */
	public static function getRequestedFunction(){
		return self::$requested_function;
	}
	
	/**
	 * Function to set requested param.
	 * 
	 * @param string $param
	 */
	public static function setParam($param){
		$param = self::sanitizeInput($param);
		if(is_array(self::$requested_params) === false){
			self::$requested_params = array();
		}
		self::$requested_params[] = $param;
	}
	
	/**
	 * Function to return requested params.
	 * 
	 * @return array
	 */
	public static function getRequestedParams(){
		return self::$requested_params;
	}
	
	/**
	 * Function to load controller.
	 * 
	 * @return boolean
	 */
	protected static function loadController(){
		if(isset(self::$module) === false || isset(self::$content) === false){
			throw new Exception("Controller cannot be loaded. Controller is not specified.");
		}
		$controller = new self::$content();
		if(is_a($controller, "Controller")){
			self::$controller = new $controller();
		}else{
			throw new Exception("Failed to load controller. Loaded class is not a instance of Class Controller");
		}
		return true;
	}
	
	
	/**
	 * Performs the function with loaded controller and returns the values.
	 * 
	 * @throws Exception
	 */
	protected static function performFunction(){
		if(isset(self::$controller) === false){
			throw new Exception("Function cannot be loaded. No Controller instanciated.");
		}
		try{
			$return = self::$controller->initializeFunction(self::getFunction(), self::getRequestedParams());
		}catch(Exception $e){
			require_once(self::getProjectPath().'/controller/'.self::$default_function_error['module'].'/'.self::$default_function_error['content'].'.php');
			self::$controller = new self::$default_function_error['content']();
			$return = self::$controller->initializeFunction(self::$default_function_error['function'], $e);
		}
		return $return;
	}
	
	
}