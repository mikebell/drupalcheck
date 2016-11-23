<?php

namespace mikebell\drupalcheck;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a set of tests for checking if a site is built with Drupal and a
 * simple runner for those tests.
 */
class DrupalCheck {
  var $url, $primary_response, $res, $version;
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
  public function isDrupal() {
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
    $url_parts = parse_url($this->url);
    $base_url = $url_parts['scheme'] . '://' . $url_parts['host'];

    // If we're at root then try and find misc/drupal.js
    try {
      $response = $this->guzzle->head($base_url . '/misc/drupal.js');
      if ($response->getStatusCode() == 200) {
        $this->is_drupal = TRUE;
        $this->results['misc/drupal.js'] = 'passed';
        return TRUE;
      }
      else {
        $this->results['misc/drupal.js'] = 'failed';
      }
    }
    catch (BadResponseException $e) {
      // Can't find the file.
      $this->results['misc/drupal.js'] = 'failed';
      $this->errors[] = $e;
    }
    catch (RequestException $e) {
      $this->results['misc/drupal.js'] = 'failed';
      $this->errors[] = $e;
    }

    $this->results['misc/drupal.js'] = 'failed';
    return false;
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
   * Test Five: Check for Drupal in X-Drupal-Cache header.
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
}
