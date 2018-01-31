<?php

namespace Drupal\xnumber\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\xnumber\Utility\Xnumber as Numeric;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;

/**
 * Plugin implementation of the 'xnumber' widget.
 *
 * @FieldWidget(
 *   id = "xnumber",
 *   label = @Translation("Xnumber field"),
 *   field_types = {
 *     "integer",
 *     "decimal",
 *     "float",
 *     "xinteger",
 *     "xdecimal",
 *     "xfloat"
 *   }
 * )
 */
class XnumberWidget extends NumberWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'default_value' => '',
      'step' => '',
      'min' => '',
      'max' => '',
      'prefix' => '',
      'suffix' => '',
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $mode_settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $settings = $this->getFormDisplayModeSettings();
    $floor = is_numeric($settings['floor']) ? $settings['floor'] : NULL;
    $ceil = is_numeric($settings['ceil']) ? $settings['ceil'] : NULL;
    $base_step = $settings['base_step'];
    $min = is_numeric($settings['min']) ? $settings['min'] : $floor;
    $max = is_numeric($settings['max']) ? $settings['max'] : $ceil;
    $step_min = $base_step == 'any' ? '> 0' : $base_step;

    $element['default_value'] = [
      '#type' => 'number',
      '#title' => $this->t('Default value', [], ['context' => 'numeric item']),
      '#step' => $settings['step'],
      '#min' => $min,
      '#max' => $max,
      '#default_value' => $mode_settings['default_value'],
      '#field_suffix' => "min {$settings['min']}, max {$settings['max']}",
      '#description' => t('The default value for this form display mode. Leave blank for default = <em>@default</em>.', [
        '@default' => $settings['base_default_value'],
      ]),
    ];
    $element['step'] = [
      '#type' => 'number',
      '#title' => $this->t('Step', [], ['context' => 'numeric item']),
      '#step' => $base_step,
      '#min' => $base_step == 'any' ? '0' : $base_step,
      '#max' => $max,
      '#default_value' => $mode_settings['step'],
      '#field_suffix' => "min {$step_min}, max {$max}",
      '#description' => $this->t('The minimum allowed amount to increment or decrement the field value. Note that setting an integer for this value on a decimal or float field restricts the input on the field to integer values only. While updating this field it is recommended to keep the <em>Default Value</em>, <em>Minimum</em> and <em>Maximum</em> fields blank. Leave blank for default = <em>@default</em>.', [
        '@default' => isset($field_settings['step']) && is_numeric($field_settings['step']) ? Numeric::toString($field_settings['step']) : $base_step,
      ]),
    ];
    $element['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum', [], ['context' => 'numeric item']),
      '#step' => $settings['step'],
      '#min' => $floor,
      '#max' => $max,
      '#default_value' => $mode_settings['min'],
      '#field_suffix' => "min {$settings['floor']}, max {$settings['max']}",
      '#description' => t('The minimum value that should be allowed in this field. Leave blank for default = <em>@default</em>.', [
        '@default' => is_numeric($field_settings['min']) ? Numeric::toString($field_settings['min']) : $settings['floor'],
      ]),
    ];
    $element['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum', [], ['context' => 'numeric item']),
      '#step' => $settings['step'],
      '#min' => $min,
      '#max' => $ceil,
      '#default_value' => $mode_settings['max'],
      '#field_suffix' => "min {$settings['min']}, max {$settings['ceil']}",
      '#description' => t('The maximum value that should be allowed in this field. Leave blank for default = <em>@default</em>.', [
        '@default' => is_numeric($field_settings['max']) ? Numeric::toString($field_settings['max']) : $settings['ceil'],
      ]),
    ];
    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix', [], ['context' => 'numeric item']),
      '#default_value' => $mode_settings['prefix'],
      '#description' => t('Define a string that should be prefixed to the value, like "$ " or "&euro; ". Leave blank for none. Separate singular and plural values with a pipe ("pound|pounds"). Leave blank for default = <em>@default</em>.', [
        '@default' => $settings['prefix'],
      ]),
    ];
    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix', [], ['context' => 'numeric item']),
      '#default_value' => $mode_settings['suffix'],
      '#description' => t('Define a string that should be suffixed to the value, like " m", " kb/s". Separate singular and plural values with a pipe ("pound|pounds"). Leave blank for default = <em>@default</em>.', [
        '@default' => $settings['suffix'],
      ]),
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder', [], ['context' => 'numeric item']),
      '#default_value' => $mode_settings['placeholder'],
      '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format. Leave blank for default = <em>@default</em>.', [
        '@default' => $settings['placeholder'],
      ]),
    ];

    if (is_numeric($settings['min'])) {
      $element['default_value']['#min'] = $settings['min'];
      $element['max']['#min'] = $settings['min'];
    }
    if (is_numeric($settings['max'])) {
      $element['default_value']['#max'] = $settings['max'];
      $element['step']['#max'] = $settings['max'];
      $element['min']['#max'] = $settings['max'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getFormDisplayModeSettings();

    foreach ($settings as $name => $value) {
      $summary[] = "{$name}: {$value}";
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $settings = $this->getFormDisplayModeSettings();
    $value = isset($items[$delta]->value) ? Numeric::toString($items[$delta]->value + 0) : NULL;
    $default_value = is_numeric($settings['default_value']) ? $settings['default_value'] : $value;
    $count = is_numeric($default_value) ? $default_value : 1;

    $element += [
      '#type' => 'number',
      '#default_value' => is_numeric($default_value) ? $default_value : '',
      '#step' => $settings['step'],
    ];

    // Set minimum and maximum.
    if (is_numeric($settings['min'])) {
      $element['#min'] = $settings['min'];
    }
    if (is_numeric($settings['max'])) {
      $element['#max'] = $settings['max'];
    }

    // Add prefix and suffix.
    if (is_string($settings['prefix']) && !empty($settings['prefix'])) {
      $prefixes = explode('|', $settings['prefix']);
      $prefix = (count($prefixes) > 1) ? $this->formatPlural($count, $prefixes[0], $prefixes[1]) : $prefixes[0];
      $element['#field_prefix'] = FieldFilteredMarkup::create($prefix);
    }
    if (is_string($settings['suffix']) && !empty($settings['suffix'])) {
      $suffixes = explode('|', $settings['suffix']);
      $suffix = (count($suffixes) > 1) ? $this->formatPlural($count, $suffixes[0], $suffixes[1]) : $suffixes[0];
      $element['#field_suffix'] = FieldFilteredMarkup::create($suffix);
    }

    // Set placeholder.
    if (is_string($settings['placeholder']) && !empty($settings['placeholder'])) {
      $element['#placeholder'] = $settings['placeholder'];
    }

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    return $element['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplayModeSettings() {
    $floor = $ceil = $none = t('None', [], ['context' => 'numeric item']);
    // Base field settings.
    $field_settings = $this->getFieldSettings();
    // The current form display mode settings.
    $settings = $this->getSettings();
    if (isset($settings['disable_on_cart']) && $settings['disable_on_cart'] === '') {
      // This is required only by commerce_xquantity module.
      unset($settings['disable_on_cart']);
    }
    // Base or default form display default value.
    $default_value = current(array_column($this->fieldDefinition->getDefaultValueLiteral(), 'value'));
    $default_value = is_numeric($default_value) ? Numeric::toString(($default_value + 0)) : $none;
    $settings['default_value'] = is_numeric($settings['default_value']) ? Numeric::toString(($settings['default_value'] + 0)) : $default_value;

    foreach ($settings as $key => $value) {
      if (!is_numeric($value) && isset($field_settings[$key]) && is_numeric($field_settings[$key])) {
        $settings[$key] = Numeric::toString($field_settings[$key]);
      }
      elseif (is_numeric($value)) {
        $settings[$key] = Numeric::toString($value);
      }
      if ($settings[$key] != '0' && empty($settings[$key])) {
        $settings[$key] = isset($field_settings[$key]) ? Numeric::toString($field_settings[$key]) : $none;
      }
    }

    $min = $settings['min'];
    $max = $settings['max'];
    if (!empty($field_settings['unsigned'])) {
      $floor = '0';
      $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
    }

    if (isset($field_settings['size'])) {
      $size = $field_settings['size'];
    }
    elseif (isset($field_settings['precision']) && isset($field_settings['scale'])) {
      $size = [
        'precision' => $field_settings['precision'],
        'scale' => $field_settings['scale'],
      ];
    }

    if (isset($size)) {
      $sizes = Numeric::getStorageMaxMin($size);
      if (!empty($field_settings['unsigned'])) {
        $ceil = $sizes['unsigned'];
        $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
        $settings['max'] = !is_numeric($max) || $max > $ceil ? $ceil : $max;
      }
      else {
        $floor = $sizes['signed']['min'];
        $ceil = $sizes['signed']['max'];
        $settings['min'] = !is_numeric($min) || $min < $floor ? $floor : $min;
        $settings['max'] = !is_numeric($max) || $max > $ceil ? $ceil : $max;
      }
    }

    switch ($this->fieldDefinition->getType()) {
      case 'integer':
      case 'xinteger':
        $step = '1';
        break;

      case 'decimal':
      case 'xdecimal':
        $step = Numeric::toString(pow(0.1, $field_settings['scale']));
        break;

      case 'float':
      case 'xfloat':
        $step = 'any';
        break;
    }

    $settings['base_default_value'] = $default_value;
    $settings['base_step'] = $step;
    $settings['step'] = $settings['step'] == $none ? $step : $settings['step'];
    $settings['floor'] = $floor;
    $settings['ceil'] = $ceil;

    return $settings;
  }

}
