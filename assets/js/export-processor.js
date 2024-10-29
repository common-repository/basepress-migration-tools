jQuery( function($){

	'use strict';

	let basepressKbExporter = {
		exportObjects: [],
		objectsNames: [],
		status: null,
		//Stores current processing object type like "products", "sections", "articles etx.
		currentObjectType: null,
		//Stores previous processing object type like "products", "sections", "articles etx.
		previousObjectType: null,
		currentItem: null,
		totalItems: 0,
		processedItems: 0,
		processedQty: 0,
		progressBar: null,
		progressSteps: null,
		itemsQtyPerStep: 0,
		exportFile: '',
		endProcessCallBack: null,

		/**
		 * Init function
		 *
		 * @param progressBar
		 * @param endProcessCallBack
		 */
		init: function( progressBar, endProcessCallBack ){
			let progressBarContainer = $( '#' + progressBar ).append( '<span><span></span></span>' );
			this.progressBar = progressBarContainer.children( 'span' );
			this.endProcessCallBack = endProcessCallBack;
		},


		/**
		 * Entry point to start the export
		 *
		 * @param itemsQtyPerStep
		 */
		export: function( itemsQtyPerStep ){
			this.itemsQtyPerStep = itemsQtyPerStep;
			this.progressSteps = 0;
			this.progressValue = 0;
			this.processedQty = 0;
			this.updateStatus( 'get_export_objects' );
		},


		/**
		 * General Ajax call for all functions
		 *
		 * @param action
		 * @param process
		 * @param packet
		 * @param callBack
		 */
		doAjax: function( process, packet = null, callBack ){
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'basepress_kb_export',
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
		},


		/**
		 * Updates process status before calling the main function
		 *
		 * @param newStatus
		 */
		updateStatus: function( newStatus ){

			if( 'item_processed' == newStatus ){
				this.status = this.processedItems < this.totalItems ? 'process_item' : 'close_export_file';
			}
			else{
				this.status = newStatus;
			}
			this.exportProcess();
		},


		/**
		 * Main function for the export process
		 */
		exportProcess: function(){

			switch( this.status ){
				case 'get_export_objects':
					this.doAjax( 'get_export_objects', '', this.setExportObjects );
					break;

				case 'process_item':
					this.processNextItem();
					break;

				case 'close_export_file':
					this.closeExportFile();
					break;

				case 'process_finished':
					this.updateProgressBar( 'process_finished' );
					this.getExportFileLink();
					this.log( '\n' );
					break;

				case 'process_failed':
					return;

				default:
					return;
			}
			return;
		},


		/**
		 * Sets objects to export as sent from server
		 *
		 * @param response
		 */
		setExportObjects: function( response ){
			if( 'error' in response ){
				this.updateStatus( 'process_failed' );
				alert( response.error );
				this.endProcessCallBack();
				return;
			}
			this.exportFile = response.exportFile;
			this.exportObjects = response.exportObjects;
			this.objectsNames = Object.keys(this.exportObjects);
			this.currentObjectType = 0;
			this.previousObjectType = 0;
			this.currentItem = 0;
			this.processedItems = 0;
			let steps = [];

			this.log( 'Found:' );
			for( var i = 0; i < this.objectsNames.length; i++ ){
				Array.prototype.push.apply( steps, this.exportObjects[this.objectsNames[i]] );
				this.log( this.exportObjects[this.objectsNames[i]].length + ' ' + this.objectsNames[i] );
			}
			this.log( '\n' );
			this.totalItems = steps.length;
			this.progressSteps = 100 / this.totalItems;

			this.updateStatus( 'process_item' );
		},


		/**
		 * Triggers processing of next item packet
		 */
		processNextItem: function(){
			let currentObjectName = this.objectsNames[this.currentObjectType];
			let previousObjectName = this.objectsNames[this.previousObjectType];

			//If there are no more items for the current object type move to the next one
			if( this.currentItem >= this.exportObjects[currentObjectName].length ){
				this.currentItem = 0;
				this.previousObjectType = this.currentObjectType;
				this.currentObjectType++;
				//If there are no more items to process finish
				if( this.currentObjectType >= this.objectsNames.length ){
					this.updateStatus( 'close_export_file' );
					return;
				}
				//Else process next item
				this.processNextItem();
				return;
			}

			let items = this.exportObjects[currentObjectName].slice( this.currentItem, this.currentItem + this.itemsQtyPerStep );
			this.log( '\nProcessing ' + currentObjectName + ': ' + items );
			this.doAjax(
				'export_items',
				{
					currentObjectName: currentObjectName,
					previousObjectName: previousObjectName,
					items: items,
					openParentElement: this.currentItem ? false : true,
					exportFile: this.exportFile
				},
				this.itemProcessed
			);
			this.previousObjectType = this.currentObjectType;
		},


		/**
		 * Call back after each items packet was processed
		 *
		 * @param response
		 */
		itemProcessed: function( response ){
			let itemsProcessedNow = response.items.length;
			this.currentItem += itemsProcessedNow;
			this.processedQty += itemsProcessedNow;
			let progressValue = this.progressSteps * this.processedQty;
			this.processedItems += itemsProcessedNow;

			this.log( 'Total Processed: ' + this.processedItems + ' / ' + this.totalItems + ' - ' + Math.round(( progressValue * 100) ) / 100 + '%' + '\n\n' );
			this.updateProgressBar();
			this.updateStatus( 'item_processed' );
		},


		/**
		 * Close the export file
		 */
		closeExportFile: function(){
			this.doAjax(
				'close_export_file',
				{
					previousObjectName: this.objectsNames[this.previousObjectType],
					exportFile: this.exportFile
				},
				function(){ this.updateStatus( 'process_finished' ); }
			);
		},


		/**
		 * Gets the new export file link from server
		 */
		getExportFileLink: function(){
			this.doAjax(
				'get_export_file_link',
				this.exportFile,
				this.endProcessCallBack
			);

		},


		/**
		 * Update progress bar
		 *
		 * @param progressStatus
		 */
		updateProgressBar: function( progressStatus = '' ){

			let progressValue = this.progressSteps * this.processedItems;
			this.progressBar.css( 'width', Math.round(progressValue) + '%' );
			switch( progressStatus ){
				case 'process_finished':
					this.progressBar.children('span').css('opacity', 0 );
					return;
					break;
				default:
					this.progressBar.children('span').css( 'opacity', 1 );
			}
		},


		/**
		 * Log function
		 *
		 * @param log
		 */
		log: function( log = '' ){
			if( log ){
				console.log( log );
			}
		}
	}

	window.basepressKbExporter = basepressKbExporter;
});