<?php
declare(strict_types=1);
const ARCHIVE_CONTROL_MODULE_ID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
require_once __DIR__ . '/../libs/common.php';  					// globale Funktionen
require_once __DIR__ . '/../libs/Phpmodbus/ModbusMaster.php';  	// Modbus Features

class StuderInnotecRS485 extends IPSModule {
    var $moduleName = "StuderInnotecRS485";
    
public function Create() {
    // Diese Zeile nicht löschen.
	parent::Create();
	//Config Profile
	$this->RegisterProfileFloat("Studer-Innotec.MWh", 	"Factory", "", " MWh", 0, 0, 0, 3);
	$this->RegisterProfileFloat("Studer-Innotec.kWh", 	"Electricity", "", " kWh", 0, 0, 0, 2);
	$this->RegisterProfileFloat("Studer-Innotec.kW", 	"Electricity", "", " kW", 0, 0, 0, 2);
	$this->RegisterProfileFloat("Studer-Innotec.Hz",	"Freqency", "", " Hz", 0, 0, 0, 2);
	$this->RegisterProfileFloat("Studer-Innotec.V",	    "Energy", "", " V", 0, 0, 0, 2);
	$this->RegisterProfileFloat("Studer-Innotec.Ah",	"Capacity", "", " Ah", 0, 0, 0, 0);
	$this->RegisterProfileFloat("Studer-Innotec.percent",	    "Percent", "", " %", 0, 0, 0, 1);
        
	// Config Variablen 
	$this->RegisterPropertyInteger('ArchiveControlID', IPS_GetInstanceListByModuleID(ARCHIVE_CONTROL_MODULE_ID)[0]);
	$this->RegisterPropertyString("Variables", "");
	$this->RegisterPropertyString('IP_Modbus_Gateway', '192.168.1.100');
	$this->RegisterPropertyString('IP_Modbus_Port', '520');
	$this->RegisterPropertyString("activeDevices", "");
	$this->RegisterPropertyBoolean("Debug", false);
	$this->RegisterTimer("UpdateTimer_2", 0, 'StuderRS485_Update_2($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_5", 0, 'StuderRS485_Update_5($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_60", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_360", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_720", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
}
 
public function ApplyChanges() {
    // Diese Zeile nicht löschen
    parent::ApplyChanges();
	//clear Timer
	$this->SetTimerInterval("UpdateTimer_5",0);
	$this->SetTimerInterval("UpdateTimer_60",0);
	$treeData = json_decode($this->ReadPropertyString("Variables"));
	if(!empty($treeData)){
		$active = array();
		foreach ($treeData as $value) {
			if(($value->Active)==true){
				$active[]=array('ID'=>($value->ID), 'Intervall'=>($value->Intervall));
			}
		}
		$intervall_active = array_unique((array_column($active, 'Intervall')));
		foreach ($intervall_active as $value) {
			$this->SetTimerInterval(("UpdateTimer_".$value), $value*60000);
			//$this->SetTimerInterval(("UpdateTimer_".$value), $value*1000);
		}
	}
}

 public function GetConfigurationForm(){
    $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
	$var_element = json_decode(file_get_contents(__DIR__ . "/../libs/_param.json"),true);
	$data['elements'][2]['values'] = $var_element ;
	return json_encode($data);
}
public function Update_2() {
	$timer_var = '2';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_5() {
	$timer_var = '5';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_60() {
	$timer_var = '60';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_360() {
	$timer_var = '360';
	$this->call_Studer_from_Timer($timer_var);
}

public function Update_720() {
	$timer_var = '720';
	$this->call_Studer_from_Timer($timer_var);
}

private function call_Studer_from_Timer($timer){
$treeData = json_decode($this->ReadPropertyString("Variables"));
foreach ($treeData as $value) {
		if((($value->Active)==true)and (($value->Intervall)== $timer)){
			$var_ID = 'ID_' . $value->ID ;
			$configpage = json_decode(IPS_GetConfigurationForm($this->InstanceID));	
			foreach ($configpage->elements[2]->values as $item) {
				if ($item->ID == $value->ID) {
					$unit =($item->Unit);
					$format=($item->Format);
					$varname= ($item->VarName);
					$mbP = ($item->mbP);
					$mb_result = explode(":", $mbP);
					$mb_device = $mb_result[0];
					$mb_adress = $mb_result[1];
				}
			}
			if (!@$this->GetIDForIdent($var_ID )) {
				IPS_LogMessage($this->moduleName,"==>create Var: ". $var_ID );
                if(!$varname){
                    $var_name = $var_ID;
                }else {$var_name = $this->Translate($varname); }
                switch ($format) {
                    case "FLOAT":
                        $this->RegisterVariableFloat($var_ID , $var_name, 'Studer-Innotec.'. $unit);
                        $this->EnableAction($var_ID );
						if (($value->Archive)==true){
							AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), true);
							IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
						}
                        break;
                    case "SHORT_ENUM":
					case "LONG_ENUM":
                        $this->RegisterVariableString($var_ID , $var_name);
                        $this->EnableAction($var_ID );
						break;
                    default :
                        IPS_LogMessage($this->moduleName,"could not find var-Format for: " . $format);
                }
			}
            switch ($format) {
                case "FLOAT":
					$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
					SetValueFloat ($this->GetIDForIdent($var_ID ),(float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1));
					break;
				case "SHORT_ENUM":
				case "LONG_ENUM":
					//create Array from 'Unit' Paramter in form
					$chunks = array_chunk(preg_split('/(:|,)/', $unit), 2);
					$result = array_combine(array_column($chunks, 0), array_column($chunks, 1));
					$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
					$StuderState = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1);
					SetValueString($this->GetIDForIdent($var_ID),$result[$StuderState]);
					break;
				default :
					IPS_LogMessage($this->moduleName,"coul not find Handler for: ". $value->Format);
            }
		}
	}


}

public function validateDevices () {
	$treeDataDevices = json_decode($this->ReadPropertyString("activeDevices"));
	if (!$treeDataDevices){exit;} //omits the error if the function is called before any saving
	foreach ($treeDataDevices as $value) {
		if(($value->Active)==true){
			if (($value->DeviceTypeID)=="XT") {
				$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
				echo(float) PhpType::bytes2float($modbus->readMultipleInputRegisters(10, 249, 2),1);
			}elseif(($value->DeviceTypeID)=="VS"){
				break;
			}elseif(($value->DeviceTypeID)=="VT"){
				break;
			}elseif(($value->DeviceTypeID)=="BSP"){
				break;
			}
		}
	}
}

public function reCheckVar() {
//function to check if a Variable what exists is active. if not active delete the Variable
	$treeData = json_decode($this->ReadPropertyString("Variables"));
	foreach ($treeData as $value) {
		$var_ID = 'ID_' . $value->ID ;
		if (@$this->GetIDForIdent($var_ID )) {
			if (!$value->Active==true){
				IPS_DeleteVariable($this->GetIDForIdent($var_ID ));
				echo "deleted " . $var_ID . "\n";
			}
			if ($value->Active==true){
				//
				if (($value->Archive)==true){
					AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), true);
					IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
				}
				else {
					AC_SetLoggingStatus($this->ReadPropertyInteger('ArchiveControlID'), $this->GetIDForIdent($var_ID), false);
					IPS_ApplyChanges($this->ReadPropertyInteger('ArchiveControlID'));
					
				}
			}
		}
	}
}

public function CheckSofwareVersion() {
	$treeDataDevices = json_decode($this->ReadPropertyString("activeDevices"));
	if (!$treeDataDevices){exit;} //omits the error if the function is called before any saving
	foreach ($treeDataDevices as $value) {
		if(($value->Active)==true){
			$chunks = array_chunk(preg_split('/(:|,)/', ($value->mb_Software_msb_lsb)), 2);
			$result = array_combine(array_column($chunks, 0), array_column($chunks, 1));
			
			$infoId_msb = $result["msb"];
			$infoId_lsb = $result["lsb"];
			$DeviceCat = $value->mb_DeviceTypeID;
			if (($value->DeviceTypeID)=="XT") {
				$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
				$xt_type = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters(10, 248, 2),1);
				switch ($xt_type) {
					case "1":
						$DeviceType = "XTH";
						break;
					case "256":
						$DeviceType = "XTM";
						break;
					case "512":
						$DeviceType = "XTS";
						break;
					default :
						exit;
				}
				
			}
			else {
				switch ($value->DeviceTypeID) {
				case "VS" :
					$DeviceType = "VARIOSTRING";
					break;
				case "VT" :
					$DeviceType = "VARIOTRACK";
				case "BSP" :
					//ToDo: BSP does not work as there could be several BSP Types (CAN, BSP, etc ...)
					exit;
				}
			}
			
			$msb = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($DeviceCat, $infoId_msb, 2),1);
			$lsb = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($DeviceCat, $infoId_lsb, 2),1);
	
			$studer_version = json_decode((file_get_contents(__DIR__ . "/../libs/studer-version.json")),true);

			if (($studer_version['versions'][$DeviceType])==(($msb >>8) . "." . ($lsb >>8) . "." . ($lsb & 0xFF))){
				echo "found a active ". $DeviceType ." and no update needed \n\n";
			}
			else {
				echo "the installed Version of your ". $DeviceType .": \n". (($msb >>8) . "." . ($lsb >>8) . "." . ($lsb & 0xFF)) . "\ndiffers from the actual known version: \n" . ($studer_version['versions'][$DeviceType]) ."\n" ;
			}
		}
	}
}

private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits){
	#Function orignal from https://github.com/Joey-1970/
	if (!IPS_VariableProfileExists($Name)) {
		IPS_CreateVariableProfile($Name, 2);
	}
	else {
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