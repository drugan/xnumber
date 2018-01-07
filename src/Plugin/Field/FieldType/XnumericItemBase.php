<?php

namespace Drupal\xnumber\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Base class for xnumeric configurable field types.
 */
abstract class XnumericItemBase extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'step' => '',
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
    $element = [];
    $none = $this->t('None', [], ['context' => 'numeric item']);
    $settings = $this->getSettings();
    extract($this->getValue());

    $base_default_value = $this->t('The <em>Default Value</em> field above is the regular number field displayed in a <em>default</em> form display mode. So, if any of the settings are changed on the mode then they will override the field settings. In its turn, the value on this <em>Default Value</em> field may be set to serve as a base default value for any of the form display modes, the <em>default</em> form display mode including (if not overriden).</ br>The current <em>Base Default Value</em>: <strong>@value</strong><h3>Need help?</h3>A verbose tutorial with a lot of screenshots can be found here: <a href=":href" target="_blank">admin/help/xnumber</a>', [
      '@value' => isset($value) && is_numeric($value) ? Numeric::toString($value) : $none,
      ':href' => '/admin/help/xnumber',
    ]);

    $element['base_default_value'] = [
      '#type' => 'details',
      '#title' => t('Base Default Value'),
      '#open' => TRUE,
      '#markup' => "<div class='description'>$base_default_value</div>",
    ];
    $element['step'] = [
      '#type' => 'number',
      '#title' => $this->t('Step', [], ['context' => 'numeric item']),
      '#description' => $this->t('The default minimum allowed amount to increment or decrement the field value. Note that setting an integer for this value on a decimal or float field restricts the input on the field to integer values only. Can be overriden on a form display mode. While updating this field it is recommended to keep the <em>Default Value</em>, <em>Minimum</em> and <em>Maximum</em> fields blank.'),
      '#maxlenght' => 60,
    ];
    $element['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum', [], ['context' => 'numeric item']),
      '#default_value' => Numeric::toString($settings['min']),
      '#description' => $this->t('The default minimum value that should be allowed in this field. Can be overriden on a form display mode. While updating this field it is recommended to keep the <em>Default Value</em> and <em>Maximum</em> fields blank. Leave blank for default minimum.'),
    ];
    $element['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum', [], ['context' => 'numeric item']),
      '#default_value' => Numeric::toString($settings['max']),
      '#description' => $this->t('The default maximum value that should be allowed in this field. Can be overriden on a form display mode. While updating this field it is recommended to keep the <em>Default Value</em> field blank. Leave blank for default maximum.'),
    ];
    $element['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix', [], ['context' => 'numeric item']),
      '#default_value' => $settings['prefix'],
      '#size' => 60,
      '#description' => t("Define a default string that should be prefixed to the value, like '$ ' or '&euro; '. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds'). Can be overriden on a form display mode."),
    ];
    $element['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix', [], ['context' => 'numeric item']),
      '#default_value' => $settings['suffix'],
      '#size' => 60,
      '#description' => t("Define a default string that should be suffixed to the value, like ' m', ' kb/s'. Leave blank for none. Separate singular and plural values with a pipe ('pound|pounds'). Can be overriden on a form display mode."),
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder', [], ['context' => 'numeric item']),
      '#default_value' => isset($settings['placeholder']) ? $settings['placeholder'] : '',
      '#description' => t('The default text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format. Can be overriden on a form display mode.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (empty($this->value) && Numeric::toString($this->value) !== '0') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    // Find the way to fetch the current display mode for the field and remove
    // the return statement below. For now rely on the max and min validation
    // done on the client and element level.
    // @see Drupal\xnumber\Render\Element\Xnumber::validateNumber()
    // @see https://www.drupal.org/project/drupal/issues/2816859#comment-12016259
    return $constraints;
  }

}
