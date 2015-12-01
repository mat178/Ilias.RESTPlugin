<?php
/**
 * ILIAS REST Plugin for the ILIAS LMS
 *
 * Authors: D.Schaefer and T.Hufschmidt <(schaefer|hufschmidt)@hrz.uni-marburg.de>
 * Since 2014
 */
namespace RESTController\libs;

// Requires RESTController


/**
 * Class: RESTException
 *  This is the base-class of all exceptions thrown by the RESTController itself
 *  This does not include onces thrown by PHP or ILIAS.
 *  Do not use this class directly, but derive your own (customized)
 *  implementations to allow different exceptions to be distinguished.
 *
 * @See http://php.net/manual/de/class.exception.php for additonal methods.
 */
class RESTException extends \Exception {
  // All RESTException can optionally have an attached 'rest-code'
  // Unlike the default exception-code, this can be non-numeric!
  protected $restCode = 0;
  protected $restData = array();


  /**
   * Constructor: RESTException($message, $restCode, $restData, $previous)
   *  Creates a new instance of this exception which can either
   *  be thrown or used to pass data along.
   *
   * Parameters:
   *  $message <String> - A human-readable message about the cause of the exception
   *  $restCode <String> - [Optional] A machine-readable identifier for the cause of the exception
   *  $restData <Array[Mixed]> - [Optional] Optional data that should be attached to the exception. (Must be an array!)
   *  $previous <Exception> - [Optional] Attach previous exception that caused this exception
   */
  public function __construct ($message, $restCode = 0, $restData = null, $previous = NULL) {
    // Call parent constructor
    $message = self::format($message, $restCode, $restData);
    parent::__construct ($message, 0, $previous);

    // Store data
    if (is_array($restData))
      $this->restData = $restData;

    // This internal values
    $this->restCode = $restCode;
  }


  /**
   * Function: getRESTMessage()
   *  Returns the formated message.
   *  (Wrapper around getMessage() to allow overwriting)
   *
   * Return:
   *  <String> - (Formated) Message attached to this exception
   */
  public function getRESTMessage() {
    // Return already formated exception-message
    return $this->getMessage();
  }


  /**
   * Function: getRESTCode()
   *  Returns the REST-code attached to this exception.
   *
   * Return:
   *  <String> - REST-Code that was attched to this exception.
   */
  public function getRESTCode() {
    // Return internal rest-code
    return $this->restCode;
  }


  /**
   * Function: getData()
   *  Returns data that might have been attached to this exception.
   *
   * Return:
   *  <Array[Mixed]> - Data attached to this exception
   */
  public function getRESTData() {
    // Return internal data-array
    return $this->restData;
  }


  /**
   * Static-Function: format($message, $data)
   *  Formats special placeholders in the given message with data
   *  from the $data-array.
   *  Supported placeholders:
   *   {{restcode}} - Will be replaced with $this->getRESTCode()
   *   {{KEY}} - Will be replaced with $data[key]
   *   {{%KEY}} - Will be replaced with $data[key] (As fallback when key needs to be restcode)
   *
   * Parameters:
   *  $message <String> - Unformated message containing format parameters
   *  $data <Array[Mixed]> - Data-Array from which should be used to repĺace placeholders in $message
   *
   * Return:
   *  <String> - Formated $message with special placeholders replaced with values from $data-array
   */
  public static function format($message, $code, $data) {
    $message = str_replace('{{restcode}}', $code, $message);
    if (is_array($data))
      foreach($data as $key => $value) {
        $message = str_replace(sprintf('{{%s}}', $key), $value, $message);
        $message = str_replace(sprintf('{{%%%s}}', $key), $value, $message);
      }
  }


  /**
   * Function: send($code)
   *  Utility-Function to make sneding responses generated from RESTExceptions
   *  easier, since they 95% of the time will look the same.
   *
   * Note:
   *  This will send the preformated exception-information and terminate the application!
   *
   * Parameters:
   *  $code - HTTP-Code that should be used
   */
  public function send($code) {
    // Fect instance of the RESTController
    $app = \RESTController\RESTController::getInstance();

    // Send formated exception-information
    $app->halt($code, array(
      'message' => $this->getRESTMessage(),
      'code'    => $this->getRESTCode(),
      'data'    => $this->getRESTData()
    ));
  }
}
