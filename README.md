goodhill-client-php
===================

PHP Client for Goodhill API

```php
//Example usage:
$api_key = '1234';
$secret = '5678';
$host = 'https://api.goodhill-solutions.com'

$GoodhillApi = new Goodhill\Client($api_key, $secret, array(
	$host
));

$settings = array(
  'query' => 'ACME',
	'page' => 1,
	'limit' => 8
);

$result = $GoodhillApi->search($settings);
```
