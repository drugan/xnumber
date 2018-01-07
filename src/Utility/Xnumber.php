<?php

namespace Drupal\xnumber\Utility;

use Drupal\Component\Utility\Number;

/**
 * Provides helper methods for manipulating numbers.
 *
 * @ingroup utility
 */
class Xnumber extends Number {

  /**
   * Verifies that a number is a multiple of a given step.
   *
   * The implementation assumes it is dealing with IEEE 754 double precision
   * floating point numbers that are used by PHP on most systems.
   *
   * This is based on the number/range verification methods of webkit.
   *
   * @param float $value
   *   The value that needs to be checked.
   * @param float $step
   *   The step scale factor. Must be positive.
   * @param float $min
   *   (optional) A minimum, to which the difference must be a multiple of the
   *   given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occurred, or FALSE otherwise.
   *
   * @see http://opensource.apple.com/source/WebCore/WebCore-1298/html/NumberInputType.cpp
   */
  public static function validStep($value, $step, $min = NULL) {
    $scale = static::getDecimalDigits($step);
    // Set scale for all subsequent BC MATH calculations.
    bcscale($scale);
    $value = static::toString($value);
    $step = static::toString($step);

    // The $step must be greater than 0.
    if (bccomp($step, '0') !== 1) {
      return FALSE;
    }

    $comp = bccomp($value, $step);

    if (is_numeric($min)) {
      $min = static::toString($min);

      // No further checking has sense if $min is greater than $value.
      if (bccomp($min, $value) === 1) {
        return FALSE;
      }

      $floor = $min;
      $ceil = $value;
    }
    elseif ($comp === 1) {
      $floor = $step;
      $ceil = $value;
    }
    else {
      $floor = $value;
      $ceil = $step;
    }

    // The $step is valid if it is equal to the unsigned $value.
    if (bccomp(abs($value), $step) === 0) {
      return TRUE;
    }

    // If the $scale is 0 then we have an integer $step.
    if ($scale == 0 && static::getDecimalDigits($value) == 0) {
      // Quite robust solution for any range between $ceil and $floor.
      // @see http://php.net/manual/en/function.bcmod.php#38474
      $sub = bcsub($ceil, $floor);
      $remainder = '';

      do {
        $substr = (int) $remainder . substr($sub, 0, 5);
        $sub = substr($sub, 5);
        $remainder = $substr % $step;
      } while (strlen($sub));

      if (empty($remainder)) {
        return TRUE;
      }
    }
    else {
      // Try fmod() and then move to the old way of detecting $step validity
      // which sometimes fails even on pretty obviously valid step like:
      // $value = -0.1, $step = 0.1, $min = -9999999.99.
      // @todo Explore the way browsers do that check.
      $remainder = static::toString(fmod($value, $step));
      // Round with bcsub() if $step is 9.001 but $remainder is (-)9.00101.
      $sub = bcsub($remainder, $step);
      $sub_sub = bcsub($sub, $step);
      if (empty($remainder) || empty($sub) || empty($sub_sub)) {
        return TRUE;
      }
    }

    $min = $min === NULL ? 0.0 : $min;
    $double_value = (double) abs($value - $min);

    // The fractional part of a double has 53 bits. The greatest number that
    // could be represented with that is 2^53. If the given value is even bigger
    // than $step * 2^53, then dividing by $step will result in a very small
    // remainder. Since that remainder can't even be represented with a single
    // precision float the following computation of the remainder makes no sense
    // and we can safely ignore it instead.
    if ($double_value / pow(2.0, 53) > $step) {
      return TRUE;
    }

    // Now compute that remainder of a division by $step.
    $remainder = (double) abs($double_value - $step * round($double_value / $step));

    // $remainder is a double precision floating point number. Remainders that
    // can't be represented with single precision floats are acceptable. The
    // fractional part of a float has 24 bits. That means remainders smaller
    // than $step * 2^-24 are acceptable.
    $computed_acceptable_error = (double) ($step / pow(2.0, 24));

    return $computed_acceptable_error >= $remainder || $remainder >= ($step - $computed_acceptable_error);
  }

  /**
   * Generates a sorting code from an integer.
   *
   * Consists of a leading character indicating length, followed by N digits
   * with a numerical value in base 36 (alphadecimal). These codes can be sorted
   * as strings without altering numerical order.
   *
   * It goes:
   * 00, 01, 02, ..., 0y, 0z,
   * 110, 111, ... , 1zy, 1zz,
   * 2100, 2101, ..., 2zzy, 2zzz,
   * 31000, 31001, ...
   *
   * @param int $i
   *   The integer value to convert.
   *
   * @return string
   *   The alpha decimal value.
   *
   * @see \Drupal\Component\Utility\Number::alphadecimalToInt
   */
  public static function intToAlphadecimal($i = 0) {
    $num = base_convert((int) $i, 10, 36);
    $length = strlen($num);

    return chr($length + ord('0') - 1) . $num;
  }

  /**
   * Decodes a sorting code back to an integer.
   *
   * @param string $string
   *   The alpha decimal value to convert.
   *
   * @return int
   *   The integer value.
   *
   * @see \Drupal\Component\Utility\Number::intToAlphadecimal
   */
  public static function alphadecimalToInt($string = '00') {
    return (int) base_convert(substr($string, 1), 36, 10);
  }

  /**
   * Helper method to truncate a decimal number to a given number of decimals.
   *
   * @param float $decimal
   *   Decimal number to truncate.
   * @param int $num
   *   Number of digits the output will have.
   *
   * @return float
   *   Decimal number truncated.
   */
  public static function truncateDecimal($decimal, $num) {
    return floor($decimal * pow(10, $num)) / pow(10, $num);
  }

  /**
   * Helper method to get the number of decimal digits out of a decimal number.
   *
   * @param int $decimal
   *   The number to calculate the number of decimals digits from.
   *
   * @return int
   *   The number of decimal digits.
   */
  public static function getDecimalDigits($decimal) {
    $decimal = static::toString($decimal);
    $digits = 0;
    while ($decimal - round($decimal)) {
      $decimal *= 10;
      $decimal = static::toString($decimal);
      $digits++;
    }
    return $digits;
  }

  /**
   * Helper method to cast a number to string.
   *
   * @param float|int $number
   *   The integer or decimal or floating point number to cast.
   *
   * @return string
   *   The string representation of a number.
   */
  public static function toString($number) {
    // Ensure that number is not in scientific notation.
    if (is_numeric($number) && stristr($number, 'e')) {
      $minus = $number < 0 ? '-' : '';
      $abs = abs(floatval($number));
      // @todo Find better way to convert from scientific notation.
      $float = explode('.', ($abs + 1));
      $number = $minus . ($float[0] - 1);
      if (!empty($float[1])) {
        $number .= '.' . $float[1];
      }
    }
    return (string) $number;
  }

  /**
   * Helper method to get number min and max storage sizes.
   *
   * @param string|array $size
   *   (optional) The storage size name or an array with precision and scale.
   *
   * @return array
   *   An array of sizes keyed by by size name.
   *
   * @see \Drupal\Core\Database\Driver\mysql::getFieldTypeMap()
   * @see \Drupal\Core\Database\Driver\pgsql::getFieldTypeMap()
   * @see \Drupal\Core\Database\Driver\sqlite::getFieldTypeMap()
   * @see https://dev.mysql.com/doc/refman/5.7/en/integer-types.html
   * @see https://dev.mysql.com/doc/refman/5.7/en/fixed-point-types.html
   * @see https://mariadb.com/kb/en/mariadb/data-types-numeric-data-types/
   * @see https://www.postgresql.org/docs/9.5/static/datatype-numeric.html
   * @see https://www.sqlite.org/datatype3.html
   */
  public static function getStorageMaxMin($size = NULL) {
    $sizes = [
      'tiny' => [
        'signed' => [
          'min' => '-128',
          'max' => '127',
        ],
        'unsigned' => '255',
      ],
      'small' => [
        'signed' => [
          'min' => '-32768',
          'max' => '32767',
        ],
        'unsigned' => '65535',
      ],
      'medium' => [
        'signed' => [
          'min' => '-8388608',
          'max' => '8388607',
        ],
        'unsigned' => '16777215',
      ],
      'normal' => [
        'signed' => [
          'min' => '-2147483648',
          'max' => '2147483647',
        ],
        'unsigned' => '4294967295',
      ],
      'big' => [
        'signed' => [
          'min' => '-9223372036854775808',
          'max' => '9223372036854775807',
        ],
        // The commented out greatest size does not work with filter_var() for
        // PHP 5.6 now, while flawlessly saves the value in the MySql database
        // and displays it on a web page.
        // @todo Find out why it fails even with options arg to filter_var() in:
        // @see Drupal\Core\Validation\Plugin\Validation\Constraint\PrimitiveTypeConstraintValidator::validate()
        // 'unsigned' => '18446744073709551615'.
        'unsigned' => '9223372036854775807',
      ],
    ];

    if (isset($size['precision']) && isset($size['scale'])) {
      $integers = str_pad('', ($size['precision'] - $size['scale']), '9');
      $decimals = str_pad('', $size['scale'], '9');
      $max = ($integers ? $integers : '0') . ($decimals ? ".$decimals" : '');
      $sizes = [
        'signed' => [
          'min' => "-$max",
          'max' => $max,
        ],
        'unsigned' => $max,
      ];
    }
    elseif (isset($sizes[$size])) {
      $sizes = $sizes[$size];
    }

    return $sizes;
  }

}
