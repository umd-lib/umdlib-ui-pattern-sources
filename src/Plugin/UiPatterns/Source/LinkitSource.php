<?php

declare(strict_types=1);

namespace Drupal\umdlib_ui_pattern_sources\Plugin\UiPatterns\Source;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\linkit\Utility\LinkitHelper;
use Drupal\linkit\SubstitutionManagerInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'linkit_link',
  label: new TranslatableMarkup('Dynamic Link'),
  description: new TranslatableMarkup('For autosuggesting links using Linkit.'),
  prop_types: ['slot', 'string', 'url']
)]
class LinkitSource extends SourcePluginBase {

  /**
   * The substitution manager.
   *
   * @var \Drupal\linkit\SubstitutionManagerInterface
   */
  protected $substitutionManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->substitutionManager = $container->get('plugin.manager.linkit.substitution');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'value' => "",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $value = $this->getSetting('value') ?? null;
    if (empty($value)) {
      return null;
    }
    $isSlot = ($this->propDefinition["ui_patterns"]["type_definition"]->getPluginId() === "slot");

    if (empty($value) || !is_scalar($value)) {
      return $isSlot ? [] : "";
    }
    $url = $value;
    // Check if URL is absolute
    if (!UrlHelper::isValid($url, TRUE)) {
      $entity = LinkitHelper::getEntityFromUserInput($value);
      // Check if internal link
      if ($entity) {
        $substitution_type = SubstitutionManagerInterface::DEFAULT_SUBSTITUTION;
        $url_obj = $this->substitutionManager->createInstance($substitution_type)->getUrl($entity);
        $url = $url_obj->toString();
      }
      // Final check to verify is valid URL
      if (!UrlHelper::isValid($url)) {
        return null;
      }
    }
    if ($isSlot) {
      $bubbleable_metadata = new BubbleableMetadata();
      $build = [
        "#markup" => Markup::create($url),
      ];
      $bubbleable_metadata->applyTo($build);
      return $build;
    }
    return Html::escape($url);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $prop_def = $this->propDefinition;
    $form = parent::settingsForm($form, $form_state);

    $form['value'] = [
      '#type' => 'linkit',
      '#title' => !empty($prop_def['title']) ? $prop_def['title'] : $this->t('Select a Link'),
      '#description' => $this->t('Start typing to find content and select a link.'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => ['linkit_profile_id' => 'default'],
      '#default_value' => $this->getSetting('value'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    if (empty($this->getSetting('value'))) {
      return [];
    }
    return [
      $this->getSetting('value'),
    ];
  }

    /**
   * {@inheritdoc}
   */
  public function calculateDependencies() : array {
    $dependencies = parent::calculateDependencies();
    if ($this->moduleHandler->moduleExists('linkit')) {
      static::mergeConfigDependencies($dependencies, ["module" => ["linkit"]]);
    }
    return $dependencies;
  }
}
