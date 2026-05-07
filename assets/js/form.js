jQuery(document).ready(function($) {
    'use strict';
    
    const form = $('#am-dcf-form');
    const submitButton = form.find('.am-dcf-submit-button');
    const messageDiv = $('#am-dcf-message');
    const originalSubmitText = submitButton.text();
    let isSubmitting = false;

    function unlockSubmitButton() {
        isSubmitting = false;
        submitButton.prop('disabled', false).text(originalSubmitText);
    }
    
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

        // Prevent double-submits and avoid "stuck disabled button" states.
        if (isSubmitting) return;
        isSubmitting = true;
        submitButton.prop('disabled', true).text('Submitting...');

        try {
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
                        // Inject case number after rendering and keep only the number non-translatable.
                        let caseNumber = (response.data && response.data.case_number) ? String(response.data.case_number) : '';
                        if (!caseNumber && response.data && response.data.submission_id) {
                            // Fallback in case translators/plugins strip the case number from response payload.
                            const generated = Number(response.data.submission_id) + 1000;
                            if (!Number.isNaN(generated)) {
                                caseNumber = 'STORM-' + String(generated).padStart(5, '0');
                            }
                        }

                        const caseNumberEl = messageDiv.find('.am-dcf-case-number');
                        if (caseNumberEl.length) {
                            caseNumberEl.attr('data-wg-notranslate', '').attr('translate', 'no').text(caseNumber);
                        } else if (caseNumber) {
                            const caseLabel = (response.data && response.data.case_label) ? String(response.data.case_label) : 'Case number:';
                            messageDiv.find('.am-dcf-success-text').append(
                                '<div class="am-dcf-case-box">' + caseLabel + ' <strong class="am-dcf-case-number" data-wg-notranslate translate="no"></strong></div>'
                            );
                            messageDiv.find('.am-dcf-case-number').text(caseNumber);
                        }
                        form[0].reset();
                        $('#file-list').empty();

                        // Scroll to message
                        $('html, body').animate({
                            scrollTop: messageDiv.offset().top - 100
                        }, 500);
                    } else {
                        if (response.data && response.data.errors) {
                            displayErrors(response.data.errors);
                        } else {
                            messageDiv.addClass('error').text((response.data && response.data.message) ? response.data.message : 'An error occurred.');
                        }
                    }
                    unlockSubmitButton();
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
                    unlockSubmitButton();
                },
                complete: function() {
                    // Ensure the button is always unlocked even if callbacks throw.
                    unlockSubmitButton();
                }
            });
        } catch (err) {
            // If anything throws after disabling the button, make sure we re-enable it.
            console.error('AM DCF Submit exception:', err);
            messageDiv.addClass('error').text('An unexpected error occurred. Please try again.');
            unlockSubmitButton();
        }
    });
});

