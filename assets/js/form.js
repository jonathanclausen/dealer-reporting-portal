jQuery(document).ready(function($) {
    'use strict';
    
    const form = $('#am-dcf-form');
    const submitButton = form.find('.am-dcf-submit-button');
    const messageDiv = $('#am-dcf-message');
    
    // Serial number validation - auto-format to HKX + 16 digits
    $('#serial_number').on('input', function() {
        let value = $(this).val().replace(/[^0-9HKX]/g, '').toUpperCase();
        
        // Ensure it starts with HKX
        if (value.length > 0 && !value.startsWith('HKX')) {
            if (value.startsWith('HK')) {
                value = 'HKX' + value.substring(2);
            } else if (value.startsWith('H')) {
                value = 'HKX' + value.substring(1);
            } else {
                value = 'HKX' + value;
            }
        }
        
        // Limit to 19 characters (HKX + 16 digits)
        if (value.length > 19) {
            value = value.substring(0, 19);
        }
        
        $(this).val(value);
    });
    
    // File input change handler
    $('#files').on('change', function() {
        const fileList = $('#file-list');
        fileList.empty();
        
        if (this.files.length > 0) {
            $.each(this.files, function(index, file) {
                const fileItem = $('<div class="am-dcf-file-list-item"></div>');
                fileItem.text(file.name + ' (' + formatFileSize(file.size) + ')');
                fileList.append(fileItem);
            });
        }
    });
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // Clear errors
    function clearErrors() {
        $('.am-dcf-error').text('');
        messageDiv.removeClass('success error').text('');
    }
    
    // Display errors
    function displayErrors(errors) {
        clearErrors();
        
        $.each(errors, function(field, message) {
            const errorElement = $('#error_' + field);
            if (errorElement.length) {
                errorElement.text(message);
            } else if (field === 'files') {
                $('#error_files').text(message);
            }
        });
    }
    
    // Date/Time display handling - no longer needed with direct inputs
    
    // Auto-format 24h time input
    $('#incident_time').on('input', function() {
        let val = $(this).val().replace(/[^0-9]/g, '');
        if (val.length >= 3) {
            val = val.substring(0, 2) + ':' + val.substring(2, 4);
        }
        $(this).val(val);
    });

    // Form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        clearErrors();
        
        // Simple CAPTCHA validation
        const val1 = parseInt($('input[name="captcha_val1"]').val());
        const val2 = parseInt($('input[name="captcha_val2"]').val());
        const ans = parseInt($('#captcha_ans').val());
        
        if (ans !== (val1 + val2)) {
            displayErrors({
                captcha_ans: 'Wrong answer'
            });
            return;
        }

        submitButton.prop('disabled', true).text('Submitting...');
        
        // Validate serial number format
        const serialNumber = $('#serial_number').val();
        if (!/^HKX\d{16}$/.test(serialNumber)) {
            displayErrors({
                serial_number: 'Serial number must be 19 digits and start with HKX'
            });
            submitButton.prop('disabled', false).text('Submit Defect Report');
            return;
        }
        
        // Create FormData
        const formData = new FormData(this);
        formData.append('action', 'am_dcf_submit_form');
        formData.append('nonce', amDcfAjax.nonce);
        
        // Submit via AJAX
        $.ajax({
            url: amDcfAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    messageDiv.addClass('success').html(response.data.message);
                    form[0].reset();
                    $('#file-list').empty();
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: messageDiv.offset().top - 100
                    }, 500);
                } else {
                    if (response.data.errors) {
                        displayErrors(response.data.errors);
                    } else {
                        messageDiv.addClass('error').text(response.data.message || 'An error occurred.');
                    }
                }
            },
            error: function(xhr, status, error) {
                let errorMsg = 'An error occurred. Please try again.';
                if (xhr.status === 500) {
                    errorMsg = 'Server Error (500). Please check your error log or contact support.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Security/Session Error (403). Please refresh the page.';
                }
                messageDiv.addClass('error').text(errorMsg);
                console.error('AM DCF AJAX Error:', status, error, xhr.responseText);
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Submit');
            }
        });
    });
});

