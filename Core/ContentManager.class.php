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
		if(isset($_SERVER['HTTP_NETROWORKSWEBSERVICE']) === true){
			self::$webservicecall = true;
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
	
	public static function isWebServiceCall(){
		if(self::$webservicecall === true){
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
			if(self::checkContentStructure() === false){
				self::buildContentStructure();
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
		$dirs = scandir($controllerpath);
		foreach($dirs as $moduledir){
			if($moduledir != '.' && $moduledir != '..' && is_dir($controllerpath.$moduledir)){
				$possiblemodule = R::findOne('module', 'modulename = ?', array($moduledir));
				if(!$possiblemodule->id){
					return false;
				}
				$moduleids[] = $possiblemodule->id;
				$contentfiles = scandir($controllerpath.$moduledir.'/');
				foreach($contentfiles as $content){
					if(is_file($controllerpath.$moduledir.'/'.$content)){
						$tmp = explode(".", $content);
						$contentname = $tmp[0];
						$registeredContent = R::findOne('content', 'contentname = ?', array($contentname));
						if(!$registeredContent->id){
							return false;
						}
						$sharedcontent = $possiblemodule->sharedContentList[$registeredContent->id];
						if(!$sharedcontent->id){
							return false;
						}
						$contentids[] = $sharedcontent->id;
					}
				}
			}
		}
		if(is_array($moduleids)){
			$invalidmodules = R::find('module', 'id NOT IN ('.R::genSlots($moduleids).')', $moduleids);
			if(sizeof($invalidmodules) > 0){
				return false;
			}
		}
		if(is_array($contentids)){
			$invalidcontents = R::find('content', 'id NOT IN ('.R::genSlots($contentids).')', $contentids);
			if(sizeof($invalidcontents) > 0){
				return false;
			}
		}
		return true;
	}
	
	/**
	 * After structure has been changed: this function will build the correct relations between modules and contents within database.
	 * 
	 */
	private static function buildContentStructure(){
		try{
			$controllerpath = self::getProjectPath().'/controller/';
			$possiblemodules = scandir($controllerpath);
			$registeredmodules = R::findAll('module');
			foreach($registeredmodules as $regmodule){
				if(!in_array($regmodule->modulename, $possiblemodules)){
					$dependingContents = $regmodule->sharedContentList;
					foreach($dependingContents as $dependcontent){
						$dependmodules = $dependcontent->withCondition('modulename != ?',array($regmodule->modulename))->sharedModuleList;
						if(empty($dependmodules)){
							R::trash($dependcontent);
						}
					}
					R::trash($regmodule);
				}else{
					$dependingContents = $regmodule->sharedContentList;
					$possiblecontent = scandir($controllerpath.$regmodule->modulename.'/');
					foreach($dependingContents as $dependcontent){
						if(!in_array($dependcontent->contentname.'.php', $possiblecontent)){
							$dependmodules = $dependcontent->withCondition('modulename != ?',array($regmodule->modulename))->sharedModuleList;
							unset($regmodule->sharedContentList[$dependcontent->id]);
							R::store($regmodule);
							if(empty($dependmodules)){
								R::trash($dependcontent);
							}
						}
					}
				}
			}
			foreach($possiblemodules as $dirname){
				if(is_dir($controllerpath.$dirname) && $dirname != '.' &&  $dirname != '..'){
					$module = R::findOne('module', 'modulename = ?' , array($dirname));
					if($module == null){
						$module = R::dispense('module');
						$module->modulename = $dirname;
					}
					$possiblecontent = scandir($controllerpath.$dirname.'/');
					foreach($possiblecontent as $file){
						if(is_file($controllerpath.$dirname.'/'.$file)){
							$tmp = explode('.', $file);
							$contentname = $tmp[0];
							$content = R::findOne('content', 'contentname = ?', array($contentname));
							if($content == null){
								$content = R::dispense('content');
								$content->contentname = $contentname;
								R::store($content);
								$module->sharedContentList[] = $content;
							}else{
								$check = $content->withCondition('modulename = ?',array($module->modulename))->sharedModuleList;
								if(empty($check)){
									$module->sharedContentList[] = $content;
								}
							}
						}
					}
					R::begin();
					try{
						R::store($module);
						R::commit();
					}catch(Exception $e){
						R::rollback();
					}
				}
			}
		}catch(Exception $e){
			exit("Error occured while building content structure. Contact your Development Team.");
		}
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