#!/usr/bin/php
<?php

require 'vendor/autoload.php';

use Carbon\Carbon;
use GuzzleHttp\Client;

class Alex {

  private $version = '1.0.0';

  private $live = false;

  private $start;

  private $end;

  public function __construct($start, $end, $live) {

    /**
     * Setup the dates
     */
    $this->start  = $start  ?: (new Carbon('first day of this month'))->format('Y-m-d');
    $this->end    = $end ?: (new Carbon('last day of this month'))->format('Y-m-d');
    $this->live   = $live;

    $this->run();

  } // end construct;

  public function run() {

    $client = new Client();

    $res = $client->request('GET', 'https://www.mercadobitcoin.net/api/BTC/ticker/', [
      'auth' => ['nextpress-nfe', 'Zu/6P8Lq']
    ]);

    var_dump( json_decode($res->getBody()) );

    die;

  }

} // end class Alex;

/**
 * Start the emission of the NFSEs
 *
 * @return void
 */
function start_alex() {

  global $argv;

  /**
   * Parse passed arguments
   */
  parse_str(implode('&', array_slice($argv, 1)), $args);

  new Alex(@$args['after'], @$args['before'], @$args['live']);

  die;

} // end start_alex;

// Run!
start_alex();