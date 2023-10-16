<?php

namespace Sirv\Magento2\Model\Api;

/**
 * S3 api
 *
 * @author    Sirv Limited <support@sirv.com>
 * @copyright Copyright (c) 2018-2023 Sirv Limited <support@sirv.com>. All rights reserved
 * @license   https://sirv.com/
 * @link      https://sirv.com/integration/magento/
 */
class S3
{
    /**
     * Endpoint
     *
     * @var string
     */
    protected $host = 's3.sirv.com';

    /**
     * Bucket
     *
     * @var string
     */
    protected $bucket = '';

    /**
     * Access Key
     *
     * @var string
     */
    protected $key = '';

    /**
     * Secret key
     *
     * @var string
     */
    protected $secret = '';

    /**
     * Date
     *
     * @var string
     */
    protected $date;

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
    protected $curlInfo;

    /**
     * The last transfer response code
     *
     * @var integer
     */
    protected $responseCode = 0;

    /**
     * The last transfer response headers
     *
     * @var array
     */
    protected $responseHeaders = [];

    /**
     * Error message for the last transfer
     *
     * @var string
     */
    protected $errorMsg = '';

    /**
     * Is the connection available
     *
     * @var bool
     */
    protected $connected = null;

    /**
     * Callback for rate limit exceeded case
     *
     * @var callback
     */
    protected $rateLimitExceededCallback = null;

    /**
     * Rate limit data
     *
     * @var array
     */
    protected $rateLimitData = [
        'methods' => [],
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
     * @param array $params
     * @return void
     */
    public function __construct(array $params = [])
    {
        if (empty($params)) {
            $this->connected = false;
        }

        foreach ($params as $name => $value) {
            $this->{$name} = $value;
        }

        $this->date = gmdate('D, d M Y H:i:s T');

        if (!isset(self::$curlHandle)) {
            self::$curlHandle = curl_init();
        }
    }

    /**
     * Check credentials
     *
     * @return bool
     */
    public function checkCredentials()
    {
        $request = [
            'method' => __FUNCTION__,
            'verb' => 'HEAD',
            'resource' => '/' . $this->bucket
        ];
        $result = $this->sendRequest($request);

        $this->connected = ($this->responseCode == 200);

        return $this->connected;
    }

    /**
     * Get buckets list
     *
     * @return array
     */
    public function listBuckets()
    {
        static $buckets = null;

        if ($buckets === null) {
            $buckets = [];

            if ($this->connected === null) {
                $this->checkCredentials();
            }

            if ($this->connected) {
                $request = [
                    'method' => __FUNCTION__,
                    'verb' => 'GET',
                    'resource' => '/'
                ];
                $result = $this->sendRequest($request);

                if ($this->responseCode == 200) {
                    $useErrors = libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($result);
                    libxml_use_internal_errors($useErrors);
                    if ($xml !== false && isset($xml->Buckets->Bucket)) {
                        foreach ($xml->Buckets as $item) {
                            $buckets[] = (string)$item->Bucket->Name;
                        }
                    }
                }
            }
        }

        return $buckets;
    }

    /**
     * Get objects list
     *
     * @param string $path
     * @return array
     */
    public function getObjectsList($path = '/')
    {
        $list = [];

        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if ($this->connected) {
            if ($path != '/') {
                $path = '/' . trim($path, '/') . '/';
            }

            $request = [
                'method' => __FUNCTION__,
                'verb' => 'GET',
                'resource' => '/' . $this->bucket . $path
            ];
            $result = $this->sendRequest($request);

            if ($this->responseCode == 200) {
                $useErrors = libxml_use_internal_errors(true);
                $xml = simplexml_load_string($result);
                libxml_use_internal_errors($useErrors);
                if ($xml !== false) {
                    if (isset($xml->Contents)) {
                        foreach ($xml->Contents as $item) {
                            $list[] = $path . (string)$item->Key;
                        }
                    }

                    if (isset($xml->CommonPrefixes)) {
                        foreach ($xml->CommonPrefixes as $item) {
                            $list[] = $path . (string)$item->Prefix;
                        }
                    }

                    sort($list, SORT_STRING);
                }
            }
        }

        return $list;
    }

    /**
     * Get pofiles
     *
     * @return array
     */
    public function getProfiles()
    {
        static $profiles = null;

        if ($profiles === null) {
            $profiles = [];

            if ($this->connected === null) {
                $this->checkCredentials();
            }

            if ($this->connected) {

                $request = [
                    'method' => __FUNCTION__,
                    'verb' => 'GET',
                    'bucket' => $this->bucket,
                    'resource' => '/?prefix=Profiles/',
                ];
                $result = $this->sendRequest($request);

                if ($this->responseCode == 200) {
                    $useErrors = libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($result);
                    libxml_use_internal_errors($useErrors);
                    if ($xml !== false && isset($xml->Contents)) {
                        foreach ($xml->Contents as $contents) {
                            $key = (string)$contents->Key;
                            if (preg_match('#^Profiles/([^/]+)\.profile$#', $key, $matches)) {
                                $profiles[] = $matches[1];
                            }
                        }
                    }
                }
            }
        }

        return $profiles;
    }

    /**
     * Upload file
     *
     * @param string $sirvPath
     * @param string $fsPath
     * @param bool $webAccessible
     * @param array $headers
     * @return bool
     */
    public function uploadFile($sirvPath, $fsPath, $webAccessible = false, $headers = null)
    {
        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if (!$this->connected) {
            return false;
        }

        $sirvPath = str_replace('%2F', '/', rawurlencode($sirvPath));

        $request = [
            'method' => __FUNCTION__,
            'verb' => 'PUT',
            'bucket' => $this->bucket,
            'resource' => "{$sirvPath}",
            'content-md5' => $this->encodeWithBase64(md5_file($fsPath))
        ];

        $fh = fopen($fsPath, 'r');

        $curlOptions = [
            'CURLOPT_PUT' => true,
            'CURLOPT_INFILE' => $fh,
            'CURLOPT_INFILESIZE' => filesize($fsPath),
            'CURLOPT_CUSTOMREQUEST' => 'PUT'
        ];

        if ($headers === null) {
            $headers = [];
        }

        $headers['Content-MD5'] = $request['content-md5'];

        if ($webAccessible === true && !isset($headers['x-amz-acl'])) {
            $headers['x-amz-acl'] = 'public-read';
        }

        if (!isset($headers['Content-Type'])) {
            $ext = pathinfo($fsPath, PATHINFO_EXTENSION);
            $headers['Content-Type'] = isset($this->mimeTypes[$ext]) ? $this->mimeTypes[$ext] : 'application/octet-stream';
        }

        $request['content-type'] = $headers['Content-Type'];

        $result = $this->sendRequest($request, $headers, $curlOptions);

        fclose($fh);

        return $this->responseCode == 200;
    }

    /**
     * Download file
     *
     * @param string $sirvPath
     * @param string $fsPath
     * @return bool
     */
    public function downloadFile($sirvPath, $fsPath)
    {
        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if (!$this->connected) {
            return false;
        }

        $sirvPath = str_replace('%2F', '/', rawurlencode($sirvPath));

        $request = [
            'method' => __FUNCTION__,
            'verb' => 'GET',
            'bucket' => $this->bucket,
            'resource' => "{$sirvPath}"
        ];

        $fh = fopen($fsPath, 'w');
        $curlOptions = [
            'CURLOPT_FILE' => $fh
        ];

        $this->sendRequest($request, [], $curlOptions);

        fclose($fh);

        return $this->responseCode == 200;
    }

    /**
     * Check if object exists
     *
     * @param string $sirvPath
     * @return bool
     */
    public function doesObjectExist($sirvPath)
    {
        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if (!$this->connected) {
            return false;
        }

        $sirvPath = str_replace('%2F', '/', rawurlencode($sirvPath));

        $request = [
            'method' => __FUNCTION__,
            'verb' => 'HEAD',
            'bucket' => $this->bucket,
            'resource' => "{$sirvPath}",
        ];

        $curlOptions = [
            'CURLOPT_NOBODY' => true,
        ];

        $result = $this->sendRequest($request, null, $curlOptions);

        return $this->responseCode == 200;
    }

    /**
     * Delete object
     *
     * @param string $sirvPath
     * @return bool
     */
    public function deleteObject($sirvPath)
    {
        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if (!$this->connected) {
            return false;
        }

        $sirvPath = str_replace('%2F', '/', rawurlencode($sirvPath));

        $request = [
            'method' => __FUNCTION__,
            'verb' => 'DELETE',
            'bucket' => $this->bucket,
            'resource' => "{$sirvPath}",
        ];

        $result = $this->sendRequest($request);

        return $this->responseCode == 204;
    }

    /**
     * Delete multiple objects
     *
     * @param array $keys
     * @return bool
     */
    public function deleteMultipleObjects($keys)
    {
        if ($this->connected === null) {
            $this->checkCredentials();
        }

        if (!$this->connected) {
            return false;
        }

        $contents = '<' . '?xml version="1.0"?>' . "\n" .
                    '<Delete xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Object><Key>' .
                    implode('</Key></Object><Object><Key>', $keys) .
                    '</Key></Object><Quiet>true</Quiet></Delete>' . "\n";

        $contentMd5 = base64_encode(hash('md5', $contents, true));

        $request = [
            'method' => __FUNCTION__,
            'verb' => 'POST',
            'bucket' => $this->bucket,
            'resource' => "/?delete",
            'content-md5' => $contentMd5,
            'content-type' => "application/xml",
        ];

        $filesize = strlen($contents);
        $fh = fopen('php://temp', 'wb+');
        fwrite($fh, $contents);
        rewind($fh);

        $curlOptions = [
            'CURLOPT_CUSTOMREQUEST' => 'POST',
            'CURLOPT_UPLOAD' => true,
            'CURLOPT_INFILE' => $fh,
            'CURLOPT_INFILESIZE' => $filesize,
        ];

        $headers = [
            'Content-Type' => 'application/xml',
            'Content-MD5' => $contentMd5,
        ];

        $result = $this->sendRequest($request, $headers, $curlOptions);
        fclose($fh);

        return $this->responseCode == 200;
    }

    /**
     * Send request
     *
     * @param array $request
     * @param array $headers
     * @param array $curlOptions
     * @return mixed
     */
    protected function sendRequest($request, $headers = null, $curlOptions = null)
    {
        $method = $request['method'];
        if (isset($this->rateLimitData['methods'][$method])) {
            $type = $this->rateLimitData['methods'][$method];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            if ($rateLimitExpireTime >= time()) {
                $this->responseCode = 429;
                $this->errorMsg = 'Rate limit exceeded. Too many requests. Retry after ' .
                    date('Y-m-d\TH:i:s.v\Z (e)', $rateLimitExpireTime) . '. ' .
                    'Please visit https://sirv.com/help/resources/api/#API_limits';
                return false;
            }
        }

        if ($headers === null) {
            $headers = [];
        }

        $headers['Date'] = $this->date;
        $headers['Authorization'] = 'AWS ' . $this->key . ':' . $this->getSignature($request, $headers);
        foreach ($headers as $k => $v) {
            $headers[$k] = "$k: $v";
        }

        $host = isset($request['bucket']) ? $request['bucket'] . '.' . $this->host : $this->host;

        $uri = 'http://' . $host . $request['resource'];

        curl_reset(self::$curlHandle);

        curl_setopt_array(
            self::$curlHandle,
            [
                CURLOPT_URL => $uri,
                CURLOPT_CUSTOMREQUEST => $request['verb'],
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                //CURLOPT_SSL_VERIFYHOST => false,
                //CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADERFUNCTION => [$this, 'headerDataHandler'],
                CURLOPT_USERAGENT => 'Sirv/Magento 2/' . $this->moduleVersion,
            ]
        );

        $this->responseHeaders = [];

        if (is_array($curlOptions)) {
            foreach ($curlOptions as $k => $v) {
                curl_setopt(self::$curlHandle, constant($k), $v);
            }
        }

        $result = curl_exec(self::$curlHandle);

        $this->responseCode = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);
        //$this->curlInfo = curl_getinfo(self::$curlHandle);

        if ($result === false) {
            $this->errorMsg = curl_error(self::$curlHandle);
        }

        if ($this->responseCode == 429) {
            if (isset($this->responseHeaders['x-ratelimit-type'])) {
                $type = $this->responseHeaders['x-ratelimit-type'];
                $this->rateLimitData['methods'][$method] = $type;
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
     * Get response code
     *
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
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
     * Check if rate limit exceeded
     *
     * @param string $method
     * @return bool
     */
    public function isRateLimitExceeded($method)
    {
        $isRateLimitExceeded = false;

        if (isset($this->rateLimitData['methods'][$method])) {
            $type = $this->rateLimitData['methods'][$method];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            $isRateLimitExceeded = ($rateLimitExpireTime >= time());
        }

        return $isRateLimitExceeded;
    }

    /**
     * Get rate limit expire time
     *
     * @param string $method
     * @return int
     */
    public function getRateLimitExpireTime($method)
    {
        $rateLimitExpireTime = 0;

        if (isset($this->rateLimitData['methods'][$method])) {
            $type = $this->rateLimitData['methods'][$method];
            $rateLimitExpireTime = $this->rateLimitData['types'][$type];
            if ($rateLimitExpireTime < time()) {
                $rateLimitExpireTime = 0;
            }
        }

        return $rateLimitExpireTime;
    }

    /**
     * Get signature
     *
     * @param array $request
     * @param array $headers
     * @return string
     */
    protected function getSignature($request, $headers = null)
    {
        if ($headers === null) {
            $headers = [];
        }

        $canonicalizedAmzHeadersArr = [];
        $canonicalizedAmzHeadersStr = '';
        foreach ($headers as $k => $v) {
            $k = strtolower($k);

            if (substr($k, 0, 5) != 'x-amz') {
                continue;
            }

            if (isset($canonicalizedAmzHeadersArr[$k])) {
                $canonicalizedAmzHeadersArr[$k] .= ',' . trim($v);
            } else {
                $canonicalizedAmzHeadersArr[$k] = trim($v);
            }
        }

        ksort($canonicalizedAmzHeadersArr);

        foreach ($canonicalizedAmzHeadersArr as $k => $v) {
            $canonicalizedAmzHeadersStr .= "$k:$v\n";
        }

        if (isset($request['bucket'])) {
            $request['resource'] = '/' . $request['bucket'] . $request['resource'];
        }

        $str  = $request['verb'] . "\n";
        $str .= isset($request['content-md5']) ? $request['content-md5'] . "\n" : "\n";
        $str .= isset($request['content-type']) ? $request['content-type'] . "\n" : "\n";
        $str .= isset($request['date']) ? $request['date'] . "\n" : $this->date . "\n";
        $str .= $canonicalizedAmzHeadersStr . preg_replace('#\?(?!delete$).*$#is', '', $request['resource']);

        $sha1 = $this->calculateHash($str);
        return $this->encodeWithBase64($sha1);
    }

    /**
     * Calculate hash
     * Algorithm adapted (stolen) from http://pear.php.net/package/Crypt_HMAC/
     *
     * @param string $data
     * @return string
     */
    protected function calculateHash($data)
    {
        $key = $this->secret;
        if (strlen($key) > 64) {
            $key = pack('H40', sha1($key));
        }

        if (strlen($key) < 64) {
            $key = str_pad($key, 64, chr(0));
        }

        $ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
        $opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));
        return sha1($opad . pack('H40', sha1($ipad . $data)));
    }

    /**
     * Encode with MIME base64
     *
     * @param string $str
     * @return string
     */
    protected function encodeWithBase64($str)
    {
        $ret = '';
        $length = strlen($str);
        for ($i = 0; $i < $length; $i += 2) {
            $ret .= chr(hexdec(substr($str, $i, 2)));
        }

        return base64_encode($ret);
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

    /**
     * MIME types
     *
     * @var array
     */
    protected $mimeTypes = [
        '323' => 'text/h323',
        'acx' => 'application/internet-property-stream',
        'ai' => 'application/postscript',
        'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'asf' => 'video/x-ms-asf',
        'asr' => 'video/x-ms-asf',
        'asx' => 'video/x-ms-asf',
        'au' => 'audio/basic',
        'avi' => 'video/quicktime',
        'axs' => 'application/olescript',
        'bas' => 'text/plain',
        'bcpio' => 'application/x-bcpio',
        'bin' => 'application/octet-stream',
        'bmp' => 'image/bmp',
        'c' => 'text/plain',
        'cat' => 'application/vnd.ms-pkiseccat',
        'cdf' => 'application/x-cdf',
        'cer' => 'application/x-x509-ca-cert',
        'class' => 'application/octet-stream',
        'clp' => 'application/x-msclip',
        'cmx' => 'image/x-cmx',
        'cod' => 'image/cis-cod',
        'cpio' => 'application/x-cpio',
        'crd' => 'application/x-mscardfile',
        'crl' => 'application/pkix-crl',
        'crt' => 'application/x-x509-ca-cert',
        'csh' => 'application/x-csh',
        'css' => 'text/css',
        'dcr' => 'application/x-director',
        'der' => 'application/x-x509-ca-cert',
        'dir' => 'application/x-director',
        'dll' => 'application/x-msdownload',
        'dms' => 'application/octet-stream',
        'doc' => 'application/msword',
        'dot' => 'application/msword',
        'dvi' => 'application/x-dvi',
        'dxr' => 'application/x-director',
        'eps' => 'application/postscript',
        'etx' => 'text/x-setext',
        'evy' => 'application/envoy',
        'exe' => 'application/octet-stream',
        'fif' => 'application/fractals',
        'flr' => 'x-world/x-vrml',
        'gif' => 'image/gif',
        'gtar' => 'application/x-gtar',
        'gz' => 'application/x-gzip',
        'h' => 'text/plain',
        'hdf' => 'application/x-hdf',
        'hlp' => 'application/winhlp',
        'hqx' => 'application/mac-binhex40',
        'hta' => 'application/hta',
        'htc' => 'text/x-component',
        'htm' => 'text/html',
        'html' => 'text/html',
        'htt' => 'text/webviewhtml',
        'ico' => 'image/x-icon',
        'ief' => 'image/ief',
        'iii' => 'application/x-iphone',
        'ins' => 'application/x-internet-signup',
        'isp' => 'application/x-internet-signup',
        'jfif' => 'image/pipeg',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'application/x-javascript',
        'latex' => 'application/x-latex',
        'lha' => 'application/octet-stream',
        'lsf' => 'video/x-la-asf',
        'lsx' => 'video/x-la-asf',
        'lzh' => 'application/octet-stream',
        'm13' => 'application/x-msmediaview',
        'm14' => 'application/x-msmediaview',
        'm3u' => 'audio/x-mpegurl',
        'man' => 'application/x-troff-man',
        'mdb' => 'application/x-msaccess',
        'me' => 'application/x-troff-me',
        'mht' => 'message/rfc822',
        'mhtml' => 'message/rfc822',
        'mid' => 'audio/mid',
        'mny' => 'application/x-msmoney',
        'mov' => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mp2' => 'video/mpeg',
        'mp3' => 'audio/mpeg',
        'mpa' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpp' => 'application/vnd.ms-project',
        'mpv2' => 'video/mpeg',
        'ms' => 'application/x-troff-ms',
        'mvb' => 'application/x-msmediaview',
        'nws' => 'message/rfc822',
        'oda' => 'application/oda',
        'p10' => 'application/pkcs10',
        'p12' => 'application/x-pkcs12',
        'p7b' => 'application/x-pkcs7-certificates',
        'p7c' => 'application/x-pkcs7-mime',
        'p7m' => 'application/x-pkcs7-mime',
        'p7r' => 'application/x-pkcs7-certreqresp',
        'p7s' => 'application/x-pkcs7-signature',
        'pbm' => 'image/x-portable-bitmap',
        'pdf' => 'application/pdf',
        'pfx' => 'application/x-pkcs12',
        'pgm' => 'image/x-portable-graymap',
        'pko' => 'application/ynd.ms-pkipko',
        'pma' => 'application/x-perfmon',
        'pmc' => 'application/x-perfmon',
        'pml' => 'application/x-perfmon',
        'pmr' => 'application/x-perfmon',
        'pmw' => 'application/x-perfmon',
        'png' => 'image/png',
        'pnm' => 'image/x-portable-anymap',
        'pot' => 'application/vnd.ms-powerpoint',
        'ppm' => 'image/x-portable-pixmap',
        'pps' => 'application/vnd.ms-powerpoint',
        'ppt' => 'application/vnd.ms-powerpoint',
        'prf' => 'application/pics-rules',
        'ps' => 'application/postscript',
        'pub' => 'application/x-mspublisher',
        'qt' => 'video/quicktime',
        'ra' => 'audio/x-pn-realaudio',
        'ram' => 'audio/x-pn-realaudio',
        'ras' => 'image/x-cmu-raster',
        'rgb' => 'image/x-rgb',
        'rmi' => 'audio/mid',
        'roff' => 'application/x-troff',
        'rtf' => 'application/rtf',
        'rtx' => 'text/richtext',
        'scd' => 'application/x-msschedule',
        'sct' => 'text/scriptlet',
        'setpay' => 'application/set-payment-initiation',
        'setreg' => 'application/set-registration-initiation',
        'sh' => 'application/x-sh',
        'shar' => 'application/x-shar',
        'sit' => 'application/x-stuffit',
        'snd' => 'audio/basic',
        'spc' => 'application/x-pkcs7-certificates',
        'spl' => 'application/futuresplash',
        'src' => 'application/x-wais-source',
        'sst' => 'application/vnd.ms-pkicertstore',
        'stl' => 'application/vnd.ms-pkistl',
        'stm' => 'text/html',
        'svg' => 'image/svg+xml',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc' => 'application/x-sv4crc',
        't' => 'application/x-troff',
        'tar' => 'application/x-tar',
        'tcl' => 'application/x-tcl',
        'tex' => 'application/x-tex',
        'texi' => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'tgz' => 'application/x-compressed',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'tr' => 'application/x-troff',
        'trm' => 'application/x-msterminal',
        'tsv' => 'text/tab-separated-values',
        'txt' => 'text/plain',
        'uls' => 'text/iuls',
        'ustar' => 'application/x-ustar',
        'vcf' => 'text/x-vcard',
        'vrml' => 'x-world/x-vrml',
        'wav' => 'audio/x-wav',
        'wcm' => 'application/vnd.ms-works',
        'wdb' => 'application/vnd.ms-works',
        'wks' => 'application/vnd.ms-works',
        'wmf' => 'application/x-msmetafile',
        'wps' => 'application/vnd.ms-works',
        'wri' => 'application/x-mswrite',
        'wrl' => 'x-world/x-vrml',
        'wrz' => 'x-world/x-vrml',
        'xaf' => 'x-world/x-vrml',
        'xbm' => 'image/x-xbitmap',
        'xla' => 'application/vnd.ms-excel',
        'xlc' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel',
        'xls' => 'application/vnd.ms-excel',
        'xlt' => 'application/vnd.ms-excel',
        'xlw' => 'application/vnd.ms-excel',
        'xof' => 'x-world/x-vrml',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-xwindowdump',
        'z' => 'application/x-compress',
        'zip' => 'application/zip'
    ];
}
