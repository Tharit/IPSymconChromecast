<?php

require_once (__DIR__ . "/../libs/mdns.php");

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
                        'label' => 'uuid',
                        'name'  => 'uuid',
                        'width' => 'auto', 
                    ],
                    [
                        'label' => 'host',
                        'name'  => 'host',
                        'width' => '400px', 
                    ],
                    [
                        'label' => 'port',
                        'name'  => 'port',
                        'width' => '250px', ], 
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
                $host = $device['host'];
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
                    'host'       => $ip,
                    'port'       => $port,
                    'create'     => [
                        [
                            'moduleID'      => '{F250ACBC-6C4A-4699-80D7-C3121E5E80D3}',
                            'configuration' => [
                                'uuid' => $uuid,
                                'name' => $name,
                                'host' => $host,
                                'port' => $port, 
                            ], 
                        ],
                        [
                            'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',
                            'configuration' => [
                                'Host' => $host,
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

    private function scan($wait = 15) {
    // Performs an mdns scan of the network to find chromecasts and returns an array
		// Let's test by finding Google Chromecasts
		$mdns = new mDNS();
		// Search for chromecast devices
		// For a bit more surety, send multiple search requests
		$firstresponsetime = - 1;
		$lastpackettime = - 1;
		$starttime = round(microtime(true) * 1000);
		$mdns->query("_googlecast._tcp.local", 1, 12, "");
		$mdns->query("_googlecast._tcp.local", 1, 12, "");
		$mdns->query("_googlecast._tcp.local", 1, 12, "");
		$cc = $wait;
		$filetoget = 1;
		$dontrequery = 0;
		set_time_limit($wait * 2);
		$chromecasts = array();
		while ($cc > 0) {
			$inpacket = "";
			while ($inpacket == "") {
				$inpacket = $mdns->readIncoming();
				if ($inpacket <> "") {
					if ($inpacket->packetheader->getQuestions() > 0) {
						$inpacket = "";
					}
				}
				if ($lastpackettime <> - 1) {
					// If we get to here then we have a valid last packet time
					$timesincelastpacket = round(microtime(true) * 1000) - $lastpackettime;
					if ($timesincelastpacket > ($firstresponsetime * 5) && $firstresponsetime != - 1) {
						return $chromecasts;
					}
				}
				if ($inpacket <> "") {
					$lastpackettime = round(microtime(true) * 1000);
				}
				$timetohere = round(microtime(true) * 1000) - $starttime;
				// Maximum five second rule
				if ($timetohere > 5000) {
					return $chromecasts;
				}
			}
			// If our packet has answers, then read them
			// $mdns->printPacket($inpacket);
			if ($inpacket->packetheader->getAnswerRRs() > 0) {
				$dontrequery = 0;
				// $mdns->printPacket($inpacket);
				for ($x = 0; $x < sizeof($inpacket->answerrrs); $x++) {
					if ($inpacket->answerrrs[$x]->qtype == 12) {
						// print_r($inpacket->answerrrs[$x]);
						if ($inpacket->answerrrs[$x]->name == "_googlecast._tcp.local") {
							if ($firstresponsetime == - 1) {
								$firstresponsetime = round(microtime(true) * 1000) - $starttime;
							}
							$name = "";
							for ($y = 0; $y < sizeof($inpacket->answerrrs[$x]->data); $y++) {
								$name.= chr($inpacket->answerrrs[$x]->data[$y]);
							}
							// The chromecast itself fills in additional rrs. So if that's there then we have a quicker method of
							// processing the results.
							// First build any missing entries with any 33 packets we find.
							for ($p = 0; $p < sizeof($inpacket->additionalrrs); $p++) {
								if ($inpacket->additionalrrs[$p]->qtype == 33) {
									$d = $inpacket->additionalrrs[$p]->data;
									$port = ($d[4] * 256) + $d[5];
									// We need the target from the data
									$offset = 6;
									$size = $d[$offset];
									$offset++;
									$target = "";
									for ($z = 0; $z < $size; $z++) {
										$target.= chr($d[$offset + $z]);
									}
									$target.= ".local";
									if (!isset($chromecasts[$inpacket->additionalrrs[$p]->name])) {
										$chromecasts[$inpacket->additionalrrs[$x]->name] = array(
											"port" => $port,
											"ip" => "",
											"target" => "",
											"friendlyname" => ""
										);
									}
									$chromecasts[$inpacket->additionalrrs[$x]->name]['target'] = $target;
								}
							}
							// Next repeat the process for 16
							for ($p = 0; $p < sizeof($inpacket->additionalrrs); $p++) {
								if ($inpacket->additionalrrs[$p]->qtype == 16) {
									$fn = "";
									for ($q = 0; $q < sizeof($inpacket->additionalrrs[$p]->data); $q++) {
										$fn.= chr($inpacket->additionalrrs[$p]->data[$q]);
									}
									$stp = strpos($fn, "fn=") + 3;
									$etp = strpos($fn, "ca=");
									$fn = substr($fn, $stp, $etp - $stp - 1);
									if (!isset($chromecasts[$inpacket->additionalrrs[$p]->name])) {
										$chromecasts[$inpacket->additionalrrs[$x]->name] = array(
											"port" => 8009,
											"ip" => "",
											"target" => "",
											"friendlyname" => ""
										);
									}
									$chromecasts[$inpacket->additionalrrs[$x]->name]['friendlyname'] = $fn;
								}
							}
							// And finally repeat again for 1
							for ($p = 0; $p < sizeof($inpacket->additionalrrs); $p++) {
								if ($inpacket->additionalrrs[$p]->qtype == 1) {
									$d = $inpacket->additionalrrs[$p]->data;
									$ip = $d[0] . "." . $d[1] . "." . $d[2] . "." . $d[3];
									foreach($chromecasts as $key => $value) {
										if ($value['target'] == $inpacket->additionalrrs[$p]->name) {
											$value['ip'] = $ip;
											$chromecasts[$key] = $value;
										}
									}
								}
							}
							$dontrequery = 1;
							// Check our item. If it doesn't exist then it wasn't in the additionals, so send requests.
							// If it does exist then check it has all the items. If not, send the requests.
							if (isset($chromecasts[$name])) {
								$xx = $chromecasts[$name];
								if ($xx['target'] == "") {
									// Send a 33 request
									$mdns->query($name, 1, 33, "");
									$dontrequery = 0;
								}
								if ($xx['friendlyname'] == "") {
									// Send a 16 request
									$mdns->query($name, 1, 16, "");
									$dontrequery = 0;
								}
								if ($xx['target'] != "" && $xx['friendlyname'] != "" && $xx['ip'] == "") {
									// Only missing the ip address for the target.
									$mdns->query($xx['target'], 1, 1, "");
									$dontrequery = 0;
								}
							}
							else {
								// Send queries. These'll trigger a 1 query when we have a target name.
								$mdns->query($name, 1, 33, "");
								$mdns->query($name, 1, 16, "");
								$dontrequery = 0;
							}
							if ($dontrequery == 0) {
								$cc = $wait;
							}
							set_time_limit($wait * 2);
						}
					}
					if ($inpacket->answerrrs[$x]->qtype == 33) {
						$d = $inpacket->answerrrs[$x]->data;
						$port = ($d[4] * 256) + $d[5];
						// We need the target from the data
						$offset = 6;
						$size = $d[$offset];
						$offset++;
						$target = "";
						for ($z = 0; $z < $size; $z++) {
							$target.= chr($d[$offset + $z]);
						}
						$target.= ".local";
						if (!isset($chromecasts[$inpacket->answerrrs[$x]->name])) {
							$chromecasts[$inpacket->answerrrs[$x]->name] = array(
								"port" => $port,
								"ip" => "",
								"target" => $target,
								"friendlyname" => ""
							);
						}
						else {
							$chromecasts[$inpacket->answerrrs[$x]->name]['target'] = $target;
						}
						// We know the name and port. Send an A query for the IP address
						$mdns->query($target, 1, 1, "");
						$cc = $wait;
						set_time_limit($wait * 2);
					}
					if ($inpacket->answerrrs[$x]->qtype == 16) {
						$fn = "";
						for ($q = 0; $q < sizeof($inpacket->answerrrs[$x]->data); $q++) {
							$fn.= chr($inpacket->answerrrs[$x]->data[$q]);
						}
						$stp = strpos($fn, "fn=") + 3;
						$etp = strpos($fn, "ca=");
						$fn = substr($fn, $stp, $etp - $stp - 1);
						if (!isset($chromecasts[$inpacket->answerrrs[$x]->name])) {
							$chromecasts[$inpacket->answerrrs[$x]->name] = array(
								"port" => 8009,
								"ip" => "",
								"target" => "",
								"friendlyname" => $fn
							);
						}
						else {
							$chromecasts[$inpacket->answerrrs[$x]->name]['friendlyname'] = $fn;
						}
						$mdns->query($chromecasts[$inpacket->answerrrs[$x]->name]['target'], 1, 1, "");
						$cc = $wait;
						set_time_limit($wait * 2);
					}
					if ($inpacket->answerrrs[$x]->qtype == 1) {
						$d = $inpacket->answerrrs[$x]->data;
						$ip = $d[0] . "." . $d[1] . "." . $d[2] . "." . $d[3];
						// Loop through the chromecasts and fill in the ip
						foreach($chromecasts as $key => $value) {
							if ($value['target'] == $inpacket->answerrrs[$x]->name) {
								$value['ip'] = $ip;
								$chromecasts[$key] = $value;
								// If we have an IP address but no friendly name, try and get the friendly name again!
								if (strlen($value['friendlyname']) < 1) {
									$mdns->query($key, 1, 16, "");
									$cc = $wait;
									set_time_limit($wait * 2);
								}
							}
						}
					}
				}
			}
			$cc--;
		}
		return $chromecasts;
    }

    protected function GetChromecastDeviceInfo($devices)
    {
        $harmony_info = [];
        foreach ($result as $device) {
            $harmony_info[] = [
                'name' => $device['friendlyname'],
                'uuid' => explode(".", $device['target'])[0],
                'host' => $device['ip'],
                'port' => $device['port']
            ];
        }

        return $harmony_info;
    }

    private function DiscoverDevices(): array
    {
        $devices = $this->scan();
        $this->SendDebug('Discover Response:', json_encode($devices), 0);
        $chromecast_info = $this->GetChromecastDeviceInfo($devices);
        foreach ($chromecast_info as $device) {
            $this->SendDebug('uuid:', $device['uuid'], 0);
            $this->SendDebug('name:', $device['name'], 0);
            $this->SendDebug('host:', $device['host'], 0);
            $this->SendDebug('port:', $device['port'], 0);
        }

        return $chromecast_info;
    }
}