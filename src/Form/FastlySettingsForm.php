<?php
/**
 * @file
 * Contains Drupal\fastly\Form\FastlySettingsForm.
 */

namespace Drupal\fastly\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Defines a form to configure module settings.
 */
class FastlySettingsForm extends ConfigFormBase {

  /**
   * The Fastly API.
   *
   * @var \Drupal\Fastly\Api
   */
  protected $fastlyApi;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Drupal\Core\Config\ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->fastlyApi = \Drupal::service('fastly.api');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fastly_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['fastly.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('fastly.settings');

    $api_key = $config->get('api_key');
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $api_key,
      '#required' => TRUE,
      '#description' => 'Company ID for a Fastly account.'
    );

    $service_options = $this->getServiceOptions($api_key);
    if ($service_options) {
      $form['service_id'] = array(
        '#type' => 'select',
        '#title' => $this->t('Service'),
        '#options' => $service_options,
        '#default_value' => !empty($service_options) ? $config->get('service_id') : '',
        '#description' => $this->t('A Service represents the configuration for your website to be served through Fastly.'),
        '#required' => TRUE,
      );
    }
    else {
      $form['service_id'] = array(
        '#type' => 'item',
        '#title' => $this->t('Service'),
        '#markup' => $this->t('After entering an API key, click "Save configuration" to see the available services.'),
      );
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    if (!$this->isValidApiKey($api_key)) {
      $form_state->setErrorByName('api_key', $this->t('Invalid API key.'));
    }
    $service_id = $form_state->getValue('service_id');
    $service_options = $this->getServiceOptions($form_state->getValue('api_key'));
    if (empty($service_id)) {
      if (!($service_options)) {
        $form_state->setErrorByName('api_key', $this->t('The API key is valid but no services exist.'));
      }
      else {
        $form_state->setValue('service_id', key($service_options));
        if (count($service_options) > 1) {
         drupal_set_message($this->t('Please check that the correct service has been selected and click "Save configuration."'), 'warning');
        }
      }
    }
    elseif (!in_array($service_id, array_keys($service_options))) {
      $form_state->setErrorByName('service_id', $this->t('The service selected is not valid for the API key.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('fastly.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('service_id', $form_state->getValue('service_id'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Get services from Fastly.
   *
   * @param string $api_key
   *   API key.
   *
   * @return array
   *   Id => name.
   */
  protected function getServiceOptions($api_key) {
    if (!$this->isValidApiKey($api_key)) {
      return [];
    }
    try {
      $this->fastlyApi->setApiKey($api_key);
      $services = $this->fastlyApi->getServices();
      $service_options = [];
      foreach ($services as $service) {
        $service_options[$service->id] = $service->name;
      }
      ksort($service_options);
      return $service_options;
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      return [];
    }
  }

  /**
   * Checks if API key valid.
   *
   * @param string $api_key
   *   API key.
   *
   * @return bool
   *   TRUE if API key is validated by Fastly.
   */
  protected function isValidApiKey($api_key) {
    if (empty($api_key)) {
      return FALSE;
    }
    $this->fastlyApi->setApiKey($api_key);
    return $this->fastlyApi->validateApiKey();
  }

}
