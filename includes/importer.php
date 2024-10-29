<?php
// Exit if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Basepress_importer' ) ){

	class Basepress_importer{

		private $import_dir = '';
		private $origin_base_url = '';
		private $destination_base_url = '';


		/**
		 * Basepress_importer constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param $import_folder
		 */
		public function __construct( $import_folder ){
			//Add the Ajax callback for our exporter
			add_action( 'init', function(){
				add_action( 'wp_ajax_basepress_kb_import', array( $this, 'basepress_kb_import' ) );
			} );

			$uploads_dir = wp_upload_dir();
			if( isset( $uploads_dir['error'] ) && $uploads_dir['error'] ){
				$this->import_dir = $uploads_dir['error'];
			}
			else{
				$this->import_dir = $uploads_dir['basedir'] . '/' . $import_folder .'/';
			}
		}


		/**
		 * The Ajax callback for the importer
		 *
		 * @since 1.0.0
		 */
		public function basepress_kb_import(){

			$process = $_POST['process'];
			$data = $_POST['packet'];

			switch( $process ){
				case 'get_import_objects':
					$this->get_import_objects( $data );
					break;
				case 'import_items':
					$this->import_items( $data );
					break;
				default:
					wp_die();
			}
		}


		/**
		 * Gets the list of objects to import
		 *
		 * @since 1.0.0
		 */
		private function get_import_objects( $filename ){

			$object_types = array();
			if( ! file_exists( $this->import_dir ) ){
				//If the import directory doesn't exit we send the error as response
				$object_types = $this->import_dir;
			}
			else{
				$import_file = $this->import_dir . $filename;
				$import_xml = $this->load_xml( $import_file );

				if( ! empty( $import_xml ) ){
					foreach( $import_xml as $object_type => $items ){
						switch( $object_type ){
							//Skip non necessary objects
							case 'exporter_ver':
							case 'origin_base_url':
							case 'authors':
								continue 2;
							//These are handled as a single item each
							case 'entry_page':
							case 'widgets':
							case 'settings':
								$object_types[$object_type] = array( 1 );
								break;
							//Store the item id for authors, KBs, sections and posts
							default:
								$object_types[$object_type] = array();
								foreach( $items as $item ){
									$object_types[ $object_type ][] = (int)$item->id;
								}
						}
					}
				}
			}

			wp_send_json( $object_types );
		}


		/**
		 * Main function to import the items to the export file.
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 */
		private function import_items( $data ){
			$import_file = $this->import_dir . sanitize_text_field( $data['importFile'] );
			$current_object_name = $data['currentObjectName'];
			$items = $data['items'];
			$default_author = $data['defaultAuthor'];

			switch( $current_object_name ){
				case 'kbs':
					$this->import_kb_items( $items, $import_file );
					break;
				case 'sections':
					$this->import_section_items( $items, $import_file );
					break;
				case 'tags':
					$this->import_tag_items( $items, $import_file );
					break;
				case 'posts':
					$this->import_post_items( $items, $import_file, $default_author );
					break;
				case 'widgets':
					$this->import_widgets( $import_file );
					break;
				//The entry page is saved with the settings
				case 'settings':
					$this->import_settings( $import_file );
					//Flush rewrite rules after the settings have been saved
					add_action( 'shutdown', function(){
						flush_rewrite_rules();
					});
					break;
			}

			wp_send_json( $data );
		}


		/**
		 * Imports the KB items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @return string
		 */
		private function import_kb_items( $items, $import_file ){
			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			global $basePress_migration_tools;

			$basepress_meta_keys = $basePress_migration_tools->basepress_kb_meta_keys;

			foreach( $items as $item_id ){
				$kb = $xml->xpath( "//kbs/kb[id='{$item_id}']" );
				if( ! empty( $kb ) ){
					//Create array of args for new KB
					$kb_data = $this->simpleXmlToArray( $kb[0] );

					//Prepare KBs meta data
					if( isset( $kb_data['termmeta'] ) && isset( $kb_data['termmeta']['metadata'] ) && ! empty( $kb_data['termmeta']['metadata'] ) ){
						$kb_meta = array();
						foreach( $kb_data['termmeta']['metadata'] as $metadata ){
							if( in_array( $metadata['meta_key'], $basepress_meta_keys ) ){
								$kb_meta[ $metadata['meta_key'] ] = $metadata['meta_value'];
							}
						}
					}

					$new_kb = wp_insert_term(
						$kb_data['name'],
						'knowledgebase_cat',
						array(
							'description' => $kb_data['description'],
							'slug'        => $kb_data['slug'],
						)
					);

					if( ! empty( $new_kb ) && ! is_wp_error( $new_kb ) ){
						foreach( $kb_meta as $meta_key => $meta_value ){
							$meta_value = maybe_unserialize( $meta_value );
							if( 'image' == $meta_key ){
								$meta_value = $this->maybe_update_links( $meta_value );
							}
							update_term_meta( $new_kb['term_id'], $meta_key, $meta_value );
						}
					}

				}
			}
		}


		/**
		 * Imports the section items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @param $import_file
		 */
		private function import_section_items( $items, $import_file ){
			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			global $basePress_migration_tools;

			$basepress_meta_keys = $basePress_migration_tools->basepress_section_meta_keys;

			foreach( $items as $item_id ){
				$section = $xml->xpath( "//sections/section[id='{$item_id}']" );
				if( ! empty( $section ) ){
					//Create array of args for new KB
					$section_data = $this->simpleXmlToArray( $section[0] );

					//Set the meta_input for the new article
					if( isset( $section_data['termmeta'] ) && isset( $section_data['termmeta']['metadata'] ) && ! empty( $section_data['termmeta']['metadata'] ) ){
						$section_meta = array();
						foreach( $section_data['termmeta']['metadata'] as $metadata ){
							if( in_array( $metadata['meta_key'], $basepress_meta_keys ) ){
								$section_meta[ $metadata['meta_key'] ] = $metadata['meta_value'];
							}
						}
					}

					$parent_term = get_term_by( 'slug', $section_data['parent'], 'knowledgebase_cat' );

					$new_section = wp_insert_term(
						$section_data['name'],
						'knowledgebase_cat',
						array(
							'description' => $section_data['description'],
							'slug'        => $section_data['slug'],
							'parent'      => $parent_term->term_id,
						)
					);

					if( ! empty( $new_section ) && ! is_wp_error( $new_section ) ){
						foreach( $section_meta as $meta_key => $meta_value ){
							$meta_value = maybe_unserialize( $meta_value );
							if( 'image' == $meta_key ){
								$meta_value = $this->maybe_update_links( $meta_value );
							}
							update_term_meta( $new_section['term_id'], $meta_key, $meta_value );
						}
					}

				}
			}
		}


		/**
		 * Imports the tag items
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @param $import_file
		 */
		private function import_tag_items( $items, $import_file ){
			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			foreach( $items as $item_id ){
				$tag = $xml->xpath( "//tags/tag[id='{$item_id}']" );
				if( ! empty( $section ) ){
					//Create array of args for new KB
					$tag_data = $this->simpleXmlToArray( $tag[0] );

					$new_tag = wp_insert_term(
						$tag_data['name'],
						'knowledgebase_tag',
						array(
							'description' => $tag_data['description'],
							'slug'        => $tag_data['slug'],
						)
					);
				}
			}
		}

		/**
		 * Imports the articles
		 *
		 * @since 1.0.0
		 *
		 * @param $items
		 * @param $import_file
		 * @param $default_author
		 */
		private function import_post_items( $items, $import_file, $default_author ){
			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			global $basePress_migration_tools;

			$basepress_meta_keys = $basePress_migration_tools->basepress_post_meta_keys;
			$new_author = false;

			foreach( $items as $item_id ){
				if( 3880 == $item_id ){
					$test = 1;
				}
				$post = $xml->xpath( "//posts/post[id='{$item_id}']" );
				if( ! empty( $post ) ){

					//Create array of args for new article
					$post_data = $this->simpleXmlToArray( $post[0] );

					//Update image links in articles content
					if( ! empty( $post_data['post_content'] ) ){
						$post_data['post_content'] = $this->maybe_update_links( $post_data['post_content'] );
					}

					$post_data['post_content'] = wp_slash( $post_data['post_content'] );

					//Get the new author if it exists on the destination site
					if( isset( $post_data['author'] ) && ! empty( $post_data['author'] ) ){
						$author = $xml->xpath( "//authors/author[id='{$post_data['author']}']" );
						$new_author = get_user_by( 'login', (string)$author[0]->author_login );
					}

					//Set the author ID for the new article
					$post_data['author'] = $new_author ? $new_author->ID : (int)$default_author;

					//Set the meta_data for the new article
					if( isset( $post_data['postmeta'] ) && isset( $post_data['postmeta']['metadata'] ) && ! empty( $post_data['postmeta']['metadata'] ) ){
						$post_data['meta_input'] = array();
						foreach( $post_data['postmeta']['metadata'] as $metadata ){
							if( in_array( $metadata['meta_key'], $basepress_meta_keys ) ){
								$post_data['meta_input'][ $metadata['meta_key'] ] = maybe_unserialize( $metadata['meta_value'] );
							}
						}
					}

					//Unset unnecessary keys
					unset( $post_data['id'] );
					unset( $post_data['postmeta'] );

					//Set missing data
					$post_data['post_type'] = 'knowledgebase';

					//Save the article
					$post_id = wp_insert_post( $post_data, true );

					if( ! empty( $post_id ) && ! is_wp_error( $post_id ) ){
						//Set the post sections
						if( isset( $post_data['post_sections'] ) && ! empty( $post_data['post_sections'] ) ){
							$post_sections = implode( $post_data['post_sections'] );
							wp_set_object_terms( $post_id, $post_sections, 'knowledgebase_cat' );
						}

						//Set the post tags
						if( isset( $post_data['post_stags'] ) && ! empty( $post_data['post_stags'] ) ){
							$post_sections = implode( $post_data['post_stags'] );
							wp_set_object_terms( $post_id, $post_sections, 'knowledgebase_tag' );
						}
					}
				}
			}
		}


		/**
		 * Imports the widgets
		 *
		 * @since 1.0.0
		 *
		 * @param $import_file
		 */
		private function import_widgets( $import_file ){

			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			$sidebars_widgets = get_option( 'sidebars_widgets', array() );

			// Create the sidebar if it doesn't exist.
			if ( ! isset( $sidebars_widgets['basepress-sidebar'] ) ){
				$sidebars_widgets['basepress-sidebar'] = array();
			}

			$widgets = $this->simpleXmlToArray( $xml->widgets );

			foreach( $widgets['widget'] as $widget ){
				$widget_base = $widget['base'];
				$widget_data = maybe_unserialize( $widget['data'] );

				$widget_instances = get_option( 'widget_' . $widget_base, array() );

				// Retrieve the key of the next widget instance
				$numeric_keys = array_filter( array_keys( $widget_instances ), 'is_int' );
				$next_key = $numeric_keys ? max( $numeric_keys ) + 1 : 2;

				$sidebars_widgets['basepress-sidebar'][] = $widget_base . '-' . $next_key;

				// Add the new widget instance
				$widget_instances[ $next_key ] = $widget_data;

				update_option( 'widget_' . $widget_base, $widget_instances );
			}

			update_option( 'sidebars_widgets', $sidebars_widgets );
		}


		/**
		 * Imports the settings
		 *
		 * @since 1.0.0
		 *
		 * @param $import_file
		 */
		private function import_settings( $import_file ){
			$xml = $this->load_xml( $import_file );

			if( empty( $xml ) ){
				return;
			}

			$settings = $this->simpleXmlToArray( $xml->settings );
			$settings = array_map( function( $data ){
				$data = json_decode( $data, true );
				return $data;
			}, $settings );

			$entry_page = $this->simpleXmlToArray( $xml->entry_page );
			$entry_page_id = wp_insert_post( array(
				'post_title'   => $entry_page['post_title'],
				'post_name'    => $entry_page['post_name'],
				'post_content' => $this->maybe_update_links( $entry_page['post_content'] ),
				'post_status'  => 'publish',
				'post_type'    => 'page'
			), true );

			if( ! empty( $entry_page_id ) && ! is_wp_error( $entry_page_id ) ){
				$settings['basepress_settings']['entry_page'] = $entry_page_id;
			}

			foreach( $settings as $name => $data ){
				update_option( $name, $data, true );
			}

			//Disable the Wizard as the setup was done from the import
			delete_option( 'basepress_run_wizard' );
		}


		/**
		 * Loads the XML file for import
		 *
		 * @since 1.0.0
		 *
		 * @param $import_file
		 * @return false|SimpleXMLElement|string
		 */
		private function load_xml( $import_file ){
			if( file_exists( $import_file ) ){
				$xml = simplexml_load_file( $import_file );
				$this->destination_base_url = get_home_url();
				$this->origin_base_url = $xml->origin_base_url;
				return $xml;
			}
			return false;
		}


		/**
		 * Converts the XML data to Array
		 *
		 * @since 1.0.0
		 * 
		 * @param null $sxe
		 * @return array
		 */
		function simpleXmlToArray($sxe = null) {
			if( ! $sxe instanceOf SimpleXMLElement ){
				return array();
			}

			$extract = array();

			foreach( $sxe->children() as $key => $value ){
				if( array_key_exists( $key, $extract ) ){
					if( ! isset( $extract[$key][0] ) ){
						$tmp_extract = $extract[$key];
						$extract[$key] = array();
						$extract[$key][0] = $tmp_extract;
					} else
						$extract[$key] = (array) $extract[$key];
				}

				if( $value->count() ){
					if( isset( $extract[$key] ) && is_array( $extract[$key] ) ){
						$extract[$key][] = $this->simpleXmlToArray($value);
					}
					else{
						$extract[$key] = $this->simpleXmlToArray($value);
					}
				}
				else{
					if( isset( $extract[$key] ) && is_array( $extract[$key] ) ){
						$extract[$key][] = strval( $value );
					}
					else{
						$extract[$key] = strval( $value );
					}
				}
			}

			return $extract;
		}


		/**
		 * Updates links point to the old domain to the new one
		 *
		 * @since 1.0.0
		 *
		 * @param $data
		 * @return array|string|string[]|string[][]
		 */
		private function maybe_update_links( $data ){
			if(
				empty( $this->destination_base_url ) ||
				empty( $this->origin_base_url ) ||
				empty( $data )
			){
				return $data;
			}

			//If the data is an array, process all items
			if( is_array( $data ) ){
				return array_map(
					function( $item ){
						return str_replace( $this->origin_base_url, $this->destination_base_url, $item );
					}, $data
				);
			}
			else{
				return str_replace( $this->origin_base_url, $this->destination_base_url, $data );
			}
		}
	}
}

