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

        $this->RequireParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}'); // IO Client Socket

        // properties
        $this->RegisterPropertyString('uuid', '');
        $this->RegisterPropertyString('name', '');
        $this->RegisterPropertyString('type', '');
        $this->RegisterPropertyString('ip', '');
        $this->RegisterPropertyString('port', '');

        // variables
        $this->RegisterVariableString("Application", "Application");
        $this->RegisterVariableString("State", "State");
        $this->RegisterVariableString("Title", "Title");
        $this->RegisterVariableFloat("Volume", "Volume", "~Intensity.1");
        $this->EnableAction("Volume");

        // messages
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);

        // clear state on startup
        $this->ResetState();

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
                // reset state
                $this->ResetState();

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
            // heartbeat
            if($c->namespace === 'urn:x-cast:com.google.cast.tp.heartbeat') {
                if($data->type === 'PING') {
                    $this->Pong();
                }
            // receiver
            } else if($c->namespace === 'urn:x-cast:com.google.cast.receiver') {
                if($data->type === 'RECEIVER_STATUS') {
                    $oldApplication = $this->MUGetBuffer('Application');

                    if(isset($data->status->volume)) {
                        $level = $data->status->volume->level;
                        if($level != $this->GetValue("Volume")) {
                            $this->SetValue("Volume", $level);
                        }
                    }

                    if(isset($data->status->applications) && count($data->status->applications) === 1) {
                        $application = $data->status->applications[0];

                        $applicationDidChange = false;
                        if(!is_object($oldApplication) || $oldApplication->appId != $application->appId) {
                            $applicationDidChange = true;
                            $this->ResetState();
                            $this->SetValue("Application", $application->displayName);
                            $this->MUSetBuffer("Application", $application);
                        }

                        $oldSessionId = $this->MUGetBuffer('SessionId');
                        $newSessionId = $application->sessionId;
                        if($oldSessionId != $newSessionId) {
                            // new session in same app?
                            if(!$applicationDidChange) {
                                $this->ResetState(false, true);
                            }
                            $this->MUSetBuffer('TransportId', $application->transportId);
                            $this->MUSetBuffer('SessionId', $newSessionId);

                            $supportsMediaNS = false;
                            foreach($application->namespaces as $namespace) {
                                if($namespace->name === 'urn:x-cast:com.google.cast.media') {
                                    $supportsMediaNS = true;
                                    break;
                                }
                            }
                            if($supportsMediaNS) {
                                $this->connect($newSessionId);
                                $this->SendMediaCommand("GET_STATUS");
                            }
                        }
                    } else if(is_object($oldApplication)) {
                        $this->ResetState();
                    }
                }
            // media
            } else if($c->namespace === 'urn:x-cast:com.google.cast.media') {
                if($data->type === 'MEDIA_STATUS' && count($data->status) >= 1) {
                    $status = $data->status[0];

                    $this->MUSetBuffer('MediaSessionId', $status->mediaSessionId);

                    // media information is only sent if it changed
                    if(isset($status->media)) {
                        $media = $status->media;
                        $oldMedia = $this->MUGetBuffer('Media');

                        if(!is_object($oldMedia) || $oldMedia->contentId != $media->contentId) {
                            $this->MUSetBuffer('Media', $media);
                            $newTitle = $media->metadata->title;
                            if(isset($media->metadata->artist)) {
                                $newTitle .= ' • ' . $media->metadata->artist;
                            }
                            $this->SetValue("Title", $newTitle);
                        }
                    }

                    // state is updated everytime we receive a message, because it acts as trigger for the tracker
                    $this->SetValue("State", $status->playerState);
                    
                    $this->MUSetBuffer('Tracker', [
                        "position" => $status->currentTime,
                        "timestamp" => microtime(true),
                        "rate" => $status->playbackRate,
                        "repeat" => $status->repeatMode,
                        "state" => $status->playerState
                    ]);
                }
            }

            $this->SendDebug('JSON Data', $c->namespace . ' | ' . print_r($data, true), 0);
        } else {
            $this->SendDebug('Binary Data', print_r($c->payload_binary, true), 0);
        }
    }
    
    public function RequestAction($ident, $value)
    {
        $this->SendDebug('Action', $ident, 0);
        if($ident === 'Volume') {
            $this->SetVolume($value);
        }
    }

    //------------------------------------------------------------------------------------
    // external methods
    //------------------------------------------------------------------------------------
    public function GetData() {
        $data = $this->MUGetBuffer('Application');
        return json_encode(empty($data) ? null : $data);
    }

    public function GetTrackerData() {
        $data = $this->MUGetBuffer('Tracker');
        return json_encode(empty($data) ? null : $data);
    }

    public function GetMediaData() {
        $data = $this->MUGetBuffer('Media');
        return json_encode(empty($data) ? null : $data);
    }

    public function Stop() {
        $sessionId = $this->MUGetBuffer('SessionId');
        if(!$sessionId) return false;
    
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.receiver";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"STOP","requestId":' . ($this->GetRequestID()) . ',"sessionId":"'.$sessionId.'"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());

        return true;
    }

    public function Play() {
        $mediaSessionId = $this->MUGetBuffer('MediaSessionId');
        if(empty($mediaSessionId)) return false;

        $this->SendMediaCommand('PLAY', $mediaSessionId);
        
        return true;
    }

    public function Pause() {
        $mediaSessionId = $this->MUGetBuffer('MediaSessionId');
        if(empty($mediaSessionId)) return false;

        $this->SendMediaCommand('PAUSE', $mediaSessionId);
        
        return true;
    }

    public function Next() {
        $mediaSessionId = $this->MUGetBuffer('MediaSessionId');
        if(empty($mediaSessionId)) return false;

        $this->SendMediaCommand('QUEUE_NEXT', $mediaSessionId);
        
        return true;
    }

    public function Prev() {
        $mediaSessionId = $this->MUGetBuffer('MediaSessionId');
        if(empty($mediaSessionId)) return false;

        $this->SendMediaCommand('QUEUE_PREV', $mediaSessionId);
        
        return true;
    }

    //------------------------------------------------------------------------------------
    // module internals
    //------------------------------------------------------------------------------------
    private function FormatDuration($seconds) {
        return str_pad(floor($seconds / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad(floor($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    private function ResetState($application = true, $media = true) {
        // reset app state
        if($application) {
            $this->SetValue("Application", '');
            $this->MUSetBuffer('Application', '');
            $this->MUSetBuffer('SessionId', '');
            $this->MUSetBuffer('TransportId', '');
        }
        if($media) {
            $this->SetValue("Title", '');
            $this->SetValue("State", '');
            $this->MUSetBuffer("Tracker", '');
            $this->MUSetBuffer('Media', '');
            $this->MUSetBuffer('MediaSessionId', '');
        }
    }

    private function SetVolume($volume) {
        $volume = max(min(1, $volume), 0);
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.receiver";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"SET_VOLUME", "volume":{"level":'.$volume.'},"requestId":' . ($this->GetRequestID()) . '}';

        CSCK_SendText($this->GetConnectionID(), $c->encode());
    }

    private function SendMediaCommand($command, $mediaSessionId = "") {
        // media session id must be present for all commands except GET_STATUS
        if(empty($mediaSessionId) && $command !== "GET_STATUS") {
            return;
        }
    
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = $this->MUGetBuffer('SessionId');
		$c->namespace = "urn:x-cast:com.google.cast.media";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"'.$command.'", ';
        if(!empty($mediaSessionId)) {
            $c->payload_utf8 .= '"mediaSessionId":' . $mediaSessionId . ', ';
        }
        $c->payload_utf8 .= '"requestId":' . ($this->GetRequestID()) . '}';

        CSCK_SendText($this->GetConnectionID(), $c->encode());
    }
    
    private function Pong() {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = "receiver-0";
		$c->namespace = "urn:x-cast:com.google.cast.tp.heartbeat";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"PONG"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());
    }

    private function Connect($destination_id = "") {
        $c = new CastMessage();
		$c->source_id = "sender-0";
		$c->destination_id = empty($destination_id) ? "receiver-0" : $destination_id;
		$c->namespace = "urn:x-cast:com.google.cast.tp.connection";
		$c->payload_type = 0;
		$c->payload_utf8 = '{"type":"CONNECT"}';
        CSCK_SendText($this->GetConnectionID(), $c->encode());

        $this->RequestStatus();
    }

    private function RequestStatus() {
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