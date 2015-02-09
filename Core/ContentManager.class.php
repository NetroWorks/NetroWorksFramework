<?php

require_once(realpath(dirname(__FILE__)).'/Manager.class.php');
require_once(realpath(dirname(__FILE__)).'/Controller.class.php');
require_once(realpath(dirname(__FILE__)).'/UserManager.class.php');
require_once(realpath(dirname(__FILE__)).'/Model.class.php');
require_once(realpath(dirname(__FILE__)).'/WebService.class.php');
require_once(realpath(dirname(__FILE__)).'/../LIBS/RedBean/rb.php');
require_once(realpath(dirname(__FILE__)).'/../LIBS/Smarty/Smarty.class.php');

/**
 * Part of NetroWorksFrameWork:
 * ContentManager initializing the content for the webapplication. Read Documentation for further use.
 * 
 * @uses: RedBeanPHP ORM, Smarty Template Engine, NetroWorksFrameWork\Manager, NetroWorksFrameWork\Controller
 * @uses: NetroWorksFrameWork\UserManager, NetroWorksFrameWork\Model
 * @author NetroWorksSystems 
 *
 */
class ContentManager extends Manager {
	
	private static $smarty;
	private static $output = "";
	private static $views;
	private static $side_controllers = array();
	private static $webservicecall;
	
	/**
	 * Routine of Content Manager.
	 * Let's start!
	 * Please check the order!
	 * Required before: 
	 * 	ContentManager::setProject()
	 * 	ContentManager::setProejctPath()
	 *  ContentManager::setupORM()
	 * 	ContentManager::setStaging()
	 * 	ContentManager::setDefaultPage()
	 * 	ContentManager::setDefaultContentError()
	 * 	ContentManager::setDefaultFunctionError()
	 * 
	 * if User management is required:
	 * 	ContentManager::setUserMode()
	 * 	ContentManager::setUserContents()
	 * 	ContentManager::setUserModeDefaultPage()
	 * 
	 * if guest mode is required in addition:
	 * 	ContentManager::setGuestMode()
	 * 	ContentManager::setGuestContents()
	 * 	ContentManager::setGuestModeDefaultPage()
	 */
	public static function buildContent(){
		if(isset(self::$project) === false || isset(self::$project_path) === false || isset(self::$staging) === false 
				||isset(self::$default_content_error) === false || isset(self::$default_function_error) === false){
			exit("Could not build content. Required functions were not called.");
		}
		if(isset(self::$dbcheck) === false){	
			self::$dbcheck = false;
		}
		self::setupSmarty();
		self::initializeContent();
		self::parseRequest();
		if(self::isWebServiceCall()){
			self::parseWebServiceRequest();
		}
		self::initializeUserManager();
		self::loadController();
		$return = self::performFunction();
		if(self::isWebServiceCall()){
			header('Content-Type: application/json');
			print(json_encode($return));
		}else{
			self::loadSideControllers();
			self::loadViews();
			print self::getOutput();
		}
	}
	
	public static function parseWebServiceRequest(){
		if(isset($_POST['jsonparams'])){
			$jsonparams = json_decode($_POST['jsonparams'], true);
			$jsonparams = self::sanitizeInput($jsonparams);
			foreach($jsonparams as $key => $value){
				self::$requested_params[$key] = $value;
			}
		}elseif(isset($_GET['jsonparams'])){
			$jsonparams = json_decode($_GET['jsonparams'], true);
			$jsonparams = self::sanitizeInput($jsonparams);
			foreach($jsonparams as $key => $value){
				self::$requested_params[$key] = $value;
			}
		}
	}
	
	/**
	 * Function to check if project was called as WebService or normal Webapplication request.
	 * 
	 * @return boolean
	 */
	public static function isWebServiceCall(){
		if(isset($_SERVER['HTTP_NETROWORKSWEBSERVICE']) === true  || (isset($_SERVER['CONTENT_TYPE']) === true && preg_match('/json/', $_SERVER['CONTENT_TYPE'])) ){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Function to initialize content of the project within the database to provide correct entities of contents and modules.
	 * 
	 * @return boolean
	 */
	private static function initializeContent(){
		if(self::$dbcheck){
			$possible_controllers = self::checkContentStructure();
			if(is_array($possible_controllers)){
				self::buildContentStructure($possible_controllers);
				UserManager::refreshInitialContent();
			}
		}
		return true;
	}
	
	/**
	 * Looks up filesystem and compares stored contents within database to decide if structure has been changed.
	 * 
	 * @return boolean
	 */
	private static function checkContentStructure(){
		$controllerpath = self::getProjectPath().'/controller/';
		$possible_controllers = array();
		$dirs = scandir($controllerpath);
		foreach($dirs as $key => $dir){
			if(is_dir($controllerpath.$dir) && $dir != '.' && $dir != '..'){
				$contents = scandir($controllerpath.$dir);
				foreach($contents as $content){
					if(preg_match('/\.php$/', $content)){
						$content = explode('.', $content);
						$content = $content[0];
						$possible_controllers[] = array("module_name" => $dir,
														"content_name" => $content);
					}
				}
			}
		}
		$registered_controllers = R::find('controller');
		if(empty($registered_controllers) === false){
			if(count($possible_controllers) != sizeof($registered_controllers)){
				return $possible_controllers;
			}
			foreach($registered_controllers as $controller){
				if(sizeof(array_keys($possible_controllers, array("module_name" => $controller->module->modulename, "content_name" => $controller->content->contentname))) == 0){
					return $possible_controllers;
				}
			}
		}elseif(sizeof($possible_controllers) > 0){
			return $possible_controllers;
		}
		return true;
	}
	
	/**
	 * After structure has been changed: this function will build the correct relations between modules and contents within database.
	 * 
	 */
	private static function buildContentStructure($possible_controllers){
		R::freeze(false);
		R::begin();
		try{
			$registered_controllers = R::find('controller');
			$controllers = array();
			if(empty($registered_controllers)){
				//Just to be sure, wipe all possible remaining data
				R::wipe('controller');
				R::wipe('module');
				R::wipe('content');
			}else{
				foreach($registered_controllers as $key => $r_controller){
					if(sizeof(array_keys($possible_controllers, array("module_name" => $r_controller->module->modulename, "content_name" => $r_controller->content->contentname))) == 0){
						R::trash($r_controller);
						unset($registered_controllers[$key]);
					}
				}
			}
			if(sizeof($possible_controllers) == 0){
				R::wipe('controller');
				R::wipe('module');
				R::wipe('content');
			}else{
				$modules = array();
				$contents = array();
				foreach($possible_controllers as $p_controller){
					$modules[$p_controller['module_name']] = true;
					$module = R::findOne('module', 'modulename = ?', array($p_controller['module_name']));
					if($module->id == 0){
						$module = R::dispense('module');
						$module->modulename = $p_controller['module_name'];
						$m_id = R::store($module);
						$module = R::load('module', $m_id);
					}
					$contents[$p_controller['content_name']] = true;
					$content = R::findOne('content', 'contentname = ?', array($p_controller['content_name']));
					if($content->id == 0){
						$content = R::dispense('content');
						$content->contentname = $p_controller['content_name'];
						$c_id = R::store($content);
						$content = R::load('content', $c_id);
					}
					$controller = R::findOne('controller', 'content_id = :cid AND module_id = :mid', array(":mid" => $module->id, ":cid" => $content->id));
					if($controller->id == 0){
						$controller = R::dispense('controller');
						$controller->module = $module;
						$controller->content = $content;
					}
					$controllers[] = $controller;
				}
				R::storeAll($controllers);
				R::exec('DELETE FROM content WHERE contentname NOT IN ('.R::genSlots(array_keys($contents)).')', array_keys($contents));
				R::exec('DELETE FROM module WHERE modulename NOT IN ('.R::genSlots(array_keys($modules)).')', array_keys($modules));
			}
		}catch(Exception $e){
			R::rollback();
			exit("Error occured while building content structure. Contact your Development Team.");
		}
		R::freeze(true);
		return true;
	}
	
	/**
	 * Initial Setup of smarty template Engine. This defines the required folder structure within the project.
	 * 
	 */
	private static function setupSmarty(){
		self::$smarty = new Smarty();
		self::$smarty->setTemplateDir(self::getProjectPath().'/templates/');
		self::$smarty->setCompileDir(self::getProjectPath().'/templates_c/');
		self::$smarty->setConfigDir(self::getProjectPath().'/configs/');
		self::$smarty->setCacheDir(self::getProjectPath().'/cache/');
	}
	
	/**
	 * Function to assign values to smarty. 
	 * 
	 * @param string $key
	 * @param string|array $data
	 */
	public static function assignToSmarty($key, $data){
		self::$smarty->assign($key, $data);
	}
	
	/**
	 * Function to load views from controller and building the output.
	 */
	public static function loadViews(){
		self::$views = self::$controller->getViews();
		if(is_array(self::$views) && sizeof(self::$views) > 0){
			foreach(self::$views as $view){
				if(self::$smarty->templateExists($view)){
					self::$output .= self::$smarty->fetch($view);
				}
			}
		}
		if(!strlen(self::$output)){
			self::$output .= "<body><p>Could not find views.</p><p>Please ensure to have views available with correct syntax.</p></body>";
		}
	}
	
	/**
	 * Function to load additional controllers, which could be required from other controllers.
	 * Stored as array within an array. 
	 * array ( 1 => array( "module" => $value,
	 * 						"content" => $value,
	 * 						"function" => $value))
	 */
	public static function loadSideControllers(){
		$names = self::$controller->getSideControllers();
		if(sizeof($names) > 0){
			foreach($names as $key => $controller){
				if(file_exists(self::getProjectPath().'/controller/'.$controller['module'].'/'.$controller['content'].'.php')){
					require_once(self::getProjectPath().'/controller/'.$controller['module'].'/'.$controller['content'].'.php');
					if(class_exists($controller['content']) && method_exists($controller['content'], $controller['function'])){
						self::$side_controllers[$key] = new $controller['content']();
						try{
							self::$side_controllers[$key]->initializeFunction($controller['function'], ContentManager::getRequestedParams());
						}catch(Exception $e){
							require_once(self::getProjectPath().'/controller/'.self::$default_function_error['module'].'/'.self::$default_function_error['content'].'.php');
							self::$controller = new self::$default_function_error['content']();
							self::$controller->initializeFunction(self::$default_function_error['function'], $e);
						}
					}
				}
			}
		}
	}
	
	/**
	 * Function to pass URI to get the requested module, content and function and other additional params.
	 * Everything passed after index.php/.../.../../ seperated with "/" are target.
	 * The first value is module, the second is controller and the third is function. All others after it are params
	 */
	public static function parseRequest(){
		if(isset($_SERVER['PATH_INFO'])){
			$pathinfo = $_SERVER['PATH_INFO'];
			$tmp = explode("/", $pathinfo);
			$check = 0;
			if($pathinfo != '/' && sizeof($tmp) > 0){
				foreach($tmp as $pinfo){
					if(strlen($pinfo) > 0){
						switch($check){
							case 0:
								self::setModule($pinfo);
								$check++;
								break;
							case 1:
								self::setContent($pinfo);
								$check++;
								break;
							case 2:
								self::setFunction($pinfo);
								$check++;
								break;
							default:
								self::setParam($pinfo);
						}
					}
				}
				return true;
			}
		}
		self::setModule("");
		self::setContent("");
		self::setFunction("");
		return true;
	}
	
	/**
	 * Initializes the UserManager.
	 */
	public static function initializeUserManager(){
		if(self::getUserMode() || self::getGuestMode()){
			UserManager::initializeUser();
		}
		return true;
	}
	
	/**
	 * Function to get the builded output after function loadViews().
	 * 
	 * @return string
	 */
	public static function getOutput(){
		return self::$output;
	}
	
	
}