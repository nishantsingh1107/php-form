<?php
    require_once __DIR__ . "/../../../config/db.php";
    require_once __DIR__ . "/../../../vendor/autoload.php";

    use Stripe\Webhook;
    use Stripe\Exception\SignatureVerificationException;

    $payload = file_get_contents("php://input");
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    try{
        $event = Webhook::constructEvent(
            $payload,
            $sigHeader,
            $_ENV['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET')
        );
    }catch(UnexpectedValueException $e){
        http_response_code(400);
        exit;
    }catch(SignatureVerificationException $e){
        http_response_code(400);
        exit;
    }

    if($event->type === 'payment_intent.succeeded'){
        $intent = $event->data->object;
        $userId = (int)$intent->metadata->user_id;
        $credits = (int)$intent->metadata->credits;
        $piId = $intent->id;

        try{
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE payments SET status = 'succeeded' WHERE stripe_payment_intent_id = :pi AND status = 'pending'");
            $stmt->execute([':pi' => $piId]);
            if($stmt->rowCount() === 1){
                $pdo->prepare("UPDATE users SET post_credits = post_credits + :credits WHERE id = :uid")->execute([
                    ':credits' => $credits,
                    ':uid' => $userId
                ]);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            exit;
        }
    }

    if($event->type === 'payment_intent.payment_failed'){
        $intent = $event->data->object;
        $piId = $intent->id;
        try{
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE stripe_payment_intent_id = :pi AND status = 'pending'");
            $stmt->execute([':pi' => $piId]);
            $pdo->commit();
        }
        catch(Exception $e){
            $pdo->rollBack();
            http_response_code(500);
            exit;
        }
    }

    if ($event->type === 'payment_intent.processing') {
        $intent = $event->data->object;
        $piId = $intent->id;
        $stmt = $pdo->prepare("UPDATE payments SET status = 'pending' WHERE stripe_payment_intent_id = :pi AND status NOT IN ('succeeded', 'failed')");
        $stmt->execute([':pi' => $piId]);
    }

    if ($event->type === 'payment_intent.canceled') {
        $intent = $event->data->object;
        $piId = $intent->id;
        $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE stripe_payment_intent_id = :pi");
        $stmt->execute([':pi' => $piId]);
    }

    http_response_code(200);
?>