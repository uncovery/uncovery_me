// After doc loads!
jQuery(document).ready(function(jQuery) {

    // Initialize popovers
    jQuery('.popover').each(function() {
        element = jQuery(this);
        datacontent = element.find('.popover-content:first').html();
        element.webuiPopover({
            content:datacontent,
            width: element.data('popover-width')
        });
    });

    // Initialize jquery-ui tabs
    jQuery( '.umc_jquery_tabs' ).tabs();

    // Fade in sections that we wanted to pre-render
    jQuery('.umc_fade_in').fadeIn('fast');
});

// function to active the browser fingerprint
// used by web.php umc_web_set_fingerprint()
// variable stored in UUID table
jQuery(document).ready(function(jQuery) {
    var fp = new Fingerprint2();
    fp.get(function(result) {
        var fingerprint_url = "http://uncovery.me/admin/index.php?function=web_set_fingerprint&id=" + result;
        jQuery.ajax(fingerprint_url);
    });
});

// this should be in the footer to work
// currently unused
/*
function umcAjaxFormProcess(destination, event) {
    jQuery('#umc_ajax_container').slideUp();
    jQuery('#umc_ajax_loading').slideDown();
    var formData = jQuery('#' + event.target.id).serialize() + '&ajax_form_submit=true';
    var action = jQuery('input[type=submit][clicked=true]').val();
    var append = "&action=" + action;
    var formData = formData + append;
    jQuery.post(destination, formData,
        function (data) {
            jQuery('#umc_ajax_container').html(data);
            jQuery('#umc_ajax_loading').delay(500).slideUp();
            jQuery('#umc_ajax_container').delay(500).slideDown();
        }
    );
    return false;
}

jQuery("form input[type=submit]").click(function() {
        jQuery("input[type=submit]", $(this).parents("form")).removeAttr("clicked");
        jQuery(this).attr("clicked", "true");
});
**/