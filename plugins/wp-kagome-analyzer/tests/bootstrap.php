<?php
/**
 * PHPUnit bootstrap for the pure-PHP Analyzer integration tests.
 *
 * These tests do not require WordPress; they only need Composer's autoloader
 * so that Hametuha\KagomeWp\Analyzer and the test classes resolve.
 *
 * @package Hametuha\KagomeWp\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
