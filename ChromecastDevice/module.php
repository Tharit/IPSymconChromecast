<?php

require_once(__DIR__ . '/../libs/protobuf.php');
require_once(__DIR__ . '/../libs/ModuleUtilities.php');

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

        // properties
        $this->RegisterPropertyString('uuid', '');
        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyString('type', '');
        $this->RegisterPropertyString('ip', '');
        $this->RegisterPropertyString('port', '');

        // variables
        $this->RegisterVariableString("ActiveApplication", "Active Application");
        $this->RegisterVariableFloat("Volume", "Volume", "~Intensity");

        // messages
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        // if this is not the initial creation there might already be a parent
        if($this->UpdateConnection() && $this->HasActiveParent()) {
            $this->Connect();
        }
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
            case FM_CONNECT:
                // if new parent and it is already active: connect immediately
                if($this->UpdateConnection() && $this->HasActiveParent()) {
                    $this->Connect();
                }
            case FM_DISCONNECT:
                $this->UpdateConnection();
                break;
            case IM_CHANGESTATUS:
                // if parent became active: connect
                if ($Data[0] === IS_ACTIVE) {
                    $this->Connect();
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

        try {
            $c = new CastMessage();
            $c->decode($data);
        } catch (Exception $e) {
            return;
        }

        if($c->payload_type === 0) {
            $data = json_decode($c->payload_utf8);
            if($data['type'] === 'RECEIVER_STATUS') {
                if(isset($data['volume'])) {
                    $this->SetValue("Volume", $data['volume']['level']);
                }
                if(isset($data['applications']) && count($data['applications']) === 1) {
                    $this->SetValue("ActiveApplication", $data['applications'][0]['displayName']);
                    $this->MUSetBuffer('TransportId', $data['applications'][0]['transportId']);
                    $this->MUSetBuffer('SessionId', $data['applications'][0]['sessionId']);
                } else {
                    $this->SetValue("ActiveApplication", "");
                    $this->MUSetBuffer('TransportId', '');
                    $this->MUSetBuffer('SessionId', '');
                }
            } else if($data['type'] === 'PING') {
                $this->Pong();
            }
            $this->SendDebug('JSON Data', $c->namespace . '|' . print_r($data, true), 0);
        } else {
            $this->SendDebug('Binary Data', print_r($c->payload_binary, true), 0);
        }
    }
    
    public function RequestAction($ident, $value)
    {
        // change volume
    }

    //------------------------------------------------------------------------------------
    // external methods
    //------------------------------------------------------------------------------------
    public function Stop() {
        $sessionId = $this->MUGetBuffer('sessionId');
        if(!$sessionId) return false;
    
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.receiver";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"STOP","requestId":' . ($this->GetRequestID()) . ',"sessionId":'.$sessionId.'}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());

        return true;
    }

    //------------------------------------------------------------------------------------
    // module internals
    //------------------------------------------------------------------------------------
    private function Pong() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.tp.heartbeat";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"PONG"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());
    }

    private function Connect() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.tp.connection";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"CONNECT"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());

        $this->RequestCastStatus();
    }

    private function RequestCastStatus() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.receiver";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"GET_STATUS", "requestId":' . ($this->GetRequestID()) . '}';

        CSCK_SendText($this->GetConnectionID(), $c->encode());
    }

    private function GetRequestID() {
        $requestId = $this->MUGetBuffer('RequestID');
        if($requestId == "") $requestId = 0;
        $requestId++;
        $this->MUSetBuffer("RequestID", $requestId);
        return $requestId;
    }
}