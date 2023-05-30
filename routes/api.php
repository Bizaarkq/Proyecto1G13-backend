<?php
/**
 * Rutas para API de CPA
 */
$router->group(['prefix' => 'api'], function () use ($router) {
    //logs
    $router->group(['namespace' => '\Rap2hpoutre\LaravelLogViewer'], function() use ($router) {
        $router->get('logs', 'LogViewerController@index');
    });

    //auth routes
    $router->post('auth/login', 'AuthController@login');
    
    $router->group(['middleware' => 'auth', 'prefix' => 'auth'], function() use ($router) {
        $router->post('logout', 'AuthController@logout');
        $router->get('me', 'AuthController@me');
    });

    //revision routes
    $router->group(['middleware' => 'auth', 'prefix' => 'revision'], function() use ($router) {
        $router->group(['middleware' => 'role:docente'], function() use ($router) {
            $router->get('docente', 'RevisionController@getRevisionesDocente');
            $router->get('pendientes', 'RevisionController@getListadoRevPendientes');
            $router->post('aprobar', 'RevisionController@aprobarRevision');
        });

        $router->group(['middleware' => 'role:estudiante'], function() use ($router) {
            $router->get('estudiante', 'RevisionController@getRevisionesEstudiante');
            $router->post('solicitar', 'RevisionController@solicitarRevision');
            $router->get('evaluaciones', 'RevisionController@getEvaluacionesRevision');
        });
    });

    //evaluacion routes
    $router->group(['middleware' => 'auth', 'prefix' => 'evaluacion'], function() use ($router) {
        $router->group(['middleware' => 'role:docente'], function() use ($router) {
            //$router->get('docente', 'EvaluacionController@getEvaluacionesDocente');
            $router->get('evaluaciones-estudiantes/{id_ciclo}/{id_materia}', 'EvaluacionController@list');
            $router->post('crear', 'EvaluacionController@create');
            $router->post('marcar-asistencia', 'EvaluacionController@marcarAsistencia');
            $router->post('registrar-nota', 'EvaluacionController@registrarNota');
            //$router->post('editar', 'EvaluacionController@editarEvaluacion');
            //$router->post('eliminar', 'EvaluacionController@eliminarEvaluacion');
        });

        $router->group(['middleware' => 'role:estudiante'], function() use ($router) {
            $router->get('estudiante', 'EvaluacionController@getEvaluacionesEstudiante');
        });
    });
});

