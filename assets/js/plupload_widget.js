// JavaScript equivalent of PHP dirname()
function dirname(path) {
    return path.replace(/\\/g,'/').replace(/\/[^\/]*$/, '');;
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
      file.relativePath = file.relativePath.replace(_basedir,'')
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
    if (i == files.length - 1) _basedir = dirname(file.relativePath).substring(0,dirname(file.relativePath).indexOf('/',1))
  }
  return _basedir
}

(function ($) {

  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function (up, files) {
    setTimeout(function () { up.start(); }, 100);
  };

  Drupal.plupload_widget.uploadCompleteCallback = function (up, files) {

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



})(jQuery);

