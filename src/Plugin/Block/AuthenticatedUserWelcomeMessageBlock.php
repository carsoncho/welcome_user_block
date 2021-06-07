<?php

namespace Drupal\welcome_user_block\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an authenticated user welcome message block.
 *
 * @Block(
 *   id = "welcome_user_block_authenticated_user_welcome_message",
 *   admin_label = @Translation("Authenticated User Welcome Message"),
 *   category = @Translation("Custom")
 * )
 */
class AuthenticatedUserWelcomeMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AuthenticatedUserWelcomeMessageBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxy $current_user, DateFormatter $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($this->currentUser->isAuthenticated());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'welcome_message' => $this->t('Hello everyone!'),
      'date_format' => 'custom',
      'date_format_custom' => 'F jS, Y g:i a'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['welcome_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Welcome Message'),
      '#default_value' => $this->configuration['welcome_message'],
    ];

    // Allow admins to choose date-time format to display last logged in date in.
    $date_format_storage = $this->entityTypeManager->getStorage('date_format');
    $formats = $date_format_storage->getQuery()->execute();
    $format_objs = $date_format_storage->loadMultiple(array_keys($formats));
    $options = [];
    foreach ($format_objs as $id => $format) {
      $example = $this->dateFormatter->format(time(), $id);
      $options[$id] = "{$format->label()} - $example";
    }
    $options['custom'] = 'Custom';
    $date_format_collection_url = Url::fromRoute('entity.date_format.collection');
    $form['date_format'] = [
      '#title' => $this->t('Date Format'),
      '#description' => $this->t('Select a date format to display the user\'s last logged in date in. You can manage the stored date and time formats <a href=:url>here</a>.', [':url' => $date_format_collection_url->toString()]),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->configuration['date_format'],
      '#required' => TRUE,
    ];

    // Mimic how the 'Format' field in the DateFormatFormBase.php works.
    $form['date_format_custom'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Date-Time Format'),
      '#description' => $this->t('A user-defined date format. See the <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters">PHP manual</a> for available options.'),
      '#default_value' => $this->configuration['date_format_custom'],
      '#attributes' => [
        'data-drupal-date-formatter' => 'source',
      ],
      '#field_suffix' => ' <small class="js-hide" data-drupal-date-formatter="preview">' . $this->t('Displayed as %date_format', ['%date_format' => '']) . '</small>',
      '#states' => [
        'required' => [
          ':input[name="settings[date_format]"]' => ['value' => 'custom']
        ],
        'visible' => [
          ':input[name="settings[date_format]"]' => ['value' => 'custom']
        ],
      ],
    ];
    $form['#attached']['drupalSettings']['dateFormats'] = $this->dateFormatter->getSampleDateFormats();
    $form['#attached']['library'][] = 'system/drupal.system.date';
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    /*
     * In case there was a value in custom text field then user chose not to use
     * 'custom' we wipe out the field value before it's saved.
     */
    if ($form_state->getValue('date_format') !== 'custom') {
      $form_state->setValue('date_format_custom', '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['welcome_message'] = $form_state->getValue('welcome_message');
    $this->configuration['date_format'] = $form_state->getValue('date_format');
    $this->configuration['date_format_custom'] = $form_state->getValue('date_format_custom');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user_name = $this->currentUser->getAccountName();
    $last_login_date =  $this->currentUser->getLastAccessedTime();
    $chosen_format = $this->configuration['date_format'];
    $custom_format = $this->configuration['date_format'] == 'custom' ? $this->configuration['date_format_custom'] : '';
    $formatted_date = $this->dateFormatter->format($last_login_date, $chosen_format, $custom_format);
    $link = Link::createFromRoute(
      $this->t('Visit your profile'),
      'entity.user.canonical',
      ['user' => $this->currentUser->id()]
    );

    $link->toRenderable();

    $build['content'] = [
      '#theme' => 'welcome_message',
      '#welcome_message' => $this->configuration['welcome_message'],
      '#username' => $user_name,
      '#last_login_date' => $formatted_date,
      '#link' => $link->toRenderable(),
    ];

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
