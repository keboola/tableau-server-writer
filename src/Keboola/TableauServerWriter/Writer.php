<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\TableauServerWriter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Writer
{
    const API_VERSION = '2.0';

    /** @var Client  */
    protected $client;
    protected $token;
    protected $siteId;
    protected $serverUrl;
    protected $projectId;
    protected $baseUri;

    public function __construct($serverUrl, $username, $password, $site = null, $projectId = null)
    {
        $this->serverUrl = $serverUrl;
        $this->projectId = $projectId;
        $this->baseUri = '/api/' . self::API_VERSION;

        $stack = \GuzzleHttp\HandlerStack::create();
        $stack->push(self::addMixedMultipart());
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $serverUrl,
            'handler' => $stack
        ]);

        $this->login($username, $password, $site);
    }

    private static function addMixedMultipart()
    {
        return function (callable $handler) {
            return function (
                \Psr\Http\Message\RequestInterface $request,
                array $options
            ) use ($handler) {
                if (!empty($options['isMixed']) && $request->getBody() instanceof \GuzzleHttp\Psr7\MultipartStream) {
                    $request = $request->withHeader('Content-Type', 'multipart/mixed; ; boundary='
                        . $request->getBody()->getBoundary());
                }
                return $handler($request, $options);
            };
        };
    }

    /**
     * @param string $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    public function login($username, $password, $site = null)
    {
        try {
            $result = $this->client->post("{$this->baseUri}/auth/signin", [
                'body' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<tsRequest>
    <credentials name="{$username}" password="{$password}">
        <site contentUrl="{$site}" />
    </credentials>
</tsRequest>
XML
            ]);

            $xml = self::parseResponse($result->getBody());
            $xPath = $xml->xpath('//ts:credentials/@token');
            if (!count($xPath)) {
                throw new Exception('Token could not be found in API response: ' . $result->getBody());
            }
            $this->token = (string)$xPath[0];

            $xPath = $xml->xpath('//ts:credentials/ts:site/@id');
            if (!count($xPath)) {
                throw new Exception('Site Id could not be found in API response: ' . $result->getBody());
            }
            $this->siteId = (string)$xPath[0];
        } catch (ClientException $e) {

            throw new Exception('Login to API failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function logout()
    {
        try {
            $this->client->post("{$this->baseUri}/auth/signout", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);
        } catch (ClientException $e) {
            throw new Exception('Logout from API failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function listProjects()
    {
        try {
            $result = $this->client->get("{$this->baseUri}/sites/{$this->siteId}/projects", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);
            $xml = self::parseResponse($result->getBody());
            $xPath = $xml->xpath('//ts:project');
            $result = [];
            foreach ($xPath as $r) {
                $subResult = [];
                foreach ($r->attributes() as $k => $v) {
                    $subResult[$k] = (string)$v;
                }
                $result[] = $subResult;
            }
            return $result;
        } catch (ClientException $e) {
            throw new Exception('Listing of projects failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function getProjectId($name)
    {
        foreach ($this->listProjects() as $p) {
            if (isset($p['name']) && $p['name'] == $name) {
                return $p['id'];
            }
        }
        return false;
    }

    public function initFileUpload()
    {
        try {
            $result = $this->client->post("{$this->baseUri}/sites/{$this->siteId}/fileUploads", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);

            $xml = self::parseResponse($result->getBody());
            $xPath = $xml->xpath('//ts:fileUpload/@uploadSessionId');
            if (!count($xPath)) {
                throw new Exception('Token could not be found in API response: ' . $result->getBody());
            }
            return (string)$xPath[0];
        } catch (ClientException $e) {
            throw new Exception('Init file upload failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function uploadChunk($chunk, $sessionId)
    {
        try {
            $this->client->put("{$this->baseUri}/sites/{$this->siteId}/fileUploads/{$sessionId}", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ],
                'multipart' => [
                    [
                        'name' => 'request_payload',
                        'contents' => '',
                        'headers'  => [
                            'Content-Type' => 'text/xml'
                        ]
                    ],
                    [
                        'name' => 'tableau_file',
                        'contents' => $chunk,
                        'filename' => 'file.tde',
                        'headers'  => [
                            'Content-Type' => 'application/octet-stream'
                        ]
                    ]
                ],
                'isMixed' => true
            ]);
        } catch (ClientException $e) {
            throw new Exception('Upload chunk failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function finishDatasourceUpload($name, $sessionId)
    {
        try {
            $result = $this->client->post(
                "{$this->baseUri}/sites/{$this->siteId}/datasources?uploadSessionId={$sessionId}&datasourceType=tde&overwrite=true",
                [
                    'headers' => [
                        'X-Tableau-Auth' => $this->token
                    ],
                    'multipart' => [
                        [
                            'name' => 'request_payload',
                            'contents' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<tsRequest>
    <datasource name="{$name}">
        <project id="{$this->projectId}" />
    </datasource>
</tsRequest>
XML
                            ,
                            'headers'  => [
                                'Content-Type' => 'text/xml'
                            ]
                        ]
                    ],
                    'isMixed' => true
                ]
            );

            $xml = self::parseResponse($result->getBody());
            $xPath = $xml->xpath('//ts:datasource/@id');
            if (!count($xPath)) {
                throw new Exception('Token could not be found in API response: ' . $result->getBody());
            }
            return (string)$xPath[0];
        } catch (ClientException $e) {
            throw new Exception('Finish upload failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function publishDatasource($name, $filename)
    {
        if (!file_exists($filename)) {
            throw new Exception("File {$filename} does not exist");
        }

        $uploadSessionId = $this->initFileUpload();

        $handle = fopen($filename, "rb");
        while (!feof($handle)) {
            $contents = fread($handle, 64000000);
            $this->uploadChunk($contents, $uploadSessionId);
        }
        fclose($handle);

        return $this->finishDatasourceUpload($name, $uploadSessionId);
    }

    public function getDatasource($datasourceId)
    {
        $result = $this->client->get("{$this->baseUri}/sites/{$this->siteId}/datasources/{$datasourceId}", [
            'headers' => [
                'X-Tableau-Auth' => $this->token
            ]
        ]);
        $xml = self::parseResponse($result->getBody());
        return [
            'id' => $datasourceId,
            'name' => (string)$xml->xpath('//ts:datasource/@name')[0],
            'type' => (string)$xml->xpath('//ts:datasource/@type')[0],
            'project' => (string)$xml->xpath('//ts:project/@id')[0],
            'owner' => (string)$xml->xpath('//ts:owner/@id')[0],
        ];
    }

    public function deleteDatasource($datasourceId)
    {
        $this->client->delete("{$this->baseUri}/sites/{$this->siteId}/datasources/{$datasourceId}", [
            'headers' => [
                'X-Tableau-Auth' => $this->token
            ]
        ]);
    }

    private static function parseResponse($response)
    {
        $xml = new \SimpleXMLElement($response);
        foreach ($xml->getDocNamespaces() as $strPrefix => $strNamespace) {
            if (!$strPrefix) {
                $xml->registerXPathNamespace('ts', $strNamespace);
            }
        }
        return $xml;
    }
}
