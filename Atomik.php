<?php
/**
 * Atomik Framework
 * Copyright (c) 2008 Maxime Bouroumeau-Fuseau
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package Atomik
 * @author Maxime Bouroumeau-Fuseau
 * @copyright 2008 (c) Maxime Bouroumeau-Fuseau
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link http://www.atomikframework.com
 */
	 
define('ATOMIK_VERSION', '2.1');

/* -------------------------------------------------------------------------------------------
 *  DEFAULT CONFIGURATION
 * ------------------------------------------------------------------------------------------ */

Atomik::set(array(

	/* plugins */
	'plugins'				    => array(),
    
    /* routes */
    'routes' 					=> array(),

	/* debug mode */
	'debug' 					=> false,

    'atomik' => array(
    
    	/* request */
    	'trigger' 			    => 'action',
    	'default_action' 		=> 'index',

		/* register the class autoloader */
		'class_autoload'		=> true,
        
		/* enable routing */
        'enable_routing' 		=> true,

		/* views */
		'views' => array(
			'file_extension' 	=> '.phtml',
			'layout'			=> false,
			'engine' 			=> false
		),
    
    	/* dirs */
        'dirs' => array(
			'app'				=> './app',
        	'plugins'			=> './app/plugins/',
        	'actions' 			=> './app/actions/',
        	'views'	 			=> array(
				'./app/views/',
        		'./app/templates/' // for backward compatibility
			),
			'layouts'			=> array(
				'./app/layouts',
				'./app/views'
			),
        	'includes'			=> array(
        		'./app/includes/', 
        		'./app/libraries/'
			)
        ),
    
    	/* files */
        'files' => array(
        	'bootstrap'		    => './app/bootstrap.php',
        	'pre_dispatch' 	    => './app/pre_dispatch.php',
        	'post_dispatch' 	=> './app/post_dispatch.php',
        	'404' 			    => './app/404.php',
        	'error' 			=> './app/error.php'
        ),
    	
    	/* error management */
    	'catch_errors'			=> false,
    	'display_errors'		=> true,
        'error_report_attrs'	=> array(
		    'atomik-error'               => 'style="padding: 10px"',
		    'atomik-error-title'    	 => 'style="font-size: 1.3em; font-weight: bold; color: #FF0000"',
		    'atomik-error-lines'         => 'style="width: 100%; margin-bottom: 20px; background-color: #fff;'
		                                  . 'border: 1px solid #000; font-size: 0.8em"',
		    'atomik-error-line'          => '',
		    'atomik-error-line-error'    => 'style="background-color: #ffe8e7"',
		    'atomik-error-line-number'   => 'style="background-color: #eeeeee"',
		    'atomik-error-line-text'	 => '',
		    'atomik-error-stack'		 => ''
		)
    	
    ),
    
    'start_time' 				=> time() + microtime()
));

/* -------------------------------------------------------------------------------------------
 *  CORE
 * ------------------------------------------------------------------------------------------ */

/* creates the A function (shortcut to Atomik::get) */
if (!function_exists('A')) {
    /**
     * Shortcut function to Atomik::get()
     * Useful when dealing with selectors
     *
     * @see Atomik::get()
     * @return mixed
     */
    function A()
    {
        $args = func_get_args();
        return call_user_func_array(array('Atomik', 'get'), $args);
    }
}

/* starts Atomik unless ATOMIK_AUTORUN is set to false */
if (!defined('ATOMIK_AUTORUN') || ATOMIK_AUTORUN === true) {
    Atomik::dispatch();
}

/**
 * Atomik Framework Main class
 *
 * @package Atomik
 */
class Atomik
{
    /**
     * Global store
     * 
     * This property is used to stored all data accessed using get(), set()...
     *
     * @var array
     */
	protected static $_store = array();
	
	/**
	 * Loaded plugins
	 * 
	 * When a plugin is loaded, its name is saved in this array to 
	 * avoid loading it twice.
	 *
	 * @var array
	 */
	protected static $_plugins = array();
	
	/**
	 * Registered events
	 * 
	 * The array keys are event names and their value is an array with 
	 * the event callbacks
	 *
	 * @var array
	 */
	protected static $_events = array();
	
	/**
	 * Selectors namespaces
	 * 
	 * The array keys are the namespace name and the associated value is
	 * the callback to call when the namespace is used
	 *
	 * @var array
	 */
	protected static $_namespaces = array();
	
	/**
	 * Execution contexts
	 * 
	 * Each call to Atomik::execute() creates a context.
	 * 
	 * @var array
	 */
	protected static $_execContexts = array();
	
	/**
	 * Dispatches the request
	 * 
	 * This is the main method to call. It takes an URI, applies routes if
	 * needed, executes the action and renders the view.
	 * If $uri is null, the value of the GET parameter specified as the trigger 
	 * will be used.
	 * 
	 * @param string $uri OPTIONAL
	 */
	public static function dispatch($uri = null)
	{
	    /* wrap the whole app inside a try/catch block to catch all errors */
	    try {
    		 
    		/* loads bootstrap file */
    		if (file_exists($filename = self::get('atomik/files/bootstrap'))) {
    			require($filename);
    		}
    		
    		/* adds includes dirs to php include path */
    		$includePath = '';
    		foreach (self::path(self::get('atomik/dirs/includes'), true) as $dir) {
    		    if (@is_dir($dir)) {
    		        $includePath .= PATH_SEPARATOR . $dir;
    		    }
    		}
    		set_include_path(get_include_path() . $includePath);
	        
    		/* registers the error handler */
    		if (self::get('atomik/catch_errors', true) === true) {
    			set_error_handler(array('Atomik', 'errorHandler'));
    		}
    		
    		/* registers the class autoload handler */
    		if (self::get('atomik/class_autoload', true) === true) {
    			if (!function_exists('spl_autoload_register')) {
    				throw new Exception('Missing spl_autoload_register function');
    			}
    			spl_autoload_register(array('Atomik', 'needed'));
    		}
    	
    		/* loads plugins */
    		foreach (self::get('plugins') as $key => $value) {
    		    if (!is_string($key)) {
    		        $key = $value;
    		        $value = array();
    		    }
    			self::loadPlugin(ucfirst($key), $value);
    		}
    	
    		/* core is starting */
    		self::fireEvent('Atomik::Start', array(&$cancel));
    		if ($cancel) {
				self::end(true);
    		}
    	
    		/* checks if url rewriting is used */
    		if (!self::has('url_rewriting')) {
    			self::set('url_rewriting', isset($_SERVER['REDIRECT_URL']) || isset($_SERVER['REDIRECT_URI']));
    		}
    	
    		/* checks if it's needed to auto discover the request */
    		if ($uri === null) {
    		    
        		/* retreives the requested url */
        		$trigger = self::get('atomik/trigger');
        		if (!isset($_GET[$trigger]) || empty($_GET[$trigger])) {
        			/* no trigger specified, using default page name */
        			$uri = self::get('atomik/default_action');
        		} else {
        		    $uri = trim($_GET[$trigger], '/');
        		}
    	
        		/* retreives the base url */
        		if (!self::has('base_url')) {
        			if (self::get('url_rewriting') && (isset($_SERVER['REDIRECT_URL']) || isset($_SERVER['REDIRECT_URI']))) {
        			    /* finds the base url from the redirected url */
        				$redirectUrl = isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['REDIRECT_URI'];
        				self::set('base_url', substr($redirectUrl, 0, -strlen($_GET[$trigger])));
        			} else {
        			    /* finds the base url from the script name */
        				self::set('base_url', trim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');
        			}
        		}
        		
    		} else {
    		    /* sets the user defined request */
            	/* retreives the base url */
            	if (!self::has('base_url')) {
    			    /* finds the base url from the script name */
    				self::set('base_url', trim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');
            	}
    		}
    		
    		/* routes the request */
    		if(self::get('enable_routing', true)) {
    			$request = self::route($uri, $_GET);
    		} else {
    			$request = array('action' => $uri);
    		}
        		
        	/* checking if no dot are in the action name to avoid any hack attempt and if no 
        	 * underscore is use as first character in a segment */
        	if (strpos($request['action'], '..') !== false || substr($request['action'], 0, 1) == '_' 
        	    || strpos($request['action'], '/_') !== false) {
        		    self::trigger404();
        	}
    		
    		self::set('request_uri', $uri);
    		self::set('request', $request);
    	
    		/* all configuration has been set, ready to dispatch */
    		self::fireEvent('Atomik::Dispatch::Before', array(&$cancel));
    		if ($cancel) {
				self::end(true);
    		}
    	
    		/* global pre dispatch action */
    		if (file_exists($filename = self::get('atomik/files/pre_dispatch'))) {
    			require($filename);
    		}
    	
    		/* executes the action */
    		if (($content = self::execute(self::get('request/action'), true, false, false)) === false) {
    			self::trigger404();
    		}
    		
    		/* renders the layout if enable */
    		if (($layout = self::get('atomik/views/layout', false)) !== false) {
				$content = self::renderLayout($layout, $content);
    		}
    		
    		echo $content;
    	
    		/* dispatch done */
    		self::fireEvent('Atomik::Dispatch::After');
    	
    		/* global post dispatch action */
    		if (file_exists($filename = self::get('atomik/files/post_dispatch'))) {
    			require($filename);
    		}
    	
    		/* end */
    		self::end(true);
    		
	    } catch (Exception $e) {
	        
			/* checks if we really want to catch errors */
	        if (!self::get('atomik/catch_errors', true)) {
	            throw $e;
	        }
	        
			self::fireEvent('Atomik::Error', array($e));
			self::renderException($e);
			self::end(false);
	    }
	}
	
	/**
	 * Parse an uri to extract parameters
	 *
	 * @param string $uri
	 * @param array $params OPTIONAL
	 * @param array $routes OPTIONAL By default, it uses the config key atomik/routes
	 * @return array
	 */
	public static function route($uri, $params = array(), $routes = null)
	{
		if ($routes === null) {
			$routes = self::get('routes');
		}
		
		Atomik::fireEvent('Atomik::Router::Start', array(&$uri, &$routes));
		
		/* extracts uri information */
		$components = parse_url($uri);
		$uri = trim($components['path'], '/');
		$uriSegments = explode('/', $uri);
		if (isset($components['query'])) {
			parse_str($components['query'], $query);
			$params = array_merge($query, $params);
		}
		
		/* searches for a route matching the uri */
		$found = false;
		$request = array();
		foreach ($routes as $route => $default) {
			if (!is_string($route)) {
				$route = $default;
				$default = array();
			}
			
			$valid = true;
			$segments = explode('/', trim($route, '/'));
			$request = $default;
			
			for ($i = 0, $count = count($segments); $i < $count; $i++) {
				if (substr($segments[$i], 0, 1) == ':') {
					/* segment is a parameter */
					if (isset($uriSegments[$i])) {
						/* this segment is defined in the uri */
						$request[substr($segments[$i], 1)] = $uriSegments[$i];
						$segments[$i] = $uriSegments[$i];
					} else if (!array_key_exists(substr($segments[$i], 1), $default)) {
						/* not defined in the uri and no default value */
						$valid = false;
						break;
					}
				} else {
					/* fixed segment */
					if (!isset($uriSegments[$i]) || $uriSegments[$i] != $segments[$i]) {
						$valid = false;
						break;
					}
				}
			}
			
			/* checks if route is valid and if controller and action params are set */
			if ($valid && isset($request['action'])) {
				$found = true;
				/* if there's remaining segments in the uri, adding them as params */
				if (($count = count($uriSegments)) > ($start = count($segments))) {
					for ($i = $start; $i < $count; $i += 2) {
						if (isset($uriSegments[$i + 1])) {
							$request[$uriSegments[$i]] = $uriSegments[$i + 1];
						}
					}
				}
				break;
			}
		}
		
		if (!$found) {
			/* route not found */
			$request = array('action' => $uri);
		}
		
		$request = array_merge($params, $request);
		
		Atomik::fireEvent('Atomik::Router::End', array(&$uri, &$request));
		
		return $request;
	}
	
	/**
	 * Executes an action
	 * 
	 * Searches for a file called after the action (with the php extension) inside
	 * directories set under atomik/dirs/actions. If no file is found, it will search
	 * for a view and render it. If neither of them are found, it will return an error.
	 *
	 * @see Atomik::render()
	 * @param string $action The action name
	 * @param bool $render OPTIONAL (default true) Whether to render the associated view
	 * @param bool $echo OPTIONAL (default false) Whether to output the associated view
	 * @param bool $triggerError OPTIONAL (default true) Whether to throw an exception if the action is not found
	 * @return array|string|bool
	 */
	public static function execute($action, $render = true, $echo = false, $triggerError = true)
	{
		$view = $action;
		$vars = array();
		
		/* creates the execution context */
		$context = array('action' => &$action, 'view' => &$view, 'render' => &$render);
		self::$_execContexts[] =& $context;
	
		self::fireEvent('Atomik::Execute::Start', array(&$action, &$view, &$render, &$echo, &$triggerError));
		if ($action === false) {
			return false;
		}
		
		$actionFilename = self::path($action . '.php', self::get('atomik/dirs/actions'));
		$viewFilename = self::path($view . self::get('atomik/views/file_extension'), self::get('atomik/dirs/views'));
		
		/* checks if at least the action file or the view file is defined */
		if ($actionFilename === false && $viewFilename === false) {
			if ($triggerError) {
				throw new Exception('Action ' . $action . ' does not exist');
			}
			return false;
		}
	
		self::fireEvent('Atomik::Execute::Before', array(&$action, &$actionFilename, &$view, &$render, &$echo, &$triggerError));
	
		/* executes the action */
		if ($actionFilename !== false) {
		    /* executes the action in its own scope and fetches defined variables */
			$vars = self::_executeInScope($actionFilename);
		}
	
		self::fireEvent('Atomik::Execute::After', array($action, &$view, &$vars, &$render, &$echo, &$triggerError));
		
		/* removes the execution context */
		array_pop(self::$_execContexts);
		
		/* returns $vars if the view should not be rendered */
		if (!$render) {
			return $vars;
		}
		
		/* renders the view associated to the action */
		return self::render($view, $vars, $echo, $triggerError);
	}
	
	/**
	 * Requires the actions file inside a clean scope and returns defined
	 * variables
	 *
	 * @param string $__actionFilename
	 * @return array
	 */
	public static function _executeInScope($__actionFilename)
	{
		require($__actionFilename);
		
		/* retreives "public" variables (not prefixed with an underscore) */
		$definedVars = get_defined_vars();
		$vars = array();
		foreach ($definedVars as $name => $value) {
			if (substr($name, 0, 1) != '_') {
				$vars[$name] = $value;
			}
		}
		
		return $vars;
	}
	
	/**
	 * Prevents the view of the action being executed to be rendered
	 */
	public static function noRender()
	{
		if (count(self::$_execContexts)) {
			self::$_execContexts[count(self::$_execContexts) - 1]['render'] = false;
		}
	}
	
	/**
	 * Renders a view
	 * 
	 * Searches for a file called after the view inside
	 * directories configured in atomik/dirs/views. If no file is found, an 
	 * exception is throwed.
	 *
	 * @param string $view The view name
	 * @param array $vars OPTIONAL An array containing key/value pairs that will be transformed to variables accessible inside the view
	 * @param bool $echo OPTIONAL (default false) Whether to output the result
	 * @param bool $triggerError OPTIONAL (default true) Whether to throw an exception if an error occurs
	 * @return string|bool
	 */
	public static function render($view, $vars = array(), $echo = false, $triggerError = true, $dirs = null)
	{
		if ($dirs === null) {
			$dirs = self::get('atomik/dirs/views');
		}
		
		self::fireEvent('Atomik::Render::Start', array(&$view, &$vars, &$echo, &$triggerError));
		
		/* view filename */
		$filename = self::path($view . self::get('atomik/views/file_extension'), $dirs);
		
		/* checks if the file exists */
		if ($filename === false) {
			if ($triggerError) {
				throw new Exception('View ' . $view . ' not found');
			}
			return false;
		}
		
		self::fireEvent('Atomik::Render::Before', array(&$view, &$vars, &$echo, &$triggerError, &$filename));
		
		$output = self::renderFile($filename, $vars);
		
		self::fireEvent('Atomik::Render::After', array($view, &$output, &$vars, &$filename, &$echo, &$triggerError));
		
		/* checks if it's needed to echo the output */
		if (!$echo) {
			return $output;
		}
		
		self::fireEvent('Atomik::Output::Before', array(&$output));
		echo $output;
		self::fireEvent('Atomik::Output::After', array($output));
	}
	
	/**
	 * Renders a file using a filename which will not be resolved.
	 * 
	 * Example:
	 * <code>
	 * Atomik::renderFile('my_file.php');
	 * </code>
	 *
	 * @param string $__filename Filename
	 * @param array $__vars OPTIONAL An array containing key/value pairs that will be transformed to variables accessible inside the file
	 * @param bool $__echo OPTIONAL Whether to output the result
	 * @return The output of the rendered file
	 */
	public static function renderFile($__filename, $__vars = array(), $__echo = false)
	{
		self::fireEvent('Atomik::RenderFile::Before', array(&$__filename, &$__vars, &$__echo));
		
		if (($callback = self::get('atomik/views/engine', false)) !== false) {
			if (!is_callable($callback)) {
				throw new Exception('The specified rendering engine callback cannot be called');
			}
			$output = $callback($__filename, $__vars);
			
		} else {
			extract($__vars);
			ob_start();
			include($__filename);
			$output = ob_get_clean();
		}
		
		self::fireEvent('Atomik::RenderFile::After', array(&$__filename, &$output, $__vars, $__echo));
		
		if (!$__echo) {
			return $output;
		}
		
		self::fireEvent('Atomik::Output::Before', array(&$output));
		echo $output;
		self::fireEvent('Atomik::Output::After', array($output));
	}
	
	/**
	 * Renders a layout
	 * 
	 * @param $layout
	 * @param $content
	 * @param $echo
	 * @param $triggerError
	 * @return string
	 */
	public static function renderLayout($layout, $content, $echo = false, $triggerError = true)
	{
		return self::render($layout, array('contentForLayout' => $content), $echo, $triggerError, self::get('atomik/dirs/layouts'));
	}
	
	/**
	 * Disables the layout
	 */
	public static function disableLayout()
	{
		self::set('atomik/views/layout', false);
	}

	/**
	 * Fires the Atomik::End event and exits the application
	 *
	 * @param bool $success OPTIONAL Whether the application exit on success or because an error occured
	 */
	public static function end($success = false)
	{
		self::fireEvent('Atomik::End', array($success));
		exit;
	}
	
	
	/* -------------------------------------------------------------------------------------------
	 *  Accessor methods
	 * ------------------------------------------------------------------------------------------ */
	
	
	/**
	 * Sets a key/value pair in the store
	 * 
	 * If the first argument is an array, values are merged recursively.
	 * The array is first dimensionized
	 * You can set values from sub arrays by using a path-like key.
	 * For example, to set the value inside the array $array[key1][key2]
	 * use the key 'key1/key2'
	 * Can be used on any array by specifying the third argument
	 *
	 * @see Atomik::_dimensionizeArray()
	 * @param array|string $key Can be an array to set many key/value
	 * @param mixed $value OPTIONAL
	 * @param bool $dimensionize OPTIONAL Whether to use Atomik::_dimensionizeArray()
	 * @param array $array OPTIONAL The array on which the operation is applied
	 * @param array $add OPTIONAL Whether to add values or replace them
	 */
	public static function set($key, $value = null, $dimensionize = true, &$array = null, $add = false)
	{
		/* if $data is null, uses the global store */
		if ($array === null) {
		    $array = &self::$_store;
		}
		
		/* transforms a string key into a key/value array to 
		 * use with _dimensionizeArray() */
		if (!is_array($key) && $value !== null) {
			$key = array($key => $value);
		}
	    
	    if (!is_array($key)) {
	    	return;
	    }
	    
	    if ($dimensionize) {
    		$key = self::_dimensionizeArray($key);
	    }
    	
	    /* merges the store and the array */
    	if ($add) {
    		$array = array_merge_recursive($array, $key);
    	} else {
        	$array = self::_mergeRecursive($array, $key);
    	}
	}
	
	/**
	 * Adds a value to the array pointed by the key
	 * 
	 * If the first argument is an array, values are merged recursively.
	 * The array is first dimensionized
	 * You can add values to sub arrays by using a path-like key.
	 * For example, to add a value to the array $array[key1][key2]
	 * use the key 'key1/key2'
	 * If the value pointed by the key is not an array, it will be
	 * transformed to one.
	 * Can be used on any array by specifying the third argument
	 *
	 * @see Atomik::_dimensionizeArray()
	 * @param array|string $key Can be an array to add many key/value
	 * @param mixed $value OPTIONAL
	 * @param bool $dimensionize OPTIONAL Whether to use Atomik::_dimensionizeArray()
	 * @param array $array OPTIONAL The array on which the operation is applied
	 */
	public static function add($key, $value = null, $dimensionize = true, &$array = null)
	{
		return self::set($key, $value, $dimensionize, $array, true);
	}
	
	/**
	 * Like array_merge() but recursively
	 *
	 * @see array_merge()
	 * @param array $array1
	 * @param array $array2
	 * @return array
	 */
	public static function _mergeRecursive($array1, $array2)
	{
	    $array = $array1;
	    foreach ($array2 as $key => $value) {
	        if (is_array($value) && array_key_exists($key, $array1) && is_array($array1[$key])) {
	            $array[$key] = self::_mergeRecursive($array1[$key], $value);
	            continue;
	        }
	        $array[$key] = $value;
	    }
	    return $array;
	}
	
	/**
	 * Recursively checks array for path-like keys (ie. keys containing slashes)
	 * and transform them into multi dimensions array
	 *
	 * @param array $array
	 * @return array
	 */
	public static function _dimensionizeArray($array)
	{
		$dimArray = array();
		
		foreach ($array as $key => $value) {
			/* checks if the key is a path */
			if (strpos($key, '/') !== false) {
				$parts = explode('/', $key);
				$firstPart = array_shift($parts);
				/* recursively dimensionize the key */
				$value = self::_dimensionizeArray(array(implode('/', $parts) => $value));
				
				if (isset($dimArray[$firstPart])) {
					if (!is_array($dimArray[$firstPart])) {
						/* if $firstPart exists but is not an array, drops the value and use an array */
						$dimArray[$firstPart] = array();
					}
					/* merge recursively both arrays */
					$dimArray[$firstPart] = self::_mergeRecursive($dimArray[$firstPart], $value);
				} else {
					$dimArray[$firstPart] = $value;
				}
				
			} else if (is_array($value)) {
				/* dimensionize sub arrays */
				$value = self::_dimensionizeArray($value);
				if (isset($dimArray[$key])) {
					$dimArray[$key] = self::_mergeRecursive($dimArray[$key], $value);
				} else {
					$dimArray[$key] = $value;
				}
			} else {
				$dimArray[$key] = $value;
			}
		}
		
		return $dimArray;
	}
	
	/**
	 * Gets a value using its associatied key from the store
	 * 
	 * You can fetch value from sub arrays by using a path-like
	 * key. Separate each key with a slash. For example if you want 
	 * to fetch the value from an $store[key1][key2][key3] you can use
	 * key1/key2/key3
	 * Can be used on any array by specifying the third argument
	 *
	 * @param string|array $key OPTIONAL If null, fetches all values (default null)
	 * @param mixed $default OPTIONAL Default value if the key is not found (default null)
	 * @param array $array OPTIONAL The array on which the operation is applied
	 * @return mixed
	 */
	public static function get($key = null, $default = null, $array = null)
	{
	    /* returns the store */
	    if ($key === null) {
	        return self::$_store;
	    }
	    
	    /* checks if a namespace is used */
	    if (is_string($key) && preg_match('/^([a-z]+):(.*)/', $key, $match)) {
	        /* checks if the namespace exists */
	        if (isset(self::$_namespaces[$match[1]])) {
	            /* calls the namespace callback and returns */
	            $args = func_get_args();
	            $args[0] = $match[2];
	            return call_user_func_array(self::$_namespaces[$match[1]], $args);
	        }
	    }
	    
		/* if $data is null, uses the global store */
		if ($array === null) {
		    $array = self::$_store;
		}
		
		/* checks if the $key is an array */
	    if (!is_array($key)) {
	        /* checks if it has slashes */
	        if (strpos($key, '/') === false) {
		        /* return the value */
	            return array_key_exists($key, $array) ? $array[$key] : $default;
	        }
	        
            /* creates an array by spliting using slashes */
            $key = explode('/', $key);
		}
		
		/* checks if the key exists */
		$firstKey = array_shift($key);
		if (isset($array[$firstKey])) {
		    if (count($key) > 0) {
		        /* there's still keys so it goes deeper */
		        return self::get($key, $default, $array[$firstKey]);
		    } else {
		        /* the key has been found */
		        return $array[$firstKey];
		    }
		}
		
		/* key not found, returns default */
		return $default;
	}
	
	/**
	 * Checks if a key is defined in the store
	 * 
	 * Can check through sub array using a path-like key
	 * Can be used on any array by specifying the second argument
	 *
	 * @see Atomik::get()
	 * @param string $key
	 * @param array $array OPTIONAL The array on which the operation is applied
	 * @return bool
	 */
	public static function has($key, $array = null)
	{
	    /* returns the store */
	    if ($array === null) {
	        $array = self::$_store;
	    }
	    
		/* checks if the $key is an array */
	    if (!is_array($key)) {
	        /* checks if it has slashes */
    	    if (!strpos($key, '/')) {
    	        return array_key_exists($key, $array);
    	    }
            /* creates an array by spliting using slashes */
            $key = explode('/', $key);
	    }
	    
		/* checks if the key exists */
	    $firstKey = array_shift($key);
	    if (array_key_exists($firstKey, $array)) {
	        if (count($key) == 0) {
		        /* the key has been found */
	            return true;
	        } else if (is_array($array[$firstKey])) {
		        /* there's still keys so it goes deeper */
	            return self::has($key, $array[$firstKey]);
	        }
	    }
	    
		/* key not found */
	    return false;
	}
	
	/**
	 * Deletes a key from the store
	 * 
	 * Can delete through sub array using a path-like key
	 * Can be used on any array by specifying the second argument
	 *
	 * @see Atomik::get()
	 * @param string $key
	 * @param array $array OPTIONAL The array on which the operation is applied
	 */
	public static function delete($key, &$array = null)
	{
	    /* returns the store */
	    if ($array === null) {
	        $array = &self::$_store;
	    }
	    
		/* checks if the $key is an array */
	    if (!is_array($key)) {
	        /* checks if it has slashes */
    	    if (!strpos($key, '/')) {
    	        if (array_key_exists($key, $array)) {
    	            unset($array[$key]);
    	            return;
    	        }
    	    }
            /* creates an array by spliting using slashes */
            $key = explode('/', $key);
	    }
	    
		/* checks if the key exists */
	    $firstKey = array_shift($key);
	    if (array_key_exists($firstKey, $array)) {
	        if (count($key) == 0) {
		        unset($array[$firstKey]);
	        } else if (is_array($array[$firstKey])) {
		        /* there's still keys so it goes deeper */
	            self::delete($key, $array[$firstKey]);
	        } else {
	            throw new Exception('Key "' . $key . '" does not exists');
	        }
	    }
	}
	
	/**
	 * Registers a new selector namespace
	 * 
	 * A namespace preceed a key. When used, $callback will be 
	 * called instead of the normal logic. Applies only on get() calls.
	 *
	 * @param string $namespace
	 * @param callback $callback
	 */
	public static function registerSelector($namespace, $callback)
	{
	    self::$_namespaces[$namespace] = $callback;
	}
	
	
	/* -------------------------------------------------------------------------------------------
	 *  Plugins methods
	 * ------------------------------------------------------------------------------------------ */
	
	
	/**
	 * Loads a plugin
	 *
	 * @param string $plugin The plugin name
	 * @param array $config OPTIONAL Configuration for this plugin
	 * @param array $dirs OPTIONAL Directories from where to load the plugin
	 * @param string $classNameTemplate OPTIONAL % will be replaced with the plugin name
	 * @param bool $callStart OPTINAL Whether to call the start() method on the plugin class
	 * @return bool Success
	 */
	public static function loadPlugin($plugin, $config = array(), $dirs = null, $classNameTemplate = '%Plugin', $callStart = true)
	{
		$plugin = ucfirst($plugin);
		
		/* use default directories */
		if ($dirs === null) {
			$dirs = self::get('atomik/dirs/plugins');
		}
		
		/* checks if the plugin is already loaded */
		if (self::isPluginLoaded($plugin, $dirs)) {
			return true;
		}
		
		self::fireEvent('Atomik::Plugin::Before', array(&$plugin, &$config));
		
		/* checks if $plugin has been set to false from one of the event callbacks */
		if ($plugin === false) {
			return false;
		}
		
		/* checks if the class is defined */
		$pluginClass = str_replace('%', $plugin, $classNameTemplate);
		if (!class_exists($pluginClass, false)) {
			
			/* tries to load the plugin from a file */
			if (($filename = self::path($plugin . '.php', $dirs)) === false) {
				/* no file, checks for a directory */
				if (($dirname = self::path($plugin, $dirs)) === false) {
					/* plugin not found */
					throw new Exception('Missing plugin (no file or directory matching plugin name): ' . $plugin);
				} else {
					/* directory found, plugin file should be inside */
					$filename = $dirname . '/Plugin.php';
					$pluginDir = dirname($dirname);
					if (!file_exists($filename)) {
						throw new Exception('Missing plugin (no file inside the plugin\'s directory): ' . $plugin);
					}
					/* adds the libraries folder from the plugin directory to the include path */
					if (@is_dir($dirname . '/libraries')) {
						set_include_path($dirname . '/libraries'. PATH_SEPARATOR . get_include_path());
					}
				}
			} else {
				$pluginDir = dirname($filename);
			}
			
			/* loads the plugin */
			require($filename);
		}
		
		/* checks if the *Plugin class is defined. The use of this class
		 * is not mandatory in plugin file */
		if (class_exists($pluginClass, false)) {
		    $registerEventsCallback = true;
		    
			/* call the start method on the plugin class if it's defined */
		    if ($callStart && method_exists($pluginClass, 'start')) {
    		    if (call_user_func(array($pluginClass, 'start'), $config) === false) {
    		        $registerEventsCallback = false;
    		    }
		    }
		    
		    /* automatically registers events callback for methods starting with "on" */
		    if ($registerEventsCallback) {
		    	self::attachClassListeners($pluginClass);
		    }
		}
		
		self::fireEvent('Atomik::Plugin::After', array($plugin));
		
		/* stores the plugin name so we won't load it twice */
		$pluginDir = isset($pluginDir) ? rtrim($pluginDir, DIRECTORY_SEPARATOR) : true;
		if (isset(self::$_plugins[$plugin])) {
			self::$_plugins[$plugin][] = $pluginDir;
		} else {
			self::$_plugins[$plugin] = array($pluginDir);
		}
		
		return true;
	}
	
	/**
	 * Checks if a plugin is already loaded
	 *
	 * @param string $plugin
	 * @return bool
	 */
	public static function isPluginLoaded($plugin, $dirs = null)
	{
		$plugin = ucfirst($plugin);
		
		/* use default directories */
		if ($dirs === null) {
			$dirs = self::get('atomik/dirs/plugins');
		}
		
		if (!isset(self::$_plugins[$plugin])) {
			/* plugin not loaded */
			return false;
		}
		
		if (self::$_plugins[$plugin] === true) {
			/* plugin loaded and was not included (so no dir check needed) */
			return true;
		}
		
		/* plugin loaded using include, so it checks if $dirs match
		 * the dir from where the plugin has been loaded */
		foreach (self::path($dirs, true) as $dir) {
			if (in_array(trim($dir, DIRECTORY_SEPARATOR), self::$_plugins[$plugin])) {
				return true;
			}
		}
		
		return false;
	}
	
	
	/* -------------------------------------------------------------------------------------------
	 *  Events methods
	 * ------------------------------------------------------------------------------------------ */
	
	
	/**
	 * Registers a callback to an event
	 *
	 * @param string $event The event name
	 * @param callback $callback
	 * @param int $priority OPTIONAL
	 * @param bool $important OPTIONAL If a listener of the same priority already exists, registers the new listener before the existing one. 
	 */
	public static function listenEvent($event, $callback, $priority = 50, $important = false)
	{
		/* initialize the current event array */
		if (!isset(self::$_events[$event])) {
			self::$_events[$event] = array();
		}
		
		/* while there is an event with the same priority, checks
		 * with an higher or lower priority
		 */
		while (isset(self::$_events[$event][$priority])) {
			$priority += $important ? -1 : 1;
		}
		
		/* stores the callback */
		self::$_events[$event][$priority] = $callback;
	}
	
	/**
	 * Automatically registers events callback for methods starting with "on"
	 *
	 * @param string|object $class
	 */
	public static function attachClassListeners($class)
	{
        $methods = get_class_methods($class);
        foreach ($methods as $method) {
            if (preg_match('/^on[A-Z].*$/', $method)) {
                $event = preg_replace('/(?<=\\w)([A-Z])/', '::\1', substr($method, 2));
                self::listenEvent($event, array($class, $method));
            }
        }
	}
	
	/**
	 * Fires an event
	 * 
	 * @param string $event The event name
	 * @param array $args OPTIONAL Arguments for the callback
	 * @param bool $resultAsString OPTIONAL Whether to return all callback results as a string
	 * @return $array An array containing results of each executed callbacks
	 */
	public static function fireEvent($event, $args = array(), $resultAsString = false)
	{
		$results = array();
		
		// executes all callback
	    if (isset(self::$_events[$event])) {
	    	$keys = array_keys(self::$_events[$event]); 
	    	sort($keys);
			foreach ($keys as $key) {
				$callback = self::$_events[$event][$key];
				$key = is_array($callback) ? implode('::', $callback) : $callback;
				$results[$key] = call_user_func_array($callback, $args);
			}
		}
		
		
		if ($resultAsString) {
			return implode('', $results);
		}
		return $results;
	}
	
	
	/* -------------------------------------------------------------------------------------------
	 *  Helper methods
	 * ------------------------------------------------------------------------------------------ */
	
	/**
	 * Builds a path.
	 * 
	 * Multiple cases possible:
	 * 
	 *  1) path($setOfPaths, $asArray = false)
	 *     Returns a path from a set of paths (the set can be a string
	 *     or an array). If the second argument is true, it returns
	 *     all paths from the set as an array
	 *
	 *  2) path($file, $setOfPaths, $check = true)
	 *     Searches for a file in the set of paths and returns the
	 *     first one it finds. If $check is set to false, it returns
	 *     the filename as if it was in the first path from the set of
	 *     paths. Returns false when $check is true and no file where found.
	 *
	 * @param string|array $file
	 * @param string|array|bool $paths
	 * @param bool $check
	 * @return string|array
	 */
	public static function path($file, $paths = null, $check = true)
	{
	    /* case1, $file is an array */
	    if (is_array($file)) {
	        if ($paths === true) {
	            /* returns $paths as array */
	            return $file;
	        }
	        /* returns the first path from the paths */
	        return $file[0];
	    }
	    /* $file is a string */
        
	    /* case 1 */
        if ($paths === null || is_bool($paths)) {
            if ($paths === true) {
                /* returns $file as array */
                return array($file);
            }
            /* returns $file as string */
            return $file;
        }
        
        /* case 2, $paths is a string */
        if (is_string($paths)) {
            $filename = rtrim($paths, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
            if (!$check || file_exists($filename)) {
                return $filename;
            }
            return false;
        }
        
        /* case 2, $paths is an array */
        if (is_array($paths)) {
            foreach ($paths as $path) {
                $filename = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
                if (!$check || file_exists($filename)) {
                    return $filename;
                }
            }
        }
        
        /* nothing found */
        return false;
	}
	
	/**
	 * Returns an url for the action depending on whether url rewriting
	 * is used or not
	 * 
	 * Can be used on links starting with a protocol but they will of course
	 * not be resolved like action names.
	 *
	 * @param string $action The action name. Can contain GET parameters (after ?)
	 * @param array $params OPTIONAL GET parameters
	 * @param bool $useIndex OPTIONAL (default true) Whether to use index.php in the url
	 * @return string
	 */
	public static function url($action = null, $params = array(), $useIndex = true)
	{
		if ($action === null) {
			$action = self::get('request');
		}
		
		/* removes the query string from the action */
		$queryString = '';
		if (strpos($action, '?') !== false) {
			$parts = explode('?', $action);
			$action = $parts[0];
			$queryString = $parts[1];
		}
		
		/* adds parameters to the query string */
		foreach ($params as $param => $value) {
			if (preg_match('/:' . $param . '/', $action)) {
				$action = str_replace(':' . $param, $value, $action);
			} else {
				$queryString .= $param . '=' . urlencode($value);
			}
		}
		
		/* checks if $action is not a url (checking if there is a protocol) */
		if (!preg_match('/^([a-z]+):\/\/.*/', $action)) {
			/* base url */
			$url = rtrim(self::get('base_url', '.'), '/') . '/';
			
			/* checks if url rewriting is used */
			if (!$useIndex || self::get('url_rewriting', false) === true) {
				$url .= $action . (!empty($queryString) ? '?' . $queryString : '');
			} else {
				/* no url rewriting, using index.php */
				$url .= 'index.php?' . self::get('atomik/trigger') 
				      . '=' . $action . (!empty($queryString) ? '&' . $queryString : '');
			}
		}
		
		/* trigger an event */
		$args = func_get_args();
		unset($args[0]);	
		self::fireEvent('Atomik::Url', array($action, &$url, $args));
		
		return $url;
	}
	
	/**
	 * Returns the url of an asset file (ie. an url without index.php)
	 *
	 * @param string $filename
	 * @param array $params OPTIONAL
	 * @return string
	 */
	public static function asset($filename, $params = array())
	{
		return self::url($filename, $params, false);
	}

	/*
	 * Includes a file
	 *
	 * @param string $include Filename or class name following the PEAR convention
	 * @param bool $className OPTIONAL If false, $include can't be a class name
	 * @param string|array $dirs OPTIONAL Include from specific directories rather than include path
	 */
	public static function needed($include, $className = true, $dirs = null)
	{
		self::fireEvent('Atomik::Needed', array(&$include, &$className, &$dirs));
		if ($include === null) {
			return;
		}
		
		if ($className && strpos($include, '_') !== false) {
			$include = str_replace('_', DIRECTORY_SEPARATOR, $include);
		}
		$include .= '.php';
		
		if ($dirs !== null) {
	    	require_once(self::path($include, $dirs));
		} else {
			require_once($include);
		}
	}

	/**
	 * Redirects to another url
	 *
	 * @see Atomik::url()
	 * @param string $url
	 * @param bool $useUrl OPTIONAL (default true) Use Atomik::url()
	 */
	public static function redirect($url, $useUrl = true)
	{
		self::fireEvent('Atomik::Redirect', array(&$url, $useUrl));
		
		/* uses Atomik::url() */
		if ($useUrl) {
			$url = self::url($url);
		}
		
		/* redirects */
		header('Location: ' . $url);
		self::end();
	}
	
	/**
	 * Triggers a 404 error
	 */
	public static function trigger404()
	{
		self::fireEvent('Atomik::404');
		
		/* HTTP header */
		header('HTTP/1.0 404 Not Found');
		
		if (file_exists($filename = self::get('atomik/files/404'))) {
			/* includes the 404 error file */
			include($filename);
		} else {
			echo '<h1>404 - File not found</h1>';
		}
		
		self::end();
	}
	
	/**
	 * Builds an html string from an array
	 * 
	 * Array's value can be a string or if the key is a string, which in this
	 * case represent a tag name, an array.
	 * Attributes start with @. Attributes can be defined as one array using
	 * the "@" key (in this case, the attributes array keys do not need to start
	 * with @).
	 *
	 * @param string|array $html
	 * @param string $tag OPTIONAL Wrap the html into an element with this tag name
	 * @param bool $dimensionize OPTIONAL Whether to use Atomik::_dimensionizeArray()
	 * @return string
	 */
	public static function html($html, $tag = null, $dimensionize = false)
	{
		if ($tag !== null) {
			/* building a proper $html array using the tag */
			return self::html(array($tag => $html));
		}
		
		self::fireEvent('Atomik::Html', array(&$html, &$tag, &$dimensionize));
		
		if (is_string($html)) {
			/* no need to parse */
			return $html;
		}
		
		if ($dimensionize) {
			/* dimensionizing the array */
			$html = self::_dimensionizeArray($html);
		}
		
		$output = '';
		
		foreach ($html as $tag => $value) {
			/* do not parse attributes */
			if (substr($tag, 0, 1) == '@') {
				continue;
			}
			/* the key does not represent a tag */
			if (!is_string($tag)) {
				if (!is_array($value)) {
					$output .= $value;
					continue;
				}
			}
			/* the key is a tag */
			$attributes = array();
			if (is_array($value)) {
				/* has more than one children */
				if (isset($value['@'])) {
					/* using the @ key for setting attributes */
					foreach ($value['@'] as $attrName => $attrValue) {
						$attributes[] = sprintf('%s="%s"', $attrName, $attrValue);
					}
				} else {
					/* no @ key found, checking through all sub keys for the ones
					 * starting with @ */
					foreach ($value as $attrName => $attrValue) {
						if (substr($attrName, 0, 1) == '@') {
							$attributes[] = sprintf('%s="%s"', substr($attrName, 1), $attrValue);
						}
					}
				}
				/* parsing the value */
				$keys = array_keys($value);
				if (!is_string($keys[0]) && is_array($value[0])) {
					foreach ($value as $item) {
						$output .= self::html($item, $tag);
					}
					break;
				} else {
					$value = self::html($value);
				}
			}
			
			/* builds the html string */
			$output .= sprintf("<%s %s>%s</%s>\n", $tag, implode(' ', $attributes), $value, $tag);
		}
		
		return $output;
	}
	
	/**
	 * Equivalent to var_dump() but can be disabled using the configuration
	 *
	 * @see var_dump()
	 * @param mixed $data
	 * @param bool $force OPTIONAL Always display the dump even if debug from the config is set to false
	 * @param bool $echo OPTIONAL Whether to echo
	 * @return string|null
	 */
	public static function debug($data, $force = false, $echo = true)
	{
		if (!$force && !self::get('debug', false)) {
			return;
		}
		
		self::fireEvent('Atomik::Debug', array(&$data, &$force, &$echo));
		
		/* var_dump() does not support returns */
		ob_start();
		var_dump($data);
		$dump = ob_get_clean();
		
		if (!$echo) {
			return $dump;
		}
		
		echo $dump;
	}
	
	/**
	 * Catch errors and throw an ErrorException instead
	 *
	 * @internal
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @param mixed $errcontext
	 */
	public static function errorHandler($errno, $errstr, $errfile = '', $errline = 0, $errcontext = null)
	{
		/* handles errors depending on the level defined with error_reporting */
		if ($errno <= error_reporting()) {
		    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		}
	}
	
	/**
	 * Renders an exception
	 * 
	 * @param Exception $exception
	 * @param bool $return OPTIONAL Return the output instead of printing it
	 * @return string
	 */
	public static function renderException($exception, $return = false)
	{	
		/* checks if the user defined error file is available */
		if (file_exists($filename = self::get('atomik/files/error'))) {
			include($filename);
			self::end(false);
		}
		
		$attributes = self::get('atomik/error_report_attrs');
	
		echo '<div ' . $attributes['atomik-error'] . '>'
		   . '<span ' . $attributes['atomik-error-title'] . '>'
		   . 'An error has occured!</span>';
		
		/* only display error information if atomik/display_errors is true */
		if (self::get('atomik/display_errors', false) === false) {
		    echo '</div>';
		    self::end(false);
		}
		
		/* builds the html erro report */
		$html = '<br />An error of type <strong>' . get_class($exception) . '</strong> '
		      . 'was caught at <strong>line ' . $exception->getLine() . '</strong><br />'
		      . 'in file <strong>' . $exception->getFile() . '</strong>'
		      . '<p>' . $exception->getMessage() . '</p>'
			  . '<table ' . $attributes['atomik-error-lines'] . '>';
		
	    /* builds the table which display the lines around the error */
		$lines = file($exception->getFile());
		$start = $exception->getLine() - 7;
		$start = $start < 0 ? 0 : $start;
		$end = $exception->getLine() + 7;
		$end = $end > count($lines) ? count($lines) : $end; 
		for($i = $start; $i < $end; $i++) {
		    /* color the line with the error. with standard Exception, lines are */
			if($i == $exception->getLine() - (get_class($exception) != 'ErrorException' ? 1 : 0)) {
				$html .= '<tr ' . $attributes['atomik-error-line-error'] . '><td>';
			}
			else {
				$html .= '<tr ' . $attributes['atomik-error-line'] . '>'
				       . '<td ' . $attributes['atomik-error-line-number'] . '>';
			}
			$html .= $i . '</td><td ' . $attributes['atomik-error-line-text'] . '>' 
			       . (isset($lines[$i]) ? htmlspecialchars($lines[$i]) : '') . '</td></tr>';
		}
		
		$html .= '</table>'
		       . '<strong>Stack:</strong><p ' . $attributes['atomik-error-stack'] . '>' 
		       . nl2br($exception->getTraceAsString())
		       . '</p></div>';
		
		echo $html;
	}
}