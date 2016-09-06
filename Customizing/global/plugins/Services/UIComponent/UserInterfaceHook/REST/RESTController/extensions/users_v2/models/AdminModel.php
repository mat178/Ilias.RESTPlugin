<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\extensions\users_v2;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs            as Libs;
use \RESTController\libs\Exceptions as LibExceptions;


/**
 * Class:
 *
 */
class AdminModel extends Libs\RESTModel {
  // Allow to re-use status messages and codes
  const MSG_RBAC_CREATE_DENIED  = 'Permission to create/modify user-account denied by RBAC-System.';
  const ID_RBAC_CREATE_DENIED   = 'RESTController\\extensions\\users_v2\\Admin::ID_RBAC_CREATE_DENIED';
  const MSG_RBAC_READ_DENIED    = 'Permission to read user-account denied by RBAC-System.';
  const ID_RBAC_READ_DENIED     = 'RESTController\\extensions\\users_v2\\Admin::ID_RBAC_READ_DENIED';
  const MSG_NO_GLOBAL_ROLE      = 'Access-token user has no global role that could be inherited by new users.';
  const ID_NO_GLOBAL_ROLE       = 'RESTController\\extensions\\users_v2\\Admin::ID_NO_GLOBAL_ROLE';
  const MSG_USER_PICTURE_EMPTY  = 'User picture does not contain any base64-encoded data.';
  const ID_USER_PICTURE_EMPTY   = 'RESTController\\extensions\\users_v2\\Admin::ID_USER_PICTURE_EMPTY';
  const MSG_INVALID_MODE        = 'Invalid mode, must either be \'create\' or \'update\'.';
  const ID_INVALID_MODE         = 'RESTController\\extensions\\users_v2\\Admin::ID_INVALID_MODE';
  const MSG_INVALID_FIELD       = 'Given user-data contains an invalid field: {{field}}';
  const ID_INVALID_FIELD        = 'RESTController\\extensions\\users_v2\\Admin::ID_INVALID_FIELD';
  const MSG_MISSING_FIELD       = 'Given user-data is missing a required field: {{field}}';
  const ID_MISSING_FIELD        = 'RESTController\\extensions\\users_v2\\Admin::ID_MISSING_FIELD';


  //
  const DEFAULT_ROLE_ID     = 4;
  const SYSTEM_ROLE_ID      = SYSTEM_ROLE_ID;
  const ANONYMOUS_ROLE_ID   = ANONYMOUS_ROLE_ID;
  const ROLE_FOLDER_ID      = ROLE_FOLDER_ID;
  const USER_FOLDER_ID      = USER_FOLDER_ID;


  //
  const MODE_CREATE = 'create';
  const MODE_UPDATE = 'update';


  //
  const fields = array(
    'login',
    'id',
    'auth_mode',
    'client_ip',
    'active',
    'time_limit_from',
    'time_limit_until',
    'time_limit_unlimited',
    'interests_general',
    'interests_help_offered',
    'interests_help_looking',
    'latitude',
    'longitude',
    'loc_zoom',
    'udf',
    'language',
    'birthday',
    'gender',
    'institution',
    'department',
    'street',
    'city',
    'zipcode',
    'country',
    'sel_country',
    'phone_office',
    'phone_home',
    'phone_mobile',
    'fax',
    'matriculation',
    'hobby',
    'referral_comment',
    'delicious',
    'email',
    'im_icq',
    'im_yahoo',
    'im_msn',
    'im_aim',
    'im_skype',
    'im_jabber',
    'im_voip',
    'title',
    'firstname',
    'lastname',
    'hits_per_page',
    'show_users_online',
    'hide_own_online_status',
    'skin_style',
    'session_reminder_enabled',
    'passwd',
    'ext_account',
    'disk_quota',
    'wsp_disk_quota',
    'userfile',
    'roles',
    'send_mail'
  );


  /**
   *
   */
  protected static function GetDefaultValue($field) {
    // Fetch reference to ILIAS objects
    global $ilUser, $ilSetting, $ilClientIniFile;

    // Return default value based on field
    switch ($field) {
      // Set default email notification trigger
      case 'send_mail':
        return $ilUser->getPref('send_info_mails') == 'y';
      // Set default language
      case 'language':
        return $ilSetting->get('language');
      // Set default skin
      case 'skin_style':
        return sprintf('%s:%s', $ilClientIniFile->readVariable('layout', 'skin'), $ilClientIniFile->readVariable('layout', 'style'));
      // The default time-limit (from)
      case 'time_limit_from':
        return time();
      // The default time-limit (until)
      case 'time_limit_until':
        return time();
      // Set to unlimited by default
      case 'time_limit_unlimited':
        return 1;
      // Show online status
      case 'hide_own_online_status':
        return false;
      // Enable session reminder
      case 'session_reminder_enabled':
        return $ilSetting->get('session_reminder_enabled');
      // Set to 'default' authenticatio mode
      case 'auth_mode':
        return 'default';
      // Set default value for HitsPerPage
      case 'hits_per_page':
        return $ilSetting->get('hits_per_page');
      // Show online users?
      case 'show_users_online':
        return $ilSetting->get('show_users_online');
      // New accounts are active by default
      case 'active':
        return 1;
      // Fetch default role
      case 'roles':
        return self::DEFAULT_ROLE_ID;
    }
  }


  /**
   *
   */
  protected static function TransformField($field, $value) {
    // Transform based on field
    switch ($field) {
      // Requires unix-time
      case 'time_limit_from':
      case 'time_limit_until':
        return self::GetUnixTime($value);
      // Requires date string without time value
      case 'birthday':
        return self::GetISODate($value);
      // Requires int instead of boolean for some reason (Tip: Its ILIAS... -.-)
      case 'time_limit_unlimited':
      case 'active':
      case 'session_reminder_enabled':
        if (is_string($value))
          $value = strcmp(strtolower(substr($value, 0, 1)), 'y');
        return intval($value);
      // Requires boolean
      case 'send_mail':
        if (is_string($value))
          return strcmp(strtolower(substr($value, 0, 1)), 'y');
        elseif (is_int($value))
          return $value > 0;
        return $value;
      // Needs to be 'm' / 'f'
      case 'gender':
        if (is_string($value))
          return strtolower(substr($value, 0, 1));
        return $value;
      // Needs to be an integer value
      case 'id':
      case 'hits_per_page':
      case 'disk_quota':
      case 'wsp_disk_quota':
      case 'loc_zoom':
        return intval($value);
      // Needs to be 'y' / 'n' instead of boolean (Tip: You guessed it, because 'ILIAS' ...)
      case 'hide_own_online_status':
      case 'show_users_online':
        if (is_bool($value))
          return ($value) ? 'y' : 'n';
        if (is_string($value))
          return strtolower(substr($value, 0, 1));
        return $value;
      // Needs to be numeric (float)
      case 'latitude':
      case 'longitude':
        return floatval($value);
      // Needs to be an array
      case 'interests_general':
      case 'interests_help_offered':
      case 'interests_help_looking':
      case 'roles':
      case 'udf':
        if (!is_array($value))
          return array($value);
        return $value;
      // No transformation for any other field
      default:
        return $value;
    }
  }


  /**
   *
   */
  protected static function IsMissingField($field, $value, $mode) {

    // Check required fields for edit mode
    if ($mode == self::MODE_UPDATE) {
      // Check based on field
      switch ($field) {
        // User-ID is required
        case 'id':
          return !isset($value);
        // No other field is required in edit mode
        default:
          return false;
      }
    }

    // Check required fields for new user mode
    elseif ($mode == self::MODE_CREATE) {
      // Include required classes
      include_once('./Services/User/classes/class.ilUserDefinedFields.php');

      // Load ILIAS objects
      global $ilSetting;

      // Check based on field
      switch ($field) {
        // ID is created
        case 'id':
          return false;
        // This settings are always required
        case 'login':
        case 'passwd':
        case 'firstname':
        case 'lastname':
          return !isset($value);
        // Check User-Defined-Values against ILIAS settings
        case 'udf':
          $instance    = \ilUserDefinedFields::_getInstance();
          $definitions = $instance->getDefinitions();
          foreach ($definitions as $defField => $definition)
            if ($definition['required'] && !array_key_exists($defField, $value))
              return true;
          return false;
        // Check all other values against ILIAS settings
        default:
          // Fetch ILIAS settings for checking requirement
          $settings = $ilSetting->getAll();

          // Check if missing and set to required
          $reqField = sprintf('require_%s', $field);
          return !isset($value) && array_key_exists($reqField, $settings) && $settings[$reqField] == 1;
      }
    }
  }


  /**
   *
   */
  protected static function IsValidField($field, $value, $mode, $refId) {
    // Load ILIAS objects
    global $lng;

    // Include required classes for validation
    include_once('./Services/Style/classes/class.ilObjStyleSettings.php');
    include_once('./Services/Style/classes/class.ilStyleDefinition.php');
    include_once('./Services/Authentication/classes/class.ilAuthUtils.php');

    //
    switch ($field) {
      // Validate login
      case 'login':
        return is_string($value) && \ilUtil::isLogin($value) && ($mode != self::MODE_CREATE || !\ilObjUser::_loginExists($value));
      // Validate password
      case 'passwd':
        return is_string($value) && \ilUtil::isPassword($value);
      // Validate email
      case 'email':
        return is_string($value) && \ilUtil::is_email($value);
      // Validate language
      case 'language':
        return is_string($value) && in_array($value, $lng->getInstalledLanguages());
      // Validate skin and style
      case 'skin_style':
        // Needs to be a string
        if (!is_string($value))
          return false;

        // Extract skin/style values
        $skin_style = explode(':', $value);
        $skin       = $skin_style[0];
        $style      = $skin_style[1];

        // Check wether skin is available and active
        return \ilStyleDefinition::styleExists($skin, $style) && \ilObjStyleSettings::_lookupActivatedStyle($skin, $style);
      // Validate authentication mode
      case 'auth_mode':
        $modes = \ilAuthUtils::_getActiveAuthModes();
        return $modes[$value] == 1;
      // Validate list of roles
      case 'roles':
        return is_array($value) &&  self::ValidateRoles($value, $refId);
      // Validate 'y' / 'n' values
      case 'hide_own_online_status':
      case 'show_users_online':
        return $value === 'y' || $value === 'n';
      // Validate integer values
      case 'id':
      case 'time_limit_from':
      case 'time_limit_until':
      case 'loc_zoom':
      case 'hits_per_page':
      case 'disk_quota':
      case 'wsp_disk_quota':
        return is_int($value);
      // Validate 0 / 1 values
      case 'time_limit_unlimited':
      case 'active':
      case 'session_reminder_enabled':
        return ($value === 0 || $value === 1);
      // Validate client ip
      case 'client_ip':
        return is_string($value) &&  (strlen($value) === 0 || filter_var($value, FILTER_VALIDATE_IP) !== false);
      // Validate string values
      case 'interests_general':
      case 'interests_help_offered':
      case 'interests_help_looking':
      case 'institution':
      case 'department':
      case 'street':
      case 'city':
      case 'country':
      case 'sel_country':
      case 'phone_office':
      case 'phone_home':
      case 'phone_mobile':
      case 'fax':
      case 'hobby':
      case 'referral_comment':
      case 'delicious':
      case 'im_icq':
      case 'im_yahoo':
      case 'im_msn':
      case 'im_aim':
      case 'im_skype':
      case 'im_jabber':
      case 'im_voip':
      case 'title':
      case 'firstname':
      case 'lastname':
      case 'ext_account':
        return is_string($value);
      // Validate string / number values
      case 'matriculation':
      case 'zipcode':
        return is_string($value) || is_numeric($value);
      // Valiate float values
      case 'latitude':
      case 'longitude':
        return is_float($value);
      // Validate array values
      case 'udf':
        return is_array($value);
      // Validate gender values
      case 'gender':
        return $value == 'm' || $value == 'f' || $value == 't';
      // Validate userfile data
      case 'userfile':
        return preg_match('#^data:image/\w+;base64,#i', $value) === 1;
      // Validate birthday format
      case 'birthday':
        return preg_match('/^(\d{4})-(\d{2})-(\d{2}))$/', $value) === 1;
      // Validate boolean values
      case 'send_mail':
        return is_bool($value);
      // All other fields are valid by default
      default:
        return true;
    }
  }


  /**
   *
   */
  public static function CheckUserData($userData, $mode = self::MODE_CREATE, $refId = self::USER_FOLDER_ID) {
    // Set default values for (optional) missing parameters
    if ($mode == self::MODE_CREATE)
      foreach (self::fields as $field)
        if (!array_key_exists($field, $userData)) {
          $default = self::GetDefaultValue($field);
          if (isset($default))
            $userData[$field] = $default;
        }

    // Transform input values to be more flexible (transform time formats, string/booleans/integer as required)
    foreach ($userData as $field => $value)
      $userData[$field] = self::TransformField($field, $value);

    // Check all fields
    foreach (self::fields as $field)
      // Throw if field is required and missing
      if (self::IsMissingField($field,$userData[$field], $mode))
        throw new LibExceptions\Parameter(
          self::MSG_MISSING_FIELD,
          self::ID_MISSING_FIELD,
          array(
            'field' => $field
          )
        );

    foreach ($userData as $field => $value)
      // Check for invalid parameters
      if (!self::IsValidField($field, $value, $mode, $refId))
        throw new LibExceptions\Parameter(
          self::MSG_INVALID_FIELD,
          self::ID_INVALID_FIELD,
          array(
            'field' => $field,
            'value' => $userData[$field]
          )
        );

    // Return updated user data
    return $userData;
  }


  /**
   * Note 1:
   *  refID is either self::USER_FOLDER_ID, which in the context of ILIAS means the Admin-GUI
   *  or the Reference-ID of a categorie or organisational-unit for local administration.
   *
   * Note 2:
   *  The RBAC-System needs to be initialized with the access-token user account. (RESTIlias::loadIlUser())
   *
   * Note 3:
   *  This method does not do any input validation, this is the responisbility of functions like self::CheckUserData().
   */
  public static function StoreUserData($userData, $mode = self::MODE_CREATE, $refId = self::USER_FOLDER_ID) {
    // Import ILIAS systems (all praise the glorious 'DI-System')
    global $rbacsystem, $rbacadmin, $rbacreview, $ilSetting, $ilUser;

    // Make sure mode is correct
    if ($mode != self::MODE_CREATE && $mode != self::MODE_UPDATE)
      throw new LibExceptions\Parameter(
        self::MSG_INVALID_MODE,
        self::ID_INVALID_MODE
      );

    // Will contain return values if any
    $result = array();

    // Check rights to create user
    if ($mode == self::MODE_CREATE) {
      // Check of user is allowd to create user globally or in given category/org-unit
      if (!$rbacsystem->checkAccess('create_usr', $refId) && !$ilAccess->checkAccess('cat_administrate_users', '', $refId))
        throw new LibExceptions\RBAC(
          self::MSG_RBAC_CREATE_DENIED,
          self::ID_RBAC_CREATE_DENIED
        );

      // Create new user object
      $userObj = new \ilObjUser();
      $userObj->setLogin($userData['login']);
    }
    // Check rights to edit user
    else {
      // Check for local administration access-rights (Note: getTimeLimitOwner() should be $refId for new users)
      if ($refId != USER_FOLDER_ID && !$rbacsystem->checkAccess('cat_administrate_users', $userObj->getTimeLimitOwner()))
        throw new LibExceptions\RBAC(
          self::MSG_RBAC_CREATE_DENIED,
          self::ID_RBAC_CREATE_DENIED
        );

      // Check for Admin-GUI access-rights to users
      if ($refId == USER_FOLDER_ID && !$rbacsystem->checkAccess('visible,read', $refId))
        throw new LibExceptions\RBAC(
          self::MSG_RBAC_READ_DENIED,
          self::ID_RBAC_READ_DENIED
        );

      // Load user object
      $userObj = new \ilObjUser($userData['id']);
      if (self::HasUserValue($userData, 'login'))
        $userObj->updateLogin($userData['login']);
    }

    // Alternative: $userObj->assignData($userData);

    // Update time-limit owner (since ref-id is always required)
    $userObj->setTimeLimitOwner($refId);

    // Set user-values
    if (self::HasUserValue($userData, 'auth_mode'))
      $userObj->setAuthMode($userData['auth_mode']);
    if (self::HasUserValue($userData, 'client_ip'))
      $userObj->setClientIP($userData['client_ip']);
    if (self::HasUserValue($userData, 'active'))
      $userObj->setActive($userData['active'], $ilUser->getId());
    if (self::HasUserValue($userData, 'time_limit_from'))
      $userObj->setTimeLimitFrom($userData['time_limit_from']);
    if (self::HasUserValue($userData, 'time_limit_until'))
      $userObj->setTimeLimitUntil($userData['time_limit_until']);
    if (self::HasUserValue($userData, 'time_limit_unlimited'))
      $userObj->setTimeLimitUnlimited($userData['time_limit_unlimited']);
    if (self::HasUserValue($userData, 'interests_general'))
      $userObj->setGeneralInterests($userData['interests_general']);
    if (self::HasUserValue($userData, 'interests_help_offered'))
      $userObj->setOfferingHelp($userData['interests_help_offered']);
    if (self::HasUserValue($userData, 'interests_help_looking'))
      $userObj->setLookingForHelp($userData['interests_help_looking']);
    if (self::HasUserValue($userData, 'latitude'))
      $userObj->setLatitude($userData['latitude']);
    if (self::HasUserValue($userData, 'longitude'))
      $userObj->setLongitude($userData['longitude']);
    if (self::HasUserValue($userData, 'loc_zoom'))
      $userObj->setLocationZoom($userData['loc_zoom']);
    if (self::HasUserValue($userData, 'udf'))
      $userObj->setUserDefinedData($userData['udf']);
    if (self::HasUserValue($userData, 'language') && self::IsChangeable('language', $refId))
      $userObj->setLanguage($userData['language']);
    if (self::HasUserValue($userData, 'birthday') && self::IsChangeable('birthday', $refId))
      $userObj->setBirthday($userData['birthday']);
		if (self::HasUserValue($userData, 'gender') && self::IsChangeable('gender', $refId))
			$userObj->setGender($userData['gender']);
    if (self::HasUserValue($userData, 'institution') && self::IsChangeable('institution', $refId))
			$userObj->setInstitution($userData['institution']);
		if (self::HasUserValue($userData, 'department') && self::IsChangeable('department', $refId))
			$userObj->setDepartment($userData['department']);
		if (self::HasUserValue($userData, 'street') && self::IsChangeable('street', $refId))
			$userObj->setStreet($userData['street']);
		if (self::HasUserValue($userData, 'city') && self::IsChangeable('city', $refId))
			$userObj->setCity($userData['city']);
		if (self::HasUserValue($userData, 'zipcode') && self::IsChangeable('zipcode', $refId))
			$userObj->setZipcode($userData['zipcode']);
		if (self::HasUserValue($userData, 'country') && self::IsChangeable('country', $refId))
			$userObj->setCountry($userData['country']);
		if (self::HasUserValue($userData, 'sel_country') && self::IsChangeable('sel_country', $refId))
			$userObj->setSelectedCountry($userData['sel_country']);
		if (self::HasUserValue($userData, 'phone_office') && self::IsChangeable('phone_office', $refId))
			$userObj->setPhoneOffice($userData['phone_office']);
		if (self::HasUserValue($userData, 'phone_home') && self::IsChangeable('phone_home', $refId))
			$userObj->setPhoneHome($userData['phone_home']);
		if (self::HasUserValue($userData, 'phone_mobile') && self::IsChangeable('phone_mobile', $refId))
			$userObj->setPhoneMobile($userData['phone_mobile']);
		if (self::HasUserValue($userData, 'fax') && self::IsChangeable('fax', $refId))
			$userObj->setFax($userData['fax']);
		if (self::HasUserValue($userData, 'matriculation') && self::IsChangeable('matriculation', $refId))
			$userObj->setMatriculation($userData['matriculation']);
		if (self::HasUserValue($userData, 'hobby') && self::IsChangeable('hobby', $refId))
			$userObj->setHobby($userData['hobby']);
		if (self::HasUserValue($userData, 'referral_comment') && self::IsChangeable('referral_comment', $refId))
			$userObj->setComment($userData['referral_comment']);
    if (self::HasUserValue($userData, 'delicious') && self::IsChangeable('delicious', $refId))
      $userObj->setDelicious($userData['delicious']);
		if (self::HasUserValue($userData, 'email') && self::IsChangeable('email', $refId)) {
			$userObj->setEmail($userData['email']);
      $userObj->setDescription($userObj->getEmail());
    }
    if (self::IsChangeable('instant_messengers', $refId)) {
      if (self::HasUserValue($userData, 'im_icq'))
        $userObj->setInstantMessengerId('icq',    $userData['im_icq']);
      if (self::HasUserValue($userData, 'im_yahoo'))
        $userObj->setInstantMessengerId('yahoo',  $userData['im_yahoo']);
      if (self::HasUserValue($userData, 'im_msn'))
        $userObj->setInstantMessengerId('msn',    $userData['im_msn']);
      if (self::HasUserValue($userData, 'im_aim'))
        $userObj->setInstantMessengerId('aim',    $userData['im_aim']);
      if (self::HasUserValue($userData, 'im_skype'))
        $userObj->setInstantMessengerId('skype',  $userData['im_skype']);
      if (self::HasUserValue($userData, 'im_jabber'))
        $userObj->setInstantMessengerId('jabber', $userData['im_jabber']);
      if (self::HasUserValue($userData, 'im_voip'))
        $userObj->setInstantMessengerId('voip',   $userData['im_voip']);
    }
    if (self::HasUserValue($userData, 'title') && self::IsChangeable('title', $refId)) {
      $userObj->setUTitle($userData['title']);

      // Update fullname and full title based on firstname, lastname and user-title
      $userObj->setFullname();
      $userObj->setTitle($userObj->getFullname());
    }
    if (self::HasUserValue($userData, 'firstname') && self::IsChangeable('firstname', $refId)) {
      $userObj->setFirstname($userData['firstname']);

      // Update fullname and full title based on firstname, lastname and user-title
      $userObj->setFullname();
      $userObj->setTitle($userObj->getFullname());
    }
    if (self::HasUserValue($userData, 'lastname') && self::IsChangeable('lastname', $refId)) {
      $userObj->setLastname($userData['lastname']);

      // Update fullname and full title based on firstname, lastname and user-title
      $userObj->setFullname();
      $userObj->setTitle($userObj->getFullname());
    }

    // Set user-preferences which can have change-restrictions
    if (self::HasUserValue($userData, 'hits_per_page') && self::IsChangeable('hits_per_page', $refId))
      $userObj->setPref('hits_per_page', $userData['hits_per_page']);
    if (self::HasUserValue($userData, 'show_users_online') && self::IsChangeable('show_users_online', $refId))
      $userObj->setPref('show_users_online', $userData['show_users_online']);
    if (self::HasUserValue($userData, 'hide_own_online_status') && self::IsChangeable('hide_own_online_status', $refId))
      $userObj->setPref('hide_own_online_status', $userData['hide_own_online_status']);
    if (self::IsChangeable('skin_style', $refId)) {
      // Extract sk/style arguments
      $skin_style = explode(':', $userData['skin_style']);
      $skin       = $skin_style[0];
      $style      = $skin_style[1];

      // Set skin/style presserence
      $userObj->setPref('skin',  $skin);
      $userObj->setPref('style', $style);
    }

    // Set session reminder
    if (self::HasUserValue($userData, 'session_reminder_enabled') && $ilSetting->get('session_reminder_enabled'))
      $userObj->setPref('session_reminder_enabled', $userData['session_reminder_enabled']);

    // Set password on creation or update if allowed
    if (self::HasUserValue($userData, 'passwd') && (
      $mode == self::MODE_CREATE ||
      \ilAuthUtils::_allowPasswordModificationByAuthMode(\ilAuthUtils::_getAuthMode($userData['auth_mode']))
    )) {
      $userObj->setPasswd($userData['passwd'], IL_PASSWD_PLAIN);
      $userObj->setLastPasswordChangeTS(time());
    }

    // Set attached external account if enabled
    include_once('./Services/Authentication/classes/class.ilAuthUtils.php');
    if (self::HasUserValue($userData, 'ext_account') && \ilAuthUtils::_isExternalAccountEnabled())
      $userObj->setExternalAccount($userData['ext_account']);

    // Set disk quotas (overall abd workspace)
    require_once 'Services/WebDAV/classes/class.ilDiskQuotaActivationChecker.php';
    if (self::HasUserValue($userData, 'disk_quota') && i\lDiskQuotaActivationChecker::_isActive())
      $userObj->setPref('disk_quota',     $userData['disk_quota']     * ilFormat::_getSizeMagnitude() * ilFormat::_getSizeMagnitude());
    if (self::HasUserValue($userData, 'wsp_disk_quota') && \ilDiskQuotaActivationChecker::_isPersonalWorkspaceActive())
      $userObj->setPref('wsp_disk_quota', $userData['wsp_disk_quota'] * ilFormat::_getSizeMagnitude() * ilFormat::_getSizeMagnitude());

    // Additional user value we could set (but don't)
    // $userObj->setAgreeDate($userData['agree_date']);
    // $userObj->setLastLogin($userData['last_login']);
    // $userObj->setApproveDate($userData['approve_date']);
    // $userObj->setPasswordEncodingType($userData['password_encoding_type']);
    // $userObj->setPasswordSalt($userData['password_salt']);
    // $userObj->setTimeLimitMessage($userData['time_limit_message']);
    // if ($userData['password_change'])
    //   $userObj->setLastPasswordChangeToNow();

    // Check wether profile is incomplete ()
    include_once 'Services/User/classes/class.ilUserProfile.php';
    $userObj->setProfileIncomplete(\ilUserProfile::isProfileIncomplete($userObj));

    // Create and save user account data
    if ($mode == self::MODE_CREATE) {
      $userObj->create();
      $userObj->saveAsNew();
      $userObj->writePrefs();
    }
    // Update user account in database
    else
      $this->object->update();

    // Reset login attempts if account is active
    if ($userData['active'])
      \ilObjUser::_resetLoginAttempts($userObj->getId());

    // Create profile-picture from attached based64 encoded image
    if (self::HasUserValue($userData, 'userfile') &&  self::IsChangeable('upload', $refId)) {
      $hasVirus = self::ProcessUserPicture($userObj, $userData['userfile']);
      if (isset($hasVirus))
        $result['userfile'] = $hasVirus;
    }

    // Assign user to given roles (and deassigned missing roles)
    $assignedRoles = $rbacreview->assignedRoles($userObj->getId());
    $dropRoles     = array_diff($assignedRoles, $userData['roles']);
    $addRoles      = array_diff($userData['roles'], $assignedRoles);
    foreach ($dropRoles as $role)
      $rbacadmin->deassignUser($role, $userObj->getId());
    foreach ($addRoles as $role)
      $rbacadmin->assignUser($role, $userObj->getId());

    // Send email?
    if ($userData['send_mail'] == 'y') {
      // Create new eamil object
      include_once('Services/Mail/classes/class.ilAccountMail.php');
      $mail = new \ilAccountMail();
      $mail->useLangVariablesAsFallback(true);
      $mail->setUserPassword($userData['passwd']);
      $mail->setUser($userObj);

      // Send email and return any error-code
      $result['email'] = $mail->send();
    }

    // Return on success with some additional information
    $result['user'] = $userObj;
    return $result;
  }


  /**
   *
   */
  protected function HasUserValue($userData, $field) {
    return (is_array($userData) && array_key_exists($field, $userData));
  }


  /**
   *
   */
  protected function IsChangeable($field, $refId) {
    // Fetch reference to ILIAS settings
    global $ilSetting;

    // All settings can be changed via the admin-panel / for global accounts
    if ($refId == USER_FOLDER_ID)
      return true;

    // Fetch ILIAS settings for checking changability
    $settings = $ilSetting->getAll();

    // Check wether setting is marked as changeable
    return (bool) $settings[sprintf('usr_settings_changeable_lua_%s', $field)];
  }


  /**
   *
   */
  protected static function ProcessUserPicture($userObj, $imgData) {
    // Delete user picture files
    if (!isset($imgData))
      $userObj->removeUserPicture();

    // Create user pciture files (profile-pricutre and thumbnails)
    else {
      // Extract base64 encoded image data
      $encodedData = preg_replace('#^data:image/\w+;base64,#i', '', $imgData);
      if (!isset($encodedData) || strlen($encodedData) == 0 || strcmp($imgData, $encodedData) == 0)
        throw new LibExceptions\Parameter(
          self::MSG_USER_PICTURE_EMPTY,
          self::ID_USER_PICTURE_EMPTY
        );

      // Store decoded image data to file
      //  Note: ilObjUserGUI sets chmod to 0770; beats me why one would enabled execution bit on an UPLOADED file...
      $tmpFile = sprintf('%s/usr_images/upload_%d', ilUtil::getWebspaceDir(), $userObj->getId());
      file_put_contents($tmpFile, base64_decode($encodedData));
      chmod($tmpFile, 0664);

      // Check uploaded file for virus and delete + fail if one was detected
      $scanResult = \ilUtil::virusHandling($tmpFile, sprintf('Profile-Picutre [User: %s]', $userObj->getLogin()));
      if (!$scanResult[0]) {
        // Delete file
        unlink($tmpFile);

        // Return scan result
        return $scanResult;
      }

      // Generate tumbnails, write and update prefs
      $userObj->_uploadPersonalPicture($tmpFile, $userObj->getId());
      $userObj->setPref('profile_image', sprintf('usr_%d.jpg', $userObj->getId()));
    }
  }


  /**
   *
   */
  protected static function GetUnixTime($data) {
    // Time seems to be in unix-time
    if (is_int($data))
      return $data;
    // Time seems to be in unix-time (but a string)
    elseif (ctype_digit($data))
      return intval($data);
    // Date and time given, convert to uni-time
    elseif (array_key_exists('date', $data) && array_key_exists('time', $data)) {
      $time = new \ilDateTime(sprintf('%s %s', $data['date'], $data['time']));
      return $time->get(IL_CAL_UNIX);
    }

    // Try to use DateTime to extract unix-time
    if (is_string($data)) {
      try {
        $date = new DateTime($data);
        if ($date)
          return $date->getTimestamp();
      } catch (Exception $e) { }
    }

    // Absolute fallback-case (should only happen on wrong input)
    return time();
  }


  /**
   *
   */
  protected static function GetISODate($data) {
    // Time seems to be in unix-time
    if (is_int($data))
      return date('Y-m-d', $data);
    // Time seems to be in unix-time (but a string)
    elseif (ctype_digit($data))
      return date('Y-m-d', intval($data));
    // Time seems to be in a special format
    elseif (is_string($data)) {
      // String seems to contain more than date data
      if (strlen($data) > 8) {
        try {
          $date = new DateTime($data);
          if ($date)
            return $date->format('Y-m-d');
        // Fallback case...
        } catch (Exception $e) {
          return $data;
        }
      }
      // String hopefully only contains the date part
      else
        return $data;
    }
  }


  /**
   * Note: This check is rather restrictive (even for admins),
   *  only global and local roles in Categorie/Organisational-Unit
   *  are valid.
   *  Alternitivly we could allow admins to set any role from
   *   $rbacreview->getAssignableRoles();
   *  and local admins any role from
   *   $rbacreview->getAssignableRolesInSubtree($refId);
   */
  protected static function ValidateRoles($roles, $refId) {
    global $rbacreview;

    // Fetch list of assignable roles
    $local  = $rbacreview->getRolesOfRoleFolder($refId);
    $global = $rbacreview->getGlobalRoles();
    if ($refId != USER_FOLDER_ID)
      $global = array_filter($global, function($role) {
        return \ilObjRole::_getAssignUsersStatus($role);
      });
    $assignable = array_merge($local, $global);
    $assignable = array_map('intval', $assignable);

    // Check if all roles are assignable
    return count(array_diff($roles, $assignable)) == 0;
  }
}