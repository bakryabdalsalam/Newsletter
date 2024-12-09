jQuery(document).ready(function($) {
    if (!localStorage.getItem('newsletterPopupShown')) {
        localStorage.setItem('newsletterPopupShown', 'true');
        $('#newsletter-popup-overlay').fadeIn();
    }

    $('#newsletter-popup-close').on('click', function() {
        $('#newsletter-popup-overlay').fadeOut();
    });

    $('#newsletter-popup-overlay').on('click', function(e) {
        if (e.target.id === 'newsletter-popup-overlay') {
            $(this).fadeOut();
        }
    });
});
