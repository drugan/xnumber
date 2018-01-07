<?php

namespace Drupal\xnumber\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Defines the 'xinteger' field type.
 *
 * @FieldType(
 *   id = "xinteger",
 *   label = @Translation("Xnumber (integer)"),
 *   description = @Translation("This field stores a number in the database as an integer."),
 *   category = @Translation("Number"),
 *   default_widget = "xnumber",
 *   default_formatter = "number_integer"
 * )
 */
class XintegerItem extends XnumericItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'unsigned' => FALSE,
      'size' => 'normal',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'step' => '1',
      'min' => '',
      'max' => '',
      'prefix' => '',
      'suffix' => '',
      'placeholder' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();
    $sizes = Numeric::getStorageMaxMin($settings['size']);
    $step = Numeric::toString(static::defaultFieldSettings()['step']);

    $element['step']['#step'] = $step;
    $element['step']['#min'] = $step;
    $min_step = "min $step";
    $step = isset($settings['step']) ? Numeric::toString($settings['step']) : $step;
    $element['step']['#default_value'] = $step;
    $element['min']['#step'] = $step;
    $element['max']['#step'] = $step;

    if (!empty($settings['unsigned'])) {
      $floor = '0';
      $min = $settings['min'] < $floor ? $floor : Numeric::toString($settings['min']);
      $ceil = $sizes['unsigned'];
      $max = !is_numeric($settings['max']) || $settings['max'] > $ceil ? $ceil : Numeric::toString($settings['max']);
    }
    else {
      $floor = $sizes['signed']['min'];
      $min = $settings['min'] < $floor ? $floor : Numeric::toString($settings['min']);
      $ceil = $sizes['signed']['max'];
      $max = !is_numeric($settings['max']) || $settings['max'] > $ceil ? $ceil : Numeric::toString($settings['max']);
    }

    $element['min']['#min'] = $floor;
    $element['max']['#min'] = $min;
    $element['max']['#max'] = $ceil;
    $element['min']['#max'] = $element['step']['#max'] = $max;
    $element['min']['#field_suffix'] = "min $floor, max $max";
    $element['max']['#field_suffix'] = "min $min, max $ceil";
    $element['step']['#field_suffix'] = "$min_step, max $max";

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Integer value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $settings = $this->getSettings();
    $size = $settings['size'];
    $sizes = Numeric::getStorageMaxMin($size);
    $label = $this->getFieldDefinition()->getLabel();

    // If this is an unsigned integer, add a validation constraint for the
    // integer to be positive.
    if (!empty($settings['unsigned'])) {
      $sign = $this->t('unsigned', [], ['context' => 'numeric item']);
      $max = $sizes['unsigned'];

      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Range' => [
            'min' => '0',
            'minMessage' => t('%name: The integer must be larger or equal to %min.', [
              '%name' => $label,
              '%min' => '0',
            ]),
          ],
        ],
      ]);
    }
    else {
      $sign = $this->t('signed', [], ['context' => 'numeric item']);
      $max = $sizes['signed']['max'];
      $min = $sizes['signed']['min'];
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Range' => [
            'min' => $min,
            'minMessage' => $this->t('%name: the signed %size value may be no less than %min.', [
              '%name' => $label,
              '%size' => $size,
              '%min' => $min,
            ]),
          ],
        ],
      ]);
    }

    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Range' => [
          'max' => $max,
          'maxMessage' => $this->t('%name: the @sign %size value may be no greater than %max.', [
            '%name' => $label,
            '%size' => $size,
            '%max' => $max,
            '@sign' => $sign,
          ]),
        ],
      ],
    ]);

    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Regex' => [
          // The valid integer with a possible leading + or - sign:
          // 0, 1, 10, 101
          // No scientific notation numbers will match as they don't need to. By
          // design they are converted to regular numbers presented as strings.
          // @see Drupal\Core\Render\Element\Number::valueCallback()
          'pattern' => '/^[-+]?([1-9]\d*|0)$/i',
          'message' => $this->t('%name is not a valid number. <a href=":href" target="_blank">Read more</a>.', [
            ':href' => 'https://dev.mysql.com/doc/refman/5.7/en/precision-math-numbers.html',
            '%name' => $label,
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'unsigned' => $field_definition->getSetting('unsigned'),
          'size' => $field_definition->getSetting('size'),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];
    $settings = $this->getSettings();
    $sizes = array_keys(Numeric::getStorageMaxMin());
    $options = array_combine($sizes, $sizes);
    $driver = Database::getConnection()->driver();

    // The sqlite does not differentiate numeric types.
    if ($driver != 'sqlite') {
      // Only mysql and mariadb support sizes of unsigned numeric types.
      // Though there is no specific mariadb driver on a default install the
      // condition is for the case when a custom driver with the name is used.
      if ($driver == 'mysql' || $driver == 'mariadb') {
        $element['unsigned'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unsigned', [], ['context' => 'numeric item']),
          '#default_value' => !empty($settings['unsigned']) ? TRUE : FALSE,
          '#return_value' => TRUE,
          '#description' => $this->t('Whether the number should be restricted to positive values (without leading minus "-" sign).'),
          '#disabled' => $has_data,
        ];
      }

      if ($driver == 'pgsql') {
        unset($options['tiny'], $options['medium']);
      }

      $element['size'] = [
        '#type' => 'select',
        '#title' => $this->t('Size', [], ['context' => 'integer item']),
        '#options' => $options,
        '#default_value' => $settings['size'],
        '#description' => $this->t('The size of the field. <a href=":href" target="_blank">Read more</a>.', [
          ':href' => 'https://dev.mysql.com/doc/refman/5.7/en/integer-types.html',
        ]),
        '#disabled' => $has_data,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $min = $field_definition->getSetting('min') ?: 0;
    $max = $field_definition->getSetting('max') ?: 999;
    $values['value'] = mt_rand($min, $max);
    return $values;
  }

}
