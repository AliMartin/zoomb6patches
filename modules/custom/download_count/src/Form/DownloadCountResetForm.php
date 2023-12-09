<?php

namespace Drupal\download_count\Form;

use Drupal;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Database\Database;
use Drupal\Component\Utility\Html;

/**
 * Implements the reset form controller.
 *
 * @see \Drupal\Core\Form\FormBase
 * @see \Drupal\Core\Form\FormStateInterface
 */
class DownloadCountResetForm extends ConfirmFormBase {

  /**
   * The dc entry.
   *
   * @var dcEntry
   */
  public $dcEntry;

  /**
   * Show confirm form.
   *
   * @var showconfirmform
   */
  protected $confirm;

  /**
   * The question tag.
   *
   * @var question
   */
  protected $question;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'download_count_reset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $download_count_entry = NULL) {
    if ($download_count_entry != NULL) {
      $connection = Database::getConnection();
      $query = $connection->select('download_count', 'dc');
      $query->join('file_managed', 'f', 'dc.fid = f.fid');
      $query->fields('dc', [
        'dcid',
        'fid',
        'uid',
        'type',
        'id',
        'ip_address',
        'referrer',
        'timestamp',
      ]);
      $query->fields('f', ['filename', 'uri', 'filemime', 'filesize']);
      $query->condition('dc.dcid', $download_count_entry);
      $this->dcEntry = $query->execute()->fetchObject();
    }
    else {
      $this->dcEntry = 'all';
    }
    if ($this->dcEntry != 'all') {
      $form['dcid'] = [
        '#type' => 'value',
        '#value' => $this->dcEntry->dcid,
      ];
      $form['filename'] = [
        '#type' => 'value',
        '#value' => Html::escape($this->dcEntry->filename),
      ];
      $form['fid'] = [
        '#type' => 'value',
        '#value' => $this->dcEntry->fid,
      ];
      $form['type'] = [
        '#type' => 'value',
        '#value' => Html::escape($this->dcEntry->type),
      ];
      $form['id'] = [
        '#type' => 'value',
        '#value' => $this->dcEntry->id,
      ];
      $this->confirm = TRUE;
      $this->question = TRUE;

      return parent::buildForm($form, $form_state);
    }
    else {
      $form['dcid'] = [
        '#type' => 'value',
        '#value' => 'all',
      ];
      $this->confirm = TRUE;
      $this->question = TRUE;

      return parent::buildForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    if ($this->dcEntry != 'all') {
      return $this->t('Reset');
    }
    else {
      return $this->t('Reset All');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->dcEntry != 'all') {
      return $this->t('Are you sure you want to reset the download count for %filename on %entity #%id?', [
        '%filename' => $this->dcEntry->filename,
        '%entity' => $this->dcEntry->type,
        '%id' => $this->dcEntry->id,
      ]);
    }
    else {
      return $this->t('Are you sure you want to reset all download counts?');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('download_count.reports');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $result = NULL;
    if ($form['dcid']['#value'] == 'all') {
      $result = db_truncate('download_count')->execute();
      if ($result) {
        db_truncate('download_count_cache')->execute();
        $this->messenger()->addStatus($this->t('All download counts have been reset.'));
        Drupal::logger('download_count')
          ->notice('All download counts have been reset.');
      }
      else {
        $this->messenger()->addError($this->t('Unable to reset all download counts.'));
        Drupal::logger('download_count')
          ->error('Unable to reset all download counts.');
      }
    }
    else {
      // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
      // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
      $result = \Drupal::database()->delete('download_count')
        ->condition('fid', $form['fid']['#value'])
        ->condition('type', $form['type']['#value'])
        ->condition('id', $form['id']['#value'])
        ->execute();
      if ($result) {
        // TODO: Drupal Rector Notice: Please delete the following comment after you've made any necessary changes.
        // You will need to use `\Drupal\core\Database\Database::getConnection()` if you do not yet have access to the container here.
        \Drupal::database()->delete('download_count_cache')
          ->condition('fid', $form['fid']['#value'])
          ->condition('type', $form['type']['#value'])
          ->condition('id', $form['id']['#value'])
          ->execute();
        $this->messenger()->addStatus($this->t('Download count for %filename on %type %id was reset.', [
          '%filename' => $form['filename']['#value'],
          '%type' => $form['type']['#value'],
          '%id' => $form['id']['#value'],
        ]));
        Drupal::logger('download_count')
          ->notice('Download count for %filename on %type %id was reset.', [
            '%filename' => $form['filename']['#value'],
            '%type' => $form['type']['#value'],
            '%id' => $form['id']['#value'],
          ]);
      }
      else {
        $this->messenger()->addError($this->t('Unable to reset download count for %filename on %type %id.', [
          '%filename' => $form['filename']['#value'],
          '%type' => $form['type']['#value'],
          '%id' => $form['id']['#value'],
        ]));
        Drupal::logger('download_count')
          ->error('Unable to reset download count for %filename on %type %id.', [
            '%filename' => $form['filename']['#value'],
            '%type' => $form['type']['#value'],
            '%id' => $form['id']['#value'],
          ]);
      }
    }
    $form_state->setRedirect('download_count.reports');
  }

}
