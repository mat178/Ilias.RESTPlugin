<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, S.Schneider and T. Hufschmidt <(schaefer|schneider|hufschmidt)@hrz.uni-marburg.de>
 * 2014-2015
 */
namespace RESTController\extensions\roles_v1;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs\RESTLib, \RESTController\libs\AuthLib, \RESTController\libs\TokenLib;
use \RESTController\libs\RESTRequest, \RESTController\libs\RESTResponse;


$app->get('/v1/roles', '\RESTController\libs\AuthMiddleware::authenticateILIASAdminRole', function () use ($app) {

    $request = new RESTRequest($app);
    $model = new RolesModel();
    
    $resp = new RESTResponse($app);
    $model->getAllRoles($request, $resp);
    echo($resp->toJSON());
    

});


?>