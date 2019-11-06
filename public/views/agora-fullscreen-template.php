<?php $current_path = plugins_url('wp-agora-io') . '/public'; ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Agora.io Communication Chat</title>
  <?php wp_head() ?>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css">
  <link rel="stylesheet" href="<?php echo $current_path ?>/css/wp-agora-fullscreen.css">
</head>
<body class="agora custom-background-image">
  <div class="agora-fullscreen-container controls-bottom window-mode gradient-x">

    <div class="main-video-screen" id="full-screen-video">
      <div id="video-canvas"></div>

      <div id="buttons-container" class="row justify-content-center mt-3">
        <div class="col-md-2 text-center control-btn">
          <button id="mic-btn" type="button" class="btn btn-block btn-dark btn-xs">
            <i id="mic-icon" class="fas fa-microphone"></i>
          </button>
        </div>
        <div class="col-md-2 text-center control-btn main-btn">
          <button id="exit-btn"  type="button" class="btn btn-block btn-danger btn-xs">
            <i id="exit-icon" class="fas fa-phone-slash"></i>
          </button>
        </div>
        <div class="col-md-2 text-center control-btn">
          <button id="video-btn"  type="button" class="btn btn-block btn-dark btn-xs">
            <i id="video-icon" class="fas fa-video"></i>
          </button>
        </div>
      </div>

    </div>

    <div class="audience-container" id="audience-avatars">
      <div class="avatar-circle local" id="local-stream-container">
        <div id="mute-overlay" class="col">
          <i id="mic-icon" class="fas fa-microphone-slash"></i>
        </div>
        <div id="no-local-video" class="col text-center">
          <i id="user-icon" class="fas fa-user"></i>
        </div>
        <div id="local-video" class="col"></div>
      </div>

      <div class="remote-users">
        <div class="slick-avatars">
          <div>
            <div class="avatar-circle">
              <img src="" alt="">
            </div>
          </div>
          <div>
            <div class="avatar-circle">
              <img src="" alt="">
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
  <?php wp_footer(); ?>
  <script>
    // video profile settings
    window.cameraVideoProfile = '<?php echo $instance['videoprofile'] ?>'; // 640x480 @ 30fps & 750kbs
    window.screenVideoProfile = '<?php echo $instance['screenprofile'] ?>';
    window.addEventListener('load', function() {
      window.agoraAppId = '<?php echo $agora->settings['appId'] ?>'; // set app id
      window.channelName = '<?php echo $channel->title() ?>'; // set channel name
      window.channelId = '<?php echo $channel->id() ?>'; // set channel name
      window.userID = <?php echo $current_user->ID; ?>;
      window.agoraMode = 'communication';

      const resizeVideo = function(firstTime) {
        const size = calculateVideoScreenSize();
        const sliderSize = size.width - 200;
        
        if (!firstTime) {
          // jQuery('.slick-avatars').slick('breakpoint')
        } else {
          jQuery('.remote-users').outerWidth(sliderSize);
        }
        return size;
      }

      const size = resizeVideo(true);
      jQuery(window).smartresize(resizeVideo);

      const sliderSize = size.width - 200;
      const slidesToShow = Math.floor(sliderSize / 110);
      
      jQuery('.slick-avatars').slick({
        dots: false,
        slidesToShow,
        responsive: [{
          breakpoint: 480,
          settings: {slidesToShow: 1}
        }, {
          breakpoint: 600,
          settings: {slidesToShow: 2}
        }, {
          breakpoint: 1024,
          settings: {slidesToShow: 3}
        }, {
          breakpoint: 1280,
          settings: {slidesToShow: 4}
        }, {
          breakpoint: 1440,
          settings: {slidesToShow: 5}
        }]
      });
      // jQuery('.slick-avatars').on('breakpoint', function(event, slick, breakpoint) {
      //   console.log('breakpoint:', breakpoint)
      // })
      initClientAndJoinChannel(window.agoraAppId, window.channelName);
    });

    function rejoinChannel() {
      var thisBtn = jQuery(this);
      if(!thisBtn.prop('disabled')) {
        joinChannel('<?php echo $channel->title() ?>');
        thisBtn.prop("disabled", true);
        thisBtn.find('.spinner-border').show();
      }
    }


    // use tokens for added security
    function generateToken() {
      return <?php
      $appID = $agora->settings['appId'];
      $appCertificate = $agora->settings['appCertificate'];
      $current_user = wp_get_current_user();

      if($appCertificate && strlen($appCertificate)>0) {
        $channelName = $channel->title();
        $uid = 0; // $current_user->ID; // Get urrent user id

        // role should be based on the current user host...
        $settings = $channel->get_properties();
        $role = 'Role_Subscriber';
        $privilegeExpireTs = 0;
        if(!class_exists('RtcTokenBuilder')) {
          require_once(__DIR__.'/../../includes/token-server/RtcTokenBuilder.php');
        }
        echo '"'.RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $uid, $role, $privilegeExpireTs). '"';
      } else {
        echo 'null';
      }
      ?>;
    }
  </script>
  <script src="<?php echo $current_path ?>/js/agora-communication-client.js"></script>
  <script src="<?php echo $current_path ?>/js/communication-ui.js"></script>
</body>
</html>