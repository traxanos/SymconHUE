<?php

class HUEBridge extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('User', '');
        $this->RegisterPropertyInteger('LightsCategory', 0);
        $this->RegisterPropertyInteger('GroupsCategory', 0);
        $this->RegisterPropertyInteger('SensorsCategory', 0);
        $this->RegisterPropertyInteger('UpdateInterval', 5);

        $this->RegisterTimer('Update', 0, 'HUE_SyncStates($_IPS[\'TARGET\'], 0);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        if ($oldInterval = @$this->GetIDForIdent('UPDATE')) {
            IPS_DeleteEvent($oldInterval);
        }

        $this->ValidateConfiguration();
    }

    private function ValidateConfiguration()
    {
        if ($this->ReadPropertyInteger('LightsCategory') == 0 || $this->ReadPropertyString('Host') == '' || $this->ReadPropertyString('User') == '') {
            $this->SetStatus(104);
        } elseif (!$this->ValidateUser()) {
            $this->SetStatus(201);
        } else {
            $this->SetStatus(102);
        }
    }

    private function ValidateUser()
    {
        $result = (array)$this->Request('/lights', null);
        if (!isset($result[0]) && !isset($result[0]->error)) {
            return true;
        } else {
            return false;
        }
    }

    public function Request(string $path, array $data = null)
    {
        $host = $this->ReadPropertyString('Host');
        $user = $this->ReadPropertyString('User');

        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "http://$host:80/api/$user$path");
        curl_setopt($client, CURLOPT_USERAGENT, 'SymconHUE');
        curl_setopt($client, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($client, CURLOPT_TIMEOUT, 5);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, 1);
        if (isset($data)) {
            curl_setopt($client, CURLOPT_CUSTOMREQUEST, 'PUT');
        }
        if (isset($data)) {
            curl_setopt($client, CURLOPT_POSTFIELDS, json_encode($data));
        }
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
                            IPS_LogMessage('SymconHUE', print_r(@$item->error, 1));
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

    public function RegisterUser()
    {
        $host = $this->ReadPropertyString('Host');
        $json = json_encode(array('devicetype' => 'IPS'));
        $lenght = strlen($json);

        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, "http://$host:80/api");
        curl_setopt($client, CURLOPT_USERAGENT, 'SymconHUE');
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
            IPS_LogMessage('SymconHUE', "Response invalid. Code $status");
        } else {
            $result = json_decode($result);
            if (@isset($result[0]->error)) {
                $this->SetStatus(202);
            } else {
                if (@isset($result[0]->success->username) && $result[0]->success->username != '') {
                    $user = $result[0]->success->username;
                    IPS_SetConfiguration($this->InstanceID, json_encode(array('User' => $user)));
                    IPS_ApplyChanges($this->InstanceID);
                    print_r($this->Translate('The registration was successful.') . "\n" . $this->Translate('Close the configuration mask to apply the username.'));
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
    public function SyncDevices()
    {
        $lightsCategoryId = $this->ReadPropertyInteger('LightsCategory');
        $groupsCategoryId = $this->ReadPropertyInteger('GroupsCategory');
        $sensorsCategoryId = $this->ReadPropertyInteger('SensorsCategory');

        if (@$lightsCategoryId > 0) {
            $lights = $this->Request('/lights');
            if ($lights) {
                foreach ($lights as $lightId => $light) {
                    $name = utf8_decode((string)$light->name);
                    $uniqueId = (string)$light->uniqueid;
                    echo $this->Translate('Lamp') . ": \"$name\" ($lightId - $uniqueId)\n";

                    $deviceId = $this->GetDeviceByUniqueId($uniqueId);

                    if ($deviceId == 0) {
                        $deviceId = IPS_CreateInstance($this->LightGuid());
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
        } else {
            echo $this->Translate('Lamps could not be synced because the lamps category was not assigned.') . "\n";
            IPS_LogMessage('SymconHUE', $this->Translate('Lamps could not be synced because the lamps category was not assigned.'));
        }

        if (@$groupsCategoryId > 0) {
            $groups = (array)$this->Request('/groups');
            $group_zero = $this->Request('/groups/0');
            $group_zero->name = "All";
            $groups[0] = $group_zero;
            if ($groups) {
                foreach ($groups as $groupId => $group) {
                    $name = utf8_decode((string)$group->name);
                    echo $this->Translate('Group') .": \"$name\" ($groupId)\n";

                    $deviceId = $this->GetDeviceByGroupId($groupId);
                    if ($deviceId == 0) {
                        $deviceId = IPS_CreateInstance($this->GroupGuid());
                        IPS_SetProperty($deviceId, 'GroupId', (integer)$groupId);
                        IPS_SetName($deviceId, $name);
                    } elseif ($groupId != 0) {
                        IPS_SetName($deviceId, $name);
                    }

                    IPS_SetParent($deviceId, $groupsCategoryId);

                    // Verbinde Light mit Bridge
                    if (IPS_GetInstance($deviceId)['ConnectionID'] <> $this->InstanceID) {
                        @IPS_DisconnectInstance($deviceId);
                        IPS_ConnectInstance($deviceId, $this->InstanceID);
                    }

                    IPS_ApplyChanges($deviceId);
                    HUE_RequestData($deviceId);
                }
            }
        } else {
            echo $this->Translate('Groups could not be synced because the groups category was not assigned.') . "\n";
            IPS_LogMessage('SymconHUE', $this->Translate('Groups could not be synced because the groups category was not assigned.'));
        }

        if (@$sensorsCategoryId > 0) {
            $sensors = $this->Request('/sensors');
            if ($sensors) {
                foreach ($sensors as $sensorId => $sensor) {
                    if ($sensor->type == "ZLLPresence") {
                        // only support ZLLPresence sensor
                        $name = utf8_decode((string)$sensor->name);
                        $uniqueId = (string)$sensor->uniqueid;
                        // only use first 26 characters as uniqueid
                        $uniqueId = substr($uniqueId, 0, 26);
                        echo $this->Translate('Sensor') .": \"$name\" ($sensorId - $uniqueId)\n";

                        $deviceId = $this->GetDeviceByUniqueIdSensor($uniqueId);
                        if ($deviceId == 0) {
                            $deviceId = IPS_CreateInstance($this->SensorGuid());
                            IPS_SetProperty($deviceId, 'UniqueId', $uniqueId);
                        }

                        IPS_SetParent($deviceId, $sensorsCategoryId);

                        IPS_SetProperty($deviceId, 'SensorId', (integer)$sensorId);
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
            }
        } else {
            echo $this->Translate('Sensors could not be synced because the sensors category was not assigned.') . "\n";
            IPS_LogMessage('SymconHUE', $this->Translate('Sensors could not be synced because the sensors category was not assigned.'));
        }
        return true;
    }

    /*
     * HUE_SyncStates($bridgeId)
     * Abgleich des Status aller Lampen
     */
    public function SyncStates()
    {
        $lights = $this->Request('/lights');
        if ($lights) {
            foreach ($lights as $lightId => $light) {
                $uniqueId = (string)$light->uniqueid;
                $deviceId = $this->GetDeviceByUniqueId($uniqueId);
                if ($deviceId > 0) {
                    HUE_ApplyData($deviceId, $light);
                }
            }
        }

        $groups = $this->Request('/groups');
        if ($groups) {
            foreach ($groups as $groupId => $group) {
                $deviceId = $this->GetDeviceByGroupId($groupId);
                if ($deviceId > 0) {
                    HUE_ApplyData($deviceId, $group);
                }
            }
        }

        $sensors = $this->Request('/sensors');
        if ($sensors) {
            foreach ($sensors as $sensorId => $sensor) {
                if ($sensor->type == 'ZLLPresence' || $sensor->type == 'ZLLLightLevel' || $sensor->type == 'ZLLTemperature') {
                    // only support ZLLPresence sensor, also read data from ZLLLightLevel and ZLLTemperature sensors
                    $uniqueId = (string)$sensor->uniqueid;
                    // only use first 26 characters as uniqueid
                    $uniqueId = substr($uniqueId, 0, 26);
                    $deviceId = $this->GetDeviceByUniqueIdSensor($uniqueId);
                    if ($deviceId > 0) {
                        HUE_ApplyData($deviceId, $sensor);
                    }
                }
            }
        }
    }

    /*
     * HUE_GetDeviceByUniqueId($bridgeId, $uniqueId)
     * Liefert zu einer UniqueID die passende Lampeninstanz
     */
    public function GetDeviceByUniqueId(string $uniqueId)
    {
        $deviceIds = IPS_GetInstanceListByModuleID($this->LightGuid());
        foreach ($deviceIds as $deviceId) {
            if (IPS_GetProperty($deviceId, 'UniqueId') == $uniqueId) {
                return $deviceId;
            }
        }
    }

    public function GetDeviceByUniqueIdSensor(string $uniqueId)
    {
        $deviceIds = IPS_GetInstanceListByModuleID($this->SensorGuid());
        foreach ($deviceIds as $deviceId) {
            if (IPS_GetProperty($deviceId, 'UniqueId') == $uniqueId) {
                return $deviceId;
            }
        }
    }

    public function GetDeviceByGroupId(int $groupId)
    {
        $deviceIds = IPS_GetInstanceListByModuleID($this->GroupGuid());
        foreach ($deviceIds as $deviceId) {
            if (IPS_GetProperty($deviceId, 'GroupId') == $groupId && $this->InstanceID == IPS_GetInstance($deviceId)['ConnectionID']) {
                return $deviceId;
            }
        }
    }

    private function LightGuid()
    {
        return '{729BE8EB-6624-4C6B-B9E5-6E09482A3E36}';
    }

    private function GroupGuid()
    {
        return '{C47C8889-02C4-40A2-B18A-DBD9E47CE23D}';
    }

    private function SensorGuid()
    {
        return '{C0D95A01-1891-4953-A3B3-779678E9C955}';
    }
}
