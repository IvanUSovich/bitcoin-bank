<?php
//header("Access-Control-Allow-Origin: https://{$_SERVER['SERVER_NAME']}");
//header('Access-Control-Allow-Methods: POST, OPTIONS');
//header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$json = file_get_contents('php://input');
session_start();
$cid = 3165; // FIXME: required variable
$staffToken = '0B21DbkbRrMtPkegMuoCTsrhylTPep'; // FIXME: required variable, get from staff

/**
 * Get json from page with form with next fields:
 * - csrf - generated on the page with the form, the secret key is the hostname,
 * and the salt - time() subtract 80000. The salt set in session on page with form
 * and get in file which send form data to crm;
 * - name;
 * - last name;
 * - email;
 * - phone;
 * - phoneCountry - Alpha-2 ISO 3166-1 format;
 * - phoneCountryCode;
 * - geoCountry - Alpha-2 ISO 3166-1 format;
 * - ip.
 * All these fields required.
 */

/**
 * response
 * {
 * "connection": "success", // "failed" if no data, wrong csrf, response from crm Failed
 * "is_lead": true, // false if no valid data, response from crm false (no active domain, black ip,
 * // err write to table lead_accepted, repeat lead, black geo, wrong domain)
 * "autologin_link": "https://domain.com", // false if response from crm autologin_url false
 * "description": "",
 * "code": "",
 * }
 */

try {
    $time = $_SESSION['time_csrf'];
    $secret = $_SERVER['SERVER_NAME']; // example domain.com
    $csrf = md5($time . $secret);

    $timestamp = time(); // special for 

    $token = generateToken($timestamp, $staffToken);

    $data = json_decode($json, true);
    $data = sanitizeData($data);

    if (!$data) {
        getResult('failed', false, false, 'Empty fields: name, last name, email, phone', '0_402');
    }
    if (!isset($data['csrf']) || $data['csrf'] !== $csrf) {
        getResult('failed', false, false, "{$time} & {$secret}", '0_403');
    }
    $errors = validatingUserData($data);
    if ($errors) {
        // TODO: write to file with errors
        getResult('failed', false, false, implode(',', $errors), '0_405');
    }

    unset($data['csrf']);
    $data['client_code'] = $timestamp . ':' . $token;
    $data['date_first_contact'] = date('Y-m-d H:i:s');
    $data['cid'] = $cid;

    $url = 'https://dash.gamma.icu/signup/procform';
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($curl, CURLOPT_REFERER, $_SERVER['SERVER_NAME']);
    curl_setopt($curl, CURLOPT_POST, 1);
    $res = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($res, true);

    if ($response['connection'] === 'failed') {
        getResult('failed', false, false, $response['description'], $response['code']);
    } elseif ($response['connection'] === 'success') {
        getResult('success', $response['is_lead'], $response['autologin_url'], $response['description'], $response['code']);
    } else {
        getResult('failed', false, false, 'Something happened wrong! ' . $res, '0_400');
    }
    curl_close($curl);
    //getResult('success', true, false, 'download.php', $csrf);

} catch (Exception $e) {
    getResult('failed', false, false, 'Something happened wrong!', '0_401');
}

/**
 * @param string $status
 * @param bool $isLead
 * @param bool|string $link
 * @param string|array $description
 * @param string $code
 * @return void
 */
function getResult(
    string $status,
    bool $isLead,
    $link,
    $description,
    string $code
) {
    echo json_encode([
        'connection' => $status,
        'is_lead' => $isLead,
        'autologin_link' => $link,
        'description' => $description,
        'code' => $code
    ]);
    session_destroy(); // TODO: must destroy is isLead=true
    exit();
}

/**
 * @return string
 */

/**
 * @param string $timestamp
 * @param string $token
 * @return string
 */
function generateToken(string $timestamp, string $token): string
{
    $date = substr($timestamp, -5);
    return substr(md5($date . $token), 0, 16);
}

/**
 * @param array $dataPost
 * @return array
 */
function validatingUserData(array $dataPost): array
{
    $errors = [];
    if ($dataPost['name'] == '' || strlen($dataPost['name']) < 2) {
        $errors[] = "Field 'name' must be not empty and more than one characters";
    }
    if ($dataPost['last_name'] == '' || strlen($dataPost['last_name']) < 2) {
        $errors[] = "Field 'last_name' must be not empty and more than one characters";
    }
    if (!preg_match('/^(?:[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*|"(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21\\x23-\\x5b\\x5d-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x21-\\x5a\\x53-\\x7f]|\\\\[\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f])+)])$/i', strtolower($dataPost['email']))) {
        $errors[] = "Field 'email' must be a valid email";
    }
    if (!preg_match('/^[0-9]{5,15}+$/', trim($dataPost['phone'], '+'))) {
        $errors[] = "Field 'phone' must be a valid phone number";
    }
    if ($dataPost['geoCountry'] == '' || strlen($dataPost['geoCountry']) > 2) {
        $errors[] = "Field 'geoCountry' must be not empty. Alpha-2 ISO 3166-1 format. Like us, gb, etc..";
    }
    if ($dataPost['phoneCountry'] == '' || strlen($dataPost['phoneCountry']) > 2) {
        $errors[] = "Field 'phoneCountry' must be not empty. Alpha-2 ISO 3166-1 format. Like us, gb, etc..";
    }
    if ($dataPost['phoneCountryCode'] == '' || strlen($dataPost['phoneCountryCode']) > 4) {
        $errors[] = "Field 'phoneCountryCode' must be not empty and not more than 4 character.";
    }
    if ($dataPost['ip'] == '') {
        $errors[] = "Field 'ip' must be not empty";
    }
    return $errors;
}

/**
 * @param ?array $arr
 * @return array
 */
function sanitizeData(?array $arr): array
{
    if (is_null($arr))
        return [];
    $data = [];
    foreach ($arr as $key => $value) {
        $data[htmlspecialchars(trim($key))] = htmlspecialchars(trim($value));
    }
    return $data;
}