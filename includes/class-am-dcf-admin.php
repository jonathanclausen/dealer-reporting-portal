<?php
/**
 * Admin functionality for AM Dealer Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class AM_DCF_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_db_repair'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue admin scripts for media uploader
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'am-dcf-submissions') === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('am-dcf-admin', AM_DCF_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AM_DCF_VERSION, true);
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('am_dcf_settings', 'am_dcf_recipient_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ));

        register_setting('am_dcf_settings', 'am_dcf_spare_parts_file', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ));
    }

    /**
     * Handle manual DB repair
     */
    public function handle_db_repair() {
        if (isset($_GET['page']) && $_GET['page'] === 'am-dcf-submissions' && isset($_GET['repair_db']) && check_admin_referer('am_dcf_repair_db')) {
            AM_DCF_Database::create_table();
            wp_redirect(admin_url('admin.php?page=am-dcf-submissions&repaired=1'));
            exit;
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Defect Reports', 'am-dealer-contact-form'),
            __('Defect Reports', 'am-dealer-contact-form'),
            'manage_options',
            'am-dcf-submissions',
            array($this, 'display_submissions_page'),
            'dashicons-email-alt',
            30
        );
    }
    
    /**
     * Display submissions page
     */
    public function display_submissions_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'submissions';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Defect Reports (STORM)', 'am-dealer-contact-form'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=am-dcf-submissions&tab=submissions" class="nav-tab <?php echo $active_tab == 'submissions' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Submissions', 'am-dealer-contact-form'); ?></a>
                <a href="?page=am-dcf-submissions&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Settings', 'am-dealer-contact-form'); ?></a>
            </h2>

            <?php
            if ($active_tab == 'settings') {
                $this->display_settings_page();
            } else {
                // Get submission ID if viewing single submission
                $submission_id = isset($_GET['submission']) ? intval($_GET['submission']) : 0;
                
                if ($submission_id) {
                    $this->display_single_submission($submission_id);
                } else {
                    $this->display_submissions_list();
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Display settings page content
     */
    private function display_settings_page() {
        ?>
        <form method="post" action="options.php" style="margin-top: 20px;">
            <?php
            settings_fields('am_dcf_settings');
            do_settings_sections('am_dcf_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Recipient Email Address', 'am-dealer-contact-form'); ?></th>
                    <td>
                        <input type="email" name="am_dcf_recipient_email" value="<?php echo esc_attr(get_option('am_dcf_recipient_email', get_option('admin_email'))); ?>" class="regular-text" required />
                        <p class="description"><?php echo esc_html__('All report submissions will be sent to this email address.', 'am-dealer-contact-form'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html__('Spare Parts Excel List', 'am-dealer-contact-form'); ?></th>
                    <td>
                        <input type="text" id="am_dcf_spare_parts_file" name="am_dcf_spare_parts_file" value="<?php echo esc_attr(get_option('am_dcf_spare_parts_file')); ?>" class="regular-text" />
                        <button type="button" class="button am-dcf-upload-button"><?php echo esc_html__('Upload / Select File', 'am-dealer-contact-form'); ?></button>
                        <p class="description"><?php echo esc_html__('The link on the frontend will point to this file.', 'am-dealer-contact-form'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Display single submission
     */
    private function display_single_submission($id) {
        $submission = AM_DCF_Database::get_submission($id);
        
        if (!$submission) {
            echo '<p>' . __('Submission not found.', 'am-dealer-contact-form') . '</p>';
            return;
        }
        
        $case_number = AM_DCF_Database::get_case_number($submission->id);
        $files = !empty($submission->files) ? json_decode($submission->files, true) : array();
        
        ?>
        <div style="margin-top: 20px;">
            <h3><?php printf(__('Defect Report Details: %s', 'am-dealer-contact-form'), $case_number); ?></h3>
            <p><a href="<?php echo admin_url('admin.php?page=am-dcf-submissions'); ?>" class="button"><?php echo esc_html__('Back to List', 'am-dealer-contact-form'); ?></a></p>
            
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Case Number', 'am-dealer-contact-form'); ?></th>
                    <td><strong><?php echo esc_html($case_number); ?></strong></td>
                </tr>
                <tr>
                    <th colspan="2" style="background: #f0f0f1; padding: 10px;"><strong><?php echo esc_html__('Contact Information', 'am-dealer-contact-form'); ?></strong></th>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Dealer Name', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->dealer_name); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Contact Person', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->contact_name); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Email Address', 'am-dealer-contact-form'); ?></th>
                    <td><a href="mailto:<?php echo esc_attr($submission->contact_email); ?>"><?php echo esc_html($submission->contact_email); ?></a></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Phone Number', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->contact_phone); ?></td>
                </tr>
                <tr>
                    <th colspan="2" style="background: #f0f0f1; padding: 10px;"><strong><?php echo esc_html__('Defect Details', 'am-dealer-contact-form'); ?></strong></th>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Serial Number', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->serial_number); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Issues or Claims', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo nl2br(esc_html($submission->issues_description)); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Date', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->incident_date); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Time', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->incident_time); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Spare Part Request', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo !empty($submission->spare_part_number) ? esc_html($submission->spare_part_number) : '<em>' . esc_html__('None', 'am-dealer-contact-form') . '</em>'; ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Submitted At', 'am-dealer-contact-form'); ?></th>
                    <td><?php echo esc_html($submission->submitted_at); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Files', 'am-dealer-contact-form'); ?></th>
                    <td>
                        <?php if (empty($files)): ?>
                            <p><em><?php echo esc_html__('No files were uploaded with this report.', 'am-dealer-contact-form'); ?></em></p>
                        <?php else: ?>
                            <?php foreach ($files as $file): ?>
                                <div style="margin-bottom: 10px; border: 1px solid #ccd0d4; padding: 10px; display: inline-block; vertical-align: top; margin-right: 10px;">
                                    <?php 
                                    $is_image = false;
                                    $is_heic = false;
                                    $mime_type = isset($file['type']) ? $file['type'] : '';
                                    $extension = strtolower(pathinfo($file['url'], PATHINFO_EXTENSION));

                                    if (strpos($mime_type, 'image/heic') !== false || $extension === 'heic' || $extension === 'heif') {
                                        $is_heic = true;
                                    } elseif (strpos($mime_type, 'image/') === 0) {
                                        $is_image = true;
                                    } else {
                                        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                                            $is_image = true;
                                        }
                                    }
                                    ?>
                                    <?php if ($is_image): ?>
                                        <a href="<?php echo esc_url($file['url']); ?>" target="_blank">
                                            <img src="<?php echo esc_url($file['url']); ?>" style="max-width: 200px; max-height: 200px; display: block;" />
                                        </a>
                                    <?php elseif ($is_heic): ?>
                                        <div style="width: 200px; height: 100px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f0f0f1; border-radius: 4px; text-align: center; padding: 10px; box-sizing: border-box;">
                                            <span class="dashicons dashicons-images-alt2" style="font-size: 30px; width: 30px; height: 30px; margin-bottom: 5px;"></span>
                                            <a href="<?php echo esc_url($file['url']); ?>" target="_blank" style="font-weight: bold;">
                                                Download HEIC Image
                                            </a>
                                            <small style="display: block; margin-top: 5px; font-size: 10px;"><?php echo esc_html__('Most browsers cannot preview HEIC files directly.', 'am-dealer-contact-form'); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 200px; height: 100px; display: flex; align-items: center; justify-content: center; background: #f0f0f1; border-radius: 4px;">
                                            <a href="<?php echo esc_url($file['url']); ?>" target="_blank">
                                                <strong><?php echo esc_html(strtoupper(pathinfo($file['url'], PATHINFO_EXTENSION))); ?> File</strong>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 5px; font-size: 11px; max-width: 200px; word-wrap: break-word;">
                                        <?php echo esc_html(isset($file['name']) ? $file['name'] : basename($file['url'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Display submissions list
     */
    private function display_submissions_list() {
        $submissions = AM_DCF_Database::get_submissions(100);
        $total_count = AM_DCF_Database::get_count();
        
        ?>
        <div style="margin-top: 20px;">
            <p>
                <?php printf(__('Total Submissions: %d', 'am-dealer-contact-form'), $total_count); ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=am-dcf-submissions&repair_db=1'), 'am_dcf_repair_db'); ?>" class="button button-secondary" style="margin-left: 20px;">
                    <?php echo esc_html__('Repair Database Table', 'am-dealer-contact-form'); ?>
                </a>
            </p>

            <?php if (isset($_GET['repaired'])): ?>
                <div class="updated notice is-dismissible"><p><?php echo esc_html__('Database table repaired successfully.', 'am-dealer-contact-form'); ?></p></div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 100px;"><?php echo esc_html__('Case Number', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Dealer', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Contact', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Serial Number', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Spare Part', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Date', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Submitted', 'am-dealer-contact-form'); ?></th>
                        <th><?php echo esc_html__('Actions', 'am-dealer-contact-form'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="8"><?php echo esc_html__('No submissions yet.', 'am-dealer-contact-form'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $submission): ?>
                            <?php $case_number = AM_DCF_Database::get_case_number($submission->id); ?>
                            <tr>
                                <td><strong><?php echo esc_html($case_number); ?></strong></td>
                                <td><?php echo esc_html($submission->dealer_name); ?></td>
                                <td>
                                    <?php echo esc_html($submission->contact_name); ?><br>
                                    <small><?php echo esc_html($submission->contact_email); ?></small>
                                </td>
                                <td><?php echo esc_html($submission->serial_number); ?></td>
                                <td><?php echo !empty($submission->spare_part_number) ? esc_html($submission->spare_part_number) : '-'; ?></td>
                                <td><?php echo esc_html($submission->incident_date); ?></td>
                                <td><?php echo esc_html($submission->submitted_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=am-dcf-submissions&submission=' . $submission->id); ?>" class="button button-small"><?php echo esc_html__('View', 'am-dealer-contact-form'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

