<?php
/**
* Plugin Name: Forminator - BqETH Hash identifier
* Plugin URI: https://BqETH.com
* Description: Generate a unique code from the other form submission data
* License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No need to do anything if the request is via WP-CLI.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	return;
}


if ( ! class_exists( 'WPMUDEV_Forminator_BqETH_Identifier' ) ) {
    
    class WPMUDEV_Forminator_BqETH_Identifier {

        // User defined settings
        private $forms = array(
            200 => 'hidden-1',
            // ... more (form_id) - (hidden_field) pairs here
        );

        private static $_instance = null;

        public static function get_instance() {

            if( is_null( self::$_instance ) ){
                self::$_instance = new WPMUDEV_Forminator_BqETH_Identifier();
            }
            return self::$_instance;
        }

        private function __construct() {
            $this->init();
        }

        public function init(){
            // Do some checks here
            if ( ! defined( 'FORMINATOR_VERSION' ) || FORMINATOR_VERSION < '1.12' || ! class_exists( 'Forminator_API' ) ) {
                return;
            }
            // WP Documentation: add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): true

            // Generate a unique identifier
            add_filter( 'forminator_custom_form_submit_field_data', array( $this, 'bqeth_calculate_hidden_input' ), 10, 2 );
            // Replace in the redirect url
            add_filter( 'forminator_replace_form_payment_data', array( $this, 'bqeth_replace_redirect_params' ), 10, 3 );
        }

        public function bqeth_calculate_hidden_input( $field_data_array, $form_id ) {

            // Only provide hash for the form-field combination listed in the plugin
            if( ! in_array( $form_id, array_keys( $this->forms ) ) ){
                return $field_data_array;
            }

            // Save off the current value of the original field : 
            // 'hidden-1 is some expression such as "text-1,textarea-1" 
            // which is the value that should be hashed 
            $expression = '';
            foreach( $field_data_array as $key => $field ){
                if( $field['name'] === $this->forms[$form_id] ){
                    $expression = $field['value'];
                    unset( $field_data_array[$key] );
                }
            }

            // Replace $expression by the values of the field
            foreach( $field_data_array as $key => $field ){
                $expression = str_replace( $field['name'], $field['value'], $expression);
            }
            // Now calculate the value we should save (hex string)
            $expression = hash('sha256', $expression);

            $field_data_array[] = array(
                'name'     => $this->forms[$form_id],
                'value'    => $expression
            );

            return $field_data_array;

        }

        function bqeth_replace_redirect_params( $content, Forminator_Form_Model $custom_form = null, Forminator_Form_Entry_Model $entry = null ) {

            if ( empty( $custom_form ) ) {
                return $content;
            }

            // // Check that the form is in the list we need to process
            if( ! in_array( $custom_form->id, array_keys( $this->forms ) ) ){
                return $content;
            }

            // $content is a url:  https://bqeth.hopto.org?name={text-1}&hash={hidden-1}
            // $entry has the meta value value of hidden-1
            foreach( $this->forms as $formid => $hidden_field_name) {
                if (isset( $entry->meta_data[ $hidden_field_name ] )) {
                    $value = $entry->meta_data[$hidden_field_name]['value'];
                    // $content = str_replace( '{' . $hidden_field_name . '}', $value, $content );
                    $content = str_replace( $hidden_field_name , $value, $content );
                }
            }
        
            return $content;
        }
    }

    add_action( 'plugins_loaded', function(){
        return WPMUDEV_Forminator_BqETH_Identifier::get_instance();
    });

}
