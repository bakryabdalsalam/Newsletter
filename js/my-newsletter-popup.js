jQuery(document).ready(function($) {
    if (!localStorage.getItem('newsletterPopupShown')) {
        localStorage.setItem('newsletterPopupShown', 'true');

        // Create popup overlay with form
        var popupHtml = `
            <div id="newsletter-popup-overlay">
                <div id="newsletter-popup-content">
                    <form id="my-newsletter-form">
                        <!-- Your form fields -->
                        <label for="my_newsletter_subscribe" style="margin-bottom:10px;">
                            <input type="checkbox" name="my_newsletter_subscribe" id="my_newsletter_subscribe" value="1"> Subscribe to Newsletter
                        </label><br><br>
                        <button type="submit" class="form-button" style="width:100%;">Send</button>
                    </form>
                </div>
            </div>
        `;
        $('body').append(popupHtml);

        // Close popup on overlay click
        $('#newsletter-popup-overlay').on('click', function(e) {
            if (e.target.id === 'newsletter-popup-overlay') {
                $(this).fadeOut();
            }
        });
    }
});