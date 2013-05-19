<?php
include_once "includes/dbobject.class.php";

class User extends DBObject
{
	public $email;
	public $ip;
	
	function __construct( $id = null ) 
	{
		$this->fields = array('email','ip');
		$this->table = "users";

		parent::__construct( $id );
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