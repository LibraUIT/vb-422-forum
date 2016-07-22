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
//We need this function locally
function compareWidgetName($a, $b)
{
	global $vbphrase;
	if ($a['title'] == $b['title']) {
		return 0;
	}
	return ($a['title'] < $b['title']) ? -1 : 1;
}

/**
 * CMS Types Handler
 * Provides additional type conversion for widgets.
 * @see vB_Types
 *
 * @version $Revision: 76725 $
 * @since $Date: 2013-08-04 15:38:42 -0700 (Sun, 04 Aug 2013) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Types extends vB_Types
{
	/*Properties====================================================================*/

	/**
	 * A reference to the singleton instance
	 *
	 * @var vBCms_Types
	 */
	protected static $instance;

	/**
	 * The key to use to store the type cache.
	 *
	 * @var string
	 */
	protected $cache_key = 'vbcms_types.types';

	/**
	 * Events that expire the type cache.
	 *
	 * @var array string
	 */
	protected $cache_events = array(
				'vb_types.type_updated',
				'vb_types.package_updated',
				'vb_types.contenttype_updated',
				'vbcms_types.widgettype_updated'
	);



	/*Construction==================================================================*/

	/**
	 * Returns singleton instance of self.
	 * @todo This can be inherited once late static binding is available.  For now
	 * it has to be redefined in the child classes
	 *
	 * @return vBCms_Types						- Reference to singleton instance of the type handler
	 */
	public static function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}



	/*Initialization================================================================*/

	/**
	 * Loads the type info from the type info cache into distinct type properties.
	 *
	 * @param array mixed $type_info			- The type info cache data
	 */
	protected function loadTypeInfo($type_info)
	{
		parent::loadTypeInfo($type_info);

		$this->loadWidgetTypes($type_info);
	}


	/**
	 * Builds the type info for the applciation cache.
	 * Includes the widget types
	 *
	 * @TODO Use type collection
	 *
	 * @return array mixed						- Assoc array of type info
	 */
	protected function getTypeInfo()
	{
		// Get package and contenttypes
		$result = vB::$db->query("
					(SELECT 'package' AS classtype, package.packageid AS typeid, package.packageid AS packageid, package.productid AS productid, product.active AS enabled, package.class AS class
					 FROM " . TABLE_PREFIX . "package AS package
					 INNER JOIN " . TABLE_PREFIX . "product AS product
					  ON product.productid = package.productid
					  OR package.productid = 'vbulletin')
					UNION
					(SELECT 'contenttype' AS classtype, contenttypeid AS typeid, contenttype.packageid AS packageid, 1, 1, contenttype.class AS class
					 FROM " . TABLE_PREFIX . "contenttype AS contenttype)
					UNION
                	(SELECT 'widgettype' AS classtype, widgettypeid AS typeid, widgettype.packageid AS packageid, 1, 1, widgettype.class AS class
                 	FROM " . TABLE_PREFIX . "cms_widgettype AS widgettype)
		");

		$types = array();
		while ($type = vB::$db->fetch_array($result))
		{
			$types[] = $type;
		}

		return $types;
	}


	/*Widgets=======================================================================*/

	/**
	 * Loads widgettype info from the type info cache.
	 *
	 * @param array mixed $type_info			- The type info cache
	 * @throws vB_Exception_Critical			- Thrown if no widgettypes were found
	 */
	protected function loadWidgetTypes($type_info)
	{
		global $vbphrase;
		foreach ($type_info AS $type)
		{
			if ($type['classtype'] == 'widgettype')
			{
				if (isset($this->package_ids[$type['packageid']]))
				{
					$key = $this->getTypeKey($this->package_ids[$type['packageid']]['class'], $type['class']);
					$this->widgettypes[$key] = array('class' => $type['class'], 'id' => $type['typeid']);
					$this->widgettypes[$key]['package'] =& $this->package_ids[$type['packageid']];
					$this->widgettype_ids[$type['typeid']] =& $this->widgettypes[$key];
					$this->widgettypes[$key]['title'] = $vbphrase['widgettype_' . strtolower($key)];
				}
			}
		}

		if (!sizeof($this->widgettypes))
		{
			throw (new vB_Exception_Critical('No widgettypes found'));
		}
		uasort($this->widgettypes, 'compareWidgetName');

	}



	/**
	 * Gets a widgettype id from a type key or array(package, class).
	 * Note: This will also return the numeric id if one is given, allowing the
	 * function to be used for normalisation and validation.
	 *
	 * If the widgettype is given as an array, it must be in the form
	 * 	array('package' => package class string, 'class' => widgettype class string)
	 *
	 * @param mixed $widgettype					- Key, array(package, class) or numeric id of the widgettype
	 * @return int | false
	 */
	public function getWidgetTypeID($widgettype)
	{
		if (is_numeric($widgettype))
		{
			return (isset($this->widgettype_ids[$widgettype]) ? $widgettype : false);
		}
		else if (is_string($widgettype) OR (is_array($widgettype) AND isset($widgettype['package']) AND isset($widgettype['class'])))
		{
			if (is_array($widgettype))
			{
				$widgettype = $this->getTypeKey($widgettype['package'], $widgettype['class']);
			}

			if (!isset($this->widgettypes[$widgettype]))
			{
				return false;
			}

			return $this->widgettypes[$widgettype]['id'];
		}

		return false;
	}


	/**
	 * Checks if a widgettype id is valid and throws an exception if it isn't.
	 *
	 * @param mixed $widgettype					- Key, array(package, class) or numeric id of the widgettype
	 * @param vB_Exception $e					- An alternative exception to throw
	 * @throws mixed							- Thrown if the given widgettype is not valid
	 */
	public function assertWidgetType($widgettype, vB_Exception $e = null)
	{
		if (!($id = $this->getWidgetTypeID($widgettype)))
		{
			throw ($e ? $e : new vB_Exception_Warning('Invalid widgettype \'' . htmlspecialchars_uni(print_r($widgettype, 1)) . '\''));
		}

		return $id;
	}


	/**
	 * Gets the package class string identifier for a widgettype
	 *
	 * @param mixed $widgettype					- Key, array(package, class) or numeric id of the widgettype
	 * @return string							- The class string of the package
	 */
	public function getWidgetTypePackage($widgettype)
	{
		if (!($id = $this->getWidgetTypeID($widgettype)))
		{
			throw (new vB_Exception_Warning('Trying to get package class from invalid widgettype \'' . htmlspecialchars_uni($widgettype) . '\''));
		}

		$this->assertPackage($this->widgettype_ids[$id]['package']['id']);

		return $this->widgettype_ids[$id]['package']['class'];
	}


	/**
	 * Gets the package id for a widgettype
	 *
	 * @param mixed $widgettype					- Key, array(package, class) or numeric id of the widgettype
	 * @return int								- The integer id of the package that the widgettype belongs to
	 */
	public function getWidgetTypePackageID($widgettype)
	{
		$package = $this->getWidgetTypePackage($widgettype);

		$this->assertPackage($this->packages[$package]['id']);

		return $this->packages[$package]['id'];
	}


	/**
	 * Gets the class string identifier for a widgettype.
	 *
	 * @param mixed $widgettype					- Key, array(package, class) or numeric id of the widgettype
	 * @return string							- The class string identifier of the given widgettype
	 */
	public function getWidgetTypeClass($widgettype)
	{
		if (!($id = $this->getWidgetTypeID($widgettype)))
		{
			throw (new vB_Exception_Warning('Trying to get widgettype class from invalid widgettype \'' . htmlspecialchars_uni($widgettype) . '\''));
		}

		return $this->widgettype_ids[$id]['class'];
	}


	/**
	 * Gets the user friendly title of a widgettype.
	 * Note: The title is not stored as part of the widgettype and is instead a
	 * phrase that is evaluated from the widgettype's package and class.
	 *
	 * @param mixed $widgettype
	 */
	public function getWidgetTypeTitle($widgettype)
	{
		if (!($id = $this->getWidgetTypeID($widgettype)))
		{
			throw (new vB_Exception_Warning('Trying to get widgettype title from invalid widgettype \'' . htmlspecialchars_uni($widgettype) . '\''));
		}

		return new vB_Phrase('widgettypes', 'widgettype_' . strtolower($this->getWidgetTypePackage($widgettype) . '_' . $this->getWidgetTypeClass($widgettype)));
	}


	/**
	 * Checks of a widgettype is enabled.
	 * A widgettype is disabled if it's package is disabled.
	 *
	 * @param mixed $widgettype				- Key, array(package, class) or numeric id of the widgettype
	 * @return bool
	 */
	public function widgetTypeEnabled($widgettype)
	{
		if (!$id = $this->getWidgetTypeID($widgettype))
		{
			throw (new vB_Exception_Warning('Checking if a widgettype\'s package is enabled for an invalid widgettype \'' . $widgettype . '\''));
		}

		return $this->widgettype_ids[$id]['package']['enabled'];
	}


	/**
	 * Returns an array of widget type id => title.
	 */
	public function enumerateWidgetTypes()
	{
		$enum = array();

		foreach ($this->widgettypes AS $id => $widgettype)
		{
			$enum[$widgettype['id']] = $this->getWidgetTypeTitle($id);

		}

		return $enum;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # SVN: $Revision: 76725 $
|| ####################################################################
\*======================================================================*/