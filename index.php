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
function getAccessToken($creds)
{

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

function getRandomUser()
{
 echo "Generating data...";
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
// EMBEDDED SERVICE ACCOUNT
// =====================

$SERVICE_ACCOUNT = [
 "type" => "service_account",
 "project_id" => "hyella-task-1",
 "private_key_id" => "62ec9c3e8008af4cc431b1877ea930fb75831613",
 "private_key" => "-----BEGIN PRIVATE KEY-----\nMIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC6ybkjEMB4EV9u\nAthTqUaYifQyPDEPau63Wc0VbX/WPYzmHFQIdU/fal1uEhdz5WaIBOX9cDoD8Jxh\nKMvmk50INBtJ2mKyTO4lZ++p1MRl9hYKXBaY/3DECLEp+5k9wAQTczsgb0bn5QoM\njVm2hqheE9Lbw1HqIs4NET74FNAfHHp4on/GStAquRV68bNRxLh8opV3tsv8o8DY\nk5lUBBIvjpx+sGxRe7PGuol694h5W4Nz5IuEI1jiQsRCCDKAFTBqavkbRtR4eB+C\nASwezX7rZfuJ2zET1WFkDUKIS58cH2kmZPqY8QL4wE30HSBlzD06OiT1GWABKhQO\nvfW+UbiFAgMBAAECggEAKL7IO/Xzhj2D66ODPPy4AZ5WPn8S1KIm4KgeLIZuVHWo\nvql/SzkL61hweQpJQ2bPLuyint5USXe7JRaZI/sfTiLPsRSKYwqVCGPby9g132gG\n9suPwmA2YQzkWJwhmW7BdNy2ESU0+nDj+Ej9QOuu3pbEcFvzCnf9KqyQZ91iaOaW\nAWEohv36+GKSFUWGIhOTevNWw2PZZ5M9eNB/62B0SQbcsuZhVCb+Rb7MnwlW67KJ\nsvc/YXXxu8uqcv4lJ9cOXzyn4WN3HOzy3Dtq9Qd6j/2z3D3hiQA5inmGlaRtBYm2\nS8rF21EeziBq30NUoXoQiS6GdDKTZseutbyluherAQKBgQDgcZrguRt5eMaY+OoO\ntrtpOosT1GNgAxEuAyjy+URTee6lY6jaXiD5kcwDOAE2LQCjxPfxwD+rFerpKgj+\nHY43o6OGXAgueUlLwP0/HFSzfgABMvcqmmhY5L85Lkve1lMyBdlgG6NCHu7xMUvQ\n1e0XOR9Yr9lRcBpPkoeY7nqcjQKBgQDVDMZnwG0MFmoPx1+KrusgS9q8HAtrxTNc\nUs174YwJCIOkINkURNyeL9Oxa68EIhFt+2gLyPOF8RBCQz/0PevbS898hgZ7lfWr\n6ExtpXRcaas+yAhPICU3Sz9nxdFJw5mTtzt+TVS8Iwk28mk8kOXYcbisazHhs/YG\nt1E7mU5Z2QKBgQCBW3i0RHu9SwrLZ8sep9rkD0XRK/wKfjoMlu2m/FuQ8RnGYOYU\n1WOT85/tyv2Hx/Ayc3ej8fXAGWXG9N8x9r7c+odpDOn6PxUrgBN1qFJ5EQnXpxQl\njdDOSyibQD+iM0zH6+8ZIVS66zEz+gGEX4fCdr3GU7Og6EeBzSYx0mEAkQKBgQCo\nVNiqfyJpy4fvgaKei8ghE2329N2dQAltp8rNV47yUDTayE1cM3Bw1+8WHrQAuv40\nfNBfh13J9YbYJBRy4T3qXgLJK4gRu5GJSxigBXtjzOXNy/SsuARPpPerAWR0OMSu\nLrcl+um5YKFWNcAqZ34DPw8fw+58m1kHQ24+fcmnOQKBgQCXwDCh7p0fUbO3tA07\ndyEUUeXlg5aGaV3O9lm2Cfaqf1gVknyTKAq/TjUshKX+KLw2x5pV7dHzxVs8+x/6\nFvOt7QLMsQ/8EgtKLqmomIko12mSIOl224po+TScseYNnNIzem422MORM6xmaIMD\nrHX3oPocYLG2hUp31nUGOcDE+A==\n-----END PRIVATE KEY-----\n",
 "client_email" => "hyella-task-1@hyella-task-1.iam.gserviceaccount.com",
 "client_id" => "107054972723950582654",
 "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
 "token_uri" => "https://oauth2.googleapis.com/token",
 "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
 "client_x509_cert_url" => "https://www.googleapis.com/robot/v1/metadata/x509/hyella-task-1%40hyella-task-1.iam.gserviceaccount.com"
];

// =====================
// MAIN
// =====================

try {

 $token = getAccessToken($SERVICE_ACCOUNT);

 for ($i = 0; $i <= 6; $i++) {
  $user = getRandomUser();
  insertUser($user, $token);
 }

 echo "Done\n";

} catch (Exception $e) {
 echo "Error: " . $e->getMessage();
}