<?php
$settings = $channel->get_properties();

$agoraStyle = '';
if (!empty($settings['appearance']['splashImageURL'])) {
  $agoraStyle = 'style="background-size:cover;background-position:center center; background-image:url('.$settings['appearance']['splashImageURL'].')"';
}
$buttonText = __('Watch the Live Stream', 'agoraio'); // watchButtonText
if(!empty($settings['appearance']['watchButtonText'])) {
  $buttonText = $settings['appearance']['watchButtonText'];
}
$buttonIcon = $settings['appearance']['watchButtonIcon']!=='false';

$screenStyles = '';
if (!empty($settings['appearance']['noHostImageURL'])) {
  $screenStyles = "background-size:cover; background-image: url('".$settings['appearance']['noHostImageURL']."')";
}

// $user_avatar = get_avatar_data( $settings['host'], array('size' => 168) );
?>
<div id="agora-root" class="agora agora-broadcast agora-audience">
  <section class="agora-container no-footer">
    <?php require_once "parts/header.php" ?>

    <div class="agora-content">
      <?php require_once "parts/header-controls.php" ?>

      <div id="screen-zone" class="screen" <?php echo $agoraStyle ?>>
        <div id="screen-users" class="screen-users screen-users-1">
          <div id="full-screen-video" class="user" style="display: none; <?php echo $screenStyles; ?>"></div>

          <div id="watch-live-overlay" class="overlay user">
            <div id="overlay-container">
              <button id="watch-live-btn" type="button" class="room-title">
                <?php if($buttonIcon) { ?>
                  <i id="watch-live-icon" class="fas fa-broadcast-tower"></i>
                <?php } ?>
                <span><?php echo $buttonText ?></span>
              </button>
            </div>
          </div>
          <div id="watch-live-closed" class="overlay user" style="display: none">
            <div id="overlay-container">
              <div id="finished-btn" class="room-title">
                <?php if($buttonIcon) { ?>
                  <i id="watch-live-icon" class="fas fa-broadcast-tower"></i>
                <?php } ?>
                <span id="txt-finished" style="display:none"><?php _e('The Live Stream has finished', 'agoraio'); ?></span>
                <span id="txt-waiting"><?php _e('Waiting for broadcast connection...', 'agoraio'); ?></span>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <?php
    if (isset($agora->settings['agora-chat']) && $agora->settings['agora-chat']==='enabled') {
      require_once('parts/chat-fab.php');
    }  ?>

  </section>

  <?php require_once "parts/scripts-common.php" ?>
  <script>
    window.agoraCurrentRole = 'audience';
    window.agoraMode = 'audience';
    window.remoteStreams = {};
    var WAIT_FOR_RECONNECT_TIMEOUT = 15000; // 10 Seconds!


    window.addEventListener('load', function() {
      // set log level:
      // -- .DEBUG for dev 
      // -- .NONE for prod
      AgoraRTC.Logger.enableLogUpload();
      window.agoraLogLevel = window.location.href.indexOf('local')>0 ? AgoraRTC.Logger.ERROR : AgoraRTC.Logger.ERROR;
      AgoraRTC.Logger.setLogLevel(window.agoraLogLevel);
      
      // create client, vp8 to work across mobile devices
      window.agoraClient = AgoraRTC.createClient({mode: 'live', codec: 'vp8'});

      window.AGORA_RTM_UTILS.setupRTM(window.agoraAppId, window.channelName);

      jQuery('#fullscreen-expand').click(window.AGORA_UTILS.toggleFullscreen);

      const exitBtn = jQuery('#exit-btn')
      exitBtn.hide();
      exitBtn.click(function() {
        Object.values(window.remoteStreams).forEach(stream => stream.close());
        window.remoteStreams = {};
        finishVideoScreen();
      })

      function finishVideoScreen() {
        jQuery(".remote-stream-container").hide();
        jQuery("#full-screen-video").hide();
        jQuery("#watch-live-closed").show();

        function waitUntilClose() {
          jQuery('#txt-waiting').hide();
          jQuery('#txt-finished').show();

          agoraLeaveChannel();
        }
        exitBtn.hide();
        window.waitingClose = setTimeout(waitUntilClose, WAIT_FOR_RECONNECT_TIMEOUT)
      }
      
      // Due to broswer restrictions on auto-playing video, 
      // user must click to init and join channel
      jQuery("#watch-live-btn").click(function(){
        AgoraRTC.Logger.info("user clicked to watch broadcast");

        // init Agora SDK
        window.agoraClient.init(agoraAppId, function () {
          jQuery("#watch-live-overlay").remove();
          jQuery("#screen-zone").css('background', 'none')
          jQuery("#full-screen-video").fadeIn();
          AgoraRTC.Logger.info('AgoraRTC client initialized');
          agoraJoinChannel(); // join channel upon successfull init
        }, function (err) {
          AgoraRTC.Logger.error('[ERROR] : AgoraRTC client init failed', err);
        });
      });

      window.agoraClient.on('stream-published', function (evt) {
        AgoraRTC.Logger.info('Publish local stream successfully');
      });

      // connect remote streams
      window.agoraClient.on('stream-added', function addStream(evt) {
        const stream = evt.stream;
        const streamId = stream.getId();
        window.remoteStreams[streamId] = stream;
        if (window.waitingClose) {
          clearTimeout(window.waitingClose)
          window.waitingClose = null;
        }

        AgoraRTC.Logger.info('New stream added: ' + streamId);
        AgoraRTC.Logger.info('Subscribing to remote stream:' + streamId);

        const chatBtn = document.querySelector('#chatToggleBtn');
        if (chatBtn) {
          chatBtn.style.display = "block";
        }

        jQuery("#watch-live-closed").hide();
        jQuery("#watch-live-overlay").hide();
        jQuery("#full-screen-video").css('background', 'none').fadeIn();
        exitBtn.show();

        // Subscribe to the stream.
        window.agoraClient.subscribe(stream, function (err) {
          AgoraRTC.Logger.error('[ERROR] : subscribe stream failed', err);
        });
      });

      window.agoraClient.on('stream-removed', function closeStream(evt) {
        const stream = evt.stream;
        const streamId = stream.getId();
        stream.stop(); // stop the stream
        stream.close(); // clean up and close the camera stream

        window.remoteStreams[streamId] = null;
        delete window.remoteStreams[streamId];

        AgoraRTC.Logger.warning("Remote stream is removed " + stream.getId());
      });

      window.agoraClient.on('stream-subscribed', function (evt) {
        const remoteStream = evt.stream;
        const streamId = remoteStream.getId();
        AgoraRTC.Logger.info('Successfully subscribed to remote stream: ' + streamId);

        jQuery('#full-screen-video').hide();

        if (window.screenshareClients[streamId]) {
          // this is a screen share stream:
          console.log('Screen stream arrived:');
          window.AGORA_SCREENSHARE_UTILS.addRemoteScreenshare(remoteStream);
        } else {
          // show new stream on screen:
          window.AGORA_UTILS.addRemoteStreamView(remoteStream);

          const usersCount = Object.keys(window.remoteStreams).length;
          window.AGORA_UTILS.updateUsersCounter(usersCount);
        }
      });

      // remove the remote-container when a user leaves the channel
      window.agoraClient.on('peer-leave', function(evt) {
        AgoraRTC.Logger.info('Remote stream has left the channel: ' + evt.uid);
        
        if (!evt || !evt.stream) {
          console.error('Stream undefined cannot be removed', evt);
          return false;
        }

        const streamId = evt.stream.getId(); // the the stream id
        evt.stream.isPlaying() && evt.stream.stop(); // stop the stream
  
        if(window.remoteStreams[streamId] !== undefined) {
          window.remoteStreams[streamId].isPlaying() && window.remoteStreams[streamId].stop(); //stop playing the feed

          delete window.remoteStreams[streamId]; // remove stream from list
          const remoteContainerID = '#' + streamId + '_container';
          jQuery(remoteContainerID).empty().remove();

          const usersCount = Object.keys(window.remoteStreams).length;
          window.AGORA_UTILS.updateUsersCounter(usersCount);
        }

        if (window.screenshareClients[streamId]) {
          if (typeof window.screenshareClients[streamId].stop==='function') {
            window.screenshareClients[streamId].isPlaying() && window.screenshareClients[streamId].stop();
          }
          const remoteContainerID = '#' + streamId + '_container';
          jQuery(remoteContainerID).empty().remove();
          const streamsContainer = jQuery('#screen-zone');
          streamsContainer.toggleClass('sharescreen');
          delete window.screenshareClients[streamId];
        } else {
          const usersCount = Object.keys(window.remoteStreams).length;
          if (usersCount===0) {
            const chatBtn = document.querySelector('#chatToggleBtn');
            if (chatBtn) {
              chatBtn.style.display = "none";
            }

            finishVideoScreen();
          }
        }
      });


      // show mute icon whenever a remote has muted their mic
      window.agoraClient.on("mute-audio", function (evt) {
        window.AGORA_UTILS.toggleVisibility('#' + evt.uid + '_mute', true);
      });

      window.agoraClient.on("unmute-audio", function (evt) {
        window.AGORA_UTILS.toggleVisibility('#' + evt.uid + '_mute', false);
      });

      // show user icon whenever a remote has disabled their video
      window.agoraClient.on("mute-video", function (evt) {
        window.AGORA_UTILS.toggleVisibility('#' + evt.uid + '_no-video', true);
      });

      window.agoraClient.on("unmute-video", function (evt) {
        window.AGORA_UTILS.toggleVisibility('#' + evt.uid + '_no-video', false);
      });

      // ingested live stream 
      window.agoraClient.on('streamInjectedStatus', function (evt) {
        AgoraRTC.Logger.info("Injected Steram Status Updated");
        // evt.stream.play('full-screen-video');
        AgoraRTC.Logger.info(JSON.stringify(evt));
      }); 
    });

    // join a channel
    function agoraJoinChannel() {
      const token = window.AGORA_TOKEN_UTILS.agoraGenerateToken();

      // set the role
      window.agoraClient.setClientRole('audience', function() {
        AgoraRTC.Logger.info('Client role set to audience');
      }, function(e) {
        AgoraRTC.Logger.error('setClientRole failed', e);
      });
      
      window.agoraClient.join(token, window.channelName, window.userID, function(uid) {
          AgoraRTC.Logger.info('User ' + uid + ' join channel successfully');
          window.AGORA_RTM_UTILS.joinChannel(uid, function(err){
            if (err) {
              console.error(err)
            }
          });
      }, function(err) {
          AgoraRTC.Logger.error('[ERROR] : join channel failed', err);
      });
    }

    function agoraLeaveChannel() {
      window.dispatchEvent(new CustomEvent("agora.leavingChannel"));
      window.agoraClient.leave(function() {
        AgoraRTC.Logger.info('client leaves channel');
        window.AGORA_RTM_UTILS.leaveChannel();

        window.dispatchEvent(new CustomEvent("agora.leavedChannel"));
      }, function(err) {
        AgoraRTC.Logger.error('client leave failed ', err); //error handling
      });
    }
  </script>
</div>