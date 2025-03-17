<?php

namespace Drupal\user_feedback\EventListener;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * User feedback listener.
 */
final class UserFeedbackListener implements EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly AccountInterface $currentUser,
    private readonly ExtensionPathResolver $extensionPathResolver,
    private readonly TimeInterface $time,
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', -128],
    ];
  }

  /**
   * KernelEvents response handler.
   *
   * Much of this is lifted from onKernelResponse in https://git.drupalcode.org/project/webprofiler/-/blob/11.0.x/src/EventListener/ToolbarListener.php.
   */
  public function onKernelResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $request = $event->getRequest();

    if (!$event->isMainRequest()
      || $request->isXmlHttpRequest()
      || $response->isRedirection()
      || ($response->headers->has('Content-Type')
        && !\str_contains((string) $response->headers->get('Content-Type'), 'html'))
      || 'html' !== $request->getRequestFormat()
      || FALSE !== \stripos((string) $response->headers->get('Content-Disposition', ''), 'attachment;')
    ) {
      return;
    }

    if ($this->shouldInjectWidget($request)) {
      $this->injectWidget($response, $request);
    }
  }

  /**
   * Check if widget should be injected into response.
   */
  private function shouldInjectWidget(Request $request): bool {
    // @todo Check some configuration.
    return $this->currentUser->hasPermission('send user feedback');
  }

  /**
   * Inject widget.
   */
  private function injectWidget(Response $response, Request $request): void {
    $content = $response->getContent();
    if (FALSE === $content) {
      return;
    }

    $pos = \strripos($content, '</body>');

    if (FALSE !== $pos) {

      $assetsPath = $this->extensionPathResolver->getPath('module', 'user_feedback') . '/build';

      $assetsBaseUrl = Url::fromUserInput('/' . $assetsPath)->toString(TRUE)->getGeneratedUrl();
      $build = [
        '#theme' => 'user_feedback_widget',
        '#request' => $request,
        '#wrapper_scripts' => [
          // '<script src="' . $assetsBaseUrl . '/widget_wrapper.js"></script>',
        ],
        '#wrapper_stylesheets' => [
          '<link rel="stylesheet" href="' . $assetsBaseUrl . '/widget_wrapper.css"></script>',
        ],
        '#widget_scripts' => [
          '<script src="' . $assetsBaseUrl . '/widget.js"></script>',
        ],
        '#widget_stylesheets' => [
          '<link rel="stylesheet" href="' . $assetsBaseUrl . '/widget.css"></script>',
        ],
        '#config' => (object) [
          'path' => $this->urlGenerator->generate('user_feedback.send'),
          'request' => [
            'query' => $request->query->all(),
            'path' => $request->getPathInfo(),
            'requested_at' => (new \DateTimeImmutable())->setTimestamp($this->time->getRequestTime())->format(\DateTimeInterface::ATOM),
          ],
        ],
      ];

      $widget = $this->renderer->renderRoot($build);
      $content = \substr($content, 0, $pos) . $widget . \substr($content, $pos);
      $response->setContent($content);
    }
  }

  /**
   * Implements hook_theme().
   */
  public function theme(array $existing, string $type, string $theme, string $path): array {
    return [
      'user_feedback_widget' => [
        'template' => 'user_feedback/user_feedback_widget',
        'variables' => [
          'request' => NULL,
          'wrapper_scripts' => [],
          'wrapper_stylesheets' => [],
          'widget_scripts' => [],
          'widget_stylesheets' => [],
          'config' => [],
        ],
      ],
    ];
  }

}
