<?php

namespace Drupal\views_photo_grid\Plugin\views\style;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin to render the photo grid.
 *
 * @ViewsStyle(
 *   id = "views_photo_grid",
 *   title = @Translation("Photo Grid"),
 *   help = @Translation("Displays photos in a grid."),
 *   theme = "views_photo_grid_style",
 *   display_types = {"normal"}
 * )
 */
class PhotoGrid extends StylePluginBase {

  /**
   * Specifies if the plugin uses fields.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * Specifies if the plugin uses row plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManager $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_field.manager'));
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['grid_padding'] = ['default' => 1];
    $options['max_height'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['grid_padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Padding'),
      '#size' => 2,
      '#description' => $this->t('The amount of padding in pixels in between grid items.'),
      '#default_value' => $this->options['grid_padding'],
      '#maxlength' => 2,
    ];

    $form['max_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum image height'),
      '#size' => 4,
      '#description' => $this->t('The maximum image height in pixels.'),
      '#default_value' => $this->options['max_height'],
      '#maxlength' => 4,
    ];
  }

  /**
   * Returns the name of the image field used in the view.
   */
  public function getImageFieldName() {
    $fields = $this->displayHandler->handlers['field'];

    // Find the first non-excluded image field.
    foreach ($fields as $key => $field) {
      /* @var \Drupal\views\Plugin\views\field\EntityField $field */

      // Get the storage definition in order to determine the field type.
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($field->definition['entity_type']);

      /* @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
      $field_storage = $field_storage_definitions[$field->field];
      $field_type = $field_storage->getType();

      if (empty($field->options['exclude']) && $field_type == 'image') {
        return $field->field;
      }
    }

    return FALSE;
  }

  /**
   * Validates the view configuration.
   * Fails if there is a non-image field, or there are more
   * than one image fields that are not excluded from display.
   */
  function validate() {
    $errors = parent::validate();

    if ($this->view->storage->isNew()) {
      // Skip validation when the view is being created.
      // (the default field is a title field, which would fail.)
      return $errors;
    }

    // Get a list of fields that have been added to the display.
    $fields = $this->displayHandler->handlers['field'];

    // Check if there is exactly one image field to display.
    $fields_valid = TRUE;
    $field_count = 0;

    foreach ($fields as $key => $field) {
      // Ignore fields excluded from display.
      if (!empty($field->options['exclude'])) {
        continue;
      }

      // Determine the field's type.
      $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($field->definition['entity_type']);
      $field_type = $field_storage_definitions[$field->field]->getType();

      if ($field_type != 'image') {
        // Cannot display non-image fields. That would break the image grid.
        $fields_valid = FALSE;
        break;
      }

      $field_count++;
    }

    if (!$fields_valid || $field_count > 1) {
      $errors[] = $this->t('This format can display only one image field and no other fields.');
    }

    return $errors;
  }

}
