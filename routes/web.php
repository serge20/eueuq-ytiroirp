<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'api'], function() use ($router) {
    $router->get('/task/{id}', 'TaskController@show');
    $router->put('/task/{id}', 'TaskController@update');
    
    $router->post('/task', 'TaskController@create');
    $router->post('/batch/task', 'BatchTaskController@create');
    $router->get('/task', 'TaskController@index');
});