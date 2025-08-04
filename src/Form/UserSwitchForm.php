<?php

namespace Drupal\user_switcher\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to switch to another user account.
 */
class UserSwitchForm extends FormBase {

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * {@inheritdoc}
   */
  public function __construct(CsrfTokenGenerator $csrf_token) {
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('csrf_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'user_switcher_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User'),
      '#size' => 60,
      '#autocomplete_route_name' => 'user_switcher.autocomplete',
      '#required' => TRUE,
      '#description' => $this->t('Begin typing a username, then select from the list.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Switch'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $name = $form_state->getValue('user');
    if ($account = user_load_by_name($name)) {
      $form_state->setValue('target_uid', $account->id());
    }
    else {
      $form_state->setErrorByName('user', $this->t('The specified user does not exist.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->getValue('target_uid');
    $token = $this->csrfToken->get('user_switch_' . $uid);

    $url = Url::fromRoute('user_switcher.switch', ['user' => $uid], ['query' => ['token' => $token]]);
    $form_state->setRedirectUrl($url);
  }

}
