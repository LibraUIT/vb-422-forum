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

class SV_Mapping 
{
	var $db; 
	var $dateline; 

	// Flags
	var $loaded = false;
	var $mappings = false;
	var $processed = false;

	// Stylevar Data
	var $master = array();
	var $custom = array();
	var $result = array();

	// Mapping Data
	var $mapper = array();
	var $preset = array();
	var $delete = array();

	// Product Data
	var $product = array();
	var $productlist = array();
	var $masterstyleid;
	var $styles = array();

	function sv_mapping($registry, $masterstyleid = -1)
	{
		// Set initial stuff
		$this->dateline = TIMENOW;
		$this->db =& $registry->db;
		$this->productlist =& $registry->products;
		$this->masterstyleid = $masterstyleid;
		
		$this->styles[] = $this->masterstyleid;
		// Get styles that belong to this master considering that style.type might not exist yet
		$styles = $this->db->query_read("
			SELECT styleid
			FROM " . TABLE_PREFIX . "style
			WHERE INSTR(CONCAT(',', parentlist,','), '{$this->masterstyleid}')
		");
		while ($style = $this->db->fetch_array($styles))
		{
			$this->styles[] = $style['styleid'];
		}
	}
	
	public function add_sv_mapping($mapfrom, $mapto, $product = 'vbulletin', $delete = false)
	{
		// Add a mapping
		$this->mappings = true;
		$this->mapper[$mapfrom][] = $mapto;

		list($source_var, $source_type) = explode('.', $mapfrom);
		list($destination_var, $destination_type) = explode('.', $mapto);

		if ($product)
		{	// In reality, this should always be set
			$this->product[$destination_var] = $product;
		}

		if ($delete)
		{
			$this->delete[$source_var] = true;
		}
	}

	public function add_sv_preset($mapto, $value, $forced = true, $verify = '')
	{
		// Add a preset
		$this->mappings = true;
		$this->preset[$mapto] = 
			array(
				'value' => $value,
				'forced' => $forced,
				'verify' => $verify,
			);
	}
	
	public function remove_stylevar($stylevar)
	{
		// Mark stylevar to be deleted
		list($source_var, $source_type) = explode('.', $stylevar);

		$this->delete[$source_var] = true;
	}
	
	function sv_load()
	{
		// Get Stylevar Data
		$svdata = $this->db->query_read("
			SELECT stylevar.*
			FROM " . TABLE_PREFIX . "stylevar AS stylevar
			INNER JOIN " . TABLE_PREFIX . "stylevardfn AS stylevardfn ON (stylevar.stylevarid = stylevardfn.stylevarid)
			WHERE
				stylevar.styleid IN (" . implode(',', $this->styles) . ")
					AND
				stylevardfn.styleid = {$this->masterstyleid}
		");

		// Build master & custom lists
		while ($sv = $this->db->fetch_array($svdata))
		{
			$this->loaded = true;
			$style = $sv['styleid'];
			$stylevar = $sv['stylevarid'];
			$data = @unserialize($sv['value']);
	
			// Store valid data only
			if (is_array($data))
			{
				if ($style == $this->masterstyleid)
				{
					$this->master[$stylevar] = $data;
				}
				else
				{
					$this->custom[$stylevar][$style] = $data;
				}
			}
		}
		
		return $this->loaded;
	}

	function process()
	{
		// No data !
		if (!$this->loaded)
		{
			return false;
		}
		
		// No mappings ..
		if (!$this->mappings)
		{
			return !empty($this->delete);  // We may still have deletes
		}
		
		/* For a preset to work, the destination stylevar must exist in the mapping results. 
		   To help this happen we map each preset to itself, after all the main mappings.
		   This is still not a 100% guarantee that the preset will happen, but it helps. */
		foreach($this->preset AS $source => $data)
		{
			$this->add_sv_mapping($source, $source);
		}
		
		// Process mappings
		foreach($this->mapper AS $source => $destinations)
		{
			// Multiple destinations per source
			foreach($destinations AS $destination)
			{
				// Get stylevar names and value types
				list($source_var, $source_type) = explode('.', $source);
				list($destination_var, $destination_type) = explode('.', $destination);

				// Work out if merging whole stylevar and mapping types
				$merge = (!$source_type AND !$destination_type ? true : false);
				$source_type = ($source_type ? $source_type : $destination_type);
				$destination_type = ($destination_type ? $destination_type : $source_type);

				// Process the stylevars if they exist
				if ($this->custom[$source_var])
				{
					foreach($this->custom[$source_var] AS $style => $source_data)
					{
						/* If we have previously processed this stylevar, load it.
						   If not, load any custom version of the destination.
						   If we still have nothing, load the master values. If we
						   still have nothing, just start a new blank array */
						   
						$destination_data = $this->result[$destination_var][$style];

						if (!$merge AND !$destination_data)
						{
							$destination_data = $this->custom[$destination_var][$style];
						}
			
						if (!$destination_data)
						{
							$destination_data = $this->master[$destination_var];
						}
			
						if (!$destination_data)
						{
							$destination_data = array();
						}

						if ($merge)
						{
							// Copy all source data into the destination
							foreach($source_data AS $source_type => $source_value)
							{
								$destination_data[$source_type] = $source_value;
							}
						}
						else
						{
							// Copy just the source datatype into the destination
							$destination_data[$destination_type] = $source_data[$source_type];

							// Remove the old datatype if its not the same as the new type
							if ($source_type != $destination_type)
							{
								unset($destination_data[$source_type]);
							}
						}

						// All done, save it
						$this->processed = true;
						$this->result[$destination_var][$style] = $destination_data;
					}
				}
			}
		}
		
		foreach($this->preset AS $source => $value_data)
		{
			list($source_var, $source_type) = explode('.', $source);

			/* Load the existing results if they already exist.
			   If not, load all the customised data. If neither
			   of these exist we cannot do anything. */

			$source_data = $this->result[$source_var];
							
			if (!$source_data)
			{
				$source_data = $this->custom[$source_var];
			}

			if ($source_data)
			{
				// Add the preset to each customised style
				foreach($source_data AS $style => $destination_data)
				{
					$exists = $destination_data[$source_type] ? true : false;
					$verify = $value_data['verify'] ? 'verify_' . $value_data['verify'] : false;

					if ($exists AND $verify)
					{
						$exists = $this->$verify($destination_data[$source_type]);
					}

					if(!$exists OR $value_data['forced'])
					{
						$this->processed = true;
						$destination_data[$source_type] = $value_data['value'];
						$this->result[$source_var][$style] = $destination_data;
					}
				}
			}
		}

		return ($this->processed OR !empty($this->delete));
	}
	
	// Debug Function //
	function display_results($stop = false)
	{
		echo "<br />Results ; <br /><br />";
		foreach($this->result AS $stylevar => $styledata)
		{
			$product = $this->product[$stylevar];
			echo "Data for : $stylevar ($product) <br />";
			foreach($styledata AS $style => $data)
			{
				$svdata = @serialize($data);
				echo "Style $style : $svdata <br />";
			}
		}

		echo "<br />Deletes ; <br /><br />";
		foreach($this->delete AS $stylevar => $dummy)
		{
			echo "Delete : $stylevar <br />";
			$this->delete_svar($stylevar);
		}

		if ($stop)
		{
			print_r($this);
			exit;
		}
	}
	
	function process_results()
	{
		// Process each resulting stylevar for each style
		foreach($this->result AS $stylevar => $styledata)
		{
			foreach($styledata AS $style => $data)
			{
				// Only add if its for an installed product
				if ($this->productlist[$this->product[$stylevar]])
				{
					$this->add_svar($stylevar, $style, $data, $this->dateline);
				}
			}
		}

		// Process all the stylevar deletes
		foreach($this->delete AS $stylevar => $dummy)
		{
			$this->delete_svar($stylevar);
		}
	}

	function add_svar($stylevar, $style, $data, $time = TIMENOW, $user = 'SV-Mapper')
	{
		// If valid data, add/update it
		if ($svdata = @serialize($data))
		{
			$user = $this->db->escape_string($user);
			$svdata = $this->db->escape_string($svdata);
			$stylevar = $this->db->escape_string($stylevar);
			
			$this->db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "stylevar
				(stylevarid, styleid, value, dateline, username)
				VALUES ('$stylevar', $style, '$svdata', $time, '$user')
			");
		}
	}
	
	function delete_svar($stylevar)
	{
		/* Delete the stylevar if its set to be deleted 
		   but only if its the forum, blogs or cms, we 
		   dont want to zap any modification stylevars */

		if ($this->delete[$stylevar])
		{
			// Remove style data
			$this->db->query_write("
				DELETE stylevar, stylevardfn
				FROM " . TABLE_PREFIX . "stylevar AS stylevar
				INNER JOIN " . TABLE_PREFIX . "stylevardfn AS stylevardfn ON (stylevar.stylevarid = stylevardfn.stylevarid)
				WHERE
					stylevardfn.stylevarid = '" . $this->db->escape_string($stylevar) . "' 
						AND
					stylevardfn.product IN ('vbblog', 'vbcms', 'vbulletin')
						AND
					stylevar.styleid IN (" . implode(',', $this->styles) . ")
			");

			$name = "stylevar_{$stylevar}_name" . ($this->masterstyleid == -2) ? '_mobile' : '';
			$desc = "stylevar_{$stylevar}_description" . ($this->masterstyleid == -2) ? '_mobile' : '';
			// Remove phrase data
			$this->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "phrase
				WHERE
					fieldname = 'style'
						AND
					product IN ('vbblog', 'vbcms', 'vbulletin')
						AND
					varname IN ('" . $this->db->escape_string($name) , "','" . $this->db->escape_string($desc) . "')
			");
		}
	}

	function verify_units($unit)
	{
		$units = array('%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
		return in_array($unit, $units);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 55763 $
|| ####################################################################
\*======================================================================*/
