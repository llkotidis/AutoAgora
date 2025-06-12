<?php
/**
 * Report Listing Handler
 * Handles AJAX requests for reporting listings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook the AJAX handlers
add_action('wp_ajax_submit_listing_report', 'handle_listing_report_submission');
add_action('wp_ajax_nopriv_submit_listing_report', 'handle_listing_report_submission');

/**
 * Handle the listing report submission
 */
function handle_listing_report_submission() {
    // Debug: Log incoming data
    error_log('Report submission received. POST data: ' . print_r($_POST, true));
    
    // Check if nonce exists
    if (!isset($_POST['report_nonce'])) {
        error_log('Report submission error: No nonce provided');
        wp_send_json_error('Security check failed: No nonce provided');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['report_nonce'], 'report_listing_nonce')) {
        error_log('Report submission error: Invalid nonce');
        wp_send_json_error('Security check failed: Invalid nonce');
        return;
    }

    // Sanitize and validate input
    $listing_id = intval($_POST['reported_listing_id']);
    $reason = sanitize_text_field($_POST['report_reason']);
    $details = sanitize_textarea_field($_POST['report_details']);
    $reporter_email = sanitize_email($_POST['reporter_email']);

    // Validate required fields
    if (empty($listing_id) || empty($reason)) {
        wp_send_json_error('Required fields are missing');
        return;
    }

    // Validate listing exists
    $listing = get_post($listing_id);
    if (!$listing || $listing->post_type !== 'car') {
        wp_send_json_error('Invalid listing');
        return;
    }

    // Get current user info if logged in
    $current_user = wp_get_current_user();
    $reporter_name = '';
    $reporter_user_id = 0;

    if ($current_user->ID) {
        $reporter_user_id = $current_user->ID;
        $reporter_name = $current_user->display_name;
        if (empty($reporter_email)) {
            $reporter_email = $current_user->user_email;
        }
    }

    // Prepare report data
    $report_data = array(
        'listing_id' => $listing_id,
        'listing_title' => $listing->post_title,
        'listing_url' => get_permalink($listing_id),
        'reason' => $reason,
        'details' => $details,
        'reporter_email' => $reporter_email,
        'reporter_name' => $reporter_name,
        'reporter_user_id' => $reporter_user_id,
        'report_date' => current_time('mysql'),
        'user_ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    );

    // Send email notification to all admins
    $success = send_report_notification_email($report_data);

    if ($success) {
        wp_send_json_success('Report submitted successfully');
    } else {
        wp_send_json_error('Failed to send report notification');
    }
}

/**
 * Send email notification to all admins about the report
 */
function send_report_notification_email($report_data) {
    // Get all admin users
    $admin_users = get_users(array(
        'role' => 'administrator',
        'fields' => array('user_email', 'display_name')
    ));

    if (empty($admin_users)) {
        return false;
    }

    $site_name = get_bloginfo('name');
    $reason = $report_data['reason'];
    
    // Customize subject and content based on report reason
    $subject_prefix = get_report_subject_prefix($reason);
    $subject = sprintf('[%s] %s: %s', $site_name, $subject_prefix, $report_data['listing_title']);
    
    // Create customized email body based on reason
    $email_body = get_report_email_body($report_data);
    
    // Set headers
    $from_email = get_option('admin_email');
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $site_name . ' <' . $from_email . '>'
    );
    
    if (!empty($report_data['reporter_email'])) {
        $headers[] = 'Reply-To: ' . $report_data['reporter_email'];
    }

    // Send email to all admins
    $success_count = 0;
    foreach ($admin_users as $admin) {
        if (wp_mail($admin->user_email, $subject, $email_body, $headers)) {
            $success_count++;
        }
    }

    // Return true if at least one email was sent successfully
    return $success_count > 0;
}

/**
 * Get subject prefix based on report reason
 */
function get_report_subject_prefix($reason) {
    $prefixes = array(
        'fake_listing' => '🚨 URGENT - Fake/Fraudulent Listing Report',
        'inappropriate_content' => '⚠️ Inappropriate Content Report',
        'spam' => '📧 Spam Report',
        'wrong_category' => '📂 Category Issue Report',
        'duplicate' => '🔄 Duplicate Listing Report',
        'sold_vehicle' => '✅ Sold Vehicle Report',
        'overpriced' => '💰 Pricing Issue Report',
        'other' => '📝 General Listing Report'
    );

    return isset($prefixes[$reason]) ? $prefixes[$reason] : '📝 Listing Report';
}

/**
 * Get customized email body based on report reason
 */
function get_report_email_body($report_data) {
    $reason = $report_data['reason'];
    $listing_url = $report_data['listing_url'];
    $edit_url = admin_url('post.php?post=' . $report_data['listing_id'] . '&action=edit');
    
    // Start with reason-specific introduction
    $email_body = get_reason_specific_intro($reason) . "\n\n";
    
    // Add prominent listing link
    $email_body .= "🔗 REPORTED LISTING:\n";
    $email_body .= "Title: " . $report_data['listing_title'] . "\n";
    $email_body .= "View Listing: " . $listing_url . "\n";
    $email_body .= "Edit Listing: " . $edit_url . "\n\n";
    
    // Add report details
    $email_body .= "📋 REPORT DETAILS:\n";
    $email_body .= "Reason: " . ucfirst(str_replace('_', ' ', $reason)) . "\n";
    
    if (!empty($report_data['details'])) {
        $email_body .= "Additional Details: " . $report_data['details'] . "\n";
    }
    
    // Add reporter info if available
    if (!empty($report_data['reporter_name']) || !empty($report_data['reporter_email'])) {
        $email_body .= "\n👤 REPORTER INFORMATION:\n";
        if (!empty($report_data['reporter_name'])) {
            $email_body .= "Name: " . $report_data['reporter_name'] . "\n";
        }
        if (!empty($report_data['reporter_email'])) {
            $email_body .= "Email: " . $report_data['reporter_email'] . "\n";
        }
    }
    
    // Add suggested actions based on reason
    $email_body .= "\n" . get_suggested_actions($reason) . "\n";
    
    // Add timestamp
    $email_body .= "\n⏰ Report submitted: " . $report_data['report_date'] . "\n";
    
    return $email_body;
}

/**
 * Get reason-specific introduction text
 */
function get_reason_specific_intro($reason) {
    $intros = array(
        'fake_listing' => '🚨 URGENT: A listing has been reported as FAKE or FRAUDULENT. This requires immediate attention.',
        'inappropriate_content' => '⚠️ A listing has been reported for containing inappropriate content.',
        'spam' => '📧 A listing has been reported as spam.',
        'wrong_category' => '📂 A listing has been reported as being in the wrong category.',
        'duplicate' => '🔄 A listing has been reported as a duplicate.',
        'sold_vehicle' => '✅ A listing has been reported as already sold but still active.',
        'overpriced' => '💰 A listing has been reported as significantly overpriced.',
        'other' => '📝 A listing has been reported for review.'
    );

    return isset($intros[$reason]) ? $intros[$reason] : '📝 A listing has been reported and needs your review.';
}

/**
 * Get suggested actions based on report reason
 */
function get_suggested_actions($reason) {
    $actions = array(
        'fake_listing' => "🎯 RECOMMENDED ACTIONS:\n- Immediately suspend the listing\n- Contact the poster for verification\n- Review poster's other listings\n- Consider banning the user if confirmed fake",
        'inappropriate_content' => "🎯 RECOMMENDED ACTIONS:\n- Review the listing content and images\n- Edit or remove inappropriate content\n- Contact the poster if necessary\n- Update listing guidelines if needed",
        'spam' => "🎯 RECOMMENDED ACTIONS:\n- Check if listing violates spam policies\n- Remove listing if confirmed spam\n- Review poster's other listings\n- Consider user warnings or restrictions",
        'wrong_category' => "🎯 RECOMMENDED ACTIONS:\n- Review the listing category\n- Move to correct category if needed\n- Contact poster about proper categorization\n- Update category guidelines if needed",
        'duplicate' => "🎯 RECOMMENDED ACTIONS:\n- Search for similar listings from same user\n- Remove duplicate listings\n- Contact poster about duplicate policy\n- Merge listings if appropriate",
        'sold_vehicle' => "🎯 RECOMMENDED ACTIONS:\n- Contact seller to confirm sale status\n- Mark listing as sold or remove it\n- Update listing status guidelines\n- Remind users to update sold items",
        'overpriced' => "🎯 RECOMMENDED ACTIONS:\n- Review pricing against market value\n- Contact poster about pricing concerns\n- Check if pricing violates any policies\n- Consider market price guidelines",
        'other' => "🎯 RECOMMENDED ACTIONS:\n- Review the listing thoroughly\n- Check the additional details provided\n- Contact reporter if more info needed\n- Take appropriate action based on findings"
    );

    return isset($actions[$reason]) ? $actions[$reason] : "🎯 RECOMMENDED ACTIONS:\n- Review the reported listing\n- Take appropriate action as needed";
}

 