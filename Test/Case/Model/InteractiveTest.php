<?php

App::uses('InteractiveAppModel', 'Interactive.Model');
App::uses('Interactive', 'Interactive.Model');

class TestInteractive extends Interactive {

	public function __call($method, $arguments) {
		$protectedMethod = '_' . $method;
		if (method_exists($this, $protectedMethod)) {
			return call_user_func_array(array($this, $protectedMethod), $arguments);
		}
		return parent::__call($method, $arguments);
	}

}

class InteractiveTestCase extends CakeTestCase {
	public $Interactive = null;
	public $fixtures = array('core.post');

	public function setUp() {
		parent::setUp();
		$this->Interactive = ClassRegistry::init('TestInteractive');
		$this->Interactive->objectCache = false;
		$this->Interactive->objectPath = null;
	}

	public function tearDown() {
		unset($this->Interactive);
		parent::tearDown();
	}

	public function testInstance() {
		$this->assertTrue(is_a($this->Interactive, 'Interactive'));
	}

	public function testFindCmdTypeClass() {
		$type = $this->Interactive->findCmdType('User::find("all")');
		$this->assertEqual('class', $type);

		$type = $this->Interactive->findCmdType('User->find("all")');
		$this->assertEqual('class', $type);

		$type = $this->Interactive->findCmdType('User->Group->find("all")');
		$this->assertEqual('class', $type);

		$type = $this->Interactive->findCmdType('$User->find("all")');
		$this->assertEqual('class', $type);
	}

	public function testFindCmdTypeSql() {
		$type = $this->Interactive->findCmdType('SELECT * FROM users');
		$this->assertEqual('sql', $type);

		$type = $this->Interactive->findCmdType('delete from users where id = 2');
		$this->assertEqual('sql', $type);

		$type = $this->Interactive->findCmdType('update users set username = "test" where id = 3');
		$this->assertEqual('sql', $type);
	}

	public function testFindCmdTypeUnknown() {
		$type = $this->Interactive->findCmdType('__("test")');
		$this->assertEqual('code', $type);
	}

	public function testSqlCall() {
		$result = $this->Interactive->sqlCall('SELECT * FROM posts');
		$this->assertEqual(3, count($result));

		$result = $this->Interactive->sqlCall('select * from posts');
		$this->assertEqual(3, count($result));

		$this->Interactive->sqlCall('UPDATE posts SET title = "Test Post" WHERE id = 1');
		$result = $this->Interactive->sqlCall('select * from posts where id = 1');
		$this->assertEqual("Test Post", $result[0]['posts']['title']);
	}

	public function testCodeCall() {
		$result = $this->Interactive->codeCall('is_array(array(1,2,3))');
		$this->assertTrue($result);

		$result = $this->Interactive->codeCall('10 % 4');
		$this->assertEqual(2, $result);

		$result = $this->Interactive->codeCall('pluginSplit("Interactive.Interactive")');
		$this->assertEqual(array('Interactive', 'Interactive'), $result);
	}

	public function testFixClassName() {
		$result = $this->Interactive->fixClassName('html');
		$this->assertEqual('Html', $result);

		$result = $this->Interactive->fixClassName('$html');
		$this->assertEqual('Html', $result);

		$result = $this->Interactive->fixClassName('TestsAppsPostsController');
		$this->assertEqual('TestsAppsPostsController', $result);

		$result = $this->Interactive->fixClassName('$TestsAppsPostsController');
		$this->assertEqual('TestsAppsPostsController', $result);
	}

	protected function _setPath($type, $path) {
		$path = (array)$path;
		foreach ($path as &$p) {
			$p .= DS;
			$p = str_replace(DS . DS, DS, $p);
		}
		App::build(array(Inflector::camelize($type) => $path));
	}

	public function testGetClass() {
		$result = $this->Interactive->getClass('Html');
		$this->assertTrue(is_a($result, 'HtmlHelper'));

		$result = $this->Interactive->getClass('Form');
		$this->assertTrue(is_a($result->Html, 'HtmlHelper'));

		$this->_setPath('model', CAKE . 'Test' . DS . 'test_app' . DS . 'Model');
		$result = $this->Interactive->getClass('Post');
		$this->assertTrue(is_a($result, 'Post'));

		$this->_setPath('controller', CAKE . 'Test' . DS . 'test_app' . DS . 'Controller');
		$result = $this->Interactive->getClass('TestsAppsPostsController');
		$this->assertTrue(is_a($result, 'TestsAppsPostsController'));
	}

	public function testClassCallHelper() {
		$result = $this->Interactive->classCall('$this->Html->image("icons/ajax.gif")');
		$expected = '|<img src=".+/?img/icons/ajax\.gif" alt="" />|';
		$this->assertPattern($expected, $result);

		$result = $this->Interactive->classCall('$this->Form->input("Article.title")');
		$expected = '!<div class="input text"><label for="ArticleTitle">Title</label><input name="data\[Article\]\[title\]" type="text"( value="")? id="ArticleTitle" ?/></div>!';
		$this->assertPattern($expected, $result);
	}

	public function testClassCallController() {
		$this->_setPath('controller', CAKE . 'Test' . DS . 'test_app' . DS . 'Controller');
		$result = $this->Interactive->classCall('$TestsAppsPostsController->uses');
		$this->assertEqual(array('Post'), $result);
	}

	public function testClassCallComponent() {
		Configure::write('debug', 0);
		Configure::write('Security.salt', 'fc4a7a2d16ed61344ff95c87674620c4ece9cea1');
		App::uses('Security', 'Utility');
		Security::setHash('sha1');
		$result = $this->Interactive->classCall('AuthComponent::password("test")');
		$this->assertEqual('cfc21a50c1f69eabdb6687d7f2b33891865f69bb', $result);
		Configure::write('debug', 2);

		$result = $this->Interactive->classCall('AuthComponent->user()');
		$this->assertNull($result);
	}

	public function testClassCallCore() {
		App::import('Routing', 'Router');
		$result = $this->Interactive->classCall('Router::url(array("controller" => "posts", "action" => "view", 3))');
		$this->assertEqual('/posts/view/3', $result);
	}

	public function testClassCallModel() {
		$this->_setPath('model', CAKE . 'Test' . DS . 'test_app' . DS . 'Model');
		$result = $this->Interactive->classCall('Post::find("all")');
		$this->assertEqual(3, count($result));
		$this->assertEqual('First Post', $result[0]['Post']['title']);
	}

}
