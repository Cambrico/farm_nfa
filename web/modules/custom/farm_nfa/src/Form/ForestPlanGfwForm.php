<?php

namespace Drupal\farm_nfa\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\farm_nfa\Service\GfwApiService;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Forest plan gfw form.
 *
 * @ingroup farm_nfa
 */
class ForestPlanGfwForm extends FormBase {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The GFW API service.
   *
   * @var \Drupal\farm_nfa\Api\GfwApiService
   */
  protected $gfwApiService;

  /**
   * Constructs a new ForestPlanGfwForm.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   * @param \Drupal\farm_nfa\Service\GfwApiService $gfw_api_service
   *   The GFW API service.
   */
  public function __construct(RouteMatchInterface $routeMatch, Request $request, KeyRepositoryInterface $keyRepository, GfwApiService $gfw_api_service) {
    $this->routeMatch = $routeMatch;
    $this->request = $request;
    $this->keyRepository = $keyRepository;
    $this->gfwApiService = $gfw_api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('key.repository'),
      $container->get('farm_nfa.gfw_api_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_nfa_forest_budget_plan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set the form title.
    $form['#title'] = $this->t('GFW');
    $asset = $this->routeMatch->getParameter('asset');
    $assetType = '';
    $landType = '';
    if ($asset) {
      $assetType = $asset->bundle();
    }
    if ($asset && $asset->hasField('land_type') && !$asset->get('land_type')->isEmpty()) {
      $landType = $asset->get('land_type')->value;
    }
    $gfw_api_user = $this->keyRepository->getKey('gfw_api_user');
    $gfw_api_password = $this->keyRepository->getKey('gfw_api_password');
    $gfw_api_user = $gfw_api_user ? $gfw_api_user->getKeyValue() : '';
    $gfw_api_password = $gfw_api_password ? $gfw_api_password->getKeyValue() : '';
    $gfw_api_key = $this->gfwApiService->generateGfwApiKey('https://data-api.globalforestwatch.org', [
      'username' => $gfw_api_user,
      'password' => $gfw_api_password,
    ]);
    $form['gfw_map'] = [
      '#type' => 'farm_map',
      '#map_type' => 'farm_nfa_plan_locations',
      '#map_settings' => [
        'plan' => $this->routeMatch->getRawParameter('plan'),
        'asset' => $this->routeMatch->getRawParameter('asset'),
        'host' => $this->request->getHost(),
        'asset_type' => $assetType,
        'land_type' => $landType,
        'gfw_api_key' => $gfw_api_key,
      ],
      '#attached' => [
        'library' => [
          'farm_nfa/behavior_farm_nfa_gfw_layers',
        ],
      ],
    ];

    $form['range'] = [
      '#type' => 'daterangepicker',
      '#prefix' => '<div class="daterange-picker"><div class="field__label">' . $this->t('GFW alerts date range') . '</div>',
      '#suffix' => '</div>',
      '#DateRangePickerOptions' => [
        'initial_text' => $this->t('Select date range...'),
        'apply_button_text' => $this->t('Apply'),
        'clear_button_text' => $this->t('Clear'),
        'cancel_button_text' => $this->t('Cancel'),
        'range_splitter' => ' - ',
        'date_format' => 'd M, yy',
        // This needs to be a format recognised by javascript Date.parse method.
        'alt_format' => 'yy-mm-dd',
        'date_picker_options' => [
          'number_of_months' => 2,
        ],
      ],
    ];

    $form['datepicker_help'] = [
      '#type' => 'markup',
      '#markup' => t('Click to select the date range'),
      '#prefix' => '<div class="daterange-picker-help">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
