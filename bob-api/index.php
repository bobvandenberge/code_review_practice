<?php

// Include the api
include 'api/de_onderwijzers_api.php';

// Enable debug mode for the API
api\DeOnderwijzers::$DEBUG_ENABLED = true;

// Create a new instance of the api
$api = new api\DeOnderwijzers();

// Try the validate credentials
$result = $api->validateCredentials('a', 'password');

// Dump the result
var_dump($result);