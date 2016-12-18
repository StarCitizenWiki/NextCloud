<?php
class NextCloud {

	private static $_apiBase = 'ocs/v1.php/cloud';
	private static $_apiUrl = '';
	private static $_options = array(
		'Debug' => false,
		'WikiName' => '',
		'WikiContactMail' => '',
		'NextCloudUrl' => '',
		'NextCloudAdminUser' => '',
		'NextCloudAdminPassword' => '',
		'NextCloudUserGroup' => '',
		'NextCloudUserQuota' => '',
		'NextCloudUserPassword' => '',

	);

	private static $_debug = array(
		'name' => 'NextCloud',
		'path' => __DIR__.'/log/error.log',
	);

	private static function init()
	{
		global $wgNextCloudUrl, $wgNextCloudAdminUser, $wgNextCloudAdminPassword, $wgNextCloudUserGroup, $wgNextCloudUserQuota, $wgNextCloudWikiName, $wgNextCloudContactMail, $wgNextCloudDebug, $wgSitename;

		if (!file_exists(self::$_debug['path']))
		{
			if (!@touch(self::$_debug['path']))
			{
				throw new MWException( 'Could not create error.log' );
			}
		}

		if (
			empty($wgNextCloudAdminUser) ||
			empty($wgNextCloudAdminPassword) ||
			empty($wgNextCloudContactMail) ||
			filter_var($wgNextCloudUrl, FILTER_VALIDATE_URL) === false
		)
		{
			return false;
		}

		self::$_options['WikiName'] = $wgNextCloudWikiName;

		if (empty(self::$_options['WikiName']) && !empty($wgSitename))
		{
			self::$_options['WikiName'] = $wgSitename;
		}

		if (substr($wgNextCloudUrl, -1) !== '/')
		{
			$wgNextCloudUrl .= '/';
		}

		self::$_apiUrl = $wgNextCloudUrl.self::$_apiBase;

		self::$_options['Debug'] = $wgNextCloudDebug;
		self::$_options['WikiContactMail'] = $wgNextCloudContactMail;
		self::$_options['NextCloudUrl'] = $wgNextCloudUrl;
		self::$_options['NextCloudAdminUser'] = $wgNextCloudAdminUser;
		self::$_options['NextCloudAdminPassword'] = $wgNextCloudAdminPassword;
		self::$_options['NextCloudUserGroup'] = $wgNextCloudUserGroup;
		self::$_options['NextCloudUserQuota'] = $wgNextCloudUserQuota;
		self::$_options['NextCloudUserPassword'] = md5(uniqid());

		return true;
	}

	public static function addUser(User $user)
	{
		if (self::init() === false)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug-init-failed')->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}

		$curl = new CurlWrapper(
		    self::$_apiUrl.'/users',
		    array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
		        CURLOPT_POSTFIELDS => array(
		            'userid' => $user->getName(),
		            'password' => self::$_options['NextCloudUserPassword']
		        ),
				CURLOPT_HTTPHEADER => array("OCS-APIRequest: true")
		    )
		);
		$response = $curl->getResponse();

		if ($response['code'] == 100)
		{
			if (self::setUserSettings($user) == false)
			{
				if (self::$_options['Debug'])
				{
					$errorMessage = wfMessage('nextcloud-debug-user-creation-failed', self::_getResponseText($response['message']))->plain();
					wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
				}
				return self::_deleteUser($user);
			}


			$mailHeader = wfMessage('nextcloud-mail-head-register', self::$_options['WikiName'])->plain();
			$mailBody = wfMessage(
							'nextcloud-mail-body-register',
							$user->getName(),
							self::$_options['NextCloudUrl'],
							self::$_options['NextCloudUserPassword'],
							self::$_options['WikiName'],
							self::$_options['WikiContactMail']
						)->plain();
			$user->sendMail($mailHeader, $mailBody, self::$_options['WikiContactMail']);

			return true;
		}

		if (self::$_options['Debug'])
		{
			$errorMessage = wfMessage('nextcloud-debug', 'User Creation', self::_getResponseText($response['message']))->plain();
			wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
		}
		return false;

	}


	private static function _deleteUser(User $user)
	{
		$curl = new CurlWrapper(
		    self::$_apiUrl.'/users/'.$user->getName(),
		    array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
				CURLOPT_CUSTOMREQUEST => 'DELETE',
				CURLOPT_HTTPHEADER => array("OCS-APIRequest: true")				
		    )
		);
		$response = $curl->getResponse();

		if ($response['code'] == 101)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug-user-deletion-failed')->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}


		return true;
	}


	private static function setUserSettings(User $user)
	{
		$curl = new CurlWrapper(
		    self::$_apiUrl.'/users/'.$user->getName(),
		    array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
		        CURLOPT_CUSTOMREQUEST => 'PUT',
		        CURLOPT_POSTFIELDS => http_build_query(array(
		            'key' => 'email',
		            'value' => $user->getEmail(),
		        )),
				CURLOPT_HTTPHEADER => array("OCS-APIRequest: true")
		    )
		);
		$response = $curl->getResponse();

		if ($response['code'] != 100)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug', 'Setting User-Email', self::_getResponseText($response['message']))->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}


		if (!empty(self::$_options['NextCloudUserQuota']))
		{
			$curl = new CurlWrapper(
			    self::$_apiUrl.'/users/'.$user->getName(),
			    array(
					CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
					CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
			        CURLOPT_CUSTOMREQUEST => 'PUT',
			        CURLOPT_POSTFIELDS => http_build_query(array(
			            'key' => 'quota',
			            'value' => self::$_options['NextCloudUserQuota'],
			        )),
					CURLOPT_HTTPHEADER => array("OCS-APIRequest: true"),
			    )
			);
			$response = $curl->getResponse();

			if ($response['code'] != 100)
			{
				if (self::$_options['Debug'])
				{
					$errorMessage = wfMessage('nextcloud-debug', 'Setting User Quota', self::_getResponseText($response['message']))->plain();
					wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
				}
				return false;
			}
		}


		if (!empty(self::$_options['NextCloudUserGroup']))
		{
			$curl = new CurlWrapper(
			    self::$_apiUrl.'/users/'.$user->getName().'/groups',
			    array(
					CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
					CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
			        CURLOPT_POSTFIELDS => array(
			            'groupid' => self::$_options['NextCloudUserGroup']
			        ),
					CURLOPT_HTTPHEADER => array("OCS-APIRequest: true")
			    )
			);
			$response = $curl->getResponse();

			if ($response['code'] != 100)
			{
				if (self::$_options['Debug'])
				{
					$errorMessage = wfMessage('nextcloud-debug', 'Setting User Group', self::_getResponseText($response['message']))->plain();
					wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
				}
				return false;
			}
		}


		return true;
	}

	private static function _getResponseText($xml)
	{
		libxml_use_internal_errors(true);
		$responseArray = (array) simplexml_load_string($xml);
		if (!isset($responseArray['meta']))
		{
			return false;
		}

		$response = '';
		foreach ($responseArray['meta'] as $key => $value) {
			$response .= $key.': '.$value.' - ';
		}
		$response = rtrim($response, ' - ');

		return $response;
	}

	public static function disableUser(Block $block, User $blocker)
	{
		if (self::init() === false)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug-init-failed')->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}

		$user = $block->getTarget();

		$curl = new CurlWrapper(
			self::$_apiUrl.'/users/'.$user->getName(),
			array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => http_build_query(array(
					'key' => 'password',
					'password' => self::$_options['NextCloudUserPassword']
				)),
				CURLOPT_HTTPHEADER => array("OCS-APIRequest: true"),
			)
		);
		$response = $curl->getResponse();

		if ($response['code'] != 100)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug', 'Disabling User Password', self::_getResponseText($response['message']))->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}


		$curl = new CurlWrapper(
			self::$_apiUrl.'/users/'.$user->getName(),
			array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => self::$_options['NextCloudAdminUser'].':'.self::$_options['NextCloudAdminPassword'],
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => http_build_query(array(
					'key' => 'email',
					'value' => self::$_options['WikiContactMail'],
				)),
				CURLOPT_HTTPHEADER => array("OCS-APIRequest: true"),
			)
		);
		$response = $curl->getResponse();

		if ($response['code'] != 100)
		{
			if (self::$_options['Debug'])
			{
				$errorMessage = wfMessage('nextcloud-debug', 'Disabling User-Mail', self::_getResponseText($response['message']))->plain();
				wfErrorLog(date('Y-m-d H:i:s', time()).' - '.$errorMessage , self::$_debug['path']);
			}
			return false;
		}


		$mailHeader = wfMessage('nextcloud-mail-head-deactivation', self::$_options['WikiName'])->plain();
		$mailBody = wfMessage(
						'nextcloud-mail-body-deactivation',
						$user->getName(),
						self::$_options['WikiName'],
						self::$_options['WikiContactMail']
					)->plain();
		$user->sendMail($mailHeader, $mailBody, self::$_options['WikiContactMail']);


		return true;
	}

}

class CurlWrapper
{
    /** @var resource cURL handle */
    private $ch;

    /** @var mixed The response */
    private $response = false;

    private $responseCode = 0;

    /**
     * @param string $url
     * @param array  $options
     */
    public function __construct($url, array $options = array())
    {
        $this->ch = curl_init($url);

        foreach ($options as $key => $val) {
            curl_setopt($this->ch, $key, $val);
        }

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Get the response
     * @return string
     * @throws \RuntimeException On cURL error
     */
    public function getResponse()
    {
        $response = curl_exec($this->ch);
        $error    = curl_error($this->ch);
        $errno    = curl_errno($this->ch);

        if (0 !== $errno) {
			return array('code' => $errno, 'message' => $error);
        }

		$this->response = $response;
		$this->responseCode = self::getResponseCode($response);

        return array('code' => $this->responseCode, 'message' => $this->response);
    }

	private static function getResponseCode($xml)
	{
		if (empty($xml))
		{
			return false;
		}

		$p = xml_parser_create();
		xml_parse_into_struct($p, $xml, $content, $index);
		xml_parser_free($p);

		$code = isset($content[$index['STATUSCODE'][0]]['value']) ? $content[$index['STATUSCODE'][0]]['value'] : -1;

		return $code;
	}

    /**
     * Let echo out the response
     * @return string
     */
    public function __toString()
    {
        return $this->getResponse();
    }
}
