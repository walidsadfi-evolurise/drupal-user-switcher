<?php

namespace Drupal\user_switcher\Controller;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles switching to another user and restoring the original session.
 */
class SwitchController extends ControllerBase {

  /**
   * Session key for the original UID.
   */
  // En haut de la classe :
  public const SESSION_STACK = 'user_switcher_stack';
  public const SESSION_KEY = 'user_switcher_original_uid'; //WSA - used for 1.0.0 no more from 1.0.1 

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(CsrfTokenGenerator $csrf_token, RequestStack $request_stack) {
    $this->csrfToken = $csrf_token;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('csrf_token'),
      $container->get('request_stack')
    );
  }

  /**
   * Switches to the selected user account.
   */
  public function switch(User $user) {
    $current = $this->currentUser();

    // Permission and UID 1 protection.
    if (!$current->hasPermission('switch user accounts')) {
      throw new AccessDeniedHttpException();
    }
    if ($user->id() == 1 && $current->id() != 1) {
      throw new AccessDeniedHttpException();
    }

    // $token = $this->requestStack->getCurrentRequest()->query->get('token');
    // if (!$this->csrfToken->validate($token, 'user_switch_' . $user->id())) {
    //   throw new AccessDeniedHttpException();
    // }

    $session = $this->requestStack->getCurrentRequest()->getSession();
    $stack = $session->get(self::SESSION_STACK, []);
    $stack[] = $current->id();            
    $session->set(self::SESSION_STACK, $stack);


    user_login_finalize($user);

    \Drupal::service('page_cache_kill_switch')->trigger();
    return new TrustedRedirectResponse(Url::fromRoute('<front>')->toString());
  }

  /**
   * Restores the original user account.
   */
  public function restore() {
    // $current = $this->currentUser();

    // if (!$current->hasPermission('switch user accounts')) {
    //   throw new AccessDeniedHttpException();
    // }

    // $token = $this->requestStack->getCurrentRequest()->query->get('token');
    // if (!$this->csrfToken->validate($token, 'user_switch_restore')) {
    //   throw new AccessDeniedHttpException();
    // }

    $session = $this->requestStack->getCurrentRequest()->getSession();
    $stack = $session->get(self::SESSION_STACK, []);

    if (!empty($stack)) {
      // Dépile le dernier UID enregistré.
      $original_uid = array_pop($stack);

      // Met à jour ou nettoie la session.
      if (empty($stack)) {
        $session->remove(self::SESSION_STACK);
      }
      else {
        $session->set(self::SESSION_STACK, $stack);
      }

      if ($original = User::load($original_uid)) {
        user_login_finalize($original);
      \Drupal::service('page_cache_kill_switch')->trigger();
      }
    }

    return new TrustedRedirectResponse(Url::fromRoute('<front>')->toString());
  }


  /**
   * Provides user autocomplete suggestions.
   */
  public function autocomplete(Request $request) {
    $string = $request->query->get('q');
    $matches = [];

    if ($string !== NULL && $string !== '') {
      $uid_query = \Drupal::entityTypeManager()->getStorage('user')->getQuery()->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('name', $string, 'CONTAINS')
        ->range(0, 10)
        ->execute();
      $accounts = User::loadMultiple($uid_query);
      foreach ($accounts as $account) {
        $matches[] = [
          'value' => $account->getAccountName(),
          'label' => $account->getAccountName() . ' (' . $account->id() . ')',
        ];
      }
    }

    return new JsonResponse($matches);
  }

}
