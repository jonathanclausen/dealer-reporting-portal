<?php
/**
 * Defect Report Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Generate simple math captcha
$num1 = rand(1, 15);
$num2 = rand(1, 10);
$sum = $num1 + $num2;
// Store the answer in session or a transient for verification
// For simplicity here, we'll use a hidden field with an encrypted or hashed value if needed, 
// but we'll stick to a session for now if enabled, or just handle it in the handler.
?>

<div class="am-dcf-form-container">
    <form id="am-dcf-form" class="am-dcf-form" enctype="multipart/form-data">
        <?php wp_nonce_field('am_dcf_nonce', 'am_dcf_nonce_field'); ?>
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('am_dcf_nonce'); ?>" />
        
        <div class="am-dcf-section-title"><?php echo esc_html__('Contact Information', 'am-dealer-contact-form'); ?></div>

        <!-- Contact Information (Placeholders match the clean look) -->
        <div class="am-dcf-field-group">
            <input type="text" id="dealer_name" name="dealer_name" class="am-dcf-input" placeholder="<?php echo esc_attr__('Dealer Name', 'am-dealer-contact-form'); ?> *" required />
            <span class="am-dcf-error" id="error_dealer_name"></span>
        </div>

        <div class="am-dcf-field-group">
            <input type="text" id="contact_name" name="contact_name" class="am-dcf-input" placeholder="<?php echo esc_attr__('Your Name', 'am-dealer-contact-form'); ?> *" required />
            <span class="am-dcf-error" id="error_contact_name"></span>
        </div>

        <div class="am-dcf-field-group">
            <input type="email" id="contact_email" name="contact_email" class="am-dcf-input" placeholder="<?php echo esc_attr__('Email Address', 'am-dealer-contact-form'); ?> *" required />
            <span class="am-dcf-error" id="error_contact_email"></span>
        </div>

        <div class="am-dcf-field-group">
            <input type="tel" id="contact_phone" name="contact_phone" class="am-dcf-input" placeholder="<?php echo esc_attr__('Phone Number (with country code)', 'am-dealer-contact-form'); ?> *" required />
            <span class="am-dcf-error" id="error_contact_phone"></span>
        </div>

        <div class="am-dcf-section-title"><?php echo esc_html__('Defect Report', 'am-dealer-contact-form'); ?></div>

        <!-- Defect Details -->
        <div class="am-dcf-field-group">
            <input 
                type="text" 
                id="serial_number" 
                name="serial_number" 
                class="am-dcf-input" 
                maxlength="19"
                placeholder="<?php echo esc_attr__('Serial Number (starts with HKX)', 'am-dealer-contact-form'); ?> *"
                required
            />
            <span class="am-dcf-error" id="error_serial_number"></span>
        </div>
        
        <div class="am-dcf-field-group">
            <div class="am-dcf-date-time-row">
                <input type="date" id="incident_date" name="incident_date" class="am-dcf-input" required />
                <input type="text" id="incident_time" name="incident_time" class="am-dcf-input" placeholder="14:30 (24h)" pattern="^([01]\d|2[0-3]):([0-5]\d)$" maxlength="5" required />
            </div>
            <span class="am-dcf-error" id="error_incident_date"></span>
        </div>
        
        <div class="am-dcf-field-group">
            <textarea 
                id="issues_description" 
                name="issues_description" 
                class="am-dcf-textarea" 
                placeholder="<?php echo esc_attr__('Details about issues or claims', 'am-dealer-contact-form'); ?> *"
                required
            ></textarea>
            <span class="am-dcf-error" id="error_issues_description"></span>
        </div>
        
        <div class="am-dcf-field-group">
            <input 
                type="text" 
                id="spare_part_number" 
                name="spare_part_number" 
                class="am-dcf-input" 
                placeholder="<?php echo esc_attr__('Spare part nr. request (check the spare part list)', 'am-dealer-contact-form'); ?>"
            />
            <small class="am-dcf-help-text">
                <?php 
                if (is_user_logged_in()): 
                    $spare_parts_url = get_option('am_dcf_spare_parts_file');
                    if ($spare_parts_url): 
                    ?>
                        <a href="<?php echo esc_url($spare_parts_url); ?>" download>
                            <?php echo esc_html__('Download spare part list', 'am-dealer-contact-form'); ?>
                        </a>
                    <?php else: ?>
                        <a href="https://amrobots-my.sharepoint.com/:x:/r/personal/ff_am-robots_com/_layouts/15/Doc.aspx?sourcedoc=%7B60A15A23-FF6E-49F3-BB15-76255B0C329D%7D&file=STORM%20-%20spare%20parts%20with%20repair%20time.xlsx&action=default&mobileredirect=true" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html__('View spare part list', 'am-dealer-contact-form'); ?>
                        </a>
                    <?php 
                    endif;
                else:
                    echo esc_html__('(Log in to access spare part list)', 'am-dealer-contact-form');
                endif; 
                ?>
            </small>
        </div>
        
        <div class="am-dcf-field-group">
            <input 
                type="file" 
                id="files" 
                name="files[]" 
                class="am-dcf-file-input" 
                multiple
                accept="image/*,video/*,.heic,.heif"
            />
            <small class="am-dcf-help-text"><?php echo esc_html__('Upload Pictures/Videos (HEIC supported)', 'am-dealer-contact-form'); ?></small>
            <div id="file-list" class="am-dcf-file-list"></div>
            <span class="am-dcf-error" id="error_files"></span>
        </div>
        
        <div class="am-dcf-submit-row">
            <div class="am-dcf-captcha-wrapper">
                <div class="am-dcf-captcha-group">
                    <span><?php echo $num1; ?> + <?php echo $num2; ?> =</span>
                    <input type="text" name="captcha_ans" id="captcha_ans" class="am-dcf-captcha-input" required />
                    <input type="hidden" name="captcha_val1" value="<?php echo $num1; ?>" />
                    <input type="hidden" name="captcha_val2" value="<?php echo $num2; ?>" />
                </div>
                <span class="am-dcf-error" id="error_captcha_ans"></span>
            </div>
            <button type="submit" class="am-dcf-submit-button">
                <?php echo esc_html__('Submit', 'am-dealer-contact-form'); ?>
            </button>
        </div>
        <div id="am-dcf-message" class="am-dcf-message"></div>
    </form>
</div>
