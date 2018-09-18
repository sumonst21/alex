<?php

use GuzzleHttp\Client;
use Symfony\Component\Dotenv\Dotenv;

class Platform_Mercado_Bitcoin extends Platform {

  public $id = 'mercadobitcoin';

  public $title = 'Mercado Bitcoin';
  
  public $fee = 0.008;

  public $client;

  protected $api;

  protected $pair;

  public function init() {

    /**
     * Allow us to use environment variables
     */
    $dotenv = new Dotenv();
    $dotenv->load(__DIR__ . '/../.env');

    /**
     * Creates the HTTP client for future requests
     */
    $this->client = new Client();

    /**
     * Sets up an API
     */
    $this->api = new JonasOF\PHPMercadoBitcoinAPI\PHPMercadoBitcoinAPI([
      "TAPI_ID"       => getenv('TAPI_ID'),
      "TAPI_PASSWORD" => getenv('TAPI_PASSWORD'),
    ], new JonasOF\PHPMercadoBitcoinAPI\NonceMethods\Timestamp);

    /**
     * Set the pair
     */
    $this->pair = "BRL".strtoupper($this->coin);

  } // end init;

  public function format_value($value) {

    return number_format($value, 8, '.', '');

  } // end format_value;

  public function dispatch($order) {

    if ($order->action == 'buy') {

      return $this->buy($order);

    } else if ($order->action == 'sell') {

      return $this->sell($order);

    } // end if;

  } // end dispatch;

  public function buy($order) {

    $quantity = $this->apply_fee($order->quantity);

    $results = $this->api->placeBuyOrder(array(
      'coin_pair'   => $this->pair,
      'quantity'    => $this->format_value($quantity),
      'limit_price' => $order->limit_price,
    ));

    if (isset($results->error_message)) {

      throw new Exception($results->error_message);

    } // end if;

    return true;

  } // end buy;

  public function sell($order) {

    $quantity = $this->apply_fee($order->quantity);

    $results = $this->api->placeSellOrder(array(
      'coin_pair'   => $this->pair,
      'quantity'    => $this->format_value($quantity),
      'limit_price' => $order->limit_price,
    ));

    if (isset($results->error_message)) {

      throw new Exception($results->error_message);

    } // end if;

    return true;

  } // end sell;

  /**
   * Gets the balance
   *
   * @return Alex_Balance
   */
  public function get_account_balance() {

    $balance = new Alex_Balance;

    $results = $this->api->getAccountInfo();

    if (isset($results->error_message)) {

      throw new Exception($results->error_message);

    } // end if;

    return $balance->set_balance($results->response_data->balance);

  } // end get_account_balance;

  public function get_balance() {

  } // end get_coin_balance;

  public function ticker($action) {

    $res = $this->client->request('GET', "https://www.mercadobitcoin.com.br/api/$this->coin/orderbook/");

    $results = json_decode($res->getBody());

    $list = $action == 'buy' ? 'asks' : 'bids';

    $highest_value = $results->{$list}[0][0];

    return (object) array(
      "last" => $highest_value,
      "high" => $highest_value,
      "low"  => $highest_value,
      "buy"  => $highest_value,
      "sell" => $highest_value,
    );

  } // end ticker;

} // end Platform_Mercado_Bitcoin;

function get_platform($coin) {

  return new Platform_Mercado_Bitcoin($coin);

}  // end get_platform;