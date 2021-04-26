<?php

require_once(__DIR__ . '/../libs/protobuf.php');

class ChromecastDevice extends IPSModule
{
    protected $ParentID = 0;
    protected $RequestID = 0;

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

        IPS_LogMessage("Startup", "test");

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        IPS_LogMessage("ApplyChanges", "Test");
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UpdateParent();
                break;
            case FM_CONNECT: 
            case FM_DISCONNECT:
                $this->UpdateParent();
                break;
            case IM_CHANGESTATUS:
                if ($Data[0] === IS_ACTIVE) {
                    $this->connect();
                    $this->getCastStatus();
                }
                break;
            default:
                break;
        }
    }

    public function ReceiveData($data)
    {
        $data = json_decode($data);
        $data = utf8_decode($data->Buffer);

        $this->SendDebug('Data', $data, 0);
    }

    private function UpdateParent() {
        $newParentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        
        $this->LogMessage($newParentID . '|' . $this->ParentID, KL_NOTIFY);
        
        if($newParentID == $this->ParentID) return;

        if($this->ParentID) {
            $this->UnregisterMessage($this->ParentID, IM_CHANGESTATUS);
        }

        $this->ParentID = $newParentID;
        $this->RegisterMessage($this->ParentID, IM_CHANGESTATUS);
    }

    private function connect() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"CONNECT"}';
        CSCK_SendText($this->ParentID, $c->encode());
        //$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($c->encode())]));
    }

    private function getCastStatus() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"GET_STATUS", "requestId":' . ($this->RequestID++) . '}';

        CSCK_SendText($this->ParentID, $c->encode());
        //$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($c->encode())]));
    }
}