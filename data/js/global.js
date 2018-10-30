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

    // remove nickname editor from profile
    jQuery('.user-nickname-wrap,.user-display-name-wrap').css('display','none');
});

function WordCount(field, targetField) {
    string = field.value;
    str_array = string.split(" ");
    wordcount = str_array.length;
    jQuery('#' + targetField).text(wordcount);
    return wordcount;
}


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