<?php
/**
 * @package wr-tableau-server
 * @copyright 2015 Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */

defined('TSW_SERVER_URL')
|| define('TSW_SERVER_URL', getenv('TSW_SERVER_URL') ? getenv('TSW_SERVER_URL') : 'https://online.tableau.com');

defined('TSW_PROJECT_ID')
|| define('TSW_PROJECT_ID', getenv('TSW_PROJECT_ID') ? getenv('TSW_PROJECT_ID') : null);

defined('TSW_USERNAME')
|| define('TSW_USERNAME', getenv('TSW_USERNAME') ? getenv('TSW_USERNAME') : 'username');

defined('TSW_PASSWORD')
|| define('TSW_PASSWORD', getenv('TSW_PASSWORD') ? getenv('TSW_PASSWORD') : 'pass');

defined('TSW_SITE')
|| define('TSW_SITE', getenv('TSW_SITE') ? getenv('TSW_SITE') : null);


require_once __DIR__ . '/../vendor/autoload.php';
