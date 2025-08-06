/**
 * BR-DGE Checkout JavaScript
 * Handles payment form integration with BR-DGE SDK
 */

jQuery(document).ready(function($) {
    'use strict';

    // Check if BR-DGE parameters are available.
    if (typeof wc_brdge_params === 'undefined') {
        console.error('BR-DGE: Parameters not loaded');
        return;
    }

    // Check if this is hosted fields integration
    if (!wc_brdge_params.client_api_key) {
        // This is likely hosted payment page integration, no SDK needed
        return;
    }

    let comcardeClient = null;
    let hostedFields = null;
    let isFormValid = false;

    // Initialize BR-DGE when payment method is selected.
    function initializeBRDGE() {
        if (!wc_brdge_params.client_api_key) {
            console.error('BR-DGE: Client API key not configured');
            return;
        }

        // Create BR-DGE client.
        comcarde.client.create({
            authorization: wc_brdge_params.client_api_key
        }, function(clientErr, clientInstance) {
            if (clientErr) {
                console.error('BR-DGE Client Error:', clientErr);
                displayError('Failed to initialize payment form. Please refresh and try again.');
                return;
            }

            comcardeClient = clientInstance;
            setupHostedFields();
        });
    }

    // Setup hosted fields for secure card input.
    function setupHostedFields() {
        if (!comcardeClient) {
            return;
        }

        comcarde.hostedFields.create({
            client: comcardeClient,
            fields: {
                number: {
                    selector: '#brdge-card-number',
                    placeholder: '1234 5678 9012 3456'
                },
                cvv: {
                    selector: '#brdge-card-cvv',
                    placeholder: '123'
                },
                expirationDate: {
                    selector: '#brdge-card-expiry',
                    placeholder: 'MM/YY'
                }
            },
            styles: {
                'input': {
                    'font-size': '14px',
                    'font-family': 'inherit',
                    'color': '#333'
                },
                'input:focus': {
                    'color': '#333'
                },
                'input.invalid': {
                    'color': '#e74c3c'
                }
            }
        }, function(hostedFieldsErr, hostedFieldsInstance) {
            if (hostedFieldsErr) {
                console.error('BR-DGE Hosted Fields Error:', hostedFieldsErr);
                displayError('Payment form setup failed. Please refresh and try again.');
                return;
            }

            hostedFields = hostedFieldsInstance;
            setupHostedFieldsEventListeners();
        });
    }

    // Setup event listeners for hosted fields.
    function setupHostedFieldsEventListeners() {
        if (!hostedFields) {
            return;
        }

        hostedFields.on('validityChange', function(event) {
            const field = event.fields[event.emittedBy];
            const fieldElement = $('#brdge-card-' + event.emittedBy + '-container');

            if (field.isValid) {
                fieldElement.removeClass('invalid');
            } else if (field.isPotentiallyValid) {
                fieldElement.removeClass('invalid');
            } else {
                fieldElement.addClass('invalid');
            }

            // Check if form is valid
            isFormValid = Object.keys(event.fields).every(function(field) {
                return event.fields[field].isValid;
            });

            // Update checkout button state
            updateCheckoutButton();
        });

        hostedFields.on('cardTypeChange', function(event) {
            // Update card icon based on detected card type.
            const cardType = event.cards.length === 1 ? event.cards[0].type : '';
            updateCardIcon(cardType);
        });

        hostedFields.on('focus', function(event) {
            $('#brdge-card-' + event.emittedBy + '-container').addClass('focused');
        });

        hostedFields.on('blur', function(event) {
            $('#brdge-card-' + event.emittedBy + '-container').removeClass('focused');
        });
    }

    // Update card icon based on card type.
    function updateCardIcon(cardType) {
        const iconElement = $('#brdge-card-icon');
        iconElement.removeClass().addClass('brdge-card-icon');

        if (cardType) {
            iconElement.addClass('brdge-card-' + cardType);
        }
    }

    // Update checkout button state.
    function updateCheckoutButton() {
        const checkoutButton = $('#place_order');

        if ($('input[name="payment_method"]:checked').val() === 'brdge') {
            // Only disable button if we have hosted fields and they're invalid
            if (hostedFields && !isFormValid) {
                checkoutButton.prop('disabled', true);
            } else {
                checkoutButton.prop('disabled', false);
            }
        }
    }

    // Tokenize payment method.
    function tokenizePayment(callback) {
        if (!hostedFields) {
            callback(new Error('Payment form not initialized'));
            return;
        }

        hostedFields.tokenize(function(tokenizeErr, payload) {
            if (tokenizeErr) {
                callback(tokenizeErr);
                return;
            }

            callback(null, payload.nonce);
        });
    }

    // Display error message.
    function displayError(message) {
        const errorElement = $('#brdge-card-errors');
        errorElement.html('<div class="woocommerce-error">' + message + '</div>');

        // Scroll to error
        $('html, body').animate({
            scrollTop: errorElement.offset().top - 100
        }, 500);
    }

    // Clear error messages.
    function clearErrors() {
        $('#brdge-card-errors').empty();
    }

    // Create hosted fields HTML structure
    function createHostedFieldsHTML() {
        const cardElement = $('#brdge-card-element');

        if (cardElement.length === 0) {
            return;
        }

        const fieldsHTML = `
            <div class="brdge-field-container">
                <label for="brdge-card-number">Card Number</label>
                <div id="brdge-card-number-container" class="brdge-field">
                    <div id="brdge-card-number"></div>
                    <div id="brdge-card-icon" class="brdge-card-icon"></div>
                </div>
            </div>
            <div class="brdge-field-row">
                <div class="brdge-field-container brdge-field-half">
                    <label for="brdge-card-expiry">Expiry Date</label>
                    <div id="brdge-card-expiry-container" class="brdge-field">
                        <div id="brdge-card-expiry"></div>
                    </div>
                </div>
                <div class="brdge-field-container brdge-field-half">
                    <label for="brdge-card-cvv">CVV</label>
                    <div id="brdge-card-cvv-container" class="brdge-field">
                        <div id="brdge-card-cvv"></div>
                    </div>
                </div>
            </div>
        `;

        cardElement.html(fieldsHTML);
    }

    // Check if the current payment method selection has hosted fields
    function hasHostedFields() {
        return $('#brdge-card-element').length > 0 && $('#brdge-card-element').children().length > 0;
    }

    // Handle payment method change
    $(document).on('change', 'input[name="payment_method"]', function() {
        if ($(this).val() === 'brdge') {
            // Check if this gateway instance uses hosted fields
            if ($('#brdge-card-element').length > 0) {
                createHostedFieldsHTML();

                // Initialize BR-DGE with a small delay to ensure DOM is ready
                setTimeout(function() {
                    initializeBRDGE();
                }, 100);
            }
        } else {
            // Clean up BR-DGE instances
            if (hostedFields) {
                hostedFields.teardown();
                hostedFields = null;
            }
            comcardeClient = null;
            isFormValid = false;
        }
    });

    // Handle form submission.
    $(document).on('submit', 'form.woocommerce-checkout', function(e) {
        if ($('input[name="payment_method"]:checked').val() !== 'brdge') {
            return true;
        }

        // If this is hosted page integration (no hosted fields), proceed normally
        if (!hasHostedFields()) {
            return true;
        }

        // Check if we already have a token
        if ($('#brdge-payment-token').val()) {
            return true;
        }

        e.preventDefault();
        clearErrors();

        // Show loading state.
        const submitButton = $('#place_order');
        const originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Processing...');

        // Tokenize the payment method
        tokenizePayment(function(err, token) {
            if (err) {
                console.error('BR-DGE Tokenization Error:', err);
                displayError('Payment processing failed. Please check your card details and try again.');
                submitButton.prop('disabled', false).text(originalText);
                return;
            }

            // Set the token and resubmit the form.
            $('#brdge-payment-token').val(token);

            // Remove this event handler to prevent infinite loop.
            $('form.woocommerce-checkout').off('submit.brdge');

            // Resubmit the form.
            $('form.woocommerce-checkout').submit();
        });

        return false;
    });

    // Handle checkout form updates.
    $(document.body).on('updated_checkout', function() {
        // Reinitialize if BR-DGE is selected and has hosted fields
        if ($('input[name="payment_method"]:checked').val() === 'brdge') {
            if ($('#brdge-card-element').length > 0) {
                createHostedFieldsHTML();
                setTimeout(function() {
                    initializeBRDGE();
                }, 100);
            }
        }
    });

    // Initialize on page load if BR-DGE is preselected and has hosted fields.
    if ($('input[name="payment_method"][value="brdge"]').is(':checked')) {
        if ($('#brdge-card-element').length > 0) {
            createHostedFieldsHTML();
            setTimeout(function() {
                initializeBRDGE();
            }, 100);
        }
    }
});