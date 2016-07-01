<?php

use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

$config     = json_decode(file_get_contents(__DIR__ . '/../config/default.json'), true);
$app['config'] = $config['parameters'];

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/access.log',
));