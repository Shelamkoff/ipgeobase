<?php

namespace IpGeoBase;

use \Exception;
use \PDO;
use \ZipArchive;

class IpGeoBase {
	
	private $pdo;
	
	private $statement1;
	
	private $statement2;
	
	private $uploadDir;
	
	
	public function setUploadDir(string $path) : IpGeoBase {
		$this->uploadDir = $path;
		return $this;
	}
		
	public function __construct($pdo, $user = null, $password = null){
		if(!$pdo instanceof PDO){
			$pdo = new PDO($pdo, $user, $password);
		}
		$this->pdo = $pdo;
		$this->uploadDir = sys_get_temp_dir();
	}
	
	public function find($ip) : array {
		$long = ip2long($ip);
		if($long === false){
			throw new Exception('Invalid ip-adress.');
		}
		
		if($this->statement1 === null){
			$this->statement1 = $this->pdo->prepare('SELECT * FROM geobase WHERE `long_ip1`<= :long AND `long_ip2`>= :long LIMIT 1');
		}
		$this->statement1->execute([':long' => $long]);
		$result = $this->statement1->fetch(PDO::FETCH_ASSOC);
		
		
		if($result['city_id'] !== null){
			if($this->statement2 === null){
				$this->statement2 = $this->pdo->prepare('SELECT * FROM geobase_cities WHERE city_id = :id LIMIT 1');
			}
			$this->statement2->execute([':id' => $result['city_id']]);
			$result = array_merge($result, $this->statement2->fetch(PDO::FETCH_ASSOC));
		}
		
		if(empty($result)){
			throw new Exception('Ip-aress not found in the database.');
		}
		
		unset($result['id']);
		
		return $result;
		
	}
	
	public function getPdo() : PDO{
		return $this->pdo;
	}
	
	public function create(){
		$sth = $this->pdo->prepare('DROP TABLE IF EXISTS `geobase` ');
		$sth->execute();
		
		$sth = $this->pdo->prepare('DROP TABLE IF EXISTS `geobase_cities`');
		$sth->execute();
		
		$sth = $this->pdo->prepare('
			CREATE TABLE `geobase_cities` 
			(
				`id` INT(10) NOT NULL AUTO_INCREMENT,
				`city_id` INT(11) NULL DEFAULT NULL,
				`city` VARCHAR(255) NULL DEFAULT NULL,
				`region` VARCHAR(255) NULL DEFAULT NULL,
				`district` VARCHAR(255) NULL DEFAULT NULL,
				`latitude` FLOAT NULL DEFAULT NULL,
				`longitude` FLOAT NULL DEFAULT NULL,
				INDEX `INDEX` (`city_id`),
				PRIMARY KEY (`id`)
			)'
		);
		
		$sth->execute();
		
		$sth = $this->pdo->prepare(
			'CREATE TABLE `geobase` 
			(	
				`id` INT(10) NOT NULL AUTO_INCREMENT,
				`long_ip1` BIGINT(20) NOT NULL,
				`long_ip2` BIGINT(20) NOT NULL,
				`ip1` VARCHAR(50) NOT NULL,
				`ip2` VARCHAR(50) NOT NULL,
				`country` VARCHAR(2) NOT NULL,
				`city_id` INT(11) NULL DEFAULT NULL,
				INDEX `INDEX` (`long_ip1`, `long_ip2`),
				PRIMARY KEY (`id`)
			)'
		);
		$sth->execute();
		$this->update();
	}
	
	public function update(){
		$sth = $this->pdo->prepare('TRUNCATE  TABLE `geobase_cities`');
		$sth->execute();
		$filename = $this->uploadDir.DIRECTORY_SEPARATOR.'cities.txt';
		if(!file_exists($filename)){
			$this->getBase();
		}
		$file = file($filename);
		unset($sth);
    	foreach($file as $row){
        	$row = iconv('windows-1251', 'utf-8', $row);
			$row = trim(str_replace('	', '&', $row));
			if($sth === null){
				$sth = $this->pdo->prepare("INSERT INTO geobase_cities (city_id, city, region, district, latitude, longitude) VALUES (:id, :city, :reg, :dist, :lat, :lon)");
			}
			$ex = explode('&', $row);
			$bindings = [];
			$bindings[':id'] = $ex[0] === '-' ? null : $ex[0];
			$bindings[':city'] = $ex[1];
			$bindings[':reg'] = $ex[2];
			$bindings[':dist'] = $ex[3];
			$bindings[':lat'] = $ex[4];
			$bindings[':lon'] = $ex[5];
			$sth->execute($bindings);  
    	}
		unlink($filename);
		$sth = $this->pdo->prepare('TRUNCATE  TABLE `geobase`');
		$sth->execute();	
		unset($sth);
		$filename = $this->uploadDir.DIRECTORY_SEPARATOR.'cidr_optim.txt';
		if(!file_exists($filename)){
			$this->getBase();
		}
		$file = file($filename);
   		foreach($file as $row){
       		$row = iconv('windows-1251', 'utf-8', $row);
			$row = trim(str_replace(['	', ' - '],' ', $row));
			if($sth === null){
            	$sth = $this->pdo->prepare("INSERT INTO geobase (long_ip1, long_ip2, ip1, ip2, country, city_id) VALUES (:lon1, :lon2, :ip1, :ip2, :country, :city_id )");
			}
			$ex = explode(' ', $row);
			$bindings = [];
			$bindings[':lon1'] = $ex[0]; 
			$bindings[':lon2'] = $ex[1]; 
			$bindings[':ip1'] = $ex[2];
			$bindings[':ip2'] = $ex[3];
			$bindings[':country'] = $ex[4];
			$bindings[':city_id'] = $ex[5] === '-' ? null : $ex[5];
		
			$sth->execute($bindings);             
		}
		unlink($filename);
}

	private function getBase(){
		$file = file_get_contents('http://ipgeobase.ru/files/db/Main/geo_files.zip');
		$filename = $this->uploadDir.DIRECTORY_SEPARATOR.'geo_files.zip';
		file_put_contents($filename, $file);
		if(!$file){
			throw new Exception('Failed download Zip-file from http://ipgeobase.ru/files/db/Main/geo_files.zip.');
		}
		if(mime_content_type($filename) !== 'application/zip'){
			unlink($filename);
			throw new Exception('Invalid file extension. File from http://ipgeobase.ru/files/db/Main/geo_files.zip must be zip-archive. ');
		}
		$zip = new ZipArchive();
		$result = $zip->open($filename);
 		if($result !== true) {
			switch($result){
				case ZipArchive::ER_EXISTS : $message = 'File already exists.';
				break;
				case ZipArchive::ER_INCONS : $message = 'Incompatible ZIP-file.';
				break;
				case ZipArchive::ER_INVAL : $message = 'Invalid argument.';
				break;
				case ZipArchive::ER_MEMORY : $message = 'Error dynamic memory allocation.';
				break;
				case ZipArchive::ER_NOENT : $message = 'File dosn\t exists.';
				break;
				case ZipArchive::ER_NOZIP : $message = 'It is not ZIP-archives.';
				break;
				case ZipArchive::ER_OPEN : $message = 'Unable to open file.';
				break;
				case ZipArchive::ER_READ : $message = 'Файл не может быть read.';
				break;
				case ZipArchive::ER_SEEK : $message = 'Search error.';
				break;
			}
    		throw new Exception($message);
  		}
		$zip->extractTo($this->uploadDir.DIRECTORY_SEPARATOR); 
   		$zip->close();
		unlink($filename);
	}
	
}
