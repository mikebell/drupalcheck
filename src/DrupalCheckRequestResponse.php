<?php
/**
 * @file DrupalCheckRequestResponse.php
 */

namespace mikebell\drupalcheck;

/**
 * Simple response object for a request response.
 */
class DrupalCheckRequestResponse {

  public function addResponseField($name, $value) {
    $this->{$name} = $value;
  }

  public function getResponseField($name) {
    return $this->{$name};
  }

  public function getResponseFields() {
    return get_object_vars($this);
  }

}