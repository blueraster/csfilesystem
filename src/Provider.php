<?php
namespace BlueRaster\CSFilesystem;	
	
class Provider{
	
	private $app;
	
	public function __construct(){
		$this->app = new Silex\Application();
	}
	
}	