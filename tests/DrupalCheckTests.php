<?php

use PHPUnit\Framework\TestCase;
use mikebell\drupalcheck\DrupalCheck;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class DrupalCheckTest extends TestCase {

  public function testisDrupal() {
    $site = new DrupalCheck('https://drupal.org');

    $site->isDrupal();

    $this->assertContains('passed', $site->results['expires header'], 'Expires header is Dries birthday');
    $this->assertContains('passed', $site->results['drupal.settings'], 'Contains drupal.settings');
    $this->assertContains('passed', $site->results['misc/drupal.js'], 'Contains misc/drupal.js');
    $this->assertContains('passed', $site->results['x-generator header'], 'x-generator header is set to Drupal x');
    $this->assertContains('passed', $site->results['x-drupal-cache header'], 'x-drupal-cache header exists');
  }
}