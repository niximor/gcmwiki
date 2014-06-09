<?php

// Database backend to use
Config::Set("Backend", "MySQL");

// Connection to MySQL database.
Config::Set("MySQLMaster", array(
	"host" => "localhost", // Database host
	"user" => "", // Database user
	"password" => "", // Database password
	"database" => "" // Database name
));

Config::Set("debug", true);

