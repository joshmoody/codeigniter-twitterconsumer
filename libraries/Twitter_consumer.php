<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * As of the 1.1 version of the Twitter REST API, all requests require OAuth.
 * On 11-Mar-2013, Twitter released Application-only authentication to allow
 * requests on behalf of an APPLICATION, as opposed to on behalf of a specific USER.
 * 
 * This library implements the new authentication for public resources like user timelines.
 *
 * See https://dev.twitter.com/docs/auth/application-only-auth for more info.
 */
class Twitter_consumer{
	
	public $request_info;
	protected $token;
	
	public function __construct()
	{
		$this->load->library('curl');
		$this->load->config('twitter', TRUE);
		
		$this->request_info = NULL;
		$this->token = NULL;
		
		log_message('debug', 'Twitter Consumer Class Initialized');
	}

	/**
	 * Make an authenticated api request to twitter.
	 * 
	 * @access public
	 * @param mixed $url
	 * @param mixed $expand_urls (Should we auto expand shortened URLs? default: FALSE)
	 * @return string JSON response
	 */
	public function request($url, $expand_urls = FALSE)
	{
		if ($this->token==NULL)
		{
			try
			{
				$this->token = $this->get_token();	
			}
			catch (Exception $e)
			{
				return json_encode(array('success' => FALSE, 'error' => $e->getMessage()));
			}
		}
		
		$this->curl->create('https://api.twitter.com/1.1/' . $url);
		$this->curl->http_header('Authorization', 'Bearer ' . $this->token);
		$this->curl->http_header('User-Agent', $_SERVER['SERVER_NAME']);
		$response = $this->curl->execute();

		$this->request_info = $this->curl->info;
		
		if ($this->curl->info['http_code'] == 200)
		{
			if ($expand_urls === TRUE)
			{
				$response = $this->expand_urls($response);	
			}
			
			return $response;
		}
		else
		{
			$error = sprintf('Error: %s: %s', $this->curl->error_code, $this->curl->error_string);
			return json_encode(array('success' => FALSE, 'error' => $error));
		}
	}
	
	/**
	 * Google is killing reader, and Twitter has killed it's RSS feed. But just in case you want to expose your tweets as RSS...
	 */
	public function to_rss(&$json)
	{
		$feed_title = $this->config->item('feed_title', 'twitter');
		$feed_url = $this->config->item('feed_url', 'twitter');
		$feed_description = $this->config->item('feed_description', 'twitter');
				
		$feed = json_decode($json);

		$now = date('r');

		$output[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$output[] = '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">';
		$output[] = '<channel>';
		$output[] = sprintf('<title>%s</title>', $feed_title);
		$output[] = sprintf('<link>%s</link>', $feed_url);
		$output[] = sprintf('<description>%s</description>', $feed_description);
		$output[] = sprintf('<pubDate>%s</pubDate>', $now);
		$output[] = sprintf('<lastBuildDate>%s</lastBuildDate>', $now);
	
		foreach($feed as $item)
		{
			$screen_name = htmlentities($item->user->screen_name);
			$text_linked = $this->linkify($item->text);
			
			$output[] = '<item>';
			$output[] = sprintf('<title>%s</title>', $item->text);
			$output[] = sprintf('<guid>%s</guid>', htmlentities('https://twitter.com/'.$item->user->screen_name.'/statuses/'.$item->id_str));
			$output[] = sprintf('<link>%s</link>', htmlentities('https://twitter.com/'.$item->user->screen_name.'/statuses/'.$item->id_str));
			$output[] = sprintf('<description><![CDATA[<p>%s</p>]]></description>', $text_linked);

			$photo = $this->get_photo($item->entities);
			
			if ($photo)
			{
				$output[] = sprintf('<enclosure url="%s" length="%d" type="%s" />', $photo->url, $photo->length, $photo->type);
			}
			
			$output[] = sprintf('<dc:creator>%s</dc:creator>', $screen_name);
			$output[] = sprintf('<pubDate>%s</pubDate>', date('r', strtotime($item->created_at)));
			$output[] = '</item>';
		}

		$output[] = '</channel>';
		$output[] = '</rss>';
		return join("\n", $output);
	}

	/**
	 * Convert urls, twitter screen names, and hash tags to links.
	 */
	public function linkify($text)
	{
		// URLs
		$text = preg_replace('/(https?:\/\/\S+)/', '<a href="\1">\1</a>', $text);
		
		// Users
		$text = preg_replace('/(^|\s)@(\w+)/', '\1<a href="https://twitter.com/\2">@\2</a>', $text);
		
		// Hash Tags
		$text = preg_replace('/(^|\s)#(\w+)/', '\1<a href="https://twitter.com/search?q=%23\2">#\2</a>', $text);
		
		return $text;
	}
	
	/**
	 * Parse out a photo from a tweet. Used by RSS converter to create enclosure.
	 */
	public function get_photo($entities)
	{
		if (isset($entities->media) && is_array($entities->media) && $entities->media[0]->type == 'photo')
		{
			$url = $entities->media[0]->media_url;

			// We grab the photo with curl to get the content type and length - required attributes in RSS Enclosure.
			$response = $this->curl->simple_get($url);
			
			$result = (object) array('url'		=> $url,
									 'type'		=> $this->curl->info['content_type'],
									 'length'	=> $this->curl->info['download_content_length']);
			return $result;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Twitter's rest API shortens all URLS in the tweet by default. This method restores the original URLs.
	 */
	public function expand_urls($json)
	{
		$feed = json_decode($json);
		
		if (array_key_exists('statuses', $feed))
		{
			$feed = $feed->statuses;
		}
		
		foreach($feed as $item)
		{
			if (is_array($item->entities->urls))
			{
				foreach($item->entities->urls as $url)
				{
					$json = str_replace(addcslashes($url->url, '/'), addcslashes($url->expanded_url, '/'), $json);
				}	
			}
		}

		return $json;
	}
	
	/**
	 * Use basic auth with a key comprised of our consumer key/consumer token
	 * to request a bearer token.
	 */
	public function get_token()
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
	 * Sets the token, can be used with get_token to store the token bewtween calls
	 */
	public function set_token($token)
	{
  		$this->token=$token;
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
