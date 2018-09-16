<?php

class Platform_Default extends Platform {

  public $base_values;

  public $title = 'Padrão (Simulador)';

  public function init() {

    $this->base_values = $this->get_base_values();

  } // end init;

  public function get_base_values() {

    return array(
      "high" => "27700.00000000", 
      "low"  => "27213.11999000", 
      "last" => "27213.11999000", 
      "buy"  => "27224.01018000", 
      "sell" => "27497.99995000", 
    );

  } // end base_values;

  public function get_percentage() {

    $chance = rand(1, 100);

    if ($chance <= 25) {

      return rand(-100, 100) / 1000;

    }

    return 0;

  } // end get_percentage;

  public function get_random_values() {

    $percentage = $this->get_percentage();

    $other_values = array(
      "vol"  => "100", 
      "date" => time(),
    );

    $new_values = array_map(function($item) use ($percentage) {

      $value = (float) $item;

      return number_format( $value * (1 + $percentage), 8, '.', '');

    }, $this->base_values);

    $this->base_values = $new_values;

    return (object) array_merge($other_values, $new_values);

  } // end get_random_values;

  public function buy($amount) {

  } // end buy;

  public function sell($amount) {

  } // end sell;

  public function get_account_balance() {

    $balance = new Alex_Balance;

    $balance->add_coin('brl', 1000);
    $balance->add_coin('btc', 0.0038);

    return $balance;

  } // end get_account_balance;

  public function get_balance() {

  } // end get_coin_balance;

  public function ticker() {

    // var_dump($this->get_random_values());

    return $this->get_random_values();

  } // end ticker;

} // end Platform_Default;

function get_platform($coin) {

  return new Platform_Default($coin);

}  // end get_platform;