<?php
/**
 * Copyright 2010, Magenta Creations (@olouv)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010, Magenta Creations (@olouv)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::import('Lib', 'MgShell', array('plugin' => 'MgUtils'));

class MediaConverterWorkerShell extends MgShell {

	var $name = 'MediaConverterWorker';
	var $plugin = 'MgWorkers';

	var $components = array('MgWorkers.WorkerHandler', 'MgWorkers.TaskHandler', 'MgWorkers.ProcessHandler');
	var $tasks = array('Ffmpeg2theora');
	var $uses = array();

	var $config = array(
		'setup' => array()
	);

	function main() {
		self::construct();

		$this->WorkerHandler->work('media_converter');
	}

	/*~~ callback ~~*/

	function beforeWork($job) {
		$this->log('beforeWork');
		return $job;
	}

	function afterWork($job) {
		$this->log('afterWork');
		if(!empty($job['after']['unlink'])) @unlink($job['input']);
	}

/**
 * Setup worker
 */
	function setup() {
		self::construct();
		$this->loadComponent("MgWorkers.TaskHandler");
		parent::setup(Inflector::underscore($this->name), $this->config['setup']);
	}

/***
 ** callback methods
 **/

	function construct() {
		parent::construct();
	}

}
?>
