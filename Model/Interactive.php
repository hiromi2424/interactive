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
			$args = explode(',', $matches[2]);
			foreach($args as $i => $arg) {
				$args[$i] = eval('return ' . $arg . ';');
			}
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
		$this->type = null;
		$classType = false;
		$className = $this->_fixClassName($className);

		if ($this->type) {
			$types = array($this->type);
		} else {
			$types = array('model', 'helper');
		}

		$class = $className;
		if (strpos($className, '.') !== false) {
			list($plugin, $className) = explode('.', $className);
			$this->objectPath = App::pluginPath($plugin);
		}

		foreach($types as $type) {
			$objects = Configure::listObjects(
				$type,
				$this->objectPath ? $this->objectPath . Inflector::pluralize($type) . DS : null,
				$this->objectCache
			);
			if (in_array($className, $objects)) {
				$classType = $type;
				break;
			}
		}

		switch ($classType) {
			case 'model':
				return ClassRegistry::init($class);
			case 'controller':
				App::import('Controller', $class);
				$className = $className . 'Controller';
				return new $className();
			case 'component':
				App::import('Controller', 'Controller');
				$Controller = new Controller();
				$Controller->params['action'] = '';
				App::import('Component', $class);
				$className = $className . 'Component';
				$Class = new $className();
				$Class->initialize($Controller);
				$Class->startup($Controller);
				return $Class;
			case 'helper':
				$this->raw = true;
				App::import('Controller', 'Controller');
				$Controller = new Controller();
				$Controller->helpers[] = $class;
				App::import('View', 'View');
				$View =& new View($Controller);
				$loaded = array();
				$helpers = $View->_loadHelpers($loaded, $Controller->helpers);
				return $helpers[$className];
		}

		return false;
	}

	protected function _fixClassName($className) {
		if (stripos($className, 'component') !== false) {
			$this->type = 'component';
		}

		if (stripos($className, 'controller') !== false) {
			$this->type = 'controller';
		}

		return ucfirst(preg_replace('/(\$|controller|component)/i', '', $className));
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
