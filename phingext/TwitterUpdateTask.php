<?php
require_once ('phing/Task.php');
require_once ('twitteroauth/twitteroauth.php');

/**
 * Class TwitterUpdateTask
 *
 * Tweets a Twitter message.
 *
 * Please note that you should create a new app in order to get the
 * keys/tokens: https://dev.twitter.com/apps
 *
 * @see http://jfoucher.com/2011/06/phing-task-to-update-twitter-status.html
 */
class TwitterUpdateTask extends Task
{
	private $consumerKey = null;

	private $consumerKeySecret = null;

	private $oauthToken = null;

	private $oauthTokenSecret = null;

    private $message;

	public function setConsumerKey($str)
	{
		$this->consumerKey=$str;
	}

	public function setConsumerKeySecret($str)
	{
		$this->consumerKeySecret=$str;
	}

	public function setOauthToken($str)
	{
		$this->oauthToken=$str;
	}

	public function setOauthTokenSecret($str)
	{
		$this->oauthTokenSecret=$str;
	}

    public function setMessage($str)
	{
        $this->message=$str;
    }

    public function main()
	{
        // Connect to twitter
        $connection = new TwitterOAuth($this->consumerKey, $this->consumerKeySecret, $this->oauthToken, $this->oauthTokenSecret);

        // Pass the status message as a parameter
        $parameters = array('status' => $this->message);

        // Post the data to the API endpoint
        $status = $connection->post('statuses/update', $parameters);

        if (isset($status->error)){
            // Error: fail the build
            throw new BuildException($status->error);
        } else{
            $this->log ('Status posted to twitter');
        }

		// Sleep 10 seconds to prevent tweet being filtered by Twitter, which happens
		// when this TwitterUpdateTask is executed multiple times after each other
		sleep(10);
    }
}