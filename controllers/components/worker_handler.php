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

class WorkerHandlerComponent extends Object {

	var $name = 'WorkerHandler';
	var $components = array('MgWorkers.TaskHandler', 'MgWorkers.ProcessHandler');

/**
* Settings
*
* @var array
*/
	public $settings = array();

/**
* Default settings
*
* @var array
*/
	protected $_defaults = array(
	);

	function initialize(&$controller, $settings = array()) {
		$this->Controller =& $controller;
		$this->settings = array_merge($this->_defaults, $settings);

		Configure::write('debug', 2);
	}

	function startup(&$controller){

	}

	function add($type = null, $name = 'worker') {

		if($type == 'worker') {

			$taskName = $this->prefix . ucfirst($name);
			return $this->__task_add($taskName, 'wget --no-check-certificate --timeout=1 -O ' . TMP . 'null' . ' http' . (Configure::read('Server.ssl') ? 's' : null) . '://' . SERVER_NAME . '/workers/work/' . $name . '/username:'. $this->username . '/password:' . $this->password);

		} elseif($type == 'task') {
			$name = !empty($this->passedArgs['name']) ? $this->passedArgs['name'] : 'default';

			$tasks = array(
				'ColPlayerCheck' => 'wget --no-check-certificate --timeout=1 -O ' . TMP . 'null' . ' http' . (Configure::read('Server.ssl') ? 's' : null) . '://' . SERVER_NAME . '/players/check' . '/username:'. $this->username . '/password:' . $this->password
			);

			if(!empty($tasks[$name])) {
				debug($this->__task_add($name, $tasks[$name]));
			}

			exit;

		}

		//$shell = CONFIGS . 'shell.php';
		//$script = $_SERVER['PHPRC'] . DS . 'php.exe -f '.$shell.' http://'.SERVER_NAME.'/workers/work' . ($worker?'/'.$worker:null) . '/username:'. $this->username . '/password:' . $this->password;
	}

	function delete($worker = null) {
		if(!$worker) return false;
		return $this->TaskHandler->delete($worker);
	}

	function run($worker = null) {
		if(!$worker) return false;
		return $this->TaskHandler->run($worker);
	}

	function job($worker = null, $job = array()) {
		if(!$worker) return false;
		$workerCache = Inflector::underscore('worker_' . $worker);

		$jobs = Cache::read($workerCache, 'workers');
		$jobs[] = array_merge($job, array('created' => microtime(true)));
		$this->log($worker.'::add_job', $job);
		Cache::write($workerCache, $jobs, 'workers');

		return $this->run($worker);
	}

	function work($worker = null) {
		if(!$worker) return false;
		$workerCache = Inflector::underscore('worker_' . $worker);

		$jobs = Cache::read($workerCache, 'workers');
		if(is_array($jobs)&&isset($jobs[0])) $job =& $jobs[0];
		else $job = null;

		if (!$job) {
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'sleep');
		} elseif(!is_array($job)) {
			array_shift($jobs);
			Cache::write($workerCache, $jobs, 'workers');
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'invalid_job', compact('job'));
			$this->work($worker);
		} elseif(!empty($job['task']) && method_exists($this->Controller->{Inflector::camelize($job['task'])}, 'execute')) {

			if(!empty($job['pid'])) {
				if($this->ProcessHandler->isAlive($job['pid'])) {
					$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'isAlive', compact('job'));
					exit();
				} else {
					array_shift($jobs);
					Cache::write($workerCache, $jobs, 'workers');
					if(!empty($job['after'])) $this->_afterWork($job);
					$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'isDead', compact('job'));
					$this->work($worker);
				}
			} elseif(isset($job['pid'])&&!$job['pid']) {
				array_shift($jobs);
				Cache::write($workerCache, $jobs, 'workers');
				$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'invalid_pid', compact('job'));
				$this->work($worker);
			}

			$jobs[0] = $this->Controller->{Inflector::camelize($job['task'])}->execute($this->Controller, $job);
			Cache::write($workerCache, $jobs, 'workers');
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'started', compact('job'));
		} else {
			array_shift($jobs);
			Cache::write($workerCache, $jobs, 'workers');
			$this->log(__FUNCTION__ . ' (' . __LINE__ . ') ' . $worker . '::' . 'invalid_task', compact('job'));
			$this->work($worker);
		}

		exit;
	}

	function _afterWork($job) {
		$afterWork = $job['after'];

		if(!is_array($afterWork)) $afterWork = array($afterWork);

		if(in_array('unlink', $afterWork)) @unlink($job['input']);
	}

	/*~~ utility methods ~~*/

	function log($info = null, $data = null, $log = null) {
		if(!$info) return false;
		if(!$log) $log = SERVER_NAME . DS . date("Ymd") . '-' . SERVER_NAME . '-' . Inflector::underscore($this->name);

		if(is_array($info)) {
			if($data) $log = $data;
			$info = str_replace("\n", "\n\t", substr(print_r($info, true), 0, -1));
		} elseif(is_array($data) || is_object($data)) {
			$info = $info . "\n" . str_replace("\n", "\n\t", substr(print_r($data, true), 0, -1));
		}

		$this->Controller->log($info, $log);
	}

	function loadModel($model) {
		return $this->Controller->loadModel($model);
	}

}
?>
