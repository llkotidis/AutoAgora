<?php
/**
 * Custom User Roles Definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Add custom user roles.
 */
function add_custom_user_roles() {
    // Add the 'dealership' role if it doesn't exist
    if ( ! get_role( 'dealership' ) ) {
        add_role(
            'dealership',
            __( 'Dealership', 'astra-child' ),
            array(
                'read' => true,
                // Add other capabilities specific to dealerships here
            )
        );
    }

    // Ensure the default 'subscriber' role exists
    if ( ! get_role( 'subscriber' ) ) {
        add_role( 'subscriber', __( 'Subscriber', 'astra-child' ), array( 'read' => true ) );
    }
}
add_action( 'after_switch_theme', 'add_custom_user_roles' );
add_action( 'init', 'add_custom_user_roles' ); 