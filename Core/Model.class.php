<?php

/**
 * Part of NetroWorksFrameWork: 
 * Parent Model class for Model implementation.
 * 
 * @uses RedBeanPHP ORM
 * @author NetroWorksSystem
 */
class Model {
	
	private static $dbclass;
	
	/**
	 * Function to build the connection to specified database.
	 * 
	 * @param string $type
	 * @param string $host
	 * @param string $db
	 * @param string $user
	 * @param string $password
	 * @throws Exception
	 * @return boolean
	 */
	public function buildConnection($type, $host, $db, $user, $password){
		if(isset(self::$dbclass) === false){
			R::addDatabase(__CLASS__, $type.":host=".$host.";dbname=".$db, $user, $password, true);
			R::selectDatabase(__CLASS__);
			if(R::testConnection() === true){
				self::$dbclass = __CLASS__;
				R::selectDatabase(ContentManager::getProject());
			}else{
				R::selectDatabase(ContentManager::getProject());
				throw new Exception("Failed to build connection to specified Database.");
			}
		}
		return true;
	}
	
	
	/**
	 * Function to perform database queries. 
	 * You can either just perform plain query or use bindings.
	 * If using binding, the query should have placeholders which are defined as keys within $bindings.
	 * 
	 * @param string $query
	 * @param array $bindings
	 * @throws Exceptions
	 */
	protected function query($query, $bindings = null){
		if(isset(self::$dbclass)){
			R::selectDatabase(self::$dbclass);
		}
		if(isset(self::$dbclass) === false && ContentManager::isDBSet() === false){
			throw new Exception("There is no Database available to perform query. Please use ContentManager::setupORM() or Model::buildConnection() first.");
		}
		try{
			if($bindings == NULL){
				$result = R::getAll($query);
			}elseif(is_array($bindings)){
				$result = R::getAll($query, $bindings);
			}
		}catch(Exception $e){
			throw new Exception("Error while performing query. Errormessage: ".$e->getMessage());
		}
		if(isset(self::$dbclass)){
			R::selectDatabase(ContentManager::getProject());
		}
		return $result;
	}

	/**
	 * function to insert a new record with a custom query.
	 * You can either just perform plain query or use bindings.
	 * If using binding, the query should have placeholders which are defined as keys within $bindings.
	 *
	 * @param string $query
	 * @param array $bindings
	 * @throws Exception
	 * @return boolean
	 */
	protected function insert($query, $bindings = null){
		if(isset(self::$dbclass)){
			R::selectDatabase(self::$dbclass);
		}
		if(isset(self::$dbclass) === false && ContentManager::isDBSet() === false){
			throw new Exception("There is no Database available to perform query. Please use ContentManager::setupORM() or Model::buildConnection() first.");
		}
		try{
			if($bindings == NULL){
				$result = R::exec($query);
			}elseif(is_array($bindings)){
				$result = R::exec($query, $bindings);
			}
	
		}catch(Exception $e){
			var_dump("hallo");
			throw new Exception("Error while performing query. Errormessage: ".$e->getMessage());
		}
		if(isset(self::$dbclass)){
			R::selectDatabase(ContentManager::getProject());
		}
		return $result;
	}
	
	/**
	 * Function to instanciate a model object.
	 *
	 * @param string $modelname
	 * @throws Exception
	 * @return Model
	 */
	public static function getModelObject($modelname){
		if(file_exists(ContentManager::getProjectPath().'/model/'.$modelname.'.php')){
			require_once(ContentManager::getProjectPath().'/model/'.$modelname.'.php');
			return new $modelname();
		}else{
			throw new Exception("Model does not exist. Please ensure to have the correct location.");
		}
	
	}
}