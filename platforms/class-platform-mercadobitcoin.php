<?php

use GuzzleHttp\Client;

class Platform_Mercado_Bitcoin extends Platform {

  public $title = 'Mercado Bitcoin';

  public $client;

  public function init() {

    /**
     * Creates the HTTP client for future requests
     */
    $this->client = new Client();

  } // end init;

  public function buy($amount) {

  } // end buy;

  public function sell($amount) {

  } // end sell;

  public function get_account_balance() {

  } // end get_balance;

  public function get_balance() {

  } // end get_coin_balance;

  public function ticker() {

    $res = $this->client->request('GET', "https://www.mercadobitcoin.net/api/$this->coin/ticker/");

    $results = json_decode($res->getBody());

    return $results->ticker;

  } // end ticker;

} // end Platform_Mercado_Bitcoin;

function get_platform($coin) {

  return new Platform_Mercado_Bitcoin($coin);

}  // end get_platform;