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
