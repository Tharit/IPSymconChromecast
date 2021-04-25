<?php

class ChromecastDevice extends IPSModule
{
 
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.

        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}'); // IO Client Socket

        $this->RegisterPropertyString('uuid', '');
        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyString('type', '');
        $this->RegisterPropertyString('ip', '');
        $this->RegisterPropertyString('port', '');
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        
        $this->SendDebug('Sink', $TimeStamp, $SenderID, $Message, $Data);

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

    public function ReceiveData($data)
    {
        $this->SendDebug('Data', $data);
    }
}