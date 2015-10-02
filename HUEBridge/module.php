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
    $host = $this->GetHost();
    $user = $this->GetUser();
    $method = isset($data) ? "PUT" : "GET";
    $json = json_encode($data);

    $lenght = strlen($json);

    $request = "${method} /api/{$user}{$path} HTTP/1.1\r\n";
    $request .= "Host: ".$host."\r\n";
    $request .= "User-Agent: SymconYAVR\r\n";
    $request .= "Connection: keep-alive\r\n";
    $request .= "Content-Type: text/json; charset=UTF-8\r\n";
    $request .= "Content-Length: ".$lenght."\r\n";
    $request .= "Pragma: no-cache\r\n";
    $request .= "Cache-Control: no-cache\r\n\r\n";
    if($method == 'PUT') $request .= $json;

    $fp = fsockopen($host, 80) or die("Unable to connect!");
    fputs($fp, $request);

    $response = "";
    while (!feof($fp)) {
      $response .= fread($fp, 1024);
    }

    fclose($fp);
    list($header, $result) = explode("\r\n\r\n", $response, 2);
    $status = substr($header,9,3);

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

    $host = $this->GetHost();
    $json = json_encode(array('username' => $this->GetUser(), 'devicetype' => "IPS"));
    $lenght = strlen($json);

    $request = "POST /api HTTP/1.1\r\n";
    $request .= "Host: ".$host."\r\n";
    $request .= "User-Agent: SymconYAVR\r\n";
    $request .= "Connection: keep-alive\r\n";
    $request .= "Content-Type: text/json; charset=UTF-8\r\n";
    $request .= "Content-Length: ".$lenght."\r\n";
    $request .= "Pragma: no-cache\r\n";
    $request .= "Cache-Control: no-cache\r\n\r\n";
    $request .= $json;

    $fp = fsockopen($host, 80) or die("Unable to connect!");
    fputs($fp, $request);

    $response = "";
    while (!feof($fp)) {
      $response .= fread($fp, 1024);
    }

    fclose($fp);
    list($header, $result) = explode("\r\n\r\n", $response, 2);
    $status = substr($header,9,3);

    if ($status != '200') {
      throw new Exception("Response invalid. Code $status");
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
