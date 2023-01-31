<?php

namespace Sirv\Magento2\Model\Api;

/**
 * Sirv api
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2022 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class Sirv
{
    /**
     * Sirv API base url
     *
     */
    const API_BASE_URL = 'https://api.sirv.com';

    /**
     * Default client id
     *
     */
    const CLIENT_ID = 'LZswKxl7FAkG2VWWMJmndHzbNmR';

    /**
     * Default client secret
     *
     */
    const CLIENT_SECRET = 'uN/l/RbDzL+xLS68e7QdeX3XnC98zGgFVzbIBvJ5RVtzfnN5YXNPwRWpJtNd6sglGp98VtOC3mHBMdz2Z8TnHA==';

    /**
     * User client id
     *
     * @var string
     */
    protected $clientId = '';

    /**
     * User client secret
     *
     * @var string
     */
    protected $clientSecret = '';

    /**
     * User token
     *
     * @var string
     */
    protected $token = '';

    /**
     * Token expire time
     *
     * @var integer
     */
    protected $tokenExpireTime = 0;

    /**
     * Callback for caching token
     *
     * @var callback
     */
    protected $cacheTokenCallback = null;

    /**
     * User E-mail
     *
     * @var string
     */
    protected $email = '';

    /**
     * User password
     *
     * @var string
     */
    protected $password = '';

    /**
     * Selected account
     *
     * @var string
     */
    protected $account = '';

    /**
     * List of account aliases and JSON Web Tokens
     *
     * @var array|null
     */
    protected $jwts = null;

    /**
     * S3 bucket
     *
     * @var string
     */
    protected $bucket = '';

    /**
     * S3 key
     *
     * @var string
     */
    protected $key = '';

    /**
     * S3 secret
     *
     * @var string
     */
    protected $secret = '';

    /**
     * cURL resource
     *
     * @var resource
     */
    protected static $curlHandle = null;

    /**
     * Information about the last transfer
     *
     * @var array
     */
    protected $curlInfo = [];

    /**
     * The last transfer response code
     *
     * @var integer
     */
    protected $responseCode = 0;

    /**
     * Error message for the last transfer
     *
     * @var string
     */
    protected $errorMsg = '';

    /**
     * Json error messages
     *
     * @var array
     */
    protected $jsonErrorMessages = [
        'JSON_ERROR_NONE' => 'No error',
        'JSON_ERROR_DEPTH' => 'The maximum stack depth has been exceeded',
        'JSON_ERROR_STATE_MISMATCH' => 'Invalid or malformed JSON',
        'JSON_ERROR_CTRL_CHAR' => 'Control character error, possibly incorrectly encoded',
        'JSON_ERROR_SYNTAX' => 'Syntax error',
        'JSON_ERROR_UTF8' => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        'JSON_ERROR_RECURSION' => 'One or more recursive references in the value to be encoded',
        'JSON_ERROR_INF_OR_NAN' => 'One or more NAN or INF values in the value to be encoded',
        'JSON_ERROR_UNSUPPORTED_TYPE' => 'A value of a type that cannot be encoded was given',
        'JSON_ERROR_INVALID_PROPERTY_NAME' => 'A property name that cannot be encoded was given',
        'JSON_ERROR_UTF16' => 'Malformed UTF-16 characters, possibly incorrectly encoded',
    ];

    /**
     * Callback for rate limit exceeded case
     *
     * @var callback
     */
    protected $rateLimitExceededCallback = null;

    /**
     * The last transfer response headers
     *
     * @var array
     */
    protected $responseHeaders = [];

    /**
     * Rate limit data
     *
     * @var array
     */
    protected $rateLimitData = [
        'requests' => [
            'HEAD' => [],
            'GET' => [],
            'PUT' => [],
            'DELETE' => [],
            'POST' => [],
        ],
        'types' => [],
    ];

    /**
     * Module version
     *
     * @var string
     */
    protected $moduleVersion = '';

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->token = '';
    }

    /**
     * Init method
     *
     * @param array $data
     * @return $this
     */
    public function init(array $data)
    {
        foreach ($data as $index => $value) {
            $this->{$index} = $value;
        }

        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }

        return $this;
    }

    /**
     * Get API access token
     *
     * @return string|bool
     */
    public function getToken()
    {
        return (empty($this->token) || ($this->tokenExpireTime <= time())) ? $this->getNewToken() : $this->token;
    }

    /**
     * Get API access new token
     *
     * @return string|bool
     */
    protected function getNewToken()
    {
        $clientId = empty($this->clientId) ? self::CLIENT_ID : $this->clientId;
        $clientSecret = empty($this->clientSecret) ? self::CLIENT_SECRET : $this->clientSecret;

        $this->token = '';
        $this->tokenExpireTime = 0;

        $result = $this->sendRequest(
            'v2/token',
            'POST',
            [
                'clientId' => $clientId,
                'clientSecret' => $clientSecret
            ]
        );

        if ($this->responseCode == 200) {
            if ($result) {
                $this->token = empty($result->token) ? '' : $result->token;
                $this->tokenExpireTime = empty($result->expiresIn) ? 0 : (time() + $result->expiresIn);

                if ($this->cacheTokenCallback && $this->token && $this->tokenExpireTime) {
                    call_user_func($this->cacheTokenCallback, $this->token, $this->tokenExpireTime);
                }
            }
        } elseif ($this->responseCode == 401) {
            $this->clientId = '';
            $this->clientSecret = '';
            if (empty($this->errorMsg)) {
                $this->errorMsg = 'Unauthorized';
            }
        }

        return empty($this->token) ? false : $this->token;
    }

    /**
     * Get list of user accounts
     *
     * @return array
     */
    public function getUsersList()
    {
        return $this->setupWebTokens() ? array_keys($this->jwts) : [];
    }

    /**
     * Setup JSON Web Tokens
     *
     * @return bool
     */
    protected function setupWebTokens()
    {
        if ($this->jwts === null) {
            if (empty($this->email) || empty($this->password) || !$this->getToken()) {
                return false;
            }

            //NOTE: this call requires user email/password to authenticate, not the JWT access token
$result = $this->sendRequest(
    'v2/user/accounts',
    'POST',
    [
        'email' => $this->email,
        'password' => $this->password,
    ]
);

            if ($this->responseCode != 200 || !is_array($result) || empty($result)) {
                return false;
            }

            $this->jwts = [];
            foreach ($result as $account) {
                $this->jwts[$account->alias] = $account->token;
            }
        }

        return true;
    }

    /**
     * Get client credentials
     *
     * @return array|bool
     */
    public function getClientCredentials()
    {
        $result = false;
        if (empty($this->clientId) || empty($this->clientSecret)) {
            if ($this->setupClientCredentials()) {
                $result = true;
            }
        } else {
            $result = true;
        }

        if ($result) {
            $result = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'token' => $this->token,
                'token_expire_time' => $this->tokenExpireTime,
            ];
        }

        return $result;
    }

    /**
     * Setup client credentials
     *
     * @return bool
     */
    protected function setupClientCredentials()
    {
        if ($this->account && $this->setupWebTokens() && isset($this->jwts[$this->account])) {
            //NOTE: get REST client credentials
            //      this call requires special JWT access token sent in GET /user/accounts response
            $result = $this->sendRequest(
                'v2/rest/credentials',
                'GET',
                [],
                $this->jwts[$this->account]//JWT access token
            );

            if ($result && $this->responseCode == 200) {
                $this->clientId = empty($result->clientId) ? '' : $result->clientId;
                $this->clientSecret = empty($result->clientSecret) ? '' : $result->clientSecret;
                if ($this->clientId && $this->clientSecret) {
                    $this->getNewToken();
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get S3 credentials
     *
     * @return array|bool
     */
    public function getS3Credentials($email = '')
    {
        $result = false;
        if (empty($this->key) || empty($this->secret) || empty($this->bucket)) {
            if ($this->setupS3Credentials($email)) {
                $result = true;
            }
        } else {
            $result = true;
        }

        if ($result) {
            $result = [
                'key' => $this->key,
                'secret' => $this->secret,
                'bucket' => $this->bucket,
            ];
        }

        return $result;
    }

    /**
     * Setup S3 credentials
     *
     * @param string $email
     * @return bool
     */
    protected function setupS3Credentials($email = '')
    {
        if ($this->getToken()) {
            //NOTE: get list of account users
            $users = $this->sendRequest(
                'v2/account/users',
                'GET'
            );

            if ($this->responseCode == 200 && is_array($users)) {
                $email = empty($email) ? $this->email : $email;
                $userInfo = null;

                foreach ($users as $user) {
                    //NOTE: get user info
                    $result = $this->sendRequest(
                        'v2/user?userId=' . $user->userId,
                        'GET'
                    );

                    if ($this->responseCode == 200 && $result->email == $email) {
                        $userInfo = $result;
                        break;
                    }
                }

                if ($userInfo) {
                    $accountInfo = $this->getAccountInfo();
                    if ($accountInfo) {
                        $this->bucket = empty($accountInfo->alias) ? '' : $accountInfo->alias;
                        $this->key = empty($userInfo->email) ? '' : $userInfo->email;
                        $this->secret = empty($userInfo->s3Secret) ? '' : $userInfo->s3Secret;
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get account info
     *
     * @return stdClass|bool
     */
    public function getAccountInfo()
    {
        static $data = null;

        if ($data === null) {
            $data = false;

            if ($this->getToken()) {
                $result = $this->sendRequest(
                    'v2/account',
                    'GET'
                );

                if ($this->responseCode == 200) {
                    $data = $result;
                }
            }
        }

        return $data;
    }

    /**
     * Update account info
     *
     * @param array $data
     * @return bool
     */
    public function updateAccountInfo($data)
    {
        if (!$this->getToken()) {
            return false;
        }

        $this->sendRequest(
            'v2/account',
            'POST',
            $data
        );

        return $this->responseCode == 200;
    }

    /**
     * Register new account
     *
     * @param string $email
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @param string $alias
     * @return bool
     */
    public function registerAccount($email, $password, $firstName, $lastName, $alias)
    {
        $registered = false;

        if ($this->getToken()) {
            $this->sendRequest(
                'v2/account',
                'PUT',
                [
                    'email' => $email,
                    'password' => $password,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'alias' => $alias
                ]
            );

            if ($this->responseCode == 200) {
                $registered = true;
            }
        }

        return $registered;
    }

    /**
     * Enable JS and HTML serving
     *
     * @param bool $allow
     * @return bool
     */
    public function enableJsAndHtmlServing($allow = true)
    {
        if (!$this->getToken()) {
            return false;
        }

        $this->sendRequest(
            'v2/account/dangerousContentTypes',
            'POST',
            ['allow' => $allow]
        );

        return $this->responseCode == 200;
    }

    /**
     * Get current account storage info
     *
     * @return stdClass|bool
     */
    public function getStorageInfo()
    {
        static $storageInfo = null;

        if ($storageInfo === null) {
            $storageInfo = false;

            if ($this->getToken()) {
                $result = $this->sendRequest(
                    'v2/account/storage',
                    'GET'
                );

                if ($this->responseCode == 200) {
                    $storageInfo = $result;
                }
            }
        }

        return $storageInfo;
    }

    /**
     * Get account billing plan
     *
     * @return stdClass|bool
     */
    public function getBillingPlanInfo()
    {
        static $billingPlanInfo = null;

        if ($billingPlanInfo === null) {
            $billingPlanInfo = false;

            if ($this->getToken()) {
                $result = $this->sendRequest(
                    'v2/billing/plan',
                    'GET'
                );

                if ($this->responseCode == 200) {
                    $billingPlanInfo = $result;
                }
            }
        }

        return $billingPlanInfo;
    }

    /**
     * Get daily HTTP statistics
     *
     * @param string $from
     * @param string $to
     * @return stdClass|bool
     */
    public function getHttpStats($from, $to)
    {
        $data = false;

        if ($this->getToken()) {
            $result = $this->sendRequest(
                'v2/stats/http?from=' . $from . '&to=' . $to,
                'GET'
            );
            if ($this->responseCode == 200) {
                $data = $result;
            }
        }

        return $data;
    }

    /**
     * Download files from HTTP(S) resource and save it
     *
     * @param array $images
     * @return array|bool
     */
    public function fetchImages($images)
    {
        if ($this->getToken()) {
            $result = $this->sendRequest(
                'v2/files/fetch',
                'POST',
                $images
            );

            //NOTE: 413 Request Entity Too Large
            if ($this->responseCode == 200) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Upload file
     *
     * @param string $filename
     * @param string $fsPath
     * @param string $contents
     * @return bool
     */
    public function uploadFile($filename, $fsPath, $contents = null)
    {
        if ($this->getToken()) {
            $filename = '/' . str_replace('%2F', '/', rawurlencode(ltrim($filename, '/')));
            if ($contents === null) {
                $contents = file_get_contents($fsPath);
            }
            $this->sendRequest(
                'v2/files/upload?filename=' . $filename,
                'POST',
                ['upload' => $contents]
            );
        }

        return $this->responseCode == 200;
    }

    /**
     * Delete a single file or an empty directory
     *
     * @param string $filename
     * @return bool
     */
    public function deleteFile($filename)
    {
        if ($this->getToken()) {
            $filename = '/' . str_replace('%2F', '/', rawurlencode(ltrim($filename, '/')));
            $this->sendRequest(
                'v2/files/delete?filename=' . $filename,
                'POST'
            );
        }

        return $this->responseCode == 200;
    }

    /**
     * Config account HTTP/S3 fetching
     *
     * @param strin $url
     * @param bool $enabled
     * @param bool $minify
     * @return bool
     */
    public function configFetching($url, $enabled, $minify)
    {
        if ($enabled) {
            $data = [
                'minify' => [
                    'enabled' => $minify
                ],
                'fetching' => [
                    'enabled' => true,
                    'type' => 'http',
                    'http' => [
                        'url' => $url
                    ]
                ],
            ];
        } else {
            $data = [
                'minify' => [
                    'enabled' => false
                ],
                'fetching' => [
                    'enabled' => false
                ],
            ];
        }

        return $this->updateAccountInfo($data);
    }

    /**
     * Config CDN
     *
     * @param bool $status
     * @param string $alias
     * @return bool
     */
    public function configCDN($status, $alias = '')
    {
        if (empty($alias)) {
            $alias = $this->account;
        }

        $data = [
            'aliases' => [
                $alias => [
                    'cdn' => $status
                ]
            ]
        ];

        return $this->updateAccountInfo($data);
    }

    /**
     * Get file stats (Unix info)
     *
     * @param string $filename
     * @return stdClass|bool
     */
    public function getFileStats($filename)
    {
        $stats = false;

        if ($this->getToken()) {
            $stats = $this->sendRequest(
                'v2/files/stat?filename=' . urlencode($filename),
                'GET'
            );
        }

        return $stats;
    }

    /**
     * Set file description
     *
     * @param string $filename
     * @param string $description
     * @return bool
     */
    public function setFileDescription($filename, $description)
    {
        if ($this->getToken()) {
            $this->sendRequest(
                'v2/files/meta/description?filename=' . urlencode($filename),
                'POST',
                ['description' => $description]
            );

            if ($this->responseCode == 200) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get API limits
     * Method to check the allowed number of API requests and the number of requests used in the past 60 minutes
     *
     * @return stdClass|bool
     */
    public function getAPILimits()
    {
        $data = false;

        if ($this->getToken()) {
            $data = $this->sendRequest(
                'v2/account/limits',
                'GET'
            );

            if ($this->responseCode != 200) {
                $data = false;
            }
        }

        return $data;
    }

    /**
     * Get pofiles
     *
     * @return array
     */
    public function getProfiles()
    {
        static $profiles = null;

        if ($profiles !== null) {
            return $profiles;
        }

        $profiles = [];

        if ($this->getToken()) {
            $data = $this->sendRequest(
                'v2/files/readdir?dirname=/Profiles',
                'GET'
            );

            if ($this->responseCode == 200) {
                $contents = is_object($data) && is_array($data->contents) ? $data->contents : [];
                foreach ($contents as $content) {
                    if (preg_match('#\.profile$#i', $content->filename)) {
                        $profiles[] = preg_replace('#\.profile$#i', '', $content->filename);
                    }
                }
            }
        }

        return $profiles;
    }

    /**
     * Read folder contents
     *
     * @param string $dirname
     * @return array
     */
    public function getFolderContents($dirname)
    {
        $contents = [];

        if ($this->getToken()) {
            $dirname = '/' . str_replace('%2F', '/', rawurlencode(trim($dirname, '/')));
            $continuation = '';
            do {
                $data = $this->sendRequest(
                    'v2/files/readdir?dirname=' . $dirname .
                        (empty($continuation) ? '' : '&continuation=' . rawurlencode($continuation)),
                    'GET'
                );

                if ($this->responseCode == 200 && is_object($data) && is_array($data->contents)) {
                    $contents = array_merge($contents, $data->contents);
                    $continuation = $data->continuation ?? '';
                } else {
                    $continuation = '';
                }
            } while (!empty($continuation));
        }

        return $contents;
    }

    /**
     * Get folder options
     *
     * @param string $filename
     * @param bool $withInherited
     * @return stdClass|bool
     */
    public function getFolderOptions($filename, $withInherited = false)
    {
        if ($this->getToken()) {
            $filename = '/' . str_replace('%2F', '/', rawurlencode(ltrim($filename, '/')));
            $withInherited = $withInherited ? 'true' : 'false';
            $result = $this->sendRequest(
                'v2/files/options?filename=' . $filename . '&withInherited=' . $withInherited,
                'GET'
            );

            if ($this->responseCode == 200) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Set folder options
     *
     * @param string $filename
     * @param array $options
     * @return bool
     */
    public function setFolderOptions($filename, $options)
    {
        if ($this->getToken()) {
            $filename = '/' . str_replace('%2F', '/', rawurlencode(ltrim($filename, '/')));
            $this->sendRequest(
                'v2/files/options?filename=' . $filename,
                'POST',
                $options
            );

            if ($this->responseCode == 200) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send request
     *
     * @param string $resource
     * @param string $method
     * @param array $data
     * @param string $token
     * @return mixed
     */
    protected function sendRequest($resource, $method = 'POST', $data = [], $token = '')
    {
        $pos = strpos($resource, '?');
        $path = ($pos === false ? $resource : substr($resource, 0, $pos));

        if (isset($this->rateLimitData['requests'][$method][$path])) {
            $type = $this->rateLimitData['requests'][$method][$path];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            if ($rateLimitExpireTime >= time()) {
                $this->responseCode = 429;
                $this->errorMsg = 'Rate limit exceeded. Too many requests. Retry after ' .
                    date('Y-m-d\TH:i:s.v\Z (e)', $rateLimitExpireTime) . '. ' .
                    'Please visit https://sirv.com/help/resources/api/#API_limits';
                return false;
            }
        }

        $token = empty($token) ? $this->token : $token;

        $headers = [];
        if ($token) {
            $headers[] = 'authorization: Bearer ' . $token;
        }

        if (isset($data['upload'])) {
            $headers[] = 'content-type: application/octet-stream';
            $data = $data['upload'];
        } else {
            $headers[] = 'content-type: application/json';
            $data = json_encode($data);
        }

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => self::API_BASE_URL . '/' . $resource,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HEADERFUNCTION => [$this, 'headerDataHandler'],
                CURLOPT_USERAGENT => 'Sirv/Magento 2/' . $this->moduleVersion,
            ]
        );

        $this->responseHeaders = [];

        $result = curl_exec(self::$curlHandle);

        $this->responseCode = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);
        //$this->curlInfo = curl_getinfo(self::$curlHandle);

        $this->errorMsg = '';
        if ($result === false) {
            $this->errorMsg = curl_error(self::$curlHandle);
        } elseif ($this->responseCode == 504) {
            $this->errorMsg = '504 Gateway Time-out';
        } else {
            $result = empty($result) ? '' : json_decode($result);
            if ($result === null) {
                $this->errorMsg = function_exists('json_last_error_msg') ? json_last_error_msg() : $this->getJsonLastErrorMsg();
            } elseif (is_object($result) && isset($result->error)) {
                $this->errorMsg = $result->error;
                if (isset($result->message)) {
                    $this->errorMsg .= ': ' . $result->message;
                }
            }
        }

        if ($this->responseCode == 429) {
            if (isset($this->responseHeaders['x-ratelimit-type'])) {
                $type = $this->responseHeaders['x-ratelimit-type'];
                $this->rateLimitData['requests'][$method][$path] = $type;
                $timestamp = isset($this->responseHeaders['x-ratelimit-reset']) ? (int)$this->responseHeaders['x-ratelimit-reset'] : 0;
                $this->rateLimitData['types'][$type] = $timestamp;
                $this->errorMsg = 'Rate limit exceeded. Too many requests. Retry after ' .
                    date('Y-m-d\TH:i:s.v\Z (e)', $timestamp) . '. ' .
                    'Please visit https://sirv.com/help/resources/api/#API_limits';
            }

            if ($this->rateLimitExceededCallback) {
                call_user_func($this->rateLimitExceededCallback, $this->rateLimitData);
            }
        }

        return $result;
    }

    /**
     * Method for handling header data
     *
     * @param resource $curlHandle
     * @param string $header
     * @return int
     */
    public function headerDataHandler($curlHandle, $header)
    {
        $length = strlen($header);
        $header = explode(':', $header, 2);

        if (count($header) < 2) {
            return $length;
        }

        $name = strtolower(trim($header[0]));

        if (strpos($name, 'x-ratelimit-') === 0) {
            $this->responseHeaders[$name] = trim($header[1]);
        }

        return $length;
    }

    /**
     * Get json last error message
     *
     * @return string
     */
    protected function getJsonLastErrorMsg()
    {
        $message = 'Unknown error';
        $error = json_last_error();
        foreach ($this->jsonErrorMessages as $const => $msg) {
            if (defined($const)) {
                if ($error === constant($const)) {
                    $message = $msg;
                    break;
                }
            }
        }

        return $message;
    }

    /**
     * Get last error message
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Get last response code
     *
     * @return string
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Check if rate limit exceeded
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function isRateLimitExceeded($method, $path)
    {
        $isRateLimitExceeded = false;

        if (isset($this->rateLimitData['requests'][$method][$path])) {
            $type = $this->rateLimitData['requests'][$method][$path];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            $isRateLimitExceeded = ($rateLimitExpireTime >= time());
        }

        return $isRateLimitExceeded;
    }

    /**
     * Get rate limit expire time
     *
     * @param string $method
     * @param string $path
     * @return int
     */
    public function getRateLimitExpireTime($method, $path)
    {
        $rateLimitExpireTime = 0;

        if (isset($this->rateLimitData['requests'][$method][$path])) {
            $type = $this->rateLimitData['requests'][$method][$path];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            if ($rateLimitExpireTime < time()) {
                $rateLimitExpireTime = 0;
            }
        }

        return $rateLimitExpireTime;
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        if (isset(self::$curlHandle)) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
        }
    }
}
