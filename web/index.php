<?php

require_once __DIR__.'/../vendor/autoload.php';
$app = new Silex\Application();
$app['debug'] = true;
//echo "hello";
//echo  __DIR__ . '/../views';

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->get('/', function () use ($app) {
 $time = date("F j, Y, g:i a");
 $templateVars = array(
       'msg' => 'Task 2 from Anthony',
       'time' => $time,
      
    );    


return $app['twig']->render('layout.twig', $templateVars);
});

$app->run();

