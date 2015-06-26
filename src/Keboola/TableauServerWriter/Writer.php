<?php
/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 24/06/15
 * Time: 16:17
 */

namespace Keboola\TableauServerWriter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Writer
{
    /** @var Client  */
    protected $client;
    protected $token;
    protected $siteId;

    public function __construct($serverUrl, $username, $password, $site = null)
    {
        $this->client = new Client([
            'base_uri' => "{$serverUrl}/api/2.0/sites/{$site}",
            'timeout' => 60
        ]);

        $this->login($username, $password, $site);
    }

    public function login($username, $password, $site = null)
    {
        try {
            $result = $this->client->post("/api/2.0/auth/signin", [
                'body' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<tsRequest>
    <credentials name="{$username}" password="{$password}">
        <site contentUrl="{$site}" />
    </credentials>
</tsRequest>
XML
            ]);

            $xml = new \SimpleXMLElement($result->getBody());
            foreach($xml->getDocNamespaces() as $strPrefix => $strNamespace) {
                if (!$strPrefix) {
                    $xml->registerXPathNamespace('ts', $strNamespace);
                }
            }

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
            $this->client->post("/api/2.0/auth/signout", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);
        } catch (ClientException $e) {
            throw new Exception('Logout from API failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function initFileUpload()
    {
        try {
            $result = $this->client->post("/api/2.0/sites/{$this->siteId}/fileUploads", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);

            $xml = new \SimpleXMLElement($result->getBody());
            foreach($xml->getDocNamespaces() as $strPrefix => $strNamespace) {
                if (!$strPrefix) {
                    $xml->registerXPathNamespace('ts', $strNamespace);
                }
            }

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
            $this->client->put("/api/2.0/sites/{$this->siteId}/fileUploads/{$sessionId}", [
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
                        'filename' => 'file',
                        'headers'  => [
                            'Content-Type' => 'application/octet-stream'
                        ]
                    ]
                ]
            ]);
        } catch (ClientException $e) {echo $e->getRequest()->getUri().PHP_EOL.PHP_EOL;
            echo $e->getRequest()->getBody().PHP_EOL.PHP_EOL;
            throw new Exception('Upload chunk failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function finishUpload($sessionId)
    {
        try {
            $r = $this->client->post("/api/2.0/sites/{$this->siteId}/datasources?uploadSessionId={$sessionId}&datasourceType=tde&overwrite=true", [
                'headers' => [
                    'X-Tableau-Auth' => $this->token
                ]
            ]);echo $r->getBody().PHP_EOL;
        } catch (ClientException $e) {
            throw new Exception('Upload chunk failed with response: ' . $e->getResponse()->getBody());
        }
    }

    public function publishFile($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception("File {$filename} does not exist");
        }

        $uploadSessionId = $this->initFileUpload();

        $handle = fopen($filename, "rb");
        while (!feof($handle)) {
            $contents = fread($handle, 12400);
            $this->uploadChunk($contents, $uploadSessionId);
        }
        fclose($handle);

        $this->finishUpload($uploadSessionId);
    }
}