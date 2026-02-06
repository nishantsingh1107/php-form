<?php
    session_start();
    require_once __DIR__ . "/../../../config/db.php";
    require_once __DIR__ . "/../../../vendor/autoload.php";
    require_once __DIR__ . "/../helpers/response.php";
    require_once __DIR__ . "/../../partials/token_guard.php";

    use Stripe\Stripe;
    use Stripe\Customer;
    use Stripe\PaymentIntent;

    Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY'));
    $userId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);

    $packages = [
        20 => 19900,
        60 => 39900
    ];

    $credits = (int)($input['credits'] ?? 0);

    if(!isset($packages[$credits])){
        api_error("Invalid package", 400);
    }

    $stmt = $pdo->prepare("SELECT email, stripe_customer_id FROM users WHERE id = :uid");
    $stmt->execute([":uid" => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$user){
        api_error("User not found", 400);
    }

    $stripeCustId = $user['stripe_customer_id'];

    if(!$stripeCustId){
        $customer = Customer::create([
            'email' => $user['email'],
            'metadata' => ['user_id' => $userId]
        ]);

        $stripeCustId = $customer->id;
        $stmt = $pdo->prepare("UPDATE users SET stripe_customer_id = :cid WHERE id = :uid");
        $stmt->execute([
            ":cid" => $stripeCustId,
            ":uid" => $userId
        ]);
    }

    $paymentIntent = PaymentIntent::create([
        'amount' => $packages[$credits],
        'currency' => 'inr',
        'customer' => $stripeCustId,
        'payment_method_types' => ['card'],
        'metadata' => [
            'user_id' => $userId,
            'credits' => $credits
        ]
    ]);

    $stmt = $pdo->prepare("INSERT INTO payments (user_id, stripe_payment_intent_id, stripe_customer_id, amount, currency, credits, status) VALUES (:uid, :pid, :cid, :amt, 'inr', :credits, 'pending')");
    $stmt->execute([
        ':uid' => $userId,
        ':pid' => $paymentIntent->id,
        ':cid' => $stripeCustId,
        ':amt' => $packages[$credits]/100,
        ':credits' => $credits,
    ]);

    api_success(['client_secret' => $paymentIntent->client_secret]);
?>