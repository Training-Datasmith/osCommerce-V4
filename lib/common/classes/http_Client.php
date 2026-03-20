<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\classes;

class Http_Client
{
    public $url;
    // array containg server URL, similar to parseurl() returned array
    public $reply;
    // response code
    public $reply_string;
    // full response
    public $protocol_version = '1.1';
    public $request_headers;
    public $request_body;
    public $socket = false;
    // proxy stuff
    public $use_proxy = false;
    public $proxy_host;
    public $proxy_port;
    /**
     * httpClient constructor
     * Note: when host and port are defined, the connection is immediate
     * @seeAlso connect
     **/
    public function __construct($host = '', $port = '')
    {
        if (tep_not_null($host)) {
            $this->connect($host, $port);
        }
    }
    /**
     * turn on proxy support
     * @param proxyHost proxy host address eg "proxy.mycorp.com"
     * @param proxyPort proxy port usually 80 or 8080
     **/
    public function set_proxy($proxy_host, $proxy_port)
    {
        $this->use_proxy = true;
        $this->proxy_host = $proxy_host;
        $this->proxy_port = $proxy_port;
    }
    /**
     * setProtocolVersion
     * define the HTTP protocol version to use
     * @param version string the version number with one decimal: "0.9", "1.0", "1.1"
     * when using 1.1, you MUST set the mandatory headers "Host"
     * @return boolean false if the version number is bad, true if ok
     **/
    public function set_protocol_version($version)
    {
        if ($version > 0 && $version <= 1.1) {
            $this->protocol_version = $version;
            return true;
        } else {
            return false;
        }
    }
    /**
     * set a username and password to access a protected resource
     * Only "Basic" authentication scheme is supported yet
     * @param username string - identifier
     * @param password string - clear password
     **/
    public function set_credentials($username, $password)
    {
        $this->add_header('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }
    /**
     * define a set of HTTP headers to be sent to the server
     * header names are lowercased to avoid duplicated headers
     * @param headers hash array containing the headers as headerName => headerValue pairs
     **/
    public function set_headers($headers)
    {
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                $this->request_headers[$name] = $value;
            }
        }
    }
    /**
     * addHeader
     * set a unique request header
     * @param headerName the header name
     * @param headerValue the header value, ( unencoded)
     **/
    public function add_header($header_name, $header_value)
    {
        $this->request_headers[$header_name] = $header_value;
    }
    /**
     * removeHeader
     * unset a request header
     * @param headerName the header name
     **/
    public function remove_header($header_name)
    {
        unset($this->request_headers[$header_name]);
    }
    /**
     * Connect
     * open the connection to the server
     * @param host string server address (or IP)
     * @param port string server listening port - defaults to 80
     * @return boolean false is connection failed, true otherwise
     **/
    public function Connect($host, $port = '')
    {
        $this->url['scheme'] = 'http';
        $this->url['host'] = $host;
        if (tep_not_null($port)) {
            $this->url['port'] = $port;
        }
        return true;
    }
    /**
     * Disconnect
     * close the connection to the  server
     **/
    public function Disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
        }
    }
    /**
     * head
     * issue a HEAD request
     * @param uri string URI of the document
     * @return string response status code (200 if ok)
     * @seeAlso getHeaders()
     **/
    public function Head($uri)
    {
        $this->response_headers = $this->response_body = '';
        $uri = $this->make_uri($uri);
        if ($this->send_command('HEAD ' . $uri . ' HTTP/' . $this->protocol_version)) {
            $this->process_reply();
        }
        return $this->reply;
    }
    /**
     * get
     * issue a GET http request
     * @param uri URI (path on server) or full URL of the document
     * @return string response status code (200 if ok)
     * @seeAlso getHeaders(), getBody()
     **/
    public function Get($url)
    {
        $this->response_headers = $this->response_body = '';
        $uri = $this->make_uri($url);
        if ($this->send_command('GET ' . $uri . ' HTTP/' . $this->protocol_version)) {
            $this->process_reply();
        }
        return $this->reply;
    }
    /**
     * Post
     * issue a POST http request
     * @param uri string URI of the document
     * @param query_params array parameters to send in the form "parameter name" => value
     * @return string response status code (200 if ok)
     * @example
     * $params = array( "login" => "tiger", "password" => "secret" );
     * $http->post( "/login.php", $params );
     **/
    public function Post($uri, $query_params = '')
    {
        $uri = $this->make_uri($uri);
        if (is_array($query_params)) {
            $post_array = [];
            foreach ($query_params as $k => $v) {
                $post_array[] = urlencode($k) . '=' . urlencode($v);
            }
            $this->request_body = implode('&', $post_array);
        }
        // set the content type for post parameters
        $this->add_header('Content-Type', 'application/x-www-form-urlencoded');
        if ($this->send_command('POST ' . $uri . ' HTTP/' . $this->protocol_version)) {
            $this->process_reply();
        }
        $this->remove_header('Content-Type');
        $this->remove_header('Content-Length');
        $this->request_body = '';
        return $this->reply;
    }
    /**
     * Put
     * Send a PUT request
     * PUT is the method to sending a file on the server. it is *not* widely supported
     * @param uri the location of the file on the server. dont forget the heading "/"
     * @param filecontent the content of the file. binary content accepted
     * @return string response status code 201 (Created) if ok
     * @see RFC2518 "HTTP Extensions for Distributed Authoring WEBDAV"
     **/
    public function Put($uri, $filecontent)
    {
        $uri = $this->make_uri($uri);
        $this->request_body = $filecontent;
        if ($this->send_command('PUT ' . $uri . ' HTTP/' . $this->protocol_version)) {
            $this->process_reply();
        }
        return $this->reply;
    }
    /**
     * getHeaders
     * return the response headers
     * to be called after a Get() or Head() call
     * @return array headers received from server in the form headername => value
     * @seeAlso get, head
     **/
    public function get_headers()
    {
        return $this->response_headers;
    }
    /**
     * getHeader
     * return the response header "headername"
     * @param headername the name of the header
     * @return header value or NULL if no such header is defined
     **/
    public function get_header($headername)
    {
        return $this->response_headers[$headername];
    }
    /**
     * getBody
     * return the response body
     * invoke it after a Get() call for instance, to retrieve the response
     * @return string body content
     * @seeAlso get, head
     **/
    public function get_body()
    {
        return $this->response_body;
    }
    /**
     * getStatus return the server response's status code
     * @return string a status code
     * code are divided in classes (where x is a digit)
     *  - 20x : request processed OK
     *  - 30x : document moved
     *  - 40x : client error ( bad url, document not found, etc...)
     *  - 50x : server error
     * @see RFC2616 "Hypertext Transfer Protocol -- HTTP/1.1"
     **/
    public function get_status()
    {
        return $this->reply;
    }
    /**
     * getStatusMessage return the full response status, of the form "CODE Message"
     * eg. "404 Document not found"
     * @return string the message
     **/
    public function get_status_message()
    {
        return $this->reply_string;
    }
    /**
     * @scope only protected or private methods below
     **/
    /**
     * send a request
     * data sent are in order
     * a) the command
     * b) the request headers if they are defined
     * c) the request body if defined
     * @return string the server repsonse status code
     **/
    public function send_command($command)
    {
        $this->response_headers = [];
        $this->response_body = '';
        // connect if necessary
        if ($this->socket == false || feof($this->socket)) {
            if ($this->use_proxy) {
                $host = $this->proxy_host;
                $port = $this->proxy_port;
            } else {
                $host = $this->url['host'];
                $port = $this->url['port'];
            }
            if (!tep_not_null($port)) {
                $port = 80;
            }
            if (!$this->socket = @fsockopen($host, $port, $this->reply, $this->reply_string)) {
                return false;
            }
            if (tep_not_null($this->request_body)) {
                $this->add_header('Content-Length', strlen($this->request_body));
            }
            $this->request = $command;
            $cmd = $command . "\r\n";
            if (is_array($this->request_headers)) {
                foreach ($this->request_headers as $k => $v) {
                    $cmd .= $k . ': ' . $v . "\r\n";
                }
            }
            if (tep_not_null($this->request_body)) {
                $cmd .= "\r\n" . $this->request_body;
            }
            // unset body (in case of successive requests)
            $this->request_body = '';
            fputs($this->socket, $cmd . "\r\n");
            return true;
        }
    }
    public function process_reply()
    {
        $this->reply_string = trim(fgets($this->socket, 1024));
        if (preg_match('|^HTTP/\S+ (\d+) |i', $this->reply_string, $a)) {
            $this->reply = $a[1];
        } else {
            $this->reply = 'Bad Response';
        }
        //get response headers and body
        $this->response_headers = $this->process_header();
        $this->response_body = $this->process_body();
        return $this->reply;
    }
    /**
     * processHeader() reads header lines from socket until the line equals $lastLine
     * @scope protected
     * @return array of headers with header names as keys and header content as values
     **/
    public function process_header($last_line = "\r\n")
    {
        $headers = [];
        $finished = false;
        while (!$finished && !feof($this->socket)) {
            $str = fgets($this->socket, 1024);
            $finished = $str == $last_line;
            if (!$finished) {
                list($hdr, $value) = explode(': ', $str, 2);
                // nasty workaround broken multiple same headers (eg. Set-Cookie headers) @FIXME
                if (isset($headers[$hdr])) {
                    $headers[$hdr] .= '; ' . trim($value);
                } else {
                    $headers[$hdr] = trim($value);
                }
            }
        }
        return $headers;
    }
    /**
     * processBody() reads the body from the socket
     * the body is the "real" content of the reply
     * @return string body content
     * @scope private
     **/
    public function process_body()
    {
        $data = '';
        $counter = 0;
        do {
            $status = socket_get_status($this->socket);
            if ($status['eof'] == 1) {
                break;
            }
            if ($status['unread_bytes'] > 0) {
                $buffer = fread($this->socket, $status['unread_bytes']);
                $counter = 0;
            } else {
                $buffer = fread($this->socket, 128);
                $counter++;
                usleep(2);
            }
            $data .= $buffer;
        } while ($status['unread_bytes'] > 0 || $counter++ < 10);
        return $data;
    }
    /**
     * Calculate and return the URI to be sent ( proxy purpose )
     * @param the local URI
     * @return URI to be used in the HTTP request
     * @scope private
     **/
    public function make_uri($uri)
    {
        $a = parse_url($uri);
        if (isset($a['scheme']) && isset($a['host'])) {
            $this->url = $a;
        } else {
            unset($this->url['query']);
            unset($this->url['fragment']);
            $this->url = array_merge($this->url, $a);
        }
        if ($this->use_proxy) {
            $requesturi = 'http://' . $this->url['host'] . (empty($this->url['port']) ? '' : ':' . $this->url['port']) . $this->url['path'] . (empty($this->url['query']) ? '' : '?' . $this->url['query']);
        } else {
            $requesturi = $this->url['path'] . (empty($this->url['query']) ? '' : '?' . $this->url['query']);
        }
        return $requesturi;
    }
}