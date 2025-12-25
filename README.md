# PHP VTU.ng VTU

A PHP package for integrating with the [VTU.ng API](https://vtu.ng/api/).

[![Run Tests](https://github.com/henryejemuta/php-vtung-vtu/actions/workflows/run-tests.yml/badge.svg)](https://github.com/henryejemuta/php-vtung-vtu/actions/workflows/run-tests.yml)
[![Release](https://github.com/henryejemuta/php-vtung-vtu/actions/workflows/publish.yml/badge.svg)](https://github.com/henryejemuta/php-vtung-vtu/actions/workflows/publish.yml)

## Documentation

For full API documentation, please visit [https://vtu.ng/api/](https://vtu.ng/api/).

## Installation

You can install the package via composer:

```bash
composer require henryejemuta/php-vtung-vtu
```

## Usage

### Authentication

You can authenticate using your VTU.ng username and password to retrieve a token, or pass an existing token directly.

```php
use HenryEjemuta\Vtung\Client;

// Option 1: Authenticate with username and password
$client = new Client();
$response = $client->authenticate('your_username', 'your_password');
$token = $response['token'];

// Option 2: Instantiate with existing token
$client = new Client('your_jwt_token');
```

Note: The token expires after 7 days. It is recommended to store and reuse the token until it expires to avoid unnecessary authentication requests.

### Check Balance

```php
$balance = $client->getBalance();
print_r($balance);
```

### Purchase Airtime

```php
// $requestId should be a unique identifier for the transaction
$requestId = uniqid();
$result = $client->purchaseAirtime('mtn', '08012345678', 100, $requestId);
print_r($result);
```

### Purchase Data

```php
// Get Data Variations
$variations = $client->getDataVariations('mtn');
print_r($variations);

// Purchase Data
$requestId = uniqid();
$result = $client->purchaseData('mtn', '08012345678', 'variation_id', $requestId);
print_r($result);
```

### Verify Customer (Electricity, Cable TV, Betting)

```php
// Electricity
$customer = $client->verifyCustomer('12345678901', 'ikeja-electric', 'prepaid');

// Cable TV
$customer = $client->verifyCustomer('1234567890', 'dstv');
```

### Purchase Electricity

```php
$requestId = uniqid();
$result = $client->purchaseElectricity($requestId, '12345678901', 'ikeja-electric', 'prepaid', 1000);
```

### Purchase Cable TV

```php
$requestId = uniqid();
$result = $client->purchaseCableTV($requestId, '1234567890', 'dstv', 'variation_id');
```

## Testing

PHPUnit is used for testing.

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
