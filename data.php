<?php 
function getIPAddress()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
$ip = getIPAddress();
$geo = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip='.$ip));
$country_code = $geo ["geoplugin_countryCode"];
$city = $geo ["geoplugin_city"];
$country_code = $geo ["geoplugin_countryCode"];

?>