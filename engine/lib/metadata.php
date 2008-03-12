<?php
	/**
	 * Elgg metadata
	 * Functions to manage object metadata.
	 * 
	 * @package Elgg
	 * @subpackage Core
	 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU Public License version 2
	 * @author Marcus Povey <marcus@dushka.co.uk>
	 * @copyright Curverider Ltd 2008
	 * @link http://elgg.org/
	 */

	/**
	 * @class ElggMetadata
	 * @author Marcus Povey <marcus@dushka.co.uk>
	 */
	class ElggMetadata
	{
		/**
		 * This contains the site's main properties (id, etc)
		 * @var array
		 */
		private $attributes;
		
		/**
		 * Construct a new site object, optionally from a given id value or row.
		 *
		 * @param mixed $id
		 */
		function __construct($id = null) 
		{
			$this->attributes = array();
			
			if (!empty($id)) {
				
				if ($id instanceof stdClass)
					$metadata = $id; // Create from db row
				else
					$metadata = get_metadata($id);	
				
				if ($metadata) {
					$objarray = (array) $metadata;
					foreach($objarray as $key => $value) {
						$this->attributes[$key] = $value;
					}
				}
			}
		}
		
		function __get($name) {
			if (isset($this->attributes[$name])) {
				
				// Sanitise value if necessary
				if ($name=='value')
				{
					switch ($this->attributes['value_type'])
					{
						case 'integer' :  return (int)$this->attributes['value'];
						case 'tag' :
						case 'text' :
						case 'file' : return sanitise_string($this->attributes['value']);
							
						default : throw new InstallationException("Type {$this->attributes['value_type']} is not supported. This indicates an error in your installation, most likely caused by an incomplete upgrade.");
					}
				}
				
				return $this->attributes[$name];
			}
			return null;
		}
		
		function __set($name, $value) {
			$this->attributes[$name] = $value;
			return true;
		}	
		
		/**
		 * Return the owner of this metadata.
		 *
		 * @return mixed
		 */
		function getOwner() { return get_user($this->owner_id); }		
		
		function save()
		{
			if ($this->id > 0)
				return update_metadata($this->id, $this->name, $this->value, $this->value_type, $this->owner_id, $this->access_id);
			else
			{ 
				$this->id = create_metadata($this->entity_id, $this->entity_type, $this->name, $this->value, $this->value_type, $this->owner_id, $this->access_id);
				if (!$this->id) throw new IOException("Unable to save new ElggAnnotation");
				return $this->id;
			}
			
		}
		
		/**
		 * Delete a given site.
		 */
		function delete() { return delete_metadata($this->id); }
		
	}
	
	/**
	 * Convert a database row to a new ElggMetadata
	 *
	 * @param stdClass $row
	 * @return stdClass or ElggMetadata
	 */
	function row_to_elggmetadata($row) 
	{
		if (!($row instanceof stdClass))
			return $row;
			
		return new ElggMetadata($row);
	}
	
	/**
	 * Detect the value_type for a given value.
	 * Currently this is very crude.
	 * 
	 * TODO: Make better!
	 *
	 * @param mixed $value
	 * @param string $value_type If specified, overrides the detection.
	 * @return string
	 */
	function detect_metadata_valuetype($value, $value_type = "")
	{
		if ($value_type!="")
			return $value_type;
			
		// This is crude
		if (is_int($value)) return 'integer';
		
		return 'tag';
	}
		
	/**
	 * Create a new metadata object, or update an existing one.
	 *
	 * @param int $entity_id
	 * @param string $entity_type
	 * @param string $name
	 * @param string $value
	 * @param string $value_type
	 * @param int $owner_id
	 * @param int $access_id
	 */
	function create_metadata($entity_id, $entity_type, $name, $value, $value_type, $owner_id, $access_id = 0)
	{
		global $CONFIG;

		$entity_id = (int)$entity_id;
		$entity_type = sanitise_string(trim($entity_type));
		$name = sanitise_string(trim($name));
		$value = sanitise_string(trim($value));
		$value_type = detect_metadata_valuetype(sanitise_string(trim($value_type)));
		$time = time();
		
		$owner_id = (int)$owner_id;
		if ($owner_id==0) $owner_id = $_SESSION['id'];
		
		$access_id = (int)$access_id;

		$id = false;
		
		$existing = get_data_row("SELECT * from {$CONFIG->dbprefix}metadata WHERE entity_id = $entity_id and entity_type='$entity_type' and name='$name' limit 1");
		if ($existing) 
		{
			$id = $existing->id;
			$result = update_metadata($id,$name, $value, $value_type, $owner_id, $access_id);
			
			if (!$result) return false;
		}
		else
		{
			// Add the metastring
			$value = add_metastring($value);
			if (!$value) return false;
			
			// If ok then add it
			$id = insert_data("INSERT into {$CONFIG->dbprefix}metadata (entity_id, entity_type, name, value, value_type, owner_id, created, access_id) VALUES ($entity_id,'$entity_type','$name','$value','$value_type', $owner_id, $time, $access_id)");
		}
		
		return $id;
	}
	
	/**
	 * Update an item of metadata.
	 *
	 * @param int $id
	 * @param string $name
	 * @param string $value
	 * @param string $value_type
	 * @param int $owner_id
	 * @param int $access_id
	 */
	function update_metadata($id, $name, $value, $value_type, $owner_id, $access_id)
	{
		global $CONFIG;

		$id = (int)$id;
		$name = sanitise_string(trim($name));
		$value = sanitise_string(trim($value));
		$value_type = detect_metadata_valuetype(sanitise_string(trim($value_type)));
		
		$owner_id = (int)$owner_id;
		if ($owner_id==0) $owner_id = $_SESSION['id'];
		
		$access_id = (int)$access_id;
		
		$access = get_access_list();
		
		
		// Add the metastring
		$value = add_metastring($value);
		if (!$value) return false;
		
		// If ok then add it
		return update_data("UPDATE {$CONFIG->dbprefix}metadata set value='$value', value_type='$value_type', access_id=$access_id, owner_id=$owner_id where id=$id and name='$name' and (access_id in {$access} or (access_id = 0 and owner_id = {$_SESSION['id']}))");
	}

	/**
	 * Get a specific item of metadata.
	 * 
	 * @param $id int The item of metadata being retrieved.
	 */
	function get_metadata($id)
	{
		global $CONFIG;

		$id = (int)$id;
		$access = get_access_list();
				
		return row_to_elggmetadata(get_data_row("SELECT * from {$CONFIG->dbprefix}metadata where id=$id and (access_id in {$access} or (access_id = 0 and owner_id = {$_SESSION['id']}))"));
	}

	/**
	 * Get a list of metadatas for a given object/user/metadata type.
	 *
	 * @param int $entity_id
	 * @param string $entity_type
	 * @param string $entity_subtype
	 * @param mixed $name Either a string or an array of terms.
	 * @param mixed $value Either a string or an array of terms.
	 * @param int $owner_id
	 * @param string $order_by
	 * @param int $limit
	 * @param int $offset
	 * @return array of ElggMetadata
	 */
	function get_metadatas($entity_id = 0, $entity_type = "", $entity_subtype = "", $name = "", $value = "", $value_type = "", $owner_id = 0, $order_by = "created desc", $limit = 10, $offset = 0)
	{
		global $CONFIG;
		
		$entity_id = (int)$entity_id;
		$entity_type = sanitise_string(trim($entity_type));
		$entity_subtype = sanitise_string($entity_subtype);
		
		if (!is_array($name))
			$name = sanitise_string($name);
		if (!is_array($value))
			$value = get_metastring_id($value);
			
		if ((is_array($name)) && (is_array($value)) && (count($name)!=count($value)))
			throw new InvalidParameterException("Name and value arrays not equal.");
			
		$value_type = sanitise_string(trim($value_type));
		$owner_id = (int)$owner_id;
		$order_by = sanitise_string($order_by);
		
		$limit = (int)$limit;
		$offset = (int)$offset;
		
		$join = "";
		
		// Construct query
		$where = array();
		
		if ($entity_id != 0)
			$where[] = "o.entity_id=$entity_id";
			
		if ($entity_type != "")
			$where[] = "o.entity_type='$entity_type'";
		
		if ($owner_id != 0)
			$where[] = "o.owner_id=$owner_id";
			
		if (is_array($name)) {
			foreach ($name as $n)
				$where[]= "o.name='$n'";
		} else if ($name != "")
			$where[] = "o.name='$name'";

		if (is_array($value)) {
			foreach ($value as $v)
				$where[]= "o.value='$v'";
		} else if ($value != "")
			$where[] = "o.value='$value'";
			
		if ($value_type != "")
			$where[] = "o.value_type='$value_type'";
			
		if ($entity_subtype != "")
			$where[] = "s.id=" . get_entity_subtype($entity_id, $entity_type);
			
		// add access controls
		$access = get_access_list();
		$where[] = "(o.access_id in {$access} or (o.access_id = 0 and o.owner_id = {$_SESSION['id']}))";
		
		if ($entity_subtype!="")
			$where[] = "";
			
		// construct query.
		$query = "SELECT o.* from {$CONFIG->dbprefix}metadata o LEFT JOIN {$CONFIG->dbprefix}entity_subtypes s on o.entity_id=s.entity_id and o.entity_type=s.entity_type where ";
		for ($n = 0; $n < count($where); $n++)
		{
			if ($n > 0) $query .= " and ";
			$query .= $where[$n];
		}
		$query .= " order by $order_by limit $offset,$limit";
		
		return get_data($query, "row_to_elggmetadata");
	}

	/**
	 * Similar to get_metadatas, but instead returns the objects associated with a given meta search.
	 * 
	 * @param int $entity_id
	 * @param string $entity_type
	 * @param string $entity_subtype
	 * @param mixed $name Either a string or an array of terms.
	 * @param mixed $value Either a string or an array of terms.
	 * @param int $owner_id
	 * @param string $order_by
	 * @param int $limit
	 * @param int $offset
	 * @return mixed Array of objects or false.
	 */
	function get_objects_from_metadatas($entity_id = 0, $entity_type = "", $entity_subtype = "", $name = "", $value = "", $value_type = "", $owner_id = 0, $order_by = "created desc", $limit = 10, $offset = 0)
	{
		$results = get_metadatas($entity_id, $entity_type, $entity_subtype, $name, $value, $value_type, $owner_id, $order_by, $limit, $offset);
		$objects = false;
		
		if ($results)
		{
			$objects = array();
			
			foreach ($results as $r)
			{
				
				switch ($r->entity_type)
				{
					case 'object' : $objects[] = new ElggObject((int)$r->entity_id); break;
					case 'user' : $objects[] = new ElggUser((int)$r->entity_id); break;
					case 'collection' : $objects[] = new ElggCollection((int)$r->entity_id); break;
					case 'site' : $objects[] = new ElggSite((int)$r->entity_id); break;
					default: default : throw new InstallationException("Type {$r->entity_type} is not supported. This indicates an error in your installation, most likely caused by an incomplete upgrade.");
				}
			}
		}
		
		return $objects;
	}
	
	/**
	 * Delete an item of metadata, where the current user has access.
	 * 
	 * @param $id int The item of metadata to delete.
	 */
	function delete_metadata($id)
	{
		global $CONFIG;

		$id = (int)$id;
		$access = get_access_list();
				
		return delete_data("DELETE from {$CONFIG->dbprefix}metadata where id=$id and (access_id in {$access} or (access_id = 0 and owner_id = {$_SESSION['id']}))");
		
	}
?>