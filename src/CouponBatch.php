<?php

namespace Drupal\commerce_coupon_batch;

use Drupal\Component\Utility\Random;
use Drupal\Core\Url;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;

/**
 * Provide Batch Processing for Commerce Coupon Batch
 *
 * Class CouponBatch
 * @package Drupal\commerce_coupon_batch\Form
 */
class CouponBatch {

  /**
   * Run batch processes
   *
   * @param Coupon $template_coupon
   * @param array $params
   * @param $context
   */
  public static function batchCreateProcess($template_coupon, $params, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['max'] = $params['n'];
      $context['sandbox']['progress'] = 0;
    }

    $sandbox =& $context['sandbox'];
    $max = (int) $sandbox['max'];
    $progress =& $sandbox['progress'];
    $remaining = $max - $progress;

    // Create coupons until the multipass batch size is reached.
    // Or we run out of coupons to create.
    $counter = 0;
    // @TODO how about CouponBatchFormInterface constants?
    $limit = $remaining < 50 ? $remaining : 50;

    // @TODO looking at batch.inc, how much of this is necessary now
    while ($counter < $limit) {
      $context['message'] = t('Creating coupon @n of @max', array('@n' => $progress, '@max' => $max));

      /** @var Coupon $coupon */
      $coupon = $template_coupon->createDuplicate();
      // @TODO need to add a field to coupon for bulk.
      // $coupon->bulk = TRUE;
      // @TODO verify coupon code is unique?
      $coupon->code = $params['code'] . (new Random())->name($params['length']);
      $coupon->status = $params['status'];
      $coupon->usage_limit = $params['usage_limit'];
      $coupon->save();

      $context['results'][] = $coupon->code;

      // Increment the counter.
      $counter++;
      $progress++;
    }

    // Update progress.
    if ($progress != $max) {
      $context['finished'] = $progress / $max;
    }
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One coupon created.', '@count coupons created.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }

    drupal_set_message($message);
  }
}