#!/usr/local/bin/php
<?php

date_default_timezone_set("America/Sao_Paulo");
// pcntl_async_signals(true);

require 'vendor/autoload.php';
require 'inc/console.php';
require 'inc/class-alex-balance.php';

use Carbon\Carbon;
use GuzzleHttp\Client;

class Alex {

  private $version = '1.0.0';

  private $live = false;

  private $platform;

  private $client;

  private $coin;

  private $frequency = 60;
  
  private $status = 0;
  
  private $status_title = 0;
  
  private $sell_at = array(3, -5);
  
  private $buy_at = array(1.5, -6);

  // private $sell_at = array(0.5, -1);
  
  // private $buy_at = array(0.5, -2);
  
  private $balance = 1000;

  private $balance_coins = 0;
  
  private $limit = false;

  private $current_value;

  private $first_value;

  const STATUS_INITIAL_BUY = 0;

  const STATUS_WAITING_BUY = 1;

  const STATUS_WAITING_SELL = 2;

  public function __construct($coin, $platform, $live = 0, $limit = false, $frequency = 60) {

    /**
     * Coin
     */
    $this->coin = $coin ?: 'BTC';

    /**
     * Platform
     */
    $this->platform = $this->build_platform(($platform ?: 'default'), $this->coin);

    /**
     * Env
     */
    $this->live = $live;

    /**
     * Frequency of checking
     */
    $this->frequency = $frequency ?: 60;

    /**
     * Expending Limit 
     */
    $this->limit = is_numeric($limit) ? $limit : 9999999999;

    /**
     * Creates the HTTP client for future requests
     */
    $this->client = new Client();

    /**
     * Get the current Coin Value
     */
    $this->current_value = $this->first_value = $this->get_coin_value();

    /**
     * Get Account Balance
     */
    $this->balance = $this->get_account_balance();

    /**
     * Run, Forest, Run!
     */
    $this->run();

  } // end construct;

  public function build_platform($platform, $coin) {

    require 'platforms/class-platform.php';

    require "platforms/class-platform-{$platform}.php";

    return get_platform($coin);

  } // end build_platform;

  /**
   * Does a little jump of lines
   *
   * @return void
   */
  public function jump($lines = 1) {

    $i = 1;

    while($i <= $lines) {
      echo PHP_EOL;
      $i++;
    }

  } // end jump;

  /**
   * Prints the header of the emitter
   *
   * @return void
   */
  public function print_header() {

    $this->jump();

    Console::log('- Oi, eu sou Alex!', 'light_green');

    $this->jump();
    
    Console::log(sprintf('Versão %s', $this->version), 'light_green');
    
    Console::log(sprintf('Ambiente: %s', $this->live ? 'Produção' : 'Testes'), $this->live ? 'light_red' : 'light_blue');
    
    $this->jump();
    
    Console::log(sprintf('Moeda de Negociação: %s', $this->coin), 'light_green');
    
    Console::log(sprintf('Limite: %s', $this->format_value($this->limit)), 'light_green');
    
    Console::log(sprintf('Frequência: %s segundos', $this->frequency), 'light_green');

    Console::log(sprintf('Plataforma: %s', $this->platform->title), 'light_green');

    $this->jump();

    Console::log('Balanças Disponíveis:', 'light_green');

    Console::log(sprintf('|- BRL - Disponível: %s, Total: %s', $this->format_value($this->balance->get_coin('brl')->available), $this->format_value($this->balance->get_coin('brl')->total)), 'light_green');
    Console::log(sprintf('|- %s - Disponível %s, Total: %s', $this->coin, $this->balance->get_coin( strtolower($this->coin) )->available, $this->balance->get_coin( strtolower($this->coin) )->total), 'light_green');

  } // end print_header;

  public function run() {

    $this->print_header();

    /**
     * Start Loop
     */
    while(true) {

      try {
        
        $this->run_routines();

      } catch (Exception $e) {

        $this->console_with_time(sprintf('Erro: %s', $e->getMessage()), 'red');

      } // end try

      sleep($this->frequency);

    } // end loop;

  } // end run;

  public function get_status_label($status) {

    $labels = array(
      self::STATUS_INITIAL_BUY  => 'Esperando para compra inicial...',
      self::STATUS_WAITING_BUY  => 'Esperando para comprar...',
      self::STATUS_WAITING_SELL => 'Esperando para vender...',
    );

    return $labels[$status];

  } // end get_status_label;

  public function display_status_title() {

    if (!$this->status_title) {

      echo PHP_EOL;

      Console::log($this->get_status_label($this->status), 'light_green');

      $this->status_title = 1;

    } // end if;

  } // end display_status_title;

  public function set_status($status) {

    $this->status = $status;
    $this->status_title = 0;

  } // end set_status;

  public function get_status() {

    return $this->status;

  } // end get_status;

  public function run_routines() {

    switch ($this->get_status()) {

      case self::STATUS_INITIAL_BUY:
        $this->waiting_to_buy();
        break;

      case self::STATUS_WAITING_BUY:
        $this->waiting_to_buy();
        break;

      case self::STATUS_WAITING_SELL:
        $this->waiting_to_sell();
        break;

    } // end switch;

  } // end run_routines;

  public function get_available_balance() {

    $brl = $this->balance->get_coin('brl')->available;

    return $balance = $brl >= $this->limit ? $this->limit : $brl;

  } // end get_available_balance;

  public function build_order($action, $price) {

    if ($action == 'buy') {

      $action_multiplier = -1;

      $balance = $this->get_available_balance();

      $quantity = $balance / $price;

    } else if ($action == 'sell') {

      $action_multiplier = 1;

      $quantity = $this->balance->get_coin(strtolower($this->coin))->available;

    } // end if;

    return (object) array(
      'action'      => $action,
      'quantity'    => $quantity,
      'limit_price' => $price,
      'order_price' => $quantity * $price * $action_multiplier,
      'message'     => sprintf('Ordem de "%s" criada com quantidade %s, a um valor máximo de %s', $action, $quantity, $this->format_value($price)),
    );

  } // end build_order;

  public function dispatch($order) {

    if ($this->live) {

      $this->console_with_time(sprintf('Enviando ordem para plataforma %s', $this->platform->title), 'light_purple');
      
      $results = $this->platform->dispatch($order);

      return true;

    } else {

      $this->console_with_time('Ambiente de testes, a ordem não será executada.', 'light_purple');

      return true;

    } // end if;

  } // end dispatch;

  /**
   * Handles the initial purchase of coins
   *
   * @return void
   */
  public function waiting_to_buy() {

    $this->display_status_title();

    $comparation = $this->fetch_and_compare($this->buy_at);

    if (!$comparation) return;

    $this->console_with_time($comparation->message, $this->get_display_color_for_variation($comparation->difference));

    if ($comparation->is_above_threshold) {

      echo PHP_EOL;

      $this->console_with_time(sprintf('Limite para compra atingido (%s%%), criando ordem de compra...', $this->buy_at[$comparation->which_threshold]), 'light_purple');

      $order = $this->build_order('buy', $comparation->new_value);

      $this->console_with_time($order->message, 'light_purple');

      $success = $this->dispatch($order);

      if ($success) {

        /**
         * TODO: Refetch balance
         */
        $this->balance->add_coin('brl', $order->order_price);
        
        $this->balance->add_coin(strtolower($this->coin), $order->quantity);

        $this->console_with_time(sprintf('|- Nova Balança: %s', $this->format_value($this->balance->get_coin('brl')->available)), 'light_purple');

         /**
          * Set the new value as the current value
          */
        $this->current_value = $comparation->ticker;

        /**
         * Set the status to wait for sale
         */
        $this->set_status(self::STATUS_WAITING_SELL);

      } // end if;

    } // end if;

  } // end initial_purchase;

  public function waiting_to_sell() {

    $this->display_status_title();

    $comparation = $this->fetch_and_compare($this->sell_at);

    $this->console_with_time($comparation->message, $this->get_display_color_for_variation($comparation->difference));

    if ($comparation->is_above_threshold) {

      echo PHP_EOL;

      $this->console_with_time(sprintf('Limite para venda atingido (%s%%), criando ordem de compra...', $this->sell_at[$comparation->which_threshold]), 'light_purple');

      $order = $this->build_order('sell', $comparation->new_value);

      $this->console_with_time($order->message, 'light_purple');

      $success = $this->dispatch($order);

      if ($success) {

        /**
         * TODO: Refetch balance
         */
        $this->balance->add_coin('brl', $order->order_price);
        
        $this->balance->remove_coin(strtolower($this->coin), $order->quantity);

        $this->console_with_time(sprintf('|- Nova Balança: %s', $this->format_value($this->balance->get_coin('brl')->available)), 'light_purple');

         /**
          * Set the new value as the current value
          */
        $this->current_value = $comparation->ticker;

        /**
         * Set the status to wait for sale
         */
        $this->set_status(self::STATUS_WAITING_BUY);

      } // end if;

    } // end if;

  } // end initial_purchase;

  public function console_with_time($message, $color) {

    Console::log(sprintf('%s - %s', Carbon::now()->format('d-m-Y H:i:s'), $message), $color);

  } // end console_with_time

  public function get_display_color_for_variation($variation) {

    if ($variation > 0) {

      return 'light_green';

    } else if ($variation == 0) {

      return 'light_blue';

    }

    return 'light_red';

  } // end get_display_color_for_variation;

  public function format_value($number) {

    return 'R$ ' . number_format($number, 2, ',', '.');

  } // end format_values;

  public function fetch_and_compare($compare) {

    $new_value = $this->get_coin_value();

    /**
     * Error returning new values
     */
    if (!$new_value) {

      return;

    } // end if;

    /**
     * No current value saved
     */
    if (!$this->current_value) {

      $this->current_value = $new_value;

      return (object) array(
        'previous_value'     => $this->current_value->sell,
        'new_value'          => $new_value->sell,
        'ticker'             => $new_value,
        'difference'         => 0,
        'is_above_threshold' => false,
        'which_threshold'    => 0,
        'message'            => 'Sem valor de referência. Adicionando novo valor...',
      );

    } // end if;

    /**
     * Compare
     */
    $difference = $this->get_percentage_difference($this->current_value->sell, $new_value->sell);

    return (object) array(
      'previous_value'     => $this->current_value->sell,
      'new_value'          => $new_value->sell,
      'ticker'             => $new_value,
      'difference'         => $difference,
      'is_above_threshold' => $difference >= $compare[0] || $difference <= $compare[1],
      'which_threshold'    => $difference >= $compare[0] ? 0 : 1,
      'message'            => sprintf('O preço era %s e agora é %s, uma variação de %s%%', $this->format_value($this->current_value->sell), $this->format_value($new_value->sell), number_format($difference, 8)),
    );

  } // end fetch_and_compare;

  public function get_percentage_difference($before, $after) {

    return (1 - $before / $after) * 100;

  } // end get_percentage_difference;

  public function get_coin_value() {

    try {

      return $this->platform->ticker();

    } catch(Exception $e) {

      $this->console_with_time(sprintf('Erro: %s', $e->getMessage()), 'red');

    } // end try_catch;

  } // end get_current_value;

  public function get_account_balance() {

    try {

      return $this->platform->get_account_balance();

    } catch(Exception $e) {

      $this->console_with_time(sprintf('Erro: %s', $e->getMessage()), 'red');

    } // end try_catch;

  } // end get_current_value;

  public function call_shutdown($signal) {
    echo $signal;
    // if ($signal === SIGINT || $signal === SIGTERM) {
        $this->shutdown();
    // }
  }

  private function shutdown() {
    
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

  if (isset($args['help'])) {

    return print_help();

  } // end if;

  return new Alex(@$args['coin'], @$args['platform'], @$args['live'], @$args['limit'], @$args['frequency']);

} // end start_alex;

function print_help() {

  Console::log('Eu sou Alex, o robo de trading!', 'light_green');
  echo PHP_EOL;

  Console::log('Parâmetros disponíveis:', 'light_green');

  $params = array(
    'help'      => 'Mostra ajuda sobre os parâmetros de configuração',
    'coin'      => 'Seleciona que moeda deve ser utilizada. Padrão: BTC',
    'limit'     => 'Valor maximo em reais a ser utilizado',
    'live'      => 'Seleciona que ambiente deve ser usado, simulação ou real. Padrão: Simulação (0)',
    'frequency' => 'Muda com que frequência o Ticker deve ser checado. Padrão: 60 segundos',
  );

  foreach($params as $name => $desc) {

    Console::log(sprintf('%s -> %s', $name, $desc), 'light_green');

  } // end foreach;

  exit;

} // end print_help;

// Run!
$alex = start_alex();