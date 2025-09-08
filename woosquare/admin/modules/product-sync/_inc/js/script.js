(function () {
	function showAndHideSyncDuration()
	{
		if (jQuery( "[name='woo_square_auto_sync']:checked" ).val() == "1") {
			jQuery( '.auto_sync_duration_div' ).show();
		} else {
			jQuery( '.auto_sync_duration_div' ).hide();
		}
	}

	function manualSync(event)
	{

		jQuery( '#woo_square_error' ).remove();
		var way = event.data.name;
		jQuery( "#manual_sync_" + way + "_btn" ).off( "click" );
		var button   = jQuery( "#manual_sync_" + way + "_btn" );
		var old_text = button.text();
		button.text( 'Processing ...' );
		button.attr( 'disabled', true );
		jQuery.ajax(
			{
				type: "GET",
				url: myAjax.ajaxurl,
				data: 'action=manual_sync&way=' + way,
				success: function (html) {
					if (html) {
						jQuery( '#manual_sync_wootosqu_btn' ).parents( '.welcome-panel' ).before( '<div id="woo_square_error" class="error"><p>' + html + '</p></div>' )
					}
					button.text( old_text );
					button.attr( 'disabled', false );
					jQuery( "#manual_sync_" + way + "_btn" ).on( "click", {name: way}, manualSync );
				}
			}
		);
	}

	function initPopup()
	{
		jQuery( "#sync-error,#sync-content-woo,#sync-content-square" ).html( '' );
		jQuery( "#sync-content,#sync-error,.cd-buttons.end,.cd-buttons.start" ).hide();
		jQuery( "#sync-loader" ).show();
		jQuery( '.cd-popup' ).addClass( 'is-visible' );
	}

	function processPopup()
	{
		jQuery( '.cd-buttons.start' ).hide();
		jQuery( '#sync-processing' ).text( 'Processing ...' ).prop( 'disabled', 'true' );
		jQuery( '.cd-buttons.end' ).show();

		// disable all checkboxes
		jQuery( "#sync-content input:checkbox" ).prop( 'disabled', 'true' );

	}

	function endPopup()
	{
		jQuery( '#sync-processing' ).text( 'Close' );
		jQuery( '#sync-processing' ).prop( 'disabled', false );

	}

	function show_woo_popup(action)
	{
		jQuery( '.cd-popup' ).css( "display", "block" );
		sync.caller = "woo";
		jQuery( '#start-process' ).data( "caller",sync.caller );
		initPopup();
		var customparams = '';
		var page         = 1;
		var limit;
		var totalPages;
		if (action == 'optionsaved') {
			customparams = '&optionsaved=true&from=woo';
		}
		getitems( myAjax,action,customparams,page,limit );
	}

	function getitems(myAjax,action,customparams,page,limit)
	{

		jQuery.ajax(
			{
				type: "GET",
				url: myAjax.ajaxurl,
				data: 'action=get_non_sync_woo_data' + customparams+'&woosquare_popup_nonce='+myAjax.ajaxnonce,
				success: function (response) {

					// ensure last user ckick was on woo->square
					if (sync.caller === 'woo') {
						response = JSON.parse( response );
						if (response.error) {
							jQuery( "#sync-content, #sync-loader" ).hide();
							jQuery( "#sync-error" ).show().html( response.error );
							endPopup();
							return;
						} else if ( ! response.data) {
							return;
						}
						// response = response.data;

						jQuery( "#sync-loader" ).hide();
						if (response.offset == 0) {
							jQuery( "#sync-content-" + sync.caller ).html( response.data );
							jQuery( ".cd-popup-container-loading p" ).html( response.count + '/' + response.totalitems );
						}
						jQuery( "#sync-content,.cd-buttons.start" ).show();
						if (page != 1) {
							var filtered = jQuery( jQuery.parseHTML( response.data ) );
							var filte    = filtered.find( '#sync-product' ).html();
							// console.log( filtered.find('.woo_square_product').length() );
							jQuery( filte ).appendTo( "#sync-product" );
							jQuery( ".cd-popup-container-loading p" ).html( response.count + '/' + response.totalitems );
						}
						if (action == 'optionsaved') {
							var cusurl = '&optionsaved=true&from=woo';
							var btntxt = 'UPDATE';
							jQuery( '#start-process' ).text( btntxt ).delay( 3000 );
							jQuery( '#sync-product h3' ).hide();
							// jQuery('#sync-category h3').hide();
						} else {
							var cusurl = '';
							var btntxt = 'Start Synchronization';
							jQuery( '#start-process' ).text( btntxt ).delay( 3000 );
						}
						if (page < response.totalPages) {
							jQuery( '#start-process' ).text( 'PRODUCTS LOADING...' ).delay( 3000 );
							jQuery( '#start-process' ).attr( 'disabled', true );
							page         = page + 1;
							customparams = '';
							customparams = cusurl;
							customparams = customparams + '&limit=' + response.limit + '&page=' + page;

							getitems( myAjax,action,customparams,page,limit )
						} else {
							jQuery( '#start-process' ).text( btntxt ).delay( 3000 );
							jQuery( '#start-process' ).attr( 'disabled', false );
						}
					}
					/*  jQuery('.check:button').toggle(function(){
					var chfrom = jQuery(this).attr('class').split(' ');
					if('extpro' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_product"]:checkbox').attr('checked','checked');
					jQuery(this).val('Uncheck All')
					} else if('extcat' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_category"]:checkbox').attr('checked','checked');
					jQuery(this).val('Uncheck All')
					}
					},function(){
					var chfrom = jQuery(this).attr('class').split(' ');
					if('extpro' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_product"]:checkbox').removeAttr('checked');
					jQuery(this).val('Check All');
					} else if('extcat' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_category"]:checkbox').removeAttr('checked');
					jQuery(this).val('Check All');
					}

					});*/
				}
			}
		);
	}
	function show_square_popup(action)
	{
		jQuery( '.cd-popup' ).css( "display", "block" );
		sync.caller = "square";
		jQuery( '#start-process' ).data( "caller",sync.caller );
		initPopup();

		var customparams = '';
		if (action == 'optionsaved') {
			customparams = '&optionsaved=true&from=square';
		}
		jQuery.ajax(
			{
				type: "GET",
				url: myAjax.ajaxurl,
				data: 'action=get_non_sync_square_data' + customparams + '&woosquare_popup_nonce='+myAjax.ajaxnonce,
				success: function (response) {
					// ensure last user ckick was on square->woo
					if (sync.caller === 'square') {
						response = JSON.parse( response );
						if (response.error) {
							jQuery( "#sync-content, #sync-loader" ).hide();
							jQuery( "#sync-error" ).show().html( response.error );
							endPopup();
							return;
						} else if ( ! response.data) {
							return;
						}

						response = response.data;

						jQuery( "#sync-loader" ).hide();
						jQuery( "#sync-content-" + sync.caller ).html( response );
						jQuery( "#sync-content,.cd-buttons.start" ).show();
						if (action == 'optionsaved') {
							jQuery( '#start-process' ).text( 'UPDATE' ).delay( 3000 );
							jQuery( '#sync-product h3' ).hide();
							// jQuery('#sync-category h3').hide();
						} else {
							jQuery( '#start-process' ).text( 'Start Synchronization' ).delay( 3000 );
						}

					}
					/*         jQuery('.check:button').toggle(function(){
					var chfrom = jQuery(this).attr('class').split(' ');
					if('extpro' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_product"]:checkbox').attr('checked','checked');
					jQuery(this).val('Uncheck All')
					} else if('extcat' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_category"]:checkbox').attr('checked','checked');
					jQuery(this).val('Uncheck All')
					}
					},function(){
					var chfrom = jQuery(this).attr('class').split(' ');
					if('extpro' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_product"]:checkbox').removeAttr('checked');
					jQuery(this).val('Check All');
					} else if('extcat' == chfrom[7]){
					jQuery('.square-action input[name*="woo_square_category"]:checkbox').removeAttr('checked');
					jQuery(this).val('Check All');
					}

					});*/

				}
			}
		);
	}

	var sync      = [];
	sync.product  = [];
	sync.category = [];
	sync.caller   = '';

	function startManualSync(caller)
	{

		processPopup();
		// if(caller == 'listsaved_square' || caller == 'listsaved_woo'){
		var sync      = [];
		sync.product  = [];
		sync.category = [];
		// }
		jQuery( '#sync-product input:checkbox[name="woo_square_product"]:checked' ).each(
			function () {
					sync.product.push( jQuery( this ).val() );
			}
		);
		jQuery( '#sync-category input:checkbox[name="woo_square_category"]:checked' ).each(
			function () {
					sync.category.push( jQuery( this ).val() );
			}
		);

		if (caller == 'listsaved_square' || caller == 'listsaved_woo') {
			var action   = 'listsaved';
			var method   = "POST";
			var ajAxdata = {
				action: action ,
				products: JSON.stringify( sync.product ),
				categories: JSON.stringify( sync.category ),
				saveto: caller == 'listsaved_square' ? 'square' : 'wooco',
				ajaxnonce: myAjax.ajaxnonce,
			};
		} else {
			var action   = caller == 'woo' ? 'woo_to_square' : 'square_to_woo';
			var method   = "GET";
			var ajAxdata = 'action=' + "start_manual_" + action + "_sync";
		}

		jQuery.ajax(
			{
				type: method,
				url: myAjax.ajaxurl,
				data: ajAxdata,
				success: function (response) {
					if (response.trim() == '1') {
						if (caller == 'listsaved_square' || caller == 'listsaved_woo') {
							jQuery( "#sync-content" ).hide();
							jQuery( "#sync-error" ).show().html( "<span class=\"dashicons dashicons-yes right\"></span>Sync Preference Successfully Saved!" );
							jQuery( '#sync-processing' ).text( 'Close' );
							jQuery( '#sync-processing' ).prop( 'disabled', false );

						} else {
							syncCategoryOrProduct( caller, 'category',sync );
						}

					} else {
						jQuery( "#sync-content" ).hide();
						jQuery( "#sync-error" ).show().html( response );
					}
				},
				error: function (response) {
					jQuery( "#sync-content" ).hide();

					jQuery( "#sync-error" ).show().html( "Error occurred!" );
				}
			}
		);

	}

	let scrollHeight = 20;
	function syncCategoryOrProduct(caller, target,sync)
	{

		var currentProdId = sync[target].shift();

		if ( ! currentProdId) {
			if (target == 'category') {
				syncCategoryOrProduct( caller, 'product',sync );
			} else {
				terminateManualSync( jQuery( '#start-process' ).data( "caller" ) );
			}
			return;
		}
		var action = caller == 'woo' ? 'sync_woo_' + target + '_to_square' :
			'sync_square_' + target + '_to_woo';
		jQuery.ajax(
			{
				type: "POST",
				url: myAjax.ajaxurl,
				data: 'action=' + action + '&id=' + currentProdId + '&ajaxnonce='+myAjax.ajaxnonce,
				success: function (response) {
					if (response == 1) {
						if (target == 'category') {
							jQuery( '#sync-' + target + ' input:checkbox[name="woo_square_' + target + '"].woo_square_category[value="' + currentProdId + '"]' ).parent( 'div' ).append( '<span class="dashicons dashicons-yes right"></span>' ).addClass( 'sync-success' );

						} else {
							jQuery( '#sync-' + target + ' input:checkbox[name="woo_square_' + target + '"].woo_square_product[value="' + currentProdId + '"]' ).parent( 'div' ).append( '<span class="dashicons dashicons-yes right"></span>' ).addClass( 'sync-success' );

						}
						scrollHeight = scrollHeight + jQuery( '.square-action' ).height();

						jQuery( '.scrollwrap' ).animate( {scrollTop:scrollHeight + jQuery( '.sync-success' ).height()}, 'fast' );
					} else {
						if (currentProdId == "update_products") {
							var ress = JSON.parse( response );

							var len = ress.length - 1;
							if (ress.length > 0) {
								var i = 0;

								var xReturn = ajaxCall( ress,len,my_ajax_backend_scripts,i );
							}
							jQuery( '#sync-' + target + ' input:checkbox[name="woo_square_' + target + '"][value="' + currentProdId + '"]' ).parent( 'div' ).append( '<span class="dashicons dashicons-yes right"></span>' ).addClass( 'sync-success' );
						} else {
								jQuery( '#sync-' + target + ' input:checkbox[name="' + target + '"][value="' + currentProdId + '"]' ).parent( 'div' ).append( '<span class="dashicons dashicons-no-alt right"></span>' ).addClass( 'sync-failure' );
						}
					}

				},
				error: function (error) {
					jQuery( "#sync-" + target + " input:checkbox[name=woo_square_" + target + "][value=" + currentProdId + "]" ).parent( "div" ).append( "<span class='dashicons dashicons-no-alt right'></span>" ).addClass( 'sync-failure' );
				},
				complete: function () {
					syncCategoryOrProduct( caller, target,sync );

				}
			}
		);
	}

	function ajaxCall(ress,len,my_ajax_backend_scripts,i)
	{
		if (ress[i] && ress[i].name) {
			jQuery.ajax(
				{
					type: "POST",
					url: my_ajax_backend_scripts.ajax_url,
					data: 'action=update_square_to_woo&import_js_item=' + JSON.stringify( ress[i] ) + '&session_targets=' + JSON.stringify( ress[len] )+'&nonce='+my_ajax_backend_scripts.nonce,
				}
			).always(
				function (html) {

					jQuery( '#sync-processing' ).text( 'Processing ...' ).prop( 'disabled', 'true' );
					jQuery( '#sync-processing' ).prop( 'disabled', true );

					/*if (typeof ress[i].name  !== "undefined"){*/
					/*    jQuery('<div class="square-action sync-success"><input name="woo_square_product" type="checkbox" value="update_products" checked="" disabled="">'+ress[i].name+'<span class="dashicons dashicons-yes right"></span> </div>').appendTo('#sync-product .square-update');
					jQuery('.sync-data').animate({scrollTop:jQuery('.sync-elements').height()+jQuery('.square-update').height()}, 'fast');
					*/
					/*}*/
					/*if(ress[i].name){*/
					// if( i<=len ){
					
					if (ress[i].name) {
						jQuery( '<div class="square-action sync-success"><input name="woo_square_product" type="checkbox" value="update_products" checked="" disabled="">' + ress[i].name + '<span class="dashicons dashicons-yes right"></span> </div>' ).appendTo( '#sync-product .square-update' );
						scrollHeight = scrollHeight + jQuery( '.square-action' ).height();
						jQuery( '.scrollwrap' ).animate( {scrollTop:scrollHeight + jQuery( '.sync-success' ).height()}, 'fast' );

						i++;
						ajaxCall( ress,len,my_ajax_backend_scripts,i );

					} else {
						jQuery( '#sync-processing' ).text( 'Close' );
						jQuery( '#sync-processing' ).prop( 'disabled', false );
					}

					// var totalTime = new Date().getTime()-ajaxTime;
					// var htmls = jQuery.parseJSON( html );
					// jQuery('.store_id_'+stores_json_encoded[del].Code).css('background-color','#dff0d8');
					// setTimeout(function(){
						// jQuery('.store_id_'+stores_json_encoded[del].Code).css('text-decoration','line-through');
						// jQuery('.store_id_'+stores_json_encoded[del].Code +' + br').remove();
						// jQuery('.store_id_'+stores_json_encoded[del].Code).fadeOut('slow');
						// jQuery('#current-count').text(del);
						// del++;
					// }, 500);
				}
			);
		} else {
			endPopup();
		}
	}

	/*
	function ajaxDone(ress,len,my_ajax_backend_scripts){

	// if successful, place second call
	// if(parseInt(msg)==1){
		xReturn = ajaxCall(ress,len,my_ajax_backend_scripts);

		// Bind a callback to this *new* object
		xReturn.success(ajaxDone);
	// }
	} */

	function deleteManualSyncTransients(caller)
	{
		if(caller == 'woo'){
			jQuery.ajax(
				{
					type: "POST",
					url: myAjax.ajaxurl,
					data: 'action=delete_manual_' + caller + '_sync_transients',
					success: function (html) {
						// endPopup();
					}
				}
			);
		}
	}
	function terminateManualSync(caller)
	{
		jQuery.ajax(
			{
				type: "POST",
				url: myAjax.ajaxurl,
				data: 'action=terminate_manual_' + caller + '_sync',
				success: function (html) {
					endPopup();
				}
			}
		);
	}

	// Function to process array in chunks
	function processArrayInChunks(arr, chunkSize, callback)
	{
		for (var i = 0; i < arr.length; i += chunkSize) {
			callback( arr.slice( i, i + chunkSize ) );
		}
	}
	function get_data_by_category(categories, caller)
	{
		jQuery( '#sync-product .square-action' ).hide(); 
		jQuery.ajax(
			{
				type: 'POST',
				url: myAjax.ajaxurl,
				data: {
					action: 'get_data_by_category',
					selected_categories: categories,
					caller: caller,
					ajaxnonce: myAjax.ajaxnonce,
				},
				error: function (e) {
					jQuery( '#overlay' ).hide();
					console.log( e );
				},
				success: function (result) {
					// Cached selectors
					var $overlay               = jQuery( '#overlay' );
					var $syncProductCheckboxes = jQuery( '#sync-product input:checkbox[name="woo_square_product"]' );
					var $squareAction          = jQuery( '.square-action input[name="woo_square_product"]' );
					var chunkSize              = 10;
					// Hide overlay
					$overlay.hide();
					$squareAction.prop( "checked", false ).removeAttr( 'checked' );
					if (result) {
						result = JSON.parse( result );
						if (Array.isArray( result )) {
								// Process result array in chunks
							processArrayInChunks(
								result,
								chunkSize,
								function (chunk) {
									chunk.forEach(
										function (number) {
											$syncProductCheckboxes.each(
												function () {
													var $this = jQuery( this );
													
													if ($this.val() == number) {
														jQuery( '#square-action-' + number ).show();
														$this.prop( "checked", true ).attr( 'checked', 'checked' );
														$squareAction.filter( '.modifier_end' ).prop( 'checked', true );
													} else if ($this.val() == 'update_products') {
														jQuery( '#sync-product .update_products_action' ).show();
														$this.prop( "checked", true ).attr( 'checked', 'checked' );
													} 
												}
											);
										}
									);
								}
							);
						}
					} else {
						jQuery( '#sync-product .square-action' ).show();
						$squareAction.removeAttr( 'checked' ).prop( "checked", false );
						$squareAction.filter( '.modifier_end' ).prop( 'checked', false );
					}
				}
			}
		);
	}

	function changes_in_category(){
		var selected_categories = [];
		jQuery( '#sync-category input:checkbox[name="woo_square_category"]:checked' ).each(
			function () {
				selected_categories.push( jQuery( this ).val() );
			}
		);
		if (jQuery( '.category-toggle' ).val() == 1) {
			jQuery( '#overlay' ).show();
			var caller = jQuery( '#start-process' ).data( "caller" );
			get_data_by_category( selected_categories, caller );
		}
	}

	// Bind events to the page
	jQuery( document ).ready(
		function (jQuery) {

			jQuery( "#manual_sync_squtowoo_btn" ).on( "click", {name: 'squtowoo'}, show_square_popup );
			jQuery( "#manual_sync_wootosqu_btn" ).on( "click", {name: 'wootosqu'}, show_woo_popup );

			jQuery( document ).on(
				'change',
				'.woo_square_category' ,
				function (e) {
					// jQuery("[name='woo_square_category']").on('change', function(){
					changes_in_category();
				}
			)

			// pop-up
			// close popup
			jQuery( '.cd-popup' ).on(
				'click',
				function (event) {
					if (jQuery( event.target ).is( '.cd-popup-close' ) || jQuery( event.target ).is( '.cd-popup' )) {
						event.preventDefault();
						jQuery( this ).removeClass( 'is-visible' );

						deleteManualSyncTransients( jQuery( '#start-process' ).data( "caller" ) );

					}
				}
			);
			// close popup when clicking the esc keyboard button
			jQuery( document ).keyup(
				function (event) {
					if (event.which == '27') {
						jQuery( '.cd-popup' ).removeClass( 'is-visible' );
						// terminateManualSync(jQuery('#start-process').data("caller"));
					}
				}
			);

			// cron settings on change event
			jQuery( "[name='woo_square_auto_sync']" ).on(
				'change',
				function () {
					showAndHideSyncDuration();
				}
			);

			jQuery( ".woo_square_sync_preference" ).on(
				'click',
				function () {

					if (jQuery( "[name='woo_square_sync_preference']:checked" ).val() != 1) {
						if (jQuery( this ).val() == 0) {
							if (jQuery( "[name='woo_square_merging_option']:checked" ).val() == 1) {
									show_woo_popup( 'optionsaved' );
									jQuery( '#start-process' ).data( "caller",'listsaved_woo' );
							} else if (jQuery( "[name='woo_square_merging_option']:checked" ).val() == 2) {
									show_square_popup( 'optionsaved' );
									jQuery( '#start-process' ).data( "caller",'listsaved_square' );
							}

						}
					}

				}
			);
			jQuery( "[name='woo_square_sync_preference']" ).on(
				'change',
				function () {

					if (jQuery( this ).val() == 0) {
						if (jQuery( "[name='woo_square_merging_option']:checked" ).val() == 1) {
							show_woo_popup( 'optionsaved' );
							jQuery( '#start-process' ).data( "caller",'listsaved_woo' );
						} else if (jQuery( "[name='woo_square_merging_option']:checked" ).val() == 2) {
							show_square_popup( 'optionsaved' );
							jQuery( '#start-process' ).data( "caller",'listsaved_square' );
						}

					}
				}
			);


			function toggleEditListVisibility() {
				var selectedValue = jQuery('input[name="woo_square_sync_preference"]:checked').val();

				if (selectedValue === "1") {
					jQuery('.woosquare_edit_sync').hide();
				} else {
					jQuery('.woosquare_edit_sync').show();
				}
			}

			// Run on page load
			toggleEditListVisibility();

			// Run when radio buttons change
			jQuery('input[name="woo_square_sync_preference"]').on('change', function() {
				toggleEditListVisibility();
			}); 

			if (jQuery( "[name='sync_on_add_edit']:checked" ).val() != 1) {
				jQuery( '.pro_fields' ).fadeOut();
			} else {
				jQuery( '.pro_fields' ).fadeIn();
			}

			var radios = jQuery('input[name="sync_on_add_edit"]');

			if (!radios.is(':checked')) {
				radios.filter('[value="2"]').prop('checked', true);
			} 

			jQuery( "[name='sync_on_add_edit']" ).on(
				'change',
				function () {

					if (jQuery( this ).val() == 1) {
						jQuery( '.pro_fields' ).fadeIn();
					} else {
						jQuery( '.pro_fields' ).fadeOut();
					}
				}
			);
			jQuery( "[name='woo_square_merging_option']" ).on(
				'change',
				function () {
					var sync      = [];
					sync.product  = [];
					sync.category = [];

					jQuery( ".woo_square_merging_option" ).removeAttr( 'checked' );
					jQuery( this ).prop( 'checked', true );
				}
			);

			jQuery( '.cancel-process' ).on(
				'click',
				function (event) {
					event.preventDefault();
					jQuery( '.cd-popup' ).removeClass( 'is-visible' );
					terminateManualSync( jQuery( '#start-process' ).data( "caller" ) );
				}
			);

			jQuery( '#start-process' ).on(
				'click',
				function (event) {
					event.preventDefault();
					startManualSync( jQuery( '#start-process' ).data( "caller" ) );
					/* if(
					jQuery('#start-process').data("caller") == 'woo'
					||
					jQuery('#start-process').data("caller") == 'square'

					){
					startManualSync(jQuery('#start-process').data("caller"));
					} else if(jQuery('#start-process').data("caller") == 'listsaved'){

					startManualSync(jQuery('#start-process').data("caller"));
					} */
				}
			);

			jQuery( '#sync-processing' ).on(
				'click',
				function (event) {
					event.preventDefault();
					jQuery( '.cd-popup' ).removeClass( 'is-visible' );
					deleteManualSyncTransients( jQuery( '#start-process' ).data( "caller" ) );
				}
			);

			jQuery( '.collapse' ).on(
				'click',
				function () {
					jQuery( this ).siblings( '.grid-div' ).toggleClass( "hidden collapse-content-show" );
					jQuery( this ).children( ".dashicons" ).toggleClass( 'collapse-open' )
				}
			);

			showAndHideSyncDuration();

			// jQuery('.category-toggle').change(function() {
		jQuery( document ).on(
			'change',
			'.category-toggle' ,
			function (e) {
				e.preventDefault();
				if (this.checked) {
					jQuery( '.square-action input[name="woo_square_product"]' ).removeAttr( 'checked' );
					jQuery( 'square-action input[name="woo_square_product"]' ).prop( "checked", false );
					jQuery( '.square-action input[name="woo_square_product"].modifier_end' ).prop( 'checked', false );
					jQuery( '.square-action input[name="woo_square_category"]' ).removeAttr( 'checked' );
					jQuery( '.square-action input[name="woo_square_category"]' ).prop( "checked", false );
					jQuery( '.check:button' ).val( 'Check All' );
					jQuery( this ).val( 1 );
				} else {
					jQuery( '#sync-product .square-action' ).show();
					jQuery( '.square-action input[name="woo_square_category"]' ).attr( 'checked','checked' );
					jQuery( '.square-action input[name="woo_square_category"]' ).prop( "checked", true );
					jQuery( '.square-action input[name="woo_square_product"]' ).attr( 'checked','checked' );
					jQuery( '.square-action  input[name="woo_square_product"]' ).prop( "checked", true );
					jQuery( '.square-action input[name="woo_square_product"].modifier_end' ).prop( 'checked', true );
					jQuery( '.check:button' ).val( 'Uncheck All' );
					jQuery( this ).val( '' );
				}
			}
		);

		jQuery( document ).on(
			'click',
			'.check:button' ,
			function (evv) {
				evv.preventDefault();

				var chfrom = jQuery( this ).attr( 'class' ).split( ' ' );
				if ('extpro' == chfrom[7]) {
					var checkbox = jQuery( '.square-action input[name="woo_square_product"]' );
					if (checkbox.attr( 'checked' ) == 'checked') {
							jQuery( '.square-action input[name="woo_square_product"]' ).removeAttr( 'checked' );
						jQuery( 'square-action input[name="woo_square_product"]' ).prop( "checked", false );
						jQuery( '.square-action input[name="woo_square_product"].modifier_end' ).prop( 'checked', true );
						jQuery( this ).val( 'Check All' );

					} else if (checkbox.attr( 'checked' ) != 'checked') {
							jQuery( '.square-action input[name="woo_square_product"]' ).attr( 'checked','checked' );
						jQuery( '.square-action  input[name="woo_square_product"]' ).prop( "checked", true );
						jQuery( '.square-action input[name="woo_square_product"].modifier_end' ).prop( 'checked', true );
						jQuery( this ).val( 'Uncheck All' );
					}
				} else if ('extcat' == chfrom[7]) {

					var checkbox = jQuery( '.square-action input[name="woo_square_category"]' );
					if (checkbox.attr( 'checked' ) == 'checked') {
						jQuery( '.square-action input[name="woo_square_category"]' ).removeAttr( 'checked' );
						jQuery( '.square-action input[name="woo_square_category"]' ).prop( "checked", false );
						jQuery( this ).val( 'Check All' );
					} else if (checkbox.attr( 'checked' ) != 'checked') {
						jQuery( '.square-action input[name="woo_square_category"]' ).attr( 'checked','checked' );
						jQuery( '.square-action input[name="woo_square_category"]' ).prop( "checked", true );
						jQuery( this ).val( 'Uncheck All' );
					}
					changes_in_category();
				}
			}
		)

		jQuery( ".subsubsub li" ).each(
			function (index,key) {
				if (jQuery( this ).text().trim() == 'Square') {
					jQuery( this ).remove();

				}
				if (jQuery( this ).text().trim() == 'Square |') {
					jQuery( this ).remove();
				}

			}
		);

		var textt = jQuery( ".subsubsub li" ).last().html();
		if (textt) {
			var splittext = textt.split( '|' );

			jQuery( ".subsubsub li" ).last().html( splittext[0] );
		}


		}
	);

})();


