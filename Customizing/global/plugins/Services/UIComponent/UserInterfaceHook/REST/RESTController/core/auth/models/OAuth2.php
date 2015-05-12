<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer, S.Schneider and T. Hufschmidt <(schaefer|schneider|hufschmidt)@hrz.uni-marburg.de>
 * 2014-2015
 */
namespace RESTController\core\auth;

// This allows us to use shortcuts instead of full quantifier
use \RESTController\libs as Libs;
use \RESTController\core\clients\Clients as Clients;


/**
 * Class OAuth2Model
 * This model provides methods to accomplish the OAuth2 mechanism for ILIAS.
 * Note: In contrast to the original specification, we renamed the term OAuth2 'client_id' to 'api_key'.
 */
class OAuth2 extends Libs\RESTModel {
    /**
     *
     */
    public function authAllGrantTypes($api_key, $redirect_uri, $username, $password, $response_type, $authenticity_token) {
        // Client (api-key) is not allowed to use this grant-type or doesn't exist
        $clients = new Clients($this->app, $this->sqlDB);
        if (!$clients->clientExists($api_key))
            throw new Exceptions\LoginFailed('There is no client with this api-key.');
        if ($response_type != 'code' && $response_type != 'token')
            throw new Exceptions\ResponseType('Parameter response_type must be "code" or "token".');
        if ($response_type == 'code' && !$clients->is_oauth2_gt_authcode_enabled($api_key))
            throw new Exceptions\LoginFailed('Authorization-code grant-type is disabled for this client.');
        if ($response_type == 'token' && !$clients->is_oauth2_gt_implicit_enabled($api_key))
            throw new Exceptions\LoginFailed('Implicit grant-type is disabled for this client.');

        // Login-data (username/password) is provided, try to authenticate
        if ($username && $password) {
            // Try to authenticate via username/password & api-key
            $isAuth = Libs\AuthLib::authenticateViaIlias($username, $password);
            $clientValid = Libs\AuthLib::checkOAuth2Client($api_key);

            // Provided wrong API-Key?
            if (!$clientValid)
                return array(
                    'status' => 'showLogin',
                    'data' => array(
                        'error_msg' => 'API-Key incorrect!',
                        'api_key' => $api_key,
                        'redirect_uri' => $redirect_uri,
                        'response_type' => $response_type
                    )
                );

            // Provided wrong username/password
            if (!$isAuth)
                return array(
                    'status' => 'showLogin',
                    'data' => array(
                        'error_msg' => 'Username or password incorrect!',
                        'api_key' => $api_key,
                        'redirect_uri' => $redirect_uri,
                        'response_type' => $response_type
                    )
                );

            // Need to show grant-permission site?
            if($clients->is_oauth2_consent_message_enabled($api_key)) {
                // Generate a temporary token that can be exchanged for bearer-token
                $temp_authenticity_token = Libs\TokenLib::generateSerializedToken($username, $api_key, '', '', 10);
                $oauth2_consent_message = $clients->getOAuth2ConsentMessage($api_key);

                // Return data to route/other model
                return array(
                    'status' => 'showPermission',
                    'data' => array(
                        'api_key' => $api_key,
                        'redirect_uri' => $redirect_uri,
                        'response_type' => $response_type,
                        'authenticity_token' => $temp_authenticity_token,
                        'oauth2_consent_message' => $oauth2_consent_message
                    )
                );
            }
            // No need to show grant-permissions, goto redirect target
            else {
                // Generate a temporary token that can be exchanged for bearer-token
                if ($response_type == 'code') {
                    $authorization_code = Libs\TokenLib::generateSerializedToken($username, $api_key, 'code', $redirect_uri, 10);
                    $url = $redirect_uri . '?code='.$authorization_code;
                }
                elseif ($response_type == 'token') {
                    $bearerToken = Libs\TokenLib::generateBearerToken($username, $api_key);
                    $url = $redirect_uri . '#access_token='.$bearerToken['access_token'].'&token_type=bearer'.'&expires_in='.$bearerToken['expires_in'].'&state=xyz';
                }

                // Return data to route/other model
                return array(
                    'status' => 'redirect',
                    'data' => $url
                );
            }
        }
        // Login-data (token) is provided, try to authenticate
        elseif ($authenticity_token) {
            // Check if token is still valid
            if (!Libs\TokenLib::tokenExpired(Libs\TokenLib::deserializeToken($authenticity_token))) {
                $tokenUser = $authenticity_token['user'];
                // Generate a temporary token that can be exchanged for bearer-token
                if ($response_type == 'code') {
                    $authorization_code = Libs\TokenLib::generateSerializedToken($tokenUser, $api_key, 'code', $redirect_uri, 10);
                    $url = $redirect_uri . '?code='.$authorization_code;
                }
                elseif ($response_type == 'token') {
                    $bearerToken = Libs\TokenLib::generateBearerToken($tokenUser, $api_key);
                    $url = $redirect_uri . '#access_token='.$bearerToken['access_token'].'&token_type=bearer'.'&expires_in='.$bearerToken['expires_in'].'&state=xyz';
                }

                // Return data to route/other model
                return array(
                    'status' => 'redirect',
                    'data' => $url
                );
            }
            // Provided token has expired
            else
                throw new Exceptions\TokenExpired('The provided token has expired.');
        }
        // No login-data (token or username/password) provided, render login-page
        else
            return array(
                'status' => 'showLogin',
                'data' => array(
                    'api_key' => $api_key,
                    'redirect_uri' => $redirect_uri,
                    'response_type' => $response_type
                )
            );
    }


    /**
     * Token endpoint routine:
     * The token endpoint part of the user credentials auth flow.
     * @param $app
     * @param $request
     */
    public function tokenUserCredentials($api_key, $user, $pass) {
        $response = new OAuth2Response($app);

        $isAuth = AuthLib::authenticateViaIlias($user, $pass);
        if ($isAuth == false) {
            $response->setHttpStatus(401);
            $response->setOutputFormat('plain');
            $response->send();
        } else {
            $clients_model = new ClientsModel();
            if ($clients_model->clientExists($api_key)) {
                if ($clients_model->is_oauth2_gt_resourceowner_enabled($api_key)) {
                    $allowed_users = $clients_model->getAllowedUsersForApiKey($api_key);
                    $access_granted = false;
                    $uid = (int)RESTLib::loginToUserId($user);

                    if (in_array(-1, $allowed_users) || in_array($uid, $allowed_users)) {
                        $access_granted = true;
                    }
                    if ($access_granted == true) {
                        $app->log->debug('access granted');
                        $bearer_token = TokenLib::generateBearerToken($user, $api_key);
                        $response->setHttpHeader('Cache-Control', 'no-store');
                        $response->setHttpHeader('Pragma', 'no-cache');
                        $response->setField('access_token', $bearer_token['access_token']);
                        $response->setField('expires_in', $bearer_token['expires_in']);
                        $response->setField('token_type', $bearer_token['token_type']);
                        $response->setField('scope', $bearer_token['scope']);
                        if ($clients_model->is_resourceowner_refreshtoken_enabled($api_key)) {
                            $refresh_token = $this->getRefreshToken(TokenLib::deserializeToken($bearer_token['access_token']));
                            $response->setField('refresh_token', $refresh_token);
                        }
                    } else {
                        $response->setHttpStatus(401);
                    }
                } else {
                    $response->setHttpStatus(401);
                }
            } else {
                $response->setHttpStatus(401);
            }
            $response->send();
        }

    }


    /**
     * Token endpoint routine:
     * The token endpoint part of the client credentials auth flow.
     * @param $app
     * @param $request
     */
    public function tokenClientCredentials($api_key, $api_secret) {
        $response = new OAuth2Response($app);

        $clients_model = new ClientsModel($app);
        if ($clients_model->clientExists($api_key)) {
            if ($clients_model->is_oauth2_gt_clientcredentials_enabled($api_key)) {
                $uid = (int)$clients_model->getClientCredentialsUser($api_key);
                $user = RESTLib::userIdtoLogin($uid);
                $authResult = AuthLib::checkOAuth2ClientCredentials($api_key, $api_secret);
                if (!$authResult) {
                    $response->setHttpStatus(401);
                }
                else {
                    $bearer_token = TokenLib::generateBearerToken($user,$api_key);
                    $response->setHttpHeader('Cache-Control', 'no-store');
                    $response->setHttpHeader('Pragma', 'no-cache');
                    $response->setField('access_token',$bearer_token['access_token']);
                    $response->setField('expires_in',$bearer_token['expires_in']);
                    $response->setField('token_type',$bearer_token['token_type']);
                    $response->setField('scope',$bearer_token['scope']);
                }
            } else {
                $response->setHttpStatus(401);
            }
        } else {
            $response->setHttpStatus(401);
        }
        $response->send();


    }


    /**
     * Token endpoint routine:
     * The token endpoint part of the authorization auth flow.
     * This method exchanges an authorization code with a bearer token.
     * @param $app
     * @param $request
     */
    public function tokenAuthorizationCode($api_key, $api_secret, $code, $redirect_uri) {
        $response = new OAuth2Response($app);

        $isClientAuthorized = AuthLib::checkOAuth2ClientCredentials($api_key, $api_secret);
        if (!$isClientAuthorized) {
            $app->response()->status(401);
        }
        else {
            $code_token = TokenLib::deserializeToken($code);
            if (!TokenLib::tokenExpired($code_token)){
                $t_redirect_uri = $code_token['misc'];
                $t_user = $code_token['user'];
                $t_client_id = $code_token['api_key'];

                if ($t_redirect_uri == $redirect_uri && $t_client_id == $api_key) {

                    $clients_model = new ClientsModel();
                    if ($clients_model->clientExists($t_client_id)) {
                        if ($clients_model->is_oauth2_gt_authcode_enabled($t_client_id)) {
                            $allowed_users = $clients_model->getAllowedUsersForApiKey($t_client_id);
                            $access_granted = false;
                            $uid = (int)RESTLib::loginToUserId($t_user);

                            if (in_array(-1, $allowed_users) || in_array($uid, $allowed_users)) {
                                $access_granted = true;
                            }
                            if ($access_granted == true) {
                                $app->log->debug('auth code access granted. user: '.$t_user.' key: '.$api_key);
                                $bearer_token = TokenLib::generateBearerToken($t_user, $api_key);
                                $response->setHttpHeader('Cache-Control', 'no-store');
                                $response->setHttpHeader('Pragma', 'no-cache');
                                $response->setField('access_token', $bearer_token['access_token']);
                                $response->setField('expires_in', $bearer_token['expires_in']);
                                $response->setField('token_type', $bearer_token['token_type']);
                                $response->setField('scope', $bearer_token['scope']);
                                if ($clients_model->is_authcode_refreshtoken_enabled($api_key)) { // optional
                                    $refresh_token = $this->getRefreshToken(TokenLib::deserializeToken($bearer_token['access_token']));
                                    $response->setField('refresh_token', $refresh_token);
                                }

                            } else {
                                $response->setHttpStatus(401);
                            }
                        } else {
                            $response->setHttpStatus(401);
                        }
                    }

                }
            } else {
                $response->setHttpStatus(401);
            }
        }
        $response->send();
    }


    /**
     * Token endpoint routine:
     * Token-endpoint for refresh tokens.
     * Cf. RFC6749 Chapter 6.  Refreshing an Access Token
     * @param $app
     * @throws Exception
     */
    public function tokenRefresh2Bearer($refresh_token) {
        $response = new OAuth2Response($app, $ilDB);

        $bearer_token = $this->getBearerTokenForRefreshToken($refresh_token);
    }
    // ----------------------------------------------------------------------------------------------
    // Refresh Token Support


    /**
     * Refresh Token Endpoint routine:
     * Returns a refresh token for a valid bearer token.
     * @param $bearer_token_array
     * @return string
     */
    public function getRefreshToken($bearer_token_array) {
        $user_id = RESTLib::loginToUserId($bearer_token_array['user']);
        $api_key = $bearer_token_array['api_key'];
        $entry = $this->_checkRefreshTokenEntry($user_id, $api_key);

        $newRefreshToken = TokenLib::serializeToken(TokenLib::generateOAuth2RefreshToken($bearer_token_array['user'], $bearer_token_array['api_key']));
        if ($entry == null) { // Create new entry
            $this->_createNewRefreshTokenEntry($user_id,  $api_key, $newRefreshToken);
            return $newRefreshToken;
        } else { // Reset an existing entry
            $this->_resetRefreshTokenEntry($user_id, $api_key, $newRefreshToken);
            return $newRefreshToken;
        }
    }


    /**
    * Refresh Token Endpoint routine:
     * Returns a new bearer token for a valid refresh token.
     * Validation check and bookkeeping is realized via an internal refresh token table.
     * @param $refresh_token
     * @return array|bool
     */
    public function getBearerTokenForRefreshToken($refresh_token) {
        $refresh_token_array = TokenLib::deserializeToken($refresh_token);
        if (TokenLib::tokenValid($refresh_token_array) == true) {
            $user = $refresh_token_array['user'];
            $user_id = RESTLib::loginToUserId($user);
            $api_key = $refresh_token_array['api_key'];
            $entry = $this->_checkRefreshTokenEntry($user_id, $api_key);
            if ($entry == null) {
                return false;
            } else {
                if ($entry['num_refresh_left'] > 0 ) {
                    if ($entry['refresh_token'] == $refresh_token) {
                        $this->_issueExistingRefreshToken($user_id, $api_key);
                        $bearer_token = TokenLib::generateBearerToken($user, $api_key);
                        return $bearer_token;
                    } else {
                        return false;
                    }
                } else {
                    $this->_deleteRefreshTokenEntry($user_id, $api_key);
                    return false;
                }
            }
        } else {
            return 'Token not valid.';
        }
    }


    /**
     * Refresh Token Endpoint routine:
     * Returns the refresh token for an existing refresh token entry.
     * Decreases num_refresh_left field and updates the issuing time stamp.
     */
    protected function _issueExistingRefreshToken($user_id, $api_key) {
        global $ilDB;

        $query = '
            SELECT refresh_token, num_refresh_left
            FROM ui_uihk_rest_oauth2
            JOIN ui_uihk_rest_keys
            ON ui_uihk_rest_oauth2.api_id = ui_uihk_rest_keys.id
            AND ui_uihk_rest_oauth2.user_id='.$user_id.'
            AND ui_uihk_rest_keys.api_key="'.$api_key.'"';
        $set = $ilDB->query($query);
        if ($set != null && $entry = $ilDB->fetchAssoc($set)) {
            $ct_num_refresh_left = $entry['num_refresh_left'];
            $refresh_token = $entry['refresh_token'];

            $this->_updateRefreshTokenEntry($user_id, $api_key, 'num_refresh_left', $ct_num_refresh_left-1);
            $this->_updateRefreshTokenEntry($user_id, $api_key, 'last_refresh_timestamp', date('Y-m-d H:i:s',time()));
            return $refresh_token;
        }
    }


    /**
     * Refresh Token Endpoint routine:
     * Resets an existing refresh token entry:
     *  - Overwrites refresh token field
     *  - Increases field 'num_resets'
     *  - Overwrites field num_refresh_left
     *  - Overwrites last_refresh_timestamp
     */
    protected function _resetRefreshTokenEntry($user_id, $api_key, $newRefreshToken) {
        global $ilDB;

        $query = '
            SELECT num_resets
            FROM ui_uihk_rest_oauth2
            JOIN ui_uihk_rest_keys
            ON ui_uihk_rest_oauth2.api_id = ui_uihk_rest_keys.id
            AND ui_uihk_rest_oauth2.user_id='.$user_id.'
            AND ui_uihk_rest_keys.api_key="'.$api_key.'"';

        $set = $ilDB->query($query);
        if ($set != null && $entry = $ilDB->fetchAssoc($set)) {
            $ct_num_resets = $entry['num_resets'];

            $this->_updateRefreshTokenEntry($user_id, $api_key, 'refresh_token', $newRefreshToken);
            $this->_updateRefreshTokenEntry($user_id, $api_key, 'num_resets', $ct_num_resets + 1);
            $this->_updateRefreshTokenEntry($user_id, $api_key, 'last_refresh_timestamp', date('Y-m-d H:i:s',time()));
            $this->_updateRefreshTokenEntry($user_id, $api_key, 'num_refresh_left', 10000);
        }
    }

    /**
     * Refresh Token Endpoint routine:
     * Tester of _checkRefreshTokenEntry
     * @param $bearer_token_array
     * @return array
     */
    public function getRefreshEntryInfo($bearer_token_array) {
        $user_id = RESTLib::loginToUserId($bearer_token_array['user']);
        $api_key = $bearer_token_array['api_key'];

        $entry = $this->_checkRefreshTokenEntry($user_id, $api_key);
        if ($entry != null) {
            $result = array();
            $result['num_refresh_left'] = $entry['num_refresh_left'];
            $result['num_resets'] = $entry['num_resets'];
            $result['last_refresh_timestamp'] = $entry['last_refresh_timestamp'];
            return $result;

        }
        return array();
    }


    /**
     * Refresh Token Endpoint routine:
     * Provides information about an entry:
     * 1) Entry exists: yes or no.
     * 2) How many refreshs are left (num_refresh_left)
     * 3) Number of resets (num_resets).
     * 3) Last refresh timestamp (last_refresh_timestamp).
     *
     * @param $user_id
     * @param $api_key
     * @return array
     */
    protected function _checkRefreshTokenEntry($user_id, $api_key) {
        global $ilDB;

        $query = '
            SELECT *
            FROM ui_uihk_rest_oauth2
            JOIN ui_uihk_rest_keys
            ON ui_uihk_rest_oauth2.api_id = ui_uihk_rest_keys.id
            AND ui_uihk_rest_oauth2.user_id='.$user_id.'
            AND ui_uihk_rest_keys.api_key="'.$api_key.'"';
        $set = $ilDB->query($query);
        if ($set != null && $entry = $ilDB->fetchAssoc($set))
            return $entry;
        else
            return null;
    }


    /**
     * Refresh Token Endpoint routine:
     * Creates a new Refresh-Token Entry (helper).
     *
     * @param $user_id
     * @param $api_key
     * @param $refresh_token
     * @return mixed the insertion id
     */
    protected function _createNewRefreshTokenEntry($user_id, $api_key, $refresh_token) {
        global $ilDB;

        $sql = sprintf('SELECT id FROM ui_uihk_rest_keys WHERE api_key = "%s"', $api_key);
        $query = $ilDB->query($sql);
        if ($query != null && $row = $ilDB->fetchAssoc($query)) {
            $api_id = $row['id'];

            $a_columns = array(
                'user_id' => array('text', $user_id),
                'api_id' => array('text', $api_id),
                'refresh_token' => array('text', $refresh_token),
                'num_refresh_left' => array('integer', 10000),
                'last_refresh_timestamp' => array('date', date('Y-m-d H:i:s',0)),
                'init_timestamp' => array('date', date('Y-m-d H:i:s',time())),
                'num_resets' => array('integer', 0)
            );

            $ilDB->insert('ui_uihk_rest_oauth2', $a_columns);
            return $ilDB->getLastInsertId();
        }
    }


    /**
     * Refresh Token Endpoint routine:
     * Deletes a Refresh Token Entry
     * @param $user_id
     * @param $api_key
     * @return mixed
     */
    protected function _deleteRefreshTokenEntry($user_id, $api_key) {
        global $ilDB;

        $query = '
            DELETE ui_uihk_rest_oauth2
            FROM ui_uihk_rest_oauth2
            JOIN ui_uihk_rest_keys
            ON ui_uihk_rest_oauth2.api_id = ui_uihk_rest_keys.id
            AND ui_uihk_rest_oauth2.user_id='.$user_id.'
            AND ui_uihk_rest_keys.api_key="'.$api_key.'"';
        $numAffRows = $ilDB->manipulate($query);

        return $numAffRows;
    }


    /**
     * Refresh Token Endpoint routine:
     * Updates a refresh token entry (helper).
     * @param $user_id
     * @param $api_key
     * @param $fieldname
     * @param $newval
     * @return mixed
     */
    public function _updateRefreshTokenEntry($user_id, $api_key, $fieldname, $newval) {
        global $ilDB;

        $query = '
            UPDATE ui_uihk_rest_oauth2
            JOIN ui_uihk_rest_keys
            ON ui_uihk_rest_oauth2.api_id = ui_uihk_rest_keys.id
            AND ui_uihk_rest_oauth2.user_id='.$user_id.'
            AND ui_uihk_rest_keys.api_key="'.$api_key.'"
            SET '.$fieldname.' = "'.$newval.'"';
        $numAffRows = $ilDB->manipulate($query);

        return $numAffRows;
    }


    /**
     * Further OAuth2 routines:
     * Tokeninfo - Tokens obtained via the implicit code grant MUST by validated by the Javascript client
     * to prevent the 'confused deputy problem'.
     * @param $app
     */
    public function tokenInfo($request) {
        $access_token = $request->params('access_token');
        if (!isset($access_token)) {
            $a_data = array();
            $jsondata = $app->request()->getBody(); // json
            $a_data = json_decode($jsondata, true);
            $access_token = $a_data['token'];
            if (!isset($access_token)) {
                $headers = apache_request_headers();
                $authHeader = $headers['Authorization'];
                if ($authHeader!=null) {
                    $a_auth = explode(' ',$authHeader);
                    $access_token = $a_auth[1];    // Bearer Access Token
                    if ($access_token == null) {
                        $access_token = $a_auth[0]; // Another kind of Token
                    }
                }
            }
        }

        $token = TokenLib::deserializeToken($access_token);
        $valid = TokenLib::tokenValid($token);

        $result = array();
        if ($valid) {
            $result['api_key'] = $token['api_key'];
            // scope
            $result['user'] =  $token['user'];
            $result['type'] =  $token['type'];
            $result['expires_in'] = TokenLib::getRemainingTime($token);

        } else {
            $app->response()->status(400);
            $result['error'] = 'Invalid token.';
        }

        return $result;
    }


    /**
     * Further OAuth2 routines:
     * Allows for exchanging an ilias session to a bearer token.
     * This is used for administration purposes.
     * @param $app
     */
    public function rToken2Bearer($request) {
        $result = array();
        $user_id = '';
        $rtoken = '';
        $session_id = '';
        $api_key = '';

        if (count($request->post()) == 0) {
            $a_data = array();
            $reqdata = $app->request()->getBody(); // json
            $a_data = json_decode($reqdata, true);
            //var_dump($a_data);
            $user_id = $a_data['user_id'];
            $rtoken = $a_data['rtoken'];
            $session_id = $a_data['session_id'];
            $api_key = $a_data['api_key'];
        } else {
            $user_id = $request->params('user_id');
            $rtoken = $request->params('rtoken');
            $session_id = $request->params('session_id');
            $api_key = $request->params('api_key');
        }

        $isAuth = AuthLib::authFromIlias($user_id, $rtoken, $session_id);

        if ($isAuth == false) {
            //$app->response()->status(400);
            $result['status'] = 'error';
            $result['error'] = 'Invalid token.';
            $result['user_id']=$user_id;
            $result['rtoken']=$rtoken;
            $result['session_id']=$session_id;

        }
        else {
            $user = RESTLib::userIdtoLogin($user_id);
            $access_token = TokenLib::generateBearerToken($user, $api_key);
            $result['status'] = 'success';
            $result['user'] = $user;
            $result['token'] = $access_token;
        }
    }


    /**
     * Simplifies rendering output by allowing to reuse common code.
     * Core.php which includes many preset JavaScript and CSS libraries will always
     * be used as a base template and $file will be included into its body.
     *
     * @param $title - Sets the pages <title> tag
     * @param $file - This file will be included inside <body></body> tags
     * @param $data - Optional data (may be an array) that is passed to the template
     */
    public function render($title, $file, $data) {
        // Build absolute-path (relative to document-root)
        $sub_dir = 'core/auth/views';
        $rel_path = $this->plugin->getPluginObject(IL_COMP_SERVICE, 'UIComponent', 'uihk', 'REST')->getDirectory();
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $scriptName = str_replace('\\', '/', $scriptName);
        $scriptName = ($scriptName == '/' ? '' : $scriptName);
        $abs_path = $scriptName.'/'.$rel_path.'/RESTController/'.$sub_dir;

        // Supply data to slim application
        $this->app->render($sub_dir.'/core.php', array(
            'tpl_path' => $abs_path,
            'tpl_title' => $title,
            'tpl_file' => $file,
            'tpl_data' => $data
        ));
    }
}
