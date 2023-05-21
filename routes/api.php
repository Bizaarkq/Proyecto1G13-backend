<?php
/**
 * Rutas para API de CPA
 */
$router->group(['prefix' => 'api'], function () use ($router) {
    $router->group(['namespace' => '\Rap2hpoutre\LaravelLogViewer'], function() use ($router) {
        $router->get('logs', 'LogViewerController@index');
    });

    $router->post('auth/login', 'AuthController@login');
    $router->group(['middleware' => 'auth', 'prefix' => 'auth'], function() use ($router) {
        $router->post('logout', 'AuthController@logout');
        $router->post('me', 'AuthController@me');
    });
});

