<?php

/**
 * Part of NetroWorksFrameWork:
 * Parent Controller class for controller imeplementation.
 * Foreach custom controller extend this class to have all required functionality.
 * 
 * @author NetroWorksSystems
 *
 */
class Controller{
	
	private $views = array();
	private $side_controllers = array();
	
	
	/**
	 * Function to add a view (template) for this controller or function call.
	 * keep in mind that you add the views in order how they should be build.
	 * 
	 * @param string $viewname
	 */
	protected function addView($viewname){
		$this->views[] = $viewname;
	}
	
	/**
	 * Function to get stored views.
	 * 
	 * @return array
	 */
	public function getViews(){
		return $this->views;
	}
	
	/**
	 * Function to unset stored views.
	 * 
	 */
	public function unsetViews(){
		$this->views = array();
	}
	
	/**
	 * Function for assigning key, value pairs to the template. 
	 * Similar to smarty assign function.
	 * 
	 * @param string $key
	 * @param string|array $data
	 */
	protected function assign($key, $data){
		ContentManager::assignToSmarty($key, $data);
	}
	
	/**
	 * Function to add additional controller and function to be called.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public function addSideController($module, $content, $function){
		$check = 0;
		if(sizeof($this->side_controllers) > 0){
			foreach($this->side_controllers as $controller){
				if($controller['module'] == $module && $controller['content'] == $content && $controller['function'] == $function){
					$check = 1;
				}
			}
		}
		if($check == 0){
			$this->side_controllers[] = array(
											'module' => $module, 
											'content' => $content,
											'function' => $function);
		}
	}
	
	/**
	 * Function which let the controller behave as it is the specified controller.
	 * Notice: Be careful, as it will reset all side controllers and views to actually behave the same as the called controller
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 */
	public function switchToControllerFunction($module, $content, $function){
		if(file_exists(ContentManager::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(ContentManager::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			$controller = new $content();
			$controller->initializeFunction($function, ContentManager::getRequestedParams());
			$views = $controller->getViews();
			$this->unsetViews();
			foreach($views as $view){
				$this->addView($view);
			}
			$sidecontrollers = $controller->getSideControllers();
			$this->unsetSideControllers();
			foreach($sidecontrollers as $cont){
				$this->addSideController($cont['module'], $cont['content'], $cont['function']);
			}
		}else{
			throw new Exception("Switch to other Controller failed. Given Controller does not exist.");
		}
	}
	
	/**
	 * Function to get a controller object. 
	 * 
	 * @param string $module
	 * @param string $content
	 */
	public function getControllerObject($module, $content){
		if(file_exists(ContentManager::getProjectPath().'/controller/'.$module.'/'.$content.'.php')){
			require_once(ContentManager::getProjectPath().'/controller/'.$module.'/'.$content.'.php');
			return new $content();
		}
		return false;
	}
	/**
	 * Base function if request is made on content which is not allowed for the registered user / guest.
	 * 
	 * If you want to use custom pages just override this function within own controller subclasses.
	 */
	public function viewNotAllowed(){
		if(ContentManager::isWebServiceCall()){
			$return = array("error" => "Access denied");
			return $return;
		}
		$this->unsetViews();
		$template = '<html>
						<head>
							<title>{$project_title}</title>
							<style type="text/css">
								.well {
								  min-height: 20px;
								  padding: 19px;
								  margin-bottom: 20px;
								  background-color: #f5f5f5;
								  border: 1px solid #e3e3e3;
								  border-radius: 4px;
								  -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, .05);
								          box-shadow: inset 0 1px 1px rgba(0, 0, 0, .05);
								}
								h3 {
								  font-family: inherit;
								  font-weight: 500;
								  line-height: 1.1;
								  color: inherit;
								}
							</style>
						</head>
						<body>
							<div class="well">
								<h3>Sorry!</h3>
								<p>You are not allowed to view the requested page</p>
							</div>
						</body>
					</html>';
		$this->assign("project_title", ContentManager::getProject());
		$this->addView("eval:".$template);
	}
	
	/**
	 * Function to build a link, which can be directly returned to a anchor href.
	 * Where module and content defines the controller and function the to be called function of the controller.
	 * Params is an array with additional params which will be added to the link.
	 * 
	 * @param string $module
	 * @param string $content
	 * @param string $function
	 * @param array $params
	 * @return string
	 */
	public function buildhref($module, $content, $function, $params = null){
		$phpself = $_SERVER['SCRIPT_NAME'];
		$link = $phpself."/".$module."/".$content."/".$function."/";
		
		if(is_array($params)){
			$link .= implode("/", $params).'/';
		}
		return $link;
	}
	
	
	/**
	 * Function to get added controllers which should be called in addition.
	 * 
	 * @return multitype:
	 */
	public function getSideControllers(){
		return $this->side_controllers;
	}
	
	/**
	 * Function to unset stored side controller.s
	 */
	public function unsetSideControllers(){
		$this->side_controllers = array();
	}
	
	/**
	 * Base function to perform a controller function given as string.
	 * 
	 * @param string $functionname
	 */
	public function initializeFunction($functionname = 'init', $params = null){
		if(is_string($functionname)){
			if(method_exists($this, $functionname)){
				return $this->$functionname($params);
			}else{
				throw new Exception("Given function does not exist.");
			}
		
		}
	}
}