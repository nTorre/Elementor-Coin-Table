<?php
/**
 * Plugin Name: GetCoinInfo
 * Description: Displays table of coins with coin gecko API
 * Version: 1.0.1
 * Author: Nicolas Torriglia
 */

function register_coin_table_widget( $widgets_manager ) {
    require_once( __DIR__ . '/widgets/coin-table-widget.php' );
    $widgets_manager->register( new \Coin_Table_Widget() );
}
add_action( 'elementor/widgets/register', 'register_coin_table_widget' );