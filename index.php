<?php

// =====================
// CONFIG
// =====================

define("SHEET_ID", "1owwQvwgFjjkbvei1t70jsgvZebMvpYJvr7SMiRiGBzI");
define("SHEET_RANGE", "Sheet1");

// =====================
// HELPER FUNCTIONS
// =====================

function base64url_encode($data)
{
 return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Create OAuth access token using JWT
function getAccessToken()
{
 $creds = json_decode(file_get_contents(__DIR__ . "/service-account.json"), true);
 if (!$creds) {
  throw new Exception("Failed to locate service-account.json file\n");
 }

 $header = base64url_encode(json_encode([
  "alg" => "RS256",
  "typ" => "JWT"
 ]));

 $now = time();
 $payload = base64url_encode(json_encode([
  "iss" => $creds["client_email"],
  "scope" => "https://www.googleapis.com/auth/spreadsheets",
  "aud" => "https://oauth2.googleapis.com/token",
  "exp" => $now + 3600,
  "iat" => $now
 ]));

 $signatureInput = "$header.$payload";

 openssl_sign(
  $signatureInput,
  $signature,
  $creds["private_key"],
  "SHA256"
 );

 $jwt = "$signatureInput." . base64url_encode($signature);

 $ch = curl_init("https://oauth2.googleapis.com/token");

 curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query([
   "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
   "assertion" => $jwt
  ])
 ]);

 $response = curl_exec($ch);
 curl_close($ch);

 $data = json_decode($response, true);

 if (!isset($data["access_token"])) {
  throw new Exception(
   "access_token missing. Response was: " . $response
  );
 }

 return $data["access_token"];
}

// Simple curl JSON request
function httpJson($url, $method = "GET", $headers = [], $body = null)
{
 $ch = curl_init($url);

 curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => $method,
  CURLOPT_HTTPHEADER => $headers,
  CURLOPT_POSTFIELDS => $body
 ]);

 $response = curl_exec($ch);
 curl_close($ch);

 return json_decode($response, true);
}

// =====================
// RANDOM USER
// =====================

function getRandomUser($count)
{
 echo "Generating data ($count)...";
 $data = httpJson("https://randomuser.me/api/?nat=us&exc=login");

 if (!$data || !isset($data["results"][0])) {
  throw new Exception("Failed to fetch random user");
 }

 return $data["results"][0];
}

// =====================
// INSERT INTO SHEET
// =====================

function insertUser($user, $token)
{
 echo "Appending data...";
 $url = "https://sheets.googleapis.com/v4/spreadsheets/"
  . SHEET_ID
  . "/values/"
  . SHEET_RANGE
  . ":append?valueInputOption=USER_ENTERED";

 $row = [
  "=ROW()-1",
  '=IMAGE("' . $user["picture"]["medium"] . '")',
  $user["name"]["first"] . " " . $user["name"]["last"],
  $user["email"],
  $user["dob"]["age"],
  $user["gender"],
  rand(0, 100),
  $user["registered"]["date"],
  rand(0, 1) === 1
 ];

 $body = json_encode([
  "values" => [$row]
 ]);

 $response = httpJson($url, "POST", [
  "Authorization: Bearer $token",
  "Content-Type: application/json"
 ], $body);

 if (!isset($response["updates"])) {
  print_r($response);
  throw new Exception("Insert failed");
 }

 echo "Inserted: " . $response["updates"]["updatedRange"] . PHP_EOL;
}

// =====================
// MAIN
// =====================

try {

 $token = getAccessToken();

 for ($i = 0; $i <= 6; $i++) {
  $user = getRandomUser($i);
  insertUser($user, $token);
 }

 echo "Done\n";

} catch (Exception $e) {
 echo "Error: " . $e->getMessage();
}
