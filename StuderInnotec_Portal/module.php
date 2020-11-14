<?php
#ToDo: Logmessages funktionieren noch nicht
#ToDo: unterscheidliche Timer notwendig 
declare(strict_types=1);

class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";

    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
        $archiv = IPS_GetInstanceIDByName("Archive", 0 );
        
        //Config Profile
        $this->RegisterProfileFloat("Studer-Innotec.MWh", "Factory", "", " MWh", 0, 0, 0, 3);
        $this->RegisterProfileFloat("Studer-Innotec.kWh", "Electricity", "", " kWh", 0, 0, 0, 2);
        
        // Config Variablen 
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
    public function Destroy() {
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

    public function Update() {
        if ($this->ReadPropertyBoolean("std_3080")){
            #ToDo: set Archive Modus für $ID_XT_IN_total_yesterday
            if (!$ID_XT_IN_total_yesterday = @$this->GetIDForIdent('ID_XT_IN_total_yesterday')) {
                $ID_XT_IN_total_yesterday = $this->RegisterVariableFloat('ID_XT_IN_total_yesterday', $this->Translate('XT_IN_total_yesterday'),'Studer-Innotec.kWh');
                IPS_SetIcon($ID_XT_IN_total_yesterday, 'Graph');
                //AC_SetLoggingStatus($archiv, $ID_XT_IN_total_yesterday, true);
            }
            SetValueFloat ($ID_XT_IN_total_yesterday, (float) $this->Studer_Read("3080","Value","XT_Group")->FloatValue);
        
        }
        if ($this->ReadPropertyBoolean("std_15023")){
            #ToDo: set Archive Modus für $ID_VS_Total_produced_energy
            if (!$ID_VS_Total_produced_energy = @$this->GetIDForIdent('ID_VS_Total_produced_energy')) {
                $ID_VS_Total_produced_energy = $this->RegisterVariableFloat('ID_VS_Total_produced_energy', $this->Translate('XT_VS_Total_produced_energy'),'Studer-Innotec.MWh');
                IPS_SetIcon($ID_VS_Total_produced_energy, 'Graph');
                //AC_SetLoggingStatus($archiv, $ID_VS_Total_produced_energy, true);
            }
            SetValueFloat ($ID_VS_Total_produced_energy, (float) $this->Studer_Read("15023","Value","XT_Group")->FloatValue);    
        }   
   }

private function Studer_Read($infoId,$paramart,$device){
    global $email;
    global $pwd;
    global $installationNumber;
	global $url;
	
	$curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $this->ReadPropertyString("url") . "/ReadUserInfo?email=". $this->ReadPropertyString("Username") ."&pwd=" . $this->ReadPropertyString("Password") ."&installationNumber=". $this->ReadPropertyString("installationNumber")  ."&infoId=". $infoId . "&paramPart=". $paramart ."&device=". $device,
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
    return $xml;
    }
}
private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
 #Fuction origanl from https://github.com/Joey-1970/
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 2);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 2)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	        IPS_SetVariableProfileDigits($Name, $Digits);
	}
}