<?php

declare(strict_types=1);

namespace Drupal\umdlib_ui_pattern_sources\Plugin\UiPatterns\Source;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ui_patterns\PropTypePluginManager;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'search_facet',
  label: new TranslatableMarkup('Facet'),
  description: new TranslatableMarkup('Compatible facet for given endpoint'),
  prop_types: ['string']
)]
class FacetSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entity_type_manager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $module_handler;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ui_patterns_prop_type'),
      $container->get('context.repository'),
      $container->get('current_route_match'),
      $container->get('ui_patterns.sample_entity_generator'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Constructs a \ui_patterns\SourcePluginBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PropTypePluginManager $propTypeManager,
    protected ContextRepositoryInterface $contextRepository,
    protected RouteMatchInterface $routeMatch,
    protected SampleEntityGeneratorInterface $sampleEntityGenerator,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityTypeManagerInterface $entityManager
  ) {
    parent::__construct($configuration,
      $plugin_id,
      $plugin_definition,
      $propTypeManager,
      $contextRepository,
      $routeMatch,
      $sampleEntityGenerator,
      $moduleHandler);
    $this->entity_type_manager = $entityManager;
    $this->module_handler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'search_endpoint' => '',
      'search_facet' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $facet = $this->getSetting('settings')['search_facet'] ?? null;
    if (empty($facet)) {
      return null;
    }
    return $facet;
  }

  /**
   * {@inheritdoc}
   * 
   * @see Drupal\search_web_components_block\Plugin\Block
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    if (!$this->module_handler->moduleExists('facets') || !$this->module_handler->moduleExists('search_api_decoupled')) {
      $this->messenger()->addWarning($this->t(
        'Facets and Search API Decoupled are required modules.',
      ));
      $form['message'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];

      return $form;
    }

    $endpoints = $this->entity_type_manager->getStorage('search_api_endpoint')->loadMultiple();

    $endpoint_options = [];
    foreach ($endpoints as $endpoint) {
      $endpoint_options[$endpoint->id()] = $endpoint->label();
    }

    if (empty($endpoint_options)) {
      $link = Url::fromRoute('entity.search_api_endpoint.collection')->toString();
      $this->messenger()->addWarning($this->t(
        'No Decoupled Search Endpoints configured, you can create one <a href=":url" target="_blank">here</a>.',
        [':url' => $link])
      );
      $form['message'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];

      return $form;
    }

    $form['search_endpoint'] = [
      '#type' => 'select',
      '#options' => $endpoint_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'buildAjaxSettingsForm'],
        'wrapper' => 'umd-settings-container',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#default_value' => $this->getSetting('search_endpoint') ?? array_key_first($endpoint_options),
    ];

    $form['settings'] = [
      '#type' => 'container',
      '#id' => 'umd-settings-container',
    ];

    $complete_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;

    $endpointId = $complete_form_state->getValue(['settings', 'search_endpoint'], $this->getSetting['search_endpoint'] ?? array_key_first($endpoint_options));
    if ($endpointId !== 'manual_entry') {
      $endpoint = $this->entity_type_manager->getStorage('search_api_endpoint')->load($endpointId);
      if ($endpoint) {
        $facets = $endpoint->getFacets();
        $facet_options = [];
        foreach ($facets as $option) {
          $facet_options[$option->id()] = $option->getName();
        }

        if (!$facet_options) {
          if (!$this->module_handler->moduleExists('facets')) {
            $this->messenger()->addWarning($this->t(
              'The facets module is not enabled.',
            ));
          }
          else {
            $link = Url::fromRoute('entity.facets_facet.collection')->toString();
            $this->messenger()->addWarning($this->t(
              'No @type facets are configured, you can create some <a href=":url" target="_blank">here</a>.',
              ['@type' => implode(' ,', $this->supportedWidgets()), ':url' => $link])
            );
          }
          $form['message'] = [
            '#type' => 'status_messages',
            '#weight' => -10,
          ];
        }

        $facet_value = $complete_form_state->getValue(['settings', 'search_facet'], $this->setting['search_facet'] ?? array_key_first($facet_options));
        $facet_value = isset($facet_options[$facet_value]) ? $facet_value : NULL;
        $form['settings']['search_facet'] = [
          '#title' => $this->t('Facet'),
          '#type' => 'select',
          '#required' => TRUE,
          '#default_value' => $this->getSetting('search_facet') ?? $facet_value,
          '#options' => $facet_options,
        ];
      } else {
        $this->getLogger('search_web_components_block')->error(
          'Failed to load Decoupled Search Endpoint @id for block @block',
          ['@id' => $this->setting['endpoint'], '@block' => $this->getPluginId()]
        );
      }
    } else {
      $form['settings']['key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Facet to display'),
        '#description' => $this->t('Machine name of the facet to display'),
        '#default_value' => $this->setting['key'],
      ];
      $form['settings'] = array_merge($form['settings'], $this->formElements());
    }

    return $form;
  }

  /**
   * Handles changes to the settings when the source changes.
   */
  public static function buildAjaxSettingsForm(array $form, FormStateInterface $form_state) {
    return $form['settings']['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    if (empty($this->getSetting('search_facet'))) {
      return [];
    }
    return [
      $this->getSetting('search_facet'),
    ];
  }
}
