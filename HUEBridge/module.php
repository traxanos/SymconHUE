<?
class HUEBridge extends IPSModule {

  private $Host = "";
  private $User = "";
  private $LightsCategory = 0;

  public function Create() {
    parent::Create();
    $this->RegisterPropertyString("Host", "");
    $this->RegisterPropertyString("User", "SymconHUE");
    $this->RegisterPropertyInteger("LightsCategory", 0);
  }

  public function ApplyChanges() {
    $this->Host = "";
    $this->User = "";
    $this->CategoryLights = 0;

    parent::ApplyChanges();
    $this->ValidateConfiguration();
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
      if (!preg_match('/[a-f0-9]{32}/i', $this->User)) {
        $this->User = md5($this->User);
      }
    }
    return $this->User;
  }

  public function Request($path, $data = null) {
    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, "http://{$this->GetHost()}:80/api/{$this->GetUser()}{$path}");
    curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($client, CURLOPT_HEADER, 0);
    curl_setopt($client, CURLOPT_TIMEOUT, 10);
    curl_setopt($client, CURLOPT_BUFFERSIZE, 8192);
    curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    if (isset($data)) {
      curl_setopt($client, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($client, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $result = curl_exec($client);
    $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
    curl_close($client);
    if ($status != '200') {
      throw new Exception("Response invalid. Code $status");
    } else {
      if (isset($data)) {
        $result = json_decode($result);
        if (count($result) > 0) {
          foreach ($result as $item) {
            if (@$item->error) return false;
          }
        }
        return true;
      } else {
        return json_decode($result);
      }
    }
  }

  public function RegisterUser() {
    $client = curl_init();
    curl_setopt($client, CURLOPT_URL, "http://{$this->GetHost()}:80/api");
    curl_setopt($client, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($client, CURLOPT_HEADER, 0);
    curl_setopt($client, CURLOPT_TIMEOUT, 10);
    curl_setopt($client, CURLOPT_BUFFERSIZE, 1024);
    curl_setopt($client, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($client, CURLOPT_POST, true);
    curl_setopt($client, CURLOPT_POSTFIELDS, json_encode(array('username' => $this->GetUser(), 'devicetype' => "IPS")));
    $result = curl_exec($client);
    $status = curl_getinfo($client, CURLINFO_HTTP_CODE);
    curl_close($client);
    if ($status != '200') {
      $this->SetStatus(299);
    } else {
      $result = json_decode($result);
      print_r($result);
      if(@isset($result[0]->error)) {
        $this->SetStatus(202);
      } else {
        $this->SetStatus(102);
      }
    }
  }

  public function SyncDevices() {
    $lightsCategoryId = $this->GetLightsCategory();

    $lights = $this->Request('/lights');
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
    echo "Fertig";
  }

  public function SyncStates() {
    $lightsCategoryId = $this->ReadPropertyInteger("LightsCategory");
    if(!(@$lightsCategoryId > 0)) throw new Exception("Lampenkategorie muss ausgefüllt sein");

    $lights = $this->Request('/lights');
    foreach ($lights as $lightId => $light) {
      $uniqueId = (string)$light->uniqueid;
      $deviceId = $this->GetDeviceByUniqueId($uniqueId);
      if($deviceId > 0) HUE_ApplyData($deviceId, $light);
    }
    echo "Fertig";
  }

  public function GetDeviceByUniqueId($uniqueId) {
    $deviceIds = IPS_GetInstanceListByModuleID($this->DeviceGuid());
    foreach($deviceIds as $deviceId) {
      if(IPS_GetProperty($deviceId, 'UniqueId') == $uniqueId) {
        return $deviceId;
      }
    }
  }

  private function DeviceGuid() {
    return "{729BE8EB-6624-4C6B-B9E5-6E09482A3E36}";
  }

}
?>
