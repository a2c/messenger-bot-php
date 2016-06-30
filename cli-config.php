<?php
use Doctrine\ORM\Tools\Console\ConsoleRunner;

require_once 'web/bootstrap.php';

// $entityManager = $app['orm.em'];

return ConsoleRunner::createHelperSet($entityManager);
