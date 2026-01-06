<?php
/**
 * Form handler for AM Dealer Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class AM_DCF_Form_Handler {
    
    public function __construct() {
        add_action('wp_ajax_am_dcf_submit_form', array($this, 'handle_submission'));
        add_action('wp_ajax_nopriv_am_dcf_submit_form', array($this, 'handle_submission'));
    }
    
    /**
     * Handle form submission
     */
    public function handle_submission() {
        error_log('AM DCF: Starting submission handle');
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'am_dcf_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed.', 'am-dealer-contact-form')));
            }

            error_log('AM DCF: Nonce verified');

            // Verify CAPTCHA
            $val1 = isset($_POST['captcha_val1']) ? intval($_POST['captcha_val1']) : 0;
            $val2 = isset($_POST['captcha_val2']) ? intval($_POST['captcha_val2']) : 0;
            $ans = isset($_POST['captcha_ans']) ? intval($_POST['captcha_ans']) : -1;

            if ($ans !== ($val1 + $val2)) {
                wp_send_json_error(array(
                    'errors' => array('captcha_ans' => __('Incorrect answer.', 'am-dealer-contact-form')),
                    'message' => __('Please check the math answer.', 'am-dealer-contact-form')
                ));
            }
            
            error_log('AM DCF: Captcha verified');
            
            // Validate and sanitize input
            $errors = array();
            
            // Contact Name
            $contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
            if (empty($contact_name)) {
                $errors['contact_name'] = __('Name is required.', 'am-dealer-contact-form');
            }

            // Contact Email
            $contact_email = isset($_POST['contact_email']) ? trim($_POST['contact_email']) : '';
            if (empty($contact_email)) {
                $errors['contact_email'] = __('Email is required.', 'am-dealer-contact-form');
            } elseif (!is_email($contact_email)) {
                $errors['contact_email'] = __('Invalid email address.', 'am-dealer-contact-form');
            }

            // Contact Phone
            $contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
            if (empty($contact_phone)) {
                $errors['contact_phone'] = __('Phone number is required.', 'am-dealer-contact-form');
            }

            // Dealer Name
            $dealer_name = isset($_POST['dealer_name']) ? trim($_POST['dealer_name']) : '';
            if (empty($dealer_name)) {
                $errors['dealer_name'] = __('Dealer name is required.', 'am-dealer-contact-form');
            }

            // Serial number validation (19 digits, starts with HKX)
            $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
            if (empty($serial_number)) {
                $errors['serial_number'] = __('Serial number is required.', 'am-dealer-contact-form');
            } elseif (!preg_match('/^HKX\d{16}$/', $serial_number)) {
                $errors['serial_number'] = __('Serial number must be 19 digits and start with HKX.', 'am-dealer-contact-form');
            }
            
            // Issues description
            $issues_description = isset($_POST['issues_description']) ? trim($_POST['issues_description']) : '';
            if (empty($issues_description)) {
                $errors['issues_description'] = __('Issues or claims description is required.', 'am-dealer-contact-form');
            }
            
            // Incident date
            $incident_date = isset($_POST['incident_date']) ? trim($_POST['incident_date']) : '';
            if (empty($incident_date)) {
                $errors['incident_date'] = __('Date is required.', 'am-dealer-contact-form');
            }
            
            // Incident time
            $incident_time = isset($_POST['incident_time']) ? trim($_POST['incident_time']) : '';
            if (empty($incident_time)) {
                $errors['incident_time'] = __('Time is required.', 'am-dealer-contact-form');
            }
            
            error_log('AM DCF: Inputs validated');

            // Handle file uploads first to catch errors
            $uploaded_files = $this->handle_file_uploads($errors);
            
            error_log('AM DCF: Files handled');

            // If there are errors, return them
            if (!empty($errors)) {
                error_log('AM DCF: Validation errors: ' . json_encode($errors));
                wp_send_json_error(array('errors' => $errors));
            }
            
            // Prepare data for database
            $submission_data = array(
                'contact_name' => $contact_name,
                'contact_email' => $contact_email,
                'contact_phone' => $contact_phone,
                'dealer_name' => $dealer_name,
                'serial_number' => $serial_number,
                'issues_description' => $issues_description,
                'incident_date' => $incident_date,
                'incident_time' => $incident_time,
                'spare_part_number' => isset($_POST['spare_part_number']) ? trim($_POST['spare_part_number']) : '',
                'files' => $uploaded_files
            );
            
            error_log('AM DCF: Saving to database');

            // Save to database
            $result = AM_DCF_Database::insert_submission($submission_data);
            
            if (!is_wp_error($result) && $result) {
                error_log('AM DCF: Saved successfully, ID: ' . $result);
                $submission_id = $result;
                $case_number = AM_DCF_Database::get_case_number($submission_id);
                
                // Send email notification
                error_log('AM DCF: Sending email');
                $this->send_notification_email($submission_data, $submission_id, $case_number);
                
                error_log('AM DCF: All done');
                wp_send_json_success(array(
                    'message' => sprintf(
                        '<div class="am-dcf-success-content">
                            <span class="am-dcf-success-icon">✓</span>
                            <div class="am-dcf-success-text">
                                <h3>' . __('Thank You!', 'am-dealer-contact-form') . '</h3>
                                <p>' . __('Your defect report has been submitted successfully.', 'am-dealer-contact-form') . '</p>
                                <div class="am-dcf-case-box">
                                    ' . __('Case number:', 'am-dealer-contact-form') . ' <strong>%s</strong>
                                </div>
                            </div>
                        </div>', 
                        $case_number
                    ),
                    'submission_id' => $submission_id,
                    'case_number' => $case_number
                ));
            } else {
                $error_msg = is_wp_error($result) ? __('Database Error: ', 'am-dealer-contact-form') . $result->get_error_message() : __('An error occurred. Please try again.', 'am-dealer-contact-form');
                error_log('AM DCF Database Error: ' . $error_msg);
                wp_send_json_error(array('message' => $error_msg));
            }
        } catch (Exception $e) {
            error_log('AM DCF Fatal Error Exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('A fatal server error occurred: ', 'am-dealer-contact-form') . $e->getMessage()));
        } catch (Error $e) {
            error_log('AM DCF Fatal PHP Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('A fatal server error occurred: ', 'am-dealer-contact-form') . $e->getMessage()));
        }
    }
    
    /**
     * Handle file uploads
     */
    private function handle_file_uploads(&$errors) {
        $uploaded_files = array();
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Check if files were actually sent
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            return $uploaded_files;
        }

        $files = $_FILES['files'];
        
        // Loop through each file
        foreach ($files['name'] as $key => $value) {
            if ($files['error'][$key] === UPLOAD_ERR_OK) {
                $single_file = array(
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key]
                );

                // Use WordPress to check file type properly
                $file_type = wp_check_filetype($single_file['name']);
                $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'mp4', 'mov', 'avi', 'mpeg', 'mpg', 'ogg', 'webm');
                
                if (in_array(strtolower($file_type['ext']), $allowed_extensions) || strtolower(pathinfo($single_file['name'], PATHINFO_EXTENSION)) === 'heic') {
                    $upload_overrides = array('test_form' => false);
                    $movefile = wp_handle_upload($single_file, $upload_overrides);
                    
                    if ($movefile && !isset($movefile['error'])) {
                        $uploaded_files[] = array(
                            'url'  => $movefile['url'],
                            'file' => $movefile['file'],
                            'type' => $movefile['type'] ? $movefile['type'] : 'image/heic',
                            'name' => $single_file['name']
                        );
                    } else if (isset($movefile['error'])) {
                        $errors['files'] = sprintf(__('Upload error for %s: %s', 'am-dealer-contact-form'), $single_file['name'], $movefile['error']);
                        error_log('AM DCF Upload Error: ' . $movefile['error']);
                    }
                } else {
                    $errors['files'] = sprintf(__('File type not allowed: %s', 'am-dealer-contact-form'), $single_file['name']);
                    error_log('AM DCF File Type Rejected: ' . $file_type['ext']);
                }
            } elseif ($files['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                // Map PHP upload errors to user-friendly messages
                $error_code = $files['error'][$key];
                switch($error_code) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = __('The file is too large for the server to process. Please try a smaller file or ask your admin to increase the upload limit.', 'am-dealer-contact-form');
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_msg = __('The file was only partially uploaded.', 'am-dealer-contact-form');
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_msg = __('Missing a temporary folder on the server.', 'am-dealer-contact-form');
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_msg = __('Failed to write file to disk.', 'am-dealer-contact-form');
                        break;
                    default:
                        $error_msg = sprintf(__('Upload failed with error code: %d', 'am-dealer-contact-form'), $error_code);
                }
                
                $errors['files'] = sprintf(__('Error for %s: %s', 'am-dealer-contact-form'), $files['name'][$key], $error_msg);
                error_log('AM DCF PHP Upload Error Code: ' . $error_code);
            }
        }
        
        return $uploaded_files;
    }
    
    /**
     * Send notification email
     */
    private function send_notification_email($data, $submission_id, $case_number) {
        $to = get_option('admin_email');
        $subject = sprintf(__('New Defect Report [%s] - %s', 'am-dealer-contact-form'), $case_number, $data['dealer_name']);
        
        $view_url = admin_url('admin.php?page=am-dcf-submissions&submission=' . $submission_id);
        
        $message = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #98c82f; border-bottom: 2px solid #98c82f; padding-bottom: 10px;'>" . __('New Defect Report Received', 'am-dealer-contact-form') . "</h2>
            <p><strong>Case Number:</strong> {$case_number}</p>
            
            <h3 style='background: #f9f9f9; padding: 10px; margin-top: 20px;'>" . __('Contact Information', 'am-dealer-contact-form') . "</h3>
            <p>
                <strong>Dealer:</strong> {$data['dealer_name']}<br>
                <strong>Contact Person:</strong> {$data['contact_name']}<br>
                <strong>Email:</strong> <a href='mailto:{$data['contact_email']}'>{$data['contact_email']}</a><br>
                <strong>Phone:</strong> {$data['contact_phone']}
            </p>
            
            <h3 style='background: #f9f9f9; padding: 10px; margin-top: 20px;'>" . __('Defect Details', 'am-dealer-contact-form') . "</h3>
            <p>
                <strong>Serial Number:</strong> {$data['serial_number']}<br>
                <strong>Date/Time:</strong> {$data['incident_date']} {$data['incident_time']}<br>
                <strong>Spare Part Requested:</strong> " . (!empty($data['spare_part_number']) ? $data['spare_part_number'] : __('None', 'am-dealer-contact-form')) . "
            </p>
            <p>
                <strong>Description:</strong><br>
                " . nl2br(esc_html($data['issues_description'])) . "
            </p>
            
            <div style='margin-top: 30px; text-align: center;'>
                <a href='{$view_url}' style='background-color: #98c82f; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>" . __('View Full Report & Photos', 'am-dealer-contact-form') . "</a>
            </div>
        </div>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
    }
}

