(function ($, window, document) {
    'use strict'

    var env
    var userKey

    function getRemoteElementQueryString(elementId) {
        let url = document.getElementById(elementId).src
        let queryString = url.replace(/^.*?\?/, '')
        let params = queryString.split('&')
        for (let i in params) {
            let pair = params[i].split('=')
            if (pair[0] === 'env') {
                env = pair[1]
            }
            if (pair[0] === 'userKey') {
                userKey = pair[1]
            }
        }
    }

    // Get query string for this script
    getRemoteElementQueryString('weeconnectpay-iframe-communicator')


    var iframeHost

    switch (env) {
    case 'local':
    case 'development':
        // Do dev stuff
        iframeHost = 'https://weeconnect-api.test'
        break
    case 'staging':
        // Do staging stuff
        iframeHost = 'https://apidev.weeconnectpay.com'
        break
    case 'production':
    default:
        // Do production stuff
        iframeHost = 'https://api.weeconnectpay.com'
    }


    const iframeSource = iframeHost + '/v1/clover/order/create-and-pay/iframe?userKey=' + userKey


    // Create the iframe
    var weeconnectpayCheckoutIframe = document.createElement('iframe')
    weeconnectpayCheckoutIframe.setAttribute('src', iframeSource)
    weeconnectpayCheckoutIframe.setAttribute('id', 'weeconnectpay-wp-plugin-iframe')
    weeconnectpayCheckoutIframe.style.width = 450 + 'px'
    weeconnectpayCheckoutIframe.style.height = 200 + 'px'
    weeconnectpayCheckoutIframe.style.border = 'none'

    document.getElementById('weeconnectpay-iframe').appendChild(weeconnectpayCheckoutIframe)

    // Send a message to the child iframe
    var sendMessage = function (msg) {
        document.getElementById('weeconnectpay-wp-plugin-iframe').contentWindow.postMessage(msg, iframeHost)
    }

    // Checks for hash change sent by process_payment
    bindEvent(
        window,
        'hashchange',
        function () {
            let partials = window.location.hash.match(/^#?wee-process-(payment):([^:]+.*):([^:]+.*)$/)

            if (!partials || 3 > partials.length) {
                return
            }

            let type = partials[1]
            let clientSecret = partials[2]
            let functionToCall = partials[3]

            // Cleanup the URL if it's not already an empty hash ( to prevent recursive events from firing )
            if (window.location.hash !== '') {
                window.location.hash = ''
            }

            if (type === 'payment' && functionToCall === 'checkoutEvent') {
                let payload = {'secure_uuid': clientSecret}
                sendMessage(payload)
            }
        }
    )

    let ccFormRealTimeState

    // Listen to message from child window
    bindEvent(
        window,
        'message',
        function (e) {

            var data

            if (typeof (e.data) === 'string') {
                data = JSON.parse(e.data)
            } else {
                data = e.data
            }


            if (data.weeAction === true) {
                if (!data.functionToInvoke) {
                }
                var dynamicFunction = data.functionToInvoke

                /** @dynamicFunctionParams Object containing the param names and their values,
				 * so we don't have to blindly use params, and will help us be backwards compatible with minimumPluginVersion.
				 **/
                var dynamicFunctionParams = data.functionParams
                wc_weeconnectpay_form[(dynamicFunction)](dynamicFunctionParams)
            }


        }
    )

    // addEventListener support for IE8
    function bindEvent(element, eventName, eventHandler) {
        if (element.addEventListener) {
            element.addEventListener(eventName, eventHandler, false)
        } else if (element.attachEvent) {
            element.attachEvent('on' + eventName, eventHandler)
        }
    }

    let ccFormState
    var wc_weeconnectpay_form = {
        checkoutFinishedRedirect: function (paramsObject) {
            if (!paramsObject.redirectUrl) {
                return
            }
            var redirectUrl = paramsObject.redirectUrl
            window.location = redirectUrl
        },
        updateCCFormState: function (paramsObject) {
            if(!paramsObject.realTimeCCFormState){
                return
            }
            let state = paramsObject.realTimeCCFormState
            ccFormState = state
            // state (and this whole event from message received/function) is not set at all until the user touches a field
        }


    }

    // Something to keep the last state ( the error elements ) and compare with the new state
    let lastSubmitStateErrors = []

    function createErrorsFromFormState(state){
        let errors = []
        // Reset error array as we just push to it without validating it's in here already
        lastSubmitStateErrors = []

        if(!state){
            // Set form unused HTML error
            let selectorId = 'weeconnectpay-unused-form-input'
            lastSubmitStateErrors.push(selectorId)
            errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_empty_cc_form" value="1">`)
            return errors
        }
        // NUMBER
        if(state.CARD_NUMBER){
            if (state.CARD_NUMBER.error){
                let selectorId = 'weeconnectpay-card-number-error-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_card_number_error_cc_form" value="1">`)
            }
            if (!state.CARD_NUMBER.touched){
                let selectorId = 'weeconnectpay-untouched-card-number-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_empty_card_number_cc_form" value="1">`)
            }
        }
        // DATE
        if(state.CARD_DATE){
            if (state.CARD_DATE.error){
                let selectorId = 'weeconnectpay-card-number-error-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_date_error_cc_form" value="1">`)

            }
            if (!state.CARD_DATE.touched){
                let selectorId = 'weeconnectpay-untouched-card-date-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_empty_date_cc_form" value="1">`)

            }
        }
        // CVV
        if(state.CARD_CVV){

            if (state.CARD_CVV.error){
                let selectorId = 'weeconnectpay-card-cvv-error-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_cvv_error_cc_form" value="1">`)

            }
            if (!state.CARD_CVV.touched){
                let selectorId = 'weeconnectpay-untouched-card-cvv-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_empty_cvv_cc_form" value="1">`)

            }
        }
        // POSTAL_CODE
        if(state.CARD_POSTAL_CODE){

            if (state.CARD_POSTAL_CODE.error){
                let selectorId = 'weeconnectpay-postal-code-error-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_postal_code_error_cc_form" value="1">`)

            }
            if (!state.CARD_POSTAL_CODE.touched){
                let selectorId = 'weeconnectpay-untouched-postal-code-input'
                lastSubmitStateErrors.push(selectorId)
                errors.push(`<input type="hidden" id="${selectorId}" name="weeconnectpay_prevent_submit_empty_postal_code_cc_form" value="1">`)

            }
        }

        return errors
    }


    function isErrorFree(formErrors){
        return formErrors.length === 0
    }

    let $checkoutForm = jQuery('form.checkout')
    let $paymentMethod = jQuery('input[name="payment_method"]:checked')
    let $orderPayForm = jQuery( 'form#order_review' )

    $checkoutForm.on('checkout_place_order', function () {
        let $payment_method = $checkoutForm.find($paymentMethod).val()
        if ($payment_method === 'weeconnectpay') {
            // To be read by the validator in PHP
            let lastErrorIds = lastSubmitStateErrors
            let ccFormErrors = createErrorsFromFormState(ccFormState)

            if (isErrorFree(ccFormErrors)){
                // alert('We have a form free of our own errors!')
                return true
            }
            // Remove Previous Errors
            for (let lastErrorId of lastErrorIds) {
                let inputEl = document.getElementById(lastErrorId)
                inputEl.remove()
            }
            // Add current errors
            for (let ccFormError of ccFormErrors) {
                $checkoutForm.append(ccFormError)
            }
            return true
        }
        return true
    })

    $orderPayForm.on('submit', function () {
        // Find the payment method selected on the order-pay page
        let $payment_method = $orderPayForm.find($paymentMethod).val()
        // Make sure it's ours
        if ($payment_method === 'weeconnectpay') {
            // To be read by the validator in PHP
            let lastErrorIds = lastSubmitStateErrors
            let ccFormErrors = createErrorsFromFormState(ccFormState)
            for (let lastErrorId of lastErrorIds) {
                let inputEl = document.getElementById(lastErrorId)
                inputEl.remove()
            }
            for (let ccFormError of ccFormErrors) {
                $orderPayForm.append(ccFormError)
            }

            if(ccFormErrors.length){
                // ...
            } else {
                // ...
            }
            alert('submit cancelled!');
            return true
        }
        return true
    })


    $( document.body ).on( 'checkout_error', function() {

        let error_text = $('.woocommerce-error').find('li').first().text()
        if ( error_text.trim().startsWith('Please enter') ) {
            // ...
        }

    })


})(jQuery, window, document)
