<?php
/**
 * Flickering
 *
 * Main interface for the Flickering package
 * Creates calls to methods and handles configuration
 */
namespace Flickering;

use BadMethodCallException;
use Opauth;
use Underscore\Types\Arrays;

class Flickering
{
  /**
   * The API
   * @var Consumer
   */
  protected $consumer;

  /**
   * The User
   * @var User
   */
  protected $user;

  /**
   * The Flickr API endpoint
   * @var string
   */
  const API_URL = 'api.flickr.com/services/rest/?';

  /**
   * The Illuminate Container
   * @var Container
   */
  protected static $container;

  /**
   * Setup an instance of the API
   *
   * @param string $key    The API key
   * @param string $secret The API secret key
   */
  public function __construct($key = null, $secret = null)
  {
    $key    = $key    ?: $this->getOption('api_key');
    $secret = $secret ?: $this->getOption('api_secret');

    $this->consumer = new OAuth\Consumer($key, $secret);
  }

  /**
   * Aliased calls
   *
   * @return Method
   */
  public function __call($method, $parameters)
  {
    // Catch aliased calls
    if ($method = $this->callMethodByAlias($method, $parameters)) {
      return $method;
    }

    throw new BadMethodCallException('The requested method "' .$method. '" does not exist');
  }

  /**
   * Call a method on the current API
   *
   * @param string $method     The method name
   * @param array  $parameters Its parameters
   *
   * @return Method
   */
  public function callMethod($method, $parameters = array())
  {
    return new Method($this, $method, $parameters);
  }

  /**
   * Call a method by its alias
   *
   * @param string $method     A method alias
   * @param array  $parameters The arguments array
   *
   * @return Method
   */
  protected function callMethodByAlias($method, $parameters)
  {
    $aliases = $this->getContainer()->getConfig()->get('methods');

    if (!array_key_exists($method, $aliases)) return false;

    // Get actual method name and arguments
    $argumentList = $aliases[$method];
    $method = preg_replace_callback('/[A-Z]/', function($match) {
      return '.'.strtolower($match[0]);
    }, $method, 1);

    // Rebuild parameters array
    foreach ($argumentList as $key => $argument) {
      $arguments[$argument] = Arrays::get($parameters, $key);
    }

    return $this->callMethod($method, $arguments);
  }

  /**
   * Directly get the results of a method
   *
   * @param string $method     The method name
   * @param array  $parameters Its parameters
   *
   * @return Results
   */
  public function getResultsOf($method, $parameters = array())
  {
    return $this->callMethod($method, $parameters)->getResults();
  }

  ////////////////////////////////////////////////////////////////////
  ///////////////////////////// INTERFACE ////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Get the API Consumer
   *
   * @return Consumer
   */
  public function getConsumer()
  {
    return $this->consumer;
  }

  /**
   * Get the currently authentified User
   *
   * @return User
   */
  public function getUser()
  {
    if ($this->user) return $this->user;

    return $this->user = $this->getContainer()->getSession()->get('flickering_oauth_user');
  }

  /**
   * Get an option from the config file
   *
   * @param string $option   The option to fetch
   * @param mixed  $fallback A fallback
   *
   * @return mixed
   */
  public function getOption($option, $fallback = null)
  {
    return $this->getContainer()->getConfig()->get('config.'.$option, $fallback);
  }

  ////////////////////////////////////////////////////////////////////
  ////////////////////////////// OPAUTH //////////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Get the Opauth configuration to use for Flickering
   *
   * @return array
   */
  private function getOpauthConfiguration()
  {
    $config = $this->getContainer()->getConfig()->get('opauth');
    $config['strategy_dir'] = __DIR__.'/../vendor/flickr';
    $config['Strategy']['Flickr']['key'] = $this->consumer->key;
    $config['Strategy']['Flickr']['secret'] = $this->consumer->secret;
    $config['callback_transport'] = 'post';

    return $config;
  }

  /**
   * Return Opauth instance for authentification
   *
   * @return Opauth
   */
  public function getOpauth()
  {
    return new Opauth($this->getOpauthConfiguration());
  }

  /**
   * Process the post-authentification response
   */
  public function getOpauthCallback()
  {
    new Opauth($this->getOpauthConfiguration(), false);

    // Store User credentials into session
    if (isset($_POST['opauth'])) {
      $response = unserialize(base64_decode($_POST['opauth']));
      $user = new OAuth\User($response['auth']['credentials']['token'], $response['auth']['credentials']['secret']);
      $this->getSession()->set('flickering_oauth_user', $user);
    }
  }

  ////////////////////////////////////////////////////////////////////
  /////////////////////////// DEPENDENCIES ///////////////////////////
  ////////////////////////////////////////////////////////////////////

  /**
   * Get the IoC Container
   *
   * @return Container
   */
  public function getContainer()
  {
    if (!static::$container) {
      static::$container = new Facades\Container;
    }

    return static::$container;
  }
}
