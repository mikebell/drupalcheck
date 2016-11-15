#DrupalCheck

A simple PHP library to determine if a site is Drupal or not.
 
Based off the great work of [eojthebrave](eojthebrave) on [isthissitebuildwithdrupal.com](https://github.com/eojthebrave/isthissitebuiltwithdrupal_com).

## Install

```composer require mikebell/DrupalCheck```

## Usage

```
$test = new DrupalCheck($url);
$result = $test->isDrupal();
```