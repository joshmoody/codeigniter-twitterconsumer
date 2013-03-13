<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * As of the 1.1 version of the Twitter REST API, all requests require OAuth.
 * On 11-Mar-2012, Twitter released Application-only authentication to allow
 * requests on behalf of an APPLICATION, as opposed to on behalf of a specific USER.
 * 
 * This library implements the new authentication for public resources like user timelines.
 *
 * See https://dev.twitter.com/docs/auth/application-only-auth for more info.
 */
class Twitter_consumer{
	
	public $request_info;
	
	public function __construct()
	{
		$this->load->library('curl');
		$this->load->config('twitter', TRUE);
		
		log_message('debug', 'Twitter Consumer Class Initialized');
	}

	/**
	 * Make an authenticated api request to twitter.
	 */
	public function request($url)
	{
		try
		{
			$token = $this->get_token();	
		}
		catch (Exception $e)
		{
			return json_encode(array('success' => FALSE, 'error' => $e->getMessage()));
		}
		
		$this->curl->create('https://api.twitter.com/1.1/' . $url);
		$this->curl->http_header('Authorization', 'Bearer ' . $token);
		$this->curl->http_header('User-Agent', $_SERVER['SERVER_NAME']);
		$response = $this->curl->execute();

		$this->request_info = $this->curl->info;
		
		if ($this->curl->info['http_code'] == 200)
		{
			return $response;
		}
		else
		{
			$error = sprintf('Error: %s: %s', $this->curl->error_code, $this->curl->error_string);
			return json_encode(array('success' => FALSE, 'error' => $error));
		}
	}
	
	/**
	 * Use basic auth with a key comprised of our consumer key/consumer token
	 * to request a bearer token.
	 */
	protected function get_token()
	{
		$auth_key = $this->get_auth_key();
		$this->curl->create('https://api.twitter.com/oauth2/token');
		$this->curl->http_header('Authorization', 'Basic ' . $auth_key);
		$this->curl->http_header('User-Agent', $_SERVER['SERVER_NAME']);
		$this->curl->post('grant_type=client_credentials');
		
		$response = $this->curl->execute();

		if ($this->curl->info['http_code'] == 200)
		{
			$result = json_decode($response);
			return $result->access_token;
		}
		else
		{
			$error = sprintf('Could not get bearer token.  Error: %s: %s', $this->curl->error_code, $this->curl->error_string);
			throw new Exception($error);
		}
	}
	
	/**
	 * Creates a basic auth key used to get a bearer token.
	 */
	protected function get_auth_key()
	{	
		$consumer_key = $this->config->item('consumer_key', 'twitter');
		$consumer_secret = $this->config->item('consumer_secret', 'twitter');
		return base64_encode(urlencode($consumer_key) . ':' . urlencode($consumer_secret));
	}
	
	/**
	 * __get
	 *
	 * Allows access CI's loaded classes using the same syntax as controllers.
	 *
	 * @param   string
	 * @access private
	 */
	function __get($key)
	{
	    $CI =& get_instance();
	    return $CI->$key;
	}
}