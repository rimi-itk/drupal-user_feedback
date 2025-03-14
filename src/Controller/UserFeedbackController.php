<?php

declare(strict_types=1);

namespace Drupal\user_feedback\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User feedback controller.
 */
final class UserFeedbackController extends ControllerBase {
  use AutowireTrait;

  public function __construct(
    #[Autowire('logger.channel.user_feedback')]
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Builds the response.
   */
  public function send(Request $request): Response {
    $data = [
      'created_at' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
      'data' => $request->request->all(),
      'files' => $request->files->count(),
    ];

    /**
     * @var string $key
     * @var ?\Symfony\Component\HttpFoundation\File\UploadedFile $file
     */
    foreach ($request->files as $key => $file) {
      if (!$file) {
        continue;
      }
      $file->move(__DIR__, uniqid(__FUNCTION__, TRUE) . '-' . $key . '-' . $file->getClientOriginalName());
    }

    $this->logger->info('@data', ['@data' => json_encode($data)]);

    file_put_contents(
      __DIR__ . '/feedback.json',
      json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL,
      FILE_APPEND
    );

    return new JsonResponse(['success' => TRUE],
      status: Response::HTTP_CREATED);
  }

}
