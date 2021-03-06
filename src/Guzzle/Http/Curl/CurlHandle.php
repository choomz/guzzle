<?php

namespace Guzzle\Http\Curl;

use Guzzle\Guzzle;
use Guzzle\Common\Exception\InvalidArgumentException;
use Guzzle\Common\Collection;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Url;

/**
 * Immutable wrapper for a cURL handle
 */
class CurlHandle
{
    /**
     * @var Collection Curl options
     */
    protected $options;

    /**
     * @var resource Curl resource handle
     */
    protected $handle;

    /**
     * @var int CURLE_* error
     */
    protected $errorNo = CURLE_OK;

    /**
     * Factory method to create a new curl handle based on an HTTP request
     *
     * @param RequestInterface $request Request
     *
     * @return CurlHandle
     */
    public static function factory(RequestInterface $request)
    {
        $handle = curl_init();
        $mediator = new RequestMediator($request);
        $protocolVersion = $request->getProtocolVersion() === '1.0' ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1;

        // Array of default cURL options.
        $curlOptions = array(
            CURLOPT_URL => $request->getUrl(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_CONNECTTIMEOUT => 10, // Connect timeout in seconds
            CURLOPT_RETURNTRANSFER => false, // Streaming the return, so no need
            CURLOPT_HEADER => false, // Retrieve the received headers
            CURLOPT_USERAGENT => (string) $request->getHeader('User-Agent'),
            CURLOPT_ENCODING => '', // Supports all encodings
            CURLOPT_PORT => $request->getPort(),
            CURLOPT_HTTP_VERSION => $protocolVersion,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_STDERR => fopen('php://temp', 'r+'),
            CURLOPT_VERBOSE => true,
            CURLOPT_HTTPHEADER => array(),
            CURLOPT_HEADERFUNCTION => array($mediator, 'receiveResponseHeader'),
            CURLOPT_PROGRESSFUNCTION => array($mediator, 'progress')
        );

        // HEAD requests need no response body, everything else might
        if ($request->getMethod() != 'HEAD') {
            $curlOptions[CURLOPT_WRITEFUNCTION] = array($mediator, 'writeResponseBody');
        }

        // Account for PHP installations with safe_mode or open_basedir enabled
        // @codeCoverageIgnoreStart
        if (Guzzle::getCurlInfo('follow_location')) {
            $curlOptions[CURLOPT_FOLLOWLOCATION] = true;
            $curlOptions[CURLOPT_MAXREDIRS] = 5;
        }
        // @codeCoverageIgnoreEnd

        $headers = $request->getHeaders()->getAll();

        // Specify settings according to the HTTP method
        switch ($request->getMethod()) {
            case 'GET':
                $curlOptions[CURLOPT_HTTPGET] = true;
                break;
            case 'HEAD':
                $curlOptions[CURLOPT_NOBODY] = true;
                unset($curlOptions[CURLOPT_WRITEFUNCTION]);
                break;
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                break;
            case 'PUT':
            case 'PATCH':
                $curlOptions[CURLOPT_UPLOAD] = true;
                if ($request->hasHeader('Content-Length')) {
                    unset($headers['Content-Length']);
                    $curlOptions[CURLOPT_INFILESIZE] = (int) (string) $request->getHeader('Content-Length');
                }

                break;
        }

        if ($request instanceof EntityEnclosingRequestInterface) {

            // If no body is being sent, always send Content-Length of 0
            if (!$request->getBody() && !count($request->getPostFields())) {
                $headers['Content-Length'] = 0;
                unset($headers['Transfer-Encoding']);
                // Need to remove CURLOPT_UPLOAD to prevent chunked encoding
                unset($curlOptions[CURLOPT_UPLOAD]);
                unset($curlOptions[CURLOPT_POST]);
                // Not reading from a callback when using empty body
                unset($curlOptions[CURLOPT_READFUNCTION]);
            } else {
                // Add a callback for curl to read data to send with the request
                $curlOptions[CURLOPT_READFUNCTION] = array($mediator, 'readRequestBody');
            }

            // If the Expect header is not present, prevent curl from adding it
            if (!$request->hasHeader('Expect')) {
                $curlOptions[CURLOPT_HTTPHEADER][] = 'Expect:';
            }
        }

        // Set custom cURL options
        foreach ($request->getCurlOptions() as $key => $value) {
            $curlOptions[$key] = $value;
        }

        // Check if any headers or cURL options are blacklisted
        $client = $request->getClient();
        if ($client && $client->getConfig('curl.blacklist')) {
            foreach ($client->getConfig('curl.blacklist') as $value) {
                if (strpos($value, 'header.') === 0) {
                    $blacklistHeader = substr($value, 7);
                    // Remove headers that may have previously been set
                    // but are supposed to be blacklisted
                    unset($headers[$blacklistHeader]);
                    $headers[$blacklistHeader] = '';
                } else {
                    unset($curlOptions[$value]);
                }
            }
        }

        // Add any custom headers to the request. Emtpy headers will cause curl to
        // not send the header at all.
        foreach ($headers as $key => $value) {
            foreach ((array) $value as $val) {
                $curlOptions[CURLOPT_HTTPHEADER][] = trim("{$key}: {$val}");
            }
        }

        // Apply the options to the cURL handle.
        curl_setopt_array($handle, $curlOptions);
        $request->getParams()->set('curl.last_options', $curlOptions);

        return new static($handle, $curlOptions);
    }

    /**
     * Construct a new CurlHandle object that wraps a cURL handle
     *
     * @param resource $handle Configured cURL handle resource
     * @param Collection|array $options Curl options to use with the handle
     *
     * @throws InvalidArgumentException
     */
    public function __construct($handle, $options)
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException('Invalid handle provided');
        }
        if (is_array($options)) {
            $this->options = new Collection($options);
        } else if ($options instanceof Collection) {
            $this->options = $options;
        } else {
            throw new InvalidArgumentException('Expected array or Collection');
        }
        $this->handle = $handle;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Close the curl handle
     */
    public function close()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
        $this->handle = null;
    }

    /**
     * Check if the handle is available and still OK
     *
     * @return bool
     */
    public function isAvailable()
    {
        return is_resource($this->handle) && false != curl_getinfo($this->handle, CURLINFO_EFFECTIVE_URL);
    }

    /**
     * Get the last error that occurred on the cURL handle
     *
     * @return string
     */
    public function getError()
    {
        return $this->isAvailable() ? curl_error($this->handle) : '';
    }

    /**
     * Get the last error number that occurred on the cURL handle
     *
     * @return int
     */
    public function getErrorNo()
    {
        if ($this->errorNo) {
            return $this->errorNo;
        }

        return $this->isAvailable() ? curl_errno($this->handle) : 0;
    }

    /**
     * Set the curl error number
     *
     * @param int $error Error number to set
     *
     * @return CurlHandle
     */
    public function setErrorNo($error)
    {
        $this->errorNo = $error;

        return $this;
    }

    /**
     * Get cURL curl_getinfo data
     *
     * @param int $option (optional) Option to retrieve.  Pass null to retrieve
     *      retrieve all data as an array or pass a CURLINFO_* constant
     *
     * @return array|mixed
     */
    public function getInfo($option = null)
    {
        if (!is_resource($this->handle)) {
            return null;
        }

        if (null !== $option) {
            return curl_getinfo($this->handle, $option) ?: null;
        }

        return curl_getinfo($this->handle) ?: array();
    }

    /**
     * Get the stderr output
     *
     * @param bool $asResource (optional) Set to TRUE to get an fopen resource
     *
     * @return string|resource|null
     */
    public function getStderr($asResource = false)
    {
        $stderr = $this->getOptions()->get(CURLOPT_STDERR);
        if (!$stderr) {
            return null;
        }

        if ($asResource) {
            return $stderr;
        }

        fseek($stderr, 0);
        $e = stream_get_contents($stderr);
        fseek($stderr, 0, SEEK_END);

        return $e;
    }

    /**
     * Get the URL that this handle is connecting to
     *
     * @return Url
     */
    public function getUrl()
    {
        return Url::factory($this->options->get(CURLOPT_URL));
    }

    /**
     * Get the wrapped curl handle
     *
     * @return handle|null Returns the cURL handle or null if it was closed
     */
    public function getHandle()
    {
        return $this->handle && $this->isAvailable() ? $this->handle : null;
    }

    /**
     * Get the cURL setopt options of the handle.  Changing values in the return
     * object will have no effect on the curl handle after it is created.
     *
     * @return Collection
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Update a request based on the log messages of the CurlHandle
     *
     * @param RequestInterface $request Request to update
     */
    public function updateRequestFromTransfer(RequestInterface $request)
    {
        $log = $this->getStderr(true);

        if (!$log || !$request->getResponse()) {
            return;
        }

        // Update the transfer stats of the response
        $request->getResponse()->setInfo($this->getInfo());

        // Parse the cURL stderr output for outgoing requests
        $headers = '';
        fseek($log, 0);
        while (($line = fgets($log)) !== false) {
            if ($line && $line[0] == '>') {
                $headers = substr(trim($line), 2) . "\r\n";
                while (($line = fgets($log)) !== false) {
                    if ($line[0] == '*' || $line[0] == '<') {
                        break;
                    } else {
                        $headers .= trim($line) . "\r\n";
                    }
                }
            }
        }

        // Add request headers to the request exactly as they were sent
        if ($headers) {
            $parsed = RequestFactory::getInstance()->parseMessage($headers);
            if (!empty($parsed['headers'])) {
                $request->setHeaders(array());
                foreach ($parsed['headers'] as $name => $value) {
                    $request->setHeader($name, $value);
                }
            }
            if (!empty($parsed['protocol_version'])) {
                $request->setProtocolVersion($parsed['protocol_version']);
            }
        }
    }
}
