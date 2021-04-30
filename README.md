# Chromecast

Folgende Module beinhaltet das Chromecast Repository:

* ChromecastDevice
* ChromecastDiscovery

# API Documentation

Tracker Data:

{
    "position" => float,
    "timestamp" => float,
    "rate" => float,
    "repeat" => string,
    "state" => string
}

RepeatMode: https://developers.google.com/cast/docs/reference/chrome/chrome.cast.media#.RepeatMode
PlayerState: https://developers.google.com/cast/docs/reference/chrome/chrome.cast.media#.PlayerState

Cast Protocol ApplicationInformation:

https://developers.google.com/cast/docs/reference/web_receiver/cast.framework.system.ApplicationData

Cast Protocol MediaInformation:

https://developers.google.com/cast/docs/reference/messages#MediaInformation


# Examples

Load a video

Chromecast_Launch("CC1AD845"); // default media receiver

Chromecast_Load([
    // Here you can plug an URL to any mp4, webm, mp3 or jpg file with the proper contentType.
    'contentId' => 'http://commondatastorage.googleapis.com/gtv-videos-bucket/big_buck_bunny_1080p.mp4',
    'contentType' => 'video/mp4',
    'streamType' => 'BUFFERED', // or LIVE

    // Title and cover displayed while buffering
    'metadata' => [
        'type' => 0,
        'metadataType' => 0,
        'title' => "Big Buck Bunny", 
        'images' => [[ "url" => 
            'http://commondatastorage.googleapis.com/gtv-videos-bucket/sample/images/BigBuckBunny.jpg' 
        ]]
    ]
])