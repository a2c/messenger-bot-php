<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Message\Controller\DefaultController;

use Silex\Application;

require_once __DIR__.'/bootstrap.php';

$app->before(function (Request $request) {
    $data = json_decode($request->getContent(), true);
    $request->request->replace(is_array($data) ? $data : array());
});

$app->mount('/', new DefaultController($app));

$app->error(function (\Exception $e, $code) use ($app) {
	$message = str_replace('"', '', $e->getMessage());
	return $app->json($message, $code);
});

$app->run();