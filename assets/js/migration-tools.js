jQuery( 'window' ).ready( function( $ ){

	/**
	 * Initialize the export and import processors
	 */
	basepressKbExporter.init( 'basepress-export-progress-bar', callbackAfterExport );
	basepressKbImporter.init( 'basepress-import-progress-bar', callbackAfterImport );

	/**
	 * General Ajax call for all functions
	 *
	 * @param action
	 * @param process
	 * @param packet
	 * @param callBack
	 */
	function doAjax( action, process, packet = null, callBack ){
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: action,
				process: process,
				packet: packet
			},
			success: function( response ){
				if( 'function' == typeof callBack ){
					let _callBack = callBack.bind(basepressKbExporter);
					_callBack( response );
				}
			},
			error: function( jqXHR, textStatus, errorThrown ){
				this.log( errorThrown );
			},
			complete: function(){
			}
		});
	}


	/**
	 * Enable/disable all buttons
	 *
	 * @param state
	 */
	function toggle_buttons( state ){
		var disabled = 'enable' == state ? false : true;
		$('#basepress-export').prop('disabled', disabled);
		$('#basepress-import-new-file').prop('disabled', disabled);
		$('#basepress-import-new').prop('disabled', disabled);
		$('#basepress-import-selected').prop('disabled', disabled);
	}


	/**
	 * Triggers export
	 */
	$( '#basepress-export' ).click( function(){
		$('#basepress-export-file-link').html('');
		toggle_buttons( 'disable' );
		$(this).find('.dashicons').removeClass('hidden-icon');
		let itemsQtyPerStep = $( '#basepress-export-qty' ).val();
		basepressKbExporter.export( parseInt( itemsQtyPerStep ) );
	});


	/**
	 * Gets the new export file link from server
	 */
	function callbackAfterExport( response ){
		$( '#basepress-export' ).find('.dashicons').addClass('hidden-icon');
		toggle_buttons( 'enable' );
		if( response ){
			renderExportFileLink( response );
			updateImportFileList();
		}
	}


	/**
	 * Renders the export file link after export
	 * @param response
	 */
	function renderExportFileLink( response ){
		$( '#basepress-export-file-link' ).html( response.exportLink );
	}

	/**
	 * Gets a fresh list of archived files and updates the list on screen
	 */
	function updateImportFileList(){
		doAjax(
			'basepress_get_updated_import_list',
			'',
			'',
			function( response ){
				$( '#basepress-import-files-list' ).html( response );
			},
		);
	}


	/**
	 * Delete an archived file
	 */
	$('#basepress-import-files-list').on( 'click', '.basepress-import-file-delete', function( e ){
		var exportFile = $( this ).data('delete-file');

		$(this).find('.dashicons').removeClass('dashicons-no').addClass('dashicons-update');

		doAjax(
			'basepress_delete_export_file',
			'',
			{
				exportFile: exportFile
			},
			function(){
				updateImportFileList();
				$(this).remove();
			}
		);
	} );

	/**
	 * Delete all archived files
	 */
	$('#basepress-import-file-delete-all span').click( function( e ){

		let that = $(this);
		that.find('.dashicons').removeClass('dashicons-no').addClass('dashicons-update');

		doAjax(
			'basepress_delete_export_file',
			'',
			{
				exportFile: 'all'
			},
			function(){
				updateImportFileList();
				that.find('.dashicons').addClass('dashicons-no').removeClass('dashicons-update');
			}
		);
	} );

	/**
	 * Import new file
	 */
	$( '#basepress-import-new' ).click(function( e ){
		e.preventDefault();
		let that = $(this);
		let fileInput = $( '#basepress-import-new-file' );

		if( ! fileInput[0].files.length ){
			that.find('.dashicons').addClass('hidden-icon');
			return;
		}

		toggle_buttons( 'disable' );
		that.find('.dashicons').removeClass('hidden-icon');

		let	formData = new FormData();
		formData.append( "action", "basepress_import_new_file" );

		$.each(fileInput[0].files, function(i, file) {
			formData.append('basepress_upload_file', file );
		});

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: formData,
			cache: false,
			dataType: 'json',
			processData: false,
			contentType: false,
			success: function( response ){
				updateImportFileList();
				let itemsQtyPerStep = $( '#basepress-export-qty' ).val();
				let defaultAuthor = $( '#default-author').val();
				basepressKbImporter.import( response.filename, parseInt( itemsQtyPerStep ), defaultAuthor );
			}
		});
	});


	/**
	 * Triggers import of selected archived file
	 */
	$( '#basepress-import-selected' ).click( function(e){
		e.preventDefault();
		toggle_buttons( 'disable' );
		$(this).find('.dashicons').removeClass('hidden-icon');
		let itemsQtyPerStep = $( '#basepress-import-qty' ).val();
		let fileName = $( 'input[name=import-file]:checked' ).val();
		let defaultAuthor = $( '#default-author').val();
		basepressKbImporter.import( fileName, parseInt( itemsQtyPerStep ), defaultAuthor );
	});

	function callbackAfterImport( response ){
		$( '#basepress-import-new, #basepress-import-selected' ).find('.dashicons').addClass('hidden-icon');
		toggle_buttons( 'enable' );
	}

	/**
	 * Delete all data
	 */
	$( '#basepress-delete-data' ).click(function( e ){
		e.preventDefault();
		let that = $(this);

		that.find('.dashicons').removeClass('hidden-icon');
		toggle_buttons( 'disable' );

		doAjax(
			'basepress_delete_all_data',
			'',
			'',
			function( response ){
				that.find('.dashicons').addClass('hidden-icon');
				alert( response );
				toggle_buttons( 'enable' );
			},
		);
	} );
});