<?php

namespace Drupal\next;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for replacing path variables with entity field values.
 */
class PathVariableReplacer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a PathVariableReplacer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->loggerFactory = $logger_factory->get('next');
  }
  /**
   * Replace variables in a path with entity field values.
   *
   * @param string $path
   *   The path pattern with variables.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity containing the field values.
   *
   * @return array
   *   An array of processed paths with replaced variables.
   */
  public function replacePath(string $path, ContentEntityInterface $entity): array {
    try {
      // Find all variables in the path.
      preg_match_all('/\{([^}]+)\}/', $path, $matches);
      
      // Get all possible values for each variable.
      $variable_values = [];
      foreach ($matches[1] as $variable) {
        $variable_values[$variable] = $this->processVariable($variable, $entity);
      }
      
      // Generate all possible combinations.
      return $this->generatePaths($path, $variable_values);
    }
    catch (\Exception $e) {
      $this->loggerFactory->error('Error processing path variables: @message', ['@message' => $e->getMessage()]);
      return [$path];
    }
  }

  /**
   * Process a single variable and return its value(s).
   *
   * @param string $variable
   *   The variable to process (e.g., "field_name" or "field_ref.field_name").
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity containing the field.
   *
   * @return array
   *   An array of possible values for this variable.
   */
  protected function processVariable(string $variable, ContentEntityInterface $entity): array {
    // Split for nested fields (e.g., field_ref.field_name).
    $parts = explode('.', $variable);
    $field_name = array_shift($parts);
    
    // Handle built-in properties.
    if ($this->isBuiltInProperty($field_name)) {
      return [$this->getBuiltInPropertyValue($field_name, $entity)];
    }

    // Check if the field exists.
    if (!$entity->hasField($field_name)) {
      $this->loggerFactory->warning('Field @field not found in entity @type @id', [
        '@field' => $field_name,
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
      ]);
      return [''];
    }

    $field = $entity->get($field_name);
    if ($field->isEmpty()) {
      return [''];
    }

    // Handle multi-value fields.
    $values = [];
    foreach ($field as $delta => $value) {
      // If we have more parts, we need to traverse the reference.
      if (!empty($parts) && $value->entity instanceof ContentEntityInterface) {
        $nested_values = $this->processVariable(implode('.', $parts), $value->entity);
        $values = array_merge($values, $nested_values);
      }
      else {
        $formatted_value = $this->formatFieldValue($value);
        if (is_array($formatted_value)) {
          $values = array_merge($values, $formatted_value);
        }
        else {
          $values[] = $formatted_value;
        }
      }
    }

    return array_values(array_unique(array_filter($values)));
  }

  /**
   * Generate all possible path combinations.
   *
   * @param string $path
   *   The original path pattern.
   * @param array $variable_values
   *   Array of variable names and their possible values.
   *
   * @return array
   *   Array of all possible path combinations.
   */
  protected function generatePaths(string $path, array $variable_values): array {
    // Start with the original path.
    $paths = [$path];
    
    // Replace each variable with all its possible values.
    foreach ($variable_values as $variable => $values) {
      $new_paths = [];
      foreach ($paths as $current_path) {
        foreach ($values as $value) {
          $new_paths[] = str_replace('{' . $variable . '}', $value, $current_path);
        }
      }
      $paths = $new_paths;
    }
    
    return array_values(array_unique($paths));
  }

  /**
   * Format a field value based on its type.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   The field item to format.
   *
   * @return string|array
   *   The formatted value(s).
   */
  protected function formatFieldValue($field_item): string|array {
    $value = $field_item->getValue();
    
    // Handle different field types.
    if (isset($value['value'])) {
      return (string) $value['value'];
    }
    
    if (isset($value['target_id']) && $field_item->entity) {
      return $field_item->entity->label();
    }
    
    if (isset($value['uri'])) {
      return $value['uri'];
    }
    
    if (isset($value['latitude']) && isset($value['longitude'])) {
      return $value['latitude'] . ',' . $value['longitude'];
    }

    // Handle array values.
    if (is_array($value) && !empty($value['value']) && is_array($value['value'])) {
      return $value['value'];
    }

    // Fallback to string representation.
    return (string) $field_item;
  }

  /**
   * Check if the field name is a built-in property.
   */
  protected function isBuiltInProperty(string $property): bool {
    return in_array($property, [
      'id',
      'uuid',
      'langcode',
      'bundle',
      'label',
    ]);
  }

  /**
   * Get the value of a built-in property.
   */
  protected function getBuiltInPropertyValue(string $property, ContentEntityInterface $entity): string {
    switch ($property) {
      case 'id':
        return $entity->id();
      case 'uuid':
        return $entity->uuid();
      case 'langcode':
        return $entity->language()->getId();
      case 'bundle':
        return $entity->bundle();
      case 'label':
        return $entity->label();
      default:
        return '';
    }
  }
}