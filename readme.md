# Alex - Waverider Trader Bot


## Start scripts
Start up scripts for PM2 for each coin.

```
pm2 start alex.php --name alex-btc -- limit=100 frequency=60 live=1 coin=BTC buy_at=0.5,-3 sell_at=2,-5 platform=mercadobitcoin
pm2 start alex.php --name alex-ltc -- limit=100 frequency=60 live=1 coin=LTC buy_at=0.5,-3 sell_at=2,-5 platform=mercadobitcoin
pm2 start alex.php --name alex-bch -- limit=100 frequency=60 live=1 coin=BCH buy_at=0.5,-5 sell_at=4,-10 platform=mercadobitcoin
```

## Restarting Frozen Sessions
pm2 start alex.php --name alex-bch -- last=bch