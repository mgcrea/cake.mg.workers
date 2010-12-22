<?php

App::import('Component', array('MgWorkers.TaskHandler'));

class TaskHandlerComponentTest extends CakeTestCase {
/**
 * Fixtures
 *
 * @var array
 * @access public
 */
	public $fixtures = array();

/**
 * setUp method
 *
 * @access public
 * @return void
 */
	function startTest() {
		$this->Controller = new ArticlesTestController();
		$this->Controller->constructClasses();
		$this->Controller->action = 'edit';
		$this->Controller->params = array(
			'named' => array(),
			'pass' => array(),
			'url' => array());
		$this->Controller->modelClass = 'Article';
		$this->Controller->Component->init($this->Controller);
		$this->Controller->Component->initialize($this->Controller);
	}

/**
 * tearDown method
 *
 * @access public
 * @return void
 */
	function endTest() {
		unset($this->Controller);
		ClassRegistry::flush();
	}

/**
 * testStartup
 *
 * @access public
 * @return void
 */
	public function testStartup() {
	}

}
?>