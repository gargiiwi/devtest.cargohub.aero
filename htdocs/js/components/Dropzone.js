/**
 * Dropzone uploader component (singleton) - Supports multiple Dropzone instances within one page
 * - Allows sending files to the server asynchronously
 * - The 3rd party library (Dropzone.js) is initialized at first use
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI Components
 * @link http://www.dropzonejs.com/#configuration
 * @link https://github.com/enyo/dropzone/wiki/FAQ
 * @version 2019.01.04
 */
cargohub.components.Dropzone = donrey.BASE._extend(function() {
	// private
	var self = this,
	master = null,			// Master object that initialized this component
	// General Configuration, and individual editor configuarion
	DropzoneJS_loaded = false,
	defaults = {
		maxFilesize: 250,	
		chunking:1,
		forceChunking:1,
		parallelChunkUploads:1,
		retryChunks:3,
						// MB; Default instance max upload size (must match with filesize limit on the server)
		uploadURL: "/files?action=upload",	// Default URL for uploading files. Change if Warehouse is not handling these uploads
		removeURL: "/files?action=remove" 
		 	// Default URL for removing files. Change if Warehouse is not handling these uploads
	},
	instances = {}			// Object containing instance configuration - Key is instance name, used in all other functions to access the editor
	;

	this._public({
		name: "Dropzone",

		// Component events:
		// - on_ready			DropzoneJS instance is ready to be used
		// - on_content			At least one file has been dropped, or a mock-file is prepared
		// - on_empty			The dropzone is empty of files or mock-files
		// - on_filesending		A file has begun uploading
		// - on_filesuccess		A file has completed uploading
		// - on_fileremove		A file or mock file has been removed via a remove link
		// - on_uploadcomplete	All queued files have finished uploading
		events: {},

		/**
		 * Loads DropZone before first instance
		 * @param object _master Module which uses this component
		 */
		setup: function(_master)
		{
			if(DropzoneJS_loaded)
				return; // Already initialized in this page, nothing to do.
			if(_master)
				master = _master;

			// Load DropZone
			donrey.load_CSS(donrey.CFG.paths.lib + "dropzone/dropzone.css?v=5.5.0b");
			donrey.LAB.queueScript(donrey.CFG.paths.lib + "dropzone/dropzone.min.js?v=5.5.0b");
			donrey.LAB.queueWait(function() {
				DropzoneJS_loaded = true;
				Dropzone.autoDiscover = false;
				// Done loading ? Init already defined instances (pending)
				if(instances)
					for(var ikey in instances)
						if(instances[ikey].pending) {
							// Init Dropzone (dzo = Dropzone Object, internal to this component); instance.Dropzone is actually the direct configuration options
							instances[ikey].dzo = new Dropzone(instances[ikey].$c[0], instances[ikey].Dropzone);
							instances[ikey].active = true;
							delete instances[ikey].pending;

							if(instances[ikey].events.on_ready)
								instances[ikey].events.on_ready(instances[ikey].dzo);
						}
			});
			donrey.LAB.runQueue();
		},

		/**
		 * Sets up DropZone on the specified container element
		 * @param string ikey Instance key, used to find this dropzone object later
		 * @param object config Configuration options for the instance
		 * @param object events Event handlers (on_*)
		 */
		create_Instance: function(ikey, config, events)
		{
			var instance = {}; // TinyMCE instance parameters

			if(instances[ikey])
				return cargohub.alert("Error: Instance key "+ ikey+ " was already used");

			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error: Cannot instance DropzoneJS without a container element");

			if(config.container.length > 1)
				return cargohub.alert("Error: Container must be unique (Preferably by having a DOM ID)");

			instance = {
				key: ikey,
				$c: config.container
			};
			// Set height via a parameter
			if(config.height)
				instance.$c.css('height', config.height);

			if(events)
				instance.events = events;

			instance.uploads = 0; // if >0 - Dropzone has uploads in progress or finished (the user has interacted with it); If all uploads are removed, this becomes 0
			instance.upload_in_progress = false;

			// Dropzone specific configuration
			instance.Dropzone = config.params||{};
			
			 
			instance.Dropzone.chunking=1;
			instance.Dropzone.forceChunking=1;
			instance.Dropzone.parallelChunkUploads=!1;
			instance.Dropzone.retryChunks=1;
			instance.Dropzone.retryChunksLimit=3;


			if(instance.Dropzone.url === undefined)
				instance.Dropzone.url = defaults.uploadURL;
			if(instance.Dropzone.maxFilesize === undefined)
				instance.Dropzone.maxFilesize = defaults.maxFilesize;
			if(instance.Dropzone.addRemoveLinks === undefined)
				instance.Dropzone.addRemoveLinks = true;
				
				 
				
				
				 
    

			/**
			 * Initialization function for DropzoneJS
			 * "this" is the Dropzone object ( https://www.dropzonejs.com/#dropzone-methods )
			 */
			instance.Dropzone.init = function()
			{
				this.on("addedfile", function(file) {
					file.uid = donrey.generate_UniqId();
					if (file.is_mock || !file.type.match(/image.*/)) // This is not an image, so Dropzone doesn't create a thumbnail.
						this.emit("thumbnail", file, "/img/mime128/"+ file.type+ ".png");

					if(instance.events.on_content)
						instance.events.on_content();
				});

				this.on("sending", function(file, xhr, formData) {
				
				 xhr.ontimeout = (() => {
          /*Execute on case of timeout only*/
            console.log('Server Timeout')
        });
        
					instance.upload_in_progress++;

					if(instance.events.on_filesending)
						instance.events.on_filesending(file);
				});

				this.on("canceled", function(file) {
					instance.upload_in_progress--;
				});

				// TODO: Make this one support alternative upload points which do not return database id, chash, mtime, etc.; Also error should be handled via response codes
                                 this.on("success", function(file, responseText) {
            console.log(responseText);
        });
                                
				this.on("success", function(file, x) {
					// Appends the database ID which is found in the response, as well as some information about the file (coming from PHP)
		  		  xx = JSON.parse(file.xhr.responseText); 
                     if(file.status) {
 						file.id = xx.id;
 						file.details = xx.details;
						instance.uploads++;

						if(instance.events.on_filesuccess)
							instance.events.on_filesuccess(file); 
					} else {
						// Sometimes there might be a different kind of error, even if file is uploaded successfuly (like session timeout)
						file.status = 'error';
						cargohub.alert(x.reason, "Message from server");
					} 
 					 
				}); 

 
				this.on("error", function(file, x) {
					if(file.status == 'error' && x.error)
						cargohub.alert(x.error, "Message from server");
					else
						cargohub.alert(x);
				});

				this.on('removedfile', function(file) {
					instance.uploads--;
					if(instance.remove_event_disabled)
						return; // Do nothing if this event is disabled

					if(file.id) {
						// Tell PHP to remove the file (if an ID exists)
						$.post(instance.removeURL||defaults.removeURL, {
							id: file.id
						}, function(x) {
							// File was removed, update the total bytes counter, etc...
							if(instance.events.on_fileremove)
								instance.events.on_fileremove(file);
						})
						.fail(cargohub.its.http_fail);
					} else {
						// When there is no ID for the file it means we're removing an errored file (something that didn't complete upload)
						if(instance.events.on_fileremove)
							instance.events.on_fileremove(file);
					}
				});

				// Show a progressbar for ALL uploads in the queue (todo later)
				this.on('totaluploadprogress', function(progress, totalBytes, totalBytesSent) {
//					dd(progress);
//					dd(totalBytesSent);
				});

				// Notify the user that all files have finished uploading (This is the moment when a form can be submitted or an uploads window can be closed)
				this.on('queuecomplete', function() {
					instance.upload_in_progress = false;

					if(instance.events.on_uploadcomplete)
						instance.events.on_uploadcomplete(self.get_Files(ikey));
				});

				// Reset event (Clearing the content of Dropzone object); Same as on_empty?
				this.on('reset', function() {
					if(instance.events.on_empty)
						instance.events.on_empty();
				});
			};

			// Two modes of operation - If TinyMCE is already loaded, initialize on a new element. If not yet, add them to instances to be prepared (after it's loaded)
			if(DropzoneJS_loaded) {
				instance.dzo = new Dropzone(instance.$c[0], instance.Dropzone);
				instance.active = true;

				if(instance.events.on_ready)
					instance.events.on_ready(instance.dzo);
			} else
				instance.pending = true;

			instances[ikey] = instance;
			return instance;
		},

		/**
		 * Places files which already exist in the DropZone (for display/add/remove)
		 */
		prefill: function(ikey, files)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].dzo)
				return false; // Dropzone object not initialized yet

			// Mockup files shown in the dropzone, for the purpose of forwarding previously uploaded files between form reloads (when fields have errors)
			var mockFile = null, c;
			for(c=0; c< files.length; c++) {
				mockFile = clone(files[c]);
				mockFile.size = parseInt(mockFile.size);
				mockFile.status = "success";
				mockFile.is_mock = true;
				instances[ikey].dzo.files.push(mockFile);
				instances[ikey].dzo.emit("addedfile", mockFile);
			}
			return true;
		},

		/**
		 * Returns an array with the database ID's of the "pre" files that have been uploaded
		 * If uploads are still in progress or there is an error, it returns instead an object in the format: {error: true, reason: "what is going on?"}
		 * @param string ikey Instance key
		 */
		get_Files: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].dzo)
				return false; // Dropzone object not initialized yet

			if(instances[ikey].upload_in_progress)
				return {error: 2, reason: "Uploading still in progress, please wait until complete."};

				// Statistics
			var	file_ids = [],
				upload_size = 0,
				error_count = 0; // number of files which could not be uploaded

			// Assamble the list of uploaded file ids
			for(var c=0; c< instances[ikey].dzo.files.length; c++) {
				if(instances[ikey].dzo.files[c].status == "success") {
					file_ids.push(instances[ikey].dzo.files[c].id);
					upload_size+= parseInt(instances[ikey].dzo.files[c].size);
				} else
					error_count++;
			}

			if(error_count > 0)
				return {error: 3, reason: "Some files failed upload or were cancelled. Please remove them before continuing !"};
			else if(instances[ikey].dzo.files.length == 0)
				return {error: 1}; // This just means that it's empty, not an actual error
			else
				return {
					list: instances[ikey].dzo.files,
					ids: file_ids,
					size: upload_size
				};
		},

		/**
		 * Empties the dropzone of all uploaded files (aka, fresh start); Also cancels any uploads in progress
		 * @param string ikey Instance key
		 * @param bool local_only true = Only empty Dropzone local, without deleting the files from the server (useful when Dropzone is reused, after a successful upload)
		 */
		empty: function(ikey, local_only)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].dzo)
				return false; // Dropzone object not initialized yet

			if(local_only)
				instances[ikey].remove_event_disabled = true;
			if(instances[ikey].dzo)
				instances[ikey].dzo.removeAllFiles(true);
			if(local_only)
				instances[ikey].remove_event_disabled = false;

			instances[ikey].uploads = 0;

			if(instances[ikey].events.on_empty)
				instances[ikey].events.on_empty();
			return true;
		},

		/**
		 * Disables a Dropzone instance (when it is not needed anymore)
		 * @param string ikey Instance key
		 */
		disable: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].dzo)
				return false; // Dropzone object not initialized yet

			instances[ikey].dzo.disable();
			return true;
		},

		/**
		 * Enables an existing Dropzone instance (usually when a window is reopened)
		 * @param string ikey Instance key
		 */
		enable: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].dzo)
				return false; // Dropzone object not initialized yet

			instances[ikey].dzo.enable();
			return true;
		},

		/**
		 * Destroys the dropzone instance, allowing the ikey to be reinstanced with different settings
		 */
		destroy: function(ikey)
		{
			if(!self.disable(ikey))
				return false; // Instance not found

			delete instances[ikey];
		}
	});
	// Note: There is no specific CSS to load for the component
});