<?php
include_once "includes/db.class.php";
include_once "includes/logger.class.php";

class DBObject
{
	const ERROR_RECORD_EXISTS = "errorRecordExists";

	public $id;
	
	public $errorMessage;

	protected $db;
	protected $fields;
	protected $table;
	protected $logger;
	
	protected $isNew;
	protected $checkIfUnique;

	function __construct( $id = null, $db )
	{
		$this->db = !is_null($db) ? $db : new Database();
		$this->logger = new Logger();
		
		$this->checkIfUnique = false;

		$this->init();
		$this->isNew = true;

		if( !is_null($id) )
		{
			$this->id = $id;
			$this->load();
		}
	}
	
	protected function init(){}

	public function load()
	{
		if( !is_null($this->id) )
		{
			$sql = "SELECT * FROM " . $this->table . " WHERE id='" . $this->id . "'";

			$result = $this->db->query($sql);

			if( $this->db->affected_rows > 0 )
			{
				$row = $result->fetch_assoc();
				
				foreach($row as $field=>$value)
				{
					$this->$field = $value;
				}

				$this->isNew = false;
			}
			else
			{
				$this->id = null;
				$this->isNew = true;
			}

			return $this->id != null;
		}
		
		return false;
	}
	
	public function save()
	{
		if( $this->isNew )
		{
			$fieldNames = array();
			$fieldValues = array();

			//	see if like record exists
			$where = array();

			foreach($this->fields as $field)
			{
				$value = $this->db->real_escape_string($this->$field);

				$fieldNames[] = $field;
				$fieldValues[] = $value;

				$where[] = $field . "='" . $value . "'";
			}
			
			if( $this->checkIfUnique )
			{

				$sql = "SELECT id FROM " . $this->table . " WHERE " . implode(" AND ",$where);
				$result = $this->db->query($sql);

				if( $this->db->affected_rows > 0 )
				{
					$this->errorMessage = $this->getErrorMessage( self::ERROR_RECORD_EXISTS );
					
					return false;
				}
			}

			$sql = "INSERT INTO `" . $this->table . "` (" . implode(",",$fieldNames) . ") VALUES ('" . implode("','",$fieldValues) . "')";
			$result = $this->db->query($sql);
			
			if( $result )
			{
				$this->id = $this->db->insert_id;
				$this->isNew = false;
			}
			
			return $result;
		}
		else
		{
			$updates = array();

			foreach($this->fields as $field)
			{
				$updates[] = $field . "='" . $this->$field . "'";
			}
			
			$sql = "UPDATE `" . $this->table . "` SET " . implode(",",$updates) . " WHERE id='" . $this->id . "'";
			
			return $this->db->query($sql);
		}

		return false;
	}

	public function delete()
	{
		$sql = "DELETE FROM `" . $this->table . "` WHERE id='" . $this->id . "'";
		$success = $this->db->query($sql);

		$this->isNew = !is_null($success);

		return $success;
	}
	
	protected function getErrorMessage( $id )
	{
		return "";
	}

	public function getLog()
	{
		return $this->logger->log;
	}
}

?>