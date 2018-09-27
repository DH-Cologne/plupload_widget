<?php

namespace Drupal\plupload_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Bytes;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Xss;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget as CoreFileWidget;
use Drupal\plupload_widget\Plugin\Field\FieldWidget\PluploadWidgetTrait;

use Drupal\plupload_widget\UploadConfiguration;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @FieldWidget(
 *   id = "plupload_file_widget",
 *   label = @Translation("PLupload widget"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileWidget extends CoreFileWidget {

  use PluploadWidgetTrait;

  /**
   * Override to replace the upload/file HTML control
   * with the PLUPLOAD form element.
   *
   */
  public static function process($element, FormStateInterface $form_state, $form) {

    $element = parent::process($element, $form_state, $form);

    // If the form element does not have
    // an uplad control, skip this.
    if (!isset($element['upload'])) {
      return $element;
    }

    /** @var UploadConfiguration */
    $configuration = unserialize($form[$element['#parents'][0]]['#upload_configuration']);

    // Change the element description because
    // the PLUPLOAD widget MUST have the
    // extension filters as descripiton.
    // @see \Drupal\plupload\Element\PlUploadFile::preRenderPlUploadFile()
    // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::formElement()
    $file_upload_help = array(
       '#theme' => 'file_upload_help',
       '#description' => '',
       '#upload_validators' => '',
       '#cardinality' => $configuration->cardinality,
     );
    $element['#description'] = \Drupal::service('renderer')->renderPlain($file_upload_help);

    // Replace the upload HTML element with PLUPLOAD
    // for a single file.
    $element['upload'] = [
      '#type' => 'plupload',
      '#title' => t('Upload files'),
      //'#description' => t('This multi-upload widget uses Plupload library.'),
      '#autoupload' => TRUE,
      '#autosubmit' => TRUE,
      '#submit_element' => "[name={$element['upload_button']['#name']}]",
      '#upload_validators' => [
        'file_validate_extensions' => $configuration->validators['file_validate_extensions'],
      ],
      '#plupload_settings' => [
        'runtimes' => 'html5,flash,silverlight,html4',
        'chunk_size' => $configuration->chunk_size . 'b',
        'max_file_size' => $configuration->max_size . 'b',
        'max_file_count' => 1,
      ],
      '#event_callbacks' => [
        'FilesAdded' => 'Drupal.plupload_widget.filesAddedCallback',
        'UploadComplete' => 'Drupal.plupload_widget.uploadCompleteCallback',
      ],
      '#attached' => [
        // We need to specify the plupload attachment because it is a default
        // and will be overriden by our value.
        'library' => ['plupload_widget/plupload_widget', 'plupload/plupload'],
      ]
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {

    $element = parent::form($items, $form, $form_state, $get_delta);

    $field_definition = $this->fieldDefinition->getFieldStorageDefinition();

    // Store these seetings once for the whole widget.
    $config = new UploadConfiguration();
    $config->cardinality = $field_definition->getCardinality();
    $config->upload_location = $items[0]->getUploadLocation();
    $config->validators = $items[0]->getUploadValidators();
    $config->chunk_size = $this->getChunkSize();
    $config->max_size = $this->getMaxFileSize();

    $element['#upload_configuration'] = serialize($config);

    return $element;
  }

  /**
   * Important!! The core FILE API relies on the value callback to save the managed file,
   * not the submit handler. The submit handler is only used for file deletions.
   */
  public static function value($element, $input = FALSE, FormStateInterface $form_state) {

    // We need to fake the element ID for the PlUploadFile form element
    // to work as expected as it is being nested in a form sub-element calle
    // upload.
    $id = $element['#id'];
    $id_backup = $id;

    // If a unique identifier added with '--', we need to exclude it
    if (preg_match('/(.*)(--[0-9A-Za-z-]+)$/', $id, $reg)) {
      $id = $reg[1];
    }

    // The form element is going to tell us if one
    // or more files where uploaded.
    $element['#id'] = $id . '-upload';
    $files = \Drupal\plupload\Element\PlUploadFile::valueCallback($element, $input, $form_state);
    $element['#id'] = $id_backup;

    // Whether the files should be put in another subfolder
    $bUseSubfolder = true;

    // If using another subfolder, specify the path
    $sSubfolderPath = "models";
    
    // Will contain the relative paths as single string, if the cookie is availabe
    $jsonFiles = NULL;
    
    // Will contain the Token, if the cookie is available
    $sFilesToken = NULL;
    
    // Will contain the relative paths as array of strings
    $aRelativePaths = array();

    // Read cookie from JavaScript
    // The cookie contains the relative File Path
    if (isset($_COOKIE['UploadComplete'])) {
      $jsonFiles = json_decode($_COOKIE['UploadComplete']);
      setcookie('UploadComplete','', time()-3600);
    }
    if ($jsonFiles != NULL) {
      foreach ($jsonFiles as $file) {
        $_relPath = $file->relativePath;
        array_push($aRelativePaths, $_relPath);
      }
    }

    // Read UploadToken cookie
    if (isset($_COOKIE['UploadToken'])) {
      $sFilesToken = json_decode($_COOKIE['UploadToken']);
    }

    if (empty($files)) {
      return parent::value($element, $input, $form_state);;
    }    

    // During form rebuild after submit or ajax request this
    // method might be called twice, but we do not want to
    // generate the file entities twice....

    // This files are RAW files, they are not registered
    // anywhere, so won't get deleted on CRON runs :(
    foreach ($files as $file) {
      // Set the destination path by finding the original filename in the array of relative paths
      $relativeDestination = '';
      foreach ($aRelativePaths as $path) {
        if (strpos($path,$file['name']) !== false) {
          $relativeDestination = $path;
        }
      }

      // Remove '/' from these custom variables to prevent wrong folder movement
      $sSubfolderPath = str_replace('/', '', $sSubfolderPath);
      $sFilesToken = str_replace('/', '', $sFilesToken);

      // Handle the cases, being:
      // - Subfolder + Token + relative Paths
      // - Subfolder + relative Paths
      // - Token + relative Paths
      // - relative Paths
      $_DrupalDefaultScheme = \Drupal::config('system.file')->get('default_scheme') . '://';
      if ($bUseSubfolder && strlen($sSubfolderPath) > 0) {
        if ($sFilesToken != NULL && strlen($sFilesToken) > 0) {
          $destination = $_DrupalDefaultScheme . $sSubfolderPath . '/' . $sFilesToken . '/' . $relativeDestination;
        } else {
          $destination = $_DrupalDefaultScheme . $sSubfolderPath . '/' . $relativeDestination;
        }
      } elseif ($sFilesToken != NULL && strlen($sFilesToken) > 0) {
        $destination = $_DrupalDefaultScheme . $sFilesToken . '/' . $relativeDestination;
      } else {
        $destination = $_DrupalDefaultScheme . $relativeDestination;
      }

      $destination = file_stream_wrapper_uri_normalize($destination);

      // Create path at destination recursively
      if (!is_dir($destination)) {
        $b_mkdir = mkdir(dirname($destination), 0777, true);
      }

      /** @var \Drupal\file\Entity\File */
      $f = entity_create('file', array(
        'uri' => $file['tmppath'],
        'uid' => \Drupal::currentUser()->id(),
        'status' => 0,
        'filename' => drupal_basename($destination),
        'filemime' => \Drupal::service('file.mime_type.guesser')->guess($destination),
      ));

      $fc = file_copy($f, $destination);
      $return['fids'][] = $fc->id();
    }

    return $return;
  }
}
