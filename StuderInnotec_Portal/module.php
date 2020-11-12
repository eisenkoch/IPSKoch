<?php
class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";
    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
        // --------------------------------------------------------
        // Config Variablen
        // --------------------------------------------------------
        $this->RegisterPropertyBoolean("Debug", false);
        $this->RegisterPropertyString("Username", "");
        $this->RegisterPropertyString("Password", "");
        $this->RegisterPropertyString("installationNumber", "");
        $this->RegisterPropertyString("PortlURL", "https://portal.studer-innotec.com/scomwebservice.asmx");
        $this->RegisterPropertyBoolean("VS_Total_produced_energy", false);
        $this->RegisterPropertyBoolean("XT_IN_total_yesterday", false);
        $this->RegisterPropertyBoolean("XT_Out_total_today", false);
        $this->RegisterPropertyBoolean("XT_Out_total_yesterday", false);
        $this->RegisterPropertyInteger("UpdateIntervall", 10);
        
        $this->RegisterTimer("UpdateTimer", 0, 'Studer_Update($_IPS[\'TARGET\']);');
    }
    public function Destroy()
    {
        $this->UnregisterTimer("UpdateTimer");
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        //$this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateIntervall")*1000); 
        $this->Username = $this->ReadPropertyString("Username");
        $this->Password = $this->ReadPropertyString("Password");
        if ($this->ReadPropertyBoolean("Debug")){
                $this->LogMessage("ApplyChanges", KL_DEBUG);
        }
    

    }
    public function Update(){
    if ($this->ReadPropertyBoolean("XT_Out_total_yesterday")){
        std_3080();
    }
       
   }

  private function std_3080(){
    IPS_LogMessage($_IPS['SELF'], $username);
    global $username;
    global $password;
    global $installationNumber;
	global $url;
	
	$infoId = "3080";
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $url . "/ReadUserInfo?email=". $username ."&pwd=" . $password ."&installationNumber=". $installationNumber ."&infoId=". $infoId . "&paramPart=Value&device=XT_Group",
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
    //var_dump ($xml->FloatValue);
    SetValueFloat  (11920, (float) $xml->FloatValue);
    }
}
}