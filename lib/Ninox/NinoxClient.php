<?php
/**
 * This libary allows you to quickly and easily perform REST actions on the ninox backend using PHP.
 *
 * @author    Bertram Buchardt <support@4leads.de>
 * @copyright 2020 4leads GmbH
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace Ninox;

use stdClass;

/**
 * Interface to the Ninox REST-API
 *
 */
class NinoxClient
{
    const VERSION = '0.9.0';

    const TEAM_ID_VAR = "{TEAM_ID}";
    const DATABASE_ID_VAR = "{DATABASE_ID}";

    //NINOX QUERY-Params list--->
    const QUERY_PAGE = "page";
    const QUERY_PER_PAGE = "perPage";
    const QUERY_ORDER = "order"; //field to order by
    const QUERY_DESC = "desc"; //if true DESC, else ASC
    const QUERY_NEW = "new"; //if true show newest first --> no order
    const QUERY_UPDATED = "updated";
    const QUERY_SINCE_ID = "sinceId"; //id larger than
    const QUERY_SINCE_SQ = "sinceSq"; // sequence number lager than
    const QUERY_FILTERS = "filters";
    //<---END NINOX QUERY-Params list

    //Needed Record names
    const METHOD_GET = "GET";
    const METHOD_POST = "POST";
    const METHOD_DELETE = "DELETE";

    //Client properties
    /**
     * @var string
     */
    protected $host;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var array
     */
    protected $path;

    /**
     * @var string|null
     */
    protected static $team_id;

    /**
     * @var string|null
     */
    protected static $database_id;

    /**
     * @var array
     */
    protected $curlOptions;

    /**
     * @var string|null
     */
    private $_team_id;
    /**
     * @var string|null
     */
    private $_databse_id;

    //END Client properties


    /**
     * Setup the HTTP Client
     *
     * @param string $apiKey your 4leads API Key.
     * @param array $options an array of options, currently only "host" and "curl" are implemented.
     * @param null|string $team_id set a fixed team_id optionally for all requests
     */
    public function __construct($apiKey, ?array $options = [], ?string $team_id = null)
    {
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: 4leads-ninox-api-client/' . self::VERSION . ';php',
            'Accept: application/json',
        ];

        //override if set
        self::$team_id = $team_id ? $team_id : self::$team_id;

        $host = isset($options['host']) ? $options['host'] : 'https://api.ninoxdb.de/v1';

        $curlOptions = isset($options['curl']) ? $options['curl'] : null;
        $this->setupClient($host, $headers, null, null, $curlOptions);
    }

    /**
     * Initialize the client
     *
     * @param string $host the base url (e.g. https://api.4leads.net)
     * @param array $headers global request headers
     * @param string $version api version (configurable) - this is specific to the 4leads API
     * @param array $path holds the segments of the url path
     * @param array $curlOptions extra options to set during curl initialization
     */
    protected function setupClient($host, $headers = null, $version = null, $path = null, $curlOptions = null)
    {
        $this->host = $host;
        $this->headers = $headers ?: [];
        $this->version = $version;
        $this->path = $path ?: [];
        $this->curlOptions = $curlOptions ?: [];
    }


    /**
     * Build the final URL to be passed
     * @param string $path $the relative Path inside the api
     * @param array $queryParams an array of all the query parameters
     * @return string
     */
    public function buildUrl($path, $queryParams = null)
    {
        if (isset($queryParams) && is_array($queryParams) && count($queryParams)) {
            $path .= '?' . http_build_query($queryParams);
        }
        //replace fixed or current team and database
        $team_id = strlen($this->_team_id) ? $this->_team_id : self::$team_id;
        $database_id = strlen($this->_databse_id) ? $this->_databse_id : self::$database_id;
        $path = strlen($team_id) ? str_replace(self::TEAM_ID_VAR, urlencode($team_id), $path) : $path;
        $path = strlen($database_id) ? str_replace(self::DATABASE_ID_VAR, urlencode($database_id), $path) : $path;

        return sprintf('%s%s%s', $this->host, $this->version ?: '', $path);
    }

    /**
     * Make the API call and return the response.
     * This is separated into it's own function, so we can mock it easily for testing.
     *
     * @param string $method the HTTP verb
     * @param string $url the final url to call
     * @param stdClass $body request body
     * @param array $headers any additional request headers
     *
     * @return NinoxResponse|stdClass object
     */
    public function makeRequest($method, $url, $body = null, $headers = null): NinoxResponse
    {
        $channel = curl_init($url);

        $options = $this->createCurlOptions($method, $body, $headers);

        curl_setopt_array($channel, $options);
        $content = curl_exec($channel);

        $response = $this->parseResponse($channel, $content);

        curl_close($channel);

        if (strlen($response->responseBody)) {
            $response->responseBody = json_decode($response->responseBody);
        }

        //clean temporary set team and database id | prevent unwanted reuse in any case
        $this->_team_id = null;
        $this->_databse_id = null;

        return $response;
    }

    /**
     * Creates curl options for a request
     * this function does not mutate any private variables
     *
     * @param string $method
     * @param stdClass $body
     * @param array $headers
     *
     * @return array
     */
    private function createCurlOptions($method, $body = null, $headers = null)
    {
        $options = [
                CURLOPT_HEADER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_FAILONERROR => false,
                CURLOPT_USERAGENT => '4leads-ninox-php-client,v' . self::VERSION,
            ] + $this->curlOptions
            + [
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RETURNTRANSFER => true,
            ];

        if (isset($headers)) {
            $headers = array_merge($this->headers, $headers);
        } else {
            $headers = $this->headers;
        }

        if (isset($body)) {
            $encodedBody = json_encode($body);
            $options[CURLOPT_POSTFIELDS] = $encodedBody;
            $headers = array_merge($headers, ['Content-Type: application/json']);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        return $options;
    }

    /**
     * Prepare response object.
     *
     * @param resource $channel the curl resource
     * @param string $content
     *
     * @return NinoxResponse|stdClass  response object
     */
    private function parseResponse($channel, $content): NinoxResponse
    {
        $response = new NinoxResponse();
        $response->headerSize = curl_getinfo($channel, CURLINFO_HEADER_SIZE);
        $response->statusCode = curl_getinfo($channel, CURLINFO_HTTP_CODE);

        $response->responseBody = substr($content, $response->headerSize);

        $headString = substr($content, 0, $response->headerSize);
        $response->responseHeaders = explode("\n", $headString);
        $response->responseHeaders = array_map('trim', $response->responseHeaders);

        return $response;
    }

    /**
     * @return NinoxResponse|stdClass
     */
    public function listTeams()
    {
        $path = "/teams";
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * @param string|null $team_id
     * @return NinoxResponse|stdClass
     */
    public function listDatabases(?string $team_id): NinoxResponse
    {
        $this->_team_id = $team_id;
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases";
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * @return NinoxResponse|stdClass
     */
    public function listTables(?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables";
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * @param $tableId
     * @param array|null $queryParams
     * @param stdClass|null $filters
     * @param string|null $database_id
     * @param string|null $team_id
     * @return NinoxResponse
     */
    public function queryRecords($tableId, ?array $queryParams, ?stdClass $filters, ?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        if ($filters) {
            $queryParams[self::QUERY_FILTERS] = json_encode($filters);
        }
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables/" . urlencode($tableId) . "/records";
        $url = $this->buildUrl($path, $queryParams ? $queryParams : []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * Get a single Record from a Table by id
     * @param $tableId
     * @param $recordId
     * @param string|null $database_id
     * @param string|null $team_id
     * @return NinoxResponse
     */
    public function getRecord($tableId, $recordId, ?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables/" . urlencode($tableId) . "/records/" . urlencode($recordId);
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * Get a list of files associated to the record
     * @param $tableId
     * @param $recordId
     * @param string|null $database_id
     * @param string|null $team_id
     * @return NinoxResponse
     */
    public function listRecordFiles($tableId, $recordId, ?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables/" . urlencode($tableId) . "/records/" . urlencode($recordId) . "/files";
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_GET, $url);
    }

    /**
     * Delete a single Record from a Table by id
     * @param $tableId
     * @param $recordId
     * @param string|null $database_id
     * @param string|null $team_id
     * @return NinoxResponse
     */
    public function deleteRecord($tableId, $recordId, ?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables/" . urlencode($tableId) . "/records/" . urlencode($recordId);
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_DELETE, $url);
    }

    /**
     * Insert or Update (see Ninox Docs) multiple Records as part of an array, cast for single stdclass object possible.
     * @param $tableId
     * @param array|stdClass $upserts
     * @param string|null $database_id
     * @param string|null $team_id
     * @return NinoxResponse
     */
    public function upsertRecords($tableId, $upserts, ?string $database_id, ?string $team_id): NinoxResponse
    {
        $this->_databse_id = $database_id;
        $this->_team_id = $team_id;
        if ($upserts instanceof stdClass) {
            //cast as array of itself
            $upserts = [$upserts];
        }
        if (!is_array($upserts)) {
            //make sure its array in any case
            $upserts = [];
        }
        $path = "/teams/" . self::TEAM_ID_VAR . "/databases/" . self::DATABASE_ID_VAR . "/tables/" . urlencode($tableId) . "/records";
        $url = $this->buildUrl($path, []);
        return $this->makeRequest(self::METHOD_POST, $url, $upserts);
    }

    /**
     * Test the API-KEY
     * @return bool
     */
    public function validateKey()
    {
        return $this->listTeams()->isOK();
    }

    /**
     * If value is set all requests (except requests without team assoziated) run on this team
     * @param string|null $team_id
     */
    public static function setFixTeam(?string $team_id): void
    {
        self::$team_id = $team_id;
    }

    /**
     * If value is set all requests (except requests without database assoziated) run on this database
     * @param string|null $database_id
     */
    public static function setFixDatabase(?string $database_id): void
    {
        self::$database_id = $database_id;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string|null
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return array
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return array
     */
    public function getCurlOptions()
    {
        return $this->curlOptions;
    }

    /**
     * Set extra options to set during curl initialization
     *
     * @param array $options
     *
     * @return NinoxClient
     */
    public function setCurlOptions(array $options)
    {
        $this->curlOptions = $options;

        return $this;
    }
}