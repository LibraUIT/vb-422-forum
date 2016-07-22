<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * Widget class.
 *
 * @TODO: Move these notes to the config handling
 * If values are not allowed to be inherited down the config chain then the config
 * UI must enforce this.  For instance, if a cvar is configurable both at the
 * instance level and the node level, and the widget also has a cvar that determines
 * that inheritance is not allowed, then the widget config UI must display a required
 * config option at the node level and enfoce that a value is used.
 *
 * Similarly, if the config allows you to specify if a value is configurable at the
 * layout or node level then this must also be enforced by the UI, displaying or
 * hiding the config controls for the affected cvars.
 *
 * If 'can inherit' or 'can config' is changed for a specific cvar then it is the
 * responsibility of the widget config handler to clear or update values where
 * necessary; and the responsibility of the widget config UI to hide or display
 * configurable options.
 *
 * The configurability and inheritability of a specific cvar can be merged into a
 * single state with the following per cvar options:
 * 	Cannot Config (Always inherit)
 *  Optional (Inherit if no node level value given)
 *  Required (Value is always saved and overrides the parent, even if the value is empty)
 *
 * Whether an empty value will be saved is entirely up to the widget config UI based on
 * whether the cvar is required or not.  This is seperate from the configurability state.
 *
 * If a widget has the option for inheritance that is changed from true to false, then
 * the widget config should be responsible for clearing any config values.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vBCms_Item_Widget extends vB_Item
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this content.
	 * Note: The class should always be resolvable from a contenttypeid using the
	 * contenttype table.
	 *
	 * @var string
	 */
	protected $class;

	/**
	 * Nodeid that the widget is on.
	 * This is optional and depends on the context.  Whether a nodeid is available
	 * will determine the config that is loaded and whether the node is both
	 * editable and configurable.
	 *
	 * @var int
	 */
	protected $nodeid;

	/**
	 * The loaded config for the widget.
	 * This is the resolved config after merging the instance and node configs.
	 * The node config is only loaded if a nodeid is set for the widget.
	 *
	 * @var array cvar => value
	 */
	protected $config = array();

	/**
	 * The class name of the most appropriate DM for managing the item's data.
	 *
	 * @var string
	 */
	protected $dm_class = 'vBCms_DM_Widget';

	/**
	 * Info flags required to load all of the properties needed to set the existing
	 * fields in the DM for this item.
	 * Load basic info and config.
	 *
	 * @var array int
	 */
	protected $dm_load_flags = 0x3;

	protected $query_hook = 'vbcms_widget_querydata';

	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 */
	const INFO_CONFIG = 0x2;

	/**
	 * The total flags for all info.
	 * This would be a constant if we had late static binding.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 0x3;

	/**
	 * Query types.
	 */
	const QUERY_CONFIG = 2;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => 0x1,
		self::QUERY_CONFIG => 0x2
	);

	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid model properties.
	 * This is used to check if a class property can be set as a property and allows
	 * automagic setting of properties from db info results.
	 * @see Load()
	 * @see setInfo()
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_BASIC==================*/
		'title',		'description',
		'varname',		'widgettypeid'
	);

	/**
	 * Extra model properties.
	 * These are merged with $item_properties on construction, providing a simple
	 * way for children to extend the model properties without duplicating or
	 * destroying the common properties.
	 *
	 * @var array
	 */
	protected $widget_properties = array();

	/*INFO_BASIC==================*/

	/**
	 * A friendly title for the widget.
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * A friendly description for the widget.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * A user defined string identifier for the widget.
	 *
	 * @var string
	 */
	protected $varname;

	/**
	 * Id of the widgettype.
	 *
	 * @var int
	 */
	protected $widgettypeid;



	/*Initialisation================================================================*/

	/**
	 * Constructs the content item.
	 * The id passed will usually be the primary key of the model data in the
	 * database but as this is model specific it can be interpreted in other ways.
	 *
	 * @param mixed $itemid					- The id of the item
	 * @param int $load_flags				- Any required info prenotification
	 */
	public function __construct($itemid = false, $load_flags = false)
	{
		if (!$this->package OR !$this->class)
		{
			throw (new vBCms_Exception_Widget('No package or widgettype class defined for widget item \'' . get_class($this) . '\''));
		}

		// Ensure the widgettype is valid
		vBCms_Types::instance()->assertWidgetType(array('package' => $this->package, 'class' => $this->class));

		$this->item_properties = array_merge($this->item_properties, $this->widget_properties);

		parent::__construct($itemid, $load_flags);
	}


	/**
	 * Creates a widget item.
	 *
	 * @param string  $packageclass				- The class identifier for the package
	 * @param string  $itemclass				- The class identifier for the widget
	 * @param int 	  $itemid					- The primaryid of the widget
	 * @param boolean $load_flags				- Added for PHP 5.4 strict standards compliance
	 */
	public static function create($package, $class, $itemid = false, $load_flags = false)
	{
		$class = $package . '_Item_Widget_' . $class;
		return new $class($itemid);
	}


	/**
	 * Sets the nodeid that the widget is placed on.
	 * If the nodeid changes then the config will be reset.
	 *
	 * @param int $nodeid
	 */
	public function setNodeId($nodeid)
	{
		if ($nodeid != $this->nodeid)
		{
			$this->defaultConfig();
		}

		$this->nodeid = $nodeid;
	}



	/*LoadInfo======================================================================*/

	/**
	 * Applies the result of the load query.
	 *
	 * @param resource $result					- The db result resource
	 * @param int $load_query					- The query that the result is from
	 */
	protected function applyLoad($result, $load_query)
	{
		if (self::QUERY_CONFIG == $load_query)
		{
			$node_config = $instance_config = array();
			while ($cvar = vB::$db->fetch_array($result))
			{
				if ($cvar['instance'])
				{
					$instance_config[$cvar['name']] = ($cvar['serialized'] ? unserialize($cvar['value']) : $cvar['value']);
				}
				else
				{
					$node_config[$cvar['name']] = ($cvar['serialized'] ? unserialize($cvar['value']) : $cvar['value']);
				}
			}

			// merge widget instance config with node config
			$config = array_merge($instance_config, $node_config);
			$this->setConfig($config, true);

			// mark config as loaded
			$this->loaded_info |= self::INFO_CONFIG;

			return true;
		}

		return parent::applyLoad($result, $load_query);
	}


	/**
	 * Fetches the SQL for loading.
	 *
	 * @param int $required_query				- The required query
	 * @param bool $force_rebuild				- Whether to rebuild the string
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query = self::QUERY_BASIC, $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			return "SELECT widget.title, widget.description, widget.varname, widget.widgettypeid
					$hook_query_fields
					FROM " . TABLE_PREFIX . "cms_widget AS widget
					$hook_query_joins
					WHERE widget.widgetid = " . intval($this->itemid) . "
					$hook_query_where";
		}

		if (self::QUERY_CONFIG == $required_query)
		{
			return "SELECT config.name, config.value, (nodeid = 0) instance, config.serialized
					$hook_query_fields
					FROM " . TABLE_PREFIX . "cms_widgetconfig AS config
					$hook_query_joins
					WHERE widgetid = " . intval($this->itemid) . "
					AND nodeid = 0 " .
					($this->nodeid ?
						"OR nodeid = " . intval($this->nodeid) : '') . "
					$hook_query_where";
		}

		parent::getLoadQuery($required_query, $force_rebuild);
	}



	/*DataManager===================================================================*/

	/**
	 * Loads a corresponding DM with the fields it needs to express the current
	 * values.
	 *
	 * @param vBCms_DM_Widget $dm							- The DM to give the existing values to.
	 */
	public function loadDM(vB_DM $dm)
	{
		$this->Load($this->dm_load_flags);

		$dm->setExisting($this->item_properties);
		$dm->set('config', $this->config);
	}



	/*Config========================================================================*/

	/**
	 * Allows the config to be set.
	 *
	 * @param array mixed $config				- Assoc array of cvar => value
	 * @param bool $suppress_errors				- If true, unrecognized cvars won't error
	 */
	public function setConfig($config, $suppress_errors = false)
	{
		if (!is_array($config))
		{
			throw (new vBCms_Exception_Widget('Config passed to widget \'' . htmlspecialchars_uni($this->title) . '\' is not an array'));
		}

		foreach (array_keys($this->config) AS $cvar)
		{
			$this->config[$cvar] = null;
		}

		foreach ($config AS $cvar => $value)
		{
			$this->setConfigValue($cvar, $value, $suppress_errors);
		}

		// Mark config as loaded
		$this->loaded_info |= self::INFO_CONFIG;
	}


	/**
	 * Sets an individual cvar.
	 * Child classes should perform any transformations or validations here.
	 *
	 * @param string $cvar						- The name of the cvar to set
	 * @param mixed $value						- The value to set
	 * @param bool $suppress_errors				- If true, unrecognized cvars won't error
	 */
	public function setConfigValue($cvar, $value, $suppress_errors = false)
	{
		if (key_exists($cvar, $this->config))
		{
			$this->config[$cvar] = $value;
		}
		else if (!$suppress_errors)
		{
			throw (new vBCms_Exception_Widget('Trying to set an unknown config var \'' . htmlspecialchars_uni($cvar) . '\' on a \'' . get_class($this) . '\' widget'));
		}
	}


	/**
	 * Gets the widget config.
	 *
	 * @return array mixed
	 */
	public function getConfig()
	{
		$this->Load(self::INFO_CONFIG);

		return $this->config;
	}


	/**
	 * Resets the config.
	 */
	public function defaultConfig()
	{}

	/*Accessors=====================================================================*/

	/**
	 * Returns the widget class string identifier.
	 * This is used to resolve related classes.
	 *
	 * @return string
	 */
	public function getClass()
	{
		return $this->class;
	}


	/**
	 * Returns the package class string identifier.
	 * This is used to resolve related classes.
	 *
	 * @return string
	 */
	public function getPackage()
	{
		return $this->package;
	}


	/**
	 * Return the widget title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		$this->Load();

		return $this->title;
	}


	/**
	 * Return the widgettype title.
	 */
	public function getTypeTitle()
	{
		return vBCms_Types::instance()->getWidgetTypeTitle(array('package' => $this->getPackage(), 'class' => $this->getClass()));
	}


	/**
	 * Return the widget description
	 *
	 * @return string
	 */
	public function getDescription()
	{
		$this->Load();

		return $this->description;
	}


	/**
	 * Returns the user defined varname of the widget.
	 *
	 * @return string
	 */
	public function getVarname()
	{
		$this->Load();

		return $this->varname;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/