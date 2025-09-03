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

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'long_text_string',
  label: new TranslatableMarkup('Text String'),
  description: new TranslatableMarkup('For non-WYSIWYG plain text strings.'),
  prop_types: ['slot', 'string']
)]
class LongTextString extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'long_text_string' => "",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $long_text_string = $this->getSetting('long_text_string') ?? null;
    if (empty($long_text_string)) {
      return null;
    }
    $isSlot = ($this->propDefinition["ui_patterns"]["type_definition"]->getPluginId() === "slot");


    if (empty($long_text_string) || !is_scalar($long_text_string)) {
      return $isSlot ? [] : "";
    }
    if ($isSlot) {
      $bubbleable_metadata = new BubbleableMetadata();
      $build = [
        "#markup" => Markup::create($long_text_string),
      ];
      $bubbleable_metadata->applyTo($build);
      return $build;
    }
    return Html::escape($long_text_string);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['long_text_string'] = [
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('long_text_string'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    if (empty($this->getSetting('long_text_string'))) {
      return [];
    }
    return [
      $this->getSetting('long_text_string'),
    ];
  }
}
