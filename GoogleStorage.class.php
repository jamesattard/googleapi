<?php
/**
 * Implements the Google Storage REST API
 * @author Andries Mooij
 * @author Slawomir Jasinski <slav123@gmail.com>
 *
 *
 */

/**
 * Provides access to Google Storage
 * Relies on the unsurpassed CURL Extension and PHP >= 5.1.2 (because of hash_hmac)
 * This class returns the HTTP Code directly on failure.
 *
 * On ACL Strings:
 * - private<br>
 *   Gives the requester FULL_CONTROL permission for a bucket or object. This is the default ACL that's applied when you upload an object or create a bucket.
 * - public-read<br>
 *   Gives the requester FULL_CONTROL permission and gives all anonymous users READ permission. When you apply this to an object, anyone on the Internet can read the object without authenticating.
 * - public-read-write<br>
 *   Gives the requester FULL_CONTROL permission and gives all anonymous users READ and WRITE permission. This ACL applies only to buckets.
 * - authenticated-read<br>
 *   Gives the requester FULL_CONTROL permission and gives all authenticated Google account holders READ permission.
 * - bucket-owner-read<br>
 *   Gives the requester FULL_CONTROL permission and gives the bucket owner READ permission. This is used only with objects.
 * - bucket-owner-full-control<br>
 *   Gives the requester FULL_CONTROL permission and gives the bucket owner FULL_CONTROL permission. This is used only with objects.
 *
 *   @package Util
 */
class GoogleStorage {
        /**
         * Google Storage Access Key
         * @access protected
         * @var string
         */
        protected $accessKey;
        /**
         * Google Storage Secret
         * @access protected
         * @var string
         */
        protected $secret;
        /**
         * The Host for Google Storage. You can change this if you're using CNAME access.
         * @access public
         * @var string
         */
        public static $host = "commondatastorage.googleapis.com";
        /**
         * Enables debugging mode. Breaks some stuff, but helps you figure out errors.
         * @access protected
         * @var bool
         */
        protected $debug = false;

        /**
         * Creates a new Google Storage class
         *
         * @param string $accessKey
         * @param string $secret
         */
        public function __construct($accessKey, $secret) {
                $this->accessKey = $accessKey;
                $this->secret = $secret;
        }

        /**
         * Lists your buckets.
         *
         * @return SimpleXMLElement|integer
         */
        public function listBuckets() {
                $ret = $this->curlExec(self::$host, "GET", "/");
                return is_object($ret) ? $ret->buckets : $ret;
        }

        /**
         * Creates a bucket.
         * Returns the HTTP Result Code.
         *
         * @param string $name
         * @param string|SimpleXMLElement $acl
         * @return integer
         */
        public function createBucket($name, $acl = null) {
                $body = $acl instanceof SimpleXMLElement ? (string)$acl : "";
                if (is_string($acl) && strlen($acl)) {
                        $headers[] = "x-goog-acl: " . $acl;
                }
                return $this->curlExec($name . "." . self::$host, "PUT", "/", "", array(), $body);



                $headers = array('Content-Length: ' . mb_strlen($body), 'Date: ' . date("r"), 'Host: ' . self::$host);
                $headers[] = $this->getAuthorizationHeader($bucket . "." . self::$host, 'PUT', '/', $headers);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                $ret = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                return $code;
        }

        /**
         * Retrieves information about a bucket and it's contents.
         *
         * @param string $name
         * @param string $prefix
         * @param string $delimiter
         * @param string $marker
         * @param string $maxKeys
         * @return SimpleXMLElement|integer
         */
        public function getBucket($name, $prefix = "", $delimiter = "", $marker = "", $maxKeys = -1) {
                $parameters = array();
                if (strlen($prefix)) {
                        $parameters[] = "prefix=" . urlencode($prefix);
                }
                if (strlen($delimiter)) {
                        $parameters[] = "delimiter=" . urlencode($delimiter);
                }
                if (strlen($marker)) {
                        $parameters[] = "marker=" . urlencode($marker);
                }
                if ($maxKeys != -1) {
                        $parameters[] = "max-keys=" . $maxKeys;
                }
                $parameters = (count($parameters) ? "?" : "") . implode("&", $parameters);

                $ret = $this->curlExec($name . "." . self::$host, "GET", "/", $parameters);
                return is_object($ret) ? $ret->ListBucketResult : $ret;
        }

        /**
         * Retrieves the ACL Settings for a bucket.
         *
         * @param string $name
         * @return SimpleXMLElement|integer
         */
        public function getBucketAcl($name) {
                $ret = $this->curlExec($name . "." . self::$host, "GET", "/?acl");
                return is_object($ret) ? $ret->AccessControlList : $ret;
        }

        /**
         * Deletes an empty bucket. You can't delete non-empty buckets.
         *
         * @param string $name
         * @return integer
         */
        public function deleteBucket($name) {
                return $this->curlExec($name . "." . self::$host, "DELETE", "/");
        }

        /**
         * Stores a file in an object.
         *
         * @param string $bucket
         * @param string $name
         * @param string $file
         * @param string $aclString
         * @return integer
         */
        public function putObjectFile($bucket, $name, $file, string $aclString = NULL) {

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file);
                finfo_close($finfo);

                return $this->putObjectData($bucket, $name, $mime, '@' . $file, $aclString);
        }

        /**
         * Stores data in an object.
         *
         * @param string $bucket
         * @param string $name
         * @param string $mimeType
         * @param string $content
         * @param string $aclString
         * @return integer
         */
        public function putObjectData($bucket, $name, $mimeType, $content, string $aclString = NULL) {
                $headers = array(
                        'Content-Type: ' . $mimeType,
                );
                if (strlen($aclString)) {
                        $headers[] = "x-goog-acl: " . $aclString;
                }

		$rfname = substr($content, 1);
		$fh = fopen($rfname, 'r');
		$content = fread($fh, filesize($rfname));
		fclose($fh);


                return $this->curlExec($bucket . "." . self::$host, "PUT", "/" . $name, "", $headers, $content);
        }

        /**
         * Retrieves an object.
         *
         * @param string $bucket
         * @param string $name
         * @return string
         */
        public function getObject($bucket, $name) {
                return $this->curlExec($bucket . "." . self::$host, "GET", "/" . $name);
        }

        /**
         * Deletes an object.
         *
         * @param string $bucket
         * @param string $name
         * @return integer
         */
        public function deleteObject($bucket, $name) {
                return $this->curlExec($bucket . "." . self::$host, "DELETE", "/" . $name);
        }

        /**
         * CURL Helper function. Does all the work.
         *
         * @param string $host
         * @param string $method
         * @param string $scope
         * @param string $parameters
         * @param array $headers
         * @param string $body
         * @return SimpleXMLElement|integer|string
         */
        protected function curlExec($host, $method, $scope, $parameters = "", $headers = array(), $body = "") {
                $headers[] = 'Content-Length: ' . strlen($body);
                $headers[] = 'Date: ' . date("r");
                $headers[] = 'Host: ' . $host;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $host . "." . $scope . $parameters);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                if ($method != "GET") {
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                        if (strlen($body)) {
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                        }
                }
                $subdomain = str_replace(array(self::$host, "."), array("", ""), $host);
                $subdomain = strlen($subdomain) ? ("/" . $subdomain) : "";
                $headers[] = $this->getAuthorizationHeader($method, $subdomain . $scope, $headers);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                if ($this->debug) {
                        curl_setopt($ch, CURLOPT_HEADER, true);
                }
                $ret = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($this->debug) {
                        echo "<pre>" . $code . "\n" . $ret . "</pre>";
                }
                if ($code == 200) {
                        $size = strlen($ret);
                        if ($size) {
                                $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                                if ($type == "text/xml") {
                                        $ret = new SimpleXMLElement($ret);
                                }
                                return $ret;
                        }
                        else {
                                return $code;
                        }
                }
                else {
                        return $code;
                }
        }

        /**
         * Creates the Authorization-header in the GOOG1 format.
         *
         * @param string $method
         * @param string $resource
         * @param array $headers
         * @return string
         */
        protected function getAuthorizationHeader($method, $resource, $headers) {
                /*
                CanonicalHeaders = HTTP-Verb + "\n" +
                Content-MD5 + "\n" +
                Content-Type + "\n" +
                Date + "\n"
                 */
                $canonicalHeaders = $method . "\n";
                $md5 = preg_grep("/^Content-MD5\s*:/i", $headers);
                if (count($md5)) {
                        list($name, $content) = explode(": ", current($md5));
                        $canonicalHeaders .= $content . "\n";
                }
                else {
                        $canonicalHeaders .= "\n";
                }
                $contentType = preg_grep("/^Content-Type\s*:/i", $headers);
                if (count($contentType)) {
                        list($name, $content) = explode(": ", current($contentType));
                        $canonicalHeaders .= $content . "\n";
                }
                else {
                        $canonicalHeaders .= "\n";
                }
                $date = preg_grep("/^Date\s*:/i", $headers);
                if (count($date)) {
                        list($name, $content) = explode(": ", current($date));
                        $canonicalHeaders .= $content . "\n";
                }
                else {
                        $canonicalHeaders .= "\n";
                }

                /*
                You construct the CanonicalExtensionHeaders portion of the message by concatenating all extension (custom) headers that begin with x-goog-. However, you cannot perform a simple concatenation. You must concatenate the headers using the following process:
                - Make all custom header names lowercase.
                - Sort all custom headers lexicographically by header name.
                - Eliminate duplicate header names by creating one header name with a comma-separated list of values. Be sure there is no whitespace between the values and be sure that the order of the comma-separated list matches the order that the headers appear in your request. For more information, see RFC 2616 section 4.2.
                - Replace any folding whitespace or newlines (CRLF or LF) with a single space. For more information about folding whitespace, see RFC 2822 section 2.2.3.
                - Remove any whitespace around the colon that appears after the header name.
                - Append a newline (U+000A) to each custom header.
                - Concatenate all custom headers.
                - It's important to note that you use both the header name and the header value when you construct the CanonicalExtensionHeaders portion of the message. This is different than the CanonicalHeaders portion of the message, which used only header values.
                 */

                $customHeaders = preg_grep("/^x-goog-/i", $headers);
                $customHeaders = preg_replace("/^(x-goog-[^:]+)\s*:\s*/ie", "strtolower('$1:')", $customHeaders);
                sort($customHeaders, SORT_STRING);
                $customHeaders = preg_replace("/\r\n(\s)/s", "$1", $customHeaders);
                $customHeaders = implode(",", $customHeaders) . (count($customHeaders) ? "\n" : "");

                /*
                Signature = Base64-Encoding-Of(HMAC-SHA1(UTF-8-Encoding-Of(YourGoogleStorageSecretKey, MessageToBeSigned)))

                To create the signature you use a cryptographic hash function known as HMAC-SHA1. HMAC-SHA1 is a hash-based message authentication code (MAC) and is described in RFC 2104. It requires two input parameters, both UTF-8 encoded: a key and a message. You use your Google Storage secret as the key. You must construct the message by concatenating specific HTTP headers in a specific order.
The message that you sign is a UTF-8 encoded byte string. The following pseudocode notation shows how to construct this byte string:

                MessageToBeSigned = UTF-8-Encoding-Of(CanonicalHeaders + CanonicalExtensionHeaders + CanonicalResource)
                 */
                return "Authorization: GOOG1 " . $this->accessKey . ":" . base64_encode(hash_hmac("sha1", $canonicalHeaders . $customHeaders . $resource, $this->secret, true));
        }

}
