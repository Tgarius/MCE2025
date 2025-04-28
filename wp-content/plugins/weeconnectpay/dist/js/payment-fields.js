"use strict";
class ListenerInitializationError extends Error {
    constructor(message) {
        super(message);
        this.name = "ListenerInitializationError";
    }
}
class GoogleRecaptchaTokenizationError extends Error {
    constructor(message) {
        super(message);
        this.name = "GoogleRecaptchaTokenizationError";
    }
}
// eslint-disable-next-line @typescript-eslint/no-unused-vars
document.addEventListener("DOMContentLoaded", function () {
    let WrapperElementId;
    (function (WrapperElementId) {
        WrapperElementId["NUMBER"] = "weeconnectpay-card-number";
        WrapperElementId["DATE"] = "weeconnectpay-card-date";
        WrapperElementId["CVV"] = "weeconnectpay-card-cvv";
        WrapperElementId["ZIP"] = "weeconnectpay-card-postal-code";
        WrapperElementId["PAYMENT_REQUEST_BUTTON"] = "weeconnectpay-payment-request-button";
    })(WrapperElementId || (WrapperElementId = {}));
    let ErrorDisplayElementId;
    (function (ErrorDisplayElementId) {
        ErrorDisplayElementId["NUMBER"] = "weeconnectpay-card-number-errors";
        ErrorDisplayElementId["DATE"] = "weeconnectpay-card-date-errors";
        ErrorDisplayElementId["CVV"] = "weeconnectpay-card-cvv-errors";
        ErrorDisplayElementId["ZIP"] = "weeconnectpay-card-postal-code-errors";
        ErrorDisplayElementId["PAYMENT_REQUEST_BUTTON"] = "weeconnectpay-payment-request-button-errors";
    })(ErrorDisplayElementId || (ErrorDisplayElementId = {}));
    class Settings {
        constructor(pakms, locale, cartTotal, siteKey) {
            this.pakms = pakms;
            this.locale = locale;
            this.cartTotal = cartTotal;
            this.siteKey = siteKey ?? '';
        }
    }
    /*    type LocalizedPaymentScriptData = {
            pakms: string,
            locale: string,
            cartTotal: number,
            siteKey?: string,
        }
    
        type CloverOptions = {
            locale?: string,
            merchantId?: string,
            showSecurePayments?: boolean,
            showPrivacyPolicy?: boolean,
        }*/
    let CloverElementType;
    (function (CloverElementType) {
        CloverElementType["CARD"] = "CARD";
        CloverElementType["CVV"] = "CARD_CVV";
        CloverElementType["DATE"] = "CARD_DATE";
        CloverElementType["NUMBER"] = "CARD_NUMBER";
        CloverElementType["POSTAL_CODE"] = "CARD_POSTAL_CODE";
        CloverElementType["STREET_ADDRESS"] = "CARD_STREET_ADDRESS";
        CloverElementType["PAYMENT_REQUEST_BUTTON"] = "PAYMENT_REQUEST_BUTTON";
    })(CloverElementType || (CloverElementType = {}));
    // Styling
    let cvvInputPlaceHolder = {};
    if (window.WeeConnectPayPaymentFieldsData.locale === 'fr-CA') {
        // fr-CA wraps the line, so we need to push it higher if it takes more than 105px
        const cvvEl = document.getElementById(WrapperElementId.CVV);
        if (cvvEl) {
            if (cvvEl.offsetWidth >= 106 || cvvEl.offsetWidth === 0) {
                cvvInputPlaceHolder = {
                    whiteSpace: 'pre-line',
                    position: 'relative',
                };
            }
            else {
                cvvInputPlaceHolder = {
                    whiteSpace: 'pre-line',
                    position: 'relative',
                    top: '-7px',
                };
            }
        }
    }
    else {
        // We don't need to wrap up the line higher since it sits on one line
        cvvInputPlaceHolder = {};
    }
    // For browser security, the scope of the CSS is limited to the instance of the element created inside the iframe.
    const styles = {
        'card-number input': {
            border: '1px #C8C8C8 solid',
            borderRadius: '3px',
            textIndent: '1em',
        },
        'card-date input': {
            border: '1px #C8C8C8 solid',
            borderRadius: '3px',
            textAlign: 'center'
        },
        'card-cvv input': {
            border: '1px #C8C8C8 solid',
            borderRadius: '3px',
            textAlign: 'center'
        },
        'card-postal-code input': {
            border: '1px #C8C8C8 solid',
            borderRadius: '3px',
            textAlign: 'center'
        },
        'input': {
            // Fixes for https://community.clover.com/questions/24714/issue-in-clover-hosted-iframe-application-running.html
            padding: '0px',
            margin: '0px',
            height: '3.4em',
            width: '100%',
        },
        '::-webkit-input-placeholder': {
            textAlign: 'center',
        },
        '::-moz-placeholder': {
            textAlign: 'center',
        },
        ':-ms-input-placeholder': {
            textAlign: 'center',
        },
        ':-moz-placeholder': {
            textAlign: 'center',
        },
        'card-number input::-webkit-input-placeholder': {
            textAlign: 'inherit',
        },
        'card-number input::-moz-placeholder': {
            textAlign: 'inherit',
        },
        'card-number input:-ms-input-placeholder': {
            textAlign: 'inherit',
        },
        'card-number input:-moz-placeholder': {
            textAlign: 'inherit',
        },
        'card-cvv input::-webkit-input-placeholder': cvvInputPlaceHolder, /* Chrome/Opera/Safari */
        'card-cvv input::-moz-placeholder': cvvInputPlaceHolder, /* Firefox 19+ */
        'card-cvv input:-ms-input-placeholder': cvvInputPlaceHolder, /* IE 10+ */
        'card-cvv input:-moz-placeholder': cvvInputPlaceHolder, /* Firefox 18- */
    };
    const convertSettings = () => {
        const pakms = window.WeeConnectPayPaymentFieldsData.pakms;
        const locale = window.WeeConnectPayPaymentFieldsData.locale;
        const amount = window.WeeConnectPayPaymentFieldsData.amount ?? 0;
        const siteKey = window.WeeConnectPayPaymentFieldsData.siteKey;
        return new Settings(pakms, locale, amount, siteKey);
    };
    const settings = convertSettings();
    const clover = new window.Clover(settings.pakms);
    // @ts-expect-error Clover SDK window global -- We need it as a global to use in WooCommerce Blocks react (Unless it can be done differently)
    window.wcpClover = clover;
    clover.options = {
        locale: settings.locale,
        // merchantId: "SK36FZYP2ZKJ1",
        // showSecurePayments: false,
        // showPrivacyPolicy:  false,
    };
    // Sample payment amount -- Google Pay support
    const paymentReqData = {
        paymentReqData: {
            total: {
                label: 'Online purchase via Clover using WeeConnectPay',
                currency: "CAD",
                amount: settings.cartTotal ?? 0, // getting the total from the cart when generating the payment_fields
            },
            // Default buttonType is 'long' for button with card brand & last 4 digits
            options: {
                button: {
                    buttonType: 'short' // For button without additional text
                }
            }
        }
    };
    const tokenRateLimiter = {
        timestamps: []
    };
    function canCallCreateToken() {
        const now = Date.now();
        const windowSize = 5000; // 5 seconds in milliseconds
        const maxCalls = 2;
        // Remove timestamps older than the 5-second window
        tokenRateLimiter.timestamps = tokenRateLimiter.timestamps.filter((timestamp) => now - timestamp < windowSize);
        if (tokenRateLimiter.timestamps.length < maxCalls) {
            // Allow the call and record the timestamp
            tokenRateLimiter.timestamps.push(now);
            return true;
        }
        else {
            // Rate limit exceeded
            return false;
        }
    }
    /////
    // CLOVER SDK TOKENIZATION FAILSAFE ENDS
    /////
    let cardNumber;
    let cardDate;
    let cardCvv;
    let cardPostalCode;
    let paymentRequestButton;
    const WCPForm = {
        createElements: function () {
            const elements = clover.elements();
            cardNumber = elements.create(CloverElementType.NUMBER, styles);
            cardDate = elements.create(CloverElementType.DATE, styles);
            cardCvv = elements.create(CloverElementType.CVV, styles);
            cardPostalCode = elements.create(CloverElementType.POSTAL_CODE, styles);
            try {
                paymentRequestButton = elements.create(CloverElementType.PAYMENT_REQUEST_BUTTON, paymentReqData);
            }
            catch (e) {
                // Google Pay separator and button
                const googlePaySeparatorDiv = document.getElementById('weeconnectpay-separator-with-text');
                const googlePayButtonDiv = document.getElementById('weeconnectpay-payment-request-button');
                console.error('Google Pay paymentRequest button error:', e);
                // Make sure to hide the 2 elements we use for Google Pay if it crashes
                if (googlePayButtonDiv) {
                    googlePayButtonDiv.classList.add('wcp-google-pay-error');
                    // googlePayButtonDiv.style.display = "none";
                }
                if (googlePaySeparatorDiv) {
                    googlePaySeparatorDiv.classList.add('wcp-google-pay-error');
                    // googlePaySeparatorDiv.style.display = "none";
                }
            }
        }
    };
    const mountFields = (cardNumber, cardDate, cardCvv, cardPostalCode, paymentRequestButton) => {
        cardNumber.mount('#' + WrapperElementId.NUMBER);
        cardDate.mount('#' + WrapperElementId.DATE);
        cardCvv.mount('#' + WrapperElementId.CVV);
        cardPostalCode.mount('#' + WrapperElementId.ZIP);
        if (paymentRequestButton) {
            paymentRequestButton.mount('#' + WrapperElementId.PAYMENT_REQUEST_BUTTON);
        }
    };
    // Client Side Validation
    /*
        const cardResponse = document.getElementById('card-response');
        const displayCardNumberError = document.getElementById('card-number-errors');
        const displayCardDateError = document.getElementById('card-date-errors');
        const displayCardCvvError = document.getElementById('card-cvv-errors');
        const displayCardPostalCodeError = document.getElementById('card-postal-code-errors');
    */
    const checkoutForm = document.querySelector('form.checkout');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    const orderPayForm = document.querySelector('form#order_review');
    // CloverInputEvent
    const addError = (wrapperElement, errorDisplayElement, errorText) => {
        errorDisplayElement.textContent = errorText;
        errorDisplayElement.classList.add('error');
        wrapperElement.classList.remove('success');
        wrapperElement.classList.add('error');
    };
    const removeError = (wrapperElement, errorDisplayElement) => {
        errorDisplayElement.textContent = null;
        errorDisplayElement.classList.remove('error');
        wrapperElement.classList.remove('error');
        wrapperElement.classList.add('success');
    };
    const state = {
        cloverTokenReady: false,
        cardBrandSaved: false,
        zipVerified: false,
        recaptchaCompleted: false,
        isGooglePayActive: false
    };
    const errorState = {
        CARD_NUMBER: { error: false, touched: false },
        CARD_DATE: { error: false, touched: false },
        CARD_CVV: { error: false, touched: false },
        CARD_POSTAL_CODE: { error: false, touched: false },
    };
    // Update error and touch state
    function updateFieldState(fieldKey, hasError, isTouched) {
        const fieldState = errorState[fieldKey];
        // Determine if the state has changed
        const errorChanged = fieldState.error !== hasError;
        const touchedChanged = isTouched !== undefined && fieldState.touched !== isTouched;
        // Only update and log if there is a change
        if (errorChanged || touchedChanged) {
            fieldState.error = hasError;
            // Update touched only if isTouched is provided
            if (isTouched !== undefined) {
                fieldState.touched = isTouched;
            }
            // Log the updated state (for debugging)
            // console.log(`State updated for ${fieldKey}:`, { error: hasError, touched: fieldState.touched });
            // console.log("Current Error State:", errorState);
        }
    }
    // Check if all fields have been touched and are error-free + dynamic data for the gateway properly initialized
    function canSubmit() {
        // If using Google Pay, only check the required flags
        if (state.isGooglePayActive) {
            return state.cloverTokenReady &&
                state.cardBrandSaved &&
                state.zipVerified &&
                state.recaptchaCompleted;
        }
        // Otherwise, check both iframe fields and required flags
        const allFieldsTouchedAndNoErrors = Object.values(errorState).every(state => state.touched && !state.error);
        const allFlagsTrue = state.cloverTokenReady &&
            state.cardBrandSaved &&
            state.zipVerified &&
            state.recaptchaCompleted;
        return allFieldsTouchedAndNoErrors && allFlagsTrue;
    }
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const maybeExecuteGoogleRecaptcha = (formToSubmitOnceDone, state) => {
        if (typeof grecaptcha !== "undefined" && settings.siteKey !== '') {
            grecaptcha.ready(function () {
                try {
                    grecaptcha.execute(settings.siteKey, { action: 'submit' }).then(function (token) {
                        saveRecaptchaTokenToForm(token);
                        state.recaptchaCompleted = true;
                        formToSubmitOnceDone.trigger('submit');
                    }, function (response) {
                        console.error('Google reCAPTCHA error: ', response);
                        const exception = response;
                        console.error('Google reCAPTCHA exception: ', exception);
                        const exceptionJson = {
                            exception: exception.toString()
                        };
                        const jsonStringException = JSON.stringify(exceptionJson);
                        // Set the exception text in the token so that we can check for it in the payment processing part
                        saveRecaptchaTokenToForm(jsonStringException);
                        state.recaptchaCompleted = true;
                        formToSubmitOnceDone.trigger('submit');
                    });
                }
                catch (e) {
                    const exception = e;
                    console.error('Google reCAPTCHA exception: ', exception);
                    const exceptionJson = {
                        exception: exception.toString()
                    };
                    const jsonStringException = JSON.stringify(exceptionJson);
                    // Set the exception text in the token so that we can check for it in the payment processing part
                    saveRecaptchaTokenToForm(jsonStringException);
                    state.recaptchaCompleted = true;
                    formToSubmitOnceDone.trigger('submit');
                }
            });
        }
        else {
            state.recaptchaCompleted = true; // Mark as complete if reCAPTCHA is not enabled
            formToSubmitOnceDone.trigger('submit');
        }
    };
    let CloverIframeEventProperties;
    (function (CloverIframeEventProperties) {
        CloverIframeEventProperties["NUMBER"] = "CARD_NUMBER";
        CloverIframeEventProperties["DATE"] = "CARD_DATE";
        CloverIframeEventProperties["CVV"] = "CARD_CVV";
        CloverIframeEventProperties["ZIP"] = "CARD_POSTAL_CODE";
        // Add other common identifiers and corresponding event properties here
    })(CloverIframeEventProperties || (CloverIframeEventProperties = {}));
    function resolveValueForMemberKey(memberKey) {
        const eventResponseKey = CloverIframeEventProperties[memberKey];
        const wrapperElementId = WrapperElementId[memberKey];
        const errorElementId = ErrorDisplayElementId[memberKey];
        return { CloverIframeEventProperties: eventResponseKey, WrapperElementId: wrapperElementId, ErrorDisplayElementId: errorElementId };
    }
    const addIframeEventListener = (cloverElement, eventType, enumsMemberKey) => {
        // console.log(`Registering event type "${eventType}" for enumsMemberKey ${enumsMemberKey} on CloverElement:`, cloverElement)
        // Handle real-time validation errors from the card element
        cloverElement.addEventListener(eventType, function (event) {
            const cloverIframeEvent = event;
            const displayErrorId = resolveValueForMemberKey(enumsMemberKey).ErrorDisplayElementId;
            const displayError = document.getElementById(displayErrorId);
            const wrapperElementId = resolveValueForMemberKey(enumsMemberKey).WrapperElementId;
            const wrapperElement = document.getElementById(wrapperElementId);
            const cloverIframeEventMember = resolveValueForMemberKey(enumsMemberKey).CloverIframeEventProperties;
            // console.log("Event fired: cloverIframeEventMember ",cloverIframeEventMember)
            // console.log("Event fired: displayError element: ", displayError)
            // console.log("Event fired: wrapperElement element: ", wrapperElement)
            // console.log("Event fired: cloverIframeEvent object: ", cloverIframeEvent)
            if (displayError && wrapperElement) {
                if (cloverIframeEvent && cloverIframeEvent[cloverIframeEventMember]) {
                    const error = cloverIframeEvent[cloverIframeEventMember]?.error;
                    if (error !== undefined) {
                        addError(wrapperElement, displayError, error);
                        updateFieldState(cloverIframeEventMember, true, true);
                    }
                    else {
                        removeError(wrapperElement, displayError);
                        updateFieldState(cloverIframeEventMember, false, true);
                    }
                }
                else {
                    removeError(wrapperElement, displayError);
                    updateFieldState(cloverIframeEventMember, false, true); // Mark touched when no error data
                }
            }
        });
    };
    const initListeners = (checkoutForm, paymentMethod, orderPayForm) => {
        addIframeEventListener(cardNumber, 'change', "NUMBER");
        addIframeEventListener(cardNumber, 'blur', "NUMBER");
        addIframeEventListener(cardDate, 'change', "DATE");
        addIframeEventListener(cardDate, 'blur', "DATE");
        addIframeEventListener(cardCvv, 'change', "CVV");
        addIframeEventListener(cardCvv, 'blur', "CVV");
        addIframeEventListener(cardPostalCode, 'change', "ZIP");
        addIframeEventListener(cardPostalCode, 'blur', "ZIP");
        // Handle validation errors after tokenization
        paymentRequestButton?.addEventListener('paymentMethod', function (tokenDataEvent) {
            const tokenData = tokenDataEvent;
            const jQueryCheckoutForm = jQuery('form.checkout');
            const cardBrand = tokenData.customer?.billingInfo?.cardNetwork ?? '';
            console.log('Google Pay tokenData:', {
                token: tokenData.token,
                cardNetwork: tokenData.customer?.billingInfo?.cardNetwork,
                cardDetails: tokenData.customer?.billingInfo?.cardDetails,
                email: tokenData.customer?.email,
                billingAddress: tokenData.customer?.billingInfo?.billingAddress
            });
            state.isGooglePayActive = true;
            cloverTokenHandler(tokenData.token);
            saveCardBrandToForm(cardBrand);
            // Save additional card data if available
            if (tokenData.customer?.billingInfo?.cardDetails) {
                // Google Pay typically provides last4 in cardDetails
                saveCardLast4ToForm(tokenData.customer.billingInfo.cardDetails);
            }
            cloverTokenizedDataVerificationHandler(tokenData);
            maybeExecuteGoogleRecaptcha(jQueryCheckoutForm, state);
        });
        // Payment processing
        if (checkoutForm) {
            const jQueryCheckoutForm = jQuery('form.checkout');
            jQueryCheckoutForm.on('checkout_place_order', function (event) {
                if (canSubmit()) {
                    return true;
                }
                else {
                    event.preventDefault();
                }
                const jQueryPaymentMethod = jQuery('input[name="payment_method"]:checked').val();
                if (jQueryPaymentMethod === 'weeconnectpay') {
                    // TEMPORARY RATE LIMIT FAILSAFE FOR CLOVER SDK
                    if (canCallCreateToken()) {
                        // Use the iframe's tokenization method with the user-entered card details
                        clover.createToken()
                            .then(function (tokenDataEvent) {
                            const result = tokenDataEvent;
                            // console.log('Clover tokenization result: ', result);
                            if (result.errors) {
                                handleTokenCreationErrors(result);
                            }
                            else if (result.token) {
                                cloverTokenHandler(result.token);
                                const cardBrand = result.card?.brand ?? '';
                                const expMonth = result.card?.exp_month ?? '';
                                const expYear = result.card?.exp_year ?? '';
                                const last4 = result.card?.last4 ?? '';
                                saveCardBrandToForm(cardBrand);
                                saveCardLast4ToForm(last4);
                                saveCardExpMonthToForm(expMonth);
                                saveCardExpYearToForm(expYear);
                                cloverTokenizedDataVerificationHandler(result);
                                maybeExecuteGoogleRecaptcha(jQueryCheckoutForm, state);
                            }
                            else {
                                throw new Error('Something went wrong tokenizing the card. Payment will not be processed.');
                            }
                        });
                    }
                    else {
                        const result = {
                            errors: {
                                CARD_NUMBER: "Rate Limit Exceeded! Try again in 5 seconds."
                            }
                        };
                        handleTokenCreationErrors(result);
                        console.warn('Rate limit exceeded: clover.createToken() not called.');
                        return false;
                    }
                }
                else {
                    return true;
                }
                return false;
            });
        }
        else if (orderPayForm) {
            const jQueryOrderPayForm = jQuery('form#order_review');
            jQueryOrderPayForm.on('submit', function (event) {
                if (canSubmit()) {
                    return true;
                }
                else {
                    event.preventDefault();
                }
                const jQueryPaymentMethod = jQuery('input[name="payment_method"]:checked').val();
                if (jQueryPaymentMethod === 'weeconnectpay') {
                    // TEMPORARY RATE LIMIT FAILSAFE FOR CLOVER SDK
                    if (canCallCreateToken()) {
                        clover.createToken()
                            .then(function (tokenDataEvent) {
                            const result = tokenDataEvent;
                            if (result.errors) {
                                handleTokenCreationErrors(result);
                            }
                            else if (result.token) {
                                cloverTokenHandler(result.token);
                                const cardBrand = result.card?.brand ?? '';
                                const expMonth = result.card?.exp_month ?? '';
                                const expYear = result.card?.exp_year ?? '';
                                const last4 = result.card?.last4 ?? '';
                                saveCardBrandToForm(cardBrand);
                                saveCardLast4ToForm(last4);
                                saveCardExpMonthToForm(expMonth);
                                saveCardExpYearToForm(expYear);
                                cloverTokenizedDataVerificationHandler(result);
                                maybeExecuteGoogleRecaptcha(jQueryOrderPayForm, state);
                            }
                            else {
                                throw new Error('Something went wrong tokenizing the card. Payment will not be processed.');
                            }
                        });
                    }
                    else {
                        const result = {
                            errors: {
                                CARD_NUMBER: "Rate Limit Exceeded! Try again in 5 seconds."
                            }
                        };
                        handleTokenCreationErrors(result);
                        console.warn('Rate limit exceeded: clover.createToken() not called.');
                    }
                }
                else {
                    return true;
                }
                return false;
            });
        }
        else {
            throw new Error('Could not find an appropriate payment form.');
        }
    };
    const handleTokenCreationErrors = (result) => {
        console.log('result: ', result);
        if (result.errors) {
            // Number
            if (result.errors.CARD_NUMBER) {
                const displayNumberError = document.getElementById(ErrorDisplayElementId.NUMBER);
                const wrapperNumberElement = document.getElementById(WrapperElementId.NUMBER);
                if (displayNumberError && wrapperNumberElement) {
                    addError(wrapperNumberElement, displayNumberError, result.errors.CARD_NUMBER);
                    updateFieldState("CARD_NUMBER", true); // Set error and touched to true
                }
            }
            // Date
            if (result.errors.CARD_DATE) {
                const displayDateError = document.getElementById(ErrorDisplayElementId.DATE);
                const wrapperDateElement = document.getElementById(WrapperElementId.DATE);
                if (displayDateError && wrapperDateElement) {
                    addError(wrapperDateElement, displayDateError, result.errors.CARD_DATE);
                    updateFieldState("CARD_DATE", true);
                }
            }
            // CVV
            if (result.errors.CARD_CVV) {
                const displayCvvError = document.getElementById(ErrorDisplayElementId.CVV);
                const wrapperCvvElement = document.getElementById(WrapperElementId.CVV);
                if (displayCvvError && wrapperCvvElement) {
                    addError(wrapperCvvElement, displayCvvError, result.errors.CARD_CVV);
                    updateFieldState("CARD_CVV", true);
                }
            }
            // POSTAL_CODE
            if (result.errors.CARD_POSTAL_CODE) {
                const displayPostalCodeError = document.getElementById(ErrorDisplayElementId.ZIP);
                const wrapperPostalCodeElement = document.getElementById(WrapperElementId.ZIP);
                if (displayPostalCodeError && wrapperPostalCodeElement) {
                    addError(wrapperPostalCodeElement, displayPostalCodeError, result.errors.CARD_POSTAL_CODE);
                    updateFieldState("CARD_POSTAL_CODE", true);
                }
            }
        }
    };
    function cloverTokenHandler(token) {
        // console.log('in cloverTokenHandler. token: ',token)
        // Insert the token ID into the form, so it gets submitted to the server
        const hiddenInput = document.querySelector('#wcp-token');
        if (!hiddenInput) {
            throw new Error('Token field is missing!');
        }
        if (hiddenInput /*.value === ''*/) {
            hiddenInput.value = token;
            state.cloverTokenReady = true;
            return false;
        }
        else {
            return true;
        }
    }
    function extractPostalCodeOrAddressZip(response) {
        if ("customer" in response && response.customer?.billingInfo?.billingAddress?.postalCode) {
            // It's the Google Pay response and postalCode is present
            return response.customer.billingInfo.billingAddress.postalCode;
        }
        else if ("card" in response && response.card?.address_zip) {
            // It's the Clover iframe response and address_zip is present
            return response.card.address_zip;
        }
        else {
            // Either the response format is unexpected or postalCode/address_zip is missing
            return undefined;
        }
    }
    function cloverTokenizedDataVerificationHandler(cloverTokenizationResponse) {
        // Extract the information from the different tokenization methods;
        const zipCode = extractPostalCodeOrAddressZip(cloverTokenizationResponse);
        // Insert the tokenized data to compare (IE: Postal / Zip code) into the form, so it gets submitted to the server
        const hiddenZipInput = document.querySelector('#wcp-tokenized-zip');
        if (!hiddenZipInput) {
            throw new Error('Tokenized data: postal / zip code field is missing!');
        }
        if (hiddenZipInput.value === '' && zipCode) {
            hiddenZipInput.value = zipCode;
            state.zipVerified = true;
            return;
        }
    }
    function saveRecaptchaTokenToForm(token) {
        // Insert the reCAPTCHA token into the form, so it gets submitted to the server
        const hiddenRecaptchaInput = document.querySelector('#wcp-recaptcha-token');
        if (!hiddenRecaptchaInput) {
            throw new Error('WeeConnectPay could not find the recaptcha element in the page!');
        }
        if (token && token.length > 0) {
            hiddenRecaptchaInput.value = token;
            return;
        }
        else {
            throw new Error('WeeConnectPay did not receive a valid recaptcha token!');
        }
    }
    function saveCardBrandToForm(brand) {
        // Insert the reCAPTCHA brand into the form, so it gets submitted to the server
        const cardBrandInput = document.querySelector('#wcp-card-brand');
        if (!cardBrandInput) {
            throw new Error('WeeConnectPay could not find the card brand element in the page!');
        }
        /** I am not sure if we will always be getting a brand, so it defaults to an empty string
         * We can check for it when we display it and react accordingly
         */
        if (brand && brand === '') {
            state.cardBrandSaved = true;
            cardBrandInput.value = brand;
            return;
        }
        else if (brand && brand.length > 0) {
            state.cardBrandSaved = true;
            cardBrandInput.value = brand;
            return;
        }
        else {
            console.error('WeeConnectPay did not receive a valid card brand!');
        }
    }
    // Function to save the last 4 digits of the card number to the form
    function saveCardLast4ToForm(last4Digits) {
        // console.debug('Saving card last 4 digits to form: ', last4Digits);
        const cardLast4Input = document.querySelector('#wcp-card-last4');
        if (!cardLast4Input) {
            throw new Error('WeeConnectPay could not find the card last 4 digits element in the page!');
        }
        // Ensure the input consists of exactly 4 digits
        if (last4Digits && last4Digits.length === 4 && /^\d{4}$/.test(last4Digits)) {
            cardLast4Input.value = last4Digits;
        }
        else {
            console.error('WeeConnectPay did not receive valid card last 4 digits!');
        }
    }
    // Function to save the expiration month to the form
    function saveCardExpMonthToForm(expMonth) {
        // console.debug('saveCardExpMonthToForm: ', expMonth);
        const cardExpMonthInput = document.querySelector('#wcp-card-exp-month');
        if (!cardExpMonthInput) {
            throw new Error('WeeConnectPay could not find the card expiration month element in the page!');
        }
        if (expMonth && expMonth.match(/^(0?[1-9]|1[0-2])$/)) {
            cardExpMonthInput.value = expMonth;
        }
        else {
            console.error('WeeConnectPay did not receive a valid card expiration month!');
        }
    }
    // Function to save the expiration year to the form
    function saveCardExpYearToForm(expYear) {
        // console.debug('saveCardExpYearToForm: ', expYear);
        const cardExpYearInput = document.querySelector('#wcp-card-exp-year');
        if (!cardExpYearInput) {
            throw new Error('WeeConnectPay could not find the card expiration year element in the page!');
        }
        if (expYear && expYear.match(/^\d{4}$/)) {
            cardExpYearInput.value = expYear;
        }
        else {
            console.error('WeeConnectPay did not receive a valid card expiration year!');
        }
    }
    const createMountAndInit = function (withPaymentRequestButton = true) {
        const cardWrapperElement = document.getElementById(WrapperElementId.NUMBER);
        if (!cardWrapperElement) {
            console.error('Could not find the element to insert the iframe into.');
            return;
        }
        if (cardWrapperElement.children.length) {
            // Iframe is already loaded
            console.error('Iframe is already loaded.');
            return;
        }
        WCPForm.createElements();
        if (!withPaymentRequestButton) {
            paymentRequestButton = null;
        }
        mountFields(cardNumber, cardDate, cardCvv, cardPostalCode, paymentRequestButton);
        try {
            initListeners(checkoutForm, paymentMethod, orderPayForm);
        }
        catch (e) {
            const error = e;
            if (error.message) {
                console.log(error.message);
            }
        }
    };
    if (checkoutForm) {
        // Because WooCommerce is a special little snowflake that doesn't care about not using outdated libraries
        jQuery(document.body).on('updated_checkout', function () {
            createMountAndInit();
        });
    }
    else {
        if (jQuery("form#add_payment_method").length || jQuery("form#order_review").length) {
            createMountAndInit();
        }
    }
});
