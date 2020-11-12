<?php
declare(strict_types=1);

class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";
     //Create Profile

    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
        $archiv = IPS_GetInstanceIDByName("Archive", 0 );
        // --------------------------------------------------------
        // Config Variablen
        // --------------------------------------------------------
        $this->RegisterPropertyBoolean("Debug", false);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString("installationNumber", "");
        $this->RegisterPropertyString("url", "https://portal.studer-innotec.com/scomwebservice.asmx");
        $this->RegisterPropertyBoolean("std_15023", false);
        $this->RegisterPropertyBoolean("XT_IN_total_yesterday", false);
        $this->RegisterPropertyBoolean("XT_Out_total_today", false);
        $this->RegisterPropertyBoolean("std_3080", false);
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
        if(empty($this->ReadPropertyString("Username"))){
            #ToDo: Benötige Funktion Warung bei fehlenden Daten (User, PW, Inst-ID)
            $this->LogMessage("missing User", KL_DEBUG);
        }
        if ($this->ReadPropertyBoolean("Debug")){
                $this->LogMessage("ApplyChanges", KL_DEBUG);
        }
    

    }
    public function Update(){
    if ($this->ReadPropertyBoolean("std_3080")){
        $this->std_3080();
    }
    if ($this->ReadPropertyBoolean("std_15023")){
        $this->std_15023();
    }   
   }

function std_3080(){ //XT_IN_total_yesterday
#ToDo: set Archive Modus für $ID_XT_IN_total_yesterday
    if (!$ID_XT_IN_total_yesterday = @$this->GetIDForIdent('ID_XT_IN_total_yesterday')) {
        $ID_XT_IN_total_yesterday = $this->RegisterVariableFloat('ID_XT_IN_total_yesterday', $this->Translate('XT_IN_total_yesterday'),'~Electricity');
        IPS_SetIcon($ID_XT_IN_total_yesterday, 'Graph');
        //AC_SetLoggingStatus($archiv, $ID_XT_IN_total_yesterday, true);
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
function std_15023(){ //VS_Total_produced_energy
    #ToDo: Variablenprofil für Mwh
    #ToDo: set Archive Modus für $ID_VS_Total_produced_energy
    if (!$ID_VS_Total_produced_energy = @$this->GetIDForIdent('ID_VS_Total_produced_energy')) {
        $ID_VS_Total_produced_energy = $this->RegisterVariableFloat('ID_VS_Total_produced_energy', $this->Translate('XT_VS_Total_produced_energy'),'~Electricity');
        IPS_SetIcon($ID_VS_Total_produced_energy, 'Graph');
        //AC_SetLoggingStatus($archiv, $ID_VS_Total_produced_energy, true);
    }
	$infoId = "15023";
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
    	var_dump ($xml->FloatValue);
    SetValueFloat  ($ID_VS_Total_produced_energy, (float) $xml->FloatValue);
    }
}
}