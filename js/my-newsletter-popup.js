jQuery(document).ready(function($) {
    if (!localStorage.getItem('newsletterPopupShown')) {
        localStorage.setItem('newsletterPopupShown', 'true');

        // Create popup overlay
        $('body').append('<div id="newsletter-popup-overlay"></div>');

        // Display the form in the popup
        $('#newsletter-popup-overlay').append($('#my-newsletter-form').parent().html());

        // Close popup on overlay click
        $('#newsletter-popup-overlay').on('click', function(e) {
            if (e.target.id === 'newsletter-popup-overlay') {
                $(this).fadeOut();
            }
        });
    }
});