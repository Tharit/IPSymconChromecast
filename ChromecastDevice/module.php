<?php

require_once(__DIR__ . '/../libs/protobuf.php');

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

        // register for socket status notifications
        $this->RegisterMessage ( $this->InstanceID, FM_CONNECT );
		$this->RegisterMessage ( $this->InstanceID, FM_DISCONNECT );
        
        $ParentID = IPS_GetParent($this->InstanceID);
        if($ParentID > 0) {
            $this->RegisterMessage(IPS_GetParent($this->InstanceID), IM_CHANGESTATUS);
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        
        $this->SendDebug('Sink', $TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case FM_CONNECT:
                $this->RegisterMessage(IPS_GetParent($this->InstanceID), IM_CHANGESTATUS);
                break;
            case FM_DISCONNECT:
                break;
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->getCastStatus();
                }
                break;

            default:
                break;
        }
    }

    public function ReceiveData($data)
    {
        $this->SendDebug('Data', $data, 0);
    }

    private function getCastStatus() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"GET_STATUS","requestId":0}';

        $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => $c->encode()]));
    }
}