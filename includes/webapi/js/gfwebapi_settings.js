function gfapiCalculateSig(stringToSign, privateKey) {
    var hash = CryptoJS.HmacSHA1(stringToSign, privateKey);
    var base64 = hash.toString(CryptoJS.enc.Base64);
    return encodeURIComponent(base64);
}


function gfapiToggleSettings(enabled) {
    jQuery("#gaddon-setting-row-public_key").toggle(enabled);
    jQuery("#gaddon-setting-row-private_key").toggle(enabled);
    jQuery("#gaddon-setting-row-impersonate_account").toggle(enabled);
    jQuery("#gaddon-setting-row-developer_tools").toggle(enabled);
    jQuery("#gaddon-setting-row-qrcode").toggle(enabled);
}

jQuery(document).ready(function () {

    var enabled = jQuery("#enabled").prop("checked");
    gfapiToggleSettings(enabled);

    jQuery("#gfwebapi-qrbutton").click(function () {
        jQuery("#gfwebapi-qrcode-container").toggle();
        var $img = jQuery('#gfwebapi-qrcode');
        if ($img.length > 0)
            $img.attr('src', ajaxurl + '?action=gfwebapi_qrcode&rnd=' + Date.now());

        return false;
    });

    jQuery("#public_key, #private_key").on("keyup", function () {
        jQuery("#gfwebapi-qrcode-container").html("The keys have changes. Please save the changes and try again.")
    })

    jQuery("#gfapi-url-builder-button").click(function (e) {
        e.preventDefault();
        var publicKey, privateKey, expiration, method, route, stringToSign, url, sig;
        publicKey = jQuery("#public_key").val();
        privateKey = jQuery("#private_key").val();
        expiration = parseInt(jQuery("#gfapi-url-builder-expiration").val());
        method = jQuery("#gfapi-url-builder-method").val();
        route = jQuery("#gfapi-url-builder-route").val();
        route = route.replace(/\/$/, ""); // remove trailing slash
        var d = new Date;
        var unixtime = parseInt(d.getTime() / 1000);
        var future_unixtime = unixtime + expiration;

        stringToSign = publicKey + ":" + method + ":" + route + ":" + future_unixtime;
        sig = gfapiCalculateSig(stringToSign, privateKey);
        url = gfapiBaseUrl + "/" + route + "/?api_key=" + publicKey + "&signature=" + sig + "&expires=" + future_unixtime;
        jQuery('#gfapi-url-builder-generated-url').val(url);
        return false;
    });
    var gfapiTesterAjaxRequest;
    jQuery("#gfapi-url-tester-button").click(function (e) {
        var $button = jQuery(this);
        var $loading = jQuery("#gfapi-url-tester-loading");
        var $results = jQuery("#gfapi-url-tester-results");
        var url = jQuery('#gfapi-url-tester-url').val();
        var method = jQuery('#gfapi-url-tester-method').val();
        gfapiTesterAjaxRequest = jQuery.ajax({
            url       : url + "&test=1",
            type      : method,
            dataType  : 'json',
            data      : {},
            beforeSend: function (xhr, opts) {
                $button.attr('disabled', 'disabled');
                $loading.show();
            }
        })
            .done(function (data, textStatus, xhr) {
                $button.removeAttr('disabled');
                $loading.hide();
                $results.html(xhr.status);
                $results.fadeTo("fast", 1);
            })
            .fail(function (jqXHR) {

                $button.removeAttr('disabled');
                $loading.hide();
                $results.fadeTo("fast", 1);
                var msg;
                $loading.hide();
                if (msg == "abort") {
                    msg = "Request cancelled";
                } else {
                    msg = jqXHR.status + ": " + jqXHR.statusText;
                }
                $results.html(msg);
            });
        return false;
    });

})
