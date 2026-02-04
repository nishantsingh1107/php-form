<?php
    session_start();
    require_once __DIR__ . "/../../config/db.php";
    require_once __DIR__ . "/../../vendor/autoload.php";
    require_once __DIR__ . "/../helpers/response.php";

    use Stripe\Stripe;
    use Stripe\PaymentIntent;

    if(!isset($_SESSION['user_id'])){
        api_error("Unauthorized", 401);
    }

    $userId = (int)$_SESSION['user_id'];

    $input = json_decode(file_get_contents("php://input"), true);
    $plans = [
        'plus' => 39900,
        'pro' => 99900
    ];

    if(!$plans[$plan]){
        api_error("Invalid plan selected", 400);
    }

    $amount = $plans[$plan];
    $currency = 'inr';

    $stmt = $pdo->prepare("SELECT plan FROM users WHERE id = :uid");
    $stmt->execute([':uid' => $userId]);
    $currPlan = $stmt->fetchColumn();

    if($currPlan === $plan){
        api_error("You are already on this plan", 400);
    }

    if($currPlan === 'pro'){
        api_error("You already have the highest plan", 400);
    }

    Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY'));  
    
    try{
        $paymentIntent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'user_id' => $userId,
                'plan' => $plan
            ],
            'automatic_payment_methods' => [
                'enabled' => true
            ]
        ]);
    }catch(Exception $e){
        api_error("Unable to create payment Intent", 500);
    }


    $stmt = $pdo->prepare("INSERT INTO payments (user_id, plan, amount, currency, payment_status_id, status, created_at) VALUES (:uid, :plan, :amount, :currency, :psId, 'pending', NOW())");
    $stmt->execute([
        ":uid" => $userId,
        ":plan" => $plan,
        ":amount" => $amount,
        ":currency" => $currency,
        ":psId" => $paymentIntent->id
    ]);

    api_success([
        'client_secret' => $paymentIntent->client_secret
    ]);
?>