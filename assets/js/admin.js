jQuery(document).ready(function($) {
    'use strict';

    // Handle media upload for spare parts file
    $('.am-dcf-upload-button').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const input = $('#am_dcf_spare_parts_file');
        
        const frame = wp.media({
            title: 'Select Spare Parts Excel File',
            button: {
                text: 'Use this file'
            },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.url);
        });

        frame.open();
    });
});


