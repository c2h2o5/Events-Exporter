<?php
declare(strict_types=1);

namespace Drupal\bgm_events_exporter\Helper;

use \Drupal\Core\Datetime\DrupalDateTime;

use function count;
use function array_key_exists;

/**
 * Provide a common helper functions
 * @author <krasimir.bachev@bynd.one>
 */
class Common
{

  /**
   * @param string $type
   * @param array $conditions
   * @return array
   */
  public static function getFromStorage(string $type, array $conditions): array
  {
    $storage = \Drupal::entityTypeManager()->getStorage($type);
    $query = $storage->getQuery();
    foreach ($conditions as $condition) {
      list($field, $value, $operand) = $condition;
      if (!isset($operand)) {
        $query->condition($field, $value);
      }
      else {
        $query->condition($field, $value, $operand);
      }
    }
    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * @param array $data
   * @param array $args
   * @return void
   */
  public static function cleaner(array &$data, array $args = []): void
  {
    if (count($args)) {
      foreach ($args as $arg) {
        if (array_key_exists($arg, $data)) {
          unset($data[$arg]);
        }
      }
    }
  }

  /**
   * @param array $collection
   * @param array $fields
   * @param string $format
   * @return void
   */
  public static function dateTimeNormalizer(array &$collection, array $fields, string $format = 'Y-m-d\TH:i:sP'): void
  {
    if (!count($fields)) {
      \Drupal::logger('DateTimeNormalizer')->notice('The fields for datetime normalizing are empty!');
    }
    else {
      $drupalDateTime = new DrupalDateTime;
      foreach ($fields as $field) {
        $fromDateTime = $collection[$field];
        $collection[$field] = $drupalDateTime->createFromFormat($format, $fromDateTime)->getTimestamp();
      }
    }
  }
}
