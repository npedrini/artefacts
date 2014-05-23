<?php
include_once "includes/dbobject.class.php";

class User extends DBObject
{
	public $email;
	public $name;
	public $implied;
	public $ip;
	
	function __construct( $id = null, $db = null ) 
	{
		$this->fields = array('email','name','implied','ip');
		$this->table = "users";

		parent::__construct( $id, $db );
    }

	public function loadFromName()
	{
		$sql = "SELECT id FROM `" . $this->table . "` WHERE name='".$this->name."'";
		$result = $this->db->query( $sql );

		if( $this->db->affected_rows > 0 )
		{
			$row = $result->fetch_assoc();
			$this->id = $row['id'];
			$this->load();
		}
	}
	
	public function loadFromEmail()
	{
		$sql = "SELECT id FROM `" . $this->table . "` WHERE email='".$this->email."'";
		$result = $this->db->query( $sql );

		if( $this->db->affected_rows > 0 )
		{
			$row = $result->fetch_assoc();
			$this->id = $row['id'];
			$this->load();
		}
	}
}

?>