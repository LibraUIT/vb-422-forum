<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_xml.php');
require_once(DIR . '/includes/class_vurl.php');

/**
* vBulletin XML-RPC Abstract Object
*
* This class provides the common methods to the Client and Server, primarily the ability to build a compliant XML-RPC item
*/
class vB_XMLRPC_Abstract
{
	/**
	* vBulletin Registry Object
	*
	* @var	Object
	*/
	var $registry = null;

	/**
	* vBulletin XML Object
	*
	* @var	Object
	*/
	var $xml_object = null;

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_XMLRPC_Abstract(&$registry)
	{
		if (!is_subclass_of($this, 'vB_XMLRPC_Abstract'))
		{
			trigger_error('Direct Instantiation of vB_XMLRPC_Abstract prohibited.', E_USER_ERROR);
			return NULL;
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error(get_class($this) . '::Registry object is not an object', E_USER_ERROR);
		}
	}

	/**
	* Private
	* Build XMPRPC Output
	*
	* The first parameter is the type, methodCall, methodResponse, or Fault (methodResponse)
	* then (unlimited number of) following parameters are the params
	* Params can be sent as strings or arrays that define their type.
	*
	* Per spec, methodResponse should have a maximum of one param
	*
	* @param	string	Type
	* @param	mixed	First variable to be inserted
	* @param	mixed	Nth variable to be inserted
	*
	*/
	function build_xml($type = 'methodCall')
	{
		$tempargs = func_get_args();
		$args = $tempargs[1];

		$this->xml_object = new vB_XML_Builder($this->registry);

		// Empty doc in case we call this method multiple times
		$this->xml_object->doc = '';

		if ($type == 'methodCall')
		{
			$this->outputtype = 'call';
			$this->xml_object->add_group('methodCall');
			$this->xml_object->add_tag('methodName', $args[0]);
			array_shift($args);
		}
		else if ($type == 'methodResponse' OR $type == 'fault')
		{
			$this->outputtype = 'response';
			$this->xml_object->add_group('methodResponse');
			if ($type == 'fault')
			{
				$this->xml_object->add_group('fault');
					$this->add_value($args[0]);
				$this->xml_object->close_group('fault');
				$this->xml_object->close_group('methodResponse');
				#echo '<pre>' . htmlspecialchars_uni($this->xml_object->doc) . '</pre>';
				return;
			}
		}
		$this->xml_object->add_group('params');

		foreach($args AS $key => $value)
		{
			$this->xml_object->add_group('param');
				$this->add_value($value);
			$this->xml_object->close_group('param');
		}
		$this->xml_object->close_group('params');
		if ($type == 'methodCall')
		{
			$this->xml_object->close_group('methodCall');
		}
		else
		{
			$this->xml_object->close_group('methodResponse');
		}
		#echo '<pre>' . htmlspecialchars_uni($this->xml_object->doc) . '</pre>';
	}

	/**
	* Private
	* Add <value> object to Output
	*
	* @param	string
	*/
	function add_value($value)
	{
		if (!is_array($value))
		{
			// convert string into an array
			$value = array('string' => $value);
		}

		$key = array_pop(array_keys($value));
		$this->xml_object->add_group('value');
		switch(strtolower($key))
		{
			case 'i4':
			case 'int':
				// Integer
					$this->xml_object->add_tag('int', intval($value["$key"]));
				break;
			case 'boolean':
				// boolean
					$this->xml_object->add_tag('boolean', ($value["$key"] == 1 OR strtolower($value["$key"]) == 'true') ? 1 : 0);
				break;
			case 'double':
				// float
					$this->xml_object->add_tag('double', floatval($value["$key"]));
				break;
			case 'datetime.iso8601':
				// datetime
				$this->xml_object->add_tag('dateTime.iso8601', strval($value["$key"]));
				break;
			case 'base64':
				// base64 encoded field
				// must already be encoded
				$this->xml_object->add_tag('base64', strval($value["$key"]));
				break;
			case 'array':
				if (!is_array($value["$key"]) OR empty($value["$key"]))
				{	// treat this as a string?
					$this->xml_object->add_tag('string', strval($value["$key"]));
				}
				else
				{
					$this->xml_object->add_group('array');
						$this->xml_object->add_group('data');
						foreach($value["$key"] AS $subkey => $subvalue)
						{
							$this->add_value($subvalue);
						}
						$this->xml_object->close_group('data');
					$this->xml_object->close_group('array');
				}
				// array'
				break;
			case 'struct':
				// struct
				if (!is_array($value["$key"]) OR empty($value["$key"]))
				{	// treat this as a string?
					$this->xml_object->add_tag('string', strval($value["$key"]));
				}
				else
				{
					$this->xml_object->add_group('struct');
					foreach($value["$key"] AS $subkey => $subvalue)
					{
						$this->xml_object->add_group('member');
							$this->xml_object->add_tag('name', $subvalue['name']);
							unset($subvalue['name']);
							$this->add_value($subvalue);
						$this->xml_object->close_group('member');
					}
					$this->xml_object->close_group('struct');
				}
				break;
			case 'string':
			default:
				$this->xml_object->add_tag('string', strval($value["$key"]));
		}
		$this->xml_object->close_group('value');
	}
}

/**
* vBulletin XML-RPC Server Object
*
* This class allows the parsing of an XML-RPC Post and give response
*/
class vB_XMLRPC_Server extends vB_XMLRPC_Abstract
{
	/**
	* Error string set by the xml parser in the event parsing fails at the XML level
	*
	* @var	string
	*/
	var $xml_error = null;

	/**
	* Parsed Array of xml_string
	*
	* @var	array
	*/
	var $xml_array = array();

	/**
	* Parsed Array of xml-rpc data
	*
	* @var	array
	*/
	var $xmlrpc_array = array();

	/**
	* Array of supported methods
	*
	* @var	array
	*/
	var $supportedmethods = array(
		'system.listMethods'     => 'system_listMethods',
		'system.getCapabilities' => 'system_getCapabilities',
	);

	/**
	* Specification for Fault Code Interoperability
	* http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php
	*
	* @var	array
	*/
	var $faultcodes = array(
		'-32700' => 'parse error: not well formed',
		'-32701' => 'parse error: unsupported encoding',
		'-32702' => 'parse error: invalid character for encoding',
		'-32600' => 'server error: invalid xml-rpc. not conforming to spec.',
		'-32601' => 'server error: requested method not found',
		'-32602' => 'server error: invalid method parameters',
		'-32603' => 'server error: internal xml-rpc error',
		'-32500' => 'application error',
		'-32400' => 'system error',
		'-32300' => 'transport error',
	);

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_XMLRPC_Server(&$registry)
	{
		parent::vB_XMLRPC_Abstract($registry);
	}

	/**
	* Parse XML data into an array, if possible.
	*
	* @param	string	XML Data
	*
	* @return boolean
	*/
	function parse_xml(&$data)
	{
		$this->xml_object = new vB_XML_Parser($data);
		$this->xml_object->include_first_tag = true;
		if ($this->xml_object->parse_xml())
		{
			$this->xml_array =& $this->xml_object->parseddata;
			$this->xml_error = '';
			return true;
		}
		else
		{
			// set error conditions...
			$this->xml_array = array();
			$this->xml_error = 'Error Line ' . $this->xml_object->error_line() . '::' . $this->xml_object->error_string();
			return false;
		}
	}

	/**
	* Add supported method
	*
	* @param	string Methodname
	* @param string verify_ function to call
	*
	*/
	function add_method($method, $function)
	{
		$this->supportedmethods["$method"] = $function;
	}

	/**
	* Parse the XML for RPC compatibility
	*
	*/
	function parse_xmlrpc()
	{
		$errorcode = 0;

		if ($this->xml_error)
		{
			$xml_error_struct = $this->build_fault_struct(-32700, $this->xml_error);
			$this->build_xml_response($xml_error_struct, 1);
			return false;
		}
		else
		{
			if (!empty($this->xml_array['methodCall']))
			{
				if ($methodname_function = $this->fetch_methodname($this->xml_array['methodCall']['methodName']))
				{
					if (is_array($this->xml_array['methodCall']['params']) AND !empty($this->xml_array['methodCall']['params']))
					{
						if (isset($this->xml_array['methodCall']['params']['param']) AND !empty($this->xml_array['methodCall']['params']['param']))
						{
							if ($this->{'verify_' . $methodname_function}($this->xml_array['methodCall']['params']['param']))
							{
								return $this->xmlrpc_array;
							}
						}
						else
						{	// Invalid XML-RPC
							$errorcode = '-32600';
						}
					}
					else if (isset($this->xml_array['methodCall']['params']))
					{
						$params = array();
						// no params given, check if our function doesn't have params
						if ($this->{'verify_' . $methodname_function}($params))
						{
							return $this->xmlrpc_array;
						}
					}
					else
					{	// Invalid XML-RPC
						$errorcode = '-32600';
					}
				}
				else
				{	// Invalid Method name
					$errorcode = '-32601';
				}
			}
			else if (!empty($this->xml_array['methodResponse']))
			{ // need to parse methodresponse..doesn't send anything on parse error

				if (isset($this->xml_array['methodResponse']['fault']['value']['struct']['member']))
				{	// Fault
					if ($this->xml_array['methodResponse']['fault']['value']['struct']['member'][0]['name'] == 'faultCode')
					{
						$this->xmlrpc_array['fault']['faultCode'] = intval($this->xml_array['methodResponse']['fault']['value']['struct']['member'][0]['value']['int']);
						$this->xmlrpc_array['fault']['faultString'] = strval($this->xml_array['methodResponse']['fault']['value']['struct']['member'][1]['value']['string']);
					}
					else if ($this->xml_array['methodResponse']['fault']['value']['struct']['member'][1]['name'] == 'faultCode')
					{
						$this->xmlrpc_array['fault']['faultCode'] = intval($this->xml_array['methodResponse']['fault']['value']['struct']['member'][1]['value']['int']);
						$this->xmlrpc_array['fault']['faultString'] = strval($this->xml_array['methodResponse']['fault']['value']['struct']['member'][0]['value']['string']);
					}
					if (empty($this->xmlrpc_array['fault']['faultCode']) OR empty($this->xmlrpc_array['fault']['faultString']))
					{
						return false;
					}

					return $this->xmlrpc_array;
				}
				else if (isset($this->xml_array['methodResponse']['params']['param']['value']['string']))
				{	// Normal Response
					$this->xmlrpc_array = array(
						'string' => strval($this->xml_array['methodResponse']['params']['param']['value']['string'])
					);
					return $this->xmlrpc_array;
				}
				else
				{
					return false;
				}
			}
			else	// invalid XML-RPC
			{
				$errorcode = '-32600';
			}

			if ($errorcode)
			{
				$xml_error_struct = $this->build_fault_struct($errorcode, $this->faultcodes["$errorcode"]);
				$this->build_xml_response($xml_error_struct, 1);
			}

			return false;
		}
	}

	/**
	* Construct the fault error structure for build_xml_response()
	*
	* @param	integer	Fault Code
	* @param	string	Fault String
	*
	* @return	array	structure array
	*/
	function build_fault_struct($faultcode, $faultstring)
	{
		$error = array(
			'struct' => array(
				array(
					'name' => 'faultCode',
					'int'  => intval($faultcode)
				),
				array(
					'name' => 'faultString',
					'string' => $faultstring
				)
			)
		);

		return $error;
	}

	/**
	*	Check for validity of supplied methodName
	*
	* @param string	Method Name
	*
	* @return	mixed
	*/
	function fetch_methodname($methodname)
	{
		// Valid methodnames
		if (!empty($this->supportedmethods["$methodname"]))
		{
			return $this->supportedmethods["$methodname"];
		}
		else
		{
			return false;
		}
	}

	/**
	* Public
	* Build XMPRPC Response Output
	*
	* The first parameter is the methodName then the single Param
	* Params can be sent as strings or arrays that define their type.
	*
	* @param	mixed		variable to be output
	* @param	boolean	fault condition
	*
	*/

	/** Example Parameter Usage

	Valid Types:

	int, i4
	boolean
	double
	string
	struct
	array
	datetime.iso8601
	base64 (must be sent in already encoded)

	require_once(DIR . '/includes/class_xmlrpc.php');
	$xmlrpc = new vB_XMLRPC_Server($vbulletin);

	$array = array(
		'array' => array(
			array('string' => 'foo'),
			array('boolean' => true),
			array('double' => 2.5),
			array('boolean' => 1),
			array('boolean' => 7),
		)
	);

	$xmlrpc->build_xml_response($array);
	$xmlrpc->send_xml_response();

	Fault Example:
	$struct = array(
		'struct' => array(
			array(
				'name' => 'faultCode',
				'int'  => 13
			),
			array(
				'name' => 'faultString',
				'string' => 'Unknown Error'
			)
		)
	);
	$xmlrpc->build_xml_response($struct, 1);
	$xmlrpc->send_xml_response();

	*/
	function build_xml_response($param, $fault = false)
	{
		if (!$fault)
		{
			$this->build_xml('methodResponse', array($param));
		}
		else
		{
			$this->build_xml('fault', array($param));
		}
	}

	/**
	* Public
	* Output the XML-RPC Response via HTTP
	*
	* @return	string	Headers + XML to send as a response
	*/
	function send_xml_response()
	{
		if ($this->outputtype != 'response')
		{
			trigger_error('vB_XMLRPC_Server::send_xml_response() Must call build_xml_response() before send_xml_response()', E_USER_ERROR);
		}

		//make sure that shutdown functions are called on script exit.
		$GLOBALS['vbulletin']->shutdown->shutdown();
		if (defined('NOSHUTDOWNFUNC'))
		{
			$this->registry->db->close();
		}

		$this->xml_object->send_content_type_header();
		$this->xml_object->send_content_length_header();
		echo $this->xml_object->fetch_xml_tag() . $this->xml_object->output();
	}

	/**
	* Build xmlrpc array if the params are valid
	*
	* @var array	List of valid params
	* @var array	List of submitted params
	*
	* @return boolean
	*/
	function build_xmlrpc_array(&$params, &$pinfo)
	{
		$this->xmlrpc_array = array();
		$errorcode = 0;

		if (count($params) != count($pinfo))
		{
			$errorcode = '-32602';
		}
		else
		{
			if (count($pinfo) == 1)
			{
				$paramarray = array($pinfo);
			}
			else
			{
				$paramarray =& $pinfo;
			}
			foreach ($paramarray AS $key => $value)
			{
				if (isset($value['value']) AND !empty($value['value']))
				{
					if (key($value['value']) != $params["$key"])
					{
						$errorcode = '-32602';
					}
					else
					{
						if (!($this->add_param($value['value'], $this->xmlrpc_array)))
						{
							return false;
						}
					}
				}
				else
				{
					$errorcode = '-32600';
				}
			}
		}

		if ($errorcode)
		{
			$xml_error_struct = $this->build_fault_struct($errorcode, $this->faultcodes["$errorcode"]);
			$this->build_xml_response($xml_error_struct, 1);
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Add node to xmlrpc array
	*
	* @param	array params array
	* @param	array	destination array
	*
	* @return
	*/
	function add_param($param, &$array)
	{
		$errorcode = 0;
		switch (key($param))
		{
			case 'int':
			case 'i4':
				$array[] = array(
					'int' => intval(current($param))
				);
				break;
			case 'boolean':
				$array[] = array(
					'boolean' => (current($param) == 1 OR current($param) == 'true') ? 1 : 0
				);
				break;
			case 'string':
				$array[] = array(
					'string' => strval(current($param))
				);
				 break;
			case 'double':
				$array[] = array(
					'double' => floatval(current($param))
				);
				break;
			case 'dateTime.iso8601':
				$array[] = array(
					'dateTime.iso8601' => strval(current($param))
				);
				break;
			case 'base64':
				$array[] = array(
					'base64' => strval(current($param))
				);
				break;
			case 'struct':
				if (is_array($param['struct']['member']) AND !empty($param['struct']['member']))
				{
					if (count($param['struct']['member']) == 1)
					{
						$memberarray = array($param['struct']['member']);
					}
					else
					{
						$memberarray =& $param['struct']['member'];
					}
					$lastkey = count($array);
					if (isset($array['name']))
					{
						$lastkey = count($array) -1 ;
					}
					else
					{
						$lastkey = count($array);
					}
					foreach ($memberarray AS $key => $value)
					{
						if (!empty($value['name']) AND is_array($value['value']) AND !empty($value['value']))
						{
							$array["$lastkey"]['struct'][] = array(
								'name' => $value['name']
							);
							$lastlastkey = count($array["$lastkey"]['struct']) - 1;
							if (!($this->add_param($value['value'], $array["$lastkey"]['struct']["$lastlastkey"])))
							{
								return false;
							}
						}
						else
						{
							$xml_error_struct = $this->build_fault_struct('-32602', $this->faultcodes['-32602']);
							$this->build_xml_response($xml_error_struct, 1);
							return false;
						}
					}
				}
				else
				{
					$xml_error_struct = $this->build_fault_struct('-32602', $this->faultcodes['-32602']);
					$this->build_xml_response($xml_error_struct, 1);
					return false;
				}
				break;
			case 'array':
				if (is_array($param['array']['data']['value']) AND !empty($param['array']['data']['value']))
				{
					if (count($param['array']['data']['value']) == 1)
					{
						$paramarray = array($param['array']['data']['value']);
					}
					else
					{
						$paramarray =& $param['array']['data']['value'];
					}
					if (isset($array['name']))
					{
						$lastkey = count($array) -1 ;
					}
					else
					{
						$lastkey = count($array);
					}

					foreach ($paramarray AS $key => $value)
					{
						if (!($this->add_param($value, $array["$lastkey"]['array'])))
						{
							return false;
						}
					}
				}
				else
				{
					$xml_error_struct = $this->build_fault_struct('-32602', $this->faultcodes['-32602']);
					$this->build_xml_response($xml_error_struct, 1);
					return false;
				}
				break;
			default:
				$xml_error_struct = $this->build_fault_struct('-32602', $this->faultcodes['-32602']);
				$this->build_xml_response($xml_error_struct, 1);
				return false;
		}

		return true;
	}

	/**
	* Verify parameters match
	*
	* @var	array (empty)
	*
	* @return boolean
	*/
	function verify_system_listMethods(&$pinfo)
	{
		$params = array();	// No Params
		if ($this->build_xmlrpc_array($params, $pinfo))
		{
			$methods = array();
			foreach ($this->supportedmethods AS $method => $function)
			{
				$methods['array'][] = $method;
			}
			$this->build_xml_response($methods);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verify parameters match
	*
	* @var	array (empty)
	*
	* @return boolean
	*/
	function verify_system_getCapabilities(&$pinfo)
	{
		$params = array();	// No Params
		if ($this->build_xmlrpc_array($params, $pinfo))
		{
			$struct = array(
				'struct' => array(
					array(
						'name' => 'faults_interop',
						'struct'  => array(
							array(
								'name'   => 'specUrl',
								'string' => 'http://xmlrpc-epi.sourceforge.net/specs/rfc.fault_codes.php',
							),
							array(
								'name' => 'specVersion',
								'int'  => 20010516,
							)
						)
					)
				)
			);
			$this->build_xml_response($struct);
			return true;
		}
		else
		{
			return false;
		}
	}
}

/**
* vBulletin XML-RPC Display Object
*
* This class generates the output of an XML-RPC Request
*/
class vB_XMLRPC_Client extends vB_XMLRPC_Abstract
{
	/**
	* Output type - response or call
	*
	*/
	var $outputtype = null;

	/**
	* Constructor
	*
	* @param	object	vBulletin Registry Object
	*/
	function vB_XMLRPC_Client(&$registry)
	{
		parent::vB_XMLRPC_Abstract($registry);
	}

	/**
	* Public
	* Build XMPRPC Call Output
	*
	* The first parameter is the methodName
	* then (unlimited number of) following parameters are the params
	* Params can be sent as strings or arrays that define their type.
	*
	* @param	string	Text of the phrase
	* @param	mixed	First variable to be inserted
	* @param	mixed	Nth variable to be inserted
	*
	*/

	/** Example Parameter Usage

	Valid Types:

	int, i4
	boolean
	double
	string
	struct
	array
	datetime.iso8601
	base64 (must be sent in already encoded)

	require_once(DIR . '/includes/class_xmlrpc.php');
	$xmlrpc = new vB_XMLRPC_Client($vbulletin);

	$struct = array(
		'struct' => array(
			array(
				'name' => 'one fish, two fish',
				'string' => 'Lorax'
			),
			array(
				'name' => 'blue fish, red fish',
				'double' => 3.14
			)
		)
	);

	$array = array(
		'array' => array(
			array('string' => 'foo'),
			array('boolean' => true),
			array('double' => 2.5),
			array('boolean' => 1),
			array('boolean' => true),
		)
	);

	$xmlrpc->build_xml_call('example.ex', $struct, $array);
	$xmlrpc->send_xml_call('http://www.example.com/xmlrpc.php');

	*/
	function build_xml_call()
	{
		$args = func_get_args();
		$numargs = sizeof($args);

		if ($numargs < 1 OR !is_string($args[0]))
		{
			trigger_error('vB_XMLRPC_Client::build_xml_call() Must specify a method (string)', E_USER_ERROR);
		}

		$this->build_xml('methodCall', $args);
	}

	/**
	* Public
	* Output the XML-RPC Call via HTTP POST
	*
	*/
	function send_xml_call($url)
	{
		if ($this->outputtype != 'call')
		{
			trigger_error('vB_XMLRPC_Client::send_xml_call() Must call build_xml_call() before send_xml_call()', E_USER_ERROR);
		}

		$vurl = new vB_vURL($this->registry);
		$vurl->set_option(VURL_URL, $url);
		$vurl->set_option(VURL_POST, 1);
		$vurl->set_option(VURL_HEADER, 1);
		$vurl->set_option(VURL_ENCODING, 'gzip');
		$vurl->set_option(VURL_HTTPHEADER, array(
			$this->xml_object->fetch_content_type_header(),
		));
		$vurl->set_option(VURL_MAXREDIRS, 1);
		$vurl->set_option(VURL_FOLLOWLOCATION, 1);
		$vurl->set_option(VURL_POSTFIELDS, $this->xml_object->fetch_xml_tag() . $this->xml_object->output());
		$vurl->set_option(VURL_RETURNTRANSFER, 1);
		$vurl->set_option(VURL_CLOSECONNECTION, 1);
		return $vurl->exec();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
