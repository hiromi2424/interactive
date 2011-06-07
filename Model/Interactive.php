<?php
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
		$objectName = $this->_fixClassName($className);

		$this->type = null;
		$types = array_intersect_key(App::$types, array_flip(array(
			'component',
			'helper',
			'controller',
			'model',
		)));
		foreach($types as $type => $typeInfo) {
			$objectNameCandidate = $objectName;
			if (isset($typeInfo['suffix'])) {
				$objectNameCandidate = preg_replace(sprintf('/%s$/', $typeInfo['suffix']), '', $objectNameCandidate);
			}

			if (App::import($type, $objectNameCandidate)) {
				$this->type = $type;
				$objectName = $objectNameCandidate;

				list($plugin, $className) = pluginSplit($objectName);
				if (isset($typeInfo['suffix'])) {
					$className .= $typeInfo['suffix'];
				}
				break;
			}
		}

		switch ($this->type) {
			case 'model':
				return ClassRegistry::init($objectName);
			case 'controller':
				return new $className();
			case 'component':
				App::uses('Controller', 'Controller');
				$Controller = new Controller(new CakeRequest('/'));
				$Controller->params['action'] = '';
				$Component = new $className(new ComponentCollection);
				$Component->initialize($Controller);
				$Component->startup($Controller);
				return $Component;
			case 'helper':
				$this->raw = true;
				App::uses('View', 'View');
				App::uses('Controller', 'Controller');
				$View = new View(new Controller(new CakeRequest('/')));
				return $View->loadHelper($objectName);
		}

		return false;
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
