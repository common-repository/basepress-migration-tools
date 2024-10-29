jQuery( function($){

	'use strict';

	let basepressKbImporter = {
		importObjects: [],
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
		importFile: '',
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
		 * Entry point to start the import
		 *
		 * @param itemsQtyPerStep
		 */
		import: function( fileName, itemsQtyPerStep, defaultAuthor ){
			this.importFile = fileName;
			this.itemsQtyPerStep = itemsQtyPerStep;
			this.defaultAuthor = defaultAuthor;
			this.progressSteps = 0;
			this.progressValue = 0;
			this.updateStatus( 'get_import_objects' );
		},


		/**
		 * Updates process status before calling the main function
		 *
		 * @param newStatus
		 */
		updateStatus: function( newStatus ){

			if( 'item_processed' == newStatus ){
				this.status = this.processedItems < this.totalItems ? 'process_item' : 'process_finished';
			}
			else{
				this.status = newStatus;
			}
			this.importProcess();
		},


		/**
		 * Main function for the import process
		 */
		importProcess: function(){

			switch( this.status ){
				case 'get_import_objects':
					this.doAjax( 'basepress_kb_import', 'get_import_objects', this.importFile, this.setImportObjects );
					break;

				case 'process_item':
					this.processNextItem();
					break;

				case 'process_finished':
					this.updateProgressBar( 'process_finished' );
					this.endProcessCallBack();
					break;

				default:
					return;
			}
			return;
		},


		/**
		 * Sets objects to import as sent from server
		 *
		 * @param response
		 */
		setImportObjects: function( response ){
			this.importObjects = response;
			this.objectsNames = Object.keys(this.importObjects);
			this.currentObjectType = 0;
			this.previousObjectType = 0;
			this.currentItem = 0;
			this.processedItems = 0;
			let steps = [];

			this.log( 'Found:' );
			for( var i = 0; i < this.objectsNames.length; i++ ){
				Array.prototype.push.apply( steps, this.importObjects[this.objectsNames[i]] );
				this.log( this.importObjects[this.objectsNames[i]].length + ' ' + this.objectsNames[i] );
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

			//If there are no more items for the current object type move to the next one
			if( this.currentItem >= this.importObjects[currentObjectName].length ){
				this.currentItem = 0;
				this.previousObjectType = this.currentObjectType;
				this.currentObjectType++;
				//If there are no more items to process finish
				if( this.currentObjectType >= this.objectsNames.length ){
					this.updateStatus( 'process_finished' );
					return;
				}
				//Else process next item
				this.processNextItem();
				return;
			}

			let items = this.importObjects[currentObjectName].slice( this.currentItem, this.currentItem + this.itemsQtyPerStep );
			this.log( '\nProcessing ' + currentObjectName + ': ' + items );
			this.doAjax(
				'basepress_kb_import',
				'import_items',
				{
					currentObjectName: currentObjectName,
					items: items,
					importFile: this.importFile,
					defaultAuthor: this.defaultAuthor
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

			//this.log( 'Processed ' + itemsProcessedNow + ' ' + response.currentObjectName + ' (' + response.items + ')' );
			this.log( 'Total Processed: ' + this.processedItems + ' / ' + this.totalItems + ' - ' + Math.round(( progressValue * 100) / 100 ) + '%' + '\n\n' );
			this.updateProgressBar();
			this.updateStatus( 'item_processed' );
		},



		/**
		 * General Ajax call for all functions
		 *
		 * @param action
		 * @param process
		 * @param packet
		 * @param callBack
		 */
		doAjax: function( action, process, packet = null, callBack ){
			let that = this;
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
						let _callBack = callBack.bind( basepressKbImporter );
						_callBack( response );
					}
				},
				error: function( jqXHR, textStatus, errorThrown ){
					that.log( errorThrown );
				},
				complete: function(){
				}
			});
		},



		/**
		 * Update progress bar
		 *
		 * @param progressStatus
		 */
		updateProgressBar: function( progressStatus = '' ){

			let progressValue = this.progressSteps * this.processedItems;
			this.progressBar.css( 'width', Math.round(progressValue) + '%' );
			if( 'process_finished' == progressStatus ){
				this.progressBar.children( 'span' ).css( 'opacity', 0 );
			}
			else{
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

	window.basepressKbImporter = basepressKbImporter;
});