<?php

class ChromecastDiscovery extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterAttributeString('devices', '[]');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterTimer('Discovery', 0, 'ChromecastDiscovery_Discover($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $this->WriteAttributeString('devices', json_encode($this->DiscoverDevices()));
        $this->SetTimerInterval('Discovery', 300000);

        // Status Error Kategorie zum Import auswählen
        $this->SetStatus(102);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->ApplyChanges();
                }
                break;

            case IPS_KERNELMESSAGE:
                if ($Data[0] === KR_READY) {
                    $this->ApplyChanges();
                }
                break;
            case IPS_KERNELSTARTED:
                $this->WriteAttributeString('devices', json_encode($this->DiscoverDevices()));
                break;

            default:
                break;
        }
    }

    public function GetDevices()
    {
        $devices = $this->ReadPropertyString('devices');

        return $devices;
    }

    public function Discover()
    {
        $this->LogMessage($this->Translate('Background Discovery of Chromecast devices'), KL_NOTIFY);
        $this->WriteAttributeString('devices', json_encode($this->DiscoverDevices()));

        return json_encode($this->DiscoverDevices());
    }

    /*
     * Configuration Form
     */

    /**
     * build configuration form.
     *
     * @return string
     */
    public function GetConfigurationForm()
    {
        // return current form
        $Form = json_encode(
            [
                'elements' => [],
                'actions'  => $this->FormActions(),
                'status'   => $this->FormStatus(), ]
        );
        $this->SendDebug('FORM', $Form, 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);

        return $Form;
    }

    /**
     * return form actions by token.
     *
     * @return array
     */
    protected function FormActions()
    {
        $form = [
            [
                'name'     => 'ChromecastDiscovery',
                'type'     => 'Configurator',
                'rowCount' => 20,
                'add'      => false,
                'delete'   => true,
                'sort'     => [
                    'column'    => 'name',
                    'direction' => 'ascending', 
                ],
                'columns'  => [
                    [
                        'label' => 'name',
                        'name'  => 'name',
                        'width' => 'auto', 
                    ],
                    [
                        'label' => 'type',
                        'name'  => 'type',
                        'width' => 'auto', 
                    ],
                    [
                        'label' => 'uuid',
                        'name'  => 'uuid',
                        'width' => 'auto', 
                    ],
                    [
                        'label' => 'ip',
                        'name'  => 'ip',
                        'width' => 'auto', 
                    ],
                    [
                        'label' => 'port',
                        'name'  => 'port',
                        'width' => 'auto', ], 
                    ],
                'values'   => $this->Get_ListConfiguration(), 
            ], 
        ];

        return $form;
    }

    /**
     * return from status.
     *
     * @return array
     */
    protected function FormStatus()
    {
        $form = [
            [
                'code'    => 101,
                'icon'    => 'inactive',
                'caption' => 'Creating instance.', ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => 'Chromecast Discovery created.', ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => 'interface closed.', ],
            [
                'code'    => 201,
                'icon'    => 'inactive',
                'caption' => 'Please follow the instructions.', ], ];

        return $form;
    }

    /**
     * Liefert alle Geräte.
     *
     * @return array configlist all devices
     */
    private function Get_ListConfiguration()
    {
        $config_list = [];
        $DeviceIdList = IPS_GetInstanceListByModuleID('{F250ACBC-6C4A-4699-80D7-C3121E5E80D3}'); // Chromecast Device
        $devices = $this->DiscoverDevices();
        $this->SendDebug('Discovered Chromecast Devices', json_encode($devices), 0);
        
        if (!empty($devices)) {
            foreach ($devices as $device) {
                $instanceID = 0;
                $uuid = $device['uuid'];
                $name = $device['name'];
                $ip = $device['ip'];
                $port = $device['port'];
                foreach ($DeviceIdList as $DeviceId) {
                    if ($uuid == IPS_GetProperty($DeviceId, 'uuid')) {
                        $device_name = IPS_GetName($DeviceId);
                        $this->SendDebug(
                            'Chromecast Discovery', 'device found: ' . utf8_decode($device_name) . ' (' . $DeviceId . ')', 0
                        );
                        $instanceID = $DeviceId;
                    }
                }

                $config_list[] = [
                    'instanceID' => $instanceID,
                    'uuid'       => $uuid,
                    'name'       => $name,
                    'ip'       => $ip,
                    'port'       => $port,
                    'create'     => [
                        [
                            'moduleID'      => '{F250ACBC-6C4A-4699-80D7-C3121E5E80D3}',
                            'configuration' => [
                                'uuid' => $uuid,
                                'name' => $name,
                                'type' => $type,
                                'ip' => $ip,
                                'port' => $port, 
                            ], 
                        ],
                        [
                            'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                            'configuration' => [
                                'Host' => $ip,
                                'Port' => $port,
                                'Open' => true, 
                            ], 
                        ], 
                    ], 
                ];
            }
        }

        return $config_list;
    }

    private function DiscoverDevices(): array
    {
        $ids = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
        $devices = ZC_QueryServiceType($ids[0], '_googlecast._tcp.', '');
        $chromecasts = [];
        if (!empty($devices)) {
            foreach ($devices as $device) {
                $data = [];
                $deviceInfos = ZC_QueryService($ids[0], $device['Name'], '_googlecast._tcp.', 'local.');
                if (!empty($deviceInfos)) {
                    foreach ($deviceInfos as $info) {
                        data['port'] = $info['Port'];
                        if (empty($info['IPv4'])) {
                            $data['ip'] = $info['IPv6'][0];
                        } else {
                            $data['ip'] = $info['IPv4'][0];
                        }
                        if (array_key_exists('TXTRecords', $info)) {
                            $txtRecords = $info['TXTRecords'];
                            foreach ($txtRecords as $record) {
                                if (strpos($record, 'fn=') !== false) {
                                    $data['name'] = (string) str_replace('fn=', '', $record);
                                } else if (strpos($record, 'id=') !== false) {
                                    $data['uuid'] = (string) str_replace('id=', '', $record);
                                } else if (strpos($record, 'md=') !== false) {
                                    $data['type'] = (string) str_replace('md=', '', $record);
                                }
                            }
                            if(isset($data['ip']) && 
                                isset($data['port']) && 
                                isset($data['name']) && 
                                isset($data['uuid']) &&
                                isset($data['type'])) {  
                                array_push($chromecasts, $data);
                            }
                        }
                    }
                }
            }
        }

        $this->SendDebug('Discover Response:', json_encode($chromecasts), 0);
        
        return $chromecasts;
    }
}