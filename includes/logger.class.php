<?php

class Logger
{
	public $log;
	
	function __construct() 
	{
		$this->clear();
	}
	
	function log( $message )
	{
		$this->log[] = $message;
	}
	
	function clear()
	{
		$this->log = array();
	}
}

?>