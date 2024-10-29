<?php
/**
 * Plugin Name: BasePress Migration Tools
 * Plug URI: https://www.codesavory.com
 * Description: Migrate BasePress Knowledge Base with ease
 * Version: 1.0.0
 * Requires PHP: 7.0
 * Author: codeSavory
 * Author URI: https://www.codesavory.com
 * Text Domain: basepress-migration
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! class_exists( 'BasePress_Migration_Tools' ) ){

	class BasePress_Migration_tools{

		public $version = '1.0.0';

		/**
		 * Stores the exporter class
		 * @var Basepress_Exporter
		 */
		private $exporter;

		/**
		 * Stores the importer class
		 * @var Basepress_Importer
		 */
		private $importer;


		/**
		 * The folder name where the exports are saved
		 * @var string
		 */
		private $export_folder = 'basepress-exports';


		/**
		 * Export directory path
		 * @var mixed|string
		 */
		private $export_dir;


		/**
		 * Export directory URL
		 * @var mixed|string
		 */
		private $export_url;


		/**
		 * The list of meta keys that should be exported for KBs
		 * @var string[]
		 */
		public $basepress_kb_meta_keys = array(
			'image',
			'sections_style',
			'visibility',
			'basepress_position',
			'basepress_restriction_roles'
		);


		/**
		 * The list of meta keys that should be exported for sections
		 * @var string[]
		 */
		public $basepress_section_meta_keys = array(
			'icon',
			'image',
			'basepress_position',
			'basepress_restriction_roles'
		);


		/**
		 * The list of meta keys that should be exported for articles
		 * @var string[]
		 */
		public $basepress_post_meta_keys = array(
			'basepress_post_icon',
			'basepress_restriction_roles',
			'basepress_votes',
			'basepress_score',
			'basepress_views'
		);


		/**
		 * BasePress_Migration_tools constructor.
		 *
		 * @since 1.0.0
		 */
		function __construct(){
			//Add admin menu
			add_action( 'admin_menu', array( $this, 'add_admin_page' ), 999 );

			//Enqueue admin scripts
			add_action( 'load-basepress_page_basepress_migration', array( $this, 'enqueue_scripts' ) );

			//Loads text domain
			add_action( 'init', array( $this, 'load_text_domain' ) );

			//Add Ajax callbacks and register text domain
			add_action( 'init', array( $this, 'add_ajax_callbacks' ) );

			//Hide admin notices
			add_action( 'basepress_kb_wizard_excluded_screens', array( $this, 'hide_admin_notices' ) );

			//Set uploads directory path and URL
			$uploads_dir = wp_upload_dir();
			if( isset( $uploads_dir['error'] ) && $uploads_dir['error'] ){
				$this->export_dir = $uploads_dir['error'];
				$this->export_url = $uploads_dir['error'];
			}
			else{
				$this->export_dir = $uploads_dir['basedir'] . '/' . $this->export_folder . '/';
				$this->export_url = $uploads_dir['baseurl'] . '/' . $this->export_folder  . '/';
			}

			require_once 'includes/exporter.php';
			require_once 'includes/importer.php';
			$this->exporter = new Basepress_Exporter( $this->export_folder );
			$this->importer = new Basepress_Importer( $this->export_folder );
		}


		/**
		 * Loads the plugin text domain
		 *
		 * @since 1.0.0
		 */
		function load_text_domain(){
			load_plugin_textdomain( 'basepress-migration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}


		/**
		 * Add the Migration tools admin page
		 *
		 * @since 1.0.0
		 */
		function add_admin_page(){
			add_submenu_page( 'basepress', 'BasePress ' . __( 'Migration Tools', 'basepress-migration' ), __( 'Migration Tools', 'basepress-migration' ), 'manage_options', 'basepress_migration', array( $this, 'display_admin_screen' ) );
		}


		/**
		 * Enqueue the necessary CSS and JS for the Import/Export page
		 *
		 * @since 1.0.0
		 */
		function enqueue_scripts(){
			wp_enqueue_script( 'basepress-import-export-js', plugins_url( 'assets/js/migration-tools.js', __FILE__ ), array( 'jquery', 'basepress-export-processor-js', 'basepress-import-processor-js' ), false, true );
			wp_enqueue_style( 'basepress-import-export-css', plugins_url( 'assets/css/migration-tools.css', __FILE__ ) );
			wp_enqueue_script( 'basepress-export-processor-js',  plugins_url( 'assets/js/export-processor.js', __FILE__ ), array( 'jquery' ), false, true );
			wp_enqueue_script( 'basepress-import-processor-js', plugins_url( 'assets/js/import-processor.js', __FILE__ ), array( 'jquery' ), false, true );
		}


		/**
		 * Register our Ajax callbacks
		 *
		 * @since 1.0.0
		 */
		public function add_ajax_callbacks(){
			add_action( 'wp_ajax_basepress_get_updated_import_list', array( $this, 'basepress_get_updated_import_list' ) );
			add_action( 'wp_ajax_basepress_delete_export_file', array( $this, 'basepress_delete_export_file' ) );
			add_action( 'wp_ajax_basepress_import_new_file', array( $this, 'basepress_import_new_file' ) );
			add_action( 'wp_ajax_basepress_delete_all_data', array( $this, 'basepress_delete_all_data' ) );
		}


		/**
		 * Hide admin notices
		 *
		 * @since 1.0.0
		 *
		 * @param $screens
		 * @return mixed
		 */
		public function hide_admin_notices( $screens ){
			$screens[] = 'basepress_page_basepress_migration';
			return $screens;
		}


		/**
		 * Display the admin screen
		 *
		 * @since 1.0.0
		 */
		function display_admin_screen(){
			if( ! current_user_can( 'manage_options' ) ){
				wp_die( __( 'Sorry, you are not allowed to access this page.', 'basepress-migration' ), 403 );
			}

			?>
			<div class="wrap">
				<h1><?php echo 'BasePress ' . __( 'Migration Tools', 'basepress-migration' ); ?></h1>
				<div class="basepress-tabs">

					<!-- Export Tab-->
					<div class="basepress-tab">
						<input id="basepress_export" name="css-tabs" class="basepress-tab-switch" type="radio" checked="checked">
						<label for="basepress_export" class="basepress-tab-label"><?php _e( 'Export', 'basepress-migration' ); ?></label>
						<div class="basepress-tab-content">
							<h1>Export</h1>

							<p><input id="basepress-export-qty" type="number" value='25' min="1" max="100">
								<span class="description"><?php _e( 'Sets the amount of items processed at once. If the export fails lower this value and try again.', 'basepress-migration' ); ?></span>
							</p>
							<p><button id="basepress-export" class="button button-primary"><span class="dashicons dashicons-update hidden-icon"></span> <?php _e( 'Export', 'basepress-migration' ); ?></button></p>
							<div id="basepress-export-progress-bar" class="basepress-progress-bar"></div>
							<div id="basepress-export-file-link"></div>
						</div>
					</div>

					<!-- Import Tab-->
					<div class="basepress-tab">
						<input id="basepress_import" name="css-tabs" class="basepress-tab-switch" type="radio">
						<label for="basepress_import" class="basepress-tab-label"><?php _e( 'Import', 'basepress-migration' ); ?></label>
						<div class="basepress-tab-content">
							<h1>Import</h1>
							<div class="basepress-import-notice">
								<p><?php _e( 'Make a backup of your site before importing so you can restore the site in case something goes wrong.', 'basepress-migration' ); ?></p>
							</div>
							<div id="basepress-import-files">

								<p>
									<span class="description"><?php _e( 'Sets the amount of items processed at once. If the import fails lower this value and try again.', 'basepress-migration' ); ?></span>
									<br><input id="basepress-import-qty" type="number" value='25' min="1" max="100">
								</p>
								<p>
									<span class="description"><?php _e( 'Select the author used for the import if the exported user doesn\'t exist on this site.', 'basepress-migration' ); ?></span>
									<br><?php $this->get_authors_on_site(); ?>
								</p>
								<div id="basepress-import-progress-bar" class="basepress-progress-bar"></div>

								<hr>
								<h3><?php _e( 'Import a new file', 'basepress-migration' ); ?></h3>
								<input type="file" id="basepress-import-new-file" name="basepress-import-new-file"><br>
								<button id="basepress-import-new" class="button button-primary"><span class="dashicons dashicons-update hidden-icon"></span> <?php _e( 'Import New', 'basepress-migration' ); ?></button>
								<hr>
								<h3><?php _e( 'Import an archived file', 'basepress-migration' ); ?></h3>
								<p id="basepress-import-file-delete-all">
									<span><span class="dashicons dashicons-no"></span><?php _e( 'Delete all', 'basepress-migration' ); ?></span>
								</p>
								<ul id="basepress-import-files-list">
									<?php $this->get_import_list() ?>
								</ul>
								<button id="basepress-import-selected" class="button button-primary"><span class="dashicons dashicons-update hidden-icon"></span> <?php _e( 'Import Selected', 'basepress-migration' ); ?></button>
							</div>
						</div>
					</div>
					<!-- Delete data tab -->
					<div class="basepress-tab">
						<input id="basepress_delete_data" name="css-tabs" class="basepress-tab-switch" type="radio">
						<label for="basepress_delete_data" class="basepress-tab-label"><?php _e( 'Delete all data', 'basepress-migration' ); ?></label>
						<div class="basepress-tab-content">
							<h1>Delete all data</h1>
							<p>
								<button id="basepress-delete-data" class="button button-primary"><span class="dashicons dashicons-update hidden-icon"></span> <?php _e( 'Delete', 'basepress-migration' ); ?></button>
							</p>
						</div>
					</div>
				</div>
			</div>
			<?php
		}


		/**
		 * Get list of files to import from the export directory
		 *
		 * @since 1.0.0
		 */
		private function get_import_list(){
			$import_dir = $this->export_dir;
			$import_url = $this->export_url;
			$files = array();

			if( file_exists( $import_dir ) ){
				$files = array_diff( scandir( $import_dir, SCANDIR_SORT_ASCENDING ), array( '.', '..', '.DS_Store' ) );
			}

			if( ! empty( $files ) ){
				foreach( $files as $file ){
					echo '<li class="basepress-import-file">';
					echo '<div class="basepress-import-file-name">';
					echo '<input type="radio" name="import-file" value="'. esc_attr( $file ) .'">';
					echo esc_html( $file );
					echo '</div>';
					echo '<div class="basepress-import-file-extras">';
					echo date("F d Y H:i:s", filectime( $import_dir.$file ) );
					echo '<a class="basepress-import-file-download" href="' . esc_url( $import_url.$file ) . '" download><span class="dashicons dashicons-download"></span> ' . __( 'Download', 'basepress' ) . '</a>';
					echo '<span class="basepress-import-file-delete" data-delete-file="' . esc_attr( $file ) . '"><span class="dashicons dashicons-no"></span> ' . __( 'Delete', 'basepress' ) . '</span>';
					echo '</div>';
					echo '</li>';
				}
			}
			else{
				echo '<li class="basepress-import-file">';
				echo __( 'There are no files in the archive. Import a new file.', 'basepress-migration' );
				echo '</li>';
			}
		}


		/**
		 * Get the list of authors for the KB articles
		 *
		 * @since 1.0.0
		 */
		function get_authors_on_site(){
			$users_query = new WP_User_Query(
				array(
					'who' => 'authors',
				) );

			echo '<select id="default-author">';
			if( ! empty( $users_query->get_results() ) ){
				foreach( $users_query->get_results() as $user ){
					echo '<option value="' . $user->ID . '">' . esc_html( $user->display_name ) . '</option>';
				}
			}
			else{
				echo '<option value="" disabled>' . __( 'No Authors found', 'basepress-migration' ) . '</option>';
			}
			echo '</select>';

		}


		/**
		 * Ajax callback to get an updated list of files to import
		 *
		 * @since 1.0.0
		 */
		public function basepress_get_updated_import_list(){
			ob_start();
			$this->get_import_list();
			wp_send_json( ob_get_clean() );
		}


		/**
		 * Ajax Callback to delete an export file
		 *
		 * @since 1.0.0
		 */
		public function basepress_delete_export_file(){
			$import_dir = $this->export_dir;
			$import_file = isset( $_REQUEST['packet']['exportFile'] ) ? sanitize_text_field( $_REQUEST['packet']['exportFile'] ) : '';
			$import_files = array();
			$deleted = false;

			if( 'all' == $import_file ){
				if( file_exists( $import_dir ) ){
					$files = array_diff( scandir( $import_dir ), array( '.', '..', '.DS_Store' ) );
				}

				if( ! empty( $files ) ){
					$import_files = $files;
				}
			}
			else{
				$import_files[] = $import_file;
			}

			foreach( $import_files as $import_file ){
				$file_path = $this->export_dir . $import_file;
				if( $import_file && file_exists( $file_path ) ){
					$deleted = unlink( $file_path );
				}
			}
			wp_send_json( $deleted );
		}


		/**
		 * Ajax callback to upload a new import file
		 *
		 * @since 1.0.0
		 */
		public function basepress_import_new_file(){

			$upload_bytes = wp_convert_hr_to_bytes( ini_get( 'upload_max_filesize' ) );
			$post_bytes = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );

			$max_upload_size = $upload_bytes < $post_bytes ? $upload_bytes : $post_bytes;
			$max_upload_size_readable = esc_html( size_format( $max_upload_size ) );

			$fileErrors = array(
				"There is no error, the file uploaded with success",
				"The uploaded file exceeds the upload_max_files in server settings",
				"The uploaded file exceeds the MAX_FILE_SIZE from html form",
				"The uploaded file uploaded only partially",
				"No file was uploaded",
				"Missing a temporary folder",
				"Failed to write file to disk",
				"A PHP extension stopped file to upload"
			);

			$data = isset( $_FILES ) ? $_FILES : array();

			$response = array();

			$upload_path = $this->export_dir;

			if( ! file_exists( $upload_path )){
				mkdir( $upload_path );
			}

			$fileName = $data["basepress_upload_file"]["name"];
			$fileName = str_replace(" ", "_", $fileName);

			$temp_name = $data["basepress_upload_file"]["tmp_name"];
			$file_size = $data["basepress_upload_file"]["size"];
			$fileError = $data["basepress_upload_file"]["error"];
			$targetPath = $upload_path;
			$response["filename"] = $fileName;
			$response["file_size"] = $file_size;


			if( $fileError > 0 ){
				$response["response"] = "ERROR";
				$response["error"] = $fileErrors[ $fileError ];
			} else {
				if( file_exists($targetPath . $fileName ) ){
					$response["response"] = "ERROR";
					$response["error"] = "File already exists.";
				} else {
					if( $file_size <= $max_upload_size ){
						if( move_uploaded_file( $temp_name, $targetPath . $fileName ) ){
							$response["response"] = "SUCCESS";
							$response["filename"] =  $fileName;
						} else {
							$response["response"] = "ERROR";
							$response["error"]= "Upload Failed.";
						}

					} else {
						$response["response"] = 'ERROR';
						$response["error"]= __( 'File is too large. Max file size is', 'basepress-migration' ) . ' ' . $max_upload_size_readable . ".";
					}
				}
			}

			wp_send_json( $response );
		}


		/**
		 * Deletes all BasePress data from the database
		 *
		 * @since 1.0.0
		 */
		public function basepress_delete_all_data(){
			global $wpdb;

			//Deletes Posts, revisions, post meta and terms relationships
			$deleted = $wpdb->query( "
				DELETE a,b,c,d
		    FROM {$wpdb->posts} a
		    LEFT JOIN {$wpdb->term_relationships} b
		        ON (a.ID = b.object_id)
		    LEFT JOIN {$wpdb->postmeta} c
		        ON (a.ID = c.post_id)
		    LEFT JOIN {$wpdb->posts} d
		        ON (a.ID = d.post_parent)
		    WHERE a.post_type = 'knowledgebase';
			" );

			/*
			 * Remove all KBs and Sections
			 */
			foreach( array( 'knowledgebase_cat', 'knowledgebase_tag' ) as $taxonomy ){
				// Prepare & execute SQL
				$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('%s') ORDER BY t.name ASC", $taxonomy ) );

				// Delete Terms
				if( $terms ){
					foreach( $terms as $term ){
						$wpdb->delete(
							$wpdb->term_taxonomy, array(
								'term_taxonomy_id' => $term->term_taxonomy_id,
							)
						);
						$wpdb->delete(
							$wpdb->term_relationships, array(
								'term_taxonomy_id' => $term->term_taxonomy_id,
							)
						);
						$wpdb->delete(
							$wpdb->termmeta, array(
								'term_id' => $term->term_id,
							)
						);
						$wpdb->delete(
							$wpdb->terms, array(
								'term_id' => $term->term_id,
							)
						);
					}
				}

				// Delete Taxonomy
				$wpdb->delete(
					$wpdb->term_taxonomy, array(
					'taxonomy' => $taxonomy,
				), array( '%s' )
				);
			}

			//Delete entry page
			$options = get_option( 'basepress_settings');
			$entry_page_id = isset( $options['entry_page'] ) ? $options['entry_page'] : false;
			if( ! empty( $entry_page_id ) ){
				wp_delete_post( (int)$entry_page_id, true );
			}

			//Delete options
			delete_option( 'basepress_settings' );
			delete_option( 'basepress_ver' );
			delete_option( 'basepress_db_ver' );
			delete_option( 'basepress_plan' );
			delete_option( 'basepress_run_wizard' );
			delete_option( 'widget_basepress_products_widget' );
			delete_option( 'widget_basepress_sections_widget' );
			delete_option( 'widget_basepress_related_articles_widget' );
			delete_option( 'widget_basepress_popular_articles_widget' );
			delete_option( 'widget_basepress_toc_widget' );
			delete_option( 'widget_basepress_tag_cloud' );
			delete_option( 'widget_basepress_nav_widget' );
			delete_option( 'knowledgebase_cat_children' );
			delete_option( 'basepress_modern_theme' );
			delete_option( 'basepress_run_wizard' );

			//Remove sidebars widgets
			$sidebars = get_option( 'sidebars_widgets' );
			if( isset( $sidebars['basepress-sidebar'] ) ){
				unset( $sidebars['basepress-sidebar'] );
			}
			update_option( 'sidebars_widgets', $sidebars );

			if( false !== $deleted ){
				wp_send_json( __( 'Data deleted successfully!', 'basepress-migration' ) );
			}
			else{
				wp_send_json( __( 'Something didn\'t work. Please try again.', 'basepress-migration' ) );
			}
		}
	}

	global $basePress_migration_tools;
	$basePress_migration_tools = new BasePress_Migration_Tools();
}