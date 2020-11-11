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
    }
 
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {
    // Diese Zeile nicht löschen
    parent::ApplyChanges();
    }
 
    /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    */
    public function MeineErsteEigeneFunktion() {
        // Selbsterstellter Code
    }
}