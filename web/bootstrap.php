<?php

use Silex\Provider\DoctrineServiceProvider;

$loader = require __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();

// $app->register(new DoctrineServiceProvider, array(
//     'db.options' => array(
// 	    'driver'   => 'pdo_mysql',
// 	    'charset'  => 'utf8',
// 	    'host'     => 'localhost',
// 	    'dbname'   => 'message_app',
// 	    'user'     => 'root',
// 	    'password' => ''
//     )   
// ));

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/access.log',
));