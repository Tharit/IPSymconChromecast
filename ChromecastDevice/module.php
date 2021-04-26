<?php

require_once(__DIR__ . '/../libs/protobuf.php');

trait ModuleUtilities {
    protected function MUSetBuffer($Name, $Daten)
    {
        $this->SetBuffer($Name, serialize($Daten));
    }
    protected function MUGetBuffer($Name)
    {
        return unserialize(this->GetBuffer($Name));
    }
    protected function UpdateConnection() {
        // parent is not available until kernel finished starting
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $newParentID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $oldParentID = $this->MUGetBuffer('ConnectionID');

        $this->LogMessage($newParentID . '|' . $oldParentID, KL_NOTIFY);
        
        if($newParentID === $oldParentID) return;

        if($oldParentID) {
            $this->UnregisterMessage($oldParentID, IM_CHANGESTATUS);
        }

        $this->MUSetBuffer('ConnectionID', $newParentID);
        $this->RegisterMessage($newParentID, IM_CHANGESTATUS);
    }
    protected function GetConnectionID() {
        return $this->MUGetBuffer('ConnectionID');
    }
}

class ChromecastDevice extends IPSModule
{
    use ModuleUtilities;

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

        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        // if this is not the initial creation there might already be a parent
        $this->UpdateConnection();
    }

    /**
     * Configuration changes
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UpdateConnection();
                break;
            case FM_CONNECT:

            case FM_DISCONNECT:
                $this->UpdateConnection();
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

    private function connect() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"CONNECT"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());
        //$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($c->encode())]));
    }

    private function getCastStatus() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"GET_STATUS", "requestId":' . ($this->RequestID++) . '}';

        CSCK_SendText($this->GetConnectionID(), $c->encode());
        //$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($c->encode())]));
    }
}