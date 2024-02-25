<?php

namespace Drupal\bgm_events_exporter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Site\Settings;


/**
 * Provides a Bgm events exporter form.
 */
class Exporter extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'bgm_events_exporter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['#attributes']['id'] = 'bgm-events-exporter-wrapper';

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => 'Einstellungen'
    ];

    $form['settings']['options'] = [
      '#type' => 'select',
      '#title' => 'Events mit status:',
      '#options' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'cancelled' => 'Cancelled',
        'done' => 'Done',
        'archived' => 'Archived',
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'updatePreview'],
        'wrapper' => 'bgm-events-exporter-wrapper',
      ],
    ];

    $form['events'] = [
      '#type' => 'fieldset',
      '#title' => 'Events',
    ];

    $form['events']['table'] = [
      '#type' => 'tableselect',
      '#header' => $this->getTableHeaders(),
      '#options' => $this->getTableRows($form_state->getValue('options') ?? 'draft'),
      '#empty' => 'No content available.',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['export'] = [
      '#type' => 'submit',
      '#value' => 'Export',
      '#action' => 'export'
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {

  }

  public function updatePreview(array &$form, FormStateInterface $formState)
  {
    $form['events']['table']['#options'] = $this->getTableRows($formState->getValue('options'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState)
  {
    $trigger = $formState->getTriggeringElement();
    switch ($trigger['#action']) {

      case 'export':
        $events = \Drupal::entityTypeManager()->getStorage('big_event')->loadMultiple($formState->getValue('table'));
        if (!count($events)) {
          $this->messenger()->addError('Es sind keine verfügbare Events.');
          return $this->redirect('entity.big_event.collection');
        }
        $this->export($events, $formState);
        break;
    }
  }


  /**
   * @param string $status
   *
   * @return array
   * @throws \Exception
   */
  private function getEvents(string $status = 'draft'): array
  {
    $storage = \Drupal::entityTypeManager()->getStorage('big_event');
    $events = $storage->loadByProperties(['status' => $status]);

    if (!count($events)) {
      throw new \Exception('Events not found');
    }
    return $events;
  }

  /**
   * @return array
   */
  public function getTableHeaders(): array
  {
    return [
      'e_id' => 'ID',
      'e_label' => 'Event',
      'e_status' => 'Status'
    ];
  }

  /**
   * @param FormStateInterface $formState
   *
   * @return array
   */
  public function getTableRows(string $status): array
  {
    $output = [];
    $events = $this->getEvents($status);
    if (!count($events)) {
      return $output;
    }

    foreach ($events as $event) {
      $output[$event->id()] = [
        'e_id' => $event->id(),
        'e_label' => $event->label(),
        'e_status' => $event->get('status')->value
      ];
    }
    return $output;
  }

  /**
   * @param array $events
   *
   * @return BinaryFileResponse|RedirectResponse
   */
  protected function export(array $events, FormStateInterface $formState)
  {
    $serializer = \Drupal::service('serializer');
    $fileSystem = \Drupal::service('file_system');

    $filename = 'events_plattform-' . Settings::get('platform_id') . '_' . $formState->getValue('options') . '.json';
    $file = $fileSystem->createFilename($filename, 'temporary://');
    $fileSize = file_put_contents($file, $serializer->serialize($events, 'json'));

    $headers = [
      'Content-Type' => 'application/json',
      'Content-Disposition' => 'attachment;filename="' . $filename . '"',
      'Content-Length' => $fileSize,
      'Content-Description' => 'File Transfer'
    ];

    $formState->setResponse(new BinaryFileResponse($file, 200, $headers, true));
  }

}
