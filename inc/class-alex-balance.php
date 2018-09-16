<?php

class Alex_Balance {
  
  private $brl;

  private $btc;

  private $ltc;

  private $bch;

  public function __construct() {

    foreach($this->get_available_coins() as $coin) {

      $this->{$coin} = array(
        'available' => 0,
        'total'     => 0,
      );

    } // end foreach;

  } // end __construct;

  public function get_available_coins() {

    return array(
      'brl',
      'btc',
      'ltc',
      'bch',
    );

  } // end get_available_coins;

  public function set_balance($values) {

    if (!is_array($values)) $values = json_decode(json_encode($values), true);

    foreach($this->get_available_coins() as $coin) {

      foreach(['available', 'total'] as $balance) {

        if (isset($values[$coin][$balance])) {

          $this->{$coin}[$balance] = (float) $values[$coin][$balance];

        } // end if;

      } // end foreach;

    } // end foreach;

    return $this;

  } // end set_values;

  public function get_balance() {

    $balance = array();

    foreach($this->get_available_coins() as $coin) {

      $balance[$coin] = $this->{$coin};

    } // end foreach;

    return $balance;

  } // end get_values;

  public function get_coin($coin) {

    return (object) $this->{$coin};

  } // end get_coin;

  public function add_coin($coin, $amount) {

    foreach(['available', 'total'] as $balance) {

      $this->{$coin}[$balance] += $amount;

      if ($this->{$coin}[$balance] < 0) {

        $this->{$coin}[$balance] = 0;

      } // end if;

    } // end foreach;

  } // end add_coin;

  public function remove_coin($coin, $amount) {

    foreach(['available', 'total'] as $balance) {

      $this->{$coin}[$balance] -= $amount;

      if ($this->{$coin}[$balance] < 0) {

        $this->{$coin}[$balance] = 0;

      } // end if;

    } // end foreach;

  } // end add_coin;

} // end class Alex_Balance;