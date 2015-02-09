# Welcome and thank you for using NetroWorksFramework!

NetroWorksFramework is intended to be a helpful, simple MVC Framework for Developers building a WebApplication, 
a Website or a webservice API with the MVC concept. Providing integrated Content and User Management.  

This Framework is build with existing projects like "Smarty Template Engine" for use of controller and view part within MVC.
And "RedBeanPHP" as a ORM providing out of the box model functionality within MVC.

The use of this Framework will be slightly different if you want to use it as a webservice API. See description below in section "WebService"

## Requirements
You need at least PHP 5.3 to have all functionality of this framework. 

There are some strict limitations on usage of this framework. See below section "installation".

## Installation
The Framework is designed to be included as a library. For first usage just include `ContentManager.class.php` within your 
project and first landing page. For example "index.php". Notice that you will allways the index.php and nothing else within your application. 

For printing this help use the function "ContentManager::printHelp()". 

Your web application or website should have following folder structure:

    ./**project**
    	\_ *cache* - Smarty caching directory
    	\_ *configs* - Smarty config directory. You can also use this folder for other configuration files.
    	\_ *controller* - Location for your own controllers, having the business logic of your site
    		\_ *<modulename>* - Allocate your controllers within "module" called folders. For better structure of your controllers. 
    	\_ *htdocs* - The location for the landing page "index.php" which must have the initialization of ContentManager and is called every time. 
    			Keep in mind to have all clientbased files available here. ./css ./fonts ./javascript and others.
    			This folder is also recommended to be the document root for your webserver. To have all business logic hidden for the user.
    	\_ *model* - Free space / directory for your own purpose to use and define a model to access data within a database.
    	\_ *webservice* - Free space / directory for your own purpose to use and define webservice api calls.
    	\_ *templates* - Smarty template directory. Store your html view templates here with the extension ".tpl" to be correctly allocated.
    	\_ *templates_c* - Smarty template caching directory. No need to touch this.

## Setup
The first thing you should do is to provide required information to the ContentManager. 
So your official landing file `index.php` should have at least following functions called:

	-	ContentManager::setProject($projectname)
	-	ContentManager::setProjectPath($pathtoyourProjectfolder)
	-	ContentManager::setupORM($dbtype, $host, $dbname, $user, $password)
	-	ContentManager::setStaging($stagingtype)
	-	ContentManager::setDefaultPage($module, $content, $function)
	-	ContentManager::setDefaultContentError($module, $content, $function)
	-	ContentManager::setDefaultFunctionError($module, $content, $function)

If you don't require any Database operation or management for content. You could leave the function ::setupORM() not to call.
If you require user management which will be described below you need further these functions:

	-	ContentManager::setUserMode(true)
	-	ContentManager::setUserContents($array)
	-	ContentManager::setUserModeDefaultPage($module, $content, $function)
	
If you require in addition to the user management a guest management, you need further these functions:

	- 	ContentManager::setGuestMode(true) 
	-	ContentManager::setGuestContents($array)
	-	ContentManager::setGuestModeDefaultPage($module, $content, $function)

At last function call the following to let the ContentManager process the request and start building and outputting content.

	-	ContentManager::buildContent()	

 
## ContentManager Functions

	- ContentManager::setProject()
	- ContentManager::getProject()

This function is used to give the ContentManager a name. So the framework is available to provide any kind of information
in relation to the project.

	- ContentManager::setProjectPath()
	- ContentManager::getProjectPath()
	
This function is required to allocate all necessary directories which are required for the framework. Especially for the controllers
and Smarty directories.

	- ContentManager::setupORM()
	
See details below

	- ContentManager::setStaging()
	- ContentManager::getStaging()
	
This indicates the staging of this project. With this you can switch for example in different database and could get more information based on it's staging.
Within your controllers you can get this information with ContentManager::getStaging().

Three stages are available:
 
* Release
* Test
* Live

Feel free to test around and let behave your code different related on the staging.

	- ContentManager::setDefaultPage()
	
This is defining your default controller which should be loaded if your `index.php` is requested without any specification of content.
For example: `URL-Request: http://yourpage.com` or more in Detail: `URL-Request: http://yourpage.com/index.php`

	- ContentManager::setDefaultContentError()

You have to specify a default controller and let him output custom error messages. This controller will be loaded everytime a requested content is not available.
The behavior is the same as every other controller. You can access request information like following:

* ContentManager::getRequestedModule()
* ContentManager::getRequestedContent()
* ContentManager::getRequestedFunction()
* ContentManager::getRequestedParams()

	- ContentManager::setDefaultFunctionError($exception)
	
You have to specify a default function error controller and let it output custom messages. This controller will be loaded everytime 
a loaded controller function throws an exception. The exception will be caught and be passed to the specified function error controller. 
It is recommended to handle this exception object to specify where the exception was thrown. 

	- ContentManager::setUserMode()

If setupORM was called and the connection is established this will set the user management to active. Further details are explained below.

	- ContentManager::setUserContents()

This function is required to provide default contents which will be available and accessable through the role "member". 
If UserManagement is acquired UserManager will create default roles "admin" and "member" which indicates and associate
provided contents to this role. More explanation see below.
Each content should be provided within the array as `array("<modulename>_<contentname>", "<modulename2>_<contentname2>")`

	- ContentManager::setUserModeDefaultPage()
	
Specified controller here will be loaded if UserMode is active and the user is not logged in. So in usuall this should be the
"login" page?

	- ContentManager::setGuestMode()
	
Guest mode is available if you have triggered "UserMode" and if you have any contents which should also be available for guests, 
who don't have any user accounts for your webapplications.

	- ContentManager::setGuestContents()

Same behavior as for ::setUserContents(). This will let the UserManager create a role and user called "guest" and realize
ACL.
	
	- ContentManager::setGeustModeDefaultPage()
	
Same function as for UserModeDefaultPage. But in this case, every user located as not logged in will be redirected to the guest default page.

## ContentManagement with Database
Once you set the function `ContentManager::setupORM()`, ContentManager will create on the fly tables in your Database.

* content
* module
* controller - to be more specific, this is just a linking table for content and module specifiying a real controller
	
* **So what will the ContentManager do?** * 

- The ContentManager will scan all directories and files within the directory "controller". All folders will be registered
as modules and all .php files within module directories will be registered as contents. 
On every call, ContentManager will check if something has changed on the structure and keeps the information up to date.
If you don't need this just don't call the function `ContentManager::setupORM()`. 

* **Notice**:In this case also no built in User Management and Guest Management will be available.* 

* **Notice**: Using no ORM doesn't mean that you can't use any database functions. You cann still use the "Model" implementation
to have access to any kind of Database data. It is just for indicating the buildin UserManagement and ContentManagement.*


## UserManagement with Database
UserManager is only available if providing Database information with `ContentManager::setupORM` and setting the required information with above described functions.
Mainly the UserManager has following functions:

- Indicates the user as existing user or guest
- Looking up access rights. 
- create initial roles and rights for "admin", "member", "guest". ("guest" only if guest mode is triggered)

Creating the initial roles and rights means that the UserManager will create additional tables within the database.

* member - having all members stored. Used attributes: id, login, password, loginable, role_id
* role - having roles stored. Used attributes: id, rolename
* permissions - link table between controller and role table. Associating the rights to access specified controllers. Used Attributes: role_id, controller_id
	
So every <modulename>_<contentname> value within the array passed with the function `ContentManager::setUserContents()` will be associated
with the role "member". Which stands for a default member of this application. 
And the role "admin" will be associated with every module_content to have access rights for everything. 

Afterwars when loading the requested content, the UserManager will check if the user is allowed to access the content. 

UserManager has mainly these functions which will could be used within your project.
 
* UserManager::createRoleContents($rolename, $contents)
* UserManager::createUser($login, $password)
* UserManager::setRoleForUser($login, $rolename)
* UserManager::login($login, $password, $nopassword = false)
* UserManager::logout()
* UserManager::setAttributesForUser($login, $attributes)
* UserManager::getCurrentUser($login = false)
* UserManager::activateUser($userid)
* UserManager::deactivateUser($userid)
	
Please have a look within UserManager.class.php for further more functions. 

A user will be located as logged in if the $_SESSION['userid'] is available and the value is numeric. Afterwards it will check
if the given userid is available within the member table. 
For the first time locating a member it will check if admin role member is already is registered. If not it will automatically assign
the role "admin" to the first member who logs in. 

In case an user has no rights to access the requested content, the UserManager indicates the ContentManager to load the function
`viewNotAllowed()` within the Controller - Parent class. Which will output a message that the user is not allowed to view the page.

In case you want to have a custom page which should be shown, when accessing not allowed content. Just override the function 
`Controller::viewNotAllowed()` within your own controller and specify any view, side Controllers or output which should be loaded. 

## UserManager Functions

	- UserManager::createRoleContents($rolename, $contents)
	
This function will create a role with given name, if not exists and assign it the permissions for given content arrays. Same behavior as for `ContentManager::setUserContents()`

	- UserManager::createUser($login, $password)

As the function says, it will create a given user within the member table with given login and password. 
	
	- UserManager::setRoleForUser($login, $rolename)

Use this function to assign a role to a user with given role and loginname.
	
	- UserManager::login($login, $password, $nopassword = false)

This function will check if given login does exist and validates given `$password`. If you set the `$nopassword` parameter to `true`
this function won't perform a password check. So in case you have an external instance/service like a LDAP to check credentials
you could provide any kind of string for `$password` and then login the user in the framework.
In Detail on successfull validation the `UserManager` will set the `$_SESSION['userid']` and `$_SESSION['logginTime']` to build a session.
	
	- UserManager::logout()

UserManager will then destroy all sessions with this function.
	
	- UserManager::setAttributesForUser($login, $attributes)

Provide an assoc array like `array("name" => "xxxx", "email" => "xxx@y.tld")` and the UserManager will create additional attributes for the `member` table
within your database and assign given values to the user. Afterwards you can access these attributes for your own purpose.
	
	- UserManager::getCurrentUser($login = false)

This function will return the current logged in loginname, hardly if `$login` is set to `true` otherwise the function will check if attributes `name` and `surname`
are set within the "member" table and returns a String of that. Capitalized. 
Example: Having a Member like following table:

login | name | surname | email | password | loginable
----- | ---- | ------- | ----- | -------- | ---------
netroworks | netro | works | netroworks@systems.tld | xxxx | 1

Passing `$login = true` will let the function return `netroworks`. Passing no arguments will let the function return `Netro Works`.
	
	- UserManager::activateUser($userid)
	- UserManager::deactivateUser($userid)
	
Need further instrcutions here?
 
* **Notice:** Deactivated Users have the attribbute "loginable" set to 0/false so they won't be accepted by the function `UserManager::login()`*
	
## GuestManagement with Database
The Guestmanagement is the same treating every user, who is not logged as a "guest" member and associating the role "guest" 
with it rights to have access to the requested content. 

In case a guest tries to have access on not allowed content, the UserManager will indicate the ContentManager to load the 
`Controller::viewNotAllowed()` function.

## Definitions
Within NetroWorksFrameWork you have some definitions. These are also some kind build upon the concept "configuration by convention".

### Controllers
Controllers are classes extending the "Controller.class.php" within the FrameworkLibrary and holding the business logic of
your web application.
 
**Following example:**
*Your controller "LoginPage.php" should be coded as followed:*

    class LoginPage extends Controller {
    	/*** Your business logic here
    	***/
    }

The location and accessability are defined through the "module" and "content". 
So having a controller "LoginPage.php" you should first assign this to a module. For example "core". 
Defining this you will have to allocate the "LoginPage.php" within the directory named as the module "core" within the
controller directory. 
The `ContentManager` will be available to access this controller having the modulename and contentname within the URI.

**Following example:**
*URI: http://yourpage.com/index.php/core/LoginPage/index/*

With this URI the `ContentManager` will try to allocate the module folder "core" within the controller directory and
tries  to find the file `LoginPage.php` within that folder. Afterwards it will try to call the function `index()`. 

**So the definition here is:**
*Your HTTP-Request should always be defined as http://yourpage.com/index.php/\<modulename\>/\<contentname\>/\<functionname\>/*

You are able to pass additional params after the trailing slash of <functionname>. Separeted by "/".
All params passed this way will be available within the controller function call. 

**Following example:**
*URI:  http://yourpage.com/index.php/core/LoginPage/index/param1/param2/param3/*

The function `LoginPage::index()` will be called and the additional params `param1`,`param2`, `param3` will be passed as an array to the function index.
So if you create a funciton called "index" and want to have the additional params available you will have to declare your function as 

	public function index($params)
	
As you recognized your functions within your controller, which should be available for access should always be named 
as the same for the HTTP-Request. Same for folder and controller. As this is the only way having the ContentManager to allocate
the correct Controller.

### Views
Views are just simple HTML templates. All functionalites of Smarty Template Engine are available within here. So please visit 
the Smarty Documentation page here to get more details.

*The only Question to be answered:* 
**How can I assign a template to a controller?**
 
- All controller will have the function `Controller::addView($viewname)` available. 

So once a controller function is called you can define within your function which templates should be loaded. And they will be
loaded in the order you have added them. 
So if you first add the template "header.tpl", then "navbar.tpl", then "content.tpl". The `ContentManager` will load the templates in this order.
*Note that the order is important!* 

You will also have the function `Controller::assign($key, $data)` available to assign values to the specified template. Reflecting the integration of Smarty Template Engine

### Additional Controllers
If you have some additional controllers which should be loaded, for example, a controller which provides dynamiclly navbar information like hrefs or
other stuff which will be assigned through the function. You can add within your main controller another controller function with the function
`Controller::addSideController($module, $content, $function)`. 
There is no limitation and all added side controllers will be loaded from the ContentManager.

Mainly the Class `Controller` provides 3 ways accessing other controllers, different from the requested one.

	- Controller::addSideController($module, $content, $function)
	
Specifiying a controller function here will cause the `ContentManager` to perform the function, but will have no affect on loading any templates.
So having already a controller function which assigns anykind of Smarty Template variables which are required for one of your specified templates, these could be assigned with this function.
 
	- Controller::switchToControllerFunction($module, $content, $function)
	
This function will intend to imitate the requested controller to be the controller function specified within given parameters.
**Example:**
Having a controller providing a HTML formular output and redirecting via `<form action>` to another controller for validation. 
After handling user inputs and performing some business logic - based on user input - you don't want to specify any specific landing page for that, 
instead you want to redirect to another existing controller and their template, use this function to imitate the output of the specified controller with given parameters.

* **Notice:** Every smarty variables assigned within the validation file will be available on the controller templates specified with the given parameters * 

	- Controller::getControllerObject($module, $content)
	
With this function you can get a specific controller object. Just use this controller as an object and perform anykind of function here, which are intended for helping issues. 

### Models
This Framework is built and using the ORM RedBeanPHP. Once initialized the function `ContentManager::setupORM()` you are also available
to use any RedBeanPHP related functions to dynamically define your database tables and CRUD your data. 

In addition if you want to use your own Database without having the Framework building anykind of database structure on the fly 
or if you want to access any other database, you can specifiy any kind of Model class extending the "Model.class.php" of 
NetroWorksFrameWork. 

**For example:**
Preamble: You want to access the database "library", then for somehow it could be good to name the class "library"...
  
    class library extends Model {
    	/*** Data access, processing logic here. 
    	***/
    }

The parent "Model" class provides four basic functions: 

* Model::buildConnection($dbtype, $host, $dbname, $user, $password)
* Model::query($query, $bindings = NULL)
* Model::insert($query, $bindings = NULL)
* Model::getModelObject($modelname)

As this class is intended to be handled as an object, you do have also the __construct() function. 

	- Model::buildConnection()
	
This function will, as the name says, build the connection to the specified database. 
As the Model parent class does use the RedBeanPHP classes it will let you switch between different databases.

	- Model::query($query, $bindings = NULL)

This functions will switch to the specified Database, performs the given query, switch back to project specific database and returns the result
as an assoc array. Having for each Dataset row an indexed array entry and named keys for the query attributes.
You are also able to use anykind of namespaces within your sql and provide and assoc array for binding values. 
The `Model::query()` function uses the R::getAll() function from RedBeanPHP. Please look at their homepage for usage.

	- Model::insert($query, $bindings = NULL)
	
The behavior is much same as the `Model::query()` function. But instaed of doing queriing data it will insert / updates datasets specified in the $query.
Due to the behavior of RedBeanPHP, function `R::getAll()` won't behave as aspected in returning values for inserts and updates.
So we use the function `R::exec()` to perfmin Create and Update calls.

	- Modell:getModelObject($modelname)
	
This function allows you to instanciate an Model object defined by its name and located within the `model`direcotory as `modelname.php`.
As the class `Model` will include the existing file you don't have to include files at yourself.
	
**Notice:**
As the Model class will associate a name with each connection. You should avoid to use a class name of a model
which is the same as the project name you've specified with `ContentManager::setProject()`. As then the query function will use
the project database instead of a possible other database with the same name.

If you want to have a model class for the entire database which is used for the ContentManagement with Database, just don't call
the function `Model::buildConnection()` within your constructor of the model class. 
It will then try to perform the query with your entire database specified with function `ContentManager::setupORM()`.

**So how should a Model function be build?**

*Following example:*

    class library extends Model {
    	public function getDataX(){
    		$query = "SELECT * FROM data WHERE id = 1";
    		return $this->query($query);
    	}
    }
 	
That's all! 

In addition you could of course use any kind of RedBeanPHP ORM functions within your controller, or even within your model to 
retrieve Table data as obejcts or make directly SQL commands on your entire database. 

**Notice:** If `ContentManager::setupORM()` is not called within your landing page `index.php`, you should setup your database within your controller or model with `R::setup()` or `R::addDatabase()`. 

## Managing Access Control Layer with NetroWorksFramework 

Once you've setup the project Database with `ContentManager::setupORM()` and initialized the UserManager with `ContentManager::setUserMode()`
a very simple ACL is already setted up for use. 
Basicly the concept is, that every user is assigned to a role. And a role has rights to access controller. 
For every call, the UserManager will check if the assigned role for the role is allowed to access the requested controller. 
If yes, the request will be passed to the controller. If no, the request will endup in calling either the `Controller::viewNotAllowed()` function,
which also integrates a Webservice Json reply, when using as this Framework as a Webservice, or the requested controller specific and redefined function `[childclass]::viewNotAllowed()`.

This is the logic behind controller access. 
To manage ACL within your HTML output and show different content based on permissions, you can just make a simple condition via Smarty Template variables. 

**Example:**
*Following HTML block*

    <body>
    	<a href="link_to_somewhere">Klick</a>
    	{if $allowed_for_secret_controller}
    	<a href="link_to_secret_controller}">Pssst I'm secret!</a>
    	{/if}
    </body>

**What will happen?**
Every user (even guests are users with the role guest permissions) who is allowed to - have permissions - to access a specific controller will se the second link to `link_to_secret_controller`. 
Every other user won't see it. 

**How is this possible?**
On every call, the UserManager assignes Smarty variable formed like `allowed_for_modulename_contentname` based on the permissions set for the role, which is assigned to the user.
So on every HTML output content, you can manage the visibility through the Smarty conditional `if $allowed_for_modulename_contentname` and manage your ACL.

## Using NetroWorksFramework as WebService API
A second way in using this Framework is, to use it as a WebService API.

The setup and how to call is absolutly the same as the way of a webapplication. The only thing you have to add is to add a Request HTTP-Header Attribute.
Either you can choose to add the property "NetroWorksWebService:true" or change the `content-Type` to `application/json`. 
Setting this into the HTTP-Header and passing json for input data will let the ContentManager return `json` formatted data. 

Passing the json string named `jsonparams` will let the `ContentManager` add the json string values as array to the `$params` which will be passed to the requested controller function.
The requested controller function shall just return an data assoc array which will then be converted from `ContentManager` into a json string which will then returned to the requester. 

**Why should the controller function return just an assoc array of response?**
*This is intended to keep those functions available for the whole project.*

The core benefit of using NetroWorksFramework for building a WebService API is to benefit from the built-in UserManagement and Access Control Layer.
You can specify a default controller function which holds the logic for authentication and let manage every request with the ACL implementation of NetroWorksFramework.

*Doing so makes it - in my opinion - absolutly easy to manage rights for other systems interacting with your API.* 

**For example:**
If you have a project which shall be able to provide an api for external devices like Mobile Apps or other systems interacting with your project 
and in addition if you want to provide a Webpage within that project: Manipulating or accessing data will become much more convenient to access through other controllers which are designed to 
return HTML pages in using the function `Controller::getControllerObject()`. The return controller object could be the API controller which will await some `$params` and returns data as assoc arrays.

### Webservice Class
The `Webservice.class.php` is a parent class which provides functions to access other Webservice APIs build with NetroWorksFramework. 
This class will initiate a curl object and do an authentication call on the distributed NetroWorksFramework Webservice API and then provide functions to specify which controller you want to request to and pass parameters to the call.

**Following functions are provided for use:**

- WebService::setRequestMethod($method)
- WebService::setRequestParams($params)
- WebService::setUrl($url)
- WebService::setVerifyPeer($boolean)
- WebService::setModule($module)
- WebService::setContent($content)
- WebService::setFunction($function)
- WebService::exec()
- WebService::getWebServiceObject($webservicename)

As all other parent classes define your webservice class within the directory `webservice` to let the WebService return the webservice object which are located within that directory and named as passed.

**Example Webservice class:**

    class AnotherProjectAPI extends WebService {
    	public function __construct(){
    		require_once("/path/to/your/webservice/config_file/to/get/url/username/password");
    		$config = <config_file::getConfig()>;
    		parent::__construct($config['url'], $config['username'], $config['password']);
    		$this->setModule("api");
    		$this->setContent("data");
    	}
    	
    	public function getDataX($id){
    		$this->setFunction("getData");
    		$this->setRequestParams(array("id"=>$id));
    		$this->setRequestMethod("post");
    		$this->setVerifyPeer(false); //Not recommended, but if you just have a selfsigned certificate, that's the only way to communicate with https
    		$result = $this->exec();
    		return json_decode($result, true);
    	}
    }


#### Webservice Class Functions

	- WebService::setRequestMethod($method)

At this point only the HTTP Request methods `GET` and `POST` are implemented. Default request method is `POST`.

	- WebService::setRequestParams($params)

You can either pass an array or a single value. Each will be converted into a json string which will be as a value of the key `jsonparams`. 
A single value will be transformed into a json using the function `json_encode(array("value" => $params))`.
An array will be transformed into a json using the function `json_encode($params)`;

	- WebService::setUrl($url)

If you want to change the url, use this function.

* **Notice:** Please ensure to pass only the URL till the landing page. Don't specify any Controller function as the completed URI 
will be build after passing information with functions `WebService::setModule()`, `WebService::setContent()` and `WebService::setFunction()`.*

	- WebService::setVerifyPeer($boolean)
	
This will set the `CURLOPT_VERIFYPEER`. In case you want to request via HTTPS, please consider this. 

	- WebService::setModule($modulename)
	- WebService::setContent($contentname)
	- WebService::setFunction($functionname)

These functions specifies the controller function within the Webservice API built with NetroWorksFramework.

 	- WebService::exec()

As it says, it will execute the request and returns the plain value without converting it into an array or other format. 
If you called the api correct and build the api controllers correctly then it will return the json.

### Accessing the WebService from third party systems

In case you want to access the webservice built with NetroWorksFramework, just ensure to send json data with header parameter `Content-Type:application/json`.
That's it.


## Thank you

For reading this documentation and eventually using the framework for your next web application project! 



@author: NetroWorksSystem

  