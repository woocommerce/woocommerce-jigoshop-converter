<?php
/*
Plugin Name: WooCommerce - JigoShop Converter
Plugin URI: http://www.woothemes.com/woocommerce
Description: Convert products, product categories, and more from JigoShop to WooCommerce.
Author: Agus MU
Author URI: http://agusmu.com/
Version: 1.1
Text Domain: woo_jigo
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Plugin updates
 * */
if (is_admin()) {
	if ( ! class_exists( 'WooThemes_Plugin_Updater' ) ) require_once( 'woo-updater/plugin-updater.class.php' );
	
	$woo_plugin_updater_jigoshop_converter = new WooThemes_Plugin_Updater( __FILE__ );
	$woo_plugin_updater_jigoshop_converter->api_key = 'd788ba19ad428a8fdebff0676e15a8e6';
	$woo_plugin_updater_jigoshop_converter->init();
}

/**
 * Check if WooCommerce is active
 **/
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
	return;
	
if ( ! defined( 'WP_LOAD_IMPORTERS' ) )
	return;

/** Display verbose errors */
define( 'IMPORT_DEBUG', false );

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

/**
 * WordPress Importer class
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class Woo_Jigo_Converter extends WP_Importer {

	var $results;

	function Woo_Jigo_Converter() { /* nothing */ }

	/**
	 * Registered callback function for the WooCommerce - JigoShop Converter
	 *
	 */
	function dispatch() {
		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		switch ( $step ) {
			case 0:
				$this->analyze();
				break;
			case 1:
				check_admin_referer('woo_jigo_converter');
				$this->convert();
				break;
		}

		$this->footer();
	}

	// Display import page title
	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'JigoShop To WooCommerce Converter', 'woo_jigo' ) . '</h2>';

		$updates = get_plugin_updates();
		$basename = plugin_basename(__FILE__);
		if ( isset( $updates[$basename] ) ) {
			$update = $updates[$basename];
			echo '<div class="error"><p><strong>';
			printf( __( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', 'woo_jigo' ), $update->update->new_version );
			echo '</strong></p></div>';
		}
	}

	// Close div.wrap
	function footer() {
		echo '</div>';
	}

	// Analyze
	function analyze() {
		global $wpdb;
		echo '<div class="narrow">';
		
		// show error message when JigoShop plugin is active
		if ( class_exists( 'jigoshop' ) ) 
			echo '<div class="error"><p>'.__('Please deactivate your JigoShop plugin.', 'woo_jigo').'</p></div>';
			
		echo '<p>'.__('Analyzing JigoShop products&hellip;', 'woo_jigo').'</p>';

		echo '<ol>';

		$q = "
			SELECT p.ID
			FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
			WHERE 
				p.post_type = 'product'
				AND pm.meta_key = 'product_data'
				AND pm.meta_value != ''
				AND pm.post_id = p.ID
			";
		$product_ids = $wpdb->get_col($q);
		$products = count($product_ids);
		printf( '<li>'.__('<b>%d</b> products were identified', 'woo_jigo').'</li>', $products );

		$attributes = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}jigoshop_attribute_taxonomies");
		printf( '<li>'.__('<b>%d</b> product attribute taxonomies were identified', 'woo_jigo').'</li>', $attributes );

		// I know this is funny. Jigoshop use 'SKU', but WooCommerce use 'sku'
		$q = "
			SELECT p.ID
			FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
			WHERE 
				p.post_type = 'product_variation'
				AND BINARY pm.meta_key = 'SKU'
				AND pm.post_id = p.ID
			";
		$variation_ids = $wpdb->get_col($q);
		$variations = count($variation_ids);
		printf( '<li>'.__('<b>%d</b> product variations were identified', 'woo_jigo').'</li>', $variations );

		echo '</ol>';

		if ( $products || $attributes || $variations ) {
		
			?>
			<form name="woo_jigo" id="woo_jigo" action="admin.php?import=woo_jigo&amp;step=1" method="post">
			<?php wp_nonce_field('woo_jigo_converter'); ?>
			<p class="submit"><input type="submit" name="submit" class="button" value="<?php _e('Convert Now', 'woo_jigo'); ?>" /></p>
			</form>
			<?php

			echo '<p>'.__('<b>Please backup your database first</b>. We are not responsible for any harm or wrong doing this plugin may cause. Users are fully responsible for their own use. This plugin is to be used WITHOUT warranty.', 'woo_jigo').'</p>';
			
		}
		
		echo '</div>';
	}

	// Convert
	function convert() {
		global $wpdb;
		
		wp_suspend_cache_invalidation( true );
		
		$this->process_attributes();
		$this->process_products();
		$this->process_variations();
		
		wp_suspend_cache_invalidation( false );

		// Like the upgrade script in WC - Upgrade old meta keys for product data
		$meta = array('sku', 'downloadable', 'virtual', 'price', 'visibility', 'stock', 'stock_status', 'backorders', 'manage_stock', 'sale_price', 'regular_price', 'weight', 'length', 'width', 'height', 'tax_status', 'tax_class', 'upsell_ids', 'crosssell_ids', 'sale_price_dates_from', 'sale_price_dates_to', 'min_variation_price', 'max_variation_price', 'featured', 'product_attributes', 'file_path', 'download_limit', 'product_url', 'min_variation_price', 'max_variation_price');
		
		$wpdb->query("
			UPDATE $wpdb->postmeta 
			LEFT JOIN $wpdb->posts ON ( $wpdb->postmeta.post_id = $wpdb->posts.ID )
			SET meta_key = CONCAT('_', meta_key)
			WHERE meta_key IN ('". implode("', '", $meta) ."')
			AND $wpdb->posts.post_type IN ('product', 'product_variation')
		");
	}

	// Convert attributes
	function process_attributes() {

		global $wpdb, $woocommerce;
		
		$attributes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}jigoshop_attribute_taxonomies");		
		foreach ( $attributes as $attribute ) {
		
			$attribute_name = $attribute->attribute_name;
			$attribute_type = $attribute->attribute_type == 'multiselect' ? 'select' : $attribute->attribute_type;
			$attribute_label = '';
			
			if ( $attribute_name && $attribute_type && !taxonomy_exists( $woocommerce->attribute_taxonomy_name($attribute_name) ) ) {
			
				$wpdb->insert( $wpdb->prefix . "woocommerce_attribute_taxonomies", array( 'attribute_name' => $attribute_name, 'attribute_label' => $attribute_label, 'attribute_type' => $attribute_type ), array( '%s', '%s' ) );
				
				printf( '<p>'.__('<b>%s</b> product attribute taxonomy was converted', 'woo_jigo').'</p>', $attribute_name );
				
			}
			else {
			
				printf( '<p>'.__('<b>%s</b> product attribute taxonomy does exist', 'woo_jigo').'</p>', $attribute_name );
			
			}
			
		}
		
	}
	
	// Convert products
	function process_products() {

		$this->results = 0;

		$timeout = 600;
		if( !ini_get( 'safe_mode' ) )
			set_time_limit( $timeout );

		global $wpdb;

		$q = "
			SELECT p.ID,p.post_title
			FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
			WHERE 
				p.post_type = 'product'
				AND pm.meta_key = 'product_data'
				AND pm.meta_value != ''
				AND pm.post_id = p.ID
			";
		$products = $wpdb->get_results($q);

		$count = count($products);

		if ( $count ) {
			
			foreach ( $products as $product ) {
			
				$id = $product->ID;
				$title = $product->post_title;
				
				$meta = get_post_custom($product->ID);
				foreach ($meta as $key => $val) {
					$meta[$key] = maybe_unserialize(maybe_unserialize($val[0]));
				}
				$meta_data = $meta['product_data'];
				$meta_attributes = $meta['product_attributes'];
				
				// product_url (external)
				// sorry, JigoShop doesn't support it

				// regular_price
				if ( isset($meta_data['regular_price']) ) {
					update_post_meta( $id, '_regular_price', $meta_data['regular_price'] );	
				}
				
				// sale_price
				if ( isset($meta_data['sale_price']) ) {
					update_post_meta( $id, '_sale_price', $meta_data['sale_price'] );	
				}
				
				// price (regular_price/sale_price)
				// no update
				
				// sale_price_dates_from
				// no update
				
				// sale_price_dates_to
				// no update
				
				// visibility: visible
				// no update
				
				// featured: yes / no
				// no update
				
				// sku: 
				if ( isset($meta['SKU']) ) {
					delete_post_meta( $id, 'SKU' ); // Delete SKU first so new sku is not removed
					update_post_meta( $id, '_sku', $meta['SKU'] );	
				}
				if ( isset($meta['_SKU']) ) {
					delete_post_meta( $id, '_SKU' ); // Delete SKU first so new sku is not removed
					update_post_meta( $id, '_sku', $meta['_SKU'] );	
				}
				
				// stock_status
				if ( isset($meta_data['stock_status']) ) {
					update_post_meta( $id, '_stock_status', $meta_data['stock_status'] );	
				}

				// manage_stock
				if ( isset($meta_data['manage_stock']) ) {
					update_post_meta( $id, '_manage_stock', $meta_data['manage_stock'] );	
				}

				// stock
				if ( isset($meta_data['stock']) ) {
					update_post_meta( $id, '_stock', $meta_data['stock'] );	
				}

				// backorders 
				if ( isset($meta_data['backorders']) ) {
					update_post_meta( $id, '_backorders', $meta_data['backorders'] );	
				}

				$terms = wp_get_object_terms( $id, 'product_type' );
				if (!is_wp_error($terms) && $terms) {
					$term = current($terms);
					$product_type = $term->slug;
				}
				else {
					$product_type = 'simple';
				}

				// virtual (yes/no)
				// downloadable (yes/no)
				if ( $product_type == 'virtual' ) {
					update_post_meta( $id, '_virtual', 'yes' );	
					update_post_meta( $id, '_downloadable', 'no' );	
					wp_set_object_terms( $id, 'simple', 'product_type' );
				}
				elseif ( $product_type == 'downloadable' ) {
					update_post_meta( $id, '_virtual', 'no' );	
					update_post_meta( $id, '_downloadable', 'yes' );	
					wp_set_object_terms( $id, 'simple', 'product_type' );
				}
				else {
					update_post_meta( $id, '_virtual', 'no' );	
					update_post_meta( $id, '_downloadable', 'no' );	
				}
				
				// file_path (downloadable)
				// no update

				// weight
				if ( isset($meta_data['weight']) ) {
					update_post_meta( $id, '_weight', $meta_data['weight'] );	
				}

				// length
				if ( isset($meta_data['length']) ) {
					update_post_meta( $id, '_length', $meta_data['length'] );	
				}
				
				// width
				if ( isset($meta_data['width']) ) {
					update_post_meta( $id, '_width', $meta_data['width'] );	
				}

				// height
				if ( isset($meta_data['height']) ) {
					update_post_meta( $id, '_height', $meta_data['height'] );	
				}

				// tax_status
				if ( isset($meta_data['tax_status']) ) {
					update_post_meta( $id, '_tax_status', $meta_data['tax_status'] );	
				}
				
				// tax_class
				if ( isset($meta_data['tax_class']) ) {
					update_post_meta( $id, '_tax_class', $meta_data['tax_class'] );	
				}

				// crosssell_ids
				if ( isset($meta_data['crosssell_ids']) ) {
					update_post_meta( $id, '_crosssell_ids', $meta_data['crosssell_ids'] );	
				}

				// upsell_ids
				if ( isset($meta_data['upsell_ids']) ) {
					update_post_meta( $id, '_upsell_ids', $meta_data['upsell_ids'] );	
				}

				// per_product_shipping
				// sorry, JigoShop doesn't support it
				
				// fix product attributes
				global $woocommerce;
				$new_attributes = array();
				foreach ( $meta_attributes as $key => $attribute ) {
					$key = $woocommerce->attribute_taxonomy_name($key);
					$new_attributes[$key]['name'] = $key;
					$new_attributes[$key]['value'] = '';
					$new_attributes[$key]['position'] = $attribute['position'];
					$new_attributes[$key]['is_visible'] = $attribute['visible'] == 'yes' ? 1 : 0;
					$new_attributes[$key]['is_variation'] = $attribute['variation'] == 'yes' ? 1 : 0;
					$new_attributes[$key]['is_taxonomy'] = $attribute['is_taxonomy'] == 'yes' ? 1 : 0;
					$values = explode(",", $attribute['value']);
					// Remove empty items in the array
					$values = array_filter( $values );
					wp_set_object_terms( $id, $values, $key);
				}
				update_post_meta( $id, '_product_attributes', $new_attributes );	
				
				// delete product_data to mark it converted
				delete_post_meta( $id, 'product_data' );
				delete_post_meta( $id, 'product_attributes' );
				$this->results++;
				printf( '<p>'.__('<b>%s</b> product was converted', 'woo_jigo').'</p>', $title );
			}

		}
		
	}

	// Convert product variations
	function process_variations() {

		$this->results = 0;

		$timeout = 600;
		if( !ini_get( 'safe_mode' ) )
			set_time_limit( $timeout );

		global $wpdb, $woocommerce;

		$q = "
			SELECT p.ID,p.post_title
			FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
			WHERE 
				p.post_type = 'product_variation'
				AND BINARY pm.meta_key = 'SKU'
				AND pm.post_id = p.ID
			";
		$products = $wpdb->get_results($q);

		$count = count($products);

		if ( $count ) {
			
			foreach ( $products as $product ) {
			
				$id = $product->ID;
				$title = $product->post_title;

				$meta = get_post_custom($product->ID);
				
				// update attributes
				foreach ($meta as $key => $val) {
					if (preg_match('/^tax_/', $key)) {
						$newkey = str_replace("tax_", "attribute_pa_", $key);
						$wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = '$newkey' WHERE meta_key = '$key' AND post_id = '$id'" );
					}
				}
				
				// price (no update)
				
				// sale_price (no update)
				
				// weight (no update)
				
				// stock (no update)
				
				// sku (no update)

				// virtual
				update_post_meta( $id, '_virtual', 'no' );

				// downloadable
				update_post_meta( $id, '_downloadable', 'no' );

				// download_limit
				update_post_meta( $id, '_download_limit', '' );

				// file_path
				update_post_meta( $id, '_file_path', '' );

				// update 'SKU' to 'sku' to mark it converted
				$wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = '_sku' WHERE BINARY meta_key = 'SKU' AND post_id = '$id'" );
				
				$this->results++;
				printf( '<p>'.__('<b>%s</b> product variation was converted', 'woo_jigo').'</p>', $title );
				
			}
			
		}
		
	}

}

} // class_exists( 'WP_Importer' )

function woo_jigo_importer_init() {
	load_plugin_textdomain( 'woo_jigo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * Woo_Jigo_Converter object for registering the import callback
	 * @global Woo_Jigo_Converter $woo_jigo
	 */
	$GLOBALS['woo_jigo'] = new Woo_Jigo_Converter();
	register_importer( 'woo_jigo', 'JigoShop To WooCommerce Converter', __('Convert products, product categories, and more from JigoShop to WooCommerce.', 'woo_jigo'), array( $GLOBALS['woo_jigo'], 'dispatch' ) );
	
}
add_action( 'admin_init', 'woo_jigo_importer_init' );

