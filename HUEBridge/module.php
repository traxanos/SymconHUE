<?php

class HUEBridge extends IPSModule {

  private $Host = "";
  private $User = "";
  private $LightsCategory = 0;

  public function Create() {
    parent::Create();
    $this->RegisterPropertyString("Host", "");
    $this->RegisterPropertyString("User", "");
    $this->RegisterPropertyInteger("LightsCategory", 0);
    $this->RegisterPropertyInteger("UpdateInterval", 5);
  }

  public function ApplyChanges() {
    $this->Host = "";
    $this->User = "";
    $this->CategoryLights = 0;

    parent::ApplyChanges();

    $this->RegisterTimer('UPDATE', $this->ReadPropertyString('UpdateInterval'), 'HUE_SyncStates($id)');

    $this->ValidateConfiguration();
  }

  protected function RegisterTimer($ident, $interval, $script) {
    $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

    if ($id && IPS_GetEvent($id)['EventType'] <> 1) {
      IPS_DeleteEvent($id);
      $id = 0;
    }

    if (!$id) {
      $id = IPS_CreateEvent(1);
      IPS_SetParent($id, $this->InstanceID);
      IPS_SetIdent($id, $ident);
    }

    IPS_SetName($id, $ident);
    IPS_SetHidden($id, true);
    IPS_SetEventScript($id, "\$id = \$_IPS['TARGET'];\n$script;");

    if (!IPS_EventExists($id)) IPS_LogMessage("SymconHUE", "Ident with name $ident is used for wrong object type");

    if (!($interval > 0)) {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
      IPS_SetEventActive($id, false);
    } else {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $interval);
      IPS_SetEventActive($id, true);
    }
  }

  private function ValidateConfiguration() {
    if ($this->ReadPropertyInteger('LightsCategory') == 0 ||  $this->ReadPropertyString('Host') == '' || $this->ReadPropertyString('User') == '') {
      $this->SetStatus(104);
    } elseif(!$this->ValidateUser()) {
      $this->SetStatus(201);
    } else {
      $this->SetStatus(102);
    }
  }

  private function ValidateUser() {
    $result = (array)$this->Request("/lights", null);
    if(!isset($result[0]) && !isset($result[0]->error)) {
      return true;
    } else {
      return false;
    }
  }

  private function GetLightsCategory() {
    if($this->LightsCategory == '') $this->LightsCategory = $this->ReadPropertyString('LightsCategory');
    return $this->LightsCategory;
  }

  private function GetHost() {
    if($this->Host == '') $this->Host = $this->ReadPropertyString('Host');
    return $this->Host;
  }

  private function GetUser() {
    if($this->User == '') {
      $this->User = $this->ReadPropertyString('User');
    }
    return $this->User;
  }

  public function Request(string $path, array $data = null) {
    $host = $this->GetHost();
    $user = $this->GetUser();

    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, "http://$host:80/api/$user$path");
    curl_setopt($client, CURLOPT_USERAGENT, "SymconHUE");
    curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($client, CURLOPT_TIMEOUT, 5);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
    if (isset($data)) curl_setopt($client, CURLOPT_CUSTOMREQUEST, 'PUT');
    if (isset($data)) curl_setopt($client, CURLOPT_POSTFIELDS, json_encode($data));
    $result = curl_exec($client);
    $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
    curl_close($client);

    if ($status == '0') {
      $this->SetStatus(203);
      return false;
    } elseif ($status != '200') {
      $this->SetStatus(201);
      return false;
    } else {
      $result = json_decode($result);
      if (is_array($result) && @isset($result[0]->error->description) && $result[0]->error->description == 'unauthorized user') {
        $this->SetStatus(201);
        return false;
      }

      if (isset($data)) {
        if (count($result) > 0) {
          foreach ($result as $item) {
            if (@$item->error) {
              $this->SetStatus(299);
              return false;
            }
          }
        }
        $this->SetStatus(102);
        return true;
      } else {
        $this->SetStatus(102);
        return $result;
      }
    }
  }

  public function RegisterUser() {
    $host = $this->GetHost();
    $json = json_encode(array('devicetype' => "IPS"));
    $lenght = strlen($json);

    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, "http://$host:80/api");
    curl_setopt($client, CURLOPT_USERAGENT, "SymconHUE");
    curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($client, CURLOPT_TIMEOUT, 5);
    curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($client, CURLOPT_POST, true);
    curl_setopt($client, CURLOPT_POSTFIELDS, $json);
    $result = curl_exec($client);
    $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
    curl_close($client);

    if ($status == '0') {
      $this->SetStatus(203);
      return false;
    } elseif ($status != '200') {
      IPS_LogMessage("SymconHUE", "Response invalid. Code $status");
    } else {
      $result = json_decode($result);
      if(@isset($result[0]->error)) {
        $this->SetStatus(202);
      } else {
        if(@isset($result[0]->success->username) && $result[0]->success->username != '') {
          $user = $result[0]->success->username;
          IPS_SetConfiguration($this->InstanceID, json_encode(array('User' => $user)));
          IPS_ApplyChanges($this->InstanceID);
          print_r("Die Registrierung war erfoglreich. Schließen Sie die Konfigurationsmaske, damit der Benutzername übernommen wird.");
          $this->SetStatus(102);
        } else {
          $this->SetStatus(202);
        }
      }
    }
  }

  /*
   * HUE_SyncDevices($bridgeId)
   * Abgleich aller Lampen
   */
  public function SyncDevices() {
    $lightsCategoryId = $this->GetLightsCategory();
    if(@$lightsCategoryId > 0) {
      $lights = $this->Request('/lights');
      if ($lights) {
        foreach ($lights as $lightId => $light) {
          $name = utf8_decode((string)$light->name);
          $uniqueId = (string)$light->uniqueid;
          echo "$lightId. $name ($uniqueId)\n";

          $deviceId = $this->GetDeviceByUniqueId($uniqueId);

          if ($deviceId == 0) {
            $deviceId = IPS_CreateInstance($this->DeviceGuid());
            IPS_SetProperty($deviceId, 'UniqueId', $uniqueId);
          }

          IPS_SetParent($deviceId, $lightsCategoryId);
          IPS_SetProperty($deviceId, 'LightId', (integer)$lightId);
          IPS_SetName($deviceId, $name);

          // Verbinde Light mit Bridge
          if (IPS_GetInstance($deviceId)['ConnectionID'] <> $this->InstanceID) {
            @IPS_DisconnectInstance($deviceId);
            IPS_ConnectInstance($deviceId, $this->InstanceID);
          }

          IPS_ApplyChanges($deviceId);
          HUE_RequestData($deviceId);
        }
      }
      return true;
    } else {
      echo 'Lampen konnten nicht syncronisiert werden, da die Lampenkategorie nicht zugewiesen wurde.';
      IPS_LogMessage('SymconHUE', 'Lampen konnten nicht syncronisiert werden, da die Lampenkategorie nicht zugewiesen wurde.');
      return false;
    }
  }

  /*
   * HUE_SyncStates($bridgeId)
   * Abgleich des Status aller Lampen
   */
  public function SyncStates() {
    $lights = $this->Request('/lights');
    if ($lights) {
      foreach ($lights as $lightId => $light) {
        $uniqueId = (string)$light->uniqueid;
        $deviceId = $this->GetDeviceByUniqueId($uniqueId);
        if($deviceId > 0) HUE_ApplyData($deviceId, $light);
      }
    }
  }

  /*
   * HUE_GetDeviceByUniqueId($bridgeId, $uniqueId)
   * Liefert zu einer UniqueID die passende Lampeninstanz
   */
  public function GetDeviceByUniqueId(string $uniqueId) {
    $deviceIds = IPS_GetInstanceListByModuleID($this->DeviceGuid());
    foreach($deviceIds as $deviceId) {
      if(IPS_GetProperty($deviceId, 'UniqueId') == $uniqueId) {
        return $deviceId;
      }
    }
  }

  private function DeviceGuid() {
    return '{729BE8EB-6624-4C6B-B9E5-6E09482A3E36}';
  }

}
