<?php
class StuderInnotecWeb extends IPSModule {
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
      
        //Never delete this line!
        parent::Destroy();
    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->Username = $this->ReadPropertyString("Username");
        $this->Password = $this->ReadPropertyString("Password");

    }
 
    /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    */
    public function Studer_Update() {
        if (DEBUG):
            var_dump ("=====");
        endif;
    }
}