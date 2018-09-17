(function ($) {

  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function (up, files) {
    setTimeout(function () { up.start(); }, 100);
  };

  Drupal.plupload_widget.uploadCompleteCallback = function (up, files) {
    // Create a cookie once the files are uploaded
    // This cookie will be destroyed once read by FileWidget.php
    document.cookie = `UploadComplete=${JSON.stringify(up.files)}`;
  };

})(jQuery);

