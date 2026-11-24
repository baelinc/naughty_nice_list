/* Santa's List - Admin JS */
jQuery(function ($) {
    // Handle single entry actions from URL params
    var params = new URLSearchParams(window.location.search);
    var action = params.get('action');
    var id     = params.get('id');
    var nonce  = params.get('_wpnonce');

    if (action && id && nonce) {
        // These are handled server-side via URL; JS just confirms clear_all
    }

    // Clear all confirmation is in HTML onclick — nothing else needed here
});
