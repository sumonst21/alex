#!/usr/local/bin/php
<?php
/**
 * Sets the timezine to São Paulo
 */
date_default_timezone_set("America/Sao_Paulo");

pcntl_async_signals(true);

require 'vendor/autoload.php';
require 'inc/console.php';
require 'inc/class-alex-balance.php';

use Carbon\Carbon;
use GuzzleHttp\Client;

/**
 * Tells PHP we want to catch CLI signals
 */
pcntl_signal(SIGTERM, 'signal_handler');
pcntl_signal(SIGINT, 'signal_handler');

/**
 * Handles Terminal Signals such as ctrl+c and ctrl+z combinations
 *
 * @param int $signal
 * @return void
 */
function signal_handler($signal) {

  /**
   * Calls the shutdown
   * @since 1.0.0
   */
  Alex::get_instance()->shutdown();

} // end signal_handler;

class Alex implements \Serializable {

  /**
   * Statuses
   */
  const STATUS_INITIAL_BUY  = 0;
  const STATUS_WAITING_BUY  = 1;
  const STATUS_WAITING_SELL = 2;

  /**
   * Holds the instance of Alex
   *
   * @since 1.0.0
   * @var Alex
   */
  private static $instance;

  /**
   * Current version of Alex
   *
   * @since 1.0.0
   * @var string
   */
  public $version = '1.0.0';

  /**
   * Is this live
   *
   * @since 1.0.0
   * @var boolean
   */
  public $live = false;

  /**
   * Holds the platform class, once instantiated
   *
   * @since 1.0.0
   * @var Platform
   */
  public $platform;

  /**
   * Coin being traded. BTC, LTC, etc
   *
   * @since 1.0.0
   * @var string
   */
  public $coin;

  /**
   * How frequent we should check for price updates, in seconds
   *
   * @since 1.0.0
   * @var integer
   */
  public $frequency = 60;
  
  /**
   * Holds the current status of the bot. The different statuses tell which actions should be run inside the run_routines method
   *
   * @since 1.0.0
   * @var integer
   */
  public $status = 0;
  
  /**
   * Keeps track if we just changed status
   *
   * @since 1.0.0
   * @var integer
   */
  public $status_title = 0;
  
  /**
   * Sell stop pair. A array containing the values where we should sell our coins
   *
   * @since 1.0.0
   * @var array
   */
  public $sell_at = array(3, -5);
  
  /**
   * Buy stop pair. A array containing the values where we should buy our coins
   *
   * @since 1.0.0
   * @var array
   */
  public $buy_at = array(1.5, -3);
  
  /**
   * Holds the Alex_Balance instance for control
   *
   * @since 1.0.0
   * @var Alex_Balance
   */
  public $balance;
  
  /**
   * Let's suppose you have $3000 on your trading account, but wants Alex to have access to just $200, this is what the limit is for
   *
   * @since 1.0.0
   * @var false|float
   */
  public $limit = false;

  /**
   * Holds the current ticker. Should implement Alex_Ticker
   *
   * @since 1.0.0
   * @var Alex_Ticker
   */
  public $current_value;

  /**
   * Holds the first ticker. Should implement Alex_Ticker
   *
   * @since 1.0.0
   * @var Alex_Ticker
   */
  public $first_value;

  /**
   * Instantiate a new Alex instance
   *
   * @param string $coin
   * @param string $platform
   * @param integer $live
   * @param boolean $limit
   * @param integer $frequency
   * @param boolean $buy_at
   * @param boolean $sell_at
   * @return Alex
   */
  public static function create_instance($coin = 'BTC', $platform = 'default', $live = 0, $limit = false, $frequency = 60, $buy_at = false, $sell_at = false, $status = 0) {

    if (null === self::$instance) {
      
      self::$instance = new self($coin, $platform, $live, $limit, $frequency, $buy_at, $sell_at, $status);

    } // end if;

    return self::$instance;
    
  } // end get_instance;

  /**
   * Returns the current and only Alex instance available
   *
   * @since 1.0.0
   * @return Alex
   */
  public static function get_instance() {

    return self::$instance;
    
  } // end get_instance;

  /**
   * Constructs Alex, the bot
   *
   * @since 1.0.0
   * @param string  $coin      Uppercase coin symbol, like BTC, LTC, etc
   * @param string  $platform  Which platform to use, currently we have a simulator (default) and Mercado Bitcoin (mercadobitcoin)
   * @param integer $live      IF we are in a live environment or not (takes 0 or 1 as parameters)
   * @param boolean $limit     How much money should the bot negotiate? 
   * @param integer $frequency How frequently, in seconds, should we call the ticker API
   * @param boolean $buy_at    Comma-separeted list of stop points to buy
   * @param boolean $sell_at   Comma-separeted list of stop points to sell
   */
  public function __construct($coin = 'BTC', $platform = 'default', $live = 0, $limit = false, $frequency = 60, $buy_at = false, $sell_at = false, $status = 0) {

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
     * Set the initial status
     */
    $this->status = $status;

    /**
     * Expending Limit 
     */
    $this->limit = is_numeric($limit) ? $limit : 9999999999;

    /**
     * Buy and Sell At
     */
    if ($buy_at) {

      $this->buy_at = $this->get_stop_array($buy_at);

    } // end buy_at;

    if ($sell_at) {

      $this->sell_at = $this->get_stop_array($sell_at);

    } // end buy_at;

    /**
     * Get the current Coin Value
     */
    $this->current_value = $this->get_coin_value();
    $this->first_value   = $this->current_value;

    /**
     * Get Account Balance
     */
    $this->balance = $this->get_account_balance();

  } // end construct;

  /**
   * Serialize the class so we can take it from where we left it off the last time
   *
   * @since 1.0.0
   * @return string
   */
  public function serialize() {

    $this->platform = $this->platform->id;

    $data = get_object_vars($this);

    return serialize($data);
      
  } // end serialize;

  /**
   * Handles the unserialization of Alex once we use last to retrieve the last session
   *
   * @param string $data
   * @return void
   */
  public function unserialize($data) {

    $data = unserialize($data);

    $alex = self::create_instance(
      @$data['coin'], 
      @$data['platform'], 
      @$data['live'], 
      @$data['limit'], 
      @$data['frequency']
    );
 
    // Set our values
    if (is_array($data)) {

      foreach ($data as $k => $v) {

        $this->$k = $v;

      } // end foreach;

    } // end if;

    $this->platform = $this->build_platform(($data['platform']), $this->coin);

  } // end unserialize;

  /**
   * Gets the stop array string and breaks it up into an array
   *
   * @since 1.0.0
   * @param string $string
   * @return array
   */
  public function get_stop_array($string) {

    $values = explode(',', $string);

    return array_map(function($item) {

      return (float) $item;

    }, $values);

  } // end get_stop_array;

  /**
   * Load the necessary platform files and builds an Platform object
   * 
   * You can implement your own platforms and put them inside the platforms folder
   * 
   * @since 1.0.0 
   * @param string $platform
   * @param string $coin
   * @return Platform
   */
  public function build_platform($platform, $coin) {

    require_once 'platforms/class-platform.php';

    require_once "platforms/class-platform-{$platform}.php";

    return get_platform($coin);

  } // end build_platform;

  /**
   * Does a little jump of lines on the console
   * TODO: move this to the console classe
   *
   * @since 1.0.0
   * @return void
   */
  public function jump($lines = 1) {

    $i = 1;

    while($i <= $lines) {

      echo PHP_EOL; $i++;

    } // end while;

  } // end jump;

  /**
   * Sends a console message with the current time attached to it
   * TODO: move to Console class
   *
   * @since 1.0.0
   * @param string $message
   * @param string $color
   * @return void
   */
  public function console_with_time($message, $color) {

    Console::log(sprintf('%s - %s', Carbon::now()->format('d-m-Y H:i:s'), $message), $color);

  } // end console_with_time;

  /**
   * Prints the header Alex to the Console
   *
   * @since 1.0.0
   * @return void
   */
  public function print_header() {

    $this->jump();

    Console::log(str_repeat('-', 30), 'light_green');

    Console::log(str_pad(" Oi! Eu sou Alex! ", 30, '-', STR_PAD_BOTH), 'light_green');

    Console::log(str_repeat('-', 30), 'light_green');

    $this->jump();
    
    Console::log(sprintf('Versão %s', $this->version), 'light_green');
    
    Console::log(sprintf('Ambiente: %s', $this->live ? 'Produção' : 'Testes'), $this->live ? 'light_red' : 'light_blue');
    
    $this->jump();
    
    Console::log(sprintf('Moeda de Negociação: %s', $this->coin), 'light_green');
    
    Console::log(sprintf('Limite: %s', $this->format_value($this->limit)), 'light_green');
    
    Console::log(sprintf('Buy: %s', implode(', ', $this->buy_at)), 'light_green');

    Console::log(sprintf('Sell: %s', implode(', ', $this->sell_at)), 'light_green');
    
    Console::log(sprintf('Frequência: %s segundos', $this->frequency), 'light_green');

    Console::log(sprintf('Plataforma: %s', $this->platform->title), 'light_green');

    $this->jump();

    Console::log('Balanças Disponíveis:', 'light_green');

    Console::log(sprintf('|- BRL - Disponível: %s, Total: %s', $this->format_value($this->balance->get_coin('brl')->available), $this->format_value($this->balance->get_coin('brl')->total)), 'light_green');
    Console::log(sprintf('|- %s - Disponível %s, Total: %s', $this->coin, $this->balance->get_coin( strtolower($this->coin) )->available, $this->balance->get_coin( strtolower($this->coin) )->total), 'light_green');

  } // end print_header;

  /**
   * Main action of the bot, runs the strategy itself
   *
   * @since 1.0.0
   * @return void
   */
  public function run() {

    /**
     * Prints the header of Alex
     */
    $this->print_header();

    /**
     * Start Loop
     */
    while(true) {

      try {
        
        /**
         * Call the main routines that walk the various status available
         */
        $this->run_routines();

      } catch (Exception $e) {

        /**
         * Catch and log exceptions without breaking execution,
         * We want the bot to run as long as you want before killing it
         */
        $this->console_with_time(sprintf('Erro: %s', $e->getMessage()), 'red');

      } // end try

      sleep($this->frequency);

    } // end loop;

  } // end run;

  /**
   * Gets the label for a given status
   *
   * @since 1.0.0
   * @param string $status
   * @return string
   */
  public function get_status_label($status) {

    $labels = array(
      self::STATUS_INITIAL_BUY  => 'Esperando para compra inicial...',
      self::STATUS_WAITING_BUY  => 'Esperando para comprar...',
      self::STATUS_WAITING_SELL => 'Esperando para vender...',
    );

    return $labels[$status];

  } // end get_status_label;

  /**
   * Displays the status title only if it was never displayed before, to prevent screen polution
   *
   * @since 1.0.0
   * @return void
   */
  public function display_status_title() {

    if (!$this->status_title) {

      echo PHP_EOL;

      Console::log($this->get_status_label($this->status), 'light_green');

      $this->status_title = 1;

    } // end if;

  } // end display_status_title;

  /**
   * Change the status of the bot and re-sets the display label control
   *
   * @since 1.0.0
   * @param int $status
   * @return void
   */
  public function set_status($status) {

    $this->status = $status;
    $this->status_title = 0;

  } // end set_status;

  /**
   * Returns the current status of the bot
   *
   * @since 1.0.0
   * @return int
   */
  public function get_status() {

    return $this->status;

  } // end get_status;

  /**
   * Holds the routines (tells the bot what to do on each status).
   * 
   * In the future we can allow users to implement different buying strategies
   *
   * @since 1.0.0
   * @return void
   */
  public function run_routines() {

    /**
     * Check the current status
     */
    switch ($this->get_status()) {

      /**
       * Waits for the threshold before doing an initial purchase
       */
      case self::STATUS_INITIAL_BUY:
        $this->waiting_to_buy();
        break;

      /**
       * Bot is waiting to buy...
       */
      case self::STATUS_WAITING_BUY:
        $this->waiting_to_buy();
        break;

      case self::STATUS_WAITING_SELL:
        $this->waiting_to_sell();
        break;

    } // end switch;

  } // end run_routines;

  /**
   * Get the available balance in fiat money for purchases, taking the limit into consideration
   *
   * @since 1.0.0
   * @return float
   */
  public function get_available_balance() {

    $brl = $this->balance->get_coin('brl')->available;

    return $balance = $brl >= $this->limit ? $this->limit : $brl;

  } // end get_available_balance;

  /**
   * Creates an Order
   *
   * @param string $action Type of the order, buy or sell
   * @param float  $price
   * @return object
   */
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

  /**
   * Takes in an order object and sends it over to the platform to handle it, if live mode is on
   *
   * @since 1.0.0
   * @param object $order A valid order object
   * @return bool
   */
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
   * Handles the waiting to buy phase of the process
   *
   * @since 1.0.0
   * @return void
   */
  public function waiting_to_buy() {

    $this->display_status_title();

    $comparation = $this->fetch_and_compare($this->buy_at);

    if (!$comparation) return;

    $this->console_with_time($comparation->message, $this->get_display_color_for_variation($comparation->difference));

    if ($comparation->is_above_threshold) {

      Console::bell(2);

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

  /**
   * Handles the waiting to sell phase of the process
   *
   * @since 1.0.0
   * @return void
   */
  public function waiting_to_sell() {

    $this->display_status_title();

    $comparation = $this->fetch_and_compare($this->sell_at);

    $this->console_with_time($comparation->message, $this->get_display_color_for_variation($comparation->difference));

    if ($comparation->is_above_threshold) {

      Console::bell(2);

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

  /**
   * Decides with color to use on the console, depending on the variation of prices
   *
   * @since 1.0.0
   * @param float $variation
   * @return void
   */
  public function get_display_color_for_variation($variation) {

    if ($variation > 0) {

      return 'light_green';

    } else if ($variation == 0) {

      return 'light_blue';

    }

    return 'light_red';

  } // end get_display_color_for_variation;

  /**
   * Format a value
   * TODO: move to helper class?
   *
   * @since 1.0.0
   * @param float $number
   * @return string
   */
  public function format_value($number) {

    return 'R$ ' . number_format($number, 2, ',', '.');

  } // end format_values;

  /**
   * Fetch a new ticker object and compares it with the current one in memory
   *
   * @since 1.0.0
   * @param array $compare Pair of stop points
   * @return void
   */
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
        'previous_value'     => $this->current_value->last,
        'new_value'          => $new_value->last,
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
    $difference = $this->get_percentage_difference($this->current_value->last, $new_value->last);

    return (object) array(
      'previous_value'     => $this->current_value->last,
      'new_value'          => $new_value->last,
      'ticker'             => $new_value,
      'difference'         => $difference,
      'is_above_threshold' => $difference >= $compare[0] || $difference <= $compare[1],
      'which_threshold'    => $difference >= $compare[0] ? 0 : 1,
      'message'            => sprintf('O preço era %s e agora é %s, uma variação de %s%%', $this->format_value($this->current_value->last), $this->format_value($new_value->last), number_format($difference, 8)),
    );

  } // end fetch_and_compare;

  /**
   * Calculates the percentage difference between two values
   *
   * @since 1.0.0
   * @param float $before
   * @param float $after
   * @return float
   */
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

  /**
   * Tries to get the balance values from the platform
   *
   * @since 1.0.0
   * @return void
   */
  public function get_account_balance() {

    try {

      return $this->platform->get_account_balance();

    } catch(Exception $e) {

      $this->console_with_time(sprintf('Erro: %s', $e->getMessage()), 'red');

    } // end try_catch;

  } // end get_current_value;

  /**
   * Saves the current session to a file names last.session inside the sessions folder
   * 
   * This allow us to retrieve a session, if we use the "alex last" command
   *
   * @since 1.0.0
   * @return void
   */
  public function save_session() {

    $session_file = 'sessions/last.session';

    $handle = fopen($session_file, 'w');

    $data = self::$instance;

    fwrite($handle, serialize($data));

    fclose($handle);

  } // end freeze_session;

  /**
   * Handles Alex shutdown on ctrl+c: saves the session to a file and say bye!
   *
   * @since 1.0.0
   * @return void
   */
  public function shutdown() {

    echo PHP_EOL;

    Console::log('Alex: Salvando sessão atual... Você consegue recomeçar de onde parou usando alex.php last, viu?', 'light_green');

    $this->save_session();

    Console::log('Alex: Encerrando atividades...', 'light_green');

    Console::log('Alex: Tchau!', 'light_green');

    exit;
    
  } // end shutdown;

} // end class Alex;

/**
 * Wake Alex up! Those cryptocurrencies won't buy/sell themselves!
 * 
 * Starts Alex up, instantiating a new class or trying to load up an old session
 *
 * @since 1.0.0
 * @return void
 */
function start_alex() {

  global $argv;

  /**
   * Parse passed arguments via the CLI
   */
  parse_str(implode('&', array_slice($argv, 1)), $args);

  /**
   * Checks for the 'help' parameter. If present display the available parameters and their description
   */
  if (isset($args['help'])) {

    return print_help();

  } // end if;

  /**
   * Checks for the 'last' parameter, if it is present, tries to retrieve the last session from file
   */
  if (isset($args['last'])) {

    Console::log('Alex: Tentando carregar sessão anterior...', 'light_green');

    if (!file_exists('./sessions/last.session')) {

      Console::log('Falha ao carregar sessão anterior.', 'red');

      exit;

    } // end if;

    $session = file_get_contents('./sessions/last.session');

    try {

      $alex = unserialize($session);

    } catch (Exception $e) {

      Console::log('Falha ao carregar sessão anterior.', 'red');

    } // end if;

  } else {

    /**
     * Otherwise, we create a new instance using the parameters passed
     */
    $alex = Alex::create_instance(
      @$args['coin'], 
      @$args['platform'], 
      @$args['live'], 
      @$args['limit'], 
      @$args['frequency'],
      @$args['buy_at'],
      @$args['sell_at'],
      @$args['status']
    );

  } // end if;

  /**
   * We run the bot, finally!
   */
  $alex->run();

} // end start_alex;

/**
 * Prints the help on the screen, explaining each of the parameters
 *
 * @since 1.0.0
 * @return void
 */
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
    'platform'  => 'Muda a plataforma a ser utilizada para compras',
    'buy_at'    => 'Seta quando deve comprar',
    'sell_at'   => 'Seta quando deve vender',
    'status'    => 'Diz pro Alex em que estado ele precisa começar',
    'last'      => 'Se presente, retoma a última session. Ignora todos os demais parâmetros',
  );

  foreach($params as $name => $desc) {

    Console::log(sprintf('%s -> %s', $name, $desc), 'light_green');

  } // end foreach;

  exit;

} // end print_help;

// Run!
start_alex();