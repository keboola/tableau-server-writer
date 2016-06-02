<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

use Symfony\Component\Yaml\Yaml;

set_error_handler(
    function ($errno, $errstr, $errfile, $errline, array $errcontext) {
        if (0 === error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
);

require_once(dirname(__FILE__) . "/../vendor/autoload.php");
$arguments = getopt("d::", array("data::"));
if (!isset($arguments['data'])) {
    print "Data folder not set.";
    exit(1);
}
$config = Yaml::parse(file_get_contents($arguments['data'] . "/config.yml"));

try {
    if (!filter_var($config['parameters']['server_url'], FILTER_VALIDATE_URL) !== false) {
        print "{$config['parameters']['server_url']} is not a valid URL";
        exit(1);
    }

    $writer = new \Keboola\TableauServerWriter\Writer(
        $config['parameters']['server_url'],
        $config['parameters']['username'],
        isset($config['parameters']['#password'])
            ? $config['parameters']['#password'] : $config['parameters']['password'],
        isset($config['parameters']['site']) ? $config['parameters']['site'] : null
    );

    if (!empty($config['parameters']['project_id'])) {
        $writer->setProjectId($config['parameters']['project_id']);
    } elseif (!empty($config['parameters']['project_name'])) {
        $projectId = $writer->getProjectId($config['parameters']['project_name']);
        if (!$projectId) {
            print "Project with name {$config['parameters']['project_name']} does not exist on the server. Please check your credentials.";
            exit(1);
        }
        $writer->setProjectId($projectId);
    }

    if (!empty($config['parameters']['get_projects'])) {
        print json_encode($writer->listProjects());
    } else {
        $filesCount = 0;
        foreach (glob($arguments['data'] . "/in/files/*") as $filename) {
            $fileInfo = pathinfo($filename);
            if (!isset($fileInfo['extension']) || $fileInfo['extension'] != 'manifest') {
                $manifest = Yaml::parse(file_get_contents($filename . '.manifest'));
                $writer->publishDatasource($manifest['name'], $filename);
                $filesCount++;
            }
        }
        print "Uploaded {$filesCount} files.";
    }

    $writer->logout();
    exit(0);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    print $e->getMessage();
    exit(1);
} catch (\Keboola\TableauServerWriter\Exception $e) {
    //@TODO Handle errors
    print $e->getMessage();
    exit(1);
}
