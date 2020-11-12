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
        $this->SetTimerInterval("UpdateTimer", $this->ReadPropertyInteger("UpdateIntervall")*1000); 
        $this->Username = $this->ReadPropertyString("Username");
        $this->Password = $this->ReadPropertyString("Password");
        if ($this->ReadPropertyBoolean("Debug")){
            IPS_LogMessage($this->moduleName, "Starting UpdateProcess");
            IPS_LogMessage($this->moduleName, "User ". $this->Username);
        }
        $updateStuder_01_script= file_get_contents(__DIR__ . "/StuderWeb_Function.php");
        $scriptID = $this->RegisterScript("updateClients", "updateClients", $updateStuder_01_script);
		IPS_SetScriptTimer($scriptID, 60); 

    }
    public function Update(){
        
       
   }

   /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    */
}