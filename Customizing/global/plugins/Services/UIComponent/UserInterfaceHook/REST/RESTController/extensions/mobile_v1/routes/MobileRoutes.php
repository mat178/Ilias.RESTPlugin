<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, S.Schneider and T. Hufschmidt <(schaefer|schneider|hufschmidt)@hrz.uni-marburg.de>
 * 2014-2015
 */
namespace RESTController\extensions\mobile_v1;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs\RESTLib, \RESTController\libs\AuthLib, \RESTController\libs\TokenLib;
use \RESTController\libs\RESTRequest, \RESTController\libs\RESTResponse;

use \RESTController\extensions\admin\DescribrModel;
use \RESTController\extensions\users_v1\UsersModel;
use \RESTController\extensions\courses_v1\CoursesModel;
use \RESTController\extensions\desktop_v1\DesktopModel;
use \RESTController\extensions\groups_v1\GroupsModel;
use \RESTController\extensions\contacts_v1\ContactsModel;
use \RESTController\extensions\calendar_v1\CalendarModel;


/*
 * Prototypical implementation of some rest endpoints for development
 * and testing.
 */

$app->group('/m', function () use ($app) {

    $app->get('/courses/:id', function ($id) use ($app) {


        $id_type = $app->request()->params("id_type");
        $id_type = $id_type = null ? 'ref_id' : $id_type;
        $obj_id = -1;
        if ($id_type == 'obj_id')
        {
            $obj_id = $id;
        } else
        {
            $obj_id = RESTLib::refid_to_objid($id);
        }

        $result = array();
        $model = new DescribrModel();
        $a_descr = $model->describeIliasObject($obj_id);
        $result['object_description'] = $a_descr;

        $app->response()->header('Content-Type', 'application/json');
        echo json_encode($result);

    });

    /**
     *  Loads the following data:
     * - User / system information
     * - Personal courses, which are flagged "online"
     * - Meta-information about the courses
     * - Lists of contents of the personal courses // see of contacts > my courses, my groups
     * - People participating the courses
     * - Dates (Note: System Caldendar must be activated)
     *  - List
     *  - ICAL (ics) Feed Url
     *  In contrast to version 1, the json is structured in a better and more concise way.
     */
    $app->get('/desk', function () use ($app) {
        $t_start = microtime();
        $result = array();

        // TODO: extract user_id from valid token
        $user_id = 6;//225;//6;//361; // testuser
        //$user = RESTLib::userIdtoLogin($user_id);

        RESTLib::initAccessHandling();

        $userModel = new UsersModel();
        $userData = $userModel->getBasicUserData($user_id);
        $result['user'] = $userData;

        $courseModel = new CoursesModel();
        $my_courses = $courseModel->getCoursesOfUser($user_id);

        $repository_items = array();
        foreach ($my_courses as $course_refid)
        {
            //$my_courses [] = $course_refid;
            $courseContents = $courseModel->getCourseContent($course_refid);
            $children_ref_ids = array();
            foreach ($courseContents as $item) {
                $children_ref_ids[] = $item['ref_id'];
                $repository_items[$item['ref_id']] = $item;
            }
            $course_item = $courseModel->getCourseInfo($course_refid);
            $course_item['children_ref_ids'] = $children_ref_ids;
            $repository_items[$course_refid] = $course_item;
        }
        $result['ritems'] = $repository_items;

        $desktopModel = new DesktopModel();
        $pditems = $desktopModel->getPersonalDesktopItems($user_id);
        $pdrefids = array();
        foreach ($pditems as $pditem) {
            $pdrefids[] = $pditem['ref_id'];
        }
        $result['mypersonaldesktop'] = $pdrefids;
        $result['mycourses'] = $my_courses;

        $grpModel = new GroupsModel();
        $my_groups = $grpModel->getGroupsOfUser($user_id);
        $result['mygroups'] = $my_groups;

        // Contacts
        $contactModel = new ContactsModel();
        $data = $contactModel->getMyContacts($user_id);
        $result['contacts']['my_contacts'] = $data;
        // TODO: CourseContacts, GroupContacts

        // Calendar
        $calModel = new CalendarModel();
        $data = $calModel->getIcalAdress($user_id);
        $result['calendar']['ical_url'] = $data;
        $data = $calModel->getCalUpcomingEvents($user_id);
        $result['calendar']['events'] = $data;

        $t_end = microtime();
        $result['status']['duration'] = abs($t_end-$t_start);
        $result['status']['tstamp'] = time();
        echo json_encode($result);
    });

    /*$app->get('/search/',  function () use ($app) {
        $request = new RESTRequest($app);
        $response = new RESTResponse($app);

        try {
            $query = $request->getParam('q');
        } catch (\Exception $e) {
            $query = '';
        }

        // Using anonymous function PHP 5.3.0>=
        spl_autoload_register(function($class){
            if (file_exists($_SERVER['DOCUMENT_ROOT'].REST_PLUGIN_DIR.'/RESTController/extensions/mobile_v1/addon/' . $class . '.php')) {
                require_once($_SERVER['DOCUMENT_ROOT'].REST_PLUGIN_DIR.'/RESTController/extensions/mobile_v1/addon/' . $class . '.php');
            }
        });

        // Using elastica 1.3.4 (corresponding to elastic search 1.3.4)
        $elasticaClient = new \Elastica\Client();
        $esQuery = '{
            "query": {
                "fuzzy_like_this" : {
                    "fields" : ["title"],
                    "like_text" : "'.$query.'",
                    "max_query_terms" : 25
                }
            }
        }';
        $path = 'jdbc' . '/_search';

        $esResponse = $elasticaClient->request($path, \Elastica\Request::POST, $esQuery);
        $esResponseArray = $esResponse->getData();
        $searchResults = array();
        foreach ($esResponseArray['hits']['hits'] as $hit) {
            $searchResults[] = array('obj_id' => $hit['_source']['obj_id'],
                'type' => $hit['_source']['type'],
                'title' => $hit['_source']['title'],
                'age' => $hit['_source']['ageindays'],
                'score' => $hit['_score']);
        }

        $response->addData('search_results', $searchResults);
        $response->setMessage('You have been searching for: "'.$query.'"');
        $response->send();
    });*/


});