// get ajaxs url for send ajax request
function ajaxsURL( action ){
	return window.jxs.url + action;
}

// GET method
function ajaxsGET( action, data, doneFunc, alwaysFunc, failFunc ){
	return ajaxs( action, data, doneFunc, alwaysFunc, failFunc, { method:'GET' } );
}

// POST method
function ajaxs( action, data, doneFunc, alwaysFunc, failFunc, _data ){

	var _method = ( _data && _data.method ) ? _data.method : 'POST'

	// check 'data' parameter
	if( ! data ){
		data = {}
	}
	// if data specified as function, it means that data not set and it becomes doneFunc...
	else if( typeof data === 'function' ){
		// order is important!
		failFunc   = alwaysFunc
		alwaysFunc = doneFunc
		doneFunc   = data
		data       = {}
	}

	// early retake AJAX data to variable to not pass it in ajaxsData()
	var moreAJAXdata = typeof data.ajax === 'object' ? data.ajax : {}
	delete data.ajax

	// file upload progress function
	var uploadProgress = typeof data.uploadProgress === 'function' ? data.uploadProgress : false
	delete data.uploadProgress

	// large files size error handler function
	var largeFileError = typeof data.largeFileError === 'function' ? data.largeFileError : false
	delete data.largeFileError

	// simple object data (never FormData) for pass to event data
	var objdata = {}
	objdata.origin_data = data // set data before parse
	objdata.action = action

	// if 'data' specified as jQuery element or DOM element, get data from it...
	// or check is there Files in 'data'
	var parsed = ajaxsData( data )
	data    = parsed[0]
	objdata = jQuery.extend( objdata, parsed[1] )
	var filesSizeData = parsed[2]

	// now data can be simple object or FormData object
	// FormData uses only when FILE is uploaded
	var is_FormData = data instanceof FormData

	// action parameter reserved
	if( ( is_FormData && data.get('action') ) || data.action ){
		is_FormData ? data.delete('action') : delete data.action
		console.warn( 'AJAXS NOTICE: `data.action` parameter was deleted. It is reserved by Ajax Simply. Change the name of the parameter.' )
	}

	var jx = {} // AJAX request settings

	// FormData uses only when FILE is uploaded
	if( is_FormData ){

		jx.processData = false
		jx.contentType = false

		if( _method === 'GET' ){
			_method = 'POST' // forse
			console.log( 'AJAX contain FILE data. So request method can`t be GET, it turned to POST... Fix your code...' )
		}

		// progress for file upload
		if( uploadProgress ){
			jx.xhr = function(){
				var xhr = jQuery.ajaxSettings.xhr()

				xhr.upload.addEventListener( 'progress', function(event){
					if ( ! event.lengthComputable ) {
						console.log( 'NOTICE: total uploaded file size is not known. uploadProgress data is not available.' )
						return
					}

					var percent = Math.ceil( event.loaded / event.total * 100 )
					uploadProgress( percent, event )
				})

				return xhr
			}
		}

	}
	//	else {
	//
	//		// check the data for incorrect value...
	//		var stop;
	//		jQuery.each( data, function(ind, val){
	//			if( val instanceof jQuery || val instanceof HTMLElement ){
	//				console.error( 'DATA ERROR: You set jQuery or HTML element in the value of passed data... Fix your code!' );
	//				stop = true;
	//				return false; // break
	//			}
	//		} );
	//		if( stop ) return;
	//	}

	jx.url      =  ajaxsURL( action )
	jx.dataType = 'json' // important when data returns...
	jx.method   =  _method
	jx.data     =  data

	let consoleHTML = {

		elem   : null,
		html   : '',
		method : function( cfunc, save_cfunc ){

			return function(){

				console[ save_cfunc ].apply( null, arguments ) // default behavior

				// group
				if( 'group' === cfunc ){
					consoleHTML.html += `<div class="ajaxs-console-group">`
					if( arguments[0] )
						consoleHTML.html += `<span class="group-name">${arguments[0]}</span>`
				}
				// groupEnd
				else if( 'groupEnd' === cfunc ){
					consoleHTML.html += `</div>`
				}
				// log / error / warn / info
				else {

					let out = '', _out = '', arg, i

					for( i = 0; i < arguments.length; i++ ){
						arg = arguments[i]

						if( typeof arg === 'object' )
							_out = JSON.stringify( arg )
						else
							_out = arg

						out += `<div class="ajaxs-console-line type-${cfunc} valtype-${typeof arg}">${_out}</div>`
					}

					consoleHTML.html += out
				}
			}
		},
		process   : function( action = 'on' ){

			// this.elem нужно устанавливать каждый раз снова,
			// потому что он может пропасть при очередных запросах
			this.elem = document.querySelector( '#ajaxs-console, .ajaxs-console' )

			if( ! this.elem )
				return

			this.addCSS()
			this.wrapElem()

			if( 'on' === action )
				consoleHTML.html = ''

			let consoleMethods = [ 'log', 'error', 'warn', 'info', 'group', 'groupEnd' ]

			for( let nn = 0; nn < consoleMethods.length; nn++ ){

				let cfunc      = consoleMethods[ nn ]
				let save_cfunc = `ajaxsSave_${cfunc}`

				// on
				if( 'on' === action ){
					// save original console methods
					console[ save_cfunc ] = console[ cfunc ]
					console[ cfunc ]      = consoleHTML.method( cfunc, save_cfunc )
				}
				// off
				else if( console[ save_cfunc ] ) {
					console[ cfunc ] = console[ save_cfunc ]
				}

			}

			if( 'off' === action )
				consoleHTML.elem.innerHTML += consoleHTML.html
		},
		addCSS : function(){

			if( this.elem.dataset.cssAdded )
				return
			this.elem.dataset.cssAdded = 1

			let styles    = `
			.ajaxs-console-line{ border-bottom:1px solid rgba(0,0,0,.1); }
			.ajaxs-console-group{ margin-left:1em; }
			.ajaxs-console-group .group-name{ display:block; margin-left:-1em; font-weight:bold; }
			.ajaxs-console-line.type-error{ color:red }
			`
			let styleNode = document.createElement('style')
			styleNode.type = "text/css";

			( document.head || document.getElementsByTagName('head')[0] ).appendChild( styleNode )

			styleNode.appendChild( document.createTextNode(styles) )

		},
		wrapElem: function(){
			/*
			<div class="ajaxs-console-wrap">
				<a class="ajaxs-console-clear" href="#" onclick="this.nextElementSibling.innerHTML=''; return false">Clear Ajaxs Console</a>
				<div class="ajaxs-console"></div>
			</div>
			*/

			if( this.elem.dataset.wrapped )
				return
			this.elem.dataset.wrapped = 1

			let wrap  = document.createElement('div')
			wrap.classList.add( 'ajaxs-console-wrap' )
			let clear = document.createElement('a')
			clear.classList.add( 'ajaxs-console-clear' )
			clear.href = '#'
			clear.onclick = function(){ this.nextElementSibling.innerHTML=""; return false }
			clear.innerHTML = 'Clear Ajaxs Console'

			// insert wrapper before el & move el into wrapper
			this.elem.parentNode.insertBefore( wrap, this.elem )
			wrap.appendChild( clear )
			wrap.appendChild( this.elem )

		},
	}


	// special actions
	let extraData = null
	let fn__responseExtra = function( extraData ){

		jQuery.each( extraData, function( key, val ){

		    if( 'reload' === key ){
				setTimeout( function(){  window.location.reload()  }, parseInt(val) )
			}
			else if( 'redirect' === key ){
				setTimeout( function(){  document.location.href = val[0]  }, parseInt(val[1]) )
			}
			else if( 'console' === key ){

				var groups = {}

				val.forEach(function( _val ){
					var group = _val[2] || 'no'

					if( ! groups[ group ] )
						groups[ group ] = []

					groups[ group ].push( { data: _val[0], type: _val[1] } )
				})

				Object.keys( groups ).forEach(function(group){

					if( 'no' !== group )
						console.group( group )

					groups[ group ].forEach(function( obj ){
						console[ obj.type ]( obj.data )
					})

					if( 'no' !== group )
						console.groupEnd()
				})

			}
			else if( 'alert' === key ){

				val.forEach(function( _val ){
					alert( _val )
				})
			}
			else if( 'html' === key ){

				val.forEach(function( _val ){
					var $el    = jQuery( _val[0] )
					var html   = _val[1]
					var method = _val[2]

					if( $el.length )
						$el[ method ]( html )
				})
			}
			else if( 'trigger' === key ){

				val.forEach(function( _val ){
					var selector = (_val[1] && _val[1] !== 'document') ? _val[1] : document
					jQuery( selector ).trigger( _val[0], _val[2] )
				})
			}
			else if( 'call' === key ){

				val.forEach(function( _val ){
					var funcName    = _val[0]
					var funcNameArr = funcName.replace(/^window\./, '').split('.') // for specify 'window.functionName' or 'functionName'
					var parent      = window
					var func        = parent[ funcNameArr[0] ]

					// есть точка в названии // пропускаем 0 индекс (он уже использован)
					for( var indx = 1; indx < funcNameArr.length; indx++ ){
						if( funcNameArr[indx] in func ){
							parent = func
							func   = parent[ funcNameArr[indx] ]
						}
					}

					if( typeof func === 'function' ){
						func.apply( parent, _val[1] )
						//func( _val[1] )
						//window[ _val[0] ]( _val[1] )
					}
					else {
						console.log( 'Function window.'+ funcName +'() not found. Declare it please.' )
					}
				})
			}
			else if( 'jseval' === key ){

				val.forEach(function( _val ){
					//eval( _val );
					eval( '(function(){'+ _val +'})()' ) // in order to 'return' inside 'eval' dont crash the code...
				})
			}
		})
	}

	// filter server response - extracts 'extra' data to variable
	jx.dataFilter = function( respData, type ){

		if( respData.indexOf('"extra":') !== -1 ){

			var resp = JSON.parse( respData )

			if( typeof resp.extra !== 'undefined' ){
				// special actions
				//fn__responseExtra( resp.extra ); // should be called after the user functions...
				extraData = resp.extra // save

				respData = JSON.stringify( resp.response )
			}
		}

		return respData
	}

	// response are filtered by dataFilter function
	jx.success = function( response, status, xhr ){
		consoleHTML.process('on')

		// callback
		if( typeof doneFunc === 'function' )
			doneFunc( response )

		// deffer to call it after appended xhr.done()
		setTimeout( ()=>{

			if( extraData )
				fn__responseExtra( extraData )

			jQuery(document).trigger( 'ajaxs_done', [objdata, jx] )

			//consoleHTML.process('off') // in jx.complete()
		}, 0 )
	}

	// request fail
	jx.error = function( xhr, status, error ){
		consoleHTML.process('on')

		if( typeof failFunc === 'function' )
			failFunc()
		else
			console.log( 'Response error: ' + error )

		if( typeof xhr.responseJSON !== 'undefined' )
			fn__responseExtra( xhr.responseJSON.extra )

		// deffer to call it after appended xhr.fail()
		setTimeout( ()=>{

			jQuery(document).trigger( 'ajaxs_fail', [objdata, jx, xhr, status, error] )

			//consoleHTML.process('off') // in jx.complete()
		}, 0 )
	}

	// request always
	// contains `jx.error` or `jx.success` and runs after it
	jx.complete = function( xhr, status ){
		// consoleHTML.process('on')

		if( typeof alwaysFunc === 'function' )
			alwaysFunc()

		// deffer to call it after appended xhr.always()
		setTimeout( ()=>{
			jQuery(document).trigger( 'ajaxs_always', [objdata, jx, xhr, status] )

			consoleHTML.process('off')
		}, 0 )
	}

	jQuery(document).trigger( 'ajaxs_start', [objdata, jx] )

	// append more jx passed to ajaxs() function in 'ajax' parameter
	jQuery.each( moreAJAXdata, function( key, val ){
		jx[ key ] = val
	} )

	var xhr = jQuery.ajax( jx )

	// large file error function handler
	if( is_FormData ){

		var abort_xhr = false
		var fileSize = function( bytes ){
			var i = -1, units = ['kB','MB','GB','TB','PB']
			do {
				bytes = bytes / 1024
				i++
			} while ( bytes > 1024 )

			return Math.max( bytes, 0.1 ).toFixed(1) +' '+ units[i]
		};

		if( ! largeFileError ){
			largeFileError = function( curBigSize, allowedSize, filename ){
				if( filename )
					alert( 'ERROR: The file size is too Large: '+ curBigSize +' (max allowed: '+ allowedSize +').\n\nFile Name: '+ filename )
				else
					alert( 'ERROR: The total files size is too Large: '+ curBigSize +' (max allowed: '+ allowedSize +').' )
			};
		}

		// check allowed file size
		if( window.jxs && window.jxs.post_max_size ){

			if( (filesSizeData.total > window.jxs.post_max_size) && (filesSizeData.data.length > 1) ){

				largeFileError( fileSize(filesSizeData.total), fileSize(window.jxs.post_max_size) )

				abort_xhr = true // stop ajaxs
			}
		}

		// check allowed file size
		if( ! abort_xhr && window.jxs && window.jxs.upload_max_filesize ){

			for( var i = 0; i < filesSizeData.data.length; i++ ){
				var fdata = filesSizeData.data[i]

				if( fdata.size > window.jxs.upload_max_filesize ){

					largeFileError( fileSize(fdata.size), fileSize(window.jxs.upload_max_filesize), fdata.name )

					abort_xhr = true // stop ajaxs
				}
			}
		}

		if( abort_xhr )
			xhr.abort()
	}

	return xhr
}

function ajaxsData( elemORdata ){

	// collected
	if( elemORdata instanceof FormData )
		return elemORdata;

	let fn__isElement = function( el ){
		return el instanceof jQuery || el instanceof HTMLElement;
	};

	let data = {};

	// if HTML element passed - collect fields data
	if( fn__isElement(elemORdata) ){
		data = _ajaxsCollectData( elemORdata );
	}
	// object passed as parameters
	else {
		// if data value contain 'HTML element' add it's input values to 'data'
		jQuery.each( elemORdata, function(name, val){
			if( fn__isElement(val) )
				jQuery.extend( data, _ajaxsCollectData( val ) ); // merge
			else
				data[ name ] = val;
		});
	}

	// check - if 'data' has 'Files' objects and collect it for future use
	let filesSizeData = { total: 0, data:[] };
	let hasFiles = false;
	jQuery.each( data, function( name, val ){

		if( /__isFileType$/.test(name) || (val instanceof File) || (val instanceof FileList) ){
			hasFiles = true;

			if( val instanceof File ) {
				filesSizeData.total += val.size;
				filesSizeData.data.push({ name: val.name, size: val.size });
			}
			else {

				// FileList object or array of Files objects
				for( let i = 0; i < val.length; i++ ){
					filesSizeData.total += val[i].size;
					filesSizeData.data.push({ name: val[i].name, size: val[i].size });
				}
			}
		}
	});

	// save not FormData object for future use
	let objdata = jQuery.extend( {}, data ); // clone

	// convert all data to FormData object, when files are set
	if( hasFiles ){

		if( window.FormData === 'undefined' ){
			console.log( 'ERROR: browser not support javascript FormData() object. Files can not be sent...' );
		}
		else {
			let FData = new FormData();

			// not files
			jQuery.each( data, function( name, val ){

				name = name.replace( /__isFileType$/, '' ) // remove mark

				// single file
				if( val instanceof File ){
					FData.append( name, val )
				}
				// many files
				else if( val instanceof FileList ){
					for( let i = 0; i < val.length; i++ ){
						FData.append( `${name}[]`, val[i] )
					}
				}
				// multiple values
				else if( typeof val === 'object' ){
					for( let i in val ){
						FData.append( `${name}[]`, val[i] )
					}
				}
				// single val
				else
					FData.append( name, val )
			} );

			data = FData
		}
	}

	return [ data, objdata, filesSizeData ]
}

// Collect values of form elements
// $elem - jQuery of HTML element
function _ajaxsCollectData( $elem ){

	let data = {}

	if( ! ($elem instanceof jQuery) ){
		$elem = jQuery( $elem )
	}

	let getInputs__fn, used_attr, $inputs

	getInputs__fn = function( attr ){

		used_attr = attr // set var

		let selector = 'input[name], select[name], textarea[name], button[name]:focus'.replace( '[name]', '['+ attr +']' )

		return $elem
			.find( selector )
			.add( $elem.filter(selector) ) // add itself if match
	}

	// use another attributes to find elements
	$inputs = getInputs__fn( 'name' );
	if( ! $inputs.length ) $inputs = getInputs__fn( 'id' );
	if( ! $inputs.length ) $inputs = getInputs__fn( 'data-name' );

	$inputs.each( function( nn, el ){

		let $el   = jQuery( el )
		let name  = $el.attr( used_attr )
		let val   = $el.val()
		let multi = false

		let is_not_empty__fn = function( obj ){
			return ( typeof obj === 'object' ) && ( obj !== null )
		}

		if( name.substr(-2) === '[]' ){
			name = name.substr( 0, name.length - 2 )
			multi = true
		}

		// select
		if( $el.is('select') ){
			// for 'multiple' attr in 'select' we need to set name as an array - 'name[]'
			if( multi ){
				if( typeof val === 'object' ){
					if( val === null ){} // Pass it - "typeof null" = "object"
					else {
						if( typeof data[name] === 'undefined' ){
							data[name] = val;
						}
						else {
							if( is_not_empty__fn(data[name]) ){
								for( var i in val )
									data[name].push( val[i] ); // addcslashes
							}
							else
								data[name] = val;
						}
					}
				}
				// single value - string
				else {
					if( typeof data[name] !== 'object' ) data[name] = [];
					data[name].push( val );
				}
			}
			else {
				data[name] = val;
			}
		}
		// file
		else if( $el.is('[type="file"]') ){
			// val = el.files;
			if( el.files.length ){
				name = name +'__isFileType'; // mark. because we dont create FileList object for multiple files - we creaete simple array with File objects

				// for 'multiple' attr in 'input[type=file]' we need to set name as an array - 'name[]'
				if( multi ){
					// create array if not set
					if( typeof data[name] === 'undefined' )
						data[name] = [];

					for( var i = 0; i < el.files.length; i++ ){
						data[name].push( el.files[i] );
					}
				}
				else{
					data[name] = el.files[0];
				}
			}
		}
		// radio, checkbox, text, and other inputs
		else if(
			// type not `radio|checkbox|submit` AND tag not `button`
			! $el.is( '[type="radio"], [type="checkbox"], [type="submit"], button' )
			// checked radio|checkbox
			|| $el.is(':checked')
			// clicked submit|button
			|| $el.is(':focus')
		){

			if( multi ){
				if( is_not_empty__fn(data[name]) )
					data[name].push(val);
				else
					data[name] = [val];
			}
			else
				data[name] = val;
		}

	});

	return data;
}

