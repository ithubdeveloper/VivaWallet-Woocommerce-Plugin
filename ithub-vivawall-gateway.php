<?php
/**
* Plugin Name: VivaWallet Payment Gateway
* Plugin URI: https://github.com/ithubdeveloper/VivaWallet-Woocommerce-Plugin
* Description: Take Credit/Debit Card Payments on your store.
* Version: 1.0
* Author: ITHUB
* Author URI: https://www.linkedin.com/in/saqib-akram-52106153
* Text Domain: viva-wallaet-save-card-plugin
* License:     GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
add_action( 'plugins_loaded', 'ithub_vivawallet_plugin_init', 0 );
function ithub_vivawallet_plugin_init() {
    //if condition use to do nothin while WooCommerce is not installed
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
  include_once( 'ithub-vivawallet-woocommerce.php' );
  // class add it too WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'ithub_add_vivawallet_gateway' );
  add_filter( 'woocommerce_before_checkout_form', 'vivawallet_save_card_list' );
  
  function ithub_add_vivawallet_gateway( $methods ) {
    $methods[] = 'ithub_VivaWallet';
    return $methods;
  }
  function vivawallet_save_card_list(){
    if(isset($_GET["card_id_remove"]) && isset($_GET["card_action"]) && $_GET["card_action"]=="remove"){
        global $woocommerce;
        $login_user_id = get_current_user_id();
        $lw_redirect_checkout = $woocommerce->cart->get_checkout_url();
        if($login_user_id>0){
          $ithub_vivawallet_save_card = get_user_meta($login_user_id, 'ithub_vivawallet_save_card',true);
            if($ithub_vivawallet_save_card!=""){
              $ithub_vivawallet_cards = json_decode($ithub_vivawallet_save_card,TRUE);
              if(is_array($ithub_vivawallet_cards) && count($ithub_vivawallet_cards)>0){
                $card_id_remove = base64_decode($_GET["card_id_remove"]);
                unset($ithub_vivawallet_cards[$card_id_remove]);
                wc_add_notice("Card successful removed.", 'success' );
                if(is_array($ithub_vivawallet_cards) && count($ithub_vivawallet_cards)>0){
                    update_user_meta($login_user_id,'ithub_vivawallet_save_card',json_encode($ithub_vivawallet_cards));
                }else{
                    delete_user_meta($login_user_id, 'ithub_vivawallet_save_card');
                }
             }
           }
        }
        wp_redirect($lw_redirect_checkout);
       exit;
    }
  }  
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ithub_vivawallet_action_links' );
function ithub_vivawallet_action_links( $links ) {
  $plugin_links = array(
    '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'ithub-vivawallet' ) . '</a>',
  );

  return array_merge( $plugin_links, $links );
}


?>