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

/*$app->get('/', function () {
 $time = "211";
 $templateVars = array(
       'msg' => 'Super Hello World',
       'time' => $time,
       'ip' => '12345'
    );


return $app['twig']->render('layout.twig', array());
//   return 'Index Page';
});*/

$app->run();
