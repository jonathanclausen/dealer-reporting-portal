<?php
/**
 * Database handler for AM Dealer Contact Form
 */

if (!defined('ABSPATH')) {
    exit;
}

class AM_DCF_Database {
    
    /**
     * Create database table for storing submissions
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'am_dcf_submissions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_name varchar(255) NOT NULL,
            contact_email varchar(255) NOT NULL,
            contact_phone varchar(255) NOT NULL,
            dealer_name varchar(255) NOT NULL,
            serial_number varchar(19) NOT NULL,
            issues_description text NOT NULL,
            incident_date date NOT NULL,
            incident_time time NOT NULL,
            spare_part_number varchar(255) DEFAULT '',
            files text,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // More aggressive column check
        self::ensure_all_columns_exist();
    }

    /**
     * Manually ensure every column exists
     */
    private static function ensure_all_columns_exist() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'am_dcf_submissions';
        
        $required_columns = array(
            'contact_name'      => "varchar(255) NOT NULL AFTER id",
            'contact_email'     => "varchar(255) NOT NULL AFTER contact_name",
            'contact_phone'     => "varchar(255) NOT NULL AFTER contact_email",
            'dealer_name'       => "varchar(255) NOT NULL AFTER contact_phone",
            'serial_number'     => "varchar(19) NOT NULL AFTER dealer_name",
            'issues_description'=> "text NOT NULL AFTER serial_number",
            'incident_date'     => "date NOT NULL AFTER issues_description",
            'incident_time'     => "time NOT NULL AFTER incident_date",
            'spare_part_number' => "varchar(255) DEFAULT '' AFTER incident_time",
            'files'             => "text AFTER spare_part_number",
            'submitted_at'      => "datetime DEFAULT CURRENT_TIMESTAMP AFTER files"
        );

        foreach ($required_columns as $column => $definition) {
            $check = $wpdb->get_results("SHOW COLUMNS FROM `$table_name` LIKE '$column'");
            if (empty($check)) {
                $wpdb->query("ALTER TABLE `$table_name` ADD `$column` $definition");
            }
        }
    }
    
    /**
     * Insert a new submission
     */
    public static function insert_submission($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'am_dcf_submissions';

        // Ensure time format is HH:MM:SS
        $time = $data['incident_time'];
        if (strlen($time) === 5) {
            $time .= ':00';
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'contact_name' => sanitize_text_field($data['contact_name']),
                'contact_email' => sanitize_email($data['contact_email']),
                'contact_phone' => sanitize_text_field($data['contact_phone']),
                'dealer_name' => sanitize_text_field($data['dealer_name']),
                'serial_number' => sanitize_text_field($data['serial_number']),
                'issues_description' => sanitize_textarea_field($data['issues_description']),
                'incident_date' => sanitize_text_field($data['incident_date']),
                'incident_time' => $time,
                'spare_part_number' => sanitize_text_field($data['spare_part_number']),
                'files' => isset($data['files']) ? json_encode($data['files']) : '',
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_insert_error', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get all submissions
     */
    public static function get_submissions($limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'am_dcf_submissions';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    /**
     * Get submission by ID
     */
    public static function get_submission($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'am_dcf_submissions';
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id)
        );
    }
    
    /**
     * Get total count of submissions
     */
    public static function get_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'am_dcf_submissions';
        
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    /**
     * Generate the case number with an offset
     */
    public static function get_case_number($id) {
        // You can change this offset to start from a higher number
        $offset = 1000; 
        return 'STORM-' . str_pad($id + $offset, 5, '0', STR_PAD_LEFT);
    }
}

