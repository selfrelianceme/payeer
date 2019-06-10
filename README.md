# Payeer payment system


Require this package with composer:
```
composer require selfreliance/payeer
```
## Publish Config

```
php artisan vendor:publish --provider="Selfreliance\Payeer\PayeerServiceProvider"
```

## Use name module

```
use Selfreliance\Payeer\Payeer;
```
or
```
$payeer = resolve('payment.payeer');
```

## Configuration

Add to **.env** file:

```
#Payeer_Settings
PY_SHOP_ID=
PY_PAYEER_WALLET=
PY_SHOP_SECRET_KEY=
PY_API_ID=
PY_KEY=
PERSONAL_LINK_CAB=/personal
```

**Urls for shop setup**
```
php artisan payeer:url
```