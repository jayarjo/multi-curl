<?php

namespace I8\MultiCurl;

class MultiCurl 
{
	const IDLE = 0;
	const RUNNING = 1;
	const STOPPING = 2;

	public $on_success = false;
	public $on_error = false;

	private $_state = MultiCurl::IDLE;
	
	private $_curl_opts = array(
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_FORBID_REUSE => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_DNS_CACHE_TIMEOUT => 600,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_TIMEOUT => 120,
		CURLOPT_HTTPHEADER => array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			//'Accept-Encoding: gzip;q=0,deflate,sdch',
			'Accept-Language: en-US,en;q=0.8,es;q=0.6,ka;q=0.4,nl;q=0.2,ru;q=0.2',
			'Cache-Control: no-cache',
			'Connection: keep-alive',
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36'
		)
	);

	protected $_UAs = array(
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9) AppleWebKit/537.71 (KHTML, like Gecko) Version/7.0 Safari/537.71',
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:25.0) Gecko/20100101 Firefox/25.0',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:25.0) Gecko/20100101 Firefox/25.0',
		'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; rv:25.0) Gecko/20100101 Firefox/25.0',
		'Mozilla/5.0 (Windows NT 5.1; rv:25.0) Gecko/20100101 Firefox/25.0',
		'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36',
		'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:25.0) Gecko/20100101 Firefox/25.0',
		'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.57 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 7_0_3 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B511 Safari/9537.53',
	);


	private $_mh;

	private $_pool = array();

	private $_queue = array();

	private $_proxies;

	private $_options = array(
		'pool_size' => 100
	);


	// turn into a singleton
	protected static $_instance;
	protected function __clone() {}
	static function get_instance()
    {
        if (!isset(static::$_instance)) {
            static::$_instance = new static;
        }
        return static::$_instance;
    }


    protected function __construct()
	{
		$this->_mh = curl_multi_init();
		$this->set_options();
	}


	protected function __destruct()
	{
		if (gettype($this->_mh) == 'resource') {
			curl_multi_close($this->_mh);
		}
	}


	public function set_options($options = array())
	{
		$this->_options = array_replace($this->_options, $options);
	}


	public function get_typical_curl_opts()
    {
    	return self::$_curl_opts;
    }


	function get($url, $curl_opts = array())
	{
		return $this->request($url, $curl_opts);
	}


	function post($url, $data = array(), $curl_opts = array())
	{
		$curl_opts[CURLOPT_POST] = true;

		if (!empty($data)) {
			$curl_opts[CURLOPT_POSTFIELDS] = $data;
		}
		return $this->request($url, $curl_opts);
	}


	function request($options, $curl_opts = array())
	{
		if (is_string($options)) {
			$options = array(
				'url' => $options
			);
		}

		$options = array_replace_recursive(array(
			'priority' => 10,
			'on_success' => false,
			'on_error' => false,
			'curl_opts' => $curl_opts
		), $options);

		// transform the url to a more predictable form
		$options['url'] = trim(strtolower($options['url']), ' /');

		$this->_queue[$options['url']] = $options;

		// sort by priority
		usort($this->_queue, create_function(
			'$a, $b', 
			'return $a["priority"] == $b["priority"] ? 0 : $a["priority"] < $b["priority"] ? -1 : 1'
		));

		if ($this->_state == MultiCurl::IDLE) {
			$this->start();
		}
	}


	function start()
	{
		if ($this->_state != MultiCurl::IDLE) {
			return;
		}
		$this->_state = MultiCurl::RUNNING;


		$still_running = null;
		$this->_populate_pool();
		
		do {
		    while (CURLM_CALL_MULTI_PERFORM === ($code = curl_multi_exec($this->_mh, $still_running)));

		    if (!$still_running && $code == CURLM_OK) {
		    	// pool seems empty, add more
		    	$this->_populate_pool();
		    	if (!sizeof($this->_pool)) {
		    		break; // nothing to add
		    	}
		    } elseif ($code != CURLM_OK) {
		    	break;
		    }

            // check if we have completed requests
            while ($request = curl_multi_info_read($this->_mh)) {
            	$this->_request_completed($request['handle']);
            }
		    
		    // wait for any activity
		    while (curl_multi_select($this->_mh) === 0);
		} while (true);

		$this->_state = MultiCurl::IDLE;
	}


	/**
	 * Stop. Sets internal state to STOPPING.
	 *
	 * @param {String} [$now=true] Set to false, to wait until running connections complete or fail.
	 */
	function stop($now = true)
	{
		$this->_state = MultiCurl::STOPPING;

		if ($now) {
			// cancel all connections and empty pool
			foreach ($this->_pool as $ch_id => $item) {
				curl_close($item['handle']);
			}
			$this->_pool = array();
		}
	}


	private function _create_curl_handle($item)
	{
		$this->_options =& $options;

		$ch = curl_init();

		curl_setopt_array($ch, array_replace(array(
			CURLOPT_URL => $item['url'],
			CURLOPT_USERAGENT => $this->_UAs[array_rand($this->_UAs)]
		), $this->_curl_opts, $item['curl_opts']));

		return $ch;
	}


	private function _populate_pool()
	{
		// check if we have some free slots
		if (sizeof($this->_pool) >= $this->_options['pool_size']) {
			return;
		}

		// populate the pool
		while (sizeof($this->_pool) < $this->_options['pool_size'])
		{
			// pick the right candidate
			reset($this->_queue);
			if (!$item = current($this->_queue)) {
				return; // no more urls in the queue
			}

			$item['handle'] = $this->_create_curl_handle($item);
			
			// remove from the queue and drop into the pool
			unset($this->_queue[$item['url']]);
			$this->_pool[(string)$ch] = $item;
			
			curl_multi_add_handle($this->_mh, $item['handle']);
		}
	}


	private function _request_completed($handle)
	{
		$ch_id = (string)$handle;
		if (!isset($this->_pool[$ch_id])) {
			// universe is broken, log
			return;
		}

		$item = $this->_pool[$ch_id];
		unset($this->_pool[$ch_id]);

		// find out if it was successful or not
		$info = curl_getinfo($handle);
		if ($info && $info['http_code'] == 200)
		{
			$contents = curl_multi_getcontent($handle);

        	if (is_callable($item['on_success'])) {
        		call_user_func($item['on_success'], $url, $contents);
        	}

        	if (is_callable($this->on_success)) {
        		call_user_func($this->on_success, $url, $contents);
        	}
        }
        else 
        {
        	$error = curl_error($handle);

        	if (is_callable($item['on_error'])) {
        		call_user_func($item['on_error'], $url, $error);
        	}

        	if (is_callable($this->on_error)) {
        		call_user_func($this->on_error, $url, $error);
        	}
        }

		curl_multi_remove_handle($this->_mh, $handle);
        
        $this->_populate_pool();
	}
}



