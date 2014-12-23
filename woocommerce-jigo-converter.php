<?php
/*
Plugin Name: WooCommerce - JigoShop -> WooCommerce Converter
Plugin URI: http://www.woothemes.com/woocommerce
Description: Convert products, product categories, and more from JigoShop to WooCommerce.
Author: WooThemes
Author URI: http://woothemes.com/
Version: 2.0.1
Text Domain: woo_jigo
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '48197cd1c39056019b53eef0ffdf3c05', '19001' );

if ( ! is_woocommerce_active() )
	return;

if ( ! defined( 'WP_LOAD_IMPORTERS' ) )
	return;

/** Display verbose errors */
if ( ! defined( 'IMPORT_DEBUG' ) )
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

		/**
		 * Registered callback function for the WooCommerce - JigoShop Converter
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function dispatch() {
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

		/**
		 * Display import page title
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function header() {
			echo '<div class="wrap">';
			screen_icon();
			echo '<h2>' . __( 'JigoShop To WooCommerce Converter', 'woo_jigo' ) . '</h2>';

			$updates  = get_plugin_updates();
			$basename = plugin_basename( __FILE__ );
			if ( isset( $updates[$basename] ) ) {
				$update = $updates[$basename];
				echo '<div class="error"><p><strong>';
				printf( __( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', 'woo_jigo' ), $update->update->new_version );
				echo '</strong></p></div>';
			}
		}

		/**
		 * Close div.wrap
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function footer() {
			echo '</div>';
		}

		/**
		 * Analyze the JigoShop data
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function analyze() {
			global $wpdb;

			echo '<div class="narrow">';

			// show error message when JigoShop plugin is active
			if ( class_exists( 'jigoshop' ) ) {
				echo '<div class="error"><p>' . __( 'Please deactivate your JigoShop plugin.', 'woo_jigo' ) . '</p></div>';
			}

			echo '<p>'.__('Analyzing JigoShop products&hellip;', 'woo_jigo').'</p>';

			echo '<ol>';

			// Get the products
			$q           = "
					SELECT ID
					FROM $wpdb->posts
					WHERE post_type = 'product'
					";
			$product_ids = $wpdb->get_col( $q );
			$products    = count( $product_ids );
			printf( '<li>' . __( '<b>%d</b> "possible" products were identified', 'woo_jigo' ) . '</li>', $products );

			// Get the attributes
			$attributes = $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}jigoshop_attribute_taxonomies" );
			printf( '<li>' . __( '<b>%d</b> product attribute taxonomies were identified', 'woo_jigo' ) . '</li>', $attributes );

			// Get the variations
			$q             = "
					SELECT ID
					FROM $wpdb->posts
					WHERE post_type = 'product_variation'
					";
			$variation_ids = $wpdb->get_col( $q );
			$variations    = count( $variation_ids );
			printf( '<li>' . __( '<b>%d</b> "possible" product variations were identified', 'woo_jigo' ) . '</li>', $variations );

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

		/**
		 * The actual import method
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function convert() {
			global $wpdb;

			wp_suspend_cache_invalidation( true );

			$this->process_attributes();
			$this->process_products();
			$this->process_variations();

			wp_suspend_cache_invalidation( false );

			// Like the upgrade script in WC - Upgrade old meta keys for product data
			$meta = array( 'sku', 'downloadable', 'virtual', 'price', 'visibility', 'stock', 'stock_status', 'backorders', 'manage_stock', 'sale_price', 'regular_price', 'weight', 'length', 'width', 'height', 'tax_status', 'tax_class', 'upsell_ids', 'crosssell_ids', 'sale_price_dates_from', 'sale_price_dates_to', 'min_variation_price', 'max_variation_price', 'featured', 'product_attributes', 'file_path', 'download_limit', 'product_url' );

			$wpdb->query("
				UPDATE $wpdb->postmeta
				LEFT JOIN $wpdb->posts ON ( $wpdb->postmeta.post_id = $wpdb->posts.ID )
				SET meta_key = CONCAT('_', meta_key)
				WHERE meta_key IN ('". implode("', '", $meta) ."')
				AND $wpdb->posts.post_type IN ('product', 'product_variation')
			");
		}

		/**
		 * Convert the attributes
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function process_attributes() {
			global $wpdb, $woocommerce;

			$attributes = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jigoshop_attribute_taxonomies" );

			foreach ( $attributes as $attribute ) {

				$attribute_name  = $attribute->attribute_name;
				$attribute_type  = $attribute->attribute_type == 'multiselect' ? 'select' : $attribute->attribute_type;
				$attribute_label = ucwords( $attribute_name );
				if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
					$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
				} else {
					$taxonomy_name = $woocommerce->attribute_taxonomy_name( $attribute_name );
				}

				if ( $attribute_name && strlen( $attribute_name ) < 30 && $attribute_type ) {

					$wpdb->insert( $wpdb->prefix . "woocommerce_attribute_taxonomies", array( 'attribute_name' => $attribute_name, 'attribute_label' => $attribute_label, 'attribute_type' => $attribute_type ), array( '%s', '%s', '%s' ) );

					printf( '<p>' . __( '<b>%s</b> product attribute taxonomy was converted', 'woo_jigo' ) . '</p>', $attribute_name );

				} else {

					printf( '<p>' . __( '<b>%s</b> product attribute taxonomy does exist', 'woo_jigo' ) . '</p>', $attribute_name );

				}

			}

			// Delete our attribute taxonomy
			delete_transient('wc_attribute_taxonomies');

		}

		/**
		 * Convert products
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function process_products() {
			global $woocommerce;

			$this->results = 0;

			// Set the time limit
			$timeout = 600;
			if ( ! ini_get( 'safe_mode' ) ) {
				set_time_limit( $timeout );
			}

			global $wpdb;

			// Get the products
			$q        = "
					SELECT ID,post_title
					FROM $wpdb->posts
					WHERE post_type = 'product'
					";
			$products = $wpdb->get_results( $q );

			$count = count( $products );

			if ( $count ) {

				// Loop through the products
				foreach ( $products as $product ) {

					// ID & Title
					$id    = $product->ID;
					$title = $product->post_title;

					// Get the meta
					$meta = get_post_custom( $product->ID );

					// Maybe unserialize the data
					foreach ( $meta as $key => $val ) {
						$meta[$key] = maybe_unserialize( maybe_unserialize( $val[0] ) );
					}

					// Get the product type
					$terms = wp_get_object_terms( $id, 'product_type' );
					if ( ! is_wp_error( $terms ) && $terms ) {
						$term         = current( $terms );
						$product_type = $term->slug;
					} else {
						$product_type = 'simple';
					}

					// virtual (yes/no)
					// downloadable (yes/no)
					if ( 'virtual' == $product_type ) {
						update_post_meta( $id, '_virtual', 'yes' );
						update_post_meta( $id, '_downloadable', 'no' );
						wp_set_object_terms( $id, 'simple', 'product_type' );
					} elseif ( 'downloadable' == $product_type ) {
						update_post_meta( $id, '_virtual', 'yes' );
						update_post_meta( $id, '_downloadable', 'yes' );
						wp_set_object_terms( $id, 'simple', 'product_type' );
					} else {
						update_post_meta( $id, '_virtual', 'no' );
						update_post_meta( $id, '_downloadable', 'no' );
					}

					// product_url (external)
					if ( isset( $meta['external_url'] ) ) {
						delete_post_meta( $id, 'external_url' );
						update_post_meta( $id, '_product_url', $meta['external_url'] );
					}

					// Set the correct _price field
					if ( isset( $meta['sale_price'] ) && ! empty( $meta['sale_price'] ) && $meta['sale_price'] > 0 ) {
						update_post_meta( $id, '_price', $meta['sale_price'] );
					} else {
						update_post_meta( $id, '_price', $meta['regular_price'] );
					}

					// sale_price_dates_from
					// no update

					// sale_price_dates_to
					// no update

					// visibility: visible
					// no update

					// featured: yes / no
					if ( isset( $meta['featured'] ) && $meta['featured'] != 'yes' && $meta['featured'] != 'no' ) {
						delete_post_meta( $id, 'featured' );
						if ( $meta['featured'] ) {
							update_post_meta( $id, '_featured', 'yes' );
						} else {
							update_post_meta( $id, '_featured', 'no' );
						}
					}

					// if woo updater did the job early
					if ( isset( $meta['_featured'] ) && $meta['_featured'] != 'yes' && $meta['_featured'] != 'no' ) {
						if ( $meta['_featured'] ) {
							update_post_meta( $id, '_featured', 'yes' );
						} else {
							update_post_meta( $id, '_featured', 'no' );
						}
					}

					// manage_stock: yes / no
					if ( isset( $meta['manage_stock'] ) && $meta['manage_stock'] != 'yes' && $meta['manage_stock'] != 'no' ) {
						delete_post_meta( $id, 'manage_stock' );
						if ( $meta['manage_stock'] ) {
							update_post_meta( $id, '_manage_stock', 'yes' );
						} else {
							update_post_meta( $id, '_manage_stock', 'no' );
						}
					}

					// if woo updater did the job early
					if ( isset( $meta['_manage_stock'] ) && $meta['_manage_stock'] != 'yes' && $meta['_manage_stock'] != 'no' ) {
						if ( $meta['_manage_stock'] ) {
							update_post_meta( $id, '_manage_stock', 'yes' );
						} else {
							update_post_meta( $id, '_manage_stock', 'no' );
						}
					}

					// stock_status: -1
					if ( isset( $meta['stock_status'] ) && $meta['stock_status'] == - 1 ) {
						delete_post_meta( $id, 'stock_status' );
						update_post_meta( $id, '_stock_status', 'instock' );
					}

					// if woo updater did the job early
					if ( isset( $meta['_stock_status'] ) && $meta['_stock_status'] == - 1 ) {
						update_post_meta( $id, '_stock_status', 'instock' );
					}

					// file_path (downloadable)
					$file_path = null;

					// Check for file_path
					if ( isset( $meta['file_path'] ) && ! strstr( $meta['file_path'], ABSPATH ) ) {
						$file_path = ltrim( $meta['file_path'], '/' );
						delete_post_meta( $id, 'file_path' );

					}

					// Check for _file_path
					if ( isset( $meta['_file_path'] ) && ! strstr( $meta['_file_path'], ABSPATH ) ) {
						$file_path = ltrim( $meta['_file_path'], '/' );
						delete_post_meta( $id, '_file_path' );
					}

					// Format and save
					if ( null !== $file_path ) {
						$files = array(
							md5( $file_path ) => array(
								'name' => 'Downloadable File',
								'file' => $file_path
							)
						);

						update_post_meta( $id, '_downloadable_files', $files );
					}

					// per_product_shipping
					// sorry, JigoShop doesn't support it

					// fix product attributes
					$meta_attributes = ( isset( $meta['product_attributes'] ) ? $meta['product_attributes'] : $meta['_product_attributes'] );

					$new_attributes = array();
					foreach ( (array) $meta_attributes as $key => $attribute ) {
						if ( isset( $attribute['visible'] ) || isset( $attribute['variation'] ) ) {

							if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
								$key = wc_attribute_taxonomy_name( $key );
							} else {
								$key = $woocommerce->attribute_taxonomy_name( $key );
							}

							$new_attributes[$key]['name']     = $key;
							$new_attributes[$key]['value']    = $attribute['value'];
							$new_attributes[$key]['position'] = $attribute['position'];

							$new_attributes[$key]['is_visible']   = ( $attribute['visible'] == true ? 1 : 0 );
							$new_attributes[$key]['is_variation'] = ( $attribute['variation'] == true ? 1 : 0 );
							$new_attributes[$key]['is_taxonomy']  = ( $attribute['is_taxonomy'] == true ? 1 : 0 );

							// Format and fix the values
							$values = $attribute['value'];
							if ( is_string( $attribute['value'] ) ) {
								$values = explode( ",", $attribute['value'] );
							}

							// Remove empty items in the array
							$values = array_filter( $values );

							// Set the terms
							wp_set_object_terms( $id, $values, $key );
						}
					}

					if ( ! empty( $new_attributes ) ) {
						update_post_meta( $id, '_product_attributes', $new_attributes );
						delete_post_meta( $id, 'product_attributes' );
					}

					// WC 2.0.x Product Gallery Support
					$attachment_ids        = get_posts( 'post_parent=' . $id . '&numberposts=-1&post_type=attachment&orderby=menu_order&order=ASC&post_mime_type=image&fields=ids' );
					$attachment_ids        = array_diff( $attachment_ids, array( get_post_thumbnail_id() ) );
					$product_image_gallery = implode( ',', $attachment_ids );
					update_post_meta( $id, '_product_image_gallery', $product_image_gallery );

					$this->results++;
					printf( '<p>'.__('<b>%s</b> product was checked and converted', 'woo_jigo').'</p>', $title );
				}
			}
		}

		/**
		 * Convert product variations
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 */
		public function process_variations() {
			global $wpdb, $woocommerce;

			$this->results = 0;

			// The timeout
			$timeout = 600;
			if( !ini_get( 'safe_mode' ) ) {
				set_time_limit( $timeout );
			}

			// Get the product variations
			$q        = "
					SELECT ID,post_title
					FROM $wpdb->posts
					WHERE post_type = 'product_variation'
					";
			$products = $wpdb->get_results( $q );

			// Count the variations
			$count = count( $products );

			if ( $count ) {

				foreach ( $products as $product ) {

					$id = $product->ID;
					$title = $product->post_title;

					// Maybe unserialize all meta data
					$meta = get_post_custom( $product->ID );
					foreach ( $meta as $key => $val ) {
						$meta[$key] = maybe_unserialize( maybe_unserialize( $val[0] ) );
					}

					// update attributes
					if ( isset( $meta['variation_data'] ) ) {
						$meta_variation = $meta['variation_data'];
						foreach ( (array) $meta_variation as $key => $val ) {
							if ( preg_match( '/^tax_/', $key ) ) {
								$newkey = str_replace( "tax_", "attribute_pa_", $key );
								update_post_meta( $id, $newkey, $val );
							}
						}
					}
					delete_post_meta( $id, 'variation_data' );

					// from regular_price to price
					if ( isset( $meta['regular_price'] ) ) {
						delete_post_meta( $id, 'regular_price' );
						update_post_meta( $id, '_regular_price', $meta['regular_price'] );
					}

					// if woo updater did the job early
					/*
					if ( isset( $meta['_regular_price'] ) ) {
						delete_post_meta( $id, '_regular_price' );
						update_post_meta( $id, '_price', $meta['_regular_price'] );
					}
					*/

					$this->results++;

					printf( '<p>'.__('<b>%s</b> product variation was checked and converted', 'woo_jigo').'</p>', $title );

				}

			}

		}

	}

}

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

