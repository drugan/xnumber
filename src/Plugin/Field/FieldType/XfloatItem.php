<?php

namespace Drupal\xnumber\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Defines the 'xfloat' field type.
 *
 * @FieldType(
 *   id = "xfloat",
 *   label = @Translation("Xnumber (float)"),
 *   description = @Translation("This field stores a number in the database in a floating point format."),
 *   category = @Translation("Number"),
 *   default_widget = "xnumber",
 *   default_formatter = "number_decimal"
 * )
 */
class XfloatItem extends XnumericItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'unsigned' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'step' => 'any',
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

    // The size of a float is platform-dependent, although a maximum of ~1.8e308
    // with a precision of roughly 14 decimal digits is a common value
    // (the 64 bit IEEE format).
    // @see http://php.net/manual/en/language.types.float.php#language.types.float.casting
    $min = Numeric::toString($settings['min']);
    $max = Numeric::toString($settings['max']);

    $element['step']['#step'] = 'any';
    $element['min']['#step'] = 'any';
    $element['max']['#step'] = 'any';
    $element['default_value']['#step'] = 'any';
    $element['step']['#min'] = '0';
    $step_min = 'min > 0';
    $min_min = $max_min = '';
    $step = is_numeric($settings['step']) && $settings['step'] > 0 ? Numeric::toString($settings['step']) : '';
    $element['step']['#default_value'] = $step;

    if (!empty($step)) {
      $element['min']['#step'] = $step;
      $element['max']['#step'] = $step;
      $element['default_value']['#step'] = $step;
    }

    if (!empty($settings['unsigned'])) {
      $floor = '0';
      $min = $min < $floor ? $floor : $min;
      $element['min']['#min'] = $floor;
      $min_min = "min $floor";
    }

    if (is_numeric($min)) {
      $element['max']['#min'] = $min;
      $max_min = "min $min";
    }

    if (is_numeric($max)) {
      $element['min']['#max'] = $element['step']['#max'] = $element['default_value']['#max'] = $max;
      $min_min = $settings['unsigned'] ? "$min_min, max $max" : "max $max";
      $step_min .= ", max $max";
    }
    $element['min']['#field_suffix'] = $min_min;
    $element['max']['#field_suffix'] = $max_min;
    $element['step']['#field_suffix'] = $step_min;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();
    $label = $this->getFieldDefinition()->getLabel();
    $settings = $this->getSettings();

    // If this is an unsigned float, add a validation constraint for the
    // float to be positive.
    if (!empty($settings['unsigned'])) {
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Range' => [
            'min' => '0',
            'minMessage' => t('%name: The float must be larger or equal to %min.', [
              '%name' => $label,
              '%min' => '0',
            ]),
          ],
        ],
      ]);
    }

    $constraints[] = $constraint_manager->create('ComplexData', [
      'value' => [
        'Regex' => [
          // The float number type may also take a form of an integer without a
          // fractional part, so we accept the following formats with a possible
          // leading + or - sign:
          // integer - 0, 1, 10, 101
          // float - .1, .01, 0.1, 0.01, 1.1, 1.01, 10.1, 10.01
          // No scientific notation numbers will match as they don't need to. By
          // design they are converted to regular numbers presented as strings.
          // @see Drupal\Core\Render\Element\Number::valueCallback()
          'pattern' => '/^[-+]?(((([(0)]?\.)|([1-9]\d*\.))\d*[1-9])|([1-9]\d*|0))$/i',
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
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('float')
      ->setLabel(t('Float'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'float',
          'unsigned' => $field_definition->getSetting('unsigned'),
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

    $element['unsigned'] = [
      '#type' => 'checkbox',
      '#title' => t('Unsigned', [], ['context' => 'numeric item']),
      '#default_value' => $settings['unsigned'],
      '#return_value' => TRUE,
      '#description' => t('Whether the number should be restricted to positive values (without leading minus "-" sign).'),
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    // The size of a float is platform-dependent, although a maximum of ~1.8e308
    // with a precision of roughly 14 decimal digits is a common value.
    // @see http://php.net/manual/en/language.types.float.php
    $int = substr(rand(0, 9) . rand(1, 9999999999998), 0, rand(1, 14));
    $fract = rtrim(substr(rand(0, 99999999999999), 0, (14 - strlen($int))), '0');
    $float = empty($fract) ? $int : "$int.$fract";
    $max = is_numeric($settings['max']) ?: Numeric::toString($float + 1);
    $min = is_numeric($settings['min']) ?: Numeric::toString(("-{$float}") - 1);
    // @see "Example #1 Calculate a random floating-point number" in
    // http://php.net/manual/function.mt-getrandmax.php
    $random_float = $min + mt_rand() / mt_getrandmax() * ($max - $min);
    $values['value'] = Numeric::toString($random_float);

    return $values;
  }

}
