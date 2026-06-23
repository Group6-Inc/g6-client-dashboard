<?php
/**
 * G6 Asset Manager — theme-folder asset uploader and library manager.
 * Enabled via Developer Tools tab in G6 Dashboard Settings.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function g6_get_theme_asset_folder_options(): array {
	return [
		'assets/icons'  => 'Icons',
		'assets/logos'  => 'Logos',
		'assets/images' => 'General Images',
		'assets/js'     => 'JavaScript',
		'assets/css'    => 'CSS',
	];
}

class G6_Asset_Manager {

	private string $metadata_file;
	private string $current_dynamic_path = '';

	public function __construct() {
		$this->metadata_file = get_stylesheet_directory() . '/tam-metadata.json';
		add_action( 'admin_init',               [ $this, 'protect_metadata_file' ] );
		add_action( 'admin_menu',               [ $this, 'add_admin_page' ] );
		add_action( 'wp_ajax_tam_upload_files', [ $this, 'ajax_upload_files' ] );
		add_action( 'wp_ajax_tam_delete_asset', [ $this, 'ajax_delete_asset' ] );
		add_action( 'wp_ajax_tam_add_to_library', [ $this, 'ajax_add_to_library' ] );
		add_action( 'wp_ajax_tam_bulk_action',  [ $this, 'ajax_bulk_action' ] );
		add_action( 'wp_ajax_tam_get_table_body', [ $this, 'ajax_get_table_body' ] );
		add_filter( 'wp_get_attachment_url',    [ $this, 'fix_theme_asset_url' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset',[ $this, 'fix_theme_asset_srcset_urls' ], 10, 5 );
	}

	public function add_admin_page(): void {
		if ( ! g6_is_group6_user() ) {
			return;
		}
		add_theme_page(
			'G6 Asset Manager',
			'G6 Asset Manager',
			'manage_options',
			'upload-theme-assets',
			[ $this, 'render_page' ]
		);
	}

	private function check_permissions( string $nonce_action, string $nonce_key = '_wpnonce' ): void {
		if ( ! check_ajax_referer( $nonce_action, $nonce_key, false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}
	}

	private function read_metadata(): array {
		if ( ! file_exists( $this->metadata_file ) ) {
			return [];
		}
		$content = file_get_contents( $this->metadata_file );
		return json_decode( $content, true ) ?: [];
	}

	private function write_metadata( array $data ): void {
		file_put_contents( $this->metadata_file, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	public function ajax_upload_files(): void {
		$this->check_permissions( 'tam_nonce' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$folder_options      = g6_get_theme_asset_folder_options();
		$selected_folder_key = $_POST['destination_folder'] ?? key( $folder_options );

		if ( $selected_folder_key === 'custom' ) {
			$custom_path = wp_unslash( sanitize_text_field( $_POST['custom_path_input'] ?? '' ) );
			$custom_path = trim( str_replace( '\\', '/', $custom_path ), '/' );
			if ( empty( $custom_path ) || strpos( $custom_path, '..' ) !== false ) {
				wp_send_json_error( [ 'message' => 'Invalid or insecure custom path specified.' ] );
			}
			$selected_folder = $custom_path;
		} else {
			$selected_folder = isset( $folder_options[ $selected_folder_key ] ) ? $selected_folder_key : key( $folder_options );
		}

		$files = $_FILES['theme_assets'] ?? [];
		if ( empty( $files ) ) {
			wp_send_json_error( [ 'message' => 'No files were uploaded.' ] );
		}

		$files_uploaded_count = 0;

		if ( isset( $_POST['upload_and_add'] ) ) {
			$this->current_dynamic_path = $selected_folder;
			add_filter( 'upload_dir',   [ $this, 'dynamic_upload_dir_filter' ] );
			add_filter( 'upload_mimes', [ $this, 'allow_theme_asset_mimes' ] );

			foreach ( $files['name'] as $key => $value ) {
				if ( $files['name'][ $key ] ) {
					$file     = [
						'name'     => $files['name'][ $key ],
						'type'     => $files['type'][ $key ],
						'tmp_name' => $files['tmp_name'][ $key ],
						'error'    => $files['error'][ $key ],
						'size'     => $files['size'][ $key ],
					];
					$movefile = wp_handle_upload( $file, [ 'test_form' => false ] );
					if ( $movefile && ! isset( $movefile['error'] ) ) {
						$attachment = [
							'guid'           => $movefile['url'],
							'post_mime_type' => $movefile['type'],
							'post_title'     => sanitize_file_name( preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ) ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						];
						$attach_id  = wp_insert_attachment( $attachment, $movefile['file'] );
						if ( ! is_wp_error( $attach_id ) ) {
							$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
							wp_update_attachment_metadata( $attach_id, $attach_data );
						}
						$files_uploaded_count++;
					}
				}
			}

			remove_filter( 'upload_dir',   [ $this, 'dynamic_upload_dir_filter' ] );
			remove_filter( 'upload_mimes', [ $this, 'allow_theme_asset_mimes' ] );
			wp_send_json_success( [ 'message' => $files_uploaded_count . ' asset(s) uploaded and added to the Media Library.' ] );

		} elseif ( isset( $_POST['only_upload'] ) ) {
			$metadata          = $this->read_metadata();
			$target_dir        = get_stylesheet_directory() . '/' . $selected_folder . '/';
			$allowed_extensions = [ 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp', 'js', 'css' ];

			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			foreach ( $files['name'] as $key => $value ) {
				if ( $files['error'][ $key ] === UPLOAD_ERR_OK ) {
					$file_name = sanitize_file_name( basename( $files['name'][ $key ] ) );
					$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
					if ( in_array( $file_ext, $allowed_extensions, true ) ) {
						if ( move_uploaded_file( $files['tmp_name'][ $key ], $target_dir . $file_name ) ) {
							$relative_path             = $selected_folder . '/' . $file_name;
							$metadata[ $relative_path ] = [
								'uploader_id' => get_current_user_id(),
								'timestamp'   => current_time( 'mysql', true ),
							];
							$files_uploaded_count++;
						}
					}
				}
			}
			$this->write_metadata( $metadata );
			wp_send_json_success( [ 'message' => $files_uploaded_count . ' asset(s) uploaded directly to the server.' ] );
		}
	}

	public function ajax_delete_asset(): void {
		$this->check_permissions( 'tam_nonce' );
		$type     = sanitize_text_field( $_POST['type'] ?? '' );
		$id       = $_POST['id'] ?? '';
		$metadata = $this->read_metadata();

		if ( $type === 'db' ) {
			$attachment_id = absint( $id );
			if ( $this->is_a_theme_asset( $attachment_id ) ) {
				$guid          = get_the_guid( $attachment_id );
				$relative_path = str_replace( get_stylesheet_directory_uri() . '/', '', $guid );
				unset( $metadata[ $relative_path ] );
				$this->write_metadata( $metadata );
				$this->current_dynamic_path = dirname( $relative_path );
				add_filter( 'upload_dir', [ $this, 'dynamic_upload_dir_filter' ] );
			}
			$result = wp_delete_attachment( $attachment_id, true );
			remove_filter( 'upload_dir', [ $this, 'dynamic_upload_dir_filter' ] );
			if ( $result ) {
				wp_send_json_success( [ 'message' => 'Asset deleted from library and server.' ] );
			}
		} elseif ( $type === 'fs' ) {
			$file_to_delete = wp_unslash( $id );
			unset( $metadata[ $file_to_delete ] );
			$this->write_metadata( $metadata );
			if ( strpos( $file_to_delete, '..' ) === false && ! empty( $file_to_delete ) ) {
				$full_path = get_stylesheet_directory() . '/' . $file_to_delete;
				if ( file_exists( $full_path ) && unlink( $full_path ) ) {
					wp_send_json_success( [ 'message' => 'File deleted from server.' ] );
				}
			}
		}
		wp_send_json_error( [ 'message' => 'Could not delete asset.' ] );
	}

	public function ajax_add_to_library(): void {
		$this->check_permissions( 'tam_nonce' );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$relative_path = wp_unslash( sanitize_text_field( $_POST['id'] ?? '' ) );
		$full_path     = get_stylesheet_directory() . '/' . $relative_path;

		if ( empty( $relative_path ) || strpos( $relative_path, '..' ) !== false || ! file_exists( $full_path ) ) {
			wp_send_json_error( [ 'message' => 'Invalid file specified.' ] );
		}

		$this->current_dynamic_path = dirname( $relative_path );
		add_filter( 'upload_dir', [ $this, 'dynamic_upload_dir_filter' ] );

		$file_name  = basename( $full_path );
		$file_type  = wp_check_filetype( $file_name, null );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'guid'           => get_stylesheet_directory_uri() . '/' . $relative_path,
			'post_title'     => sanitize_file_name( preg_replace( '/\.[^.]+$/', '', $file_name ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];
		$attach_id  = wp_insert_attachment( $attachment, $full_path );

		remove_filter( 'upload_dir', [ $this, 'dynamic_upload_dir_filter' ] );

		if ( ! is_wp_error( $attach_id ) ) {
			$attach_data = wp_generate_attachment_metadata( $attach_id, $full_path );
			wp_update_attachment_metadata( $attach_id, $attach_data );
			$metadata = $this->read_metadata();
			unset( $metadata[ $relative_path ] );
			$this->write_metadata( $metadata );
			wp_send_json_success( [ 'message' => 'Asset added to Media Library.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Could not add asset to library.' ] );
		}
	}

	public function ajax_bulk_action(): void {
		$this->check_permissions( 'tam_nonce' );
		$bulk_action   = sanitize_text_field( $_POST['bulk_action'] ?? '' );
		$items         = isset( $_POST['items'] ) ? (array) $_POST['items'] : [];
		$deleted_count = 0;
		$metadata      = $this->read_metadata();

		if ( $bulk_action === 'delete' && ! empty( $items ) ) {
			foreach ( $items as $item_json ) {
				$item = json_decode( wp_unslash( $item_json ), true );
				if ( $item['type'] === 'db' ) {
					$attachment_id = absint( $item['id'] );
					$guid          = get_the_guid( $attachment_id );
					if ( wp_delete_attachment( $attachment_id, true ) ) {
						$relative_path = str_replace( get_stylesheet_directory_uri() . '/', '', $guid );
						unset( $metadata[ $relative_path ] );
						$deleted_count++;
					}
				} elseif ( $item['type'] === 'fs' ) {
					$relative = $item['id'];
					if ( ! empty( $relative ) && strpos( $relative, '..' ) === false ) {
						$full_path = get_stylesheet_directory() . '/' . $relative;
						if ( file_exists( $full_path ) && unlink( $full_path ) ) {
							unset( $metadata[ $relative ] );
							$deleted_count++;
						}
					}
				}
			}
			$this->write_metadata( $metadata );
			wp_send_json_success( [ 'message' => $deleted_count . ' item(s) deleted.' ] );
		}
		wp_send_json_error( [ 'message' => 'No action performed.' ] );
	}

	public function ajax_get_table_body(): void {
		$this->check_permissions( 'tam_nonce', '_wpnonce' );
		ob_start();
		$this->render_table_body();
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html ] );
	}

	public function get_unified_assets(): array {
		$unified_assets = [];
		$folder_options = g6_get_theme_asset_folder_options();
		$metadata       = $this->read_metadata();
		global $wpdb;
		$base_url = get_stylesheet_directory_uri();

		$all_folders_to_scan = array_keys( $folder_options );
		foreach ( array_keys( $metadata ) as $relative_path ) {
			$all_folders_to_scan[] = dirname( $relative_path );
		}
		$all_folders_to_scan = array_unique( $all_folders_to_scan );

		$where_clauses = [];
		foreach ( $all_folders_to_scan as $folder ) {
			$where_clauses[] = $wpdb->prepare( 'p.guid LIKE %s', '%' . trailingslashit( $base_url . '/' . $folder ) . '%' );
		}

		$db_assets = [];
		if ( ! empty( $where_clauses ) ) {
			$sql       = "SELECT ID, post_author, post_date_gmt, guid FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND (" . implode( ' OR ', $where_clauses ) . ')';
			$db_assets = $wpdb->get_results( $sql );
		}

		foreach ( $db_assets as $asset ) {
			$relative_path                    = str_replace( $base_url . '/', '', $asset->guid );
			$full_path                        = get_stylesheet_directory() . '/' . $relative_path;
			$asset_meta                       = wp_get_attachment_metadata( $asset->ID );
			$unified_assets[ $relative_path ] = [
				'source'        => 'db',
				'id'            => $asset->ID,
				'filename'      => basename( $asset->guid ),
				'relative_path' => dirname( $relative_path ),
				'url'           => $asset->guid,
				'date'          => $asset->post_date_gmt,
				'uploader'      => get_user_by( 'id', $asset->post_author ),
				'thumbnails'    => [],
				'dimensions'    => isset( $asset_meta['width'] ) ? "{$asset_meta['width']} &times; {$asset_meta['height']}" : null,
				'filesize'      => file_exists( $full_path ) ? size_format( filesize( $full_path ), 2 ) : null,
			];
		}

		$thumbnail_pattern = '/-(?:\d+)x(?:\d+)\.(?:' . implode( '|', [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) . ')$/i';

		foreach ( $all_folders_to_scan as $folder_path ) {
			$full_dir_path = get_stylesheet_directory() . '/' . $folder_path;
			if ( is_dir( $full_dir_path ) ) {
				$files_on_disk = array_diff( scandir( $full_dir_path ), [ '..', '.', basename( $this->metadata_file ) ] );
				foreach ( $files_on_disk as $filename ) {
					$relative_path_key = $folder_path . '/' . $filename;
					if ( preg_match( $thumbnail_pattern, $filename ) ) {
						$parent_filename = preg_replace( $thumbnail_pattern, '.' . pathinfo( $filename, PATHINFO_EXTENSION ), $filename );
						$parent_key      = $folder_path . '/' . $parent_filename;
						if ( isset( $unified_assets[ $parent_key ] ) ) {
							$unified_assets[ $parent_key ]['thumbnails'][] = [
								'filename' => $filename,
								'url'      => $base_url . '/' . $relative_path_key,
							];
						}
					} elseif ( ! isset( $unified_assets[ $relative_path_key ] ) ) {
						$file_meta  = $metadata[ $relative_path_key ] ?? null;
						$uploader   = $file_meta ? get_user_by( 'id', $file_meta['uploader_id'] ) : null;
						$date       = $file_meta ? $file_meta['timestamp'] : gmdate( 'Y-m-d H:i:s', filemtime( $full_dir_path . '/' . $filename ) );
						$dimensions = null;
						if ( function_exists( 'getimagesize' ) ) {
							$image_info = @getimagesize( $full_dir_path . '/' . $filename );
							if ( $image_info ) {
								$dimensions = "{$image_info[0]} &times; {$image_info[1]}";
							}
						}
						$unified_assets[ $relative_path_key ] = [
							'source'        => 'fs',
							'id'            => $relative_path_key,
							'filename'      => $filename,
							'relative_path' => $folder_path,
							'url'           => $base_url . '/' . $relative_path_key,
							'date'          => $date,
							'uploader'      => $uploader,
							'thumbnails'    => [],
							'dimensions'    => $dimensions,
							'filesize'      => size_format( filesize( $full_dir_path . '/' . $filename ), 2 ),
						];
					}
				}
			}
		}
		return $unified_assets;
	}

	public function render_table_body(): void {
		$unified_assets   = $this->get_unified_assets();
		if ( empty( $unified_assets ) ) {
			echo '<tr><td colspan="9">No assets found in the configured theme folders.</td></tr>';
			return;
		}

		$image_extensions = [ 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp' ];
		$file_type_icons  = [
			'js'  => 'dashicons-media-code',
			'css' => 'dashicons-media-code',
			'pdf' => 'dashicons-media-document',
		];

		foreach ( $unified_assets as $asset ) :
			$file_ext   = strtolower( pathinfo( $asset['filename'], PATHINFO_EXTENSION ) );
			$is_image   = in_array( $file_ext, $image_extensions, true );
			$icon_class = $file_type_icons[ $file_ext ] ?? 'dashicons-media-default';
			?>
			<tr data-filename="<?php echo esc_attr( strtolower( $asset['filename'] ) ); ?>" data-folder="<?php echo esc_attr( $asset['relative_path'] ); ?>" data-status="<?php echo esc_attr( $asset['source'] ); ?>">
				<th scope="row" class="check-column" data-label="Select"><input type="checkbox" class="tam-item-checkbox" value="<?php echo esc_attr( json_encode( [ 'id' => $asset['id'], 'type' => $asset['source'] ] ) ); ?>"></th>
				<td data-label="Preview">
					<?php if ( $is_image ) : ?>
						<img src="<?php echo esc_url( $asset['url'] ); ?>" alt="preview" class="tam-preview-thumb" loading="lazy">
					<?php else : ?>
						<span class="dashicons <?php echo esc_attr( $icon_class ); ?> tam-file-type-icon" title="<?php echo esc_attr( strtoupper( $file_ext ) ); ?> file"></span>
					<?php endif; ?>
				</td>
				<td data-label="File Name & Path">
					<code><?php echo esc_html( $asset['filename'] ); ?></code><br>
					<small>In: <code><?php echo esc_html( $asset['relative_path'] ); ?></code></small>
				</td>
				<td data-label="File Info">
					<?php if ( ! empty( $asset['dimensions'] ) ) : ?>
						<span><?php echo esc_html( $asset['dimensions'] ); ?></span><br>
					<?php endif; ?>
					<?php if ( ! empty( $asset['filesize'] ) ) : ?>
						<small><?php echo esc_html( $asset['filesize'] ); ?></small>
					<?php endif; ?>
				</td>
				<td data-label="In Media Library" class="tam-status-cell">
					<?php if ( $asset['source'] === 'db' ) : ?>
						<span class="dashicons dashicons-yes-alt" title="In Media Library"></span>
					<?php else : ?>
						<span class="dashicons dashicons-no-alt" title="Not in Media Library (File System Only)"></span>
					<?php endif; ?>
				</td>
				<td data-label="Date Uploaded"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $asset['date'] . ' UTC' ) ) ); ?></td>
				<td data-label="Uploader"><?php echo $asset['uploader'] ? esc_html( $asset['uploader']->display_name ) : 'N/A'; ?></td>
				<td class="tam-actions-cell" data-label="Actions">
					<?php if ( ! empty( $asset['thumbnails'] ) ) : ?>
						<button class="button button-small tam-toggle-thumbnails tam-button-has-text" aria-expanded="false">
							<span class="dashicons dashicons-plus" aria-hidden="true"></span><span class="tam-button-text"> Sizes</span>
						</button>
					<?php endif; ?>
					<button class="button button-small tam-copy-url" data-url="<?php echo esc_url( $asset['url'] ); ?>" title="Copy URL">
						<span class="dashicons dashicons-admin-links"></span><span class="screen-reader-text">Copy URL</span>
					</button>
					<?php if ( $asset['source'] === 'db' ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $asset['id'] ) ); ?>" class="button button-small" target="_blank" title="Edit in Media Library">
							<span class="dashicons dashicons-edit"></span><span class="screen-reader-text">Edit</span>
						</a>
						<button class="button button-small tam-delete-button" data-id="<?php echo esc_attr( $asset['id'] ); ?>" data-type="db" title="Delete Permanently">
							<span class="dashicons dashicons-trash"></span><span class="screen-reader-text">Delete</span>
						</button>
					<?php else : ?>
						<button class="button button-small tam-add-button" data-id="<?php echo esc_attr( $asset['id'] ); ?>" title="Add to Media Library">
							<span class="dashicons dashicons-cloud-upload"></span><span class="screen-reader-text">Add to Library</span>
						</button>
						<button class="button button-small tam-delete-button button-link-delete" data-id="<?php echo esc_attr( $asset['id'] ); ?>" data-type="fs" title="Delete from Server">
							<span class="dashicons dashicons-trash"></span><span class="screen-reader-text">Delete</span>
						</button>
					<?php endif; ?>
				</td>
			</tr>
			<tr class="tam-thumbnails-row" style="display: none;">
				<td class="tam-thumbnail-gutter check-column"></td>
				<td class="tam-thumbnail-gutter"></td>
				<td colspan="6">
					<div class="tam-thumbnails-wrapper">
						<strong>Available Sizes:</strong>
						<ul class="tam-thumbnails-list">
						<?php if ( ! empty( $asset['thumbnails'] ) ) : ?>
							<?php foreach ( $asset['thumbnails'] as $thumb ) : ?>
								<li>
									<code><?php echo esc_html( $thumb['filename'] ); ?></code>
									<button class="button button-small tam-copy-url" data-url="<?php echo esc_url( $thumb['url'] ); ?>" title="Copy Size URL">
										<span class="dashicons dashicons-admin-links"></span><span class="screen-reader-text">Copy URL</span>
									</button>
								</li>
							<?php endforeach; ?>
						<?php else : ?>
							<li>No other sizes found.</li>
						<?php endif; ?>
						</ul>
					</div>
				</td>
			</tr>
		<?php endforeach;
	}

	public function render_page(): void {
		$folder_options = g6_get_theme_asset_folder_options();
		?>
		<div class="wrap" id="tam-wrapper">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>A complete asset manager for your theme's local asset folders.</p>
			<div id="tam-notice-container"></div>
			<div class="card">
				<h2>Upload New Asset</h2>
				<form id="tam-upload-form" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="tam_upload_files">
					<?php wp_nonce_field( 'tam_nonce', '_wpnonce', true, true ); ?>
					<div class="tam-dropzone">
						<span class="dashicons dashicons-upload"></span>
						<p><strong>Drag and drop files here</strong> or</p>
						<label for="theme_assets_upload" class="button">Select Files from Computer</label>
						<input id="theme_assets_upload" type="file" name="theme_assets[]" multiple="multiple" style="display: none;">
					</div>
					<div id="tam-file-list-wrapper">
						<strong>Selected files:</strong> <span id="tam-file-list">None</span>
					</div>
					<div class="tam-upload-options">
						<div>
							<label for="destination_folder_select"><strong>Destination:</strong></label>
							<select id="destination_folder_select" name="destination_folder">
								<?php foreach ( $folder_options as $path => $label ) : ?>
									<option value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
								<option value="custom">Custom Path...</option>
							</select>
							<div id="tam-custom-path-wrapper" style="display: none; margin-top: 5px;">
								<input type="text" name="custom_path_input" class="regular-text" value="assets/images" placeholder="e.g., assets/custom">
							</div>
						</div>
						<div>
							<button type="submit" name="upload_and_add" class="button button-primary"><span class="dashicons dashicons-cloud-upload"></span> Upload & Add to Library</button>
							<button type="submit" name="only_upload" class="button button-secondary"><span class="dashicons dashicons-upload"></span> Only Upload</button>
							<span class="spinner"></span>
						</div>
					</div>
					<p class="description">
						<strong>Button Guide:</strong><br>
						&bull; <b>Upload &amp; Add to Library:</b> Integrates files with WordPress. They will appear in the Media Library and this table.<br>
						&bull; <b>Only Upload:</b> A developer utility to place files directly on the server. These files will not be tracked by this tool unless reloaded.
					</p>
				</form>
			</div>
			<hr>
			<h2>Uploaded Theme Assets</h2>
			<div class="tablenav top">
				<div class="tam-filters-wrapper">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
						<select name="action" id="bulk-action-selector-top">
							<option value="-1">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" id="tam-doaction" class="button action" value="Apply">
					</div>
					<div class="alignleft actions">
						<input type="search" id="tam-search-input" class="regular-text" placeholder="Search filenames...">
						<select id="tam-folder-filter">
							<option value="">All Folders</option>
							<?php foreach ( $folder_options as $path => $label ) : ?>
								<option value="<?php echo esc_attr( $path ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<select id="tam-status-filter">
							<option value="">All Statuses</option>
							<option value="db">In Media Library</option>
							<option value="fs">File System Only</option>
						</select>
					</div>
				</div>
			</div>
			<table class="widefat striped fixed tam-responsive-table">
				<thead>
					<tr>
						<th scope="col" id="cb" class="manage-column column-cb check-column"><input id="tam-cb-select-all" type="checkbox"></th>
						<th scope="col" style="width: 50px;">Preview</th>
						<th scope="col">File Name &amp; Path</th>
						<th scope="col" style="width: 130px;">File Info</th>
						<th scope="col" style="width: 120px; text-align: center;">In Media Library</th>
						<th scope="col" style="width: 170px;">Date Uploaded</th>
						<th scope="col" style="width: 120px;">Uploader</th>
						<th scope="col" style="text-align: right;">Actions</th>
					</tr>
				</thead>
				<tbody id="tam-table-body">
					<?php $this->render_table_body(); ?>
				</tbody>
			</table>
		</div>

		<style>
			.card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgb(0 0 0 / 4%); padding: 1.5em; }
			#tam-wrapper .spinner { float: none; vertical-align: middle; margin-left: 5px; }
			#tam-wrapper .spinner.is-active { display: inline-block; }
			.tam-dropzone { border: 2px dashed #c3c4c7; text-align: center; padding: 2em; transition: all 0.2s ease-in-out; cursor: pointer; border-radius: 4px; }
			.tam-dropzone:hover { border-color: #2271b1; }
			.tam-dropzone.dragover { border-color: #2271b1; background-color: #f0f6fc; transform: scale(1.02); }
			.tam-dropzone .dashicons { font-size: 48px; width: 48px; height: 48px; color: #999; }
			#tam-file-list-wrapper { margin: 1em 0; background: #f9f9f9; padding: 0.5em 1em; border: 1px solid #ddd; border-radius: 4px; }
			.tam-upload-options { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5em; flex-wrap: wrap; gap: 1em; }
			.tam-upload-options .button .dashicons { margin-right: 4px; vertical-align: text-top; }
			.tam-filters-wrapper { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1em; padding: 5px 0; width: 100%; }
			.tam-filters-wrapper .actions { display: flex; gap: 0.5em; align-items: center; }
			.tam-responsive-table { margin-top: 1em; }
			#tam-table-body td, #tam-table-body th { vertical-align: middle; }
			.tam-preview-thumb { max-width: 40px; max-height: 40px; vertical-align: middle; border-radius: 2px; }
			.tam-file-type-icon { font-size: 36px; width: 36px; height: 36px; color: #646970; display: block; }
			.tam-actions-cell { white-space: nowrap; text-align: right; }
			.tam-actions-cell .button { display: inline-flex; align-items: center; justify-content: center; height: 30px; padding: 0 6px; }
			.tam-actions-cell .button:not(.tam-button-has-text) { width: 30px; }
			.tam-actions-cell .button .dashicons { margin: 0; font-size: 16px; display: flex; align-items: center; }
			.tam-status-cell { text-align: center; }
			.tam-status-cell .dashicons { font-size: 24px; vertical-align: middle; }
			.tam-status-cell .dashicons-yes-alt { color: #00a32a; }
			.tam-status-cell .dashicons-no-alt { color: #d63638; }
			.tam-thumbnails-row > td { background-color: #f0f6fc; padding-top: 0 !important; padding-bottom: 0 !important; border: none !important; }
			.tam-thumbnails-wrapper { padding: 10px; }
			.tam-thumbnails-list { margin: 5px 0 0 0; list-style: none; display: flex; flex-wrap: wrap; gap: 15px; }
			.tam-thumbnails-list li { background: #fff; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; gap: 5px; }
			#tam-notice-container .notice { margin: 15px 0 5px; }
			tr.tam-row-deleting { background-color: #fbeaea !important; }
			tr.tam-row-deleting td { opacity: 0.6; }

			@media screen and (max-width: 782px) {
				.tam-responsive-table thead { display: none; }
				.tam-responsive-table tr { display: block; margin-bottom: 1.5em; border: 1px solid #ddd; box-sizing: border-box; }
				.tam-responsive-table tr.tam-thumbnails-row { border-top: none !important; margin-top: -1.5em; margin-bottom: 1.5em; border-left: 1px solid #ddd; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; }
				.tam-responsive-table td { display: flex; justify-content: space-between; align-items: center; text-align: right !important; padding: 10px; border-bottom: 1px solid #eee; box-sizing: border-box; }
				.tam-responsive-table td:before { content: attr(data-label); font-weight: bold; text-align: left; margin-right: 1em; }
				.tam-responsive-table td.check-column, .tam-responsive-table th.check-column { display: block; padding-left: 10px; }
				.tam-responsive-table td.check-column:before { display: none; }
				.tam-responsive-table .tam-actions-cell { flex-wrap: wrap; gap: 5px; justify-content: flex-end; }
				.tam-filters-wrapper { flex-direction: column; align-items: stretch; }
				.tam-filters-wrapper .alignleft { margin-right: 0; }
				.tam-upload-options { flex-direction: column; align-items: stretch; }
				.tam-upload-options > div { width: 100%; }
				.tam-upload-options .button { width: 100%; justify-content: center; }
				.tam-responsive-table .tam-thumbnails-row .tam-thumbnail-gutter { display: none; }
				.tam-responsive-table .tam-thumbnails-row td { display: block; width: 100%; padding: 15px !important; border-bottom: none !important; box-sizing: border-box; }
				.tam-responsive-table .tam-thumbnails-row td:before { display: none; }
				.tam-thumbnails-wrapper { padding: 10px; }
			}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			const wrapper   = $('#tam-wrapper');
			const nonce     = wrapper.find('input[name="_wpnonce"]').val();
			let filesToUpload = [];
			let noticeTimeout;

			function showNotice(type, message, persistent = false) {
				clearTimeout(noticeTimeout);
				const notice = $('<div class="notice is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>').addClass('notice-' + type);
				wrapper.find('#tam-notice-container').html(notice).hide().fadeIn(200);
				$('html, body').animate({ scrollTop: wrapper.offset().top - 50 }, 300);
				if (type === 'success' && !persistent) {
					noticeTimeout = setTimeout(() => notice.fadeOut(400, () => notice.remove()), 5000);
				}
				notice.on('click', '.notice-dismiss', function() { $(this).closest('.notice').remove(); });
			}

			function refreshTable() {
				const spinner = $('<span class="spinner is-active" style="display:inline-block; margin-left:10px;"></span>');
				$('.tablenav.top').append(spinner);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'tam_get_table_body', _wpnonce: nonce },
					success: function(response) {
						if (response.success) wrapper.find('#tam-table-body').html(response.data.html);
					},
					complete: function() { spinner.remove(); applyFilters(); }
				});
			}

			function updateFileList() {
				const fileList = $('#tam-file-list');
				if (filesToUpload.length === 0) {
					fileList.text('None');
				} else {
					fileList.text(filesToUpload.length + ' file(s): ' + filesToUpload.map(f => f.name).join(', '));
				}
			}

			const dropzone = $('.tam-dropzone');
			dropzone.on('dragover', function(e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover'); });
			dropzone.on('dragleave', function(e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover'); });
			dropzone.on('drop', function(e) {
				e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
				filesToUpload = Array.from(e.originalEvent.dataTransfer.files);
				updateFileList();
			});
			dropzone.on('click', function(e) { if (!$(e.target).is('input, label, select, button')) { $('#theme_assets_upload').click(); } });
			$('#theme_assets_upload').on('change', function() { filesToUpload = Array.from(this.files); updateFileList(); });

			wrapper.on('submit', '#tam-upload-form', function(e) {
				e.preventDefault();
				if (filesToUpload.length === 0) { alert('Please select or drop files to upload.'); return; }
				const form          = $(this);
				const spinner       = form.find('.spinner');
				const clickedButton = $(document.activeElement);
				const formData      = new FormData(form[0]);
				formData.append(clickedButton.attr('name'), clickedButton.val());
				filesToUpload.forEach(file => formData.append('theme_assets[]', file));
				spinner.addClass('is-active');
				form.find('button, input[type="submit"]').prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
					success: function(response) {
						if (response.success) { showNotice('success', response.data.message); refreshTable(); filesToUpload = []; updateFileList(); }
						else { showNotice('error', response.data.message || 'An unknown error occurred.', true); }
					},
					error: function() { showNotice('error', 'A server error occurred.', true); },
					complete: function() { spinner.removeClass('is-active'); form.find('button, input[type="submit"]').prop('disabled', false); }
				});
			});

			wrapper.on('click', '.tam-delete-button, .tam-add-button, .tam-copy-url', function(e) {
				e.preventDefault(); e.stopPropagation();
				const button = $(this);
				if (button.hasClass('tam-copy-url')) {
					navigator.clipboard.writeText(button.data('url')).then(() => {
						const originalIcon = button.html(); button.html('<span class="dashicons dashicons-yes"></span>');
						setTimeout(() => button.html(originalIcon), 2000);
					});
					return;
				}
				let confirmMessage, ajaxAction;
				if (button.hasClass('tam-delete-button')) {
					confirmMessage = 'Are you sure you want to permanently delete this asset?';
					ajaxAction     = 'tam_delete_asset';
				} else if (button.hasClass('tam-add-button')) {
					confirmMessage = 'Are you sure you want to add this file to the Media Library?';
					ajaxAction     = 'tam_add_to_library';
				}
				const row = button.closest('tr');
				row.addClass('tam-row-deleting');
				if (!confirm(confirmMessage)) { row.removeClass('tam-row-deleting'); return; }
				const originalContent = button.html();
				button.html('<span class="spinner is-active" style="width:auto;height:auto;margin:0;"></span>').prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: ajaxAction, _wpnonce: nonce, id: button.data('id'), type: button.data('type') },
					success: function(response) {
						if (response.success) { showNotice('success', response.data.message); refreshTable(); }
						else { showNotice('error', response.data.message, true); button.html(originalContent).prop('disabled', false); }
					},
					error: function() { button.html(originalContent).prop('disabled', false); },
					complete: function() { row.removeClass('tam-row-deleting'); }
				});
			});

			$('#tam-cb-select-all').on('click', function() { wrapper.find('#tam-table-body .tam-item-checkbox').prop('checked', this.checked); });
			$('#tam-doaction').on('click', function(e) {
				e.preventDefault();
				const button = $(this);
				const action = $('#bulk-action-selector-top').val();
				if (action === '-1') { alert('Please select a bulk action.'); return; }
				const items = wrapper.find('.tam-item-checkbox:checked').map(function() { return $(this).val(); }).get();
				if (items.length === 0) { alert('Please select items to apply the action to.'); return; }
				if (!confirm('Are you sure you want to ' + action + ' ' + items.length + ' item(s)? This cannot be undone.')) return;
				button.prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'tam_bulk_action', _wpnonce: nonce, bulk_action: action, items: items },
					success: function(response) {
						if (response.success) { showNotice('success', response.data.message); refreshTable(); }
						else { showNotice('error', response.data.message, true); }
					},
					complete: function() { button.prop('disabled', false); }
				});
			});

			function applyFilters() {
				const search = $('#tam-search-input').val().toLowerCase();
				const folder = $('#tam-folder-filter').val();
				const status = $('#tam-status-filter').val();
				$('#tam-table-body tr.tam-thumbnails-row').hide().prev('tr').find('.tam-toggle-thumbnails').attr('aria-expanded', 'false').find('.dashicons').removeClass('dashicons-minus').addClass('dashicons-plus');
				$('#tam-table-body tr:not(.tam-thumbnails-row)').each(function() {
					const row  = $(this);
					const show = (row.data('filename').toLowerCase().includes(search)) && (folder === '' || row.data('folder') === folder) && (status === '' || row.data('status') === status);
					row.toggle(show);
				});
			}
			$('#tam-search-input, #tam-folder-filter, #tam-status-filter').on('keyup change', applyFilters);

			wrapper.on('click', '.tam-toggle-thumbnails', function(e) {
				e.preventDefault(); e.stopPropagation();
				const button     = $(this);
				const icon       = button.find('.dashicons');
				const isExpanded = button.attr('aria-expanded') === 'true';
				button.closest('tr').next('.tam-thumbnails-row').slideToggle(200);
				button.attr('aria-expanded', !isExpanded);
				icon.toggleClass('dashicons-plus dashicons-minus');
			});

			$('#destination_folder_select').on('change', function() {
				$('#tam-custom-path-wrapper').toggle($(this).val() === 'custom');
			}).trigger('change');
		});
		</script>
		<?php
	}

	public function protect_metadata_file(): void {
		$htaccess = get_stylesheet_directory() . '/.htaccess';
		$marker   = 'TAM metadata protection';
		$rules    = "<Files \"tam-metadata.json\">\n    Require all denied\n</Files>";
		insert_with_markers( $htaccess, $marker, $rules );
	}

	public function allow_theme_asset_mimes( array $mimes ): array {
		$mimes['js']  = 'application/javascript';
		$mimes['css'] = 'text/css';
		return $mimes;
	}

	public function is_a_theme_asset( int $attachment_id ): bool {
		$guid = get_the_guid( $attachment_id );
		if ( empty( $guid ) ) {
			return false;
		}
		foreach ( array_keys( g6_get_theme_asset_folder_options() ) as $folder_path ) {
			if ( strpos( $guid, '/' . $folder_path . '/' ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public function fix_theme_asset_url( string $url, int $attachment_id ): string {
		if ( $this->is_a_theme_asset( $attachment_id ) ) {
			return get_the_guid( $attachment_id );
		}
		return $url;
	}

	public function fix_theme_asset_srcset_urls( $sources, $size_array, $image_src, $image_meta, int $attachment_id ) {
		if ( ! $this->is_a_theme_asset( $attachment_id ) ) {
			return $sources;
		}
		$upload_dir      = wp_get_upload_dir();
		$wrong_base_url  = $upload_dir['baseurl'];
		$correct_base_url = get_stylesheet_directory_uri();
		foreach ( $sources as $width => $source ) {
			if ( strpos( $source['url'], $wrong_base_url ) !== false ) {
				$sources[ $width ]['url'] = str_replace( $wrong_base_url, $correct_base_url, $source['url'] );
			}
		}
		return $sources;
	}

	public function dynamic_upload_dir_filter( array $path_data ): array {
		$path_data['path']    = get_stylesheet_directory() . '/' . $this->current_dynamic_path;
		$path_data['url']     = get_stylesheet_directory_uri() . '/' . $this->current_dynamic_path;
		$path_data['subdir']  = '';
		$path_data['basedir'] = get_stylesheet_directory();
		$path_data['baseurl'] = get_stylesheet_directory_uri();
		$path_data['error']   = false;
		return $path_data;
	}
}
