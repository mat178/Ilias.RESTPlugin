<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\extensions\umr_v1;


// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs as Libs;


/**
 *
 */
class BulkRequest {
  /**
   *
   */
  protected static function fetchDataRecursive($accessToken, $refIdData) {
    foreach ($refIdData as $obj) {
      if ($obj['children']) {
        $children       = array_diff_key($obj['children'], $refIdData);
        if ($children && count($children) > 0) {
          $childrenData = RefIdData::getData($accessToken, $children);

          // TODO: Why is $childrenData empty sometimes (eg. ref_id=65)?

          if ($childrenData) {
            $newData      = self::fetchDataRecursive($accessToken, $childrenData);

            if (is_array($newData))
              $refIdData  = $refIdData + $newData;
          }
        }
      }
    }

    return $refIdData;
  }


  /**
   *
   */
  public static function getBulk($accessToken) {
    // Use models to fetch data
    $calendars  = Calendars::getAllCalendars($accessToken);
    $contacts   = Contacts::getAllContacts($accessToken);
    $events     = Events::getAllEvents($accessToken);
    $user       = UserInfo::getUserInfo($accessToken);
    $cag        = MyCoursesAndGroups::getMyCoursesAndGroups($accessToken);
    $desktop    = PersonalDesktop::getPersonalDesktop($accessToken);

    // Fetch data for refIds
    $refIds     = array_merge($cag, $desktop);
    $refIds     = array_unique($refIds, SORT_NUMERIC);
    $refIdData  = RefIdData::getData($accessToken, $refIds);
    $refIdData  = self::fetchDataRecursive($accessToken, $refIdData);

    // Output result
    return array(
      'calendars'  => $calendars,
      'contacts'   => $contacts,
      'events'     => $events,
      'user'       => $user,
      'cag'        => $cag,
      'desktop'    => $desktop,
      'refIdData'  => $refIdData
    );
  }
}
