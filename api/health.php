<?php
//used localhost for now because Karabo is still working on db, cannot call db yet

date_default_timezone_set("Africa/Johannesburg");

//informs receiving device that they're expecting json in http body
header("Content-Type: application/json");
//http_response_code(200);
//for now just want to know what the actual response code is
$responseCode = http_response_code();

//test data for creating the api endpoint
$data = [
    "success" => true,
    "message" => "API message from server",
    "timestamp" => date("c"),
    "response"=> $responseCode
];

echo json_encode($data); //validate output on server page if needed
exit;