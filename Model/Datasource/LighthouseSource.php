<?php

/**
 *
 * @author Angel S. Moreno <angelxmoreno@gmail.com>
 * @link http://github.com/angelxmoreno
 */
App::uses('HttpSocket', 'Network/Http');

class LighthouseSource extends DataSource {

	/**
	 * CakePHP datasource for the LightHouse Issue Tracking platform
	 */
	public $description = 'CakePHP datasource for the LightHouse Issue Tracking platform';

	/**
	 * HttpSocket
	 *
	 */
	protected $_http;

	/*
	 * list of sources and fields
	 * @array
	 */
	protected $_database = array(
	    'users' => array(
		'id' => array(
		    'type' => 'integer',
		    'null' => false,
		    'key' => 'primary',
		    'length' => 11,
		),
		'job' => array(
		    'type' => 'string',
		    'null' => true,
		    'length' => 255,
		),
		'name' => array(
		    'type' => 'string',
		    'null' => false,
		    'length' => 255,
		),
		'website' => array(
		    'type' => 'string',
		    'null' => true,
		    'length' => 255,
		),
		'avatar_url' => array(
		    'type' => 'string',
		    'null' => true,
		    'length' => 255,
		),
	    ),
		/*
	    'changesets',
	    'projects',
	    'tickets',
	    'bins',
	    'milestones',
	    'messages',
	    'memberships'
	     *
	     */
	);

	/**
	 * The DataSource configuration
	 * These options will be customized in our ``app/Config/database.php``
	 * and will be merged in the ``__construct()``.
	 * @var array
	 */
	public $config = array(
	    'apiKey' => '',
	);

	/**
	 * The default configuration of a specific DataSource
	 * These options get merged with $this->config
	 * @var array
	 */
	protected $_baseConfig = array(
	    'baseUrl' => 'https://activereload.lighthouseapp.com/',
	);

	/**
	 * Create our HttpSocket and handle any config tweaks.
	 */
	public function __construct($config) {
		parent::__construct($config);
		$this->_http = new HttpSocket();
	}

	/**
	 * Since datasources normally connect to a database there are a few things
	 * we must change to get them to work without a database.
	 */

	/**
	 * listSources() is for caching. You'll likely want to implement caching in
	 * your own way with a custom datasource. So just ``return null``.
	 */
	public function listSources($data = null) {
		return array_keys($this->_database);
	}

	/**
	 * describe() tells the model your schema for ``Model::save()``.
	 *
	 * You may want a different schema for each model but still use a single
	 * datasource. If this is your case then set a ``schema`` property on your
	 * models and simply return ``$model->schema`` here instead.
	 */
	public function describe($model) {
		return $this->_database[$model->useTable];
	}

	/**
	 * calculate() is for determining how we will count the records and is
	 * required to get ``update()`` and ``delete()`` to work.
	 *
	 * We don't count the records here but return a string to be passed to
	 * ``read()`` which will do the actual counting. The easiest way is to just
	 * return the string 'COUNT' and check for it in ``read()`` where
	 * ``$data['fields'] === 'COUNT'``.
	 */
	public function calculate(Model $model, $func, $params = array()) {
		return 'COUNT';
	}

	/**
	 * Implement the R in CRUD. Calls to ``Model::find()`` arrive here.
	 * Since we have to manipulate the $queryData a bit for each of the different
	 * sources, we break the read() up
	 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		$readFunction = 'read' . ucfirst($model->useTable);
		if (method_exists($this, $readFunction)) {
			return call_user_func(array($this, $readFunction), $model, $queryData, $recursive);
		} else {
			throw new CakeException('Unknown source:' . $model->useTable);
		}
	}

	public function readUsers(Model $model, $queryData = array(), $recursive = null) {
		/**
		 * Here we do the actual count as instructed by our calculate()
		 * method above. We could either check the remote source or some
		 * other way to get the record count. Here we'll simply return 1 so
		 * ``update()`` and ``delete()`` will assume the record exists.
		 */
		if ($queryData['fields'] === 'COUNT') {
			return array(array(array('count' => 1)));
		}
		/**
		 * Now we get, decode and return the remote data.
		 */
		if (!array_key_exists('id', $queryData)) {
			$table2fetch = 'profile';
		} else {
			$table2fetch = $model->useTable;
		}
		$queryData['conditions']['_token'] = $this->config['apiKey'];
		$url = $this->config['baseUrl'] . $table2fetch . '.json';
		$json = $this->_http->get($url, $queryData['conditions']);
		$res = json_decode($json->body, true);
		$res = current($res);
		//debug($res);
		if (is_null($res)) {
			$error = json_last_error();
			throw new CakeException($error);
		}
		return array(array($model->alias => $res));
	}

	/**
	 * Implement the C in CRUD. Calls to ``Model::save()`` without $model->id
	 * set arrive here.
	 */
	public function create(Model $model, $fields = null, $values = null) {
		$data = array_combine($fields, $values);
		$data['apiKey'] = $this->config['apiKey'];
		$json = $this->Http->post('http://example.com/api/set.json', $data);
		$res = json_decode($json, true);
		if (is_null($res)) {
			$error = json_last_error();
			throw new CakeException($error);
		}
		return true;
	}

	/**
	 * Implement the U in CRUD. Calls to ``Model::save()`` with $Model->id
	 * set arrive here. Depending on the remote source you can just call
	 * ``$this->create()``.
	 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {
		return $this->create($model, $fields, $values);
	}

	/**
	 * Implement the D in CRUD. Calls to ``Model::delete()`` arrive here.
	 */
	public function delete(Model $model, $id = null) {
		$json = $this->Http->get('http://example.com/api/remove.json', array(
		    'id' => $id[$model->alias . '.id'],
		    'apiKey' => $this->config['apiKey'],
			));
		$res = json_decode($json, true);
		if (is_null($res)) {
			$error = json_last_error();
			throw new CakeException($error);
		}
		return true;
	}

}
