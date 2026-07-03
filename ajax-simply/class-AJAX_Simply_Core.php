<?php

/**
 * Main Ajax Simply class.
 *
 * Class AJAX_Simply_Core
 */
class AJAX_Simply_Core {

	use AJAX_Simply_Core__response_methods;

	public $data     = []; // POST data

	static $__buffer = null;

	static $instance = null;

	function __construct(){}

	# for isset() and empty()
	function __isset( $name ){
		return $this->_get_param( $name ) !== null;
	}

	function __get( $name ){
		return $this->_get_param( $name );
	}

	function _get_param( $name ){

		if( !empty($_FILES) ){
			foreach( $_FILES as & $files )
				$files = self::_maybe_compact_files( $files );
		}

		if( isset($_FILES[ $name ]) )
			return $_FILES[ $name ];

		if( isset($this->data[ $name ]) ){
			$val = $this->data[ $name ];

			if( is_array($val) ){
				array_walk_recursive( $val, function( &$val, $key ){
					if( $val && !preg_match('/[^0-9]/',$val) && intval($val) == $val ) $val = intval($val);
					elseif( is_string($val) ) $val = trim( $val, ' ' ); // delete spaces
				});
			}
			elseif( $val && !preg_match('/[^0-9]/',$val) && intval($val) == $val ) $val = intval($val); // error ex: is_numeric('682e825771') - true
			elseif( is_string($val)  ) $val = trim( $val, ' ' ); // delete spaces

			return $val;
		}

		// at the end
		if( $name === 'files' )
			return $_FILES;

		return null;
	}

	# collects an uncomfortable array of files into compact arrays of each file and adds the resulting array to the 'compact' index.
	static function _maybe_compact_files( $files ){

		if( isset($files['compact']) )                               return $files; // already added
		if( !isset($files['name']) || ! is_array( $files['name'] ) ) return $files; // if 'name' is not an array, then the field is not 'multiple'...

		foreach( $files as $key => $data ){
			foreach( $data as $index => $val )
				$files['compact'][ $index ][ $key ] = $val; // добалвяем
		}

		return $files;
	}

	static function init(){

		$_DATA = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

		// not ajaxs action
		if( empty($_REQUEST['jxs_act']) )
			return;

		// increase run time when files are uploaded
		if( !empty($_FILES) && !ini_get('safe_mode') ) @ set_time_limit( 300 );

		// ajaxs_nonce - not depends on POST or GET request method
		$_DATA['ajaxs_nonce'] = isset($_REQUEST['ajaxs_nonce']) ? $_REQUEST['ajaxs_nonce'] : '';

		// action can be:               function_name | class::method
		// it will turns to:      ajaxs_function_name | AJAXS_class::method
		// or to:            ajaxs_priv_function_name | AJAXS_PRIV_class::method
		// or to:                                     | class::ajaxs_method
		// or to:                                     | class::ajaxs_priv_method
		$jxs_act = $_REQUEST['jxs_act'];
		$jxs_act = preg_replace( '~[^a-zA-Z0-9_:\->()]~', '', $jxs_act ); // delete unwonted characters
		$jxs_act = preg_replace( '~\(\)$~', '', $jxs_act ); // delete '()' at the end

		unset( $_DATA['action'], $_DATA['jxs_act'] ); // clear no need internal vars

		// init instance -----------

		$jx = self::$instance = new self;

		$jx->data = wp_unslash( $_DATA );

		// change bool types to it's type
		array_walk_recursive( $jx->data, function( &$val, $key ){
			if    ( strcasecmp($val, 'false')      == 0 ) $val = false;
			elseif( strcasecmp($val, 'true')       == 0 ) $val = true;
			elseif( strcasecmp($val, 'null')       == 0 ) $val = null;
			elseif( strcasecmp($val, 'undefined')  == 0 ) $val = null;
			// 'int' and 'string' stays raw here, it will be modified in magic properties
		});

		// basic nonce check
		$_need_check = jxs_options('allow_nonce') && empty($_DATA['skip_basic_nonce']);
		if( $_need_check && ! wp_verify_nonce( $jx->ajaxs_nonce, 'ajaxs_action' ) ){
			$jx->console( 'AJAXS ERROR: wrong nonce code', 'error' );
			wp_die( -1, 403 );
		}

		$action = $jxs_act;

		// to insert General checks through a filter, before processing the request.
		// For example, if a query group requires the same authorization check,
		// in order not to write the same thing every time in the handler function, you can add a check once through this hook.
		$allow = apply_filters( 'ajaxs_allow_process', true, $action, $jx );
		if( ! $allow && $allow !== null ){
			$jx->console( 'AJAXS ERROR: process not allowed', 'error' );
			wp_die( -1, 403 );
		}

		// parse action - class::method
		if(     strpos($action, '->') ) $action = explode('->', $action ); // 'myclass->method'
		elseif( strpos($action, '::') ) $action = explode('::', $action ); // 'myclass::method'

		$actions = [];
		$fn__has_prefix = function( $string ){
			return preg_match( '/^ajaxs_/i', $string );
		};

		// class method
		if( is_array($action) ){

			list( $class, $method ) = $action;

			// add prefixes, if there are no prefix in the class and method name: 'AJAXS_' or 'AJAXS_PRIV_' (for class) и 'ajaxs_' or 'ajaxs_priv_' (for method)
			if( $fn__has_prefix($class) || $fn__has_prefix($method) ){
				$actions[] = [ $class, $method ];
			}
			else {
				$actions[] = [ "AJAXS_{$class}", $method ];
				$actions[] = [ $class, "ajaxs_$method" ];

				if( is_user_logged_in() ){
					$actions[] = [ "AJAXS_PRIV_$class", $method ];
					$actions[] = [ $class, "ajaxs_priv_$method" ];
				}
			}


		}
		// function
		else {
			$action = preg_replace( '~[^A-Za-z0-9_]~', '', $action );

			if( $fn__has_prefix($action) ){
				$actions[] = $action;
			}
			else {
				$actions[] = "ajaxs_{$action}";

				if( is_user_logged_in() )
					$actions[] = "ajaxs_priv_{$action}";
			}

		}

		// CALL action

		// try to find the handler
		foreach( $actions as $_action ){
			if( is_callable($_action) ){
				$action_found = true;
				self::$__buffer = call_user_func( $_action, $jx );
			}
		}

		// no matching function - use basic hooks WP AJAX: 'wp_ajax_{$action}' or 'wp_ajax_nopriv_{$action}'
		if( empty($action_found) ){

			if ( is_user_logged_in() )
				$hook_name = "wp_ajax_{$jxs_act}";
			else
				$hook_name = "wp_ajax_nopriv_{$jxs_act}";

			$return = apply_filters( $hook_name, $jx );

			if( $return instanceof AJAX_Simply_Core )
				$jx->console( 'AJAXS ERROR: There is no function, method, hook for handle AJAXS request in PHP! Current action: "'. $jxs_act .'"', 'error' );
			else
				self::$__buffer = $return;
		}

		//ob_end_clean(); // works on exit;

		exit;
	}

	static function _shutdown_function(){

		if( WP_DEBUG && WP_DEBUG_DISPLAY )
			self::_console_error_massage( error_get_last() ); // fatal error

		$reply  = self::$__reply;
		$buffer = self::$__buffer;

		// if handler function return data
		if( ! isset($reply['response']) ){
			$reply['response'] = null;

			if( $buffer !== null ){
				// $is_json - for functions like 'wp_send_json_error()'
				$is_json = is_string( $buffer ) && is_array( json_decode($buffer, true) ) && ( json_last_error() == JSON_ERROR_NONE );

				$reply['response'] = $is_json ? json_decode( $buffer ) : $buffer;
			}
		}

		// remove no need element 'response' if there is no 'extra' parameter and send response as it is
		if( empty($reply['extra']) )
			$reply = $reply['response'];

		echo wp_json_encode( $reply );
	}

	static function _console_error_massage( $args ){

		// error_get_last() has no error
		if( $args === null )
			return null;

		// error_get_last()
		if( is_array($args) ){

			list( $errno, $errstr, $errfile, $errline ) = array_values( $args );

			// only for fatal errors, because we cant define @suppress here
			if( ! in_array( $errno, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING ] ) )
				return null;

			$console_type = 'error'; // fatal error
		}
		// set_error_handler()
		else
			list( $errno, $errstr, $errfile, $errline ) = func_get_args();

		// for @suppress
		$errno = $errno & error_reporting();
		if( $errno == 0 )
			return null;

		if( ! defined('E_STRICT') )            define('E_STRICT', 2048);
		if( ! defined('E_RECOVERABLE_ERROR') ) define('E_RECOVERABLE_ERROR', 4096);

		$err_names = [
			// fatal errors
			E_ERROR             => 'Fatal error',
			E_PARSE             => 'Parse Error',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			// other errors
			E_WARNING           => 'Warning',
			E_NOTICE            => 'Notice',
			E_STRICT            => 'Strict Notice',
			E_RECOVERABLE_ERROR => 'Recoverable Error',
			// user type errors
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
		];

		$err_name = "Unknown error ($errno)";
		if( isset($err_names[ $errno ]) )
			$err_name = $err_names[ $errno ];

		if( empty($console_type) )
			$console_type = 'log';
		elseif( in_array( $errno, [ E_WARNING, E_USER_WARNING ] ) )
			$console_type = 'warn';

		self::_static_console( "$err_name: $errstr in $errfile on line $errline", $console_type, 'PHP errors' );

		return true; // don't execute PHP internal error handler for set_error_handler()
	}

}

trait AJAX_Simply_Core__response_methods {

	static $__reply  = [];

	/**
	 * Alias of self::success().
	 *
	 * @param mixed $data
	 */
	function done( $data = null ){
		$this->success( $data );
	}

	/**
	 * Alias of self::success().
	 *
	 * @param mixed $data
	 */
	function ok( $data = null ){
		$this->success( $data );
	}

	/**
	 * Die PHP execution.
	 *
	 * @param mixed $data
	 */
	function success( $data = null ){

		if( is_wp_error( $data ) )
			$this->error( $data );

		self::$__reply['response'] = [
			'success' => true,
			'ok'      => true, // alias of success
			'error'   => false,
			'data'    => $data
		];

		exit;
	}

	/**
	 * Die PHP execution.
	 *
	 * @param mixed $data
	 */
	function error( $data = null ){

		if( is_wp_error( $data ) ){
			$new_data = [];
			foreach( $data->errors as $code => $messages ){
				foreach( $messages as $message )
					$new_data[] = "$code: $message\n\n";
			}
			$data = implode( '<br><br>', $new_data );
		}

		self::$__reply['response'] = [
			'success' => false,
			'ok'      => false, // alias of success
			'error'   => true,
			'data'    => $data
		];

		exit;
	}

	/**
	 * Alias for self::reload().
	 *
	 * @param int $delay
	 */
	function refresh( $delay = 0 ){
		$this->reload( $delay );
	}

	/**
	 * @param int $delay  Delay in milliseconds: 1000 = 1 second
	 */
	function reload( $delay = 0 ){
		self::$__reply['extra']['reload'] = $delay ? intval($delay) : 1;
	}

	/**
	 * @param string $url    Redirect URL.
	 * @param int    $delay  Delay in milliseconds: 1000 = 1 second
	 */
	function redirect( $url, $delay = 0 ){
		self::$__reply['extra']['redirect'] = [ wp_sanitize_redirect($url), $delay ];
	}

	/**
	 * @param string $selector jQuery selector.
	 * @param string $html     HTML code.
	 * @param string $method   Method to manipulate passed HTML. Ex: html(by default)|append|prepend|replace|before|after
	 */
	function html( $selector, $html, $method = 'html' ){

		if( 'replace' === $method )
			$method = 'replaceWith';

		self::$__reply['extra']['html'][] = [ $selector, $html, $method ];
	}

	function append( $selector, $html ){
		$this->html( $selector, $html, 'append' );
	}

	function prepend( $selector, $html ){
		$this->html( $selector, $html, 'prepend' );
	}

	function before( $selector, $html ){
		$this->html( $selector, $html, 'before' );
	}

	function after( $selector, $html ){
		$this->html( $selector, $html, 'after' );
	}

	function replace( $selector, $html ){
		$this->html( $selector, $html, 'replace' );
	}

	function alert( $data ){
		if( is_array($data) || is_object($data) ){
			$data = print_r( $data, 1 );
		}

		self::$__reply['extra']['alert'][] = $data;
	}

	function trigger( $event, $selector = false, $args = [] ){
		self::$__reply['extra']['trigger'][] = [ $event, $selector, $args ];
	}

	function call( $func_name /* $param1, $param2 */ ){
		$args = array_slice( func_get_args(), 1 );

		self::$__reply['extra']['call'][] = [ $func_name, $args ];
	}

	# alais for ::jseval()
	function jscode( $jscode ){
		$this->jseval( $jscode );
	}

	function jseval( $jscode ){
		self::$__reply['extra']['jseval'][] = $jscode;
	}

	/**
	 * Alias of AJAX_Simply_Core::console().
	 *
	 * @param mixed ...$params
	 */
	function log( ...$params ){
		call_user_func_array( [ $this, 'console' ], $params );
	}

	/**
	 * $type: log, warn, error. Except multiple parameters.
	 *
	 * Specify 'log', 'warn', 'error' string in last parameter to set type of concole message.
	 *
	 * @param mixed ...$params
	 */
	function console( ...$params ){

		// if last element is: log, warn, error
		// !array_pop()
		$type = in_array( end($params), [ 'log', 'warn', 'error', 'info' ], true ) ? array_pop( $params ) : 'log';

		// ! after type
		static $group_num = 1;
		$group_name = 'group_'. $group_num++;
		$param_count = count( $params );
		$param_first = reset( $params );

		if( $param_count > 1 && is_string($param_first) && '::' === substr($param_first, 0, 2) ){
			$group_name = substr( $param_first, 2 );
			array_shift( $params );
		}
		$group = $param_count > 1 ? $group_name : null;

		foreach( $params as $data )
			self::_static_console( $data, $type, $group );
	}

	/**
	 * var_dump() to console. Except multiple parameters.
	 *
	 * @param mixed $data
	 */
	function dump( $data ){

		foreach( func_get_args() as $data ){
			ob_start();
			var_dump( $data );
			$data = ob_get_clean();

			self::_static_console( $data );
		}
	}

	/**
	 * Internal do not use - uses internally for PHP errors
	 *
	 * @param mixed  $data
	 * @param string $type
	 * @param string $group
	 */
	static function _static_console( $data, $type = 'log', $group = null ){

		if( is_array($data) || is_object($data) )
			$data = print_r( $data, 1 );

		self::$__reply['extra']['console'][] = [ $data, $type, $group ];
	}

	# normaly not used: PHP function can just return any value
	function response( $val ){
		self::$__reply['response'] = $val;
	}

}