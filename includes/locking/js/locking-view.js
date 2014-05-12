(function (gflocking, $) {
    var objectID, objectType;

    $(document).ready( function() {

        objectID = gflockingVars.objectID;
        objectType = gflockingVars.objectType;
        gflocking.init();
    });

    gflocking.init = function () {
        initHeartbeat();
    };

    function initHeartbeat() {
        wp.heartbeat.interval( 30 );
        var checkLocksKey = 'gform-check-locked-objects-' + objectType;
        $( document ).on( 'heartbeat-tick.' + checkLocksKey, function( e, data ) {
            var locked = data[checkLocksKey] || {};

            if ( locked.hasOwnProperty( objectID ) ) {
                var lock_data = locked[objectID];
                $('.locked-text').text( lock_data.text );
                if ( lock_data.avatar_src ) {
                    var avatar = $('<img class="avatar avatar-18 photo" width="18" height="18" />').attr( 'src', lock_data.avatar_src.replace(/&amp;/g, '&') );
                    $('.locked-avatar').empty().append( avatar );
                }
            } else {
                $(".locked-info span").empty();
            }

        }).on( 'heartbeat-send.' + checkLocksKey, function( e, data ) {
                var check = [];

                check.push( objectID);

                data[checkLocksKey] = check;
            });

    }

}(window.gflocking = window.gflocking || {}, jQuery));
