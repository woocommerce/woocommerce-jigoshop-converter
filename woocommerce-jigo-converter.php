<?php
/*
Plugin Name: WooCommerce - JigoShop -> WooCommerce Converter
Plugin URI: http://www.woothemes.com/woocommerce
Description: Convert products, product categories, and more from JigoShop to WooCommerce.
Author: WooThemes
Author URI: http://woothemes.com/
Version: 1.3.7
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

	// Jigoshop Version
	function jigoshop_version() {

		// Get the db version
		$jigoshop_db_version = get_site_option( 'jigoshop_db_version' );

		if ( ! is_numeric($jigoshop_db_version) ) {
			switch ( $jigoshop_db_version ) {
				case '0.9.6':
					$jigoshop_db_version = 1105310;
					break;
				case '0.9.7':
					$jigoshop_db_version = 1105311;
					break;
				case '0.9.7.1':
					$jigoshop_db_version = 1105312;
					break;
				case '0.9.7.2':
					$jigoshop_db_version = 1105313;
					break;
				case '0.9.7.3':
					$jigoshop_db_version = 1106010;
					break;
				case '0.9.7.4':
					$jigoshop_db_version = 1106011;
					break;
				case '0.9.7.5':
					$jigoshop_db_version = 1106130;
					break;
				case '0.9.7.6':
					$jigoshop_db_version = 1106140;
					break;
				case '0.9.7.7':
					$jigoshop_db_version = 1106220;
					break;
				case '0.9.7.8':
					$jigoshop_db_version = 1106221;
					break;
				case '0.9.8':
					$jigoshop_db_version = 1107010;
					break;
				case '0.9.8.1':
					$jigoshop_db_version = 1109080;
					break;
				case '0.9.9':
					$jigoshop_db_version = 1109200;
					break;
				case '0.9.9.1':
					$jigoshop_db_version = 1111090;
					break;
				case '0.9.9.2':
					$jigoshop_db_version = 1111091;
					break;
				case '0.9.9.3':
					$jigoshop_db_version = 1111092;
					break;
			}
		}
		return $jigoshop_db_version;
	}

	// Analyze
	function analyze() {
		global $wpdb;

		$jigoshop_version = $this->jigoshop_version();

		echo '<div class="narrow">';

		// show error message when JigoShop plugin is active
		if ( class_exists( 'jigoshop' ) )
			echo '<div class="error"><p>'.__('Please deactivate your JigoShop plugin.', 'woo_jigo').'</p></div>';

		echo '<p>'.__('Analyzing JigoShop products&hellip;', 'woo_jigo').'</p>';

		echo '<ol>';

		if ( $jigoshop_version < 1202010 ) {
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
		}
		else {
			$q = "
				SELECT ID
				FROM $wpdb->posts
				WHERE post_type = 'product'
				";
			$product_ids = $wpdb->get_col($q);
			$products = count($product_ids);
			printf( '<li>'.__('<b>%d</b> "possible" products were identified', 'woo_jigo').'</li>', $products );
		}

		$attributes = $wpdb->get_var("SELECT count(*) FROM {$wpdb->prefix}jigoshop_attribute_taxonomies");
		printf( '<li>'.__('<b>%d</b> product attribute taxonomies were identified', 'woo_jigo').'</li>', $attributes );

		if ( $jigoshop_version < 1202010 ) {
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
		}
		else {
			$q = "
				SELECT ID
				FROM $wpdb->posts
				WHERE post_type = 'product_variation'
				";
			$variation_ids = $wpdb->get_col($q);
			$variations = count($variation_ids);
			printf( '<li>'.__('<b>%d</b> "possible" product variations were identified', 'woo_jigo').'</li>', $variations );
		}

		echo '</ol>';

		if ( $products || $attributes || $variations ) {

			if ( $jigoshop_version >= 1202010 ) {
				echo '<p><em>'.__('Note: JigoShop v1.0 and greater has many similarities with WooCommerce v1.4 and greater. We need to check all products.', 'woo_jigo').'</em></p>';
			}

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
			$attribute_label = ucwords($attribute_name);
			if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
				$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
			} else {
				$taxonomy_name = $woocommerce->attribute_taxonomy_name( $attribute_name );
			}

			if ($attribute_name && strlen($attribute_name)<30 && $attribute_type && ! taxonomy_exists( $taxonomy_name ) ) {

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
		global $woocommerce;

		$jigoshop_version = $this->jigoshop_version();

		$this->results = 0;

		$timeout = 600;
		if( !ini_get( 'safe_mode' ) )
			set_time_limit( $timeout );

		global $wpdb;

		if ( $jigoshop_version < 1202010 ) {
			$q = "
				SELECT p.ID,p.post_title
				FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
				WHERE
					p.post_type = 'product'
					AND pm.meta_key = 'product_data'
					AND pm.meta_value != ''
					AND pm.post_id = p.ID
				";
		}
		else {
			$q = "
				SELECT ID,post_title
				FROM $wpdb->posts
				WHERE post_type = 'product'
				";
		}

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

				if ( $jigoshop_version < 1202010 ) {

					$meta_data = $meta['product_data'];

					// regular_price
					if ( isset($meta_data['regular_price']) ) {
						update_post_meta( $id, '_regular_price', $meta_data['regular_price'] );
					}

					// sale_price
					if ( isset($meta_data['sale_price']) ) {
						update_post_meta( $id, '_sale_price', $meta_data['sale_price'] );
					}
					
					// price
					if ( isset($meta_data['regular_price']) ) {
						if ( isset($meta_data['sale_price']) && $meta_data['sale_price'] > 0 ) 
							update_post_meta( $id, '_price', $meta_data['sale_price'] );
						else
							update_post_meta( $id, '_price', $meta_data['regular_price'] );
					}

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

					delete_post_meta( $id, 'product_data' );

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
					update_post_meta( $id, '_virtual', 'yes' );
					update_post_meta( $id, '_downloadable', 'yes' );
					wp_set_object_terms( $id, 'simple', 'product_type' );
				}
				else {
					update_post_meta( $id, '_virtual', 'no' );
					update_post_meta( $id, '_downloadable', 'no' );
				}

				// product_url (external)
				if ( isset($meta['external_url']) ) {
					delete_post_meta( $id, 'external_url' );
					update_post_meta( $id, '_product_url', $meta['external_url'] );
				}

				// price (regular_price/sale_price)
				$sale_price 	= get_post_meta( $id, '_sale_price', true );
				$regular_price 	= get_post_meta( $id, '_regular_price', true );
				if ( isset($sale_price) && $sale_price > 0 ) 
					update_post_meta( $id, '_price', $sale_price );
				else
					update_post_meta( $id, '_price', $regular_price );

				// sale_price_dates_from
				// no update

				// sale_price_dates_to
				// no update

				// visibility: visible
				// no update

				if ( $jigoshop_version >= 1202010 ) {

					// featured: yes / no
					if ( isset($meta['featured']) && $meta['featured'] != 'yes' && $meta['featured'] != 'no' ) {
						delete_post_meta( $id, 'featured' );
						if ( $meta['featured'] )
							update_post_meta( $id, '_featured', 'yes' );
						else
							update_post_meta( $id, '_featured', 'no' );
					}
					// if woo updater did the job early
					if ( isset($meta['_featured']) && $meta['_featured'] != 'yes' && $meta['_featured'] != 'no' ) {
						if ( $meta['_featured'] )
							update_post_meta( $id, '_featured', 'yes' );
						else
							update_post_meta( $id, '_featured', 'no' );
					}

					// manage_stock: yes / no
					if ( isset($meta['manage_stock']) && $meta['manage_stock'] != 'yes' && $meta['manage_stock'] != 'no' ) {
						delete_post_meta( $id, 'manage_stock' );
						if ( $meta['manage_stock'] )
							update_post_meta( $id, '_manage_stock', 'yes' );
						else
							update_post_meta( $id, '_manage_stock', 'no' );
					}
					// if woo updater did the job early
					if ( isset($meta['_manage_stock']) && $meta['_manage_stock'] != 'yes' && $meta['_manage_stock'] != 'no' ) {
						if ( $meta['_manage_stock'] )
							update_post_meta( $id, '_manage_stock', 'yes' );
						else
							update_post_meta( $id, '_manage_stock', 'no' );
					}

					// stock_status: -1
					if ( isset($meta['stock_status']) && $meta['stock_status'] == -1 ) {
						delete_post_meta( $id, 'stock_status' );
						update_post_meta( $id, '_stock_status', 'instock' );
					}
					// if woo updater did the job early
					if ( isset($meta['_stock_status']) && $meta['_stock_status'] == -1 ) {
						update_post_meta( $id, '_stock_status', 'instock' );
					}

				}

				// file_path (downloadable) -  Add ABSPATH
				if ( isset($meta_data['file_path']) && ! strstr( $meta_data['file_path'], ABSPATH ) ) {
					$meta_data['file_path'] = ltrim($meta_data['file_path'], '/');
					update_post_meta( $id, '_file_path', trailingslashit( ABSPATH ) . $meta_data['file_path'] );
				}
				if ( isset($meta_data['_file_path'])  && ! strstr( $meta_data['_file_path'], ABSPATH ) ) {
					$meta_data['_file_path'] = ltrim($meta_data['_file_path'], '/');
					update_post_meta( $id, '_file_path', trailingslashit( ABSPATH ) . $meta_data['_file_path'] );
				}

				// per_product_shipping
				// sorry, JigoShop doesn't support it

				// fix product attributes
				$meta_attributes = $meta['product_attributes'];
				if ( !$meta_attributes ) $meta_attributes = $meta['_product_attributes'];
				global $woocommerce;
				$new_attributes = array();
				foreach ( (array)$meta_attributes as $key => $attribute ) {
					if ( isset($attribute['visible']) || isset($attribute['variation']) ) {
						if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
							$key = wc_attribute_taxonomy_name( $key );
						} else {
							$key = $woocommerce->attribute_taxonomy_name( $key );
						}
						$new_attributes[$key]['name'] = $key;
						$new_attributes[$key]['value'] = $attribute['value'];
						$new_attributes[$key]['position'] = $attribute['position'];
						if ( $jigoshop_version < 1202010 ) {
							$new_attributes[$key]['is_visible'] = ( $attribute['visible'] == 'yes' ? 1 : 0  );
							$new_attributes[$key]['is_variation'] = ( $attribute['variation'] == 'yes' ? 1 : 0 );
							$new_attributes[$key]['is_taxonomy'] = ( $attribute['is_taxonomy'] == 'yes' ? 1 : 0 );
						}
						else {
							$new_attributes[$key]['is_visible'] = ( $attribute['visible'] == true ? 1 : 0  );
							$new_attributes[$key]['is_variation'] = ( $attribute['variation'] == true ? 1 : 0 );
							$new_attributes[$key]['is_taxonomy'] = ( $attribute['is_taxonomy'] == true ? 1 : 0 );
						}
						$values = explode(",", $attribute['value']);
						// Remove empty items in the array
						$values = array_filter( $values );
						wp_set_object_terms( $id, $values, $key);
					}
				}
				if ( !empty( $new_attributes ) ) {
					update_post_meta( $id, '_product_attributes', $new_attributes );
					delete_post_meta( $id, 'product_attributes' );
				}
				
				// WC 2.0.x Product Gallery Support
				$attachment_ids = get_posts( 'post_parent=' . $id . '&numberposts=-1&post_type=attachment&orderby=menu_order&order=ASC&post_mime_type=image&fields=ids' );
				$attachment_ids = array_diff( $attachment_ids, array( get_post_thumbnail_id() ) );
				$product_image_gallery = implode( ',', $attachment_ids );
				update_post_meta( $id, '_product_image_gallery', $product_image_gallery );

				$this->results++;
				if ( $jigoshop_version < 1202010 ) {
					printf( '<p>'.__('<b>%s</b> product was converted', 'woo_jigo').'</p>', $title );
				}
				else {
					printf( '<p>'.__('<b>%s</b> product was checked and converted', 'woo_jigo').'</p>', $title );
				}
			}
		}
	}

	// Convert product variations
	function process_variations() {

		$jigoshop_version = $this->jigoshop_version();

		$this->results = 0;

		$timeout = 600;
		if( !ini_get( 'safe_mode' ) )
			set_time_limit( $timeout );

		global $wpdb, $woocommerce;

		if ( $jigoshop_version < 1202010 ) {
			$q = "
				SELECT p.ID,p.post_title
				FROM $wpdb->posts AS p, $wpdb->postmeta AS pm
				WHERE
					p.post_type = 'product_variation'
					AND BINARY pm.meta_key = 'SKU'
					AND pm.post_id = p.ID
				";
		}
		else {
			$q = "
				SELECT ID,post_title
				FROM $wpdb->posts
				WHERE post_type = 'product_variation'
				";
		}
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

				if ( $jigoshop_version < 1202010 ) {

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

				}
				else {

					// update attributes
					$meta_variation = $meta['variation_data'];
					foreach ((array)$meta_variation as $key => $val) {
						if (preg_match('/^tax_/', $key)) {
							$newkey = str_replace("tax_", "attribute_pa_", $key);
							update_post_meta( $id, $newkey, $val );
						}
					}
					delete_post_meta( $id, 'variation_data' );

					// from regular_price to price
					if ( isset($meta['regular_price']) ) {
						delete_post_meta( $id, 'regular_price' );
						update_post_meta( $id, '_price', $meta['regular_price'] );
					}
					// if woo updater did the job early
					if ( isset($meta['_regular_price']) ) {
						delete_post_meta( $id, '_regular_price' );
						update_post_meta( $id, '_price', $meta['_regular_price'] );
					}

				}

				$this->results++;
				if ( $jigoshop_version < 1202010 ) {
					printf( '<p>'.__('<b>%s</b> product variation was converted', 'woo_jigo').'</p>', $title );
				}
				else {
					printf( '<p>'.__('<b>%s</b> product variation was checked and converted', 'woo_jigo').'</p>', $title );
				}

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

