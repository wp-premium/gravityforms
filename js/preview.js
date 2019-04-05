jQuery( document ).ready(function() {

  // toggle the helper classes that show the form structure
  jQuery('.toggle_helpers input[type=checkbox]').attr('checked',false);

  jQuery('#showgrid').click(function(){
    if(jQuery(this).is(":checked")) {
      jQuery('#preview_form_container').addClass("showgrid");
    } else {
      jQuery('#preview_form_container').removeClass("showgrid");
    }
  });

  jQuery('#showme').click(function(){
    if(jQuery(this).is(":checked")) {
      jQuery('.gform_wrapper form').addClass("gf_showme");
      jQuery('#helper_legend_container').css("display", "inline-block");
    } else {
      jQuery('.gform_wrapper form').removeClass("gf_showme");
      jQuery('#helper_legend_container').css("display", "none");
    }
  });

  // dismiss the alerts and set a cookie

  if (GetCookie("dismissed-notifications")) {
    jQuery(GetCookie("dismissed-notifications")).hide();
  }
  jQuery(".hidenotice").click(function () {
    var alertId = jQuery(this).closest(".preview_notice").attr("id");
    var dismissedNotifications = GetCookie("dismissed-notifications") + ",#" + alertId;
    jQuery(this).closest(".preview_notice").slideToggle('slow');
    SetCookie("dismissed-notifications",dismissedNotifications.replace('null,',''))
  });

  // Create the cookie
  function SetCookie(sName, sValue)
  {
    document.cookie = sName + "=" + escape(sValue);
    // Expires the cookie after a month
    var date = new Date();
    date.setMonth(date.getMonth()+1);
    document.cookie += ("; expires=" + date.toUTCString());
  }

  // Retrieve the value of the cookie.
  function GetCookie(sName)
  {
    var aCookie = document.cookie.split("; ");
    for (var i=0; i < aCookie.length; i++)
    {
      var aCrumb = aCookie[i].split("=");
      if (sName == aCrumb[0])
        return unescape(aCrumb[1]);
    }
    return null;
  }

  // display the current viewport size

  jQuery('#browser_size_info').text('Viewport ( Width : '
    + jQuery(window).width() + 'px , Height :' + jQuery(window).height() + 'px )');

  jQuery(window).resize(function () {
    jQuery('#browser_size_info').text('Viewport ( Width : ' + jQuery(window).width()
      + 'px , Height :' + jQuery(window).height() + 'px )');
  });

});
