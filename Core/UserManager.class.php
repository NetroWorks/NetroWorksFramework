<?php

/**
 * Part of NetroWorksFrameWork:
 * UserManager class to provide build in user and guest management.
 * 
 * @uses: RedBeanPHP ORM, NetroWorksFrameWork\ContentManager, NetroWorksFrameWork\Manager
 * @author NetroWorksSystems
 *
 */
class UserManager extends Manager {
	
	private static $SALT = '$6$rounds=9000$pxc5rbpfmjmfY$';
	private static $member_bean;
	private static $role_bean;
	
	/**
	 * Initializes the user. Decision is made if the var "$_SESSION['userid']" is set or not.
	 * 
	 * @return boolean
	 */
	public static function initializeUser(){
		session_start();
		if(self::getUserMode() === false || self::$dbcheck === false){
			return true;
		}
		if(isset($_SESSION['userid'], $_SESSION['logintime']) === true && ($_SESSION['logintime'] - time()) > 1800 ){
			self::logout();
		}
		if(isset($_SESSION['userid']) && is_numeric($_SESSION['userid']) || self::getGuestMode()){
			if(isset($_SESSION['userid'])){
				$member_bean = R::load('member', $_SESSION['userid']);
			}
			if(self::getGuestMode()){
				$member_bean = self::registerGuestMember();
			}
			if($member_bean->id){
				self::$member_bean = $member_bean;
				$role_bean = self::$member_bean->role;
				if($role_bean->id){
					self::$role_bean = $role_bean;
				}else{
					if(self::getGuestMode() && self::$member_bean->login == 'guest'){
						self::$member_bean->role = R::findOne('role', 'rolename = ?', array("guest"));
					}else{
						if(self::isAdminExistent()){
							self::$member_bean->role = R::findOne('role', 'rolename = ?', array("member")); 
						}else{
							self::$member_bean->role = R::findOne('role', 'rolename = ?', array("admin"));
						}
					}
					R::begin();
					try{
						$mid = R::store($member_bean);
						R::commit();
						self::$member_bean = R::load('member', $mid);
						self::$role_bean = $member_bean->role;
					}catch(Exception $e){
						R::rollback();
						unset(self::$member_bean);
						unset(self::$role_bean);
					}
				}
			}
		}
		self::initializeContentForUser();
	}
	
	private static function registerGuestMember(){
		$member = R::findOne('member', 'login = ?', array("guest"));
		if($member->id){
			return $member;
		}
		$member = R::dispense('member');
		$member->login = "guest";
		$member->loginable = false;
		try{
			$mid = R::store($member);
			R::commit();
			$member = R::load('member', $mid);
		}catch(Exception $e){
			R::rollback();
		}
		return $member;
	}
	
	/**
	 * Checks if the user or guest is allowed to see the requested content.
	 * 
	 * @return boolean
	 */
	private static function initializeContentForUser(){
		if(isset(self::$member_bean) && isset(self::$role_bean)){
			$contents = self::getContentsForUser();
			if(sizeof($contents)){
				foreach($contents as $content){
					ContentManager::assignToSmarty("allowed_for_".$content['modulename'].'_'.$content['contentname'], true);
				}
			}
			if(self::isAllowedFor() === false){
				self::$function = "viewNotAllowed";
			}
			return true;
		}
		if(self::$module == self::$um_default['module'] && self::$content == self::$um_default['content']){
			return true;
		}
		self::$module = self::$um_default['module'];
		self::$content = self::$um_default['content'];
		self::$function = self::$um_default['function'];
		return true;
	}
	
	/**
	 * Function to get contents assigned to the role of user.
	 */
	private static function getContentsForUser(){
		return R::getAll("SELECT content.contentname, module.modulename FROM
								content, module, content_module, roletomodcon
								WHERE content.id = content_module.content_id
								AND module.id = content_module.module_id
								AND roletomodcon.content_module_id = content_module.id
								AND roletomodcon.role_id = ? ", array(self::$role_bean->id));
	}
	
	/**
	 * Function to check if already a user with admin role is existent in project.
	 * 
	 * @return boolean
	 */
	private static function isAdminExistent(){
		$role_bean = R::findOne('role', 'rolename = ?', array('admin'));
		$user_bean = R::findOne('member', 'role_id = ?', array($role_bean->id));
		if($user_bean->id){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Refreshes or creates database content entries for at least "admin" and "member" if user mode is set.
	 * If guest mode is set it will create in addtion contents for "guest". If no roles available, they will create.
	 * 
	 * @return boolean
	 */
	public static function refreshInitialContent(){
		if(self::getGuestMode()){
			self::createRoleContents("guest", self::getGuestContents() + array(self::$default_content_error['module'].'_'.self::$default_content_error['content']));
		}
		self::createRoleContents("member", self::getUserContents() + array(self::$default_content_error['module'].'_'.self::$default_content_error['content']));
		self::createAdminContent();
	}
	
	/**
	 * Function to create the initial content within the database. 
	 * Information is stored via Manager::setGuestContents() || Manager::setUserContents();
	 * 
	 * @param string $rolename
	 * @param array $membercontents
	 */
	public static function createRoleContents($rolename, $contents){
		$role_bean = self::registerRole($rolename);
		foreach($contents as $membercontent){
			$tmp = explode('_', $membercontent);
			$module = $tmp[0];
			$content = $tmp[1];
			$module_bean = R::findOne('module', 'modulename = ?', array($module));
			$content_bean = R::findOne('content', 'contentname = ?', array($content));
			if($module_bean->id && $content_bean->id){
				$sharedModuleContent = R::findOne('content_module', 'module_id = :mid AND content_id = :cid', array(':mid' => $module_bean->id, ':cid' => $content_bean->id));
				if($sharedModuleContent->id){
					$assignedroles = $sharedModuleContent->via('roletomodcon')->sharedRoleList;
					$check = false;
					foreach($assignedroles as $assignedrole){
						if($assignedrole->id == $role_bean->id){
							$check = true;
						}
					}
					if($check == false){
						$role_bean->link('roletomodcon')->contentModule = $sharedModuleContent;
					}
				}
			}
		}
		R::begin();
		try{
			$roleid = R::store($role_bean);
			R::commit();
		}catch(Exception $e){
			R::rollback();
			return false;
		}
		$role_bean = R::load('role', $roleid);
		$roletomodcons = $role_bean->ownRoletomodconList;
		foreach($roletomodcons as $roletomodcon){
			$contentModule = $roletomodcon->contentModule;
			if(!$contentModule->id){
				unset($role_bean->ownRoletomodconList[$roletomodcon->id]);
				$roleid = R::store($role_bean);
				$role_bean = R::load('role', $roleid);
				R::trash($roletomodcon);
			}else{
				$registeredContent = $contentModule->content;
				$registeredModule = $contentModule->module;
				if($registeredContent->id && $registeredModule->id){
					$check = false;
					foreach($contents as $membercontent){
						$tmp = explode('_', $membercontent);
						$module = $tmp[0];
						$content = $tmp[1];
						if($module == $registeredModule->modulename && $content == $registeredContent->contentname){
							$check = true;
						}
					}
					if($check == false){
						unset($role_bean->ownRoletomodcon[$roletomodcon->id]);
						$roleid = R::store($role_bean);
						$role_bean = R::load('role', $roleid);
						R::trash($roletomodcon);
					}
				}
			}
		}
		return true;
	}
	
	/**
	 * Creates and updates initial admin content. Which is almost all content.
	 * 
	 * @return boolean
	 */
	public static function createAdminContent(){
		$role_bean = self::registerRole('admin');
		$roletomodcons = $role_bean->ownRoletomodconList;
		foreach($roletomodcons as $roletomodcon){
			$contentModule = $roletomodcon->contentModule;
			if(!$contentModule->id){
				unset($role_bean->ownRoletomodconList[$roletomodcon->id]);
				$roleid = R::store($role_bean);
				$role_bean = R::load('role', $roleid);
				R::trash($roletomodcon);
			}
		}
		$sharedModuleContents = R::findAll('content_module');
		foreach($sharedModuleContents as $sharedModuleContent){
			$assignedroles = $sharedModuleContent->via('roletomodcon')->sharedRoleList;
			$check = false;
			foreach($assignedroles as $assignedrole){
				if($assignedrole->id == $role_bean->id){
					$check = true;
				}
			}
			if($check == false){
				$role_bean->link('roletomodcon')->contentModule = $sharedModuleContent;
			}
		}
		R::begin();
		try{
			R::store($role_bean);
			R::commit();
		}catch(Exception $e){
			R::rollback();
			return false;
		}
		return true;
	}
	
	/**
	 * Function to register given rolename. If role doesn't exist, it will create a new one.
	 * 
	 * @param string $rolename
	 * @return bean
	 */
	public static function registerRole($rolename){
		$role_bean = R::findOne('role', 'rolename = ? ', array($rolename));
		if($role_bean->id){
			return $role_bean;
		}else{
			$role_bean = R::dispense('role');
			$role_bean->rolename = $rolename;
			$roleid = R::store($role_bean);
			$role_bean = R::load('role', $roleid);
			return $role_bean;
		}
	}
	
	/**
	 * Checks if current logged in user is allowed to view requested content.
	 *
	 */
	private static function isAllowedFor(){
		$module_bean = R::findOne('module', 'modulename = ?', array(self::getModule()));
		$content_bean = R::findOne('content', 'contentname = ? ', array(self::getContent()));
		if($content_bean->id && $module_bean->id){
			$contentmodule = R::findOne('content_module', 'module_id = :mid AND content_id = :cid',
					array(':mid' => $module_bean->id, ':cid' => $content_bean->id));
			if($contentmodule->id){
				$assignedroles = $contentmodule->via('roletomodcon')->sharedRole;
				foreach($assignedroles as $assignedrole){
					if($assignedrole->id == self::$role_bean->id){
						return true;
					}
				}
			}
		}
		return false;
	}
	
	/**
	 * Function to return the role of given userid.
	 * 
	 * @param integer $userid
	 */
	public static function getCurrentRole(){
		if(isset(self::$role_bean)){
			return ucfirst(self::$role_bean->rolename);
		}
		return false;
	}
	
	
	/**
	 * Function to change password for existing user.
	 * 
	 * @param string $login
	 * @param string $password
	 */
	public static function changePassword($login, $password){
		$hashedpassword = self::hashpassword($password);
		$member_bean = R::findOne('member', 'login = :login', array(":login" => $login));
		if(!$member_bean->id){
			throw new Exception("Given user does not exist.");
		}
		$member_bean->password = $hashedpassword;
		R::begin();
		try{
			R::store($member_bean);
			R::commit();
		}catch(Exception $e){
			R::rollback();
			throw new Exception("Failed to save into DB.");
		}
		return true;
	}
	
	/**
	 * Function to add dynamically attributes and their values to given login.
	 *
	 * @param string $login
	 * @param array $attributes
	 */
	public static function setAttributesForUser($login, $attributes){
		if(self::isUserExistent($login) === false){
			throw new Exception("Member with given login doesn't exist.");
		}
		if(is_array($attributes)){
			$user_bean = R::findOne('member', "login = :login", array(":login" => $login));
			foreach($attributes as $attname => $attvalue){
				$user_bean->$attname = $attvalue;
			}
			R::freeze(false);
			R::begin();
			try{
				R::store($user_bean);
				R::commit();
			}catch(Exception $e){
				R::rollback();
				throw new Exception("Failed to save into DB");
			}
			R::freeze();
			return true;
		}else{
			throw new Exception("Expected Array, but no array retrieved.");
		}
		return true;
	}
	
	
	/**
	 * Function to set givne roleid as role for given login.
	 * 
	 * @param string $login
	 * @param string $rolename
	 * @throws Exception
	 * @return boolean
	 */
	public static function setRoleForUser($login, $rolename){
		if(self::isUserExistent($login) === false){
			throw new Exception("Member with givne login doesn't exist.");
		}
		$member_bean = R::findOne('member', 'login = :login', array(":login" => $login));
		$role_bean = R::findOne('role', 'rolename = :rolename', array(":rolename" => $rolename));
		if(!$role_bean->id){
			throw new Exception("Given role does not exist.");
		}
		if($member_bean->id && $role_bean->id){
			$member_bean->role = $role_bean;
			R::begin();
			try{
				R::store($member_bean);
				R::commit();
			}catch(Exception $e){
				throw new Exception("Error occurred while saving data into DB.");
			}
		}
		return true;
	}
	
	/**
	 * Function to make not loginable anymore.
	 * 
	 * @param integer $userid
	 */
	public static function deactivateUser($userid){
		$user_bean = R::load('member', $userid);
		if($user_bean->id){
			$user_bean->loginable = false;
			R::begin();
			try{
				R::store($user_bean);
				R::commit();
				return true;
			}catch(Exception $e){
				R::rollback();
			}
		}
		return false;
	}
	
	/**
	 * Function to make user loginable.
	 * 
	 * @param integer $userid
	 */
	public static function activateUser($userid){
		$user_bean = R::load('member', $userid);
		if($user_bean->id){
			$user_bean->loginable = true;
			R::begin();
			try{
				R::store($user_bean);
				R::commit();
				return true;
			}catch(Exception $e){
				R::rollback();
			}
		}
		return false;
	}
	
	/**
	 * Checks if given login is already existent..
	 * 
	 * @param string $login
	 */
	public static function isUserExistent($login){
		$possible_user_bean = R::findOne('member', 'login = :login', array(":login" => $login));
		if($possible_user_bean->id){
			return true;
		}
		return false;
	}
	
	/**
	 * Function to hash a password. Returns the hashed password without the used SALT.
	 * 
	 * @param string $password
	 */
	private static function hashpassword($password){
		$hash = crypt($password, self::$SALT);
		return substr($hash, strlen(self::$SALT));
	}
	
	/**
	 * Function to login with given credentials.
	 *  
	 * @param string $login
	 * @param string $password
	 */
	public static function login($login, $password, $nopassword = false){
		$hashedpassword = self::hashpassword($password);
		unset($password);
		$possible_user_bean = R::findOne('member', 'login = :login', array(":login" => $login));
		if($possible_user_bean->id && $possible_user_bean->loginable && ($hashedpassword == $possible_user_bean->password || $nopassword === true)){
			$_SESSION['userid'] = $possible_user_bean->id;
			$_SESSION['logintime'] = time();
			self::initializeUser();
			return $possible_user_bean->id;
		}
		return false;
	}
	
	/**
	 * Function to create a user login with given login and password
	 * 
	 * @param string $login
	 * @param string $password
	 * @return boolean
	 */
	public static function createUser($login, $password){
		if(self::isUserExistent($login)){
			return false;
		}
		$member = R::dispense('member');
		$member->login = $login;
		$member->password = self::hashpassword($password);
		$member->loginable = true;
		R::begin();
		try{
			R::store($member);
			R::commit();
		}catch(Exception $e){
			R::rollback();
			return false;
		}
		return true;
	}
	
	/**
	 * Function to logout user with given userid.
	 *
	 */
	public static function logout(){
		session_destroy();
		session_start();
		unset($_SESSION);
		return true;
	}
	
	
	/**
	 * Returns the current logged in user.
	 * 
	 * @param string $login
	 */
	public static function getCurrentUser($login = false){
		if(isset(self::$member_bean)){
			if(self::$member_bean->name != "" && self::$member_bean->surname != "" && $login === false){
				$username = ucfirst(self::$member_bean->name)." ".ucfirst(self::$member_bean->surname);
			}else{
				$username = self::$member_bean->login;
			}
		}else{
			$username = "Guest or System";
		} 
		return $username;
	}
	
	
}