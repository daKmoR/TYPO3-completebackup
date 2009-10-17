$require('Core/Utilities/DomReady.js');
$require('Core/Request/Request.js');
$require('Core/Utilities/Json.js');

window.addEvent('domready', function() {

	var myRequest = new Request({
		url: 'mod.php?M=tools_txcompletebackupM1&completebackup[mode]=ajax',
		onSuccess: function(msg) {
			if( msg != 'done' ) {
				//console.log( msg );
				msg = JSON.decode(msg);
				if( $chk(msg['completebackup[list]']) ) {
					newParams = {};
					newParams.data = { 
						'completebackup[mode]': 'ajax', 
						'completebackup[offset]': msg['completebackup[offset]'], 
						'completebackup[list]': msg['completebackup[list]'], 
						'completebackup[name]': msg['completebackup[name]']
					};
					
					$('FileSystemBackup').set('html', '[' + $H(msg['completebackup[list]']).getLength() + ' files to write]' );
					this.send( newParams );
				}
				
			} else {
				$('FileSystemBackup').set('html', '[All files written]' );
			}
		}
	});

	if( $chk($('FileSystemBackup')) ) {
		myRequest.setOptions({
			data: {
				'completebackup[files]': JSON.decode($('FileSystemFiles').get('text')),
				'completebackup[name]': $('FileSystemName').get('text')
			}
		});
		myRequest.send();
		
	}

});

			
