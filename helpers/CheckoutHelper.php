<?php
/**
 * CheckoutHelper - manages pre-registration checkout and trial flows
 */
require_once __DIR__ . '/Database.php';

class CheckoutHelper
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Generate unique checkout token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Retrieve membership tier by name
     */
    private function getTier(string $planName)
    {
        require_once __DIR__ . '/../app/models/MembershipTier.php';
        $membershipModel = new MembershipTier();
        return $membershipModel->getTierByName($planName);
    }

    /**
     * Create a pending trial token for the specified plan
     */
    public function createTrialToken(string $planName, int $registrationWindowMinutes = 120, int $trialDays = 7)
    {
        $tier = $this->getTier($planName);
        if (!$tier) {
            throw new Exception('Invalid plan selected.');
        }

        $token = $this->generateToken();
        $expiresAt = (new DateTimeImmutable())->modify("+{$registrationWindowMinutes} minutes");

        $metadata = [
            'trial_days' => $trialDays,
            'plan_name' => $planName,
        ];

        $query = "INSERT INTO pending_checkouts             (token, plan_name, billing_cycle, is_trial, status, amount, currency, metadata, expires_at)            VALUES (:token, :plan_name, 'trial', 1, 'authorized', 0.00, 'GHS', :metadata, :expires_at)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':token' => $token,
            ':plan_name' => $planName,
            ':metadata' => json_encode($metadata),
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return [
            'token' => $token,
            'plan' => $tier,
            'registration_expires_at' => $expiresAt->format(DateTime::ATOM),
            'trial_days' => $trialDays,
        ];
    }

    /**
     * Initialize a Paystack payment for the selected plan and billing cycle
     */
    public function initializePayment(string $planName, string $billingCycle, string $email, string $currency = 'GHS')
    {
        $billingCycle = strtolower($billingCycle);
        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            throw new Exception('Invalid billing cycle.');
        }

        $tier = $this->getTier($planName);
        if (!$tier) {
            throw new Exception('Invalid plan selected.');
        }

        $amount = $billingCycle === 'monthly' ? $tier['price_monthly'] : $tier['price_annual'];
        if ($amount <= 0) {
            throw new Exception('Selected plan is not available for direct purchase.');
        }

        require_once __DIR__ . '/PaymentProcessor.php';
        $paymentProcessor = new PaymentProcessor();
        if (!$paymentProcessor->isConfigured()) {
            throw new Exception('Payment processor is not configured. Please contact support.');
        }

        $token = $this->generateToken();
        $registrationExpiry = (new DateTimeImmutable())->modify('+2 hours');

        $insert = "INSERT INTO pending_checkouts             (token, email, plan_name, billing_cycle, is_trial, status, amount, currency, metadata, expires_at)            VALUES (:token, :email, :plan_name, :billing_cycle, 0, 'pending', :amount, :currency, :metadata, :expires_at)";

        $metadata = [
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
        ];

        $stmt = $this->db->prepare($insert);
        $stmt->execute([
            ':token' => $token,
            ':email' => $email,
            ':plan_name' => $planName,
            ':billing_cycle' => $billingCycle,
            ':amount' => $amount,
            ':currency' => $currency,
            ':metadata' => json_encode($metadata),
            ':expires_at' => $registrationExpiry->format('Y-m-d H:i:s'),
        ]);

        $checkoutId = $this->db->lastInsertId();

        $paymentMetadata = array_merge($metadata, [
            'checkout_token' => $token,
            'pending_checkout_id' => $checkoutId,
        ]);

        $paymentData = $paymentProcessor->initializePayment($email, $amount, $currency, $paymentMetadata);

        // Update pending record with Paystack reference
        $update = "UPDATE pending_checkouts SET paystack_reference = :reference WHERE id = :id";
        $updateStmt = $this->db->prepare($update);
        $updateStmt->execute([
            ':reference' => $paymentData['reference'],
            ':id' => $checkoutId,
        ]);

        return [
            'token' => $token,
            'plan' => $tier,
            'authorization_url' => $paymentData['authorization_url'],
            'reference' => $paymentData['reference'],
            'access_code' => $paymentData['access_code'],
            'public_key' => $paymentProcessor->getPublicKey(),
            'registration_expires_at' => $registrationExpiry->format(DateTime::ATOM),
            'amount' => $amount,
            'currency' => $currency,
        ];
    }

    /**
     * Fetch pending checkout by token
     */
    public function getByToken(string $token, bool $forUpdate = false)
    {
        $query = "SELECT * FROM pending_checkouts WHERE token = :token" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($query);
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mark pending checkout as paid after successful Paystack verification
     */
    public function markPaymentSuccessful(string $token, string $reference, array $paymentData, float $amount, string $currency)
    {
        $query = "UPDATE pending_checkouts SET status = 'paid', paystack_reference = :reference, payment_data = :data,                    amount = :amount, currency = :currency, updated_at = NOW()                 WHERE token = :token";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':reference' => $reference,
            ':data' => json_encode($paymentData),
            ':amount' => $amount,
            ':currency' => $currency,
            ':token' => $token,
        ]);
    }

    /**
     * Mark checkout as expired
     */
    public function expireToken(string $token)
    {
        $stmt = $this->db->prepare("UPDATE pending_checkouts SET status = 'expired', updated_at = NOW() WHERE token = :token");
        $stmt->execute([':token' => $token]);
    }

    /**
     * Complete registration by attaching user to checkout and provisioning membership
     */
    public function completeRegistration(string $token, int $userId)
    {
        $this->db->beginTransaction();
        try {
            $checkout = $this->getByToken($token, true);
            if (!$checkout) {
                throw new Exception('Invalid or expired registration token.');
            }

            if (in_array($checkout['status'], ['expired', 'completed'], true)) {
                throw new Exception('This registration link has expired. Please choose a plan again.');
            }

            if ($checkout['status'] === 'pending') {
                throw new Exception('Payment has not been completed for this plan.');
            }

            $metadata = $checkout['metadata'] ? json_decode($checkout['metadata'], true) : [];
            $planName = $checkout['plan_name'];
            $tier = $this->getTier($planName);
            if (!$tier) {
                throw new Exception('Selected plan is no longer available.');
            }

            if (!empty($checkout['expires_at']) && strtotime($checkout['expires_at']) < time()) {
                $this->expireToken($token);
                throw new Exception('This registration token has expired.');
            }

            require_once __DIR__ . '/../app/models/User.php';
            $userModel = new User();
            $userModel->updateMembershipTier($userId, $tier['id']);

            if ((int)$checkout['is_trial'] === 1) {
                $subscriptionId = $this->createTrialSubscription($checkout, $userId, $tier['id']);
                $transactionId = null;
            } else {
                $subscriptionId = $this->createPaidSubscription($checkout, $userId, $tier['id']);
                $transactionId = $this->logPaidTransaction($checkout, $userId, $subscriptionId);
            }

            $update = $this->db->prepare("UPDATE pending_checkouts                 SET status = 'completed', user_id = :user_id, updated_at = NOW()                WHERE token = :token");
            $update->execute([':user_id' => $userId, ':token' => $token]);

            $this->db->commit();

            return [
                'subscription_id' => $subscriptionId,
                'transaction_id' => $transactionId,
                'plan' => $tier,
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function createTrialSubscription(array $checkout, int $userId, int $tierId)
    {
        $trialDays = 7;
        if (!empty($checkout['metadata'])) {
            $metadata = json_decode($checkout['metadata'], true);
            if (!empty($metadata['trial_days'])) {
                $trialDays = (int)$metadata['trial_days'];
            }
        }

        $query = "INSERT INTO user_subscriptions                 (user_id, tier_id, status, billing_cycle, started_at, expires_at, auto_renew, metadata)                VALUES (:user_id, :tier_id, 'trial', 'trial', NOW(), DATE_ADD(NOW(), INTERVAL :days DAY), 0, :metadata)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':tier_id' => $tierId,
            ':days' => $trialDays,
            ':metadata' => json_encode(['checkout_token' => $checkout['token']]),
        ]);

        return $this->db->lastInsertId();
    }

    private function createPaidSubscription(array $checkout, int $userId, int $tierId)
    {
        $billingCycle = $checkout['billing_cycle'];
        $interval = $billingCycle === 'annual' ? 'YEAR' : 'MONTH';

        $query = "INSERT INTO user_subscriptions                 (user_id, tier_id, status, billing_cycle, started_at, expires_at, auto_renew, metadata, last_payment_at, next_payment_at)                VALUES (:user_id, :tier_id, 'active', :billing_cycle, NOW(), DATE_ADD(NOW(), INTERVAL 1 {$interval}), 1, :metadata, NOW(), DATE_ADD(NOW(), INTERVAL 1 {$interval}))";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':tier_id' => $tierId,
            ':billing_cycle' => $billingCycle,
            ':metadata' => json_encode([
                'checkout_token' => $checkout['token'],
                'paystack_reference' => $checkout['paystack_reference']
            ]),
        ]);

        return $this->db->lastInsertId();
    }

    private function logPaidTransaction(array $checkout, int $userId, int $subscriptionId)
    {
        $paymentData = $checkout['payment_data'] ? json_decode($checkout['payment_data'], true) : [];
        $query = "INSERT INTO payment_transactions                 (user_id, subscription_id, transaction_type, payment_method, external_transaction_id, amount, currency, status, gateway_response, processed_at)                VALUES (:user_id, :subscription_id, 'subscription', 'paystack', :reference, :amount, :currency, 'success', :response, NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':subscription_id' => $subscriptionId,
            ':reference' => $checkout['paystack_reference'],
            ':amount' => $checkout['amount'],
            ':currency' => $checkout['currency'],
            ':response' => json_encode($paymentData),
        ]);

        return $this->db->lastInsertId();
    }
}
?>
