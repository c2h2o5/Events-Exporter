<?php

namespace Drupal\bgm_events_exporter\Normalizer;

use Drupal\bgm_events_exporter\Helper\Common;
use Drupal\serialization\Normalizer\EntityNormalizer;
use Drupal\big_event\Entity\BigEventBlock;
use Drupal\Core\Site\Settings;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

use function count;

/**
 * Provide a user normalizer for BGM Migration
 *
 * @date 28.06.2023
 * @author Krasimir Bachev <krasimir.bachev@bynd.one>
 */
class EventBlock extends EntityNormalizer
{

  /**
   * @var array
   */
  protected $supportedInterfaceOrClass = BigEventBlock::class;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an EntityNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeRepositoryInterface $entity_type_repository, EntityFieldManagerInterface $entity_field_manager)
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeRepository = $entity_type_repository;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * @param $object
   * @param $format
   * @param array $context
   *
   * @return void
   */
  public function normalize($object, $format = NULL, array $context = [])
  {
    $block = parent::normalize($object, $format, $context);

    $block['uuid'] = $block['uuid'][0]['value'];
    $block['type'] = $block['type'][0]['target_id'];
    $block['name'] = $block['name'][0]['value'];
    $block['big_event'] = $block['big_event'][0]['target_uuid'];
    $block['max_subscriber'] = $block['max_subscriber'][0]['value'];
    $block['waitinglist'] = $block['waitinglist'][0]['value'];
    $block['status'] = $block['status'][0]['value'];

    if ($block['type'] == 'local') {
      $block['field_address'] = [
        'value' => $block['field_address'][0]['value'],
        'format' => 'full_html'
      ];
    }

    if (count($block['subscriptions'])) {
      foreach ($block['subscriptions'] as $index => $subscription) {
        $user = $this->entityTypeManager->getStorage('user')->load($subscription['user_id']);
        if (!$user) {
          continue;
//          throw new \Exception('User cannot be loaded!');
        }
        $block['subscriptions'][$index]['user_id'] = $user->uuid();
      }
    }

    //clearer
    $args = ['id', 'created', 'changed'];
    Common::cleaner($block, $args);
    return $block;
  }

}
