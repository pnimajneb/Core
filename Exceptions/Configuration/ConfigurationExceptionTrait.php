<?php namespace exface\Core\Exceptions\Configuration;

use exface\Core\Interfaces\ConfigurationInterface;
use exface\Core\Exceptions\ExceptionTrait;

trait ConfigurationExceptionTrait {
	
	use ExceptionTrait {
		create_widget as create_parent_widget;
	}
	
	private $configuration = null;
	
	public function __construct (ConfigurationInterface $configuration, $message, $code = null, $previous = null) {
		parent::__construct($message, null, $previous);
		$this->set_configuration($configuration);
	}
	
	public function get_configuration(){
		return $this->configuration;
	}
	
	public function set_configuration(ConfigurationInterface $value){
		$this->configuration = $value;
		return $this;
	}
	
}
?>