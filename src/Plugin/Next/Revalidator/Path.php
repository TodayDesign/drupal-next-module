<?php

namespace Drupal\next\Plugin\Next\Revalidator;

use Drupal\Core\Form\FormStateInterface;
use Drupal\next\Annotation\Revalidator;
use Drupal\next\Event\EntityActionEvent;
use Drupal\next\Plugin\ConfigurableRevalidatorBase;
use Drupal\next\Plugin\RevalidatorInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a revalidator for paths.
 *
 * @Revalidator(
 *  id = "path",
 *  label = "Path",
 *  description = "Path-based on-demand revalidation."
 * )
 */
class Path extends ConfigurableRevalidatorBase implements RevalidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'revalidate_page' => NULL,
      'additional_paths' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['revalidate_page'] = [
      '#title' => $this->t('Revalidate page'),
      '#description' => $this->t('Revalidate the page for the entity on update.'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['revalidate_page'],
    ];

    $form['additional_paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional paths'),
      '#default_value' => $this->configuration['additional_paths'],
      '#description' => $this->t('Additional paths to revalidate on entity update. Enter one path per line. Example %example.', [
        '%example' => '/blog',
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['revalidate_page'] = $form_state->getValue('revalidate_page');
    $this->configuration['additional_paths'] = $form_state->getValue('additional_paths');
  }

  /**
   * {@inheritdoc}
   */
  public function revalidate(EntityActionEvent $event): bool {
    $revalidated = FALSE;

    $sites = $event->getSites();
    if (!count($sites)) {
      return FALSE;
    }

    $paths = [];
    if (!empty($this->configuration['revalidate_page'])) {
      $paths[] = [$event->getEntityUrl()];
    }

    if (!empty($this->configuration['additional_paths'])) {
      $additional_paths = array_map('trim', explode("\n", $this->configuration['additional_paths']));
      $entity = $event->getEntity();

      /** @var \Drupal\next\PathVariableReplacer $replacer */
      $replacer = \Drupal::service('next.path_variable_replacer');

      foreach ($additional_paths as $additional_path) {
        $paths[] = $replacer->replacePath($additional_path, $entity);
      }
    }

    // Flatten the array.
    $paths = array_merge(...$paths);

    // Make them unique. and all lowercase.
    $paths = array_map('strtolower', array_unique(array_map('Drupal\Component\Utility\UrlHelper::filterBadProtocol', $paths)));

    // Replace spaces with '-'.
    $paths = array_map(function ($path) {
      return str_replace(' ', '-', $path);
    }, $paths);

    // Replace any '/node/' with '/'.
    $paths = array_map(function ($path) {
      return str_replace('/node/', '/', $path);
    }, $paths);

    // Print the paths.
    $this->logger->notice('(@action): Paths: %paths', [
      '@action' => $event->getAction(),
      '%paths' => json_encode($paths),
    ]);

    if (!count($paths)) {
      return FALSE;
    }

    foreach ($paths as $path) {
      foreach ($sites as $site) {
        try {
          $revalidate_url = $site->getRevalidateUrlForPath($path);

          if (!$revalidate_url) {
            throw new \Exception('No revalidate url set.');
          }

          if ($this->nextSettingsManager->isDebug()) {
            $this->logger->notice('(@action): Revalidating path %path for the site %site. URL: %url', [
              '@action' => $event->getAction(),
              '%path' => $path,
              '%site' => $site->label(),
              '%url' => $revalidate_url->toString(),
            ]);
          }

          $response = $this->httpClient->request('GET', $revalidate_url->toString());
          if ($response && $response->getStatusCode() === Response::HTTP_OK) {
            if ($this->nextSettingsManager->isDebug()) {
              $this->logger->notice('(@action): Successfully revalidated path %path for the site %site. URL: %url', [
                '@action' => $event->getAction(),
                '%path' => $path,
                '%site' => $site->label(),
                '%url' => $revalidate_url->toString(),
              ]);
            }

            $revalidated = TRUE;
          }
        }
        catch (\Exception $exception) {
          watchdog_exception('next', $exception);
          $revalidated = FALSE;
        }
      }
    }

    return $revalidated;
  }

}
