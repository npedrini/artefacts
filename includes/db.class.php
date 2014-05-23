<?php
include_once "config/config.php";

class Database
{
	public $connection;

	function Database()
	{
	}
	
	function query( $string )
	{
		if( is_null($this->connection) ) 
		{
			$this->connection = new mysqli( DB_HOST, DB_USER, DB_PASS );
			$this->connection->select_db( DB_NAME );
		}

		return $this->connection->query( $string );
	}
	
	public function __get($property) 
	{
		if (property_exists($this->connection, $property)) 
		{
			return $this->connection->$property;
		}
	}
	
	function real_escape_string( $string )
	{
		return $this->connection->real_escape_string( $string );
	}
}

?>