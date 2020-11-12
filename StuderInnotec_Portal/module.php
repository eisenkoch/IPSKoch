<?php
declare(strict_types=1);

class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";
     //Create Profile

    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
        $archiv = IPS_GetInstanceIDByName("Archiv", 0 );
        // --------------------------------------------------------
        // Config Variablen
        // --------------------------------------------------------
        $this->RegisterPropertyBoolean("Debug", false);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString("installationNumber", "");
        $this->RegisterPropertyString("url", "https://portal.studer-innotec.com/scomwebservice.asmx");
        $this->RegisterPropertyBoolean("VS_Total_produced_energy", false);
        $this->RegisterPropertyBoolean("XT_IN_total_yesterday", false);
        $this->RegisterPropertyBoolean("XT_Out_total_today", false);
        $this->RegisterPropertyBoolean("ST_3080", false);
        $this->RegisterPropertyInteger("UpdateInterval", 10);
        
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
        $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateInterval")*6000);
        if ($this->ReadPropertyBoolean("Debug")){
                $this->LogMessage("ApplyChanges", KL_DEBUG);
        }
    

    }
    public function Update(){
    if ($this->ReadPropertyBoolean("ST_3080")){
        $this->std_3080();
    }
       
   }

function std_3080(){

    if (!$ID_XT_IN_total_yesterday = @$this->GetIDForIdent('ID_XT_IN_total_yesterday')) {
        $ID_XT_IN_total_yesterday = $this->RegisterVariableFloat('ID_XT_IN_total_yesterday', $this->Translate('XT_IN_total_yesterday'),'~Electricity');
        IPS_SetIcon($ID_XT_IN_total_yesterday, 'Graph');
        AC_SetLoggingStatus($archiv, $ID_XT_IN_total_yesterday, true);
    }
    
	$infoId = "3080";
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $this->ReadPropertyString("url") . "/ReadUserInfo?email=". $this->ReadPropertyString("Username") ."&pwd=" . $this->ReadPropertyString("Password") ."&installationNumber=". $this->ReadPropertyString("installationNumber") ."&infoId=". $infoId . "&paramPart=Value&device=XT_Group",
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
    SetValueFloat  ($ID_XT_IN_total_yesterday, (float) $xml->FloatValue);
    }
}
}