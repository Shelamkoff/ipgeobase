# IpGeoBase

use IpGeoBase\IpGeoBase;

Использование : $geobase = new IpGeoBase($pdo) или $geobase = new IpGeoBase($dsn, $user, $password);

$geobase->create() // Создаст таблицы geobase и geobase_cities в бд и заполнит их.

$geobase->update() // Обновит базу.

$data = $geobase->find('217.107.124.206');

var_dump($data);

Для Российских и Украинских ip:

array(11) {
  ["long_ip1"]=>
  string(10) "3647684608"
  ["long_ip2"]=>
  string(10) "3647717375"
  ["ip1"]=>
  string(12) "217.107.64.0"
  ["ip2"]=>
  string(15) "217.107.191.255"
  ["country"]=>
  string(2) "RU"
  ["city_id"]=>
  string(4) "2097"
  ["city"]=>
  string(12) "Москва"
  ["region"]=>
  string(12) "Москва"
  ["district"]=>
  string(56) "Центральный федеральный округ"
  ["latitude"]=>
  string(7) "55.7558"
  ["longitude"]=>
  string(7) "37.6176"
}

Для всех остальных :
<pre>
array(6) {
  ["long_ip1"]=>
  string(8) "84557824"
  ["long_ip2"]=>
  string(8) "84557824"
  ["ip1"]=>
  string(9) "5.10.64.0"
  ["ip2"]=>
  string(9) "5.10.64.0"
  ["country"]=>
  string(2) "NL"
  ["city_id"]=>
  NULL
}
</pre>

