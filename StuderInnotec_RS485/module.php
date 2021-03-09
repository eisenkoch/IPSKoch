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
	        
	//Config Variablen 
	$this->RegisterPropertyInteger('ArchiveControlID', IPS_GetInstanceListByModuleID(ARCHIVE_CONTROL_MODULE_ID)[0]);
	$this->RegisterPropertyInteger('summaryValues', 0);
	$this->RegisterPropertyString('Variables', '');
	$this->RegisterPropertyString('IP_Modbus_Gateway', '192.168.1.100');
	$this->RegisterPropertyString('IP_Modbus_Port', '520');
	$this->RegisterPropertyString('activeDevices', '');
	$this->RegisterPropertyString('url', 'https://service.st-ips.de/studer-version.json');
	$this->RegisterPropertyBoolean('Debug', false);
	
	//register Attribute
	$this->RegisterAttributeInteger("count_XT", 0);
	$this->RegisterAttributeInteger("count_VS", 0);
	$this->RegisterAttributeInteger("count_VT", 0);
	$this->RegisterAttributeInteger("count_BSP", 0);
	
	//register Timer
	$this->RegisterTimer("UpdateTimer_1", 0, 'StuderRS485_Update_1($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_2", 0, 'StuderRS485_Update_2($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_5", 0, 'StuderRS485_Update_5($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_60", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_360", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
	$this->RegisterTimer("UpdateTimer_720", 0, 'StuderRS485_Update_60($_IPS[\'TARGET\']);');
}
 
public function ApplyChanges() {
    // Diese Zeile nicht löschen
    parent::ApplyChanges();
	echo $this->ReadPropertyString("Variables");
	//clear Timer
	$this->SetTimerInterval("UpdateTimer_1",0);
	$this->SetTimerInterval("UpdateTimer_2",0);
	$this->SetTimerInterval("UpdateTimer_5",0);
	$this->SetTimerInterval("UpdateTimer_60",0);
	$this->SetTimerInterval("UpdateTimer_360",0);
	$this->SetTimerInterval("UpdateTimer_720",0);
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
			//$this->SetTimerInterval(("UpdateTimer_".$value), $value*2000);
		}
	$this->call_Studer_from_Timer('720');
	$this->call_Studer_from_Timer('60');
	}
}

 public function GetConfigurationForm(){
    $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
	$var_element = json_decode(file_get_contents(__DIR__ . "/../libs/_param.json"),true);
	$data['elements'][2]['values'] = $var_element ;
	return json_encode($data);
}

public function Update_1() {
	$timer_var = '1';
	$this->call_Studer_from_Timer($timer_var);
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
					$type = (explode('_', ($item->Type), 2))[0];
					$summary = ($item->summary);
					$devInfo = ($item->devInfo);
					$mbP = ($item->mbP);
					$mb_result = explode(":", $mbP);
					$mb_device = $mb_result[0];
					$mb_adress = $mb_result[1];
				}
			}
			if (!@$this->GetIDForIdent($var_ID )) {
				//add a way to validate summary
				IPS_LogMessage($this->moduleName,"summary= ". $this->ReadPropertyInteger('summaryValues'));
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
                        $this->RegisterVariableString($var_ID, $var_name);
                        $this->EnableAction($var_ID );
						break;
                    default :
                        IPS_LogMessage($this->moduleName,"could not find var-Format for: " . $format);
                }
			}
            switch ($format) {
                case "FLOAT":
					$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
					$count = $this->ReadAttributeInteger("count_". $type);
					/* 	
						"summary":"0" => create summary not allowed
						"summary":"1" => create summary allowed
						"summary":"2" => create summary never allowed 
					*/
					//IPS_LogMessage($this->moduleName,$type . " " .$count ." ". $summary );
					switch ($summary) {
						case "0":
							//IPS_LogMessage($this->moduleName,$type . " " .$count ." ". $summary );
							if ($devInfo=="info"){
								SetValueFloat ($this->GetIDForIdent($var_ID ),(float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1));
							}
							elseif ($devInfo=="param"){
								SetValueFloat ($this->GetIDForIdent($var_ID ),(float) PhpType::bytes2float($modbus->readMultipleRegisters($mb_device, $mb_adress, 2),1));
							}
							break;
						case "1":
							$counter=0;
							$val=0;
							do {
								$mb_device = $mb_device+1;
								//IPS_LogMessage($this->moduleName,"Device: ".$type . "_" .  $counter." Summary: ". $summary." ".$mb_device ." Address: ".$mb_adress);
								$val = $val + (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1);
								$counter++;
							} while($counter<$count);	
							//IPS_LogMessage($this->moduleName, $val);
							SetValueFloat ($this->GetIDForIdent($var_ID ),$val);
							break;
						case "2":
							$counter=0;
							$val=0;
							do {
								IPS_LogMessage($this->moduleName,$type . " " .$count ." ". $summary ." ". $mb_adress);
								$mb_device = $mb_device+1;
								//ToDo
								$counter++;
							} while($counter<$count);
							
							//IPS_LogMessage($this->moduleName,$type . " " .$count ." ". $summary );
							if ($devInfo=="info"){
								SetValueFloat ($this->GetIDForIdent($var_ID ),(float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1));
							}
							elseif ($devInfo=="param"){
								SetValueFloat ($this->GetIDForIdent($var_ID ),(float) PhpType::bytes2float($modbus->readMultipleRegisters($mb_device, $mb_adress, 2),1));
							}
							break;
					}
					
						$this->SendDebug("Debug", ("ID: ".$value->ID. " MB_Device: " . $mb_device ." MB_Address: ". $mb_adress) ,0);
					
					break;
				case "SHORT_ENUM":
				case "LONG_ENUM":
					$count = $this->ReadAttributeInteger("count_". $type);
					/* 	
						"summary":"0" => create summary not allowed
						"summary":"1" => create summary allowed
						"summary":"2" => create summary never allowed 
					*/
					//create Array from 'Unit' Paramter in form
					$chunks = array_chunk(preg_split('/(:|,)/', $unit), 2);
					$result = array_combine(array_column($chunks, 0), array_column($chunks, 1));
					$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
					switch ($summary) {
						case "0":
							if ($devInfo=="info"){
								$StuderState = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1);
							}
							elseif ($devInfo=="param"){
								$StuderState = (float) PhpType::bytes2float($modbus->readMultipleRegisters($mb_device, $mb_adress, 2),1);
							}
							SetValueString($this->GetIDForIdent($var_ID),$result[$StuderState]);
							break;
						case "1":
							if ($devInfo=="info"){
								$StuderState = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1);
							}
							elseif ($devInfo=="param"){
								$StuderState = (float) PhpType::bytes2float($modbus->readMultipleRegisters($mb_device, $mb_adress, 2),1);
							}
							SetValueString($this->GetIDForIdent($var_ID),$result[$StuderState]);
							break;
						case "2":
						$counter=0;
							$val=0;
							$devResult='';
							do {
								$mb_device = $mb_device+1;
								if ($devInfo=="info"){
									$StuderState = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($mb_device, $mb_adress, 2),1);
								}
								elseif ($devInfo=="param"){
									$StuderState = (float) PhpType::bytes2float($modbus->readMultipleRegisters($mb_device, $mb_adress, 2),1);
								}
								$devResult = $type. "_".$counter.": " . $result[$StuderState] . " " . $devResult;
								$counter++;
							} while($counter<$count);
							SetValueString($this->GetIDForIdent($var_ID),$devResult);
						break;
					}
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
	$this->UpdateFormField("Progress_01", "visible", true);
	foreach ($treeDataDevices as $value) {
		if(($value->Active)==true){
			$modbus = new ModbusMaster($this->ReadPropertyString("IP_Modbus_Gateway"), "TCP");
			$mb_DeviceTypeID = ($value->mb_DeviceTypeID);
			$mb_DeviceTypeRegister = ($value->mb_DeviceTypeRegister);
			if (($value->DeviceTypeID)=="XT") {
				$count=0;
				for ($i = ($mb_DeviceTypeID+1); $i <= ($mb_DeviceTypeID+9); $i++) {
					try {
						$a = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($i, $mb_DeviceTypeRegister, 2),1) . "\n";
						if ($a==1||256||512){ $count++;	}
					}catch (Exception $e) {
						//echo "did not find any XT Device\n";
					}
				}
				$this->WriteAttributeInteger("count_XT", $count);
				if ($count > 0){echo "found " .$count++ ." XT Devices\n";}
			}elseif(($value->DeviceTypeID)=="VS"){
				$count=0;
				for ($i = ($mb_DeviceTypeID+1); $i <= ($mb_DeviceTypeID+9); $i++) {
					try {
						$a = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($i, $mb_DeviceTypeRegister, 2),1) . "\n";
						if ($a==12801||13057){ $count++;	}
					}catch (Exception $e) {
						//echo "did not find any VS Device\n";
					}
				}
				$this->WriteAttributeInteger("count_VS", $count);
				if ($count > 0){echo "found " .$count++ ." VS Devices\n";}
			}elseif(($value->DeviceTypeID)=="VT"){
				$count=0;
				for ($i = ($mb_DeviceTypeID+1); $i <= ($mb_DeviceTypeID+9); $i++) {
					try {
						$a = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($i, $mb_DeviceTypeRegister, 2),1) . "\n";
						if ($a==0||1||2||3){ $count++;	}
					}catch (Exception $e) {
						//echo "did not find any VT Device\n";
					}
				}
				$this->WriteAttributeInteger("count_VT", $count);
				if ($count > 0){echo "found " .$count++ ." VT Devices\n";}
			}elseif(($value->DeviceTypeID)=="BSP"){
				$count=0;
				for ($i = ($mb_DeviceTypeID+1); $i <= ($mb_DeviceTypeID+9); $i++) {
					try {
						$a = (float) PhpType::bytes2float($modbus->readMultipleInputRegisters($i, $mb_DeviceTypeRegister, 2),1) . "\n";
						if ($a==13830||10241){ $count++;	}
					}catch (Exception $e) {
						//echo "did not find any BSP Device\n";
					}
				}
				$this->WriteAttributeInteger("count_BSP", $count);
				if ($count > 0){echo "found " .$count++ ." BSP Devices\n";}
			}
		}
	}
	$this->UpdateFormField("Progress_01", "visible", false);
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
	
			$studer_version = ($this->GetDataStuderVersion());
			
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
function GetDataStuderVersion() {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $this->ReadPropertyString("url"),
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
    
	return ($response); 
}
}