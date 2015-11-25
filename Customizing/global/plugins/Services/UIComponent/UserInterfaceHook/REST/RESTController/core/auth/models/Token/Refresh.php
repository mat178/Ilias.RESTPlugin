<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\core\auth\Token;


/**
 * Class: RefreshToken
 *  Represents an actual Refresh-Token.
 *  Mainly a generic token with additional data in misc-field.
 */
class RefreshToken extends Generic {
  /**
   * Static-Function: fromFields($tokenSettings, $user_id, $ilias_client, $api_key, $type, $misc, $lifetime)
   *  Generates a Refresh-Token from given input parameters.
   *  Expects settings-object and token-data as additional parameters.
   *
   * Parameters:
   *  @See Generic::fromFields(...) for parameter description
   *
   * Return:
   *  <RefreshToken> - Generated Refresh-Token
   */
  public static function fromFields($tokenSettings, $user_id, $ilias_client, $api_key, $type = null, $misc = null, $lifetime = null) {
    // Add additional security through randomness to refresh-token
    $randomStr  = 'refresh-' + substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 5)), 0, 5);
    $misc       = ($misc) ? $misc + $randomStr : $randomStr;

    // Return generic token with some customized fieldsd
    return parent::fromFields($tokenSettings, $user_id, $ilias_client, $api_key, $type, $misc, $lifetime);
  }
}
