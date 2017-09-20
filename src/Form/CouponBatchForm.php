<?php

namespace Drupal\commerce_coupon_batch\Form;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class CouponBatchForm.
 */
class CouponBatchForm extends FormBase {

  /**
   * The coupon storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $couponStorage;

  /**
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRoute;

  /**
   * Constructs a new CouponBatchForm object.
   *
   * @param $entity_type_manager
   */
  public function __construct(EntityTypeManager $entity_type_manager, CurrentRouteMatch $currentRouteMatch) {
    $this->couponStorage = $entity_type_manager->getStorage('commerce_promotion_coupon');
    $this->currentRoute = $currentRouteMatch;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $coupon = $this->couponStorage->create([
        'code' => '',
        'status' => 1
      ]);
    $display = \Drupal\Core\Entity\Entity\EntityFormDisplay::collectRenderDisplay($coupon, 'default');
    $display->buildForm($coupon, $form, $form_state);

    $form['code_configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Coupon parameters'),
      '#weight' => -100
    ];
    $form['code']['widget']['#description'] = $this->t('Will be used as a prefix for the generated code');

    // @todo Should this be a multi-value field? Maybe create a commerce issue
    $form['code_configuration']['code'] = $form['code'];

    unset($form['code']);
    // @todo this nested setting can't be right
    $form['code_configuration']['code']['widget'][0]['value']['#description'] = $this->t('Will be used as a prefix for the generated code');
    // @todo file bug for commerce_promotion - shouldn't this be required?
    $form['code_configuration']['code']['widget'][0]['value']['#required'] = TRUE;

    //  For now just use the code and add a generated piece
//    $form['code_configuration']['prefix'] = [
//      '#type' => 'textfield',
//      '#title' => $this->t('Prefix'),
//      '#description' => $this->t('Prefix for generated coupon codes.'),
//      '#maxlength' => 10,
//      '#size' => 64,
//    ];
//    $form['code_configuration']['suffix'] = [
//      '#type' => 'textfield',
//      '#title' => $this->t('Suffix'),
//      '#description' => $this->t('Suffix for generated coupon codes.'),
//      '#maxlength' => 10,
//      '#size' => 10,
//    ];

    $form['code_configuration']['length'] = [
      '#type' => 'number',
      '#title' => $this->t('Length'),
      '#description' => $this->t('Length for dynamic part.'),
      '#maxlength' => 10,
      '#size' => 10,
      '#required' => TRUE,
    ];
    $form['code_configuration']['number_of_codes'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of codes to generate'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => 100,
    ];



    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.commerce_promotion_coupon.collection', ['commerce_promotion' => $this->currentRoute->getParameter('commerce_promotion')]);
    $usage_limit = $form_state->getValue(['usage_limit', 0]);
    $params = [
      'code' => $form_state->getValue(['code', 0, 'value']),
      'length' => $form_state->getValue('length'),
      'usage_limit' => (bool) $usage_limit['limit'] ? $usage_limit['usage_limit'] : 0,
      'status' => $form_state->getValue('status'),
      'n' => $form_state->getValue('number_of_codes'),
    ];
    $template_coupon = $this->getTemplateCoupon($form_state->getValues());


    $batch = [
      'operations' => [
        [
          ['\Drupal\commerce_coupon_batch\CouponBatch', 'batchCreateProcess'],
          [$template_coupon, $params]
        ]
      ],
      'finished' => ['\Drupal\commerce_coupon_batch\CouponBatch', 'batchFinished'],
      'progress_message' => $this->t('Creating coupons ...')
    ];

    batch_set($batch);
  }

  /**
   * Create a template of a Coupon
   *
   * @param $values
   *  Values from a form submission
   *
   * @return Coupon
   */
  protected function getTemplateCoupon($values) {
    // @todo is this the best way to get the Promotion?
    $promotion_id = $this->currentRoute->getParameter('commerce_promotion');
    // @todo we need some validation
    // @todo we need some access checks.  See CouponAccessControlHandler
    return $this->couponStorage->create([
      'code' => '',
      'uid' => $this->currentUser()->id(),
      'status' => 0,
      'usage_limit' => (bool) $values['usage_limit'][0]['limit'] ? $values['usage_limit'][0]['usage_limit'] : 0,
      'promotion_id' => [
        'target_id' => $promotion_id
      ]
    ]);
  }
}
