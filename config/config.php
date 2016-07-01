<?php

// Database backend to use
Config::Set("Backend", "\\storage\\MySQL");

// Backend to use for storing attachments
Config::Set("StorageBackend", "\\storage\\FileSystem\\DataStore");

// Connection to MySQL database.
Config::Set("MySQLMaster", array(
	"host" => "localhost", // Database host
	"user" => "", // Database user
	"password" => "", // Database password
	"database" => "" // Database name
));

// Set debug to false in production environment!
Config::Set("debug", true);

