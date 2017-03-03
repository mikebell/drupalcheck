<?php

namespace mikebell\drupalcheck;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides a set of tests for checking if a site is built with Drupal and a
 * simple runner for those tests.
 */
class DrupalCheck {
  var $url, $primary_response, $res, $version = NULL;
  var $is_drupal = FALSE;
  var $results, $errors = array();
  /**
   * Initialize a new test client.
   *
   * @param $url
   *   URL of the site to check.
   */
  public function __construct($url) {
    $this->url = $url;
    $this->guzzle = new Client();
  }
  /**
   * Run all tests and check to see if a site is built with Drupal.
   *
   * @return bool
   *   TRUE if $this->url passes any of the tests otherwise FALSE.
   */
  public function testDrupal() {
    if ($this->getPage()) {
      $tests = array('testOne', 'testTwo', 'testThree', 'testFour', 'testFive', 'testSix');
      foreach ($tests as $test) {
        $this->$test();
      }
      if (in_array('passed', $this->results)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Is it a Drupal site?
   *
   * @return bool
   */
  public function getIsDrupal() {
    return $this->is_drupal;
  }

  /**
   * Alias of $this->testDrupal().
   *
   * @return bool
   */
  public function isDrupal() {
    return $this->testDrupal();
  }

  /**
   * Get the Drupal version discovered by tests, if possible.
   *
   * @return int|null
   */
  public function getVersion() {
    if ($this->getIsDrupal()) {
      return $this->version;
    }
    return NULL;
  }

  /**
   * Get the test results array.
   *
   * @return array
   */
  public function getResults() {
    return $this->results;
  }

  /**
   * Helper method, loads the content of the page at $this->url for other tests.
   *
   * This also has the side effect of checking for a 4** or 5** error and
   * aborting a test run if the URL can not be loaded or is malformed.
   *
   * @return bool
   */
  protected function getPage() {
    try {
      $this->primary_response = $this->guzzle->request('GET', $this->url, ['timeout' => 5, 'connect_timeout' => 5]);
    }
    catch (BadResponseException $e) {
      $this->errors[] = $e;
      return false;
    }
    catch (ConnectException $e) {
      $this->errors[] = $e;
      return false;
    }
    catch (RequestException $e) {
      $this->errors[] = $e;
      return false;
    }

    return true;
  }
  /**
   * Test One: Check for Dries' birthday in 'Expires' header.
   *
   * @return bool
   */
  protected  function testOne() {
    if ($this->primary_response->getHeaderLine('Expires') == 'Sun, 19 Nov 1978 05:00:00 GMT') {
      $this->is_drupal = TRUE;
      $this->results['expires header'] = 'passed';
      return TRUE;
    }
    else {
      $this->results['expires header'] = 'failed';
    }
    return FALSE;
  }
  /**
   * Test Two: Check for Drupal.settings in the body of the document.
   *
   * @return bool
   */
  protected function testTwo() {
    if (stristr($this->primary_response->getBody(), 'jQuery.extend(Drupal.settings')) {
      $this->is_drupal = TRUE;
      $this->results['drupal.settings'] = 'passed';
      return TRUE;
    }
    else {
      $this->results['drupal.settings'] = 'failed';
    }
    return FALSE;
  }
  /**
   * Test Three: Check for existence of misc/drupal.js.
   *
   * @return bool
   */
  protected function testThree() {
    // Check for the existence of misc/drupal.js, this is a pretty good giveaway.
    $uri =  new Uri($this->url);
    $base_url = $uri->withPath('');
    // Try to find misc/drupal.js
    $path = '/misc/drupal.js';

    // BUT (!) we need to handle redirects, so we don't get false positives
    $r = $this->requestHandleRedirect($base_url, $path, 'HEAD');
    if (!$r->getResponseField('error')) {
      /** @var ResponseInterface $response */
      $response = $r->getResponseField('response');
      // Effective URLs and HTTP status codes of redirect history
      $guzzle_effective_url = $response->getHeader('X-Guzzle-Effective-Url');
      $guzzle_effective_url_status = $response->getHeader('X-Guzzle-Effective-Url-Status');
      // get URL string and HTTP status code of last effective URL, after redirects
      $last_effective_url = end($guzzle_effective_url);
      $last_effective_url_status = end($guzzle_effective_url_status);
      // Make a url string into Psr7 Uri object
      $last_effective_uri = new Uri($last_effective_url);
      if (!in_array('ASP.NET', $response->getHeader('X-Powered-By')) && $last_effective_uri->getPath() == $path && $last_effective_url_status == 200) {
        $this->is_drupal = TRUE;
        $this->results['misc/drupal.js'] = 'passed';
        return TRUE;
      }
    }
    else {
//      drupal_set_message(t('Problem processing @site. Error: @message', ['@site' => $this->url, '@message' => $r->getResponseField('code') . ': ' . $r->getResponseField('reason')]));
    }

    $this->results['misc/drupal.js'] = 'failed';
    return FALSE;

  }

  /**
   * Test Four: Check for Drupal in X-Generator header.
   *
   * @return bool
   */
  protected  function testFour() {
    if (strpos($generator = $this->primary_response->getHeaderLine('X-Generator'), 'Drupal') !== false) {
      $this->is_drupal = TRUE;
      $this->results['x-generator header'] = 'passed';

      // If this test passes then we can get the Drupal version as well.
      preg_match('/\d/', $generator, $matches);
      $this->version = $matches[0];

      return TRUE;
    }
    else {
      $this->results['x-generator header'] = 'failed';
    }
    return FALSE;
  }

  /**
   * Test Five: Check for Drupal in X-Generator header.
   *
   * @return bool
   */
  protected  function testFive() {
    $header = $this->primary_response->getHeaderLine('X-Drupal-Cache');
    if (!empty($header)) {
      $this->is_drupal = TRUE;
      $this->results['x-drupal-cache header'] = 'passed';
      return TRUE;
    }
    else {
      $this->results['x-drupal-cache header'] = 'failed';
    }
    return FALSE;
  }

  /**
   * Test Six: check for generator in body content.
   *
   * @return bool
   */
  protected function testSix() {
    // Scan for meta tag "generator".
    $body = $this->primary_response->getBody();
    $body = (string) $body;
    preg_match('/<meta name="generator" content="Drupal .*"/', $body, $matches);
    if ($matches) {
      preg_match('/\d/', $matches[0], $drupal);
      $this->version = $drupal[0];
      $this->is_drupal = TRUE;
      $this->results['body-meta-generator'] = 'passed';
      return TRUE;
    }
    else {
      $this->results['body-meta-generator'] = 'failed';
      return false;
    }
  }

  /**
   * Make a request that handles following redirects.
   *
   * This is necessary where the redirect does not forward the requested path also.
   *
   * @param string|Uri $url
   * @param null $path
   * @param string $method
   * @return DrupalCheckRequestResponse
   */
  private function requestHandleRedirect($url, $path = NULL, $method = 'GET') {
    // Create an empty response object
    $return = new DrupalCheckRequestResponse();
    // The URL may be a Uri object or a string.
    if ($url instanceof Uri == FALSE) {
      $url = new Uri($url);
    }
    $effective_url = $url->withPath($path);

    try {

      $stack = HandlerStack::create();
      $stack->push(DrupalCheckEffectiveUrlMiddleware::middleware());

      $client = new Client([
        'handler' => $stack,
        'allow_redirects' => [
          'max'             => 5,
          'strict'          => FALSE,
          'referer'         => TRUE,
          'protocols'       => ['http', 'https'],
          'track_redirects' => TRUE,
        ],
        'on_stats' => function (TransferStats $stats) use (&$tStats) {
          $tStats = $stats;
        }
      ]);

      $response = $client->request($method, $effective_url->__toString());

      $return->addResponseField('location', $effective_url);
      $return->addResponseField('response', $response);
      $return->addResponseField('error', FALSE);
      $return->addResponseField('code', $response->getStatusCode());

      return $return;

    }
    catch (BadResponseException $e) {
      $return->addResponseField('location', $effective_url);
      $return->addResponseField('response', $e->getResponse());
      $return->addResponseField('error', TRUE);
      $return->addResponseField('code', $e->getCode());
      $return->addResponseField('reason', 'BadResponseException');
      return $return;
    }
    catch (RequestException $e) {
      $return->addResponseField('location', $effective_url);
      $return->addResponseField('response', $e->getResponse());
      $return->addResponseField('error', TRUE);
      $return->addResponseField('code', $e->getCode());
      $return->addResponseField('reason', 'RequestException');
      return $return;
    }
  }
}