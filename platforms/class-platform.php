<?php

class Platform {

  public $coin;

  public $title = 'Padrão (Simulador)';

  public $fee = 0;

  public function __construct($coin = 'BTC') {

    $this->coin = $coin;

    $this->init();

  } // end construct;

  public function apply_fees($value) {

    return $value * (1 - $this->fee);

  } // end apply_fees;

  public function dispatch($order) {

  } // end dispatch;

  public function buy($amount) {

  } // end buy;

  public function sell($amount) {

  } // end sell;

  public function get_account_balance() {

  } // end get_balance;

  public function get_balance() {

  } // end get_coin_balance;

  public function ticker($coin_balance) {

  } // end ticker;

} // end Platform_Default;