<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

namespace Keboola\TableauServerWriter;

class WriterTest extends \PHPUnit_Framework_TestCase
{
    public function testWriter()
    {
        $writer = new \Keboola\TableauServerWriter\Writer(
            TSW_SERVER_URL,
            TSW_PROJECT_ID,
            TSW_USERNAME,
            TSW_PASSWORD,
            TSW_SITE
        );
        $dataSourceName = 'test-'.uniqid();
        $dataSourceId = $writer->publishDatasource($dataSourceName, 'tests/test.tde');
        $this->assertNotEmpty($dataSourceId);

        $result = $writer->getDatasource($dataSourceId);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals($dataSourceName, $result['name']);

        $writer->deleteDatasource($dataSourceId);
        try {
            $writer->getDatasource($dataSourceId);
            $this->fail('Datasource should not exist any more');
        } catch (\Exception $e) {
            if ($e->getCode() != 404) {
                $this->fail('Datasource should not exist any more, API call should return code 404 but returned: '
                    . $e->getCode());
            }
        }

        $writer->logout();
    }
}
