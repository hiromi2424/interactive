<?php

App::uses('Controller', 'Controller');
App::uses('AppController', 'Controller');
App::uses('Component', 'Controller');
App::uses('ComponentCollection', 'Controller');
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');
App::uses('View', 'View');
App::uses('AppHelper', 'View/Helper');

class Interactive extends InteractiveAppModel {
	public $useTable = false;
	public $objectCache = true;
	public $objectPath = null;
	public $raw = false;
	public $type = null;

	public function process($cmds) {
		$cmds = explode(";", $cmds);

		$results = array();
		foreach($cmds as $cmd) {
			$this->raw = false;
			$cmd = trim($cmd);
			if (empty($cmd)) {
				continue;
			}

			if (!$type = $this->_findCmdType($cmd)) {
				continue;
			}

			$func = sprintf('_%sCall', $type);
			$output = $this->{$func}($cmd);
			$results[] = array(
				'cmd' => $cmd,
				'raw' => $this->raw,
				'output' => $output
			);
		}

		return $results;
	}

	protected function _classCall($cmd) {
		$cmd = str_replace('$this->', '', $cmd);
		list($className, $function) = preg_split('/(::|->)/', $cmd, 2);
		$Class = $this->_getClass($className);

		if (!$Class) {
			return $this->_codeCall($cmd);
		}

		preg_match('/^([a-zA-Z_]{1,})\((.{0,})\)/', $function, $matches);

		if (!$matches) {
			return $Class-> {$function};
		}

		$args = array();
		if (!empty($matches[2])) {
			$args = eval(sprintf('return array(%s);', $matches[2]));
		}

		return call_user_func_array(array($Class, $matches[1]), $args);
	}

	protected function _sqlCall($cmd) {
		return $this->query($cmd);
	}

	protected function _codeCall($cmd) {
		return eval('return ' . $cmd . ';');
	}

	protected function _getClass($className) {
		$className = $this->_fixClassName($className);

		$this->type = null;
		$types = array(
			'controller' => array(
				'suffix' => 'Controller',
				'path' => 'Controller',
			),
			'component' => array(
				'suffix' => 'Component',
				'path' => 'Controller/Component',
			),
			'model' => array(
				'path' => 'Model',
			),
			'helper' => array(
				'suffix' => 'Helper',
				'path' => 'View/Helper',
			),
		);

		$objectName = null;
		list($plugin, $className) = pluginSplit($className, true);
		foreach ($types as $type => $typeInfo) {
			$classNameCandidate = $this->_attachSuffix($className, $typeInfo);
			$classExists = class_exists($classNameCandidate);
			if (!$classExists) {
				App::uses($classNameCandidate, $plugin . $typeInfo['path']);
				$classExists = class_exists($classNameCandidate);
			}

			if ($classExists) {
				$this->type = $type;
				$className = $classNameCandidate;
				$objectName = $this->_removeSuffix($className, $typeInfo);
				break;
			}
		}

		switch ($this->type) {
			case 'model':
				return ClassRegistry::init($plugin . $objectName);
			case 'controller':
				return new $className();
			case 'component':
				$Controller = $this->controller();
				if (isset($Controller->$objectName)) {
					return $Controller->$objectName;
				}
				$Component = new $className(new ComponentCollection($Controller));
				$Component->initialize($Controller);
				$Component->startup($Controller);
				return $Component;
			case 'helper':
				$this->raw = true;
				return $this->view()->loadHelper($plugin . $objectName);
		}

		return false;
	}

	protected function _attachSuffix($className, $typeInfo) {
		if (!empty($typeInfo['suffix'])) {
			$className = $this->_removeSuffix($className, $typeInfo);
			$className .= $typeInfo['suffix'];
		}

		return $className;
	}

	protected function _removeSuffix($className, $typeInfo) {
		if (!empty($typeInfo['suffix'])) {
			$className = preg_replace(sprintf('/%s$/', $typeInfo['suffix']), '', $className);
		}

		return $className;
	}

	/*
	 * Setter/Getter of controller
	 */
	public function controller($controller = null) {
		if ($controller !== null) {
			$this->set('Controller', $controller);
		} elseif (empty($this->data[$this->alias]['Controller'])) {
			$Controller = new Controller(new CakeRequest());
			$Controller->params['action'] = '';
			$this->set(compact('Controller'));
		}

		return $this->data[$this->alias]['Controller'];
	}

	/*
	 * Setter/Getter of view
	 */
	public function view($view = null) {
		if ($view !== null) {
			$this->set('View', $view);
		} elseif (empty($this->data[$this->alias]['View'])) {
			$this->set('View', new View(new Controller(new CakeRequest())));
		}

		return $this->data[$this->alias]['View'];
	}

	protected function _fixClassName($className) {
		return ucfirst(preg_replace('/^\$/', '', $className));
	}

	protected function _findCmdType($cmd) {
		if (preg_match('/(::|->)/', $cmd)) {
			return 'class';
		}

		if (preg_match('/^(select|insert|update|delete)/i', $cmd)) {
			return 'sql';
		}

		return 'code';
	}

}
