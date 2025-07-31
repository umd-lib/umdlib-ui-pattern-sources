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
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Render\Markup;
use DateTimeImmutable;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'date',
  label: new TranslatableMarkup('Date'),
  description: new TranslatableMarkup('Date field supporting PHP formats'),
  prop_types: ['slot', 'string']
)]
class DateSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'date' => "",
      'format' => "F d, Y",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $date = $this->getSetting('date') ?? null;
    $format = $this->getSetting('format') ?? "F d, Y";
    if (empty($date)) {
      return null;
    }
    $isSlot = ($this->propDefinition["ui_patterns"]["type_definition"]->getPluginId() === "slot");

    // return $isSlot ? [] : "";

    if (empty($date) || !is_scalar($date)) {
      return $isSlot ? [] : "";
    }
    $dt = new DateTimeImmutable($date);
    $formatted_date = $dt->format($format);
    if ($isSlot) {
      $bubbleable_metadata = new BubbleableMetadata();
      $build = [
        "#markup" => Markup::create($formatted_date),
      ];
      $bubbleable_metadata->applyTo($build);
      return $build;
    }
    return Html::escape($formatted_date);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $formats = [
      'F j, Y' => $this->t("March 10, 2001"),
      'Y-m-d' => $this->t("2001-03-10"),
      'Y' => $this->t("2001"),
      'F Y' => $this->t("March 2001"),
      'l, F j' => $this->t("Saturday, March 10")
    ];

    $form['date'] = [
      '#type' => 'date',
      '#default_value' => $this->getSetting('date'),
    ];
    $form['format'] = [
      '#type' => 'select',
      '#options' => $formats,
      '#default_value' => $this->getSetting('format'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    if (empty($this->getSetting('date'))) {
      return [];
    }
    return [
      $this->getSetting('date'),
    ];
  }
}
