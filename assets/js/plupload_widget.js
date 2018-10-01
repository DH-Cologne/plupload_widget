// Globals
let isPluploadLoaded = false

// JavaScript equivalent of PHP dirname()
function dirname(path) {
  return path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '');;
}

/**
 * Wrapper for the checkIfTopLevel() function
 * Runs until there are files toplevel
 * This is to adjust cases where the first file is
 * nested within 1 or more subfolders 
 * @param  {Object} Plupload File Object 
 * @return {Object} Plupload File Object
 */
function removeFoldersUntilFilesAreTopLevel(files) {
  _basedir = checkIfTopLevel(files)
  while (_basedir != '') {
    files.forEach(file => {
      file.relativePath = file.relativePath.replace(_basedir, '')
    })
    _basedir = checkIfTopLevel(files)
  }
  return files
}

/**
 * Loops through the relative Paths of a Plupload File Object
 * If the loop reaches the end of the Object a redundant topfolder name will be returned
 * @param  {Object} Plupload File Object
 * @return {string} Topmost path that can be removed
 */
function checkIfTopLevel(files) {
  let _basedir = ''
  for (let i = 0; i < files.length; i++) {
    let file = files[i]
      // If there is a file in top level directory, stop the loop
    if (dirname(file.relativePath) == "") break;

    // If the loop reaches the last item
    // no file was found in top level
    // so set _basedir to the topmost directory that can be removed
    if (i == files.length - 1) _basedir = dirname(file.relativePath).substring(0, dirname(file.relativePath).indexOf('/', 1))
  }
  return _basedir
}

(function($) {
  // Defining the widget
  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function(up, files) {
    setTimeout(function() {
      up.start();
    }, 100);
  };

  Drupal.plupload_widget.uploadCompleteCallback = function(up, files) {
    // Save the reduced Plupload Files Object in a new Object to be passed as cookie
    let _newFiles = removeFoldersUntilFilesAreTopLevel(up.files)

    // Create a cookie once the files are uploaded
    // This cookie will be destroyed once read by FileWidget.php
    document.cookie = `UploadComplete=${JSON.stringify(_newFiles)}`;

    // Generate a Token. For this Token to be duplicate, 
    // 2 users would have to have their requests processed in the same millisecond
    // while also generating the same prefix and suffix random string
    let token = `${Math.random().toString(36).substr(2,5)}-${Date.now()}-${Math.random().toString(36).substr(2,5)}`
    document.cookie = `UploadToken=${JSON.stringify(token)}`;
  };

  // Load configuration file and configure the cookie checking
  $.getJSON("../../modules/plupload_widget/config/configuration.json", (configuration) => {
    /**
     * Checks whether the agreement cookie has the agreed value
     * If not, force the wrong cookie to expire so the Cookie-Accept-Banner reappears
     * @return {bool} Cookie Agreed
     */
    function checkCookieAgreement() {
      // Returns all cookies the document has
      let _cookies = document.cookie.split('; ')
        // Default value to return
      let _agreed = false
        // Find the right cookie and check if the agreement value matches
      _cookies.forEach(cookie => {
        if (cookie.indexOf(configuration.cookie.Name) >= 0) {
          if (cookie.split('=')[1] == configuration.cookie.AgreeVal) {
            _agreed = true
          } else {
            // Force expire the cookie and reload the page, so the user can be prompted again
            document.cookie = `${configuration.cookie.Name}=undefined;expires=Thu, 01 Jan 1970 00:00:01 GMT;path=${configuration.cookie.Path}`
            location.reload()
          }
        }
      })
      return _agreed
    }

    /**
     * Load the full plupload widget
     * @return {[type]} [description]
     */
    function initPluploadWidget() {
      isPluploadLoaded = true

      // Remove cookie reminder message if it exists
      try {
        document.getElementById(configuration.cookie.reminderID).outerHTML = ''
      } catch (e) {
        console.log('No cookie reminder to be hidden. Cookies were probably already enabled');
      }

      document.querySelectorAll('.plupload-element').forEach(widget => {
        widget.style.display = 'block';
      })
    }

    // Check if cookie agreement checking is enabled in configuration
    // If it's enabled, check if the cookie has the agreed value
    function tryInitPluploadWidget() {
      if (!configuration.useCookie || (configuration.useCookie && checkCookieAgreement())) {
        initPluploadWidget()
      } else {
        // Configure what happens when cookies are not enabled
        // alert('Cookies need to be enabled')
        // In this case all instances of the plupload widget on-site will be replaced with a notification
        document.querySelectorAll('.plupload-element').forEach(widget => {
          if (document.getElementById(configuration.cookie.reminderID) == null) {
            let errorNode = document.createElement('h1')
            errorNode.innerText = "Cookies need to be enabled"
            errorNode.id = configuration.cookie.reminderID
            widget.parentElement.appendChild(errorNode)
            widget.style.display = 'none';
          }
        })
      }
    }

    // Initial try
    tryInitPluploadWidget()
      // Subsequent tries until the widget can be loaded
    let checkingInterval = setInterval(() => {
      if (!isPluploadLoaded) {
        tryInitPluploadWidget()
      } else {
        clearInterval(checkingInterval)
      }
    }, configuration.Interval)
  });
})(jQuery);