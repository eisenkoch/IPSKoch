<?php
declare(strict_types=1);
const ARCHIVE_CONTROL_MODULE_ID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
require_once __DIR__ . '/../libs/common.php';  					// globale Funktionen
require_once __DIR__ . '/../libs/Phpmodbus/ModbusMaster.php';  	// Modbus Features

class StuderAWT extends IPSModule {
    var $moduleName = "StuderAWT";
	use StuderCommonLib;

public function Create() {
	// Diese Zeile nicht löschen.
	parent::Create();
	//Config Profile
	
	// Config Variablen 
	$this->RegisterPropertyInteger('ArchiveControlID', IPS_GetInstanceListByModuleID(ARCHIVE_CONTROL_MODULE_ID)[0]);
	$this->RegisterPropertyInteger('SOC_low', 5);
	$this->RegisterPropertyInteger('SOC_high', 95);
	$this->RegisterPropertyInteger('SOC_actual', 0);
	$this->RegisterPropertyString('Username', '');
	$this->RegisterPropertyString('Password', '');
	$this->RegisterPropertyString('installationNumber', '');
	$this->RegisterPropertyString('IP_Modbus_Gateway', '192.168.1.100');
	$this->RegisterPropertyString('IP_Modbus_Port', '520');
	$this->RegisterPropertyInteger('Config_type'	, '0');
	$this->RegisterPropertyInteger('Connection_type', '0');
	$this->RegisterPropertyInteger('Voltage', '230');
	$this->RegisterPropertyInteger('Bat_Voltage', 0);
	$this->RegisterPropertyBoolean('System_active', "0");
	$this->RegisterPropertyInteger('Batt_Cap', 0);
	$this->RegisterPropertyInteger('eID_start', 0);
	$this->RegisterPropertyInteger('eID_stop', 0);
	$this->RegisterPropertyFloat('max_Charge_Current', 0.5);
	$this->RegisterPropertyString('url', "https://portal.studer-innotec.com/scomwebservice.asmx");
	$this->RegisterTimer("UpdateTimer_2", 0, 'StuderAWT_Update_2($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateAWT", 0, 'StuderAWT_Update_AWT($_IPS[\'TARGET\']);');
	$this->RegisterTimer("ChargeAWT", 0, 'StuderAWT_Charge_AWT($_IPS[\'TARGET\']);');
}

public function Destroy() {
    //Never delete this line!
    parent::Destroy();
}

public function ApplyChanges() {
	// Diese Zeile nicht löschen
	parent::ApplyChanges();
	if (($this->ReadPropertyBoolean("System_active")) == true) {
		$this->SetTimerInterval(("UpdateTimer_2"), 2*60000);
		//$this->SetTimerInterval(("UpdateTimer_2"), 2*10000);
		$this->SetTimerInterval(("UpdateAWT"), 2*10000);
		if($this->ReadPropertyInteger("eID_start") <> 0){
			IPS_SetEventActive($this->ReadPropertyInteger("eID_start"), true);
		}
		if($this->ReadPropertyInteger("eID_stop") <> 0){
			IPS_SetEventActive($this->ReadPropertyInteger("eID_stop"), true);
		}
	}
	elseif (($this->ReadPropertyBoolean("System_active")) == false) {
		$this->SetTimerInterval(("UpdateTimer_2"), 0);
		$this->SetTimerInterval(("UpdateAWT"), 0);			//disable Timer for priceUpdate
		$this->SetTimerInterval(("ChargeAWT"), 0);			//disable Timer for Charger
		if($this->ReadPropertyInteger("eID_start") <> 0){
			IPS_SetEventActive($this->ReadPropertyInteger("eID_start"), false);
		}
		if($this->ReadPropertyInteger("eID_stop") <> 0){
			IPS_SetEventActive($this->ReadPropertyInteger("eID_stop"), false);
		}
	}
}

public function GetConfigurationForm(){
	$data = json_decode(file_get_contents(__DIR__ . '/form.json'));
	switch($this->ReadPropertyInteger("Connection_type")){
		case "0": //Connection => Portal
			$data->elements[2]->items[1]->visible = true; 	// Connection type -> enable Box for UserData
			$data->elements[8]->enabled = false;			// disable actual SOC
			$data->elements[10]->items[0]->enabled = false; // disable Bat Cap
			break;
		case "1": //Connection => RS22
			$data->elements[2]->items[1]->visible = false;
			break;
		case "2": //Connection => RS485(Modbus)
			//$data->elements[2]->items[1]->visible = false;
			$data->elements[2]->items[2]->visible = true;
			break;
	}
	return json_encode($data);
}

public function awt_init(){
	if($this->ReadPropertyInteger("eID_start") == 0){
		$eid_Start = IPS_CreateEvent(1);
		IPS_SetParent($eid_Start, $this->InstanceID);
		IPS_SetName($eid_Start,"AWT_Charge_Start");
		IPS_SetEventScript($eid_Start, 'StuderAWT_Start_AWT($_IPS[\'TARGET\']);');
		IPS_SetEventActive($eid_Start, true);
		IPS_SetProperty($this->InstanceID,"eID_start", $eid_Start);
		IPS_ApplyChanges($this->InstanceID);	
	}
	if($this->ReadPropertyInteger("eID_stop") == 0){
		$eid_Stop = IPS_CreateEvent(1);
		IPS_SetParent($eid_Stop, $this->InstanceID);
		IPS_SetName($eid_Stop,"AWT_Charge_Stop");
		IPS_SetEventScript($eid_Stop, 'StuderAWT_Stop_AWT($_IPS[\'TARGET\']);');							   
		IPS_SetEventActive($eid_Stop, true);
		IPS_SetProperty($this->InstanceID,"eID_stop", $eid_Stop);
		IPS_ApplyChanges($this->InstanceID);	
	}
	switch ($this->ReadPropertyInteger("Config_type")) {
		case "0": 		//XTM/XTH/XTS System mit Standard Batterie
			echo "Config_type  not implemented";
			break;
		case "1":		//XTM/XTH/XTS System mit BSP und Standard Batterie
			echo "Config_type  not implemented";
			break;
		case "2":		//XTM/XTH/XTS System mit CAN Adapter und Lithium Akku 
			//anlegen der notwendigen Variablen
			switch($this->ReadPropertyInteger("Connection_type")){
				case "0":	//Studer WebPortal
					//get Battery Capacity
					$awt_std_cap = (float) $this->Studer_Read_Portal("7055","Value","BSP_Group",'ReadUserInfo')->FloatValue;
					if ($awt_std_cap <> $this->ReadPropertyInteger("Batt_Cap") ){
						IPS_SetProperty($this->InstanceID,"Batt_Cap", $awt_std_cap);
						IPS_ApplyChanges($this->InstanceID);	
					}
					//get max Input Limit for Charger
					$awt_std_iLimit = (float) $this->Studer_Read_Portal("1107","Value","XT_Group","ReadParameter")->FloatValue;
					$max_loadW = ($awt_std_iLimit * $this->ReadPropertyInteger("Voltage"))/1000 ;
					IPS_SetProperty($this->InstanceID,"max_Charge_Current", $max_loadW);
					IPS_ApplyChanges($this->InstanceID);
					//get System Bat_Voltage
					$awt_std_batVoltage =  $this->Studer_Read_Portal("6057","Value","BSP_Group","ReadParameter")->UIntValue;
					switch ($awt_std_batVoltage){
						case 1: //automatic System
							echo "please select manual the correct Batteryvoltage";
							break;
						case 2: //12V System
							break;
						case 4: //24V System
							break;
						case 8: //48V System
							break;
					}
					break;
				case "1" : //Connection => RS232
					break;
				case "2" : //Connection => RS485(Modbus)
					$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
					//get Battery Capacity
					$awt_std_cap = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters(60, 110, 2),1);
					if ($awt_std_cap <> $this->ReadPropertyInteger("Batt_Cap") ){
						IPS_SetProperty($this->InstanceID,"Batt_Cap", $awt_std_cap);
						IPS_ApplyChanges($this->InstanceID);	
					}
					//get max Input Limit for Charger
					$awt_std_iLimit = (float) PhpType::bytes2float($modbus->readMultipleRegisters(10, 14, 2),1);
					$max_loadW = ($awt_std_iLimit * $this->ReadPropertyInteger("Voltage"))/1000 ;
					IPS_SetProperty($this->InstanceID,"max_Charge_Current", $max_loadW);
					IPS_ApplyChanges($this->InstanceID);
					//get System Bat_Voltage
					$awt_std_batVoltage = (float) PhpType::bytes2float($modbus->readMultipleRegisters(60, 114, 2),1);
					switch ($awt_std_batVoltage){
						case 1: //automatic System
							echo "please select manual the correct Batteryvoltage";
							break;
						case 2: //12V System
							break;
						case 4: //24V System
							break;
						case 8: //48V System
							break;
					};
					break; 
				default:
					echo $this->ReadPropertyInteger("Connection_type") . "Connection_Type not implemented";
					exit;
			}
			
		default:
			exit;
	}
}

public function Charge_AWT() {
	switch($this->ReadPropertyInteger("Connection_type")){
		case 0:	//Studer WebPortal
			$ID_Loadfill        		= '6062';   //in einem Lithium System ist das der notwendige wert zum anheben
			$param_Studer_Soc_Lithium 	= '7002';	//State of Charge
			$SOC_minLoadStart   = "90"; 
			$actual_Soc = (float) $this->Studer_Read_Portal($param_Studer_Soc_Lithium,"Value","BSP_Group","ReadUserInfo")->FloatValue;
			if ($actual_Soc < $SOC_minLoadStart){
				
				$this->Studer_Maintain_Portal('BSP_Group',number_format($this->ReadPropertyInteger('SOC_high'),1) ,$ID_Loadfill);
			}
			elseif ($actual_Soc > $this->ReadPropertyInteger('SOC_high')){
				$this->Studer_Maintain_Portal('BSP_Group',number_format($this->ReadPropertyInteger('SOC_low'),1) ,$ID_Loadfill);
			}
			break;
		case "1" : //Connection => RS232
			break;
		case "2" : //Connection => RS485(Modbus)
			$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
			$SOC_minLoadStart   = "90"; 
			$actual_Soc = (float) PhpType::bytes2float($modbus->readMultipleRegisters(60, 4, 2),1);
			if ($actual_Soc < $SOC_minLoadStart){
				//$modbus->writeMultipleRegister(60, 6124, array((number_format($this->ReadPropertyInteger('SOC_high'),1))), array("REAL"), 1);
				//$this->Studer_Maintain_Portal('BSP_Group',number_format($this->ReadPropertyInteger('SOC_high'),1) ,$ID_Loadfill);
			}
			elseif ($actual_Soc > $this->ReadPropertyInteger('SOC_high')){
				//$this->Studer_Maintain_Portal('BSP_Group',number_format($this->ReadPropertyInteger('SOC_low'),1) ,$ID_Loadfill);
			}
			break;
	}
}

public function Start_AWT(){
	$this->SetTimerInterval(("ChargeAWT"), 2*10000);
}

public function Stop_AWT(){
	$this->SetTimerInterval(("ChargeAWT"), 0);
}

public function Update_2() {
	switch ($this->ReadPropertyInteger("Config_type")) {
	case "0": 		//XTM/XTH/XTS System mit Standard Batterie
		echo "0";
		break;
	case "1":		//XTM/XTH/XTS System mit BSP und Standard Batterie
		echo "1";
		break;
	case "2":		//XTM/XTH/XTS System mit CAN Adapter und Lithium Akku (awt_xtcl)
		$this->awt_xtcl();
		break;
	default:
		exit;
	}
}

public function Update_AWT(){
$Lowest_Price = '999999999';
$starttime = '24';
$bat_voltage = 48;

//ToDo: use max Charge Value
$aa  = $this->ReadPropertyFloat("max_Charge_Current");

$min_hour = round(($this->ReadPropertyInteger("Batt_Cap") * $bat_voltage)/100 * (100 - $this->ReadPropertyInteger("SOC_actual")) / '1200');

$min_hour = strval ($min_hour);
	if ($json_data = $this->GetDataAWT()) {
		foreach ($json_data AS $item) {
			$price = (floatval($item['marketprice'] / 10));
			
			if ($price < $Lowest_Price) {
				$Lowest_Price = $price;
				$starttime = $item['start_timestamp'];
			}   
		}
	}
	$endtime = ($starttime + 1000*60*60*$min_hour);
	//ToDo: wenn stopzeit = Endzeit ...Fehler behandeln
	//set START Timer
	IPS_SetEventCyclicDateFrom ($this->ReadPropertyInteger("eID_start"),date('j',$starttime/1000),date('n',$starttime/1000),date('Y',$starttime/1000));
	IPS_SetEventCyclicTimeFrom ($this->ReadPropertyInteger("eID_start"),date('G',$starttime/1000),0,0);
	IPS_SetEventCyclic($this->ReadPropertyInteger("eID_start")  , 1 , 0,0,0,0,0);
	
	//set END Timer
	IPS_SetEventCyclicDateFrom ($this->ReadPropertyInteger("eID_stop"),date('j',$endtime/1000),date('n',$endtime/1000),date('Y',$endtime/1000));
	IPS_SetEventCyclicTimeFrom ($this->ReadPropertyInteger("eID_stop"),date('G',$endtime/1000),0,0);
	IPS_SetEventCyclic($this->ReadPropertyInteger("eID_stop")  , 1 , 0,0,0,0,0);
}

public function awt_xtcl(){
	switch($this->ReadPropertyInteger("Connection_type")){
		case 0:	//Studer WebPortal
			$param_Studer_Soc_Lithium = '7002';	//State of Charge
			$awt_std_soc = (float) $this->Studer_Read_Portal($param_Studer_Soc_Lithium,"Value","BSP_Group","ReadUserInfo")->FloatValue;
			if ($awt_std_soc <> $this->ReadPropertyInteger("SOC_actual") ){
				IPS_SetProperty($this->InstanceID,"SOC_actual", $awt_std_soc);
				IPS_ApplyChanges($this->InstanceID);	
			}
			break;
		default:
			echo $this->ReadPropertyInteger("Connection_type") ." Connection_Type not implemented";
			exit;
	}	
}

private function Studer_Read_Portal($Id,$paramart,$device,$type) {
    global $email;
    global $pwd;
    global $installationNumber;
	global $url;
		
	if ($type=='ReadUserInfo'){
		$id_type = 'infoId';
	}
	elseif ($type=='ReadParameter'){
		$id_type = 'paramId';
	}
	
	$curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => $this->ReadPropertyString("url") . "/". $type ."?email=". $this->ReadPropertyString("Username") ."&pwd=" . $this->ReadPropertyString("Password") ."&installationNumber=". $this->ReadPropertyString("installationNumber")  ."&". $id_type ."=". $Id . "&paramPart=". $paramart ."&device=". $device,
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
    return $xml;
    }
}

private function Studer_Maintain_Portal($device,$paramValue, $paramID){
    global $email;
    global $pwd;
    global $installationNumber;
	$paramPart = "Value";
	$curl = curl_init();
	global $std_url;
    

    curl_setopt_array($curl, array(
 	CURLOPT_URL => $this->ReadPropertyString("url") . "/WriteParameter?email=". $this->ReadPropertyString("Username") ."&pwd=" . $this->ReadPropertyString("Password") ."&installationNumber=". $this->ReadPropertyString("installationNumber") ."&device=". $device ."&paramPart=". $paramPart . "&paramid=". $paramID ."&paramValue=". $paramValue ."&userLevelCode=909661",
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
        echo $response;
    }
}

function GetDataAWT() {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.awattar.de/v1/marketdata',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array("cache-control: no-cache"),
    ));

    $response = json_decode(curl_exec($curl), true); 
    curl_close($curl);
    return isset($response['data']) ? $response['data'] : [];; 
}


}