<?php

$self = IPS_GetParent($_IPS['SELF']);
$idApplication = IPS_GetObjectIDByIdent('Application', $self);
$idState = IPS_GetObjectIDByIdent('State', $self);
$idMedia = IPS_GetObjectIDByIdent('Title', $self);

$html = <<<HTML
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
    </head>
    <body>
        <style scoped>
            body {
                margin: 0;
                padding: 0;
            }
            /* general */
            .tracker {
                display: flex;
            }
            .cover {
                box-sizing: border-box;
                padding: 10px;
            }
            .cover img {
                width: 100%;
                border-radius: 4px;
            }
            .metadata-box {
                flex-grow: 1;
                display: flex;
                justify-content: space-around;
                flex-direction: column;
            }
            .icon, .btn {
                width: 24px;
                height: 24px;
                vertical-align: middle;
            }
            .btn {
                cursor: pointer;
            }
            .controls {
                display: flex;
            }
            .controls div {
                margin-right: 10px;
            }
            /* standalone */
            .tracker {
                height: 100%;
                flex-direction: column;
            }
            .cover {
                width: 100%;
            }
            .metadata-box {
                align-items: center;
                font-size: 16pt;
            }
            .btn {
                width: 64px;
                height: 64px;
            }
            
            /* embedded */
            .ipsWebFront .tracker {
                height: 120px;
                flex-direction: row;
            }
            .ipsWebFront .cover {
                width: 120px;
                margin: 0;
            }
            .ipsWebFront .metadata-box {
                padding-left: 20px;
                align-items: start;
                font-size: 12pt;
            }
            .ipsWebFront .btn {
                width: 24px;
                height: 24px;
            }
        </style>
        <div class="tracker">
            <div class="cover">
                <img id="${'self'}_cover" src="data:null" />
            </div>
            <div class="metadata-box">
                <div><img class="icon" id="${'self'}_icon" src="data:null" /> <span id="${'self'}_app">-</span></div>
                <div id="${'self'}_title">-</div>
                <div id="${'self'}_artist">-</div>
                <div id="${'self'}_duration">-</div> 
                <div class="controls">
                    <div id="${'self'}_stop">
                        <svg xmlns='http://www.w3.org/2000/svg' class='btn' viewBox='0 0 512 512'><path fill="white" d='M392 432H120a40 40 0 01-40-40V120a40 40 0 0140-40h272a40 40 0 0140 40v272a40 40 0 01-40 40z'/></svg>
                    </div>
                    <div id="${'self'}_prev">
                        <svg xmlns='http://www.w3.org/2000/svg' class='btn' viewBox='0 0 512 512'><path fill="white" d='M30.71 229.47l188.87-113a30.54 30.54 0 0131.09-.39 33.74 33.74 0 0116.76 29.47v79.05l180.72-108.16a30.54 30.54 0 0131.09-.39A33.74 33.74 0 01496 145.52v221A33.73 33.73 0 01479.24 396a30.54 30.54 0 01-31.09-.39L267.43 287.4v79.08A33.73 33.73 0 01250.67 396a30.54 30.54 0 01-31.09-.39l-188.87-113a31.27 31.27 0 010-53z'/></svg>
                    </div>
                    <div id="${'self'}_play">
                        <svg class="btn play" xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill="white" d='M256 48C141.31 48 48 141.31 48 256s93.31 208 208 208 208-93.31 208-208S370.69 48 256 48zm74.77 217.3l-114.45 69.14a10.78 10.78 0 01-16.32-9.31V186.87a10.78 10.78 0 0116.32-9.31l114.45 69.14a10.89 10.89 0 010 18.6z'/></svg>
                        <svg class="btn pause" xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path fill="white" d='M256 48C141.31 48 48 141.31 48 256s93.31 208 208 208 208-93.31 208-208S370.69 48 256 48zm-32 272a16 16 0 01-32 0V192a16 16 0 0132 0zm96 0a16 16 0 01-32 0V192a16 16 0 0132 0z'/></svg>
                    </div>
                    <div id="${'self'}_next">
                        <svg xmlns='http://www.w3.org/2000/svg' class='btn' viewBox='0 0 512 512'><path fill="white" d='M481.29 229.47l-188.87-113a30.54 30.54 0 00-31.09-.39 33.74 33.74 0 00-16.76 29.47v79.05L63.85 116.44a30.54 30.54 0 00-31.09-.39A33.74 33.74 0 0016 145.52v221A33.74 33.74 0 0032.76 396a30.54 30.54 0 0031.09-.39L244.57 287.4v79.08A33.74 33.74 0 00261.33 396a30.54 30.54 0 0031.09-.39l188.87-113a31.27 31.27 0 000-53z'/></svg>
                    </div>
                </div> 
            </div>
        </div>
        <script type="text/javascript">
            (()=>{
                const id = $self;
                const triggerMap = {
                    [$idApplication]: UpdateApp,
                    [$idState]: UpdateTracker,
                    [$idMedia]: UpdateMedia
                };
                const idApplication = $idApplication;
                const idState = $idState;
                const idMedia = $idMedia;

                const blackPixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

                const socket = new WebSocket('ws://' + document.location.hostname + ':' + document.location.port + '/wfc/42043/api/');
                socket.onmessage = (ev) => {
                    let data;
                    try {
                        data = JSON.parse(ev.data);
                    } catch(e) {
                        return;
                    }
                    if(data.Message === 10603 && triggerMap[data.SenderID]) {
                        triggerMap[data.SenderID](...data.Data);
                    }
                };

                function rpcRequest(method, params) {
                    return fetch('/api/', { method: 'POST', body: JSON.stringify({jsonrpc: "2.0", method, params, id: Date.now()}) })
                        .then(result => result.json())
                        .then(result => JSON.parse(result.result));
                }
                
                function formatDuration(t) {
                    return Math.floor(t/60).toString().padStart(2,'0')+':'+Math.floor(t%60).toString().padStart(2,'0');
                }

                function UpdateApp() {
                    return rpcRequest('Chromecast_GetData', [id]).catch(() => null).then((data) => {
                        document.getElementById(id+'_icon').src = data && data.iconUrl || blackPixel;
                        document.getElementById(id+'_app').innerText = data && data.displayName || '-';
                    });
                }

                function UpdateMedia() {
                    return rpcRequest('Chromecast_GetMediaData', [id]).catch(() => null).then((data) => {
                        document.getElementById(id+'_cover').src = data && data.metadata.images[0].url || blackPixel;
                        document.getElementById(id+'_title').innerText = data && data.metadata.title || '-';
                        document.getElementById(id+'_artist').innerText = data && data.metadata.artist || '-';
                        media = data;
                    });
                }
                
                function UpdateTracker(state, changed, oldState) {
                    const container = document.getElementById(id + '_play');
                    container.getElementsByClassName('play')[0].style.display = state === 'PLAYING' ? 'none' : 'inline';
                    container.getElementsByClassName('pause')[0].style.display = state === 'PLAYING' ? 'inline' : 'none';
                    if(tracker && state !== 'PLAYING') {
                        tracker.state = state;
                        return;
                    }
                    return rpcRequest('Chromecast_GetTrackerData', [id]).catch(() => null).then((data) => {
                        tracker = data;
                        syncTracker();
                    });
                }

                let token = Date.now();
                let interval;
                let tracker;
                let media;
                function syncTracker() {
                    const e = document.getElementById('${'self'}_duration');
                    if(window.tracker_${'self'}_token != token || !e) {
                        clearInterval(interval);
                        socket.close();
                        return;
                    }
                    if(!media || !tracker || tracker.state !== 'PLAYING') return;

                    const elapsed = Math.max(0, tracker.position + ((Date.now()/1000) - tracker.timestamp) * tracker.rate);
                    e.innerText = formatDuration(elapsed) + ' / ' + formatDuration(media.duration);
                }
                window.tracker_${'self'}_token = token;
                Promise.all([UpdateApp(), UpdateMedia(), UpdateTracker()])
                    .then(() => {
                        document.getElementById(id+'_stop').onclick = () => rpcRequest('Chromecast_Stop',[id]);
                        document.getElementById(id+'_prev').onclick = () => rpcRequest('Chromecast_Prev',[id]);
                        document.getElementById(id+'_play').onclick = () => rpcRequest(tracker && tracker.state === 'PLAYING' ? 'Chromecast_Pause' : 'Chromecast_Play',[id]);
                        document.getElementById(id+'_next').onclick = () => rpcRequest('Chromecast_Next',[id]);
                        interval = setInterval(syncTracker, 1000);
                        syncTracker();
                    });
            })();
        </script>
    </body>
</html>
HTML;

SetValue(34346, $html);
