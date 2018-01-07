<?php

namespace Drupal\xnumber\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Defines the 'decimal' field type.
 *
 * @FieldType(
 *   id = "xdecimal",
 *   label = @Translation("Xnumber (decimal)"),
 *   description = @Translation("This field stores a number in the database in a fixed decimal format."),
 *   category = @Translation("Number"),
 *   default_widget = "xnumber",
 *   default_formatter = "number_decimal"
 * )
 */
class XdecimalItem extends XnumericItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'unsigned' => FALSE,
      'precision' => 10,
      'scale' => 2,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'step' => Numeric::toString(pow(0.1, static::defaultStorageSettings()['scale'])),
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

    $scale = is_numeric($settings['scale']) ? $settings['scale'] : static::defaultStorageSettings()['scale'];
    $precision = is_numeric($settings['precision']) ? $settings['precision'] : static::defaultStorageSettings()['precision'];
    $sizes = Numeric::getStorageMaxMin(['precision' => $precision, 'scale' => $scale]);
    $step = Numeric::toString(pow(0.1, $scale));

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
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Decimal value'))
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
          'type' => 'numeric',
          'unsigned' => $field_definition->getSetting('unsigned'),
          'precision' => $field_definition->getSetting('precision'),
          'scale' => $field_definition->getSetting('scale'),
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
      '#title' => $this->t('Unsigned', [], ['context' => 'numeric item']),
      '#default_value' => !empty($settings['unsigned']) ? TRUE : FALSE,
      '#return_value' => TRUE,
      '#description' => $this->t('Whether the number should be restricted to positive values (without leading minus "-" sign).'),
      '#disabled' => $has_data,
    ];

    $element['precision'] = [
      '#type' => 'number',
      '#title' => $this->t('Precision', [], ['context' => 'numeric item']),
      '#min' => 10,
      '#max' => 32,
      '#default_value' => $settings['precision'],
      '#description' => $this->t('The total number of digits to store in the database, including those to the right of the decimal. <a href=":href" target="_blank">Read more</a>.', [
        ':href' => 'https://dev.mysql.com/doc/refman/5.7/en/fixed-point-types.html',
      ]),
      '#disabled' => $has_data,
    ];

    $element['scale'] = [
      '#type' => 'number',
      '#title' => $this->t('Scale', [], ['context' => 'decimal places']),
      '#min' => 0,
      '#max' => 10,
      '#default_value' => $settings['scale'],
      '#description' => $this->t('The number of digits to the right of the decimal.'),
      '#disabled' => $has_data,
    ];

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

    // If this is an unsigned decimal, add a validation constraint for the
    // decimal to be positive.
    if (!empty($settings['unsigned'])) {
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Range' => [
            'min' => '0',
            'minMessage' => t('%name: The decimal must be larger or equal to %min.', [
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
          // The decimal number may also take a form of an integer without a
          // fractional part, so we accept the following formats with a possible
          // leading + or - sign:
          // integer - 0, 1, 10, 101
          // decimal - .1, .01, 0.1, 0.01, 1.1, 1.01, 10.1, 10.01
          // No scientific notation numbers will match as they don't need to. By
          // design they are converted to regular numbers presented as strings.
          // @see Drupal\Core\Render\Element\Number::valueCallback()
          'pattern' => '/^[-+]?(((([(0)]?\.)|([1-9]\d*\.))\d*[1-9])|([1-9]\d*|0))$/i',
          'message' => $this->t('%name is not a valid number.AAAAAA <a href=":href" target="_blank">Read more</a>.', [
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
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $precision = $settings['precision'] ?: 10;
    $scale = $settings['scale'] ?: 2;
    // $precision - $scale is the number of digits on the left of the decimal
    // point.
    // The maximum number you can get with 3 digits is 10^3 - 1 --> 999.
    // The minimum number you can get with 3 digits is -1 * (10^3 - 1).
    $max = is_numeric($settings['max']) ?: pow(10, ($precision - $scale)) - 1;
    $min = is_numeric($settings['min']) ?: -pow(10, ($precision - $scale)) + 1;

    // Get the number of decimal digits for the $max.
    $decimal_digits = Numeric::getDecimalDigits($max);
    // Do the same for the min and keep the higher number of decimal digits.
    $decimal_digits = max(Numeric::getDecimalDigits($min), $decimal_digits);
    // If $min = 1.234 and $max = 1.33 then $decimal_digits = 3.
    $scale = rand($decimal_digits, $scale);

    // @see "Example #1 Calculate a random floating-point number" in
    // http://php.net/manual/function.mt-getrandmax.php
    $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);
    $values['value'] = Numeric::truncateDecimal($random_decimal, $scale);
    return $values;
  }

}
