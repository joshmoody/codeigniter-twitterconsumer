# CodeIgniter-Twitter Consumer

Twitter API client that works with Application-only authentication for compatibility with the 1.1 API.  Can expose tweets as RSS or JSON and restore shortened URLS.

As of the 1.1 version of the [Twitter REST API](https://dev.twitter.com/docs/api), all requests require OAuth.
On 11-Mar-2013, Twitter released Application-only authentication to allow
requests on behalf of an APPLICATION, as opposed to on behalf of a specific USER.

This library implements the new authentication for public resources like user timelines.

See <https://dev.twitter.com/docs/auth/application-only-auth> for more info on the API.

See <https://dev.twitter.com/apps> to register an application - you'll need a consumer key and consumer secret.

## Features
1. Wraps API requests in OAuth.
2. Optionally restore auto-shortened urls.
3. Expose tweets as RSS.

## Requirements

1. PHP 5
2. CodeIgniter 2.0+
3. CodeIgniter Curl library: <http://getsparks.org/packages/curl/show>

## Example

	// Load the library.
	$this->load->library('twitter_consumer');
	
	// Which API resource do we want to fetch?
	$resource = '/statuses/user_timeline.json?screen_name=joshmoody';
	
	// Make the call, passing TRUE as 2nd param to restore shortened URLS.
	$response = $this->twitter_consumer->request($resource, TRUE);
	
	// Send a header and output response.
	header('Content-Type: application/json; charset=utf-8');
	print $response;
	exit;
	
## Example 2 (If RSS is your thing)
	// Load the library.
	$this->load->library('twitter_consumer');
	
	// Which API resource do we want to fetch?
	$resource = '/statuses/user_timeline.json?screen_name=joshmoody';
	
	// Make the call, passing TRUE as 2nd param to restore shortened URLS.
	$response = $this->twitter_consumer->request($resource, TRUE);

	// Convert the json response to RSS	
	$rss = $this->twitter_consumer->to_rss($response);
	header('Content-Type: text/xml');
	print $rss;
	exit;

## Installation
1. Copy config/twitter.php to your config directory.
2. Update config/twitter.php with your consumer key/secret.
3. Copy libraries/twitter_consumer to your libraries directory.
4. Download/Install the Curl library from <https://github.com/philsturgeon/codeigniter-curl>
