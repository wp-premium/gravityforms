(function (gflocking, $) {
    var objectType;

    $(document).ready(function () {
        objectType = gflockingVars.objectType;
        gflocking.init();

    });

    gflocking.init = function () {
        initHeartbeat();
    };

    function initHeartbeat() {

        var checkLocksKey = 'gform-check-locked-objects-' + objectType;

        wp.heartbeat.interval( 30 );

        $(document).on('heartbeat-tick.' + checkLocksKey,function (e, data) {
            var locked = data[checkLocksKey] || {};

            $('.gf-locking').each(function (i, el) {
                var id , $row = $(el), lock_data, avatar;
                id = $row.data("id");
                if (locked.hasOwnProperty(id)) {
                    if (!$row.hasClass('wp-locked')) {
                        lock_data = locked[id];
                        $row.find('.locked-text').text(lock_data.text);
                        $row.find('.check-column input[type=checkbox]').prop('checked', false);

                        if (lock_data.avatar_src) {
                            avatar = $('<img class="avatar avatar-18 photo" width="18" height="18" />').attr('src', lock_data.avatar_src.replace(/&amp;/g, '&'));
                            $row.find('.locked-avatar').empty().append(avatar);
                        }
                        $row.addClass('wp-locked');
                    }
                } else if ($row.hasClass('wp-locked')) {
                    $row.removeClass('wp-locked').delay(1000).find('.locked-info span').empty();
                }
            });
        }).on('heartbeat-send.' + checkLocksKey, function (e, data) {
                var check = [];

                $('.gf-locking').each(function (i, row) {
                    check.push($(row).data("id"));
                });

                if (check.length)
                    data[checkLocksKey] = check;
            });

    }

}(window.gflocking = window.gflocking || {}, jQuery));
