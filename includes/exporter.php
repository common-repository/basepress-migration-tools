<?php
// Exit if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Basepress_Exporter' ) ){

	class Basepress_Exporter{

		private $exporter_ver = '1.0';
		private $export_dir = '';
		private $export_url = '';


		/**
		 * Basepress_Exporter constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param $export_folder
		 */
		public function __construct( $export_folder ){
			//Add the Ajax callback for our exporter
			add_action( 'init', function(){
				add_action( 'wp_ajax_basepress_kb_export', array( $this, 'basepress_kb_export' ) );
			} );

			$uploads_dir = wp_upload_dir();

			if( isset( $uploads_dir['error'] ) && $uploads_dir['error'] ){
				$this->export_dir = $uploads_dir['error'];
				$this->export_url = $uploads_dir['error'];
			}
			else{
				$this->export_dir = $uploads_dir['basedir'] . '/' . $export_folder . '/';
				$this->export_url = $uploads_dir['baseurl'] . '/' . $export_folder . '/';
			}
		}


		/**
		 * The Ajax callback for the exporter
		 *
		 * @since 1.0.0
		 */
		public function basepress_kb_export(){

			$process = $_POST['process'];
			$data = $_POST['packet'];

			switch( $process ){
				case 'get_export_objects':
					$this->get_export_objects();
					break;
				case 'export_items':
					$this->export_items( $data );
					break;
				case 'close_export_file':
					$this->close_export_file( $data );
					break;
				case 'get_export_file_link':
					$this->get_export_file_link( $data );
					break;
				default:
					wp_die();
			}
		}


		/**
		 * Gets the list of objects to export
		 *
		 * @since 1.0.0
		 */
		private function get_export_objects(){

			$object_types = array( 'entry_page', 'authors', 'kbs', 'sections','tags', 'posts', 'widgets', 'settings' );
			$objects_items = array();
			$kbs = array();

			foreach ( $object_types as $object_type ) {
				switch( $object_type ){
					case 'authors':
						$authors = get_users(
							array(
								'fields' => 'ID',
								'has_published_posts' => 'knowledgebase'
							)
						);
						$objects_items['authors'] = $authors;
						break;

					case 'entry_page':
						$objects_items['entry_page'] = array( 1 );
						break;

					case 'kbs':
						$kbs = get_terms(
							array(
								'taxonomy'   => 'knowledgebase_cat',
								'fields'     => 'ids',
								'hide_empty' => false,
								'parent'     => 0,
							)
						);
						$objects_items['kbs'] = $kbs;
						break;

					case 'sections':
						$objects_items['sections'] = array();

						foreach( $kbs as $kb ){
							$sections = get_terms(
								array(
									'taxonomy'   => 'knowledgebase_cat',
									'fields'     => 'ids',
									'hide_empty' => false,
									'child_of'   => $kb
								)
							);
							$objects_items['sections'] = array_merge( $objects_items['sections'], $sections );
						}
						break;

					case 'tags':
						if( ! taxonomy_exists( 'knowledgebase_tag' ) ){
							$objects_items['tags'] = [];
							break;
						}

						$tags = get_terms(
							array(
								'taxonomy'   => 'knowledgebase_tag',
								'fields'     => 'ids',
								'hide_empty' => false
							)
						);
						$objects_items['tags'] = $tags;
						break;

					case 'posts':
						$items_query = new WP_Query(
							array(
								'post_type'      => 'knowledgebase',
								'posts_per_page' => -1,
								'fields'         => 'ids'
							)
						);
						$objects_items['posts'] = $items_query->posts;
						break;
					case 'widgets':
						$objects_items['widgets'] = array( 1 );
						break;
					case 'settings':
						$objects_items['settings'] = array( 1 );
						break;
				}
			}

			//Remove any empty item
			$objects_items = array_filter( $objects_items );

			$export_file = $this->make_export_file();

			if( $export_file ){
				$response = array(
					'exportObjects' => $objects_items,
					'exportFile' => $export_file
				);
			}
			else{
				$response = array(
					'error' => __( 'The export file could not be created. Make sure that files can be written in the uploads directory', 'basepress-migration' )
				);
			}

			wp_send_json( $response );
		}


		/**
		 * Creates a new export file
		 *
		 * @since 1.0.0
		 *
		 * @return false|string
		 */
		private function make_export_file(){
			$file_was_created = true;

			//Get site name
			$original_website_name = get_bloginfo( 'name' );
			//Make site name lowercase
			$website_name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $original_website_name ) : strtolower( $original_website_name );
			//Replace spaces with dashes
			$website_name = str_replace( ' ', '-', $website_name );
			//Remove special characters
			$website_name = preg_replace( '/[^a-z0-9\-]/', '', $website_name );

			//Get the date as an integer
			$export_date = date( 'Y-m-d H:i:s' );
			$export_date_string = date( 'Y-m-d-His' );
			$export_file_name = 'basepress-export-' . $website_name . '-' . $export_date_string . '.xml';

			//Get the full path for the file
			$export_file_path = $this->export_dir . $export_file_name;

			//Generate an empty file if it doesn't exists
			if( ! file_exists( $export_file_path ) && wp_mkdir_p( $this->export_dir ) ){
				$file_was_created = file_put_contents( $export_file_path, $this->get_export_file_header( $original_website_name, $export_date ) );
			}

			return $file_was_created === false ? false : $export_file_name;
		}


		/**
		 * Adds the xml headers to the new export file
		 *
		 * @since 1.0.0
		 *
		 * @param $original_website_name
		 * @param $export_date
		 * @return false|string
		 */
		private function get_export_file_header( $original_website_name, $export_date ){
			ob_start();
			echo '<?xml version="1.0" encoding="' . get_bloginfo('charset') . "\" ?>\n";
			echo "<!-- BasePress Knowledge Base export file -->\n";
			echo "<!-- Site Name: {$original_website_name} -->\n";
			echo "<!-- Export date: {$export_date} -->\n";
			echo "<!-- Exporter Version: {$this->exporter_ver} -->\n\n";
			echo "<basepress-export>\n";
			echo "\t<exporter_ver>{$this->exporter_ver}</exporter_ver>\n";
			echo "\t<origin_base_url>" . get_home_url() . "</origin_base_url>\n";
			return ob_get_clean();
		}


		/**
		 * Main function to export the items to the export file.
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 */
		private function export_items( $data ){
			$previous_object_name = $data['previousObjectName'];
			$current_object_name = $data['currentObjectName'];
			$items = $data['items'];
			$open_new_parent_element = $data['openParentElement'] == 'true' ? true : false;
			$close_last_parent_element = $previous_object_name != $current_object_name;
			$export_file_name = isset( $data['exportFile'] ) ? sanitize_text_field( $data['exportFile'] ) : false;
			$export_file_path = $this->export_dir . $export_file_name;

			$export_string = '';

			if( $export_file_path && file_exists( $export_file_path ) ){

				if( $close_last_parent_element ){
					$export_string .= $this->close_parent_element( $previous_object_name );
				}
				if( $open_new_parent_element ){
					$export_string .= $this->open_parent_element( $current_object_name );
				}
				switch( $current_object_name ){
					case 'authors':
						$export_string .= $this->export_author_items( $items );
						break;

					case 'entry_page':
						$export_string .= $this->export_entry_page( $items );
						break;

					case 'kbs':
						$export_string .= $this->export_kbs_items( $items );
						break;

					case 'sections':
						$export_string .= $this->export_section_items( $items );
						break;

					case 'tags':
						$export_string .= $this->export_tag_items( $items );
						break;

					case 'posts':
						$export_string .= $this->export_post_items( $items );
						break;

					case 'widgets':
						$export_string .= $this->export_widgets_items();
						break;

					case 'settings':
						$export_string .= $this->export_settings();
						break;
				}

				if( $export_string ){
					file_put_contents( $export_file_path, $export_string, FILE_APPEND );
				}
			}

			wp_send_json( $data );
		}


		/**
		 * Opens the xml parent element for the current object
		 *
		 * @since 1.0.0
		 *
		 * @param $object_name
		 * @return string
		 */
		private function open_parent_element( $object_name ){
			$string = "\t<" . $object_name . ">\n";
			return $string;
		}


		/**
		 * Closes the xml parent element for the current object
		 *
		 * @since 1.0.0
		 *
		 * @param $object_name
		 * @return string
		 */
		private function close_parent_element( $object_name ){
			$string = "\t</" . $object_name . ">\n";
			return $string;
		}


		/**
		 * Exports the authors items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function export_author_items( $items ){
			$string = '';

			foreach( $items as $author_id ){
				$author = get_userdata( (int)$author_id );
				if( ! is_wp_error( $author ) ){
					$string .= "\t\t<author>\n";
					$string .= "\t\t<id>" . intval( $author->ID) . "</id>\n";
					$string .= "\t\t<author_login>" . $this->cdata( $author->user_login ) . "</author_login>\n";
					$string .= "\t\t<author_email>" . $this->cdata( $author->user_email ) . "</author_email>\n";
					$string .= "\t\t<author_display_name>" . $this->cdata( $author->display_name ) . "</author_display_name>\n";
					$string .= "\t\t<author_first_name>" . $this->cdata( $author->first_name ) . "</author_first_name>\n";
					$string .= "\t\t<author_last_name>" . $this->cdata( $author->last_name ) . "</author_last_name>\n";
					$string .= "\t\t</author>\n";
				}
			}
			return $string;
		}


		/**
		 * Exports the KB entry page
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		private function export_entry_page(){
			$string = '';
			$settings = get_option( 'basepress_settings' );
			$entry_page_id = isset( $settings['entry_page'] ) && ! empty( $settings['entry_page'] ) ? $settings['entry_page'] : false;
			$post = ! empty( $entry_page_id ) ? get_post( $entry_page_id ) : false;

			if( empty( $post ) || is_wp_error( $post ) ){
				return $string;
			}

			$string .= "\t\t\t<post_title>" . $this->cdata( $post->post_title ) . "</post_title>\n";
			$string .= "\t\t\t<post_name>" . $this->cdata( $post->post_name ) . "</post_name>\n";
			$string .= "\t\t\t<post_content>" . $this->cdata( $post->post_content ) . "</post_content>\n";
			return $string;
		}


		/**
		 * Exports the KB items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function export_kbs_items( $items ){
			$string = '';

			foreach( $items as $kb_id ){
				$kb = get_term( $kb_id, 'knowledgebase_cat' );
				if( ! is_wp_error( $kb ) ){

					$meta_data = get_term_meta( $kb->term_id );

					$string .= "\t\t<kb>\n";
					$string .= "\t\t\t<id>" . intval( $kb_id ) . "</id>\n";
					$string .= "\t\t\t<name>" . $this->cdata( $kb->name ) . "</name>\n";
					$string .= "\t\t\t<slug>" . $this->cdata( $kb->slug ) . "</slug>\n";
					$string .= "\t\t\t<description>" . $this->cdata( $kb->description ) . "</description>\n";
					$string .= "\t\t\t<termmeta>\n";
					foreach( $meta_data as $meta_key => $meta_value ){
						$string .= "\t\t\t\t<metadata>\n";
						$string .= "\t\t\t\t\t<meta_key>" . $this->cdata( $meta_key ) . "</meta_key>\n";
						$string .= "\t\t\t\t\t<meta_value>" . $this->cdata( $meta_value[0] ) . "</meta_value>\n";
						$string .= "\t\t\t\t</metadata>\n";
					}
					$string .= "\t\t\t</termmeta>\n";
					$string .= "\t\t</kb>\n";
				}
			}
			return $string;
		}


		/**
		 * Exports the section items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function export_section_items( $items ){
			$string = '';

			foreach( $items as $section_id ){
				$section = get_term( $section_id, 'knowledgebase_cat' );
				if( ! is_wp_error( $section ) ){

					$parent_term = get_term( $section->parent, 'knowledgebase_cat' );
					$meta_data = get_term_meta( $section->term_id );

					$string .= "\t\t<section>\n";
					$string .= "\t\t\t<id>" . intval( $section->term_id ) . "</id>\n";
					$string .= "\t\t\t<name>" . $this->cdata( $section->name ) . "</name>\n";
					$string .= "\t\t\t<slug>" . $this->cdata( $section->slug ) . "</slug>\n";
					$string .= "\t\t\t<description>" . $this->cdata( $section->description ) . "</description>\n";
					$string .= "\t\t\t<parent>" . $parent_term->slug . "</parent>\n";
					$string .= "\t\t\t<termmeta>\n";
					foreach( $meta_data as $meta_key => $meta_value ){
						$string .= "\t\t\t\t<metadata>\n";
						$string .= "\t\t\t\t\t<meta_key>" . $this->cdata( $meta_key ) . "</meta_key>\n";
						$string .= "\t\t\t\t\t<meta_value>" . $this->cdata( $meta_value[0] ) . "</meta_value>\n";
						$string .= "\t\t\t\t</metadata>\n";
					}
					$string .= "\t\t\t</termmeta>\n";
					$string .= "\t\t</section>\n";
				}
			}
			return $string;
		}


		/**
		 * Exports the tag items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function export_tag_items( $items ){
			$string = '';

			foreach( $items as $tag_id ){
				$tag = get_term( $tag_id, 'knowledgebase_tag' );
				if( ! is_wp_error( $tag ) ){

					$string .= "\t\t<tag>\n";
					$string .= "\t\t\t<id>" . intval( $tag->term_id ) . "</id>\n";
					$string .= "\t\t\t<name>" . $this->cdata( $tag->name ) . "</name>\n";
					$string .= "\t\t\t<slug>" . $this->cdata( $tag->slug ) . "</slug>\n";
					$string .= "\t\t\t<description>" . $this->cdata( $tag->description ) . "</description>\n";
					$string .= "\t\t</tag>\n";
				}
			}
			return $string;
		}


		/**
		 * Exports the post items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function export_post_items( $items ){
			$string = '';

			foreach( $items as $post_id ){
				$post = get_post( $post_id );
				if( ! is_wp_error( $post ) ){

					$post_sections = get_the_terms( $post_id, 'knowledgebase_cat' );

					$post_tags = taxonomy_exists( 'knowledgebase_tag' )
						? get_the_terms( $post_id, 'knowledgebase_tag' )
						: array();

					$meta_data = get_post_meta( $post->ID );

					$string .= "\t\t<post>\n";
					$string .= "\t\t\t<id>" . intval( $post->ID ) . "</id>\n";
					$string .= "\t\t\t<author>" . intval( $post->post_author ) . "</author>\n";
					$string .= "\t\t\t<post_date>" . $this->cdata( $post->post_date ) . "</post_date>\n";
					$string .= "\t\t\t<post_date_gmt>" . $this->cdata( $post->post_date_gmt ) . "</post_date_gmt>\n";
					$string .= "\t\t\t<post_content>" . $this->cdata( $post->post_content ) . "</post_content>\n";
					$string .= "\t\t\t<post_title>" . $this->cdata( $post->post_title ) . "</post_title>\n";
					$string .= "\t\t\t<post_excerpt>" . $this->cdata( $post->post_excerpt ) . "</post_excerpt>\n";
					$string .= "\t\t\t<post_status>" . $this->cdata( $post->post_status ) . "</post_status>\n";
					$string .= "\t\t\t<comment_status>" . $this->cdata( $post->comment_status ) . "</comment_status>\n";
					$string .= "\t\t\t<ping_status>" . $this->cdata( $post->ping_status ) . "</ping_status>\n";
					$string .= "\t\t\t<post_password>" . $this->cdata( $post->post_password ) . "</post_password>\n";
					$string .= "\t\t\t<post_name>" . $this->cdata( $post->post_title ) . "</post_name>\n";
					$string .= "\t\t\t<menu_order>" . intval( $post->menu_order ) . "</menu_order>\n";
					$string .= "\t\t\t<post_type>knowledgebase</post_type>\n";

					//Add post sections
					if( ! empty( $post_sections ) ){
						$string .= "\t\t\t<post_sections>\n";
							foreach( $post_sections as $post_section ){
								$string .= "\t\t\t\t<post_section_id>{$post_section->slug}</post_section_id>\n";
							}
						$string .= "\t\t\t</post_sections>\n";
					}

					//Add post tags
					if( ! empty( $post_tags ) ){
						$string .= "\t\t\t<post_tags>\n";
							foreach( $post_tags as $post_tag ){
								$string .= "\t\t\t\t<post_tag_id>{$post_tag->slug}</post_tag_id>\n";
							}
						$string .= "\t\t\t</post_tags>\n";
					}

					//Add post meta
					$string .= "\t\t\t<postmeta>\n";
					foreach( $meta_data as $meta_key => $meta_value ){
						$string .= "\t\t\t\t<metadata>\n";
						$string .= "\t\t\t\t\t<meta_key>" . $this->cdata( $meta_key ) . "</meta_key>\n";
						$string .= "\t\t\t\t\t<meta_value>" . $this->cdata( $meta_value[0] ) . "</meta_value>\n";
						$string .= "\t\t\t\t</metadata>\n";
					}
					$string .= "\t\t\t</postmeta>\n";
					$string .= "\t\t</post>\n";
				}
			}
			return $string;
		}


		/**
		 * Exports the widget items
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		private function export_widgets_items(){
			$string = '';

			$widgets_id_base = array(
				'basepress_nav_widget',
				'basepress_popular_articles_widget',
				'basepress_products_widget',
				'basepress_related_articles_widget',
				'basepress_sections_widget',
				'basepress_tag_cloud',
				'basepress_toc_widget'
			);

			$widget_instances = array();

			foreach( $widgets_id_base as $id_base ){
				$instances = get_option( 'widget_' . $id_base );
				if( ! empty( $instances ) ){
					foreach( $instances as $instance_id => $instance_data ){
						if( is_numeric( $instance_id ) ){
							$unique_instance_id = "{$id_base}-{$instance_id}";
							$widget_instances[$unique_instance_id] = array( 'id_base' => $id_base, 'data' => $instance_data );
						}
					}
				}
			}

			$sidebars_widgets = get_option('sidebars_widgets');
			$active_widgets = isset( $sidebars_widgets['basepress-sidebar'] ) ? $sidebars_widgets['basepress-sidebar'] : array();

			if( ! empty( $active_widgets ) ){
				foreach( $active_widgets as $widget ){
					if( isset( $widget_instances[$widget] ) ){
						$widget_data = serialize( $widget_instances[$widget]['data'] );
						$string .= "\t\t\t<widget>\n";
						$string .= "\t\t\t\t<base>" . $this->cdata( $widget_instances[$widget]['id_base'] ) . "</base>\n";
						$string .= "\t\t\t\t<data>" . $this->cdata( $widget_data ) . "</data>\n";
						$string .= "\t\t\t</widget>\n";
					}
				}
			}

			return $string;
		}


		/**
		 * Exports the plugin settings
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		private function export_settings(){
			$settings = get_option( 'basepress_settings' );
			$active_theme = isset( $settings['theme_style'] ) ? $settings['theme_style'] : false;
			$theme_settings = get_option( 'basepress_' . $active_theme . '_theme' );
			$string = '';
			if( $settings ){
				$string .= "\t\t<basepress_settings>" . $this->cdata( wp_json_encode( $settings ) ) . "</basepress_settings>\n";
			}
			if( $theme_settings ){
				$string .= "\t\t<basepress_{$active_theme}_theme>" . $this->cdata( wp_json_encode( $theme_settings ) ) . "</basepress_{$active_theme}_theme>\n";
			}
			return $string;
		}


		/**
		 * Adds the last xml closing elements to the export file
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 * @return string
		 */
		private function close_export_file( $data ){
			$export_file_name = isset( $data['exportFile'] ) ? $data['exportFile'] : false;
			$export_file_path = $this->export_dir . $export_file_name;
			$previous_object_name = $data['previousObjectName'];
			$export_string = '';

			if( file_exists( $export_file_path ) ){
				$export_string .= $this->close_parent_element( $previous_object_name );
				$export_string .= "</basepress-export>\n";

				file_put_contents( $export_file_path, $export_string, FILE_APPEND );
			}

			return $export_file_path;
		}


		/**
		 * Gets the link of the last created export file
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 */
		private function get_export_file_link( $data ){
			$link = $this->export_url . $data;
			$response_link = '';
			$response_link .= '<a class="button button-primary" href="' . $link . '" download>';
			$response_link .= __( 'Download Export File', 'basepress' );
			$response_link .= '</a>';

			wp_send_json( array( 'exportLink' => $response_link, 'exportFile' => $data ) );
		}


		/**
		 * Wraps string with CDATA mark up
		 *
		 * @since 1.0.0
		 *
		 * @param $str
		 * @return string
		 */
		private function cdata( $str ) {
			if ( ! seems_utf8( $str ) ) {
				$str = utf8_encode( $str );
			}
			$str = '<![CDATA[' . $str . ']]>';
			return $str;
		}
	}
}
