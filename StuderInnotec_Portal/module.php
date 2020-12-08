<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class StuderInnotecWeb extends IPSModule {
    var $moduleName = "StuderInnotecWeb";

    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();
		#ToDo: Archiv funktioniert noch nicht
        $archiv = IPS_GetInstanceIDByName("Archive", 0 );
        
        //Config Profile
        $this->RegisterProfileFloat("Studer-Innotec.MWh", 	"Factory", "", " MWh", 0, 0, 0, 3);
        $this->RegisterProfileFloat("Studer-Innotec.kWh", 	"Electricity", "", " kWh", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.kW", 	"Electricity", "", " kW", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.Hz",	"Freqency", "", " Hz", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.V",	    "Energy", "", " V", 0, 0, 0, 2);
        $this->RegisterProfileFloat("Studer-Innotec.percent",	    "Percent", "", " %", 0, 0, 0, 1);
        
        // Config Variablen 
        $this->RegisterPropertyString("Variables", "");
		$this->RegisterPropertyBoolean("Debug", false);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString("installationNumber", "");
        $this->RegisterPropertyString("url", "https://portal.studer-innotec.com/scomwebservice.asmx");
        $this->RegisterTimer("UpdateTimer_5", 0, 'Studer_Update_5($_IPS[\'TARGET\']);');
		$this->RegisterTimer("UpdateTimer_60", 0, 'Studer_Update_60($_IPS[\'TARGET\']);');
    }
    public function Destroy() {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
		//clear Timer
		$this->SetTimerInterval("UpdateTimer_5",0);
		$this->SetTimerInterval("UpdateTimer_60",0);
        if(empty($this->ReadPropertyString("Username"))){
            //Warung fehlender Username
			if ($this->ReadPropertyBoolean("Debug")){IPS_LogMessage($this->moduleName,"Warung fehlender Username");}
            $this->SetStatus(201);
		}else {$this->SetStatus(102);}
		if(empty($this->ReadPropertyString("Password"))){
            //Warung fehlendes Passwort
			if ($this->ReadPropertyBoolean("Debug")){IPS_LogMessage($this->moduleName,"Warung fehlendes Passwort");}
            $this->SetStatus(202);
		}else {$this->SetStatus(102);}
		if(empty($this->ReadPropertyString("installationNumber"))){
            //Warung fehlende installationNumber
            if ($this->ReadPropertyBoolean("Debug")){IPS_LogMessage($this->moduleName,"Warung fehlende installationNumber");}
			$this->SetStatus(203);
		}else {$this->SetStatus(102);}
        
		$treeData = json_decode($this->ReadPropertyString("Variables"));
		if(!empty($treeData)){
			$active = array();
			foreach ($treeData as $value) {
				if(($value->Active)==true){
					$active[]=array('ID'=>($value->ID), 'Intervall'=>($value->Intervall));
					//IPS_LogMessage($this->moduleName,($value->ID));
				}
			}
			$intervall_active = array_unique((array_column($active, 'Intervall')));
			foreach ($intervall_active as $value) {
				$this->SetTimerInterval(("UpdateTimer_".$value), $value*60000);
				//$this->SetTimerInterval(("UpdateTimer_".$value), $value*1000);
				//IPS_LogMessage($this->moduleName,($value));
			}
		}
    }
    
public function GetConfigurationForm(){
    $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
}

public function Update_5() {
	//IPS_LogMessage($this->moduleName,"Update_5");
	$timer_var = '5';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_60() {
	//IPS_LogMessage($this->moduleName,"Update_60");
	$timer_var = '60';
	$this->call_Studer_from_Timer($timer_var);
}

private function call_Studer_from_Timer($timer){
$treeData = json_decode($this->ReadPropertyString("Variables"));
	foreach ($treeData as $value) {
		if((($value->Active)==true)and (($value->Intervall)== $timer)){
			$var_ID = 'ID_' . $value->ID ;
			
			if (!@$this->GetIDForIdent($var_ID )) {
				IPS_LogMessage($this->moduleName,"==>create Var: ". $var_ID );
                if(!$value->VarName){
                    $var_name = $var_ID;
                }else {$var_name = $this->Translate($value->VarName); }
                #Todo Check VarType (Float, etc....)
                switch ($value->Format) {
                    case "FLOAT":
                        $this->RegisterVariableFloat($var_ID , $var_name, 'Studer-Innotec.'. $value->Unit);
                        $this->EnableAction($var_ID );
                        #ToDo: fix Icon
                        //IPS_SetIcon($ID_XT_IN_total_yesterday, 'Graph');
                        break;
                    case "SHORT_ENUM":
                        $this->RegisterVariableString($var_ID , $var_name);
                        $this->EnableAction($var_ID );
						break;
                    default :
                        IPS_LogMessage($this->moduleName,"could not find var-Format for: " . $value->Format);
                }

				#ToDo: fix Logging
				//AC_SetLoggingStatus($archiv, $ID_XT_IN_total_yesterday, true);
			}
            switch ($value->Format) {
                case "FLOAT":
                SetValueFloat ($this->GetIDForIdent($var_ID ), (float) $this->Studer_Read($value->ID,"Value",$value->Type)->FloatValue);
                break;
            case "SHORT_ENUM":
                //create Array from 'Unit' Paramter in form
                $chunks = array_chunk(preg_split('/(:|,)/', $value->Unit), 2);
                $result = array_combine(array_column($chunks, 0), array_column($chunks, 1));

                $StuderState = $this->Studer_Read($value->ID,"Value", $value->Type);
                $objAsString = (string)$StuderState->FloatValue; 
                SetValueString($this->GetIDForIdent($var_ID),$result[$objAsString]);
				break;
            default :
                IPS_LogMessage($this->moduleName,"coul not find Handler for: ". $value->Format);
            }
		}
	}
}

private function Studer_Read($infoId,$paramart,$device) {
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

private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits){
    #Fuction orignal from https://github.com/Joey-1970/
	
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

public function validateAccount() {
	global $email;
	global $pwd;
	global $installationNumber;
	global $url;
	
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $this->ReadPropertyString("url") . "/GetInstallationList?email=". $this->ReadPropertyString("Username") ."&pwd=" . $this->ReadPropertyString("Password"),
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
	$xml = new SimpleXMLElement($response);
	
	if ($err) {
    	echo "cURL Error #:" . $err;
    } else {
		if (($xml->ErrorCode)!=1){
			print("something is wrong Studer-Innotec says: " . $xml->ErrorMessage);
		}
		else {
			echo "Studer-Innotec has the following ID's for you: \n" ;	
			foreach ($xml->InstallationList->InstallationInfo as $installation) {
				echo $installation->Id," => ", $installation->Name, PHP_EOL;
			}
		}
	}
}
}