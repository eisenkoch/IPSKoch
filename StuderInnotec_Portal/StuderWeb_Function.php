<?php
if ($this->ReadPropertyBoolean("VS_Total_produced_energy")){
    std_3020();
}

function std_3020(){
    global $email;
    global $pwd;
    global $installationNumber;
	global $url;
	
	$infoId = "3020";
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $url . "/ReadUserInfo?email=". $email ."&pwd=" . $pwd ."&installationNumber=". $installationNumber ."&infoId=". $infoId . "&paramPart=Value&device=XT_Group",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POSTFIELDS => "",
    CURLOPT_HTTPHEADER => array("cache-control: no-cache"),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
    echo "cURL Error #:" . $err;
    } else {
    $xml = new SimpleXMLElement($response);
	if (DEBUG):
    	var_dump ($xml->FloatValue);
	endif;
    	SetValueBoolean (38731, (float) $xml->FloatValue);
    }
}