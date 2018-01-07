<?php

namespace Drupal\xnumber\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\xnumber\Utility\Xnumber as Numeric;
use Drupal\Core\Render\Element\Number;

/**
 * Overrides \Drupal\Core\Render\Element\Number class.
 *
 * {@inheritdoc}
 */
class Xnumber extends Number {

  /**
   * Form element validation handler for #type 'number'.
   *
   * Note that #required is validated by _form_validate() already.
   */
  public static function validateNumber(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];
    if ($value === '') {
      return;
    }

    $name = empty($element['#title']) ? $element['#parents'][0] : $element['#title'];

    // Ensure the input is numeric.
    if (!is_numeric($value)) {
      $form_state->setError($element, t('%name must be a number.', ['%name' => $name]));
      return;
    }

    // Ensure that the input is greater than the #min property, if set.
    if (isset($element['#min']) && $value < $element['#min']) {
      $form_state->setError($element, t('%name must be higher than or equal to %min.', ['%name' => $name, '%min' => $element['#min']]));
    }

    // Ensure that the input is less than the #max property, if set.
    if (isset($element['#max']) && $value > $element['#max']) {
      $form_state->setError($element, t('%name must be lower than or equal to %max.', ['%name' => $name, '%max' => $element['#max']]));
    }

    if (isset($element['#step']) && strtolower($element['#step']) != 'any') {
      // Check that the input is an allowed multiple of #step (offset by #min if
      // #min is set).
      $min = isset($element['#min']) ? $element['#min'] : NULL;

      if (!Numeric::validStep($value, $element['#step'], $min)) {
        $form_state->setError($element, t('%name is not a valid number.', ['%name' => $name]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (is_numeric($input)) {
      // Cast number to string to prevent errors on extremely small or large
      // float values which may appear in scientific notation.
      return Numeric::toString($input);
    }
    return NULL;
  }

}
