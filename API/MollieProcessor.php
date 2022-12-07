<?php

namespace MollieForPaymattic\API;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Services\PlaceholderParser;
use WPPayForm\App\Services\ConfirmationHelper;
use WPPayForm\App\Models\SubmissionActivity;

require_once MOLLIE_FOR_PAYMATTIC_DIR. '/Settings/MollieElement.php';
require_once MOLLIE_FOR_PAYMATTIC_DIR. '/Settings/MollieSettings.php';
require_once MOLLIE_FOR_PAYMATTIC_DIR. '/API/IPN.php';



class MollieProcessor
{
    public $method = 'mollie';

    protected $form;

    public function init()
    {
        new  \MollieForPaymattic\Settings\MollieElement();
        (new  \MollieForPaymattic\Settings\MollieSettings())->init();

        add_filter('wppayform/choose_payment_method_for_submission', array($this, 'choosePaymentMethod'), 10, 4);
        add_action('wppayform/form_submission_make_payment_mollie', array($this, 'makeFormPayment'), 10, 6);

        add_action('wppayform_payment_frameless_' . $this->method, array($this, 'handleSessionRedirectBack'));

        add_action('wpf_ipn_endpoint_' . $this->method, function () {
            (new IPN())->verifyIPN();
            exit(200);
        });
        add_filter('wppayform/entry_transactions_' . $this->method, array($this, 'addTransactionUrl'), 10, 2);
        add_action('wppayform_ipn_mollie_action_paid', array($this, 'handlePaid'), 10, 2);
        add_action('wppayform_ipn_mollie_action_refunded', array($this, 'handleRefund'), 10, 3);
        add_filter('wppayform/submitted_payment_items_' . $this->method, array($this, 'validateSubscription'), 10, 4);
    }



    protected function getPaymentMode($formId = false)
    {
        $isLive = (new \MollieForPaymattic\Settings\MollieSettings())->isLive($formId);

        if ($isLive) {
            return 'live';
        }
        return 'test';
    }

    public function addTransactionUrl($transactions, $submissionId)
    {
        foreach ($transactions as $transaction) {
            if ($transaction->payment_method == 'mollie' && $transaction->charge_id) {
                $transactionUrl = Arr::get(unserialize($transaction->payment_note), '_links.dashboard.href');
                $transaction->transaction_url =  $transactionUrl;
            }
        }
        return $transactions;
    }

    public function choosePaymentMethod($paymentMethod, $elements, $formId, $form_data)
    {
        if ($paymentMethod) {
            // Already someone choose that it's their payment method
            return $paymentMethod;
        }
        // Now We have to analyze the elements and return our payment method
        foreach ($elements as $element) {
            if ((isset($element['type']) && $element['type'] == 'mollie_gateway_element')) {
                return 'mollie';
            }
        }
        return $paymentMethod;
    }

    public function makeFormPayment($transactionId, $submissionId, $form_data, $form, $hasSubscriptions)
    {
        $paymentMode = $this->getPaymentMode();

        $transactionModel = new Transaction();
        if ($transactionId) {
            $transactionModel->updateTransaction($transactionId, array(
                'payment_mode' => $paymentMode
            ));
        }
        $transaction = $transactionModel->getTransaction($transactionId);

        $submission = (new Submission())->getSubmission($submissionId);
        $this->handleRedirect($transaction, $submission, $form, $paymentMode);
    }

    private function getSuccessURL($form, $submission)
    {
        // Check If the form settings have success URL
        $confirmation = Form::getConfirmationSettings($form->ID);
        $confirmation = ConfirmationHelper::parseConfirmation($confirmation, $submission);
        if (
            ($confirmation['redirectTo'] == 'customUrl' && $confirmation['customUrl']) ||
            ($confirmation['redirectTo'] == 'customPage' && $confirmation['customPage'])
        ) {
            if ($confirmation['redirectTo'] == 'customUrl') {
                $url = $confirmation['customUrl'];
            } else {
                $url = get_permalink(intval($confirmation['customPage']));
            }
            $url = add_query_arg(array(
                'payment_method' => 'mollie'
            ), $url);
            return PlaceholderParser::parse($url, $submission);
        }
        // now we have to check for global Success Page
        $globalSettings = get_option('wppayform_confirmation_pages');
        if (isset($globalSettings['confirmation']) && $globalSettings['confirmation']) {
            return add_query_arg(array(
                'wpf_submission' => $submission->submission_hash,
                'payment_method' => 'mollie'
            ), get_permalink(intval($globalSettings['confirmation'])));
        }
        // In case we don't have global settings
        return add_query_arg(array(
            'wpf_submission' => $submission->submission_hash,
            'payment_method' => 'mollie'
        ), home_url());
    }

    public function handleRedirect($transaction, $submission, $form, $methodSettings)
    {
        $successUrl = $this->getSuccessURL($form, $submission);

        $listener_url = add_query_arg(array(
            'wpf_payment_api_notify' => 1,
            'payment_method'         => $this->method,
            'submission_id'          => $submission->id,
            'transaction_hash'       => $transaction->transaction_hash,
        ), home_url('index.php'));

        $paymentArgs = array(
            'amount' => [
                'currency' => $submission->currency,
                'value' => number_format((float) $transaction->payment_total / 100, 2, '.', '')
            ],
            'description' => $form->post_title,
            'redirectUrl' => $successUrl,
            'webhookUrl' => $listener_url,
            'metadata' => json_encode([
                'form_id' => $form->ID,
                'submission_id' => $submission->id
            ]),
            'sequenceType' => 'oneoff'
        );

        $paymentArgs = apply_filters('wppayform_mollie_payment_args', $paymentArgs, $submission, $transaction, $form);
        $paymentIntent = (new IPN())->makeApiCall('payments', $paymentArgs, $form->ID, 'POST');

        if (is_wp_error($paymentIntent)) {
            do_action('wppayform_log_data', [
                'form_id' => $submission->form_id,
                'submission_id'        => $submission->id,
                'type' => 'activity',
                'created_by' => 'Paymattic BOT',
                'title' => 'Mollie Payment Redirect Error',
                'content' => $paymentIntent->get_error_message()
            ]);

            wp_send_json_success([
                'message'      => $paymentIntent->get_error_message()
            ], 423);
        }

        do_action('wppayform_log_data', [
            'form_id' => $form->ID,
            'submission_id' => $submission->id,
            'type' => 'activity',
            'created_by' => 'Paymattic BOT',
            'title' => 'Mollie Payment Redirect',
            'content' => 'User redirect to Mollie for completing the payment'
        ]);

        wp_send_json_success([
            // 'nextAction' => 'payment',
            'call_next_method' => 'normalRedirect',
            'redirect_url' => $paymentIntent['_links']['checkout']['href'],
            'message'      => __('You are redirecting to mollie.com to complete the purchase. Please wait while you are redirecting....', 'mollie-for-paymattic'),
        ], 200);
    }


    public function handlePaid($submission, $vendorTransaction)
    {
        $transaction = $this->getLastTransaction($submission->id);

        if (!$transaction || $transaction->payment_method != $this->method) {
            return;
        }

        do_action('wppayform/form_submission_activity_start', $transaction->form_id);


        if ($transaction->payment_method != 'mollie') {
            return; // this isn't a mollie standard IPN
        }

        $status = 'paid';

        $updateData = [
            'payment_note'     => maybe_serialize($vendorTransaction),
            'charge_id'        => sanitize_text_field($vendorTransaction['id']),
        ];

        // Let's make the payment as paid
        $this->markAsPaid('paid', $updateData, $transaction);
    }

    public function handleRefund($refundAmount, $submission, $vendorTransaction)
    {
        $transaction = $this->getLastTransaction($submission->id);
        $this->updateRefund($vendorTransaction['status'], $refundAmount, $transaction, $submission);
    }

    public function updateRefund($newStatus, $refundAmount, $transaction, $submission)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($submission->id);
        if ($submission->payment_status == $newStatus) {
            return;
        }

        $submissionModel->updateSubmission($submission->id, array(
            'payment_status' => $newStatus
        ));

        Transaction::where('submission_id', $submission->id)->update(array(
            'status' => $newStatus,
            'updated_at' => current_time('mysql')
        ));

        do_action('wppayform/after_payment_status_change', $submission->id, $newStatus);

        $activityContent = 'Payment status changed from <b>' . $submission->payment_status . '</b> to <b>' . $newStatus . '</b>';
        $note = wp_kses_post('Status updated by Mollie.');
        $activityContent .= '<br />Note: ' . $note;
        SubmissionActivity::createActivity(array(
            'form_id' => $submission->form_id,
            'submission_id' => $submission->id,
            'type' => 'info',
            'created_by' => 'Mollie',
            'content' => $activityContent
        ));
    }

    public function getLastTransaction($submissionId)
    {
        $transactionModel = new Transaction();
        $transaction = $transactionModel->where('submission_id', $submissionId)
            ->first();
        return $transaction;
    }

    public function markAsPaid($status, $updateData, $transaction)
    {
        $submissionModel = new Submission();
        $submission = $submissionModel->getSubmission($transaction->submission_id);

        $formDataRaw = $submission->form_data_raw;
        $formDataRaw['mollie_ipn_data'] = $updateData;
        $submissionData = array(
            'payment_status' => $status,
            'form_data_raw' => maybe_serialize($formDataRaw),
            'updated_at' => current_time('Y-m-d H:i:s')
        );

        $submissionModel->where('id', $transaction->submission_id)->update($submissionData);

        $transactionModel = new Transaction();
        $updateDate = array(
            'charge_id' => $updateData['charge_id'],
            'payment_note' => $updateData['payment_note'],
            'status' => $status,
            'updated_at' => current_time('Y-m-d H:i:s')
        );
        $transactionModel->where('id', $transaction->id)->update($updateDate);

        $transaction = $transactionModel->getTransaction($transaction->id);
        SubmissionActivity::createActivity(array(
            'form_id' => $transaction->form_id,
            'submission_id' => $transaction->submission_id,
            'type' => 'info',
            'created_by' => 'PayForm Bot',
            'content' => sprintf(__('Transaction Marked as paid and Mollie Transaction ID: %s', 'mollie-for-paymattic'), $updateDate['charge_id'])
        ));

        do_action('wppayform/form_payment_success_mollie', $submission, $transaction, $transaction->form_id, $data);
        do_action('wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $data);
    }

    public function validateSubscription($paymentItems)
    {
        wp_send_json_error(array(
            'message' => __('Mollie doesn\'t support subscriptions right now', 'mollie-for-paymattic'),
            'payment_error' => true
        ), 423);
    }
}
