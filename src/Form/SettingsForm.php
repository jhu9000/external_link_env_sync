<?php

namespace Drupal\external_link_env_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class file.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'external_link_env_sync.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'external_link_env_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable external_link_env_sync'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['condition_pattern'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#columns' => 40,
      '#title' => $this->t('Environment conditions and patterns'),
      '#default_value' => $config->get('condition_pattern') ?? '',
      '#description' => $this->t('Enter "[environment variable name]=[environment variable value], [pattern]", one per line. Pattern should include a "{{hostname}}" token that will be replaced to make new hostname. Example: "IS_DOCKSAL=1, http://{{hostname}}.myproject.docksal.site".'),
    ];

    $form['search_replace'] = [
      '#type' => 'textarea',
      '#rows' => 10,
      '#columns' => 40,
      '#title' => $this->t('Search and replacement list'),
      '#default_value' => $config->get('search_replace') ?? '',
      '#description' => $this->t('Enter "[search], [replacement for token in pattern]" excluding scheme and port, one per line. Example: "stage-mysite.com, mysite.com".'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns an array of settings keys.
   */
  public function settingsKeys() {
    return [
      'enabled',
      'condition_pattern',
      'search_replace',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save config settings.
    $config = $this->config(static::SETTINGS);
    foreach ($this->settingsKeys() as $key) {
      $value = $form_state->getValue($key);
      if (is_string($value)) {
        $value = trim($value);
      }
      $config->set($key, $value);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo maybe
  }

}
