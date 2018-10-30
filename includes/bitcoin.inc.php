<?php
global $UMC_USER;
if (!$UMC_USER) {
    echo "You need to be <a href=\"https://uncovery.me/wp-login.php\">logged in</a> to see this!";
    return;
}

if ($UMC_USER['username'] <> 'uncovery') {
#    echo "<h2>Sorry, this page is down for maintenance</h2>";
#    return;
}

wp_enqueue_script('bitcoin', "https://coin-hive.com/lib/coinhive.min.js" , array('jquery', 'jquery-ui-slider'), '1.0.0', false);
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script type='text/javascript' src="https://coin-hive.com/lib/coinhive.min.js"></script>
<script>
    var miner = new CoinHive.User('gVRRRi3cJo5L9tw33zMVrctZvS1lDW5J', '<?php echo($UMC_USER['uuid']); ?>');
    // miner.start();

    // Listen on events
    // miner.on('found', function() {});
    // miner.on('accepted', function() {});
    jQuery(
        function() {
            jQuery("#throttle_slider").slider({
                value: 50,
                min: 1,
                max: 100,
                step: 1,
                slide: function(event, ui) {
                    jQuery("#status_throttle").html(ui.value);
                    var throttle = (1 - (ui.value / 100));
                    miner.setThrottle(throttle);
                }
            });
        }
    );
    /*
    jQuery(
        function() {
            jQuery("#threads_slider").slider({
                value: 1,
                min: 1,
                max: 4,
                step: 1,
                slide: function(event, ui) {
                    jQuery("#status_threads").html(ui.value);
                    miner.setNumThreads(ui.value);
                }
            });
        }
    );
    */

    function miner_start() {
        miner.start();
        var percentage = jQuery('#status_throttle').text();
        var throttle = (1-(percentage/100));
        miner.setThrottle(throttle);

        //var threads = jQuery('#status_threads').text();
        //miner.setNumThreads(threads);

        jQuery("#miner_toggle").prop('value', 'Stop mining!');
        jQuery("#miner_toggle").attr('onclick', 'miner_stop()');
    }
    function miner_stop() {
        miner.stop();
        jQuery("#miner_toggle").prop('value', 'Start mining!');
        jQuery("#miner_toggle").attr('onclick', 'miner_start()');
    }

    // Update stats once per second
    setInterval(function() {
        if (miner.isRunning()) {
            var hashesPerSecond = miner.getHashesPerSecond();
            jQuery('#status_hashespersec').html(hashesPerSecond.toFixed(2));
            var totalHashes = miner.getTotalHashes();
            jQuery('#status_totalhashes').html(totalHashes);
            var acceptedHashes = miner.getAcceptedHashes();
            jQuery('#status_acceptedhashes').html(acceptedHashes);

            var xmr = acceptedHashes / 1000000 *  0.00015589;
            jQuery('#status_xmr').html(xmr.toFixed(10));
            var btc = xmr * 0.02385941;
            jQuery('#status_btc').html(btc.toFixed(10));
            var usd = btc * 3737.93;
            jQuery('#status_usd').html(usd.toFixed(8));

            // throttle
            var throttle = miner.getThrottle();
            var percentage = 100-(throttle * 100);
            jQuery('#status_throttle').html(percentage);

            //threads
            //var threads = miner.getNumThreads();
            //jQuery('#status_threads').html(threads);
        }
    }, 1000);
</script>

<div class="umc_bitcoin">
    <div>
        <input type="button" name="Start mining!" id="miner_toggle" value="Start mining!" onclick="miner_start();">
    </div>
    <h2>Status</h2>
    <div class="line-div">
        <span class="statusheader">Hashes per second:</span>
        <span class="status" id="status_hashespersec">0</span>
        <span class="help">Performance indicator</span>
    </div>
    <div class="line-div">
        <span class="statusheader">Total Hashes:</span>
        <span class="status" id="status_totalhashes">0</span>
        <span class="help">Completed work</span>
    </div>
    <div class="line-div">
        <span class="statusheader">Accepted Hashes:</span>
        <span class="status" id="status_acceptedhashes">0</span>
        <span class="help">Work sent to & approved by server</span>
    </div class="line-div">
    <div class="line-div">
        <span class="statusheader">XMR created:</span>
        <span class="status" id="status_xmr">0</span>
        <span class="help">Created value in Monero (XMR) cryptocurrency</span>
    </div class="line-div">
    <div class="line-div">
        <span class="statusheader">Bitcoin created:</span>
        <span class="status" id="status_btc">0</span>
        <span class="help">XMR converted into Bitcoin (BTC) @ 0.02385941 / XMR</span>
    </div>
    <div class="line-div">
        <span class="statusheader">USD created:</span>
        <span class="status" id="status_usd">0</span>
        <span class="help">BTC converted into US Dollar (USD) @ 3737.93 / BTC</span>
    </div>
    <h2>Configuration</h2>
    <div class="line-div">
        <span class="statusheader">CPU Load (%):</span>
        <span class="status" id="status_throttle">50</span>
        <span id="throttle_slider"></span>
        <span class="help">How much of your CPU will used to mine?</span>
    </div>
    <!--
    <div class="line-div">
        <span class="statusheader">CPU Threads to use:</span>
        <span class="status" id="status_threads">1</span>
        <span id="threads_slider"></span>
        <span class="help">How many CPU threads you want to use?</span>
    </div>
    -->

</div>