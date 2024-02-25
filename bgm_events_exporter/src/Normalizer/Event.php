<?php

namespace Drupal\bgm_events_exporter\Normalizer;

use Drupal\serialization\Normalizer\EntityNormalizer;
use Drupal\big_event\Entity\BigEvent;
use Drupal\Core\Site\Settings;
use Drupal\bgm_events_exporter\Helper\Common;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\file\Entity\File;

use function count;

/**
 * Provide a user normalizer for BGM Migration
 *
 * @date 28.06.2023
 * @author Krasimir Bachev <krasimir.bachev@bynd.one>
 */
class Event extends EntityNormalizer
{

  /**
   * @var array
   */
  protected $supportedInterfaceOrClass = BigEvent::class;

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
    $event = parent::normalize($object, $format, $context);
    $blocks = $object->getEventBlocks();

    $event['uuid'] = $event['uuid'][0]['value'];
    $event['langcode'] = $event['langcode'][0]['value'];
    $event['type'] = ['target_id' => $event['type'][0]['target_id']];
    $event['title'] = $event['title'][0]['value'];
    $event['status'] = $event['status'][0]['value'];

    $event['field_date'] = $event['field_date'][0]['value'] ?? null;
    $event['field_teaser']['value'] = $event['field_big_teaser_txt'][0]['value'];
    $event['field_teaser']['format'] = 'full_html';
    $event['field_max_event_blocks'] = $event['field_max_event_blocks'][0]['value'];
    $event['field_company'] = Settings::get('platform_id');
    $event['field_image'] = $this->getEncodedImage($event['field_big_teaser_img'][0]);

    //normalize event blocks
    $event['blocks'] = count($blocks) ? parent::normalize($blocks, $format, $context) : [];

    //normalize the paragraphs
    if (count($event['field_paragraphs'])) {
      $paragraphs = $event['field_paragraphs'];
      unset($event['field_paragraphs']);
      foreach ($paragraphs as $paragraph) {
        $type = $paragraph['type'][0]['target_id'];
        $para = ['type' => $type];

        switch ($type) {
          case 'cta_buttons':
            $para['type'] = 'buttons';
            $para['field_headline_plain'] = $paragraph['field_big_headline_txt'][0]['value'];
            $para['field_links'] = $paragraph['field_cta_button'];
            break;

          case 'text':
            $para['field_text_html']['value'] = $paragraph['field_big_body_txt'][0]['value'];
            $para['field_text_html']['format'] = 'full_html';
            $para['field_headline_long'] = $paragraph['field_big_headline_txt'][0]['value'];
            break;

          case 'video_private':
            $para['field_apivideo_id'] = $paragraph['field_video_private_id'][0]['value'];
            $para['field_image_background'] = $this->getEncodedImage($paragraph['field_big_video_preview_img'][0]);
            break;

          case 'big_quote':
            $para['type'] = 'quote';
            $para['field_highlighted'] = $paragraph['field_big_highlighted_bool'][0]['value'];
            $para['field_headline_long'] = $paragraph['field_big_quote_txt'][0]['value'];
            break;

          case 'bild':
            $para['field_image'] = $this->getEncodedImage($paragraph['field_big_image_img'][0]);
            $para['field_original_size'] = $paragraph['field_original_size'][0]['value'];
            break;

          case 'video':
            $para['field_video_embed'] = $paragraph['field_big_video_media'][0]['value'];
            $para['field_image_background'] = $this->getEncodedImage($paragraph['field_big_video_preview_img'][0]);
            break;

          case 'gallery':
            $images = $paragraph['field_big_gallery_img'];
            if (count($images)) {
              foreach ($images as $img) {
                $para['field_images'][] = $this->getEncodedImage($img);
              }
            }
            break;

          default:
            if (!isset($event['field_paragraphs']) || !count($event['field_paragraphs'])) {
              $event['field_paragraphs'] = [];
            }
            break 2;
        }
        $event['field_paragraphs'][] = $para;
      }
    }

    //clearer
    $args = [
      'id', 'user_id',
      'created', 'changed',
      'default_langcode', 'field_big_teaser_txt',
      'field_big_teaser_img', 'event_blocks'
    ];
    Common::cleaner($event, $args);
    return $event;
  }

  /**
   * @param array $data
   *
   * @return array
   */
  private function getEncodedImage(array $data): array
  {
    $image = File::load($data['target_id']);
    if (!$image) {
      \Drupal::logger('Event normalizer')->error('Image not found!');
      return [];
    }
    $file = base64_encode(file_get_contents($image->getFileUri()));
    return [
      'image' => ($file) ?: null,
      'basename' => $image->getFilename()
    ];
  }

}
