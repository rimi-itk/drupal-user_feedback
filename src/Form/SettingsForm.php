<?php

declare(strict_types=1);

namespace Drupal\user_feedback\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Provides a User feedback form.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'user_feedback.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'user_feedback_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::SETTINGS);

    $form['settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Settings'),
      '#default_value' => $config->get('settings'),
      '#description' => $this->t('Yaml settings'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $settings = $form_state->getValue('settings');
    assert(is_string($settings));
    try {
      Yaml::decode($settings);
    }
    catch (\Exception $exception) {
      $form_state->setErrorByName(
        'settings',
        $this->t('Invalid settings: @message',
          ['@message' => $exception->getMessage()])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::SETTINGS)
      ->set('settings', $form_state->getValue('settings'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
