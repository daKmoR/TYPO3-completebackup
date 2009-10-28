$require('Core/Utilities/DomReady.js');
$require('Core/Request/Request.js');
$require('Core/Utilities/Json.js');

window.addEvent('domready', function() {

	var myRequest = new Request({
		url: 'mod.php?M=tools_txcompletebackupM1',
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
					
					$('FileSystemBackup').set('html', '[<img src="gfx/spinner.gif" alt="spinner" />' + $H(msg['completebackup[list]']).getLength() + ' files to write]' );
					this.send( newParams );
				}
				
			} else {
				$('FileSystemBackup').set('html', '[All files written]' );
				var notifyRequest = new Request({
					url: 'mod.php?M=tools_txcompletebackupM1',
					data: {
						'completebackup[mode]': 'notify',
						'completebackup[file]': $('notifyServerFile').get('text'),
						'completebackup[sql]': $('notifyServerSql').get('text')
					},
					onSuccess: function(msg) {
						$('notifyServerResponse').set('html', 'finished');
						$('notifyServerReady').set('html', ' has been notified');
					}
				});
				notifyRequest.send();
			}
		}
	});

	if( $chk($('FileSystemBackup')) ) {
		myRequest.setOptions({
			data: {
				'completebackup[mode]': 'ajax',
				'completebackup[files]': JSON.decode($('FileSystemFiles').get('text')),
				'completebackup[name]': $('FileSystemName').get('text')
			}
		});
		myRequest.send();
		
	}

});

			
